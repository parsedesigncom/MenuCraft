<?php
/**
 * Gutenberg block registration.
 *
 * `menucraft/menu` is a dynamic block: attributes are saved as JSON in
 * post_content and the HTML is produced at render time by wrapping the
 * `[menucraft]` shortcode. This keeps rendering logic in one place —
 * every improvement to the shortcode's template flows through to the
 * block automatically.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block controller.
 */
class MenuCraft_Block {

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
	 * Translates the block attributes (camelCase per JS conventions) back
	 * into the corresponding shortcode attributes (snake_case) and hands
	 * the string to do_shortcode(). The shortcode already handles asset
	 * enqueue + full HTML rendering.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
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

		// WP standard `className` attribute → shortcode `class`.
		if ( ! empty( $attributes['className'] ) ) {
			$parts[] = 'class="' . esc_attr( (string) $attributes['className'] ) . '"';
		}

		$sc = '[menucraft' . ( empty( $parts ) ? '' : ' ' . implode( ' ', $parts ) ) . ']';
		return do_shortcode( $sc );
	}
}
