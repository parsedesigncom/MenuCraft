<?php
/**
 * MenuCraft — default per-item template used by the [menucraft] shortcode.
 *
 * Themes may override this file by copying it to:
 *   your-theme/menucraft/shortcode-item.php
 *
 * Available variables (passed by MenuCraft_Public::render_item):
 *   $item      array<string,mixed>            Hydrated item.
 *   $allergens array<int,array<string,mixed>> Allergen map keyed by id.
 *   $tags      array<int,array<string,mixed>> Tag map keyed by id (for pill labels).
 *   $config    array<string,mixed>            Normalised shortcode config
 *                                             (image_pos, variants_mode).
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

$image_pos     = isset( $config['image_pos'] ) ? $config['image_pos'] : 'left';
$variants_mode = isset( $config['variants_mode'] ) ? $config['variants_mode'] : 'inline';

$item_id       = (int) $item['id'];
$active_variants = array();
if ( ! empty( $item['variants'] ) ) {
	foreach ( $item['variants'] as $v ) {
		if ( ! empty( $v['is_active'] ) ) {
			$active_variants[] = $v;
		}
	}
}
$has_variants  = ! empty( $active_variants );
$show_variants_inline = $has_variants && 'inline' === $variants_mode;
$show_variants_modal  = $has_variants && 'modal' === $variants_mode;

$image_url     = ! empty( $item['media_id'] ) ? wp_get_attachment_image_url( (int) $item['media_id'], 'medium' ) : '';
$categories    = isset( $item['category_ids'] ) ? array_map( 'intval', (array) $item['category_ids'] ) : array();
$item_tag_ids  = isset( $item['tag_ids'] ) ? array_map( 'intval', (array) $item['tag_ids'] ) : array();
$item_all_ids  = isset( $item['allergen_ids'] ) ? array_map( 'intval', (array) $item['allergen_ids'] ) : array();
$has_long_desc = ! empty( $item['description_long'] );
$has_details   = $has_long_desc || $show_variants_modal;

// Compute an "effective" price to show in the card header when variants
// are hidden or absent: prefer base price, otherwise the smallest variant
// (with "from" prefix).
$price_display = '';
$price_hint    = '';
if ( null !== $item['price'] ) {
	$price_display = MenuCraft_Public::format_price( $item['price'] );
} elseif ( $has_variants ) {
	$min_price = null;
	foreach ( $active_variants as $v ) {
		$p = (float) $v['price'];
		if ( null === $min_price || $p < $min_price ) {
			$min_price = $p;
		}
	}
	if ( null !== $min_price ) {
		$price_display = MenuCraft_Public::format_price( $min_price );
		/* translators: prefix for "from X€" price when only the cheapest variant is shown */
		$price_hint = __( 'from', 'menucraft' );
	}
}
$show_price_header = '' !== $price_display && ! $show_variants_inline;

// Build the modal body HTML once, server-side, so JS only injects
// pre-escaped content. wp_kses_post protects the long description if
// someone pasted markup into it.
$modal_html = '';
if ( $has_long_desc ) {
	$modal_html .= '<div class="menucraft-modal-desc">' . wpautop( wp_kses_post( $item['description_long'] ) ) . '</div>';
}
if ( $show_variants_modal ) {
	$modal_html .= '<ul class="menucraft-modal-variants">';
	foreach ( $active_variants as $v ) {
		$modal_html .= '<li>'
			. '<span class="menucraft-modal-variant-label">' . esc_html( $v['label'] ) . '</span>'
			. '<span class="menucraft-modal-variant-price">' . esc_html( MenuCraft_Public::format_price( $v['price'] ) ) . '</span>'
			. '</li>';
	}
	$modal_html .= '</ul>';
}

