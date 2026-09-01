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
	 * Register the top-level admin menu and its child screens.
	 *
	 * The first submenu re-uses the parent slug to override the label that
	 * WordPress would otherwise auto-generate ("MenuCraft" → "Dashboard").
	 * All child screens render a shared placeholder view until their real
	 * UI is built.
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

		add_submenu_page(
			'menucraft',
			__( 'MenuCraft Dashboard', 'menucraft' ),
			__( 'Dashboard', 'menucraft' ),
			'manage_options',
			'menucraft',
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			'menucraft',
			__( 'Items', 'menucraft' ),
			__( 'Items', 'menucraft' ),
			'manage_options',
			'menucraft-items',
			array( $this, 'render_placeholder' )
		);

		add_submenu_page(
			'menucraft',
			__( 'Categories', 'menucraft' ),
			__( 'Categories', 'menucraft' ),
			'manage_options',
			'menucraft-categories',
			array( $this, 'render_placeholder' )
		);

		add_submenu_page(
			'menucraft',
			__( 'Tags', 'menucraft' ),
			__( 'Tags', 'menucraft' ),
			'manage_options',
			'menucraft-tags',
			array( $this, 'render_placeholder' )
		);

		add_submenu_page(
			'menucraft',
			__( 'Allergens', 'menucraft' ),
			__( 'Allergens', 'menucraft' ),
			'manage_options',
			'menucraft-allergens',
			array( $this, 'render_placeholder' )
		);

		add_submenu_page(
			'menucraft',
			__( 'Offers', 'menucraft' ),
			__( 'Offers', 'menucraft' ),
			'manage_options',
			'menucraft-offers',
			array( $this, 'render_placeholder' )
		);

		add_submenu_page(
			'menucraft',
			__( 'Options', 'menucraft' ),
			__( 'Options', 'menucraft' ),
			'manage_options',
			'menucraft-options',
			array( $this, 'render_placeholder' )
		);
	}

	/**
	 * Render the MenuCraft dashboard (parent-menu page).
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-display.php';
	}

	/**
	 * Render a shared placeholder screen for submenus without a real UI yet.
	 */
	public function render_placeholder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-placeholder.php';
	}
}
