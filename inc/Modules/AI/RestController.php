<?php
/**
 * AI REST Controller.
 *
 * Handles the POST /wp-json/ai/v1/suggest endpoint.
 *
 * @package WriteFlowAI\Modules\AI
 */

declare( strict_types = 1 );

namespace WriteFlowAI\Modules\AI;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WriteFlowAI\Framework\Contracts\Abstracts\Abstract_REST_Controller;
use WriteFlowAI\Services\AIClient;

/**
 * Class - RestController
 */
final class RestController extends Abstract_REST_Controller {

	/**
	 * Override the default plugin namespace to produce route: ai/v1.
	 *
	 * Using a short, API-style namespace (`ai`) instead of the plugin slug keeps
	 * the endpoint URL clean and decoupled from the plugin internals, which matters
	 * once we expose this to a JS client in the Block Editor.
	 *
	 * @var string
	 */
	protected $namespace = 'ai/v';

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $rest_base = 'suggest';

	/**
	 * Maximum allowed content length in characters.
	 *
	 * Capped here to prevent oversized payloads hitting the (future) AI microservice.
	 * This value is intentionally a class constant so it appears clearly in tests.
	 */
	private const MAX_CONTENT_LENGTH = 1000;

	/**
	 * {@inheritDoc}
	 *
	 * Route: POST /wp-json/ai/v1/suggest
	 * Route: POST /wp-json/ai/v1/suggest-stream
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace . $this->version,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE, // POST only.
					'callback'            => [ $this, 'handle_suggest' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					// Declaring args here lets WP handle type coercion and
					// run our callbacks before the main callback fires.
					'args'                => $this->get_endpoint_args(),
				],
			]
		);

		register_rest_route(
			$this->namespace . $this->version,
			'/' . $this->rest_base . '-stream',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE, // POST only.
					'callback'            => [ $this, 'handle_suggest_stream' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_endpoint_args(),
				],
			]
		);
	}

	/**
	 * Returns the schema for the endpoint's accepted arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_endpoint_args(): array {
		return [
			'content' => [
				'type'              => 'string',
				'required'          => true,
				'description'       => __( 'The text content to generate an AI suggestion for.', 'writeflow-ai' ),
				// validate_callback runs first; if it returns a WP_Error WP short-circuits
				// and the sanitize_callback never runs.
				'validate_callback' => [ $this, 'validate_content' ],
				'sanitize_callback' => static function ( string $value ): string {
					// wp_unslash guards against magic-quotes edge-cases in some hosting environments.
					return sanitize_text_field( wp_unslash( $value ) );
				},
			],
		];
	}

	/**
	 * Validates the `content` parameter.
	 *
	 * WordPress already enforces `required` and `type => string` before this runs,
	 * so we only need the business-rule checks: non-empty and within length limit.
	 *
	 * @param mixed $value Raw parameter value from the request.
	 */
	public function validate_content( mixed $value ): bool|WP_Error {
		// Redundant type guard — kept for explicit safety given mixed input.
		if ( ! is_string( $value ) ) {
			return new WP_Error(
				'invalid_content_type',
				__( 'The content parameter must be a string.', 'writeflow-ai' ),
				[ 'status' => 400 ]
			);
		}

		if ( '' === trim( $value ) ) {
			return new WP_Error(
				'empty_content',
				__( 'The content parameter cannot be empty.', 'writeflow-ai' ),
				[ 'status' => 400 ]
			);
		}

		// Check against the raw (untrimmed) length to avoid bypassing the limit
		// by padding with whitespace.
		if ( mb_strlen( $value ) > self::MAX_CONTENT_LENGTH ) {
			return new WP_Error(
				'content_too_long',
				sprintf(
					/* translators: %d: maximum allowed character count. */
					__( 'The content parameter must not exceed %d characters.', 'writeflow-ai' ),
					self::MAX_CONTENT_LENGTH
				),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Handles the POST /wp-json/ai/v1/suggest request.
	 *
	 * The `content` param is already validated and sanitized by the time this
	 * callback runs — no additional cleaning is needed here.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function handle_suggest( WP_REST_Request $request ): WP_REST_Response {
		$content = $request->get_param( 'content' );

		$suggestion = $this->generate_suggestion( $content );

		// If the service returned an error, return a proper error response.
		if ( is_wp_error( $suggestion ) ) {
			$response = rest_ensure_response(
				[
					'success' => false,
					'error'   => [
						'code'    => $suggestion->get_error_code(),
						'message' => $suggestion->get_error_message(),
					],
				]
			);
			$response->set_status( 500 );
			return $response;
		}

		return rest_ensure_response(
			[
				'success' => true,
				'data'    => [
					'suggestion' => $suggestion,
				],
			]
		);
	}

	/**
	 * Handles the POST /wp-json/ai/v1/suggest-stream request.
	 *
	 * Streams AI-generated text directly to the HTTP client chunk-by-chunk.
	 *
	 * This method bypasses WordPress's normal REST response pipeline intentionally:
	 * it clears output buffers, sets streaming-appropriate headers, and calls exit
	 * after emitting all chunks. Returning a WP_REST_Response here would buffer
	 * the full response, defeating the purpose of streaming.
	 *
	 * Error protocol:
	 *   - Pre-stream errors (e.g. missing API key) are emitted as a plain-text
	 *     marker prefixed with "[WRITEFLOW_STREAM_ERROR]:" so the frontend can
	 *     detect and surface them without relying on HTTP status codes.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function handle_suggest_stream( WP_REST_Request $request ): void {
		$content = $request->get_param( 'content' );
		$client  = new AIClient();

		// Clear every output-buffering layer so chunks reach the client immediately.
		// WordPress (and some hosting stacks) enable OB by default.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Override any headers WordPress set before dispatching our callback.
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		// Instructs Nginx's proxy_buffering to pass chunks straight through.
		header( 'X-Accel-Buffering: no' );

		$result = $client->suggest_stream(
			$content,
			static function ( string $chunk ): void {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text chunks from OpenAI; escaping would corrupt the stream.
				echo $chunk;
				flush();
			}
		);

		// suggest_stream returns WP_Error for both pre-stream failures (API key
		// missing) and mid-stream failures (cURL error). Emit a detectable marker
		// so the JS client can surface a human-readable message.
		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Error message is from internal WP_Error, not user input.
			echo '[WRITEFLOW_STREAM_ERROR]:' . $result->get_error_message();
			flush();
		}

		exit;
	}

	/**
	 * Generates an AI suggestion for the given content.
	 *
	 * Delegates to the AIClient service, which handles OpenAI API communication.
	 * Designed to be swappable for other AI providers in the future.
	 *
	 * @param string $content Sanitized text content from the request.
	 *
	 * @return string|WP_Error The suggestion string on success, WP_Error on failure.
	 */
	private function generate_suggestion( string $content ): string|WP_Error {
		$client = new AIClient();
		return $client->suggest( $content );
	}

	/**
	 * Checks that the current user has permission to use this endpoint.
	 *
	 * `edit_posts` is the same capability gate used by the Block Editor itself,
	 * so any author/editor/admin can call this endpoint while unauthenticated
	 * requests are rejected cleanly.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function permissions_check( WP_REST_Request $request ): bool|WP_Error { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- Required by WP REST API contract.
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You must be logged in with editor access to use this endpoint.', 'writeflow-ai' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}
}
