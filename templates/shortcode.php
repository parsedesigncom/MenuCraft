<?php
/**
 * MenuCraft — default [menucraft] shortcode template.
 *
 * Themes may override this file by copying it to:
 *   your-theme/menucraft/shortcode.php
 *
 * Available variables (extracted by MenuCraft_Public::render_shortcode):
 *   $items       array<int,array>  Hydrated, filtered, active items.
 *   $categories  array<int,array>  Categories that appear on visible items.
 *   $tags        array<int,array>  Tags that appear on visible items.
 *   $allergens   array<int,array>  Allergen map keyed by allergen id.
 *   $config      array             Normalised shortcode config (see MenuCraft_Public::normalise_atts).
 *   $atts        array             Raw shortcode attributes.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

$instance_id  = isset( $config['instance_id'] ) ? $config['instance_id'] : 'menucraft-menu';
$image_pos    = isset( $config['image_pos'] ) ? $config['image_pos'] : 'left';
$grid_class   = ! empty( $config['grid_enabled'] ) ? ' menucraft-menu--grid' : ' menucraft-menu--rows';
$titles       = isset( $config['titles'] ) ? $config['titles'] : array();
$custom_class = ! empty( $config['custom_class'] ) ? ' ' . $config['custom_class'] : '';

$filters_override = apply_filters( 'menucraft_shortcode_filters_html', '', $categories, $tags );
$cats_override    = apply_filters( 'menucraft_shortcode_categories_html', '', $categories );
$tags_override    = apply_filters( 'menucraft_shortcode_tags_html', '', $tags );
$items_override   = apply_filters( 'menucraft_shortcode_items_html', '', $items );
?>
<div class="menucraft menucraft-menu<?php echo esc_attr( $grid_class ); ?> menucraft-image-<?php echo esc_attr( $image_pos ); ?><?php echo esc_attr( $custom_class ); ?>"
	id="<?php echo esc_attr( $instance_id ); ?>"
	data-menucraft-menu
	data-menucraft-root>

	<?php if ( ! empty( $config['grid_css'] ) ) : ?>
		<style><?php echo $config['grid_css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
	<?php endif; ?>

	<?php if ( is_string( $filters_override ) && '' !== $filters_override ) : ?>
		<?php echo $filters_override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<?php do_action( 'menucraft_before_filters', $categories, $tags ); ?>
		<div class="menucraft-filter-bar" data-menucraft-filter-bar>

			<?php if ( ! empty( $categories ) ) : ?>
				<?php if ( is_string( $cats_override ) && '' !== $cats_override ) : ?>
					<?php echo $cats_override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<div class="menucraft-filter-group" data-menucraft-filter-group="category">
						<span class="menucraft-filter-label"><?php echo esc_html( $titles['categories'] ); ?></span>
						<div class="menucraft-filter-chips">
							<?php foreach ( $categories as $cat ) : ?>
								<button type="button"
									class="menucraft-filter-chip"
									data-menucraft-filter="category"
									data-menucraft-value="<?php echo esc_attr( (string) $cat['id'] ); ?>"
									<?php if ( ! empty( $cat['is_default'] ) ) : ?>data-menucraft-default<?php endif; ?>>
									<?php echo esc_html( $cat['name'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! empty( $tags ) ) : ?>
				<?php if ( is_string( $tags_override ) && '' !== $tags_override ) : ?>
					<?php echo $tags_override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<div class="menucraft-filter-group" data-menucraft-filter-group="tag">
						<span class="menucraft-filter-label"><?php echo esc_html( $titles['tags'] ); ?></span>
						<div class="menucraft-filter-chips">
							<?php foreach ( $tags as $tag ) : ?>
								<button type="button"
									class="menucraft-filter-chip"
									data-menucraft-filter="tag"
									data-menucraft-value="<?php echo esc_attr( (string) $tag['id'] ); ?>">
									<?php echo esc_html( $tag['name'] ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>

		</div>
		<?php do_action( 'menucraft_after_filters', $categories, $tags ); ?>
	<?php endif; ?>

	<?php if ( is_string( $items_override ) && '' !== $items_override ) : ?>
		<?php echo $items_override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<?php do_action( 'menucraft_before_items', $items ); ?>
		<div class="menucraft-items" data-menucraft-items>
			<?php if ( empty( $items ) ) : ?>
				<p class="menucraft-empty">
					<?php esc_html_e( 'No menu items to display yet.', 'menucraft' ); ?>
				</p>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<?php echo MenuCraft_Public::render_item( $item, $allergens, $tags, $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php do_action( 'menucraft_after_items', $items ); ?>
	<?php endif; ?>

	<?php
	// ---------- Allergens legend (fine-print list at end of menu) ----------
	if ( ! empty( $config['show_allergens_legend'] ) && ! empty( $allergens ) ) :
		/**
		 * Filter: replace the allergens-legend HTML entirely.
		 * Return '' to keep the built-in output.
		 *
		 * @param string $html      Empty by default.
		 * @param array  $allergens Allergen rows keyed by id.
		 */
		$legend_override = apply_filters( 'menucraft_shortcode_allergens_legend_html', '', $allergens );

		if ( is_string( $legend_override ) && '' !== $legend_override ) {
			echo $legend_override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			/**
			 * Action: right before the allergens legend.
			 *
			 * @param array $allergens
			 */
			do_action( 'menucraft_before_allergens_legend', $allergens );
			?>
			<div class="menucraft-allergens-legend" data-menucraft-allergens-legend>
				<span class="menucraft-allergens-legend-label">
					<?php echo esc_html( $titles['allergens'] ); ?>:
				</span>
				<span class="menucraft-allergens-legend-list">
					<?php foreach ( $allergens as $allergen ) : ?>
						<span class="menucraft-allergens-legend-item">
							<strong><?php echo esc_html( $allergen['code'] ); ?></strong>
							<?php echo esc_html( $allergen['name'] ); ?>
						</span>
					<?php endforeach; ?>
				</span>
			</div>
			<?php
			/**
			 * Action: right after the allergens legend.
			 *
			 * @param array $allergens
			 */
			do_action( 'menucraft_after_allergens_legend', $allergens );
		}
	endif;
	?>

	<?php // ---- Long-description / variants modal shell (populated by JS) ---- ?>
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
