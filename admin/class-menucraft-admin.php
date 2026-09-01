<?php
/**
 * Admin-facing functionality of the plugin.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin-area hooks, screens, assets and settings.
 */
class MenuCraft_Admin {

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
	 * Enqueue admin styles.
	 */
	public function enqueue_styles() {
		// Styles will be enqueued here in future iterations.
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts() {
		// Scripts will be enqueued here in future iterations.
	}

	/**
	 * Register the top-level admin menu. Submenu items will be added later.
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'MenuCraft', 'menucraft' ),
			__( 'MenuCraft', 'menucraft' ),
			'manage_options',
			'menucraft',
			array( $this, 'render_admin_page' ),
			'dashicons-coffee'
		);
	}

	/**
	 * Render the main plugin admin page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-display.php';
	}
}
