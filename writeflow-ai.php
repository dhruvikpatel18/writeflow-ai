<?php
/**
 * WriteFlow AI
 *
 * @package           rtCamp/WriteFlowAI
 * @author            rtCamp
 * @copyright         2026 rtCamp
 * @license           GPL-2.0-or-later
 *
 * Plugin Name:       WriteFlow AI
 * Plugin URI:        https://github.com/dhruvikpatel18/writeflow-ai
 * Description:       AI-powered writing assistant for Gutenberg with inline suggestions, summarization, and SEO optimization.
 * Version:           0.0.1
 * Author:            Dhruvik Malaviya
 * Author URI:        https://profiles.wordpress.org/dhruvik18/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       writeflow-ai
 * Domain Path:       /languages
 * Requires PHP:      8.2
 * Requires at least: 6.9
 * Tested up to:      6.9
 */

declare( strict_types = 1 );

namespace WriteFlowAI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * File path to the plugin's main file.
	 */
	define( 'WRITEFLOW_AI_FILE', __FILE__ );

	/**
	 * Version of the plugin.
	 */
	define( 'WRITEFLOW_AI_VERSION', '0.0.1' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'WRITEFLOW_AI_PATH', plugin_dir_path( WRITEFLOW_AI_FILE ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'WRITEFLOW_AI_URL', plugin_dir_url( WRITEFLOW_AI_FILE ) );
}

constants();

// If autoloader fails, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! class_exists( 'WriteFlowAI\Autoloader' ) || ! \WriteFlowAI\Autoloader::autoload() ) {
	return;
}

// Load the main plugin class.
if ( class_exists( 'WriteFlowAI\Main' ) ) {
	\WriteFlowAI\Main::get_instance();
}
