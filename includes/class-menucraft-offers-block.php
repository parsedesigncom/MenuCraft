<?php
/**
 * Gutenberg block for offers.
 *
 * `menucraft/offers` is a dynamic block whose render_callback wraps the
 * [menucraft_offers] shortcode. Attributes are split into two groups:
 * "shortcode-facing" ones (image, columns, validity, show_items, show_desc,
 * show_dates, conditions) are re-emitted into the shortcode string, while
 * block-only decoration (font scale, item alignment, border radius, ~10
 * color slots) is applied via classes and a scoped inline <style> on the
 * outer wrapper. The shortcode itself never sees the decoration attrs.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offers block controller.
 */
class MenuCraft_Offers_Block {

	/**
	 * Color-slot registry. Fewer slots than the menu block because offers
	 * have no filter chips, tag pills or allergen legend. Groups are
	 * mirrored in blocks/offers/index.js.
	 *
	 * @return array<int,array{0:string,1:string,2:string}>
	 */
	private static function color_slots() {
		return array(
			// Container.
			array( 'bgColor',           '',                             'background-color' ),
			array( 'textColor',         '',                             'color' ),
			// Cards (offers reuse .menucraft-item classes for the card).
			array( 'cardBg',            ' .menucraft-item',             'background-color' ),
			array( 'cardBorder',        ' .menucraft-item',             'border-color' ),
			array( 'cardTitleColor',    ' .menucraft-item-title',       'color' ),
			array( 'cardDescColor',     ' .menucraft-item-desc',        'color' ),
			array( 'cardPriceColor',    ' .menucraft-item-price',       'color' ),
			// Offer-specific bits.
			array( 'linesColor',        ' .menucraft-offer-line',       'color' ),
			array( 'validityColor',     ' .menucraft-offer-validity',   'color' ),
			array( 'conditionsColor',   ' .menucraft-offer-conditions', 'color' ),
		);
	}

	/**
	 * Selectors that receive the global border-radius when the block's
	 * `borderRadius` attribute is set.
	 *
	 * @return string[]
	 */
	private static function border_radius_selectors() {
		return array(
			'',                              // outer wrapper
			' .menucraft-item',              // offer card
			' .menucraft-item-media img',    // card image
			' .menucraft-modal-dialog',      // modal
		);
	}

	/**
	 * Register the block type. The block category itself is already
	 * registered by MenuCraft_Block so we don't double-add it here.
	 */
	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		register_block_type(
			MENUCRAFT_PLUGIN_DIR . 'blocks/offers',
			array(
				'render_callback' => array( __CLASS__, 'render_block' ),
			)
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		$sc = self::build_shortcode( $attributes );

		$scale      = self::whitelist( isset( $attributes['fontScale'] ) ? $attributes['fontScale'] : 'medium', array( 'small', 'medium', 'large' ), 'medium' );
		$item_align = self::whitelist( isset( $attributes['itemAlign'] ) ? $attributes['itemAlign'] : 'left', array( 'left', 'center', 'right' ), 'left' );

		$scale_short = ( 'small' === $scale ) ? 's' : ( ( 'large' === $scale ) ? 'l' : 'm' );
		$block_id    = wp_unique_id( 'menucraft-offers-block-' );

		$classes = array(
			'wp-block-menucraft-offers',
			'menucraft-block',
			'menucraft-scale-' . $scale_short,
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
	 * Assemble the `[menucraft_offers …]` shortcode string from the
	 * shortcode-facing attributes only. Decoration attrs stay on the
	 * PHP side.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	private static function build_shortcode( array $attributes ) {
		$map = array(
			'image'      => 'image',
			'columns'    => 'columns',
			'validity'   => 'validity',
			'showItems'  => 'show_items',
			'showDesc'   => 'show_desc',
			'showDates'  => 'show_dates',
			'conditions' => 'conditions',
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

		return '[menucraft_offers' . ( empty( $parts ) ? '' : ' ' . implode( ' ', $parts ) ) . ']';
	}

	/**
	 * Build the scoped <style> block for color slots + global border-radius.
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
	 * Sanitize a CSS color value for safe injection into inline <style>.
	 * Same whitelist as MenuCraft_Block: strips anything that could escape
	 * the property context (;, {, <, > etc.).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_css_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$safe = preg_replace( '/[^a-zA-Z0-9#()\-_.,%\s]/', '', $value );
		return is_string( $safe ) ? $safe : '';
	}
}
