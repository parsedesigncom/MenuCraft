<?php
/**
 * Main MenuCraft admin page view (Dashboard).
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap menucraft-wrap">
	<div class="menucraft-card">
		<header class="menucraft-page-header">
			<h1 class="menucraft-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'Manage your restaurant or cafe menu — items, categories, tags, allergens and offers — from one place.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>
		<div class="menucraft-page-body">
			<p><?php esc_html_e( 'Welcome to MenuCraft. Features will appear here.', 'menucraft' ); ?></p>
		</div>
		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>
</div>
