<?php
/**
 * Loads translations for the plugin.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Responsible for loading the plugin text domain.
 */
class MenuCraft_I18n {

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			MENUCRAFT_TEXT_DOMAIN,
			false,
			dirname( MENUCRAFT_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}
