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

		// TODO: Replace with actual AI microservice call in a future iteration.
		// Keeping this stub isolated here makes it a single swap point later.
		$suggestion = $this->generate_suggestion( $content );

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
	 * Generates an AI suggestion for the given content.
	 *
	 * Currently returns a static placeholder. This method will be the single
	 * integration point replaced when the real AI microservice is wired up.
	 *
	 * @param string $content Sanitized text content from the request.
	 */
	private function generate_suggestion( string $content ): string { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- $content used once real AI is wired.
		return 'This is an AI suggestion based on your content.';
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
