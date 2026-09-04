<?php
/**
 * Categories admin screen.
 *
 * Table skeleton is rendered here and populated by JS from the REST
 * endpoint. The off-canvas panel doubles as create and edit surface
 * (mode is toggled by JS via data-menucraft-mode). Delete uses a
 * separate centered modal for confirmation.
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
				<button type="button"
					class="button button-primary menucraft-btn-add"
					data-menucraft-panel-open="menucraft-panel-category-form"
					data-menucraft-panel-mode-target="menucraft-panel-category-form"
					data-menucraft-panel-mode="create">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'New Category', 'menucraft' ); ?>
				</button>
			</div>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'Group menu items into categories such as Coffee, Snacks or Desserts.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">
			<table class="wp-list-table widefat striped fixed menucraft-table menucraft-categories-table"
				data-menucraft-list="categories"
				data-menucraft-panel="menucraft-panel-category-form"
				data-menucraft-modal-delete="menucraft-modal-delete-category">
				<thead>
					<tr>
						<th scope="col" class="menucraft-col-thumb"><?php esc_html_e( 'Image', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-name"><?php esc_html_e( 'Name', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-color"><?php esc_html_e( 'Color', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-desc"><?php esc_html_e( 'Description', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-active"><?php esc_html_e( 'Active', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-dates"><?php esc_html_e( 'Dates', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-actions"><?php esc_html_e( 'Actions', 'menucraft' ); ?></th>
					</tr>
				</thead>
				<tbody data-menucraft-list-body>
					<tr class="menucraft-row-status" data-menucraft-list-loading>
						<td colspan="7"><?php esc_html_e( 'Loading…', 'menucraft' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>

	<?php // -------- Off-canvas panel: create/edit (mode set via JS) -------- ?>
	<aside class="menucraft-offcanvas" id="menucraft-panel-category-form" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-category-form-title">
			<form class="menucraft-form"
				data-menucraft-endpoint="categories"
				data-menucraft-mode="create">
				<header class="menucraft-offcanvas-header">
					<h2 class="menucraft-offcanvas-title"
						id="menucraft-panel-category-form-title"
						data-menucraft-title-create="<?php esc_attr_e( 'New Category', 'menucraft' ); ?>"
						data-menucraft-title-edit="<?php esc_attr_e( 'Edit Category', 'menucraft' ); ?>">
						<?php esc_html_e( 'New Category', 'menucraft' ); ?>
					</h2>
					<button type="button"
						class="menucraft-offcanvas-close"
						data-menucraft-panel-close
						aria-label="<?php esc_attr_e( 'Close', 'menucraft' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</header>

				<div class="menucraft-offcanvas-body">
					<div class="menucraft-field">
						<label for="menucraft-cat-name">
							<?php esc_html_e( 'Name', 'menucraft' ); ?>
							<span class="menucraft-required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="menucraft-cat-name" name="name" required>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-cat-description"><?php esc_html_e( 'Description', 'menucraft' ); ?></label>
						<textarea id="menucraft-cat-description" name="description" rows="4"></textarea>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-cat-color"><?php esc_html_e( 'Color', 'menucraft' ); ?></label>
						<input type="color" id="menucraft-cat-color" name="color" value="#3858e9">
					</div>

					<div class="menucraft-field menucraft-field-media">
						<label><?php esc_html_e( 'Image', 'menucraft' ); ?></label>
						<div class="menucraft-media-picker" data-menucraft-media-picker>
							<div class="menucraft-media-preview"
								data-menucraft-media-preview
								data-empty="<?php esc_attr_e( 'No image selected', 'menucraft' ); ?>"></div>
							<div class="menucraft-media-actions">
								<button type="button" class="button" data-menucraft-media-choose>
									<?php esc_html_e( 'Choose Image', 'menucraft' ); ?>
								</button>
								<button type="button" class="button-link menucraft-media-remove" data-menucraft-media-remove hidden>
									<?php esc_html_e( 'Remove', 'menucraft' ); ?>
								</button>
							</div>
							<input type="hidden" name="media_id" value="0" data-menucraft-media-input>
						</div>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-cat-sort"><?php esc_html_e( 'Sort Order', 'menucraft' ); ?></label>
						<input type="number" id="menucraft-cat-sort" name="sort_order" value="0" step="1" min="0">
					</div>

					<div class="menucraft-field menucraft-field-checkbox">
						<label for="menucraft-cat-active">
							<input type="checkbox" id="menucraft-cat-active" name="is_active" value="1" checked>
							<?php esc_html_e( 'Active', 'menucraft' ); ?>
						</label>
					</div>

					<div class="menucraft-field menucraft-field-checkbox">
						<label for="menucraft-cat-default">
							<input type="checkbox" id="menucraft-cat-default" name="is_default" value="1">
							<?php esc_html_e( 'Set as default', 'menucraft' ); ?>
						</label>
						<p class="menucraft-field-help">
							<?php esc_html_e( 'When set, this category is pre-selected in the frontend filter so the menu opens focused on this section. Only one category can be default at a time.', 'menucraft' ); ?>
						</p>
					</div>
				</div>

				<footer class="menucraft-offcanvas-footer">
					<button type="button" class="button" data-menucraft-panel-close>
						<?php esc_html_e( 'Cancel', 'menucraft' ); ?>
					</button>
					<button type="submit"
						class="button button-primary"
						data-menucraft-submit
						data-menucraft-label-create="<?php esc_attr_e( 'Save Category', 'menucraft' ); ?>"
						data-menucraft-label-edit="<?php esc_attr_e( 'Update Category', 'menucraft' ); ?>">
						<?php esc_html_e( 'Save Category', 'menucraft' ); ?>
					</button>
				</footer>
			</form>
		</div>
	</aside>

	<?php // -------- Confirm delete modal -------- ?>
	<div class="menucraft-modal" id="menucraft-modal-delete-category" aria-hidden="true" role="dialog" aria-modal="true">
		<div class="menucraft-modal-backdrop" data-menucraft-modal-close></div>
		<div class="menucraft-modal-dialog" aria-labelledby="menucraft-modal-delete-category-title">
			<header class="menucraft-modal-header">
				<h2 class="menucraft-modal-title" id="menucraft-modal-delete-category-title">
					<?php esc_html_e( 'Delete category?', 'menucraft' ); ?>
				</h2>
			</header>
			<div class="menucraft-modal-body">
				<p>
					<?php
					printf(
						/* translators: %s: category name placeholder replaced by JS. */
						esc_html__( 'Are you sure you want to delete %s? This cannot be undone.', 'menucraft' ),
						'<strong data-menucraft-modal-target-name>—</strong>'
					);
					?>
				</p>
			</div>
			<footer class="menucraft-modal-footer">
				<button type="button" class="button" data-menucraft-modal-close>
					<?php esc_html_e( 'Cancel', 'menucraft' ); ?>
				</button>
				<button type="button"
					class="button menucraft-btn-danger"
					data-menucraft-modal-confirm-delete>
					<?php esc_html_e( 'Delete', 'menucraft' ); ?>
				</button>
			</footer>
		</div>
	</div>
</div>
