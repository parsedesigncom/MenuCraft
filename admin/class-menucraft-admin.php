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
	 * Hook suffixes returned by add_menu_page / add_submenu_page.
	 *
	 * Populated during register_admin_menu() and consulted by enqueue and
	 * body-class filters to scope work to MenuCraft screens only.
	 *
	 * @var string[]
	 */
	private $page_hooks = array();

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
	 * Enqueue admin styles on MenuCraft screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( ! $this->is_menucraft_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'menucraft-admin',
			MENUCRAFT_PLUGIN_URL . 'assets/css/menucraft-admin.css',
			array(),
			$this->version
		);
	}

	/**
	 * Enqueue admin scripts on MenuCraft screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( ! $this->is_menucraft_screen( $hook_suffix ) ) {
			return;
		}

		// Scripts will be enqueued here in future iterations.
	}

	/**
	 * Append a body class on MenuCraft screens so CSS can scope safely.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, $this->page_hooks, true ) ) {
			$classes .= ' menucraft-admin';
		}

		return $classes;
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
		$this->page_hooks[] = add_menu_page(
			__( 'MenuCraft', 'menucraft' ),
			__( 'MenuCraft', 'menucraft' ),
			'manage_options',
			'menucraft',
			array( $this, 'render_admin_page' ),
			'dashicons-coffee'
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'MenuCraft Dashboard', 'menucraft' ),
			__( 'Dashboard', 'menucraft' ),
			'manage_options',
			'menucraft',
			array( $this, 'render_admin_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Items', 'menucraft' ),
			__( 'Items', 'menucraft' ),
			'manage_options',
			'menucraft-items',
			array( $this, 'render_placeholder' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Categories', 'menucraft' ),
			__( 'Categories', 'menucraft' ),
			'manage_options',
			'menucraft-categories',
			array( $this, 'render_placeholder' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Tags', 'menucraft' ),
			__( 'Tags', 'menucraft' ),
			'manage_options',
			'menucraft-tags',
			array( $this, 'render_placeholder' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Allergens', 'menucraft' ),
			__( 'Allergens', 'menucraft' ),
			'manage_options',
			'menucraft-allergens',
			array( $this, 'render_placeholder' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Offers', 'menucraft' ),
			__( 'Offers', 'menucraft' ),
			'manage_options',
			'menucraft-offers',
			array( $this, 'render_placeholder' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Options', 'menucraft' ),
			__( 'Options', 'menucraft' ),
			'manage_options',
			'menucraft-options',
			array( $this, 'render_placeholder' )
		);

		// add_menu_page / add_submenu_page return false when the current user
		// lacks the capability — filter those out to keep the list truthy.
		$this->page_hooks = array_values( array_filter( $this->page_hooks ) );
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

	/**
	 * True when the given hook suffix belongs to a MenuCraft screen.
	 *
	 * @param string $hook_suffix Hook suffix passed by admin_enqueue_scripts.
	 * @return bool
	 */
	private function is_menucraft_screen( $hook_suffix ) {
		return in_array( $hook_suffix, $this->page_hooks, true );
	}
}
