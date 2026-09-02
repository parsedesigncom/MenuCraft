<?php
/**
 * Public-facing functionality of the plugin.
 *
 * Registers the [menucraft] shortcode, its conditional CSS/JS enqueue,
 * and the template-loader that lets themes override output by dropping a
 * file into `theme/menucraft/`.
 *
 * All shortcode HTML flows through documented filter/action hooks so
 * developers can restyle or extend the output without editing plugin
 * files.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public / front-end controller.
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
	 * Register the shortcode. Called from the main loader.
	 */
	public function register_shortcodes() {
		add_shortcode( 'menucraft', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register (do NOT enqueue) the public assets so the shortcode can
	 * enqueue them lazily when it actually runs. Following WP.org's
	 * "only load on pages that need it" performance guideline.
	 */
	public function register_assets() {
		wp_register_style(
			$this->plugin_name . '-public',
			MENUCRAFT_PLUGIN_URL . 'assets/css/menucraft-public.css',
			array(),
			$this->version
		);

		wp_register_script(
			$this->plugin_name . '-public',
			MENUCRAFT_PLUGIN_URL . 'assets/js/menucraft-public.js',
			array(),
			$this->version,
			true
		);
	}

	/**
	 * Locate a template, preferring `theme/menucraft/<name>.php` if it
	 * exists (WP-standard override pattern), otherwise falling back to
	 * `plugin/templates/<name>.php`.
	 *
	 * @param string $name Template basename without extension.
	 * @return string Absolute path.
	 */
	public static function locate_template( $name ) {
		$name  = sanitize_file_name( $name );
		$theme = locate_template( array( 'menucraft/' . $name . '.php' ) );
		if ( $theme ) {
			return $theme;
		}
		return MENUCRAFT_PLUGIN_DIR . 'templates/' . $name . '.php';
	}

	/**
	 * Per-page instance counter, so multiple shortcodes on the same page
	 * each get a unique HTML id used to scope inline grid CSS.
	 *
	 * @var int
	 */
	private static $instance_counter = 0;

	/**
	 * Shortcode entry point.
	 *
	 * Supported attributes (all optional — omitted values keep the default
	 * design):
	 *   image=left|right|top       Image placement per item. Default: left.
	 *   variants=inline|modal      Show variants inline in the card or hide
	 *                              them and reveal in the item modal. Default:
	 *                              inline.
	 *   categories_title="…"       Override the Categories filter label.
	 *   tags_title="…"             Override the Tags filter label.
	 *   allergens_title="…"        Override the Allergens filter label.
	 *   columns="720__1 920__2 …"  Enable grid layout. Space-separated
	 *                              tokens; each token is
	 *                              "<max-width-px>__<columns>". Below the
	 *                              smallest breakpoint the smallest col count
	 *                              wins; above the largest breakpoint the
	 *                              largest col count is the base. Omitting
	 *                              this attribute leaves the default
	 *                              single-column rows layout untouched.
	 *   class="…"                  Extra CSS class(es) appended to the outer
	 *                              wrapper so a frontend developer can style
	 *                              this shortcode instance from their theme
	 *                              CSS without touching the plugin. Multiple
	 *                              classes may be space-separated; each is
	 *                              sanitized with sanitize_html_class().
	 *
	 * @param array<string,mixed>|string $atts    Raw shortcode attributes.
	 * @param string|null                $content Enclosed content (unused for now).
	 * @return string Rendered HTML.
	 */
	public function render_shortcode( $atts, $content = null ) {
		unset( $content );

		$atts = shortcode_atts(
			array(
				'image'            => 'left',
				'variants'         => 'inline',
				'categories_title' => '',
				'tags_title'       => '',
				'allergens_title'  => '',
				'columns'          => '',
				'class'            => '',
			),
			is_array( $atts ) ? $atts : array(),
			'menucraft'
		);

		// Enqueue at render time — safe mid-content because WP prints the
		// tags in the footer.
		wp_enqueue_style( $this->plugin_name . '-public' );
		wp_enqueue_script( $this->plugin_name . '-public' );

		self::$instance_counter++;
		$instance_id = 'menucraft-menu-' . self::$instance_counter;

		$config = self::normalise_atts( $atts, $instance_id );

		$items      = $this->collect_items();
		$categories = $this->collect_categories( $items );
		$tags       = $this->collect_tags( $items );
		$allergens  = $this->collect_allergens( $items );

		$context = array(
			'atts'       => $atts,
			'config'     => $config,
			'items'      => $items,
			'categories' => $categories,
			'tags'       => $tags,
			'allergens'  => $allergens,
		);

		/**
		 * Filter: fully rewrite the shortcode output.
		 *
		 * Returning a non-empty string short-circuits the built-in
		 * template — developers replacing the whole render.
		 *
		 * @param string              $html    Empty by default.
		 * @param array<string,mixed> $context items, categories, tags, allergens, atts.
		 */
		$override = apply_filters( 'menucraft_shortcode_html', '', $context );
		if ( is_string( $override ) && '' !== $override ) {
			return $override;
		}

		$template = self::locate_template( 'shortcode' );

		ob_start();
		/**
		 * Action: fires before the shortcode template is included.
		 *
		 * @param array<string,mixed> $context Full render context.
		 */
		do_action( 'menucraft_before_shortcode', $context );

		// Expose $context to the template via a symbol table.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $context, EXTR_SKIP );
		include $template;

		/**
		 * Action: fires after the shortcode template is included.
		 *
		 * @param array<string,mixed> $context Full render context.
		 */
		do_action( 'menucraft_after_shortcode', $context );

		return (string) ob_get_clean();
	}

	/**
	 * Validate + normalise shortcode attributes into a config bag the
	 * templates can consume without re-checking every value.
	 *
	 * @param array<string,mixed> $atts        Raw sanitized shortcode atts.
	 * @param string              $instance_id Unique HTML id for this shortcode call.
	 * @return array<string,mixed>
	 */
	private static function normalise_atts( array $atts, $instance_id ) {
		$image_choices    = array( 'left', 'right', 'top' );
		$variant_choices  = array( 'inline', 'modal' );

		$image_pos    = in_array( $atts['image'], $image_choices, true ) ? $atts['image'] : 'left';
		$variants_mode = in_array( $atts['variants'], $variant_choices, true ) ? $atts['variants'] : 'inline';

		$breakpoints = self::parse_columns_spec( (string) $atts['columns'] );
		$grid_css    = ! empty( $breakpoints ) ? self::build_grid_css( $instance_id, $breakpoints ) : '';

		$titles = array(
			'categories' => trim( (string) $atts['categories_title'] ),
			'tags'       => trim( (string) $atts['tags_title'] ),
			'allergens'  => trim( (string) $atts['allergens_title'] ),
		);
		if ( '' === $titles['categories'] ) { $titles['categories'] = __( 'Categories', 'menucraft' ); }
		if ( '' === $titles['tags'] )       { $titles['tags']       = __( 'Tags', 'menucraft' ); }
		if ( '' === $titles['allergens'] )  { $titles['allergens']  = __( 'Allergens', 'menucraft' ); }

		// Split, sanitize, dedupe: an empty result is fine — the template
		// simply omits the class token.
		$custom_class = '';
		if ( '' !== trim( (string) $atts['class'] ) ) {
			$parts = preg_split( '/\s+/', trim( (string) $atts['class'] ) );
			$clean = array();
			foreach ( $parts as $p ) {
				$s = sanitize_html_class( $p );
				if ( '' !== $s ) {
					$clean[ $s ] = true;
				}
			}
			$custom_class = implode( ' ', array_keys( $clean ) );
		}

		return array(
			'instance_id'   => $instance_id,
			'image_pos'     => $image_pos,
			'variants_mode' => $variants_mode,
			'titles'        => $titles,
			'grid_enabled'  => ! empty( $breakpoints ),
			'grid_css'      => $grid_css,
			'custom_class'  => $custom_class,
		);
	}

	/**
	 * Parse a "720__1 920__2 1200__3" style column spec into an ascending
	 * sorted list of breakpoints. Accepts one or two underscores between
	 * the width and the column count so a typo like "1200_3" still works.
	 *
	 * @param string $spec Raw shortcode value.
	 * @return array<int,array{max:int,cols:int}> Sorted ascending by max.
	 */
	private static function parse_columns_spec( $spec ) {
		$spec = trim( $spec );
		if ( '' === $spec ) {
			return array();
		}
		$out = array();
		foreach ( preg_split( '/\s+/', $spec ) as $token ) {
			if ( preg_match( '/^(\d+)_+(\d+)$/', $token, $m ) ) {
				$max  = (int) $m[1];
				$cols = max( 1, (int) $m[2] );
				if ( $max > 0 ) {
					$out[] = array( 'max' => $max, 'cols' => $cols );
				}
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['max'] - $b['max'];
			}
		);
		return $out;
	}

	/**
	 * Emit the inline grid CSS scoped to a single shortcode instance.
	 *
	 * Base column count comes from the largest breakpoint (screens above
	 * the biggest max stay on that count). Each smaller breakpoint is
	 * emitted as a max-width override, in descending order so the smallest
	 * width — matching the smallest screen — wins the cascade.
	 *
	 * @param string                                  $instance_id Unique id.
	 * @param array<int,array{max:int,cols:int}>      $breakpoints Sorted ascending.
	 * @return string
	 */
	private static function build_grid_css( $instance_id, array $breakpoints ) {
		if ( empty( $breakpoints ) ) {
			return '';
		}
		$id = '#' . $instance_id . ' .menucraft-items';

		$base = end( $breakpoints );
		reset( $breakpoints );

		$css  = $id . '{display:grid;gap:16px;grid-template-columns:repeat(' . (int) $base['cols'] . ',minmax(0,1fr));}';

		// Emit smaller breakpoints in DESCENDING order so the narrower rule
		// appears last in the cascade and wins on small screens where both
		// match. The base (largest) breakpoint is skipped because the
		// unconditional rule above already covers it.
		$without_base = array_slice( $breakpoints, 0, count( $breakpoints ) - 1 );
		usort( $without_base, function ( $a, $b ) { return $b['max'] - $a['max']; } );

		foreach ( $without_base as $bp ) {
			$css .= '@media (max-width:' . (int) $bp['max'] . 'px){' . $id . '{grid-template-columns:repeat(' . (int) $bp['cols'] . ',minmax(0,1fr));}}';
		}

		return $css;
	}

	/**
	 * Fetch the items to render. Only active items are considered, and
	 * developers can post-filter or replace the list wholesale.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_items() {
		$items = MenuCraft_Item_Repository::all();
		$items = array_values(
			array_filter(
				$items,
				function ( $item ) {
					return ! empty( $item['is_active'] );
				}
			)
		);

		/**
		 * Filter: modify or replace the items list before rendering.
		 *
		 * @param array<int,array<string,mixed>> $items Hydrated items.
		 */
		$items = apply_filters( 'menucraft_shortcode_items', $items );

		return is_array( $items ) ? $items : array();
	}

	/**
	 * Fetch categories that are referenced by at least one visible item,
	 * so the filter chips never contain "dead" options.
	 *
	 * @param array<int,array<string,mixed>> $items Visible items.
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_categories( array $items ) {
		$used = array();
		foreach ( $items as $it ) {
			foreach ( (array) ( isset( $it['category_ids'] ) ? $it['category_ids'] : array() ) as $id ) {
				$used[ (int) $id ] = true;
			}
		}

		$all  = MenuCraft_Category_Repository::all();
		$rows = array_values(
			array_filter(
				$all,
				function ( $row ) use ( $used ) {
					return ! empty( $row['is_active'] ) && isset( $used[ (int) $row['id'] ] );
				}
			)
		);

		/**
		 * Filter: modify the category set shown in the filter bar.
		 *
		 * @param array<int,array<string,mixed>> $rows  Filtered categories.
		 * @param array<int,array<string,mixed>> $items Visible items.
		 */
		return apply_filters( 'menucraft_shortcode_categories', $rows, $items );
	}

	/**
	 * Same idea as collect_categories() for tags.
	 *
	 * @param array<int,array<string,mixed>> $items Visible items.
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_tags( array $items ) {
		$used = array();
		foreach ( $items as $it ) {
			foreach ( (array) ( isset( $it['tag_ids'] ) ? $it['tag_ids'] : array() ) as $id ) {
				$used[ (int) $id ] = true;
			}
		}

		$all  = MenuCraft_Tag_Repository::all();
		$rows = array_values(
			array_filter(
				$all,
				function ( $row ) use ( $used ) {
					return ! empty( $row['is_active'] ) && isset( $used[ (int) $row['id'] ] );
				}
			)
		);

		/**
		 * Filter: modify the tag set shown in the filter bar.
		 *
		 * @param array<int,array<string,mixed>> $rows  Filtered tags.
		 * @param array<int,array<string,mixed>> $items Visible items.
		 */
		return apply_filters( 'menucraft_shortcode_tags', $rows, $items );
	}

	/**
	 * All allergens referenced by any visible item, keyed by id, so
	 * item-templates can render short codes without another DB roundtrip.
	 *
	 * @param array<int,array<string,mixed>> $items Visible items.
	 * @return array<int,array<string,mixed>> id => allergen row.
	 */
	private function collect_allergens( array $items ) {
		$used = array();
		foreach ( $items as $it ) {
			foreach ( (array) ( isset( $it['allergen_ids'] ) ? $it['allergen_ids'] : array() ) as $id ) {
				$used[ (int) $id ] = true;
			}
		}

		$out = array();
		foreach ( MenuCraft_Allergen_Repository::all() as $row ) {
			if ( ! empty( $row['is_active'] ) && isset( $used[ (int) $row['id'] ] ) ) {
				$out[ (int) $row['id'] ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Render one item to HTML. Used from within the shortcode template
	 * and exposed as a public method so developers can call it from a
	 * theme override.
	 *
	 * @param array<string,mixed>            $item      Hydrated item.
	 * @param array<int,array<string,mixed>> $allergens Allergen map keyed by id.
	 * @param array<int,array<string,mixed>> $tags      Tag map keyed by id.
	 * @param array<string,mixed>            $config    Normalised shortcode config
	 *                                                  (image_pos, variants_mode …).
	 * @return string
	 */
	public static function render_item( array $item, array $allergens = array(), array $tags = array(), array $config = array() ) {
		$template = self::locate_template( 'shortcode-item' );
		// Fill in defaults so theme overrides that call render_item()
		// directly don't have to know about every config key.
		$config = array_merge(
			array(
				'image_pos'     => 'left',
				'variants_mode' => 'inline',
			),
			$config
		);

		ob_start();
		/**
		 * Action: fires before a single item's HTML.
		 *
		 * @param array<string,mixed> $item   Hydrated item.
		 * @param array<string,mixed> $config Normalised config.
		 */
		do_action( 'menucraft_before_item', $item, $config );

		include $template;

		/**
		 * Action: fires after a single item's HTML.
		 *
		 * @param array<string,mixed> $item   Hydrated item.
		 * @param array<string,mixed> $config Normalised config.
		 */
		do_action( 'menucraft_after_item', $item, $config );

		$html = (string) ob_get_clean();

		/**
		 * Filter: replace or wrap a single item's HTML.
		 *
		 * @param string                         $html      Rendered HTML.
		 * @param array<string,mixed>            $item      Item.
		 * @param array<int,array<string,mixed>> $allergens Allergen map.
		 * @param array<int,array<string,mixed>> $tags      Tag map.
		 * @param array<string,mixed>            $config    Normalised config.
		 */
		return (string) apply_filters( 'menucraft_shortcode_item_html', $html, $item, $allergens, $tags, $config );
	}

	/**
	 * Format a price with the configured currency symbol. Kept static so
	 * templates can call it without instantiating anything.
	 *
	 * @param float|int|string $value Price value.
	 * @return string
	 */
	public static function format_price( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		$currency = (string) MenuCraft_Options::get( 'currency', '€' );
		return number_format_i18n( (float) $value, 2 ) . ' ' . $currency;
	}
}
