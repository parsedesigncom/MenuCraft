<?php
/**
 * Gutenberg block registration.
 *
 * `menucraft/menu` is a dynamic block: attributes are saved as JSON in
 * post_content and the HTML is produced at render time by wrapping the
 * `[menucraft]` shortcode with an outer div that carries per-instance
 * colors, alignment and font-scale — everything block-only. The
 * shortcode itself never sees these attributes.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block controller.
 */
class MenuCraft_Block {

	/**
	 * Color-slot registry.
	 *
	 * Each entry: [ block-attribute name, CSS selector (relative to the
	 * outer wrapper; empty = the wrapper itself), CSS property ].
	 *
	 * When adding a slot, mirror the entry in blocks/menu/index.js so the
	 * sidebar exposes it.
	 *
	 * @return array<int,array{0:string,1:string,2:string}>
	 */
	private static function color_slots() {
		return array(
			// Container.
			array( 'bgColor',             '',                                       'background-color' ),
			array( 'textColor',           '',                                       'color' ),
			// Filter.
			array( 'filterBarBg',         ' .menucraft-filter-bar',                 'background-color' ),
			array( 'filterBarBorder',     ' .menucraft-filter-bar',                 'border-color' ),
			array( 'filterLabelColor',    ' .menucraft-filter-label',               'color' ),
			array( 'chipBg',              ' .menucraft-filter-chip',                'background-color' ),
			array( 'chipText',            ' .menucraft-filter-chip',                'color' ),
			array( 'chipBorder',          ' .menucraft-filter-chip',                'border-color' ),
			array( 'chipActiveBg',        ' .menucraft-filter-chip.is-active',      'background-color' ),
			array( 'chipActiveText',      ' .menucraft-filter-chip.is-active',      'color' ),
			// Items.
			array( 'itemBg',              ' .menucraft-item',                       'background-color' ),
			array( 'itemBorder',          ' .menucraft-item',                       'border-color' ),
			array( 'itemTitleColor',      ' .menucraft-item-title',                 'color' ),
			array( 'itemDescColor',       ' .menucraft-item-desc',                  'color' ),
			array( 'itemPriceColor',      ' .menucraft-item-price',                 'color' ),
			array( 'allergenSupColor',    ' .menucraft-item-allergens',             'color' ),
			array( 'variantDividerColor', ' .menucraft-item-variant',               'border-bottom-color' ),
			// Tags.
			array( 'tagBorder',           ' .menucraft-item-tag',                   'border-color' ),
			array( 'tagText',             ' .menucraft-item-tag',                   'color' ),
			// Legend.
			array( 'legendBg',            ' .menucraft-allergens-legend',           'background-color' ),
			array( 'legendText',          ' .menucraft-allergens-legend',           'color' ),
		);
	}

	/**
	 * Register the block type from its block.json + inject our custom
	 * block category into the inserter.
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WordPress < 5.0.
		}

		register_block_type(
			MENUCRAFT_PLUGIN_DIR . 'blocks/menu',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);

		add_filter( 'block_categories_all', array( __CLASS__, 'inject_category' ), 10, 1 );
	}

	/**
	 * Add a dedicated "MenuCraft" category to the block inserter so our
	 * blocks live in one predictable place instead of scattered across
	 * Widgets/Common.
	 *
	 * @param array $categories Existing block categories.
	 * @return array
	 */
	public static function inject_category( $categories ) {
		return array_merge(
			array(
				array(
					'slug'  => 'menucraft',
					'title' => __( 'MenuCraft', 'menucraft' ),
					'icon'  => 'coffee',
				),
			),
			$categories
		);
	}