$body_html  = '';
$body_html .= '<div class="menucraft-item-body">';
$body_html .= '<header class="menucraft-item-head">';
$body_html .= '<h3 class="menucraft-item-title">' . esc_html( $item['name'] ) . '</h3>';
if ( $show_price_header ) {
	$body_html .= '<span class="menucraft-item-price">';
	if ( '' !== $price_hint ) {
		$body_html .= '<span class="menucraft-item-price-hint">' . esc_html( $price_hint ) . '</span> ';
	}
	$body_html .= esc_html( $price_display );
	$body_html .= '</span>';
}
$body_html .= '</header>';

if ( ! empty( $item['description_short'] ) ) {
	$body_html .= '<p class="menucraft-item-desc">' . esc_html( $item['description_short'] ) . '</p>';
}

if ( $show_variants_inline ) {
	$body_html .= '<ul class="menucraft-item-variants">';
	foreach ( $active_variants as $v ) {
		$body_html .= '<li class="menucraft-item-variant">'
			. '<span class="menucraft-item-variant-label">' . esc_html( $v['label'] ) . '</span>'
			. '<span class="menucraft-item-variant-price">' . esc_html( MenuCraft_Public::format_price( $v['price'] ) ) . '</span>'
			. '</li>';
	}
	$body_html .= '</ul>';
}

if ( ! empty( $item_tag_ids ) || ! empty( $item_all_ids ) ) {
	$body_html .= '<footer class="menucraft-item-meta">';
	if ( ! empty( $item_all_ids ) ) {
		$codes = array();
		foreach ( $item_all_ids as $aid ) {
			if ( isset( $allergens[ $aid ] ) ) {
				$codes[] = $allergens[ $aid ]['code'];
			}
		}
		if ( ! empty( $codes ) ) {
			$body_html .= '<span class="menucraft-item-allergens">'
				. esc_html__( 'Allergens:', 'menucraft' ) . ' '
				. esc_html( implode( ', ', $codes ) )
				. '</span>';
		}
	}
	if ( ! empty( $item_tag_ids ) ) {
		$body_html .= '<span class="menucraft-item-tags">';
		foreach ( $item_tag_ids as $tid ) {
			if ( ! isset( $tags[ $tid ] ) ) {
				continue;
			}
			$style = ! empty( $tags[ $tid ]['color'] ) ? ' style="border-color:' . esc_attr( $tags[ $tid ]['color'] ) . '"' : '';
			$body_html .= '<span class="menucraft-item-tag"' . $style . '>' . esc_html( $tags[ $tid ]['name'] ) . '</span>';
		}
		$body_html .= '</span>';
	}
	$body_html .= '</footer>';
}
$body_html .= '</div>';

$media_html = '';
if ( $image_url ) {
	$media_html = '<div class="menucraft-item-media">'
		. '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $item['name'] ) . '" loading="lazy">'
		. '</div>';
}
?>
<article class="menucraft-item menucraft-item--image-<?php echo esc_attr( $image_pos ); ?><?php echo $has_details ? ' menucraft-item-has-details' : ''; ?>"
	data-menucraft-item="<?php echo esc_attr( (string) $item_id ); ?>"
	data-menucraft-categories="<?php echo esc_attr( implode( ',', $categories ) ); ?>"
	data-menucraft-tags="<?php echo esc_attr( implode( ',', $item_tag_ids ) ); ?>"
	data-menucraft-allergens="<?php echo esc_attr( implode( ',', $item_all_ids ) ); ?>"
	<?php if ( $has_details ) : ?>
		tabindex="0"
		role="button"
		aria-label="<?php echo esc_attr( sprintf( /* translators: %s: item name */ __( 'Show details for %s', 'menucraft' ), $item['name'] ) ); ?>"
	<?php endif; ?>>

	<?php
	// HTML order swaps for image=right; top and left both put media first.
	if ( 'right' === $image_pos ) {
		echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body_html;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>

	<?php if ( $has_details ) : ?>
		<script type="application/json" data-menucraft-item-details="<?php echo esc_attr( (string) $item_id ); ?>">
			<?php echo wp_json_encode( array( 'title' => $item['name'], 'html' => $modal_html ) ); ?>
		</script>
	<?php endif; ?>

</article>
