<?php
/**
 * The core plugin class.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. Loads dependencies and wires hooks for admin and public areas.
 */
class MenuCraft {

	/**
	 * Loader instance responsible for registering hooks.
	 *
	 * @var MenuCraft_Loader
	 */
	protected $loader;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-loader.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-i18n.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-schema.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-options.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-slug.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-category-repository.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-tag-repository.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-allergen-repository.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-item-repository.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-offer-repository.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-rest.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-block.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-offers-block.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'includes/class-menucraft-group-block.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'admin/class-menucraft-admin.php';
		require_once MENUCRAFT_PLUGIN_DIR . 'public/class-menucraft-public.php';

		$this->loader = new MenuCraft_Loader();
	}

	/**
	 * Register text domain for internationalization.
	 */
	private function set_locale() {
		$i18n = new MenuCraft_I18n();
		$this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register admin-area hooks.
	 */
	private function define_admin_hooks() {
		$admin = new MenuCraft_Admin( MENUCRAFT_TEXT_DOMAIN, MENUCRAFT_VERSION );
		$this->loader->add_action( 'admin_menu', $admin, 'register_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_filter( 'admin_body_class', $admin, 'admin_body_class' );
		$this->loader->add_action( 'admin_init', 'MenuCraft_Schema', 'maybe_upgrade' );
		$this->loader->add_action( 'rest_api_init', 'MenuCraft_REST', 'register_routes' );
	}

	/**
	 * Register public-facing hooks.
	 *
	 * Public assets are registered on `init` (not `wp_enqueue_scripts`) so
	 * their handles exist in both frontend and block-editor contexts —
	 * blocks reference them via block.json's `style` / `viewScript` fields
	 * and WP looks them up at block-render time.
	 */
	private function define_public_hooks() {
		$public = new MenuCraft_Public( MENUCRAFT_TEXT_DOMAIN, MENUCRAFT_VERSION );
		$this->loader->add_action( 'init', $public, 'register_shortcodes' );
		$this->loader->add_action( 'init', $public, 'register_assets' );
		$this->loader->add_action( 'init', 'MenuCraft_Block', 'register' );
		$this->loader->add_action( 'init', 'MenuCraft_Offers_Block', 'register' );
		$this->loader->add_action( 'init', 'MenuCraft_Group_Block', 'register' );
	}

	/**
	 * Run the plugin by executing the loader.
	 */
	public function run() {
		$this->loader->run();
	}
}
