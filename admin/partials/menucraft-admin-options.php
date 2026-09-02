<?php
/**
 * Options admin screen.
 *
 * Plugin-wide settings. Loaded and persisted via REST /options.
 * Currency change takes effect after page reload (asset localization
 * happens server-side).
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap menucraft-wrap">
	<div class="menucraft-card">
		<header class="menucraft-page-header">
			<div class="menucraft-page-header-row">
				<h1 class="menucraft-page-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			</div>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'Plugin-wide settings. Changes take effect after saving; some fields (currency prefix) require a page reload to appear across the admin.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">
			<form class="menucraft-form menucraft-options-form"
				data-menucraft-options-form>
				<div class="menucraft-field">
					<label for="menucraft-option-currency">
						<?php esc_html_e( 'Currency Symbol', 'menucraft' ); ?>
					</label>
					<input type="text"
						id="menucraft-option-currency"
						name="currency"
						maxlength="10"
						placeholder="€"
						class="menucraft-input-narrow">
					<p class="menucraft-field-help">
						<?php esc_html_e( 'Shown as a non-editable prefix on every price input across the admin (e.g. €, $, CHF).', 'menucraft' ); ?>
					</p>
				</div>

				<footer class="menucraft-form-footer">
					<button type="submit"
						class="button button-primary"
						data-menucraft-submit>
						<?php esc_html_e( 'Save Options', 'menucraft' ); ?>
					</button>
				</footer>
			</form>
		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>
</div>
