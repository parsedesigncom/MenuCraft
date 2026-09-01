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
	 */
	public static function activate() {
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-schema.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-options.php';

		MenuCraft_Schema::create_tables();

		// Persist current schema version so later admin_init checks can detect drift.
		MenuCraft_Options::update( 'db_version', MENUCRAFT_DB_VERSION );

		// Safety switch for uninstall — user must opt in to destroy their data.
		if ( null === MenuCraft_Options::get( 'delete_data_on_uninstall', null ) ) {
			MenuCraft_Options::update( 'delete_data_on_uninstall', '0' );
		}
	}
}
