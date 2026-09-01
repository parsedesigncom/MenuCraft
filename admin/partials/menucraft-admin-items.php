<?php
/**
 * Items admin screen.
 *
 * Table skeleton (populated by JS from REST), main create/edit off-canvas
 * with chip selectors for categories/tags/allergens, a variants sub-panel
 * layered on top of the main panel, and a delete confirmation modal.
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
					data-menucraft-panel-open="menucraft-panel-item-form"
					data-menucraft-panel-mode="create">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'New Item', 'menucraft' ); ?>
				</button>
			</div>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'Menu items — food and drinks. Assign categories, tags and allergens, and define size/portion variants for pricing.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">
			<div class="menucraft-filters menucraft-filters-collapsed" data-menucraft-filters="items">
				<div class="menucraft-filters-header"
					data-menucraft-filters-toggle
					role="button"
					tabindex="0"
					aria-expanded="false"
					aria-controls="menucraft-filters-body-items">
					<span class="menucraft-filters-chevron dashicons dashicons-arrow-right" aria-hidden="true"></span>
					<span class="menucraft-filters-title">
						<span class="dashicons dashicons-filter" aria-hidden="true"></span>
						<?php esc_html_e( 'Filters', 'menucraft' ); ?>
					</span>
					<span class="menucraft-filters-count" data-menucraft-filters-count hidden></span>
					<button type="button"
						class="button-link menucraft-filters-reset"
						data-menucraft-filters-reset>
						<?php esc_html_e( 'Reset', 'menucraft' ); ?>
					</button>
				</div>
				<div class="menucraft-filters-body" id="menucraft-filters-body-items">
					<div class="menucraft-filter-field menucraft-filter-search">
						<label for="menucraft-filter-search">
							<?php esc_html_e( 'Search', 'menucraft' ); ?>
						</label>
						<input type="search"
							id="menucraft-filter-search"
							data-menucraft-filter="search"
							placeholder="<?php esc_attr_e( 'Name or description…', 'menucraft' ); ?>">
					</div>

					<div class="menucraft-filter-field">
						<label><?php esc_html_e( 'Categories', 'menucraft' ); ?></label>
						<div class="menucraft-chips menucraft-chips-filter"
							data-menucraft-chips="categories"
							data-menucraft-chips-name="filter_categories"
							data-menucraft-chips-empty="<?php esc_attr_e( 'No categories.', 'menucraft' ); ?>"></div>
					</div>

					<div class="menucraft-filter-field">
						<label><?php esc_html_e( 'Tags', 'menucraft' ); ?></label>
						<div class="menucraft-chips menucraft-chips-filter"
							data-menucraft-chips="tags"
							data-menucraft-chips-name="filter_tags"
							data-menucraft-chips-empty="<?php esc_attr_e( 'No tags.', 'menucraft' ); ?>"></div>
					</div>

					<div class="menucraft-filter-field">
						<label><?php esc_html_e( 'Allergens', 'menucraft' ); ?></label>
						<div class="menucraft-chips menucraft-chips-filter"
							data-menucraft-chips="allergens"
							data-menucraft-chips-name="filter_allergens"
							data-menucraft-chips-empty="<?php esc_attr_e( 'No allergens.', 'menucraft' ); ?>"></div>
					</div>

					<div class="menucraft-filter-row">
						<div class="menucraft-filter-field">
							<label for="menucraft-filter-status">
								<?php esc_html_e( 'Status', 'menucraft' ); ?>
							</label>
							<select id="menucraft-filter-status" data-menucraft-filter="status">
								<option value=""><?php esc_html_e( 'All', 'menucraft' ); ?></option>
								<option value="active"><?php esc_html_e( 'Active', 'menucraft' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'menucraft' ); ?></option>
							</select>
						</div>

						<div class="menucraft-filter-field">
							<label><?php esc_html_e( 'Price', 'menucraft' ); ?></label>
							<div class="menucraft-filter-range">
								<input type="number"
									step="0.01"
									min="0"
									data-menucraft-filter="price_min"
									placeholder="<?php esc_attr_e( 'From', 'menucraft' ); ?>">
								<span aria-hidden="true">–</span>
								<input type="number"
									step="0.01"
									min="0"
									data-menucraft-filter="price_max"
									placeholder="<?php esc_attr_e( 'To', 'menucraft' ); ?>">
							</div>
						</div>

						<div class="menucraft-filter-field">
							<label for="menucraft-filter-image">
								<?php esc_html_e( 'Image', 'menucraft' ); ?>
							</label>
							<select id="menucraft-filter-image" data-menucraft-filter="image">
								<option value=""><?php esc_html_e( 'All', 'menucraft' ); ?></option>
								<option value="with"><?php esc_html_e( 'With image', 'menucraft' ); ?></option>
								<option value="without"><?php esc_html_e( 'Without image', 'menucraft' ); ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<div class="menucraft-bulk-toolbar" data-menucraft-bulk-toolbar hidden>
				<span class="menucraft-bulk-count">
					<strong data-menucraft-bulk-count>0</strong>
					<span><?php esc_html_e( 'selected', 'menucraft' ); ?></span>
				</span>
				<div class="menucraft-bulk-actions">
					<button type="button"
						class="button button-primary"
						data-menucraft-bulk-open="menucraft-panel-item-bulk">
						<?php esc_html_e( 'Bulk Edit…', 'menucraft' ); ?>
					</button>
					<button type="button" class="button" data-menucraft-bulk-clear>
						<?php esc_html_e( 'Clear selection', 'menucraft' ); ?>
					</button>
				</div>
			</div>

			<table class="wp-list-table widefat striped fixed menucraft-table menucraft-items-table"
				data-menucraft-list="items"
				data-menucraft-selectable
				data-menucraft-panel="menucraft-panel-item-form"
				data-menucraft-modal-delete="menucraft-modal-delete-item">
				<thead>
					<tr>
						<th scope="col" class="menucraft-col-select">
							<input type="checkbox"
								data-menucraft-select-all
								aria-label="<?php esc_attr_e( 'Select all', 'menucraft' ); ?>">
						</th>
						<th scope="col" class="menucraft-col-thumb"><?php esc_html_e( 'Image', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-name"><?php esc_html_e( 'Name', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-categories"><?php esc_html_e( 'Categories', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-price"><?php esc_html_e( 'Price', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-active"><?php esc_html_e( 'Active', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-dates"><?php esc_html_e( 'Dates', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-actions"><?php esc_html_e( 'Actions', 'menucraft' ); ?></th>
					</tr>
				</thead>
				<tbody data-menucraft-list-body>
					<tr class="menucraft-row-status">
						<td colspan="8"><?php esc_html_e( 'Loading…', 'menucraft' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>

	<?php // -------- Main off-canvas panel: create/edit item -------- ?>
	<aside class="menucraft-offcanvas" id="menucraft-panel-item-form" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-item-form-title">
			<form class="menucraft-form"
				data-menucraft-endpoint="items"
				data-menucraft-mode="create">
				<header class="menucraft-offcanvas-header">
					<h2 class="menucraft-offcanvas-title"
						id="menucraft-panel-item-form-title"
						data-menucraft-title-create="<?php esc_attr_e( 'New Item', 'menucraft' ); ?>"
						data-menucraft-title-edit="<?php esc_attr_e( 'Edit Item', 'menucraft' ); ?>">
						<?php esc_html_e( 'New Item', 'menucraft' ); ?>
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
						<label for="menucraft-item-name">
							<?php esc_html_e( 'Name', 'menucraft' ); ?>
							<span class="menucraft-required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="menucraft-item-name" name="name" required>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-item-desc-short"><?php esc_html_e( 'Short Description', 'menucraft' ); ?></label>
						<textarea id="menucraft-item-desc-short" name="description_short" rows="2" maxlength="500"></textarea>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-item-desc-long"><?php esc_html_e( 'Long Description', 'menucraft' ); ?></label>
						<textarea id="menucraft-item-desc-long" name="description_long" rows="5"></textarea>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-item-price">
							<?php esc_html_e( 'Base Price', 'menucraft' ); ?>
						</label>
						<input type="number"
							id="menucraft-item-price"
							name="price"
							step="0.01"
							min="0"
							placeholder="<?php esc_attr_e( 'Leave empty when using variants only', 'menucraft' ); ?>">
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
						<label><?php esc_html_e( 'Categories', 'menucraft' ); ?></label>
						<div class="menucraft-chips"
							data-menucraft-chips="categories"
							data-menucraft-chips-name="category_ids"
							data-menucraft-chips-empty="<?php esc_attr_e( 'No categories yet — create some first.', 'menucraft' ); ?>">
						</div>
					</div>

					<div class="menucraft-field">
						<label><?php esc_html_e( 'Tags', 'menucraft' ); ?></label>
						<div class="menucraft-chips"
							data-menucraft-chips="tags"
							data-menucraft-chips-name="tag_ids"
							data-menucraft-chips-empty="<?php esc_attr_e( 'No tags yet — create some first.', 'menucraft' ); ?>">
						</div>
					</div>

					<div class="menucraft-field">
						<label><?php esc_html_e( 'Allergens', 'menucraft' ); ?></label>
						<div class="menucraft-chips"
							data-menucraft-chips="allergens"
							data-menucraft-chips-name="allergen_ids"
							data-menucraft-chips-empty="<?php esc_attr_e( 'No allergens yet — create some first.', 'menucraft' ); ?>">
						</div>
					</div>

					<div class="menucraft-field">
						<label><?php esc_html_e( 'Variants', 'menucraft' ); ?></label>
						<div class="menucraft-variants-summary" data-menucraft-variants-summary>
							<span class="menucraft-variants-count" data-menucraft-variants-count>
								<?php esc_html_e( 'None', 'menucraft' ); ?>
							</span>
							<button type="button"
								class="button"
								data-menucraft-subpanel-open="menucraft-panel-item-variants"
								data-menucraft-subpanel-parent="menucraft-panel-item-form">
								<?php esc_html_e( 'Manage Variants', 'menucraft' ); ?>
							</button>
						</div>
						<p class="menucraft-field-help">
							<?php esc_html_e( 'Add size or portion variants (Small / Medium / Large …). When variants are set, the item is priced from the smallest variant.', 'menucraft' ); ?>
						</p>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-item-sort"><?php esc_html_e( 'Sort Order', 'menucraft' ); ?></label>
						<input type="number" id="menucraft-item-sort" name="sort_order" value="0" step="1" min="0">
					</div>

					<div class="menucraft-field menucraft-field-checkbox">
						<label for="menucraft-item-active">
							<input type="checkbox" id="menucraft-item-active" name="is_active" value="1" checked>
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
						data-menucraft-label-create="<?php esc_attr_e( 'Save Item', 'menucraft' ); ?>"
						data-menucraft-label-edit="<?php esc_attr_e( 'Update Item', 'menucraft' ); ?>">
						<?php esc_html_e( 'Save Item', 'menucraft' ); ?>
					</button>
				</footer>
			</form>
		</div>
	</aside>

	<?php // -------- Sub-panel: variants (opened from main item panel) -------- ?>
	<aside class="menucraft-offcanvas menucraft-offcanvas-sub" id="menucraft-panel-item-variants" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-item-variants-title">
			<header class="menucraft-offcanvas-header">
				<div class="menucraft-offcanvas-title-group">
					<span class="menucraft-offcanvas-breadcrumb">
						<?php esc_html_e( 'Item', 'menucraft' ); ?>
						<span class="menucraft-breadcrumb-sep" aria-hidden="true">›</span>
					</span>
					<h2 class="menucraft-offcanvas-title" id="menucraft-panel-item-variants-title">
						<?php esc_html_e( 'Variants', 'menucraft' ); ?>
					</h2>
				</div>
				<button type="button"
					class="menucraft-offcanvas-close"
					data-menucraft-panel-close
					aria-label="<?php esc_attr_e( 'Close', 'menucraft' ); ?>">
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
				</button>
			</header>

			<div class="menucraft-offcanvas-body">
				<p class="menucraft-field-help">
					<?php esc_html_e( 'Each variant has a label (Small / Medium …) and its own price. Empty labels are skipped on save.', 'menucraft' ); ?>
				</p>

				<div class="menucraft-variants-list" data-menucraft-variants-list>
					<?php // Rendered by JS from the item form's in-memory state. ?>
				</div>

				<div class="menucraft-variants-empty" data-menucraft-variants-empty hidden>
					<?php esc_html_e( 'No variants yet.', 'menucraft' ); ?>
				</div>

				<button type="button" class="button menucraft-variants-add" data-menucraft-variant-add>
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add Variant', 'menucraft' ); ?>
				</button>
			</div>

			<footer class="menucraft-offcanvas-footer">
				<button type="button" class="button button-primary" data-menucraft-panel-close>
					<?php esc_html_e( 'Done', 'menucraft' ); ?>
				</button>
			</footer>
		</div>
	</aside>

	<?php // -------- Bulk-edit off-canvas -------- ?>
	<aside class="menucraft-offcanvas" id="menucraft-panel-item-bulk" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-item-bulk-title">
			<form class="menucraft-form" data-menucraft-bulk-form>
				<header class="menucraft-offcanvas-header">
					<h2 class="menucraft-offcanvas-title" id="menucraft-panel-item-bulk-title">
						<?php esc_html_e( 'Bulk Edit', 'menucraft' ); ?>
						<span class="menucraft-offcanvas-title-badge">
							<strong data-menucraft-bulk-panel-count>0</strong>
							<?php esc_html_e( 'items', 'menucraft' ); ?>
						</span>
					</h2>
					<button type="button"
						class="menucraft-offcanvas-close"
						data-menucraft-panel-close
						aria-label="<?php esc_attr_e( 'Close', 'menucraft' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</header>

				<div class="menucraft-offcanvas-body">
					<p class="menucraft-field-help">
						<?php esc_html_e( 'Only fields with a mode selected are applied. Others are left untouched.', 'menucraft' ); ?>
					</p>

					<?php
					$bulk_relations = array(
						array(
							'label'      => __( 'Categories', 'menucraft' ),
							'chips_key'  => 'categories',
							'mode_name'  => 'categories_mode',
							'chips_name' => 'categories_ids',
							'empty'      => __( 'No categories yet.', 'menucraft' ),
						),
						array(
							'label'      => __( 'Tags', 'menucraft' ),
							'chips_key'  => 'tags',
							'mode_name'  => 'tags_mode',
							'chips_name' => 'tags_ids',
							'empty'      => __( 'No tags yet.', 'menucraft' ),
						),
						array(
							'label'      => __( 'Allergens', 'menucraft' ),
							'chips_key'  => 'allergens',
							'mode_name'  => 'allergens_mode',
							'chips_name' => 'allergens_ids',
							'empty'      => __( 'No allergens yet.', 'menucraft' ),
						),
					);
					?>
					<?php foreach ( $bulk_relations as $rel ) : ?>
						<div class="menucraft-bulk-op">
							<label class="menucraft-bulk-op-label"><?php echo esc_html( $rel['label'] ); ?></label>
							<select class="menucraft-bulk-mode" name="<?php echo esc_attr( $rel['mode_name'] ); ?>">
								<option value=""><?php esc_html_e( 'No change', 'menucraft' ); ?></option>
								<option value="replace"><?php esc_html_e( 'Replace with…', 'menucraft' ); ?></option>
								<option value="add"><?php esc_html_e( 'Add these…', 'menucraft' ); ?></option>
								<option value="remove"><?php esc_html_e( 'Remove these…', 'menucraft' ); ?></option>
							</select>
							<div class="menucraft-chips"
								data-menucraft-chips="<?php echo esc_attr( $rel['chips_key'] ); ?>"
								data-menucraft-chips-name="<?php echo esc_attr( $rel['chips_name'] ); ?>"
								data-menucraft-chips-empty="<?php echo esc_attr( $rel['empty'] ); ?>">
							</div>
						</div>
					<?php endforeach; ?>

					<div class="menucraft-bulk-op">
						<label class="menucraft-bulk-op-label" for="menucraft-bulk-base-price-mode">
							<?php esc_html_e( 'Base Price', 'menucraft' ); ?>
						</label>
						<select class="menucraft-bulk-mode" id="menucraft-bulk-base-price-mode" name="base_price_mode">
							<option value=""><?php esc_html_e( 'No change', 'menucraft' ); ?></option>
							<option value="replace"><?php esc_html_e( 'Set to…', 'menucraft' ); ?></option>
							<option value="increase"><?php esc_html_e( 'Increase by…', 'menucraft' ); ?></option>
							<option value="decrease"><?php esc_html_e( 'Decrease by…', 'menucraft' ); ?></option>
						</select>
						<input type="number"
							name="base_price_value"
							step="0.01"
							min="0"
							placeholder="<?php esc_attr_e( 'Amount', 'menucraft' ); ?>">
						<p class="menucraft-field-help">
							<?php esc_html_e( 'Items without a base price are skipped when increasing or decreasing.', 'menucraft' ); ?>
						</p>
					</div>

					<div class="menucraft-bulk-op">
						<label class="menucraft-bulk-op-label" for="menucraft-bulk-variant-prices-mode">
							<?php esc_html_e( 'Variant Prices', 'menucraft' ); ?>
						</label>
						<select class="menucraft-bulk-mode" id="menucraft-bulk-variant-prices-mode" name="variant_prices_mode">
							<option value=""><?php esc_html_e( 'No change', 'menucraft' ); ?></option>
							<option value="increase"><?php esc_html_e( 'Increase by…', 'menucraft' ); ?></option>
							<option value="decrease"><?php esc_html_e( 'Decrease by…', 'menucraft' ); ?></option>
						</select>
						<input type="number"
							name="variant_prices_value"
							step="0.01"
							min="0"
							placeholder="<?php esc_attr_e( 'Amount', 'menucraft' ); ?>">
						<p class="menucraft-field-help">
							<?php esc_html_e( 'Applies to every variant of every selected item. Prices are floored at 0.', 'menucraft' ); ?>
						</p>
					</div>

					<div class="menucraft-bulk-op">
						<label class="menucraft-bulk-op-label" for="menucraft-bulk-is-active-mode">
							<?php esc_html_e( 'Active State', 'menucraft' ); ?>
						</label>
						<select class="menucraft-bulk-mode" id="menucraft-bulk-is-active-mode" name="is_active_mode">
							<option value=""><?php esc_html_e( 'No change', 'menucraft' ); ?></option>
							<option value="1"><?php esc_html_e( 'Activate', 'menucraft' ); ?></option>
							<option value="0"><?php esc_html_e( 'Deactivate', 'menucraft' ); ?></option>
						</select>
					</div>
				</div>

				<footer class="menucraft-offcanvas-footer">
					<button type="button" class="button" data-menucraft-panel-close>
						<?php esc_html_e( 'Cancel', 'menucraft' ); ?>
					</button>
					<button type="submit" class="button button-primary" data-menucraft-submit>
						<?php esc_html_e( 'Apply', 'menucraft' ); ?>
					</button>
				</footer>
			</form>
		</div>
	</aside>

	<?php // -------- Confirm delete modal -------- ?>
	<div class="menucraft-modal" id="menucraft-modal-delete-item" aria-hidden="true" role="dialog" aria-modal="true">
		<div class="menucraft-modal-backdrop" data-menucraft-modal-close></div>
		<div class="menucraft-modal-dialog" aria-labelledby="menucraft-modal-delete-item-title">
			<header class="menucraft-modal-header">
				<h2 class="menucraft-modal-title" id="menucraft-modal-delete-item-title">
					<?php esc_html_e( 'Delete item?', 'menucraft' ); ?>
				</h2>
			</header>
			<div class="menucraft-modal-body">
				<p>
					<?php
					printf(
						/* translators: %s: item name placeholder replaced by JS. */
						esc_html__( 'Are you sure you want to delete %s? This also removes its variants and assignments. This cannot be undone.', 'menucraft' ),
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
