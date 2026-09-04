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

		// Loads wp.media (used by our vanilla picker to open the native library UI).
		wp_enqueue_media();

		wp_enqueue_script(
			'menucraft-admin',
			MENUCRAFT_PLUGIN_URL . 'assets/js/menucraft-admin.js',
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'menucraft-admin',
			'menucraftAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'menucraft/v1/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'currency'  => (string) MenuCraft_Options::get( 'currency', '€' ),
				'i18n'      => array(
					'saving'          => __( 'Saving…', 'menucraft' ),
					'saveSuccess'     => __( 'Saved.', 'menucraft' ),
					'saveError'       => __( 'Save failed.', 'menucraft' ),
					'updateSuccess'   => __( 'Updated.', 'menucraft' ),
					'deleteSuccess'   => __( 'Deleted.', 'menucraft' ),
					'deleteError'     => __( 'Delete failed.', 'menucraft' ),
					'listError'       => __( 'Could not load list.', 'menucraft' ),
					'empty'           => __( 'No entries yet.', 'menucraft' ),
					'active'          => __( 'Active', 'menucraft' ),
					'inactive'        => __( 'Inactive', 'menucraft' ),
					'edit'            => __( 'Edit', 'menucraft' ),
					'delete'          => __( 'Delete', 'menucraft' ),
					'mediaTitle'      => __( 'Select Image', 'menucraft' ),
					'mediaButton'     => __( 'Use this image', 'menucraft' ),
					'mediaEmpty'      => __( 'No image selected', 'menucraft' ),
					'mediaUnavail'    => __( 'Media library unavailable.', 'menucraft' ),
					'from'            => __( 'from', 'menucraft' ),
					'noPrice'         => __( 'no price', 'menucraft' ),
					'variantsNone'    => __( 'None', 'menucraft' ),
					'variantsCount'   => __( '%d variant(s)', 'menucraft' ),
					'variantLabel'    => __( 'Label', 'menucraft' ),
					'variantPrice'    => __( 'Price', 'menucraft' ),
					'variantRemove'   => __( 'Remove', 'menucraft' ),
					'variantLabelHint' => __( 'e.g. Small, Medium, Large', 'menucraft' ),
					'bulkApplied'     => __( 'Applied to %d item(s).', 'menucraft' ),
					'bulkNoOps'       => __( 'Nothing to apply — pick at least one operation.', 'menucraft' ),
					'bulkNoSelection' => __( 'Select at least one item first.', 'menucraft' ),
					'filtersActive'   => __( '%d filter(s) active', 'menucraft' ),
					'noMatches'       => __( 'No items match the current filters.', 'menucraft' ),
					'offerLinesNone'  => __( 'None', 'menucraft' ),
					'offerLinesCount' => __( '%d line(s)', 'menucraft' ),
					'offerQuantity'   => __( 'Qty', 'menucraft' ),
					'offerPickVariant' => __( '— pick variant —', 'menucraft' ),
					'offerNoVariant'  => __( '(no variant)', 'menucraft' ),
					'offerRemoveLine' => __( 'Remove line', 'menucraft' ),
					'offerAlways'     => __( 'Always', 'menucraft' ),
					'offerFrom'       => __( 'From %s', 'menucraft' ),
					'offerUntil'      => __( 'Until %s', 'menucraft' ),
					'offerBetween'    => __( '%1$s – %2$s', 'menucraft' ),
					'offerCurrent'    => __( 'Currently valid', 'menucraft' ),
					'offerUpcoming'   => __( 'Upcoming', 'menucraft' ),
					'offerExpired'    => __( 'Expired', 'menucraft' ),
					'defaultCategory' => __( 'Default', 'menucraft' ),
					'defaultCategoryTitle' => __( 'Pre-selected in the frontend filter', 'menucraft' ),
				),
			)
		);
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
			array( $this, 'render_items_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Categories', 'menucraft' ),
			__( 'Categories', 'menucraft' ),
			'manage_options',
			'menucraft-categories',
			array( $this, 'render_categories_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Tags', 'menucraft' ),
			__( 'Tags', 'menucraft' ),
			'manage_options',
			'menucraft-tags',
			array( $this, 'render_tags_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Allergens', 'menucraft' ),
			__( 'Allergens', 'menucraft' ),
			'manage_options',
			'menucraft-allergens',
			array( $this, 'render_allergens_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Offers', 'menucraft' ),
			__( 'Offers', 'menucraft' ),
			'manage_options',
			'menucraft-offers',
			array( $this, 'render_offers_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'Options', 'menucraft' ),
			__( 'Options', 'menucraft' ),
			'manage_options',
			'menucraft-options',
			array( $this, 'render_options_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'MenuCraft Help & Documentation', 'menucraft' ),
			__( 'Help & Docs', 'menucraft' ),
			'manage_options',
			'menucraft-help',
			array( $this, 'render_help_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'menucraft',
			__( 'About MenuCraft', 'menucraft' ),
			__( 'About', 'menucraft' ),
			'manage_options',
			'menucraft-about',
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
	 * Render the Categories admin screen.
	 */
	public function render_categories_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-categories.php';
	}

	/**
	 * Render the Tags admin screen.
	 */
	public function render_tags_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-tags.php';
	}

	/**
	 * Render the Allergens admin screen.
	 */
	public function render_allergens_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-allergens.php';
	}

	/**
	 * Render the Items admin screen.
	 */
	public function render_items_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-items.php';
	}

	/**
	 * Render the Offers admin screen.
	 */
	public function render_offers_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-offers.php';
	}

	/**
	 * Render the Options admin screen.
	 */
	public function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-options.php';
	}

	/**
	 * Render the Help & Docs admin screen.
	 */
	public function render_help_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require MENUCRAFT_PLUGIN_DIR . 'admin/partials/menucraft-admin-help.php';
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
