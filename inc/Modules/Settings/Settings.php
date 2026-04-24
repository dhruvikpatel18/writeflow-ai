<?php
/**
 * Registers the plugin's admin settings.
 *
 * @package WriteFlowAI\Modules\Settings
 */

declare(strict_types = 1);

namespace WriteFlowAI\Modules\Settings;

use WriteFlowAI\Framework\Contracts\Interfaces\Registrable;

/**
 * Class - Settings
 */
final class Settings implements Registrable {
	/**
	 * The setting prefix.
	 */
	private const SETTING_PREFIX = 'writeflow_ai_';

	/**
	 * The setting group.
	 */
	public const SETTING_GROUP = self::SETTING_PREFIX . 'settings';

	/**
	 * Key for the API key option.
	 */
	public const API_KEY_OPTION = self::SETTING_PREFIX . 'api_key';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		// Register the OpenAI API key setting.
		register_setting(
			self::SETTING_GROUP,
			self::API_KEY_OPTION,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_api_key' ],
				'show_in_rest'      => false,
				'description'       => __( 'OpenAI API key for WriteFlow AI', 'writeflow-ai' ),
			]
		);
	}

	/**
	 * Sanitizes the API key input.
	 *
	 * @param mixed $value The raw input value.
	 *
	 * @return string Sanitized API key, or empty string if invalid.
	 */
	public function sanitize_api_key( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$trimmed = trim( $value );

		if ( '' === $trimmed || ! str_starts_with( $trimmed, 'sk-' ) ) {
			add_settings_error(
				self::API_KEY_OPTION,
				'invalid_api_key',
				__( 'OpenAI API key must start with "sk-".', 'writeflow-ai' ),
				'error'
			);
			return '';
		}

		return $trimmed;
	}
}
