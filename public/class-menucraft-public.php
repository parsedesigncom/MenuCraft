<?php
/**
 * Public-facing functionality of the plugin.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles front-end hooks, shortcodes, blocks and assets.
 */
class MenuCraft_Public {

	/**
	 * Plugin text domain / handle.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin handle used for asset identifiers.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue public styles.
	 */
	public function enqueue_styles() {
		// Styles will be enqueued here in future iterations.
	}

	/**
	 * Enqueue public scripts.
	 */
	public function enqueue_scripts() {
		// Scripts will be enqueued here in future iterations.
	}
}
