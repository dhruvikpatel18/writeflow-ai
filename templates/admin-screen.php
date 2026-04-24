<?php
/**
 * Template for the WriteFlow AI Settings screen.
 *
 * @package writeflow-ai
 */

declare( strict_types = 1 );

use WriteFlowAI\Modules\Settings\Settings;

$api_key = get_option( Settings::API_KEY_OPTION );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'WriteFlow AI Settings', 'writeflow-ai' ); ?></h1>

	<p>
		<?php esc_html_e( 'Configure your OpenAI API key to enable AI-powered suggestions in the Gutenberg editor.', 'writeflow-ai' ); ?>
	</p>

	<p>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: OpenAI API keys page URL */
				__( 'Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>', 'writeflow-ai' ),
				'https://platform.openai.com/api/keys'
			)
		);
		?>
	</p>

	<form action="options.php" method="post">
		<?php
		settings_fields( Settings::SETTING_GROUP );
		settings_errors( Settings::API_KEY_OPTION );
		?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( Settings::API_KEY_OPTION ); ?>">
						<?php esc_html_e( 'OpenAI API Key', 'writeflow-ai' ); ?>
					</label>
				</th>
				<td>
					<input
						type="password"
						id="<?php echo esc_attr( Settings::API_KEY_OPTION ); ?>"
						name="<?php echo esc_attr( Settings::API_KEY_OPTION ); ?>"
						value="<?php echo esc_attr( $api_key ); ?>"
						class="regular-text"
						placeholder="sk-..."
					/>
					<p class="description">
						<?php esc_html_e( 'Your API key is stored securely and never shared except with OpenAI.', 'writeflow-ai' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
