<?php
/**
 * Main MenuCraft admin page view (Dashboard).
 *
 * At-a-glance counters — offers on top because they're time-sensitive,
 * then items with the variant + missing-image breakdown, then a per-
 * taxonomy count. Each number animates from 0 to its target on page
 * load via the small counter helper in menucraft-admin.js.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

// ---- Load raw data ---------------------------------------------------

$items      = MenuCraft_Item_Repository::all();
$offers     = MenuCraft_Offer_Repository::all();
$categories = MenuCraft_Category_Repository::all();
$tags       = MenuCraft_Tag_Repository::all();
$allergens  = MenuCraft_Allergen_Repository::all();

// ---- Item stats ------------------------------------------------------

$items_total          = count( $items );
$items_no_image       = 0;
$items_with_variants  = 0;
$variants_total       = 0;

foreach ( $items as $it ) {
	if ( empty( $it['media_id'] ) ) {
		$items_no_image++;
	}
	$count = ! empty( $it['variants'] ) ? count( $it['variants'] ) : 0;
	if ( $count > 0 ) {
		$items_with_variants++;
		$variants_total += $count;
	}
}

// ---- Offer stats -----------------------------------------------------

$offers_total   = count( $offers );
$offers_active  = 0;
$offers_current = 0;
$now_mysql      = current_time( 'mysql', 1 );

foreach ( $offers as $of ) {
	if ( ! empty( $of['is_active'] ) ) {
		$offers_active++;
	}
	if ( empty( $of['is_active'] ) ) {
		continue;
	}
	$from  = ! empty( $of['valid_from'] ) ? $of['valid_from'] : null;
	$until = ! empty( $of['valid_until'] ) ? $of['valid_until'] : null;
	$in_from  = ! $from || $from <= $now_mysql;
	$in_until = ! $until || $until >= $now_mysql;
	if ( $in_from && $in_until ) {
		$offers_current++;
	}
}

// Tile helper — renders one counter card. $value is the numeric target,
// the initial text is "0" so the count-up starts from zero.
$tile = function ( $value, $label, $modifier = '' ) {
	$class = 'menucraft-counter' . ( '' !== $modifier ? ' menucraft-counter--' . $modifier : '' );
	?>
	<div class="<?php echo esc_attr( $class ); ?>">
		<span class="menucraft-counter-value" data-menucraft-counter="<?php echo esc_attr( (string) $value ); ?>">0</span>
		<span class="menucraft-counter-label"><?php echo esc_html( $label ); ?></span>
	</div>
	<?php
};
?>
<div class="wrap menucraft-wrap">
	<div class="menucraft-card">
		<header class="menucraft-page-header">
			<div class="menucraft-page-header-row">
				<h1 class="menucraft-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			</div>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'At a glance — the numbers behind your menu.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">

			<?php // -------------------- Offers (top priority) -------------------- ?>
			<section class="menucraft-counters menucraft-counters--primary">
				<h2 class="menucraft-counters-title"><?php esc_html_e( 'Offers', 'menucraft' ); ?></h2>
				<div class="menucraft-counters-grid">
					<?php $tile( $offers_total,   __( 'Total offers', 'menucraft' ),      'primary' ); ?>
					<?php $tile( $offers_active,  __( 'Active', 'menucraft' ),           'primary' ); ?>
					<?php $tile( $offers_current, __( 'Currently valid', 'menucraft' ), 'primary' ); ?>
				</div>
			</section>

			<?php // -------------------- Items -------------------- ?>
			<section class="menucraft-counters">
				<h2 class="menucraft-counters-title"><?php esc_html_e( 'Items', 'menucraft' ); ?></h2>
				<div class="menucraft-counters-grid">
					<?php $tile( $items_total,         __( 'Total items', 'menucraft' ) ); ?>
					<?php $tile( $items_with_variants, __( 'Items with variants', 'menucraft' ) ); ?>
					<?php $tile( $variants_total,      __( 'Variants in total', 'menucraft' ) ); ?>
					<?php $tile( $items_no_image,      __( 'Without image', 'menucraft' ), $items_no_image > 0 ? 'warn' : '' ); ?>
				</div>
			</section>

			<?php // -------------------- Taxonomies -------------------- ?>
			<section class="menucraft-counters">
				<h2 class="menucraft-counters-title"><?php esc_html_e( 'Categories, tags & allergens', 'menucraft' ); ?></h2>
				<div class="menucraft-counters-grid">
					<?php $tile( count( $categories ), __( 'Categories', 'menucraft' ) ); ?>
					<?php $tile( count( $tags ),       __( 'Tags', 'menucraft' ) ); ?>
					<?php $tile( count( $allergens ),  __( 'Allergens', 'menucraft' ) ); ?>
				</div>
			</section>

		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>
</div>
