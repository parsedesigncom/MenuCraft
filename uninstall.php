<?php
/**
 * Fires when the plugin is uninstalled.
 *
 * Only removes the plugin's data set when the administrator explicitly
 * opted in via the "delete_data_on_uninstall" option. Default is to keep
 * all tables intact so accidental removal does not destroy content.
 *
 * @package MenuCraft
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-menucraft-schema.php';
require_once __DIR__ . '/includes/class-menucraft-options.php';

$delete = MenuCraft_Options::get( 'delete_data_on_uninstall', '0' );

if ( '1' === (string) $delete ) {
	MenuCraft_Schema::drop_tables();
}
