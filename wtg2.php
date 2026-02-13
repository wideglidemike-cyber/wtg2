<?php
/**
 * Plugin Name: Wine Tours Grapevine 2
 * Plugin URI: https://winetoursgrapevine.com
 * Description: Complete booking system for wine tours with gift certificates, progressive slot unlock, Square invoice automation, and admin dashboard.
 * Version: 1.0.5
 * Author: Wine Tours Grapevine
 * Author URI: https://winetoursgrapevine.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wtg2
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package WTG2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'WTG2_VERSION', '1.0.5' );

/**
 * Plugin directory path.
 */
define( 'WTG2_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'WTG2_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'WTG2_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load Composer autoloader for Square SDK.
 */
if ( file_exists( WTG2_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WTG2_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The code that runs during plugin activation.
 */
function activate_wtg2() {
	require_once WTG2_PLUGIN_DIR . 'includes/class-wtg-activator.php';
	WTG_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wtg2() {
	require_once WTG2_PLUGIN_DIR . 'includes/class-wtg-deactivator.php';
	WTG_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wtg2' );
register_deactivation_hook( __FILE__, 'deactivate_wtg2' );

/**
 * The core plugin class.
 */
require_once WTG2_PLUGIN_DIR . 'includes/class-wtg-plugin.php';

/**
 * Begin execution of the plugin.
 */
function run_wtg2() {
	$plugin = WTG_Plugin::get_instance();
	$plugin->run();
}

run_wtg2();
