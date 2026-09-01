<?php
/**
 * Tags admin screen.
 *
 * Same structure as the Categories screen (tags share the schema);
 * only the identifiers, labels and REST endpoint differ.
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
					data-menucraft-panel-open="menucraft-panel-tag-form"
					data-menucraft-panel-mode="create">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'New Tag', 'menucraft' ); ?>
				</button>
			</div>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'Label items with tags such as vegan, halal, gluten-free.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">
			<table class="wp-list-table widefat striped fixed menucraft-table menucraft-tags-table"
				data-menucraft-list="tags"
				data-menucraft-panel="menucraft-panel-tag-form"
				data-menucraft-modal-delete="menucraft-modal-delete-tag">
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
					<tr class="menucraft-row-status">
						<td colspan="7"><?php esc_html_e( 'Loading…', 'menucraft' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>

	<?php // -------- Off-canvas panel: create/edit -------- ?>
	<aside class="menucraft-offcanvas" id="menucraft-panel-tag-form" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-tag-form-title">
			<form class="menucraft-form"
				data-menucraft-endpoint="tags"
				data-menucraft-mode="create">
				<header class="menucraft-offcanvas-header">
					<h2 class="menucraft-offcanvas-title"
						id="menucraft-panel-tag-form-title"
						data-menucraft-title-create="<?php esc_attr_e( 'New Tag', 'menucraft' ); ?>"
						data-menucraft-title-edit="<?php esc_attr_e( 'Edit Tag', 'menucraft' ); ?>">
						<?php esc_html_e( 'New Tag', 'menucraft' ); ?>
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
						<label for="menucraft-tag-name">
							<?php esc_html_e( 'Name', 'menucraft' ); ?>
							<span class="menucraft-required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="menucraft-tag-name" name="name" required>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-tag-description"><?php esc_html_e( 'Description', 'menucraft' ); ?></label>
						<textarea id="menucraft-tag-description" name="description" rows="4"></textarea>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-tag-color"><?php esc_html_e( 'Color', 'menucraft' ); ?></label>
						<input type="color" id="menucraft-tag-color" name="color" value="#3858e9">
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
						<label for="menucraft-tag-parent"><?php esc_html_e( 'Parent Tag', 'menucraft' ); ?></label>
						<select id="menucraft-tag-parent" name="parent_id" data-menucraft-parent-select>
							<option value=""><?php esc_html_e( '— None —', 'menucraft' ); ?></option>
						</select>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-tag-sort"><?php esc_html_e( 'Sort Order', 'menucraft' ); ?></label>
						<input type="number" id="menucraft-tag-sort" name="sort_order" value="0" step="1" min="0">
					</div>

					<div class="menucraft-field menucraft-field-checkbox">
						<label for="menucraft-tag-active">
							<input type="checkbox" id="menucraft-tag-active" name="is_active" value="1" checked>
							<?php esc_html_e( 'Active', 'menucraft' ); ?>
						</label>
					</div>
				</div>

				<footer class="menucraft-offcanvas-footer">
					<button type="button" class="button" data-menucraft-panel-close>
						<?php esc_html_e( 'Cancel', 'menucraft' ); ?>
					</button>
					<button type="submit"
						class="button button-primary"
						data-menucraft-submit
						data-menucraft-label-create="<?php esc_attr_e( 'Save Tag', 'menucraft' ); ?>"
						data-menucraft-label-edit="<?php esc_attr_e( 'Update Tag', 'menucraft' ); ?>">
						<?php esc_html_e( 'Save Tag', 'menucraft' ); ?>
					</button>
				</footer>
			</form>
		</div>
	</aside>

	<?php // -------- Confirm delete modal -------- ?>
	<div class="menucraft-modal" id="menucraft-modal-delete-tag" aria-hidden="true" role="dialog" aria-modal="true">
		<div class="menucraft-modal-backdrop" data-menucraft-modal-close></div>
		<div class="menucraft-modal-dialog" aria-labelledby="menucraft-modal-delete-tag-title">
			<header class="menucraft-modal-header">
				<h2 class="menucraft-modal-title" id="menucraft-modal-delete-tag-title">
					<?php esc_html_e( 'Delete tag?', 'menucraft' ); ?>
				</h2>
			</header>
			<div class="menucraft-modal-body">
				<p>
					<?php
					printf(
						/* translators: %s: tag name placeholder replaced by JS. */
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
