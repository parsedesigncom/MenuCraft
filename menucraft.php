<?php
/**
 * Plugin Name:       MenuCraft
 * Plugin URI:        https://wordpress.org/plugins/menucraft/
 * Description:       Display beautiful restaurant and cafe menus for food and drinks.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Parse Design
 * Author URI:        https://parsedesign.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       menucraft
 * Domain Path:       /languages
 *
 * @package MenuCraft
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'MENUCRAFT_VERSION', '0.1.0' );
define( 'MENUCRAFT_DB_VERSION', '1.0' );
define( 'MENUCRAFT_PLUGIN_FILE', __FILE__ );
define( 'MENUCRAFT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MENUCRAFT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MENUCRAFT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MENUCRAFT_TEXT_DOMAIN', 'menucraft' );

/**
 * Activation hook.
 */
function menucraft_activate() {
	require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-activator.php';
	MenuCraft_Activator::activate();
}
register_activation_hook( __FILE__, 'menucraft_activate' );

/**
 * Deactivation hook.
 */
function menucraft_deactivate() {
	require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-deactivator.php';
	MenuCraft_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'menucraft_deactivate' );

/**
 * Load the core plugin class.
 */
require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft.php';

/**
 * Bootstrap the plugin.
 */
function menucraft_run() {
	$plugin = new MenuCraft();
	$plugin->run();
}
menucraft_run();
