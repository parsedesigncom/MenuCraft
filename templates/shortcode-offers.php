<?php
/**
 * MenuCraft — default [menucraft_offers] shortcode template.
 *
 * Themes may override this file by copying it to:
 *   your-theme/menucraft/shortcode-offers.php
 *
 * Available variables (extracted by MenuCraft_Public::render_offers_shortcode):
 *   $offers     array<int,array>  Filtered active offers.
 *   $items_map  array<int,array>  id => hydrated item, used to render lines.
 *   $config     array             Normalised shortcode config.
 *   $atts       array             Raw shortcode attributes.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

$instance_id  = isset( $config['instance_id'] ) ? $config['instance_id'] : 'menucraft-offers';
$image_pos    = isset( $config['image_pos'] ) ? $config['image_pos'] : 'left';
$grid_class   = ! empty( $config['grid_enabled'] ) ? ' menucraft-offers--grid' : ' menucraft-offers--rows';
$custom_class = ! empty( $config['custom_class'] ) ? ' ' . $config['custom_class'] : '';

$list_override = apply_filters( 'menucraft_offers_shortcode_items_html', '', $offers );
?>
<div class="menucraft menucraft-offers menucraft-image-<?php echo esc_attr( $image_pos ); ?><?php echo esc_attr( $grid_class ); ?><?php echo esc_attr( $custom_class ); ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-menucraft-root>

	<?php if ( ! empty( $config['grid_css'] ) ) : ?>
		<style><?php echo $config['grid_css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
	<?php endif; ?>

	<?php if ( is_string( $list_override ) && '' !== $list_override ) : ?>
		<?php echo $list_override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<?php do_action( 'menucraft_before_offers', $offers ); ?>
		<div class="menucraft-offers-list" data-menucraft-offers-list>
			<?php if ( empty( $offers ) ) : ?>
				<p class="menucraft-empty">
					<?php esc_html_e( 'No current offers.', 'menucraft' ); ?>
				</p>
			<?php else : ?>
				<?php foreach ( $offers as $offer ) : ?>
					<?php echo MenuCraft_Public::render_offer( $offer, $items_map, $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php do_action( 'menucraft_after_offers', $offers ); ?>
	<?php endif; ?>

	<?php // ---- Details modal (populated by JS) ---- ?>
	<div class="menucraft-modal"
		id="<?php echo esc_attr( $instance_id ); ?>-modal"
		role="dialog"
		aria-modal="true"
		aria-hidden="true"
		aria-labelledby="<?php echo esc_attr( $instance_id ); ?>-modal-title"
		data-menucraft-modal>
		<div class="menucraft-modal-backdrop" data-menucraft-modal-close></div>
		<div class="menucraft-modal-dialog">
			<header class="menucraft-modal-header">
				<h2 class="menucraft-modal-title" id="<?php echo esc_attr( $instance_id ); ?>-modal-title"></h2>
				<button type="button"
					class="menucraft-modal-close"
					data-menucraft-modal-close
					aria-label="<?php esc_attr_e( 'Close', 'menucraft' ); ?>">&times;</button>
			</header>
			<div class="menucraft-modal-body" data-menucraft-modal-body></div>
		</div>
	</div>

</div>
