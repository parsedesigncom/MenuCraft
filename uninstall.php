<?php
/**
 * Fires when the plugin is uninstalled.
 *
 * @package MenuCraft
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Uninstall logic (options, custom tables, transients) will be added in future iterations.
