<?php
/**
 * MenuCraft — default [menucraft_group] shortcode template.
 *
 * Themes may override this file by copying it to:
 *   your-theme/menucraft/shortcode-group.php
 *
 * Available variables (extracted by MenuCraft_Public::render_group_shortcode):
 *   $source_type  string             'category' | 'tag' | ''
 *   $source       array|null         The resolved category/tag row, or null
 *                                    when no valid source was picked.
 *   $items        array<int,array>   Active items in that group.
 *   $allergens    array<int,array>   Allergen map keyed by id (for legend + item codes).
 *   $tags         array<int,array>   Tag map keyed by id (for pill labels on items).
 *   $config       array              Normalised shortcode config.
 *   $atts         array              Raw shortcode attributes.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

$instance_id  = isset( $config['instance_id'] ) ? $config['instance_id'] : 'menucraft-group';
$image_pos    = isset( $config['image_pos'] ) ? $config['image_pos'] : 'left';
$grid_class   = ! empty( $config['grid_enabled'] ) ? ' menucraft-group--grid' : ' menucraft-group--rows';
$custom_class = ! empty( $config['custom_class'] ) ? ' ' . $config['custom_class'] : '';
$collapsed    = ! empty( $config['collapsed'] );

$source_name  = $source ? (string) $source['name'] : '';
$source_desc  = $source ? (string) $source['description'] : '';
$source_media = ( $source && ! empty( $source['media_id'] ) )
	? wp_get_attachment_image_url( (int) $source['media_id'], 'large' )
	: '';

$header_html = '';
if ( ! empty( $config['show_header'] ) && $source ) {
	$header_html .= '<header class="menucraft-group-header">';
	if ( $source_media && ! $collapsed ) {
		$header_html .= '<div class="menucraft-group-header-media"><img src="' . esc_url( $source_media ) . '" alt="' . esc_attr( $source_name ) . '" loading="lazy"></div>';
	}
	$header_html .= '<div class="menucraft-group-header-body">';
	$header_html .= '<h2 class="menucraft-group-title">' . esc_html( $source_name ) . '</h2>';
	if ( '' !== $source_desc ) {
		$header_html .= '<p class="menucraft-group-desc">' . esc_html( $source_desc ) . '</p>';
	}
	$header_html .= '</div>';
	$header_html .= '</header>';
}

// Collapsed header includes the image in the expanded content instead of
// the header, so users first see just the teaser text.
$expanded_media_html = '';
if ( $collapsed && $source_media && ! empty( $config['show_header'] ) ) {
	$expanded_media_html = '<div class="menucraft-group-expanded-media"><img src="' . esc_url( $source_media ) . '" alt="' . esc_attr( $source_name ) . '" loading="lazy"></div>';
}
?>
<div class="menucraft menucraft-group menucraft-image-<?php echo esc_attr( $image_pos ); ?><?php echo esc_attr( $grid_class ); ?><?php echo esc_attr( $custom_class ); ?><?php echo $collapsed ? ' menucraft-group--collapsible' : ''; ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-menucraft-root>

	<?php if ( ! empty( $config['grid_css'] ) ) : ?>
		<style><?php echo $config['grid_css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
	<?php endif; ?>

	<?php if ( ! $source ) : ?>
		<p class="menucraft-empty">
			<?php esc_html_e( 'No matching category or tag found.', 'menucraft' ); ?>
		</p>
	<?php elseif ( $collapsed ) : ?>
		<?php // Collapsible mode: <details> element for zero-JS toggle behaviour. ?>
		<details class="menucraft-group-details">
			<summary class="menucraft-group-summary">
				<?php echo $header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="menucraft-group-summary-icon" aria-hidden="true"></span>
			</summary>
			<div class="menucraft-group-content">
				<?php echo $expanded_media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php do_action( 'menucraft_before_items', $items ); ?>
				<div class="menucraft-items" data-menucraft-items>
					<?php if ( empty( $items ) ) : ?>
						<p class="menucraft-empty"><?php esc_html_e( 'No menu items to display yet.', 'menucraft' ); ?></p>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<?php echo MenuCraft_Public::render_item( $item, $allergens, $tags, $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<?php do_action( 'menucraft_after_items', $items ); ?>

				<?php if ( ! empty( $config['show_allergens_legend'] ) && ! empty( $allergens ) ) : ?>
					<div class="menucraft-allergens-legend" data-menucraft-allergens-legend>
						<span class="menucraft-allergens-legend-label"><?php esc_html_e( 'Allergens', 'menucraft' ); ?>:</span>
						<span class="menucraft-allergens-legend-list">
							<?php foreach ( $allergens as $allergen ) : ?>
								<span class="menucraft-allergens-legend-item">
									<strong><?php echo esc_html( $allergen['code'] ); ?></strong>
									<?php echo esc_html( $allergen['name'] ); ?>
								</span>
							<?php endforeach; ?>
						</span>
					</div>
				<?php endif; ?>
			</div>
		</details>
	<?php else : ?>
		<?php echo $header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php do_action( 'menucraft_before_items', $items ); ?>
		<div class="menucraft-items" data-menucraft-items>
			<?php if ( empty( $items ) ) : ?>
				<p class="menucraft-empty"><?php esc_html_e( 'No menu items to display yet.', 'menucraft' ); ?></p>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php echo MenuCraft_Public::render_item( $item, $allergens, $tags, $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php do_action( 'menucraft_after_items', $items ); ?>

		<?php if ( ! empty( $config['show_allergens_legend'] ) && ! empty( $allergens ) ) : ?>
			<div class="menucraft-allergens-legend" data-menucraft-allergens-legend>
				<span class="menucraft-allergens-legend-label"><?php esc_html_e( 'Allergens', 'menucraft' ); ?>:</span>
				<span class="menucraft-allergens-legend-list">
					<?php foreach ( $allergens as $allergen ) : ?>
						<span class="menucraft-allergens-legend-item">
							<strong><?php echo esc_html( $allergen['code'] ); ?></strong>
							<?php echo esc_html( $allergen['name'] ); ?>
						</span>
					<?php endforeach; ?>
				</span>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php // ---- Details modal (populated by JS, same shell for items) ---- ?>
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
