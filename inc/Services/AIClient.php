<?php
/**
 * OpenAI API client for AI suggestions.
 *
 * @package WriteFlowAI\Services
 */

declare( strict_types = 1 );

namespace WriteFlowAI\Services;

use WP_Error;

/**
 * Class AIClient
 *
 * Handles communication with OpenAI API for content suggestions.
 * Designed to be swappable in the future for other AI providers (Claude, etc.).
 */
final class AIClient {

	/**
	 * OpenAI API base URL.
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * OpenAI model identifier.
	 *
	 * gpt-4o-mini is cost-effective and fast for text suggestions.
	 *
	 * @var string
	 */
	private const MODEL = 'gpt-4o-mini';

	/**
	 * Temperature for response generation (0.0–2.0).
	 *
	 * 0.7 balances creativity and consistency. Lower = more deterministic.
	 *
	 * @var float
	 */
	private const TEMPERATURE = 0.7;

	/**
	 * Maximum tokens in the response.
	 *
	 * Caps response length to keep suggestions concise.
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 200;

	/**
	 * HTTP request timeout in seconds.
	 *
	 * Prevents hanging on slow API responses.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 15;

	/**
	 * API key for OpenAI authentication.
	 *
	 * @var string|null
	 */
	private ?string $api_key;

	/**
	 * Constructor.
	 *
	 * Resolves API key in order of precedence:
	 * 1. WRITEFLOW_AI_API_KEY constant (recommended for production)
	 * 2. writeflow_ai_api_key WordPress option (useful in development)
	 * 3. null (will error gracefully on first request)
	 */
	public function __construct() {
		// Try constant first (wp-config.php is more secure).
		if ( defined( 'WRITEFLOW_AI_API_KEY' ) ) {
			$this->api_key = (string) WRITEFLOW_AI_API_KEY;
			return;
		}

		// Fall back to WordPress option (for development or dynamic config).
		$option_value = get_option( 'writeflow_ai_api_key' );
		$this->api_key = is_string( $option_value ) && '' !== $option_value ? $option_value : null;
	}

	/**
	 * Generates an AI suggestion for the given content.
	 *
	 * @param string $content Plain-text content to suggest improvements for.
	 *
	 * @return string|WP_Error The suggestion string on success, WP_Error on failure.
	 */
	public function suggest( string $content ): string|WP_Error {
		// Validate that API key is available.
		$key_error = $this->validate_api_key();
		if ( is_wp_error( $key_error ) ) {
			return $key_error;
		}

		// Build the request to OpenAI.
		$request = $this->build_request( $content );

		// Make the HTTP request.
		$response = wp_remote_post(
			self::API_BASE_URL . '/chat/completions',
			$request
		);

		// Handle low-level HTTP errors (network issues, etc.).
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ai_http_error',
				sprintf(
					'Failed to reach OpenAI API: %s',
					$response->get_error_message()
				)
			);
		}

		// Parse and validate the API response.
		return $this->parse_response( $response );
	}

	/**
	 * Validates that an API key is configured.
	 *
	 * @return true|WP_Error True if valid, WP_Error if missing.
	 */
	private function validate_api_key(): true|WP_Error {
		if ( ! $this->api_key ) {
			return new WP_Error(
				'ai_missing_api_key',
				'OpenAI API key is not configured. Set WRITEFLOW_AI_API_KEY in wp-config.php or the writeflow_ai_api_key option.'
			);
		}

		return true;
	}

	/**
	 * Builds the HTTP request for the OpenAI Chat Completions API.
	 *
	 * @param string $content The user's text content.
	 *
	 * @return array<string, mixed> Request arguments compatible with wp_remote_post().
	 */
	private function build_request( string $content ): array {
		$body = [
			'model'       => self::MODEL,
			'messages'    => [
				[
					'role'    => 'system',
					'content' => 'You are a helpful writing assistant that improves clarity and quality.',
				],
				[
					'role'    => 'user',
					'content' => $content,
				],
			],
			'temperature' => self::TEMPERATURE,
			'max_tokens'  => self::MAX_TOKENS,
		];

		return [
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'      => wp_json_encode( $body ),
			'timeout'   => self::REQUEST_TIMEOUT,
			// Verify SSL in production; skip in local development for easier testing.
			'sslverify' => 'production' === wp_get_environment_type(),
		];
	}

	/**
	 * Parses the OpenAI API response and extracts the suggestion.
	 *
	 * Expected response structure:
	 * {
	 *   "choices": [
	 *     { "message": { "content": "..." } }
	 *   ]
	 * }
	 *
	 * @param array<string, mixed> $response Raw response from wp_remote_post().
	 *
	 * @return string|WP_Error The suggestion text, or WP_Error if invalid.
	 */
	private function parse_response( array $response ): string|WP_Error {
		// Get the response body as a string.
		$body = wp_remote_retrieve_body( $response );

		if ( ! is_string( $body ) || '' === $body ) {
			return new WP_Error(
				'ai_empty_response',
				'OpenAI API returned an empty response body.'
			);
		}

		// Decode JSON response.
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'ai_invalid_json',
				'OpenAI API returned invalid JSON. Response: ' . substr( $body, 0, 100 )
			);
		}

		// Check for error field in response body (OpenAI error format).
		if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) ) {
			$error_msg = $decoded['error']['message'] ?? 'Unknown error';
			return new WP_Error(
				'ai_api_error',
				sprintf( 'OpenAI API error: %s', $error_msg )
			);
		}

		// Check HTTP status code.
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			return new WP_Error(
				'ai_http_error',
				sprintf(
					'OpenAI API returned HTTP %d. Response: %s',
					$status_code,
					substr( $body, 0, 200 )
				)
			);
		}

		// Extract the suggestion from the choices array.
		if ( ! isset( $decoded['choices'] ) || ! is_array( $decoded['choices'] ) || empty( $decoded['choices'] ) ) {
			return new WP_Error(
				'ai_no_choices',
				'OpenAI API response contains no choices.'
			);
		}

		$first_choice = $decoded['choices'][0];

		if ( ! is_array( $first_choice ) || ! isset( $first_choice['message'] ) || ! is_array( $first_choice['message'] ) ) {
			return new WP_Error(
				'ai_invalid_choice_structure',
				'OpenAI API response has invalid choice structure.'
			);
		}

		$message = $first_choice['message'];

		if ( ! isset( $message['content'] ) ) {
			return new WP_Error(
				'ai_no_content',
				'OpenAI API response has no message content.'
			);
		}

		$suggestion = $message['content'];

		if ( ! is_string( $suggestion ) ) {
			return new WP_Error(
				'ai_invalid_content_type',
				'OpenAI API returned non-string content.'
			);
		}

		// Trim whitespace from the suggestion.
		return trim( $suggestion );
	}
}