	/**
	 * Server-side render callback declared in block.json.
	 *
	 * Builds a `[menucraft …]` shortcode from the layout/title/columns
	 * attributes, wraps its output in an outer div that carries block-only
	 * decoration (colors, alignment, font-scale) and prepends a scoped
	 * <style> block with the color overrides. The scoping selector is
	 * the outer wrapper's unique id, generated per instance.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$sc = self::build_shortcode( $attributes );

		$scale        = self::whitelist( isset( $attributes['fontScale'] ) ? $attributes['fontScale'] : 'medium', array( 'small', 'medium', 'large' ), 'medium' );
		$filter_align = self::whitelist( isset( $attributes['filterAlign'] ) ? $attributes['filterAlign'] : 'left', array( 'left', 'center', 'right' ), 'left' );
		$item_align   = self::whitelist( isset( $attributes['itemAlign'] ) ? $attributes['itemAlign'] : 'left', array( 'left', 'center', 'right' ), 'left' );

		$scale_short = ( 'small' === $scale ) ? 's' : ( ( 'large' === $scale ) ? 'l' : 'm' );

		$block_id = wp_unique_id( 'menucraft-block-' );

		// Build the wrapper class list explicitly. Earlier we routed this
		// through get_block_wrapper_attributes(), but its behaviour when
		// passing a custom `class` extra differs across WP versions and
		// sometimes dropped our alignment/scale classes silently. Doing
		// it by hand is predictable and still WP-standard: the base
		// `wp-block-menucraft-menu` class + the user's `className` are
		// applied, plus `align{wide,full}` when the toolbar toggle is set.
		$classes = array(
			'wp-block-menucraft-menu',
			'menucraft-block',
			'menucraft-scale-' . $scale_short,
			'menucraft-filter-align-' . $filter_align,
			'menucraft-content-align-' . $item_align,
		);

		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = (string) $attributes['className'];
		}
		if ( ! empty( $attributes['align'] ) && in_array( (string) $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$classes[] = 'align' . $attributes['align'];
		}

		$style = self::build_style_block( $block_id, $attributes );

		return sprintf(
			'<div id="%1$s" class="%2$s">%3$s%4$s</div>',
			esc_attr( $block_id ),
			esc_attr( implode( ' ', $classes ) ),
			$style,
			do_shortcode( $sc )
		);
	}

	/**
	 * Assemble the `[menucraft …]` shortcode string from the block's
	 * shortcode-facing attributes. Block-only attributes (colors,
	 * alignment, scale) are handled outside via CSS and never leak into
	 * the shortcode call.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	private static function build_shortcode( array $attributes ) {
		$map = array(
			'image'           => 'image',
			'variants'        => 'variants',
			'categoriesTitle' => 'categories_title',
			'tagsTitle'       => 'tags_title',
			'allergensTitle'  => 'allergens_title',
			'columns'         => 'columns',
			'allergensLegend' => 'allergens_legend',
		);

		$parts = array();
		foreach ( $map as $block_key => $shortcode_key ) {
			if ( ! isset( $attributes[ $block_key ] ) ) {
				continue;
			}
			$value = (string) $attributes[ $block_key ];
			if ( '' === $value ) {
				continue;
			}
			$parts[] = $shortcode_key . '="' . esc_attr( $value ) . '"';
		}

		if ( ! empty( $attributes['className'] ) ) {
			$parts[] = 'class="' . esc_attr( (string) $attributes['className'] ) . '"';
		}

		return '[menucraft' . ( empty( $parts ) ? '' : ' ' . implode( ' ', $parts ) ) . ']';
	}

	/**
	 * Selectors that receive a globally-applied border-radius when the
	 * `borderRadius` attribute is set. One value, many targets — covers
	 * container / filter bar / chips / item cards / item images / tag
	 * pills / modal dialog so the whole block adopts the same corner
	 * roundness in one setting.
	 *
	 * @return string[]
	 */
	private static function border_radius_selectors() {
		return array(
			'',                              // outer wrapper itself
			' .menucraft-filter-bar',
			' .menucraft-filter-chip',
			' .menucraft-item',
			' .menucraft-item-media img',
			' .menucraft-item-tag',
			' .menucraft-modal-dialog',
		);
	}

	/**
	 * Build a <style> block scoped to the outer wrapper id, applying
	 * whatever color slots the author actually filled in plus a global
	 * border-radius when that attribute is set.
	 *
	 * @param string              $block_id   Outer wrapper id.
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	private static function build_style_block( $block_id, array $attributes ) {
		$rules = array();

		foreach ( self::color_slots() as $slot ) {
			list( $attr, $selector, $property ) = $slot;
			if ( ! isset( $attributes[ $attr ] ) ) {
				continue;
			}
			$value = self::sanitize_css_value( (string) $attributes[ $attr ] );
			if ( '' === $value ) {
				continue;
			}
			$rules[] = '#' . $block_id . $selector . '{' . $property . ':' . $value . ';}';
		}

		if ( isset( $attributes['borderRadius'] ) && '' !== (string) $attributes['borderRadius'] ) {
			$radius = max( 0, (int) $attributes['borderRadius'] );
			foreach ( self::border_radius_selectors() as $sel ) {
				$rules[] = '#' . $block_id . $sel . '{border-radius:' . $radius . 'px;}';
			}
		}

		if ( empty( $rules ) ) {
			return '';
		}
		return '<style>' . implode( '', $rules ) . '</style>';
	}

	/**
	 * Whitelist a value against a set of allowed strings.
	 *
	 * @param string   $value    Candidate.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Value returned when candidate is not allowed.
	 * @return string
	 */
	private static function whitelist( $value, array $allowed, $fallback ) {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitize a color value coming from the block editor's color picker
	 * for safe injection into an inline <style>. Accepts hex, rgb(),
	 * hsl(), var(--…) and CSS named colors; strips everything that could
	 * escape the CSS property context.
	 *
	 * @param string $value Raw value.
	 * @return string Safe value, or empty string when input is not usable.
	 */
	private static function sanitize_css_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Strip any character that shouldn't appear in a CSS color value —
		// notably ; { } < > " ' which could break out of the property.
		$safe = preg_replace( '/[^a-zA-Z0-9#()\-_.,%\s]/', '', $value );
		return is_string( $safe ) ? $safe : '';
	}
}
