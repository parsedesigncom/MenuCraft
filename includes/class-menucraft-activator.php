<?php
/**
 * Runs on plugin activation.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation tasks: schema creation and default option seeding.
 */
class MenuCraft_Activator {

	/**
	 * Activation callback.
	 *
	 * Delegates schema setup to MenuCraft_Schema::maybe_upgrade() so fresh
	 * installs and re-activations after a version bump take the same code
	 * path — this guarantees structural migrations run even when the user
	 * deactivates and re-activates the plugin between updates.
	 */
	public static function activate() {
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-schema.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-options.php';

		MenuCraft_Schema::maybe_upgrade();

		// Safety switch for uninstall — user must opt in to destroy their data.
		if ( null === MenuCraft_Options::get( 'delete_data_on_uninstall', null ) ) {
			MenuCraft_Options::update( 'delete_data_on_uninstall', '0' );
		}
	}
}
