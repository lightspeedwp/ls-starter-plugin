<?php
/**
 * Plugin Name:       {{PLUGIN_NAME}}
 * Plugin URI:        {{PLUGIN_URI}}
 * Description:       {{PLUGIN_DESCRIPTION}}
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            {{AUTHOR_NAME}}
 * Author URI:        {{AUTHOR_URI}}
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       {{TEXT_DOMAIN}}
 * Domain Path:       /languages
 *
 * @package {{NAMESPACE}}
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'LS_STARTER_VERSION', '0.2.0' );
define( 'LS_STARTER_PLUGIN_FILE', __FILE__ );
define( 'LS_STARTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LS_STARTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the plugin text domain for translation.
 */
function ls_starter_load_textdomain() {
	load_plugin_textdomain(
		'{{TEXT_DOMAIN}}',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'ls_starter_load_textdomain' );

/**
 * Load optional plugin includes.
 * Add your include files in inc/ and require them here when ready.
 */
function ls_starter_init() {
	// Native asset cachebusting: replaces the Cachebuster plugin, see inc/class-cachebusting.php.
	require_once LS_STARTER_PLUGIN_DIR . 'inc/class-cachebusting.php';

	// Notice for plugins whose function this codebase already covers natively.
	require_once LS_STARTER_PLUGIN_DIR . 'inc/class-redundant-plugins-notice.php';
}
add_action( 'plugins_loaded', 'ls_starter_init' );
