<?php
/**
 * Offers admin screen.
 *
 * Table populated by JS from REST, main create/edit off-canvas with a
 * chips-plus-rows picker for line items (chip = add a row for that item,
 * row configures variant + quantity), and a delete confirmation modal.
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
					data-menucraft-panel-open="menucraft-panel-offer-form"
					data-menucraft-panel-mode="create">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'New Offer', 'menucraft' ); ?>
				</button>
			</div>
			<p class="menucraft-page-description">
				<?php esc_html_e( 'Bundle single items or combos at a fixed price with an optional validity window and free-form conditions.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">
			<div class="menucraft-filters menucraft-filters-collapsed" data-menucraft-filters="offers">
				<div class="menucraft-filters-header"
					data-menucraft-filters-toggle
					role="button"
					tabindex="0"
					aria-expanded="false"
					aria-controls="menucraft-filters-body-offers">
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
				<div class="menucraft-filters-body" id="menucraft-filters-body-offers">
					<div class="menucraft-filter-field menucraft-filter-search">
						<label for="menucraft-filter-offers-search">
							<?php esc_html_e( 'Search', 'menucraft' ); ?>
						</label>
						<input type="search"
							id="menucraft-filter-offers-search"
							data-menucraft-filter="search"
							placeholder="<?php esc_attr_e( 'Name, description or conditions…', 'menucraft' ); ?>">
					</div>

					<div class="menucraft-filter-row">
						<div class="menucraft-filter-field">
							<label for="menucraft-filter-offers-status">
								<?php esc_html_e( 'Status', 'menucraft' ); ?>
							</label>
							<select id="menucraft-filter-offers-status" data-menucraft-filter="status">
								<option value=""><?php esc_html_e( 'All', 'menucraft' ); ?></option>
								<option value="active"><?php esc_html_e( 'Active', 'menucraft' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'menucraft' ); ?></option>
							</select>
						</div>

						<div class="menucraft-filter-field">
							<label for="menucraft-filter-offers-validity">
								<?php esc_html_e( 'Validity', 'menucraft' ); ?>
							</label>
							<select id="menucraft-filter-offers-validity" data-menucraft-filter="validity">
								<option value=""><?php esc_html_e( 'All', 'menucraft' ); ?></option>
								<option value="current"><?php esc_html_e( 'Currently valid', 'menucraft' ); ?></option>
								<option value="upcoming"><?php esc_html_e( 'Upcoming', 'menucraft' ); ?></option>
								<option value="expired"><?php esc_html_e( 'Expired', 'menucraft' ); ?></option>
								<option value="always"><?php esc_html_e( 'No date limit', 'menucraft' ); ?></option>
							</select>
						</div>

						<div class="menucraft-filter-field">
							<label><?php esc_html_e( 'Price', 'menucraft' ); ?></label>
							<div class="menucraft-filter-range">
								<input type="number"
									step="0.01"
									min="0"
									data-menucraft-filter="price_min"
									data-menucraft-price
									placeholder="<?php esc_attr_e( 'From', 'menucraft' ); ?>">
								<span aria-hidden="true">–</span>
								<input type="number"
									step="0.01"
									min="0"
									data-menucraft-filter="price_max"
									data-menucraft-price
									placeholder="<?php esc_attr_e( 'To', 'menucraft' ); ?>">
							</div>
						</div>

						<div class="menucraft-filter-field">
							<label for="menucraft-filter-offers-image">
								<?php esc_html_e( 'Image', 'menucraft' ); ?>
							</label>
							<select id="menucraft-filter-offers-image" data-menucraft-filter="image">
								<option value=""><?php esc_html_e( 'All', 'menucraft' ); ?></option>
								<option value="with"><?php esc_html_e( 'With image', 'menucraft' ); ?></option>
								<option value="without"><?php esc_html_e( 'Without image', 'menucraft' ); ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<table class="wp-list-table widefat striped fixed menucraft-table menucraft-offers-table"
				data-menucraft-list="offers"
				data-menucraft-panel="menucraft-panel-offer-form"
				data-menucraft-modal-delete="menucraft-modal-delete-offer">
				<thead>
					<tr>
						<th scope="col" class="menucraft-col-thumb"><?php esc_html_e( 'Image', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-name"><?php esc_html_e( 'Name', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-price"><?php esc_html_e( 'Price', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-validity"><?php esc_html_e( 'Validity', 'menucraft' ); ?></th>
						<th scope="col" class="menucraft-col-items"><?php esc_html_e( 'Items', 'menucraft' ); ?></th>
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

	<?php // -------- Main off-canvas panel: create/edit offer -------- ?>
	<aside class="menucraft-offcanvas" id="menucraft-panel-offer-form" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-offer-form-title">
			<form class="menucraft-form"
				data-menucraft-endpoint="offers"
				data-menucraft-mode="create">
				<header class="menucraft-offcanvas-header">
					<h2 class="menucraft-offcanvas-title"
						id="menucraft-panel-offer-form-title"
						data-menucraft-title-create="<?php esc_attr_e( 'New Offer', 'menucraft' ); ?>"
						data-menucraft-title-edit="<?php esc_attr_e( 'Edit Offer', 'menucraft' ); ?>">
						<?php esc_html_e( 'New Offer', 'menucraft' ); ?>
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
						<label for="menucraft-offer-name">
							<?php esc_html_e( 'Name', 'menucraft' ); ?>
							<span class="menucraft-required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="menucraft-offer-name" name="name" required>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-offer-description"><?php esc_html_e( 'Description', 'menucraft' ); ?></label>
						<textarea id="menucraft-offer-description" name="description" rows="3"></textarea>
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
						<label for="menucraft-offer-price">
							<?php esc_html_e( 'Total Price', 'menucraft' ); ?>
							<span class="menucraft-required" aria-hidden="true">*</span>
						</label>
						<input type="number"
							id="menucraft-offer-price"
							name="price"
							step="0.01"
							min="0"
							required
							data-menucraft-price
							placeholder="0.00">
						<p class="menucraft-field-help">
							<?php esc_html_e( 'One fixed price for the whole offer, regardless of the number of items.', 'menucraft' ); ?>
						</p>
					</div>

					<div class="menucraft-field-row">
						<div class="menucraft-field">
							<label for="menucraft-offer-valid-from"><?php esc_html_e( 'Valid From', 'menucraft' ); ?></label>
							<input type="datetime-local" id="menucraft-offer-valid-from" name="valid_from">
						</div>
						<div class="menucraft-field">
							<label for="menucraft-offer-valid-until"><?php esc_html_e( 'Valid Until', 'menucraft' ); ?></label>
							<input type="datetime-local" id="menucraft-offer-valid-until" name="valid_until">
						</div>
					</div>
					<p class="menucraft-field-help">
						<?php esc_html_e( 'Leave both empty for an offer with no time limit.', 'menucraft' ); ?>
					</p>

					<div class="menucraft-field">
						<label for="menucraft-offer-conditions"><?php esc_html_e( 'Conditions', 'menucraft' ); ?></label>
						<textarea id="menucraft-offer-conditions" name="conditions_text" rows="2"
							placeholder="<?php esc_attr_e( 'e.g. from 20€ order value, regulars only, in-house only…', 'menucraft' ); ?>"></textarea>
						<p class="menucraft-field-help">
							<?php esc_html_e( 'Free-form text — no order system yet, so conditions are informational only.', 'menucraft' ); ?>
						</p>
					</div>

					<div class="menucraft-field">
						<label><?php esc_html_e( 'Items in Offer', 'menucraft' ); ?></label>
						<div class="menucraft-offer-items-summary" data-menucraft-offer-items-summary>
							<span class="menucraft-offer-items-count" data-menucraft-offer-items-count>
								<?php esc_html_e( 'None', 'menucraft' ); ?>
							</span>
							<button type="button"
								class="button"
								data-menucraft-subpanel-open="menucraft-panel-offer-items"
								data-menucraft-subpanel-parent="menucraft-panel-offer-form">
								<?php esc_html_e( 'Manage Items', 'menucraft' ); ?>
							</button>
						</div>
						<p class="menucraft-field-help">
							<?php esc_html_e( 'Pick items and, where applicable, a specific variant. Combos = multiple lines; single = one line.', 'menucraft' ); ?>
						</p>
					</div>

					<div class="menucraft-field">
						<label for="menucraft-offer-sort"><?php esc_html_e( 'Sort Order', 'menucraft' ); ?></label>
						<input type="number" id="menucraft-offer-sort" name="sort_order" value="0" step="1" min="0">
					</div>

					<div class="menucraft-field menucraft-field-checkbox">
						<label for="menucraft-offer-active">
							<input type="checkbox" id="menucraft-offer-active" name="is_active" value="1" checked>
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
						data-menucraft-label-create="<?php esc_attr_e( 'Save Offer', 'menucraft' ); ?>"
						data-menucraft-label-edit="<?php esc_attr_e( 'Update Offer', 'menucraft' ); ?>">
						<?php esc_html_e( 'Save Offer', 'menucraft' ); ?>
					</button>
				</footer>
			</form>
		</div>
	</aside>

	<?php // -------- Sub-panel: offer items picker -------- ?>
	<aside class="menucraft-offcanvas menucraft-offcanvas-sub" id="menucraft-panel-offer-items" aria-hidden="true">
		<div class="menucraft-offcanvas-backdrop" data-menucraft-panel-close></div>
		<div class="menucraft-offcanvas-panel"
			role="dialog"
			aria-modal="true"
			aria-labelledby="menucraft-panel-offer-items-title">
			<header class="menucraft-offcanvas-header">
				<div class="menucraft-offcanvas-title-group">
					<span class="menucraft-offcanvas-breadcrumb">
						<?php esc_html_e( 'Offer', 'menucraft' ); ?>
						<span class="menucraft-breadcrumb-sep" aria-hidden="true">›</span>
					</span>
					<h2 class="menucraft-offcanvas-title" id="menucraft-panel-offer-items-title">
						<?php esc_html_e( 'Items', 'menucraft' ); ?>
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
					<?php esc_html_e( 'Click an item chip to add a line. An item can appear multiple times (e.g. the same item with two different variants). Items with variants require a variant per line.', 'menucraft' ); ?>
				</p>

				<div class="menucraft-field">
					<label><?php esc_html_e( 'Available Items', 'menucraft' ); ?></label>
					<div class="menucraft-chips menucraft-chips-picker"
						data-menucraft-offer-items-chips
						data-menucraft-chips-empty="<?php esc_attr_e( 'No items yet — create some first.', 'menucraft' ); ?>">
					</div>
				</div>

				<div class="menucraft-field">
					<label><?php esc_html_e( 'Selected Lines', 'menucraft' ); ?></label>
					<div class="menucraft-offer-items-list" data-menucraft-offer-items-list>
						<?php // Rendered by JS from itemFormState-equivalent for offers. ?>
					</div>
					<div class="menucraft-offer-items-empty" data-menucraft-offer-items-empty hidden>
						<?php esc_html_e( 'No lines yet — click an item chip above.', 'menucraft' ); ?>
					</div>
				</div>
			</div>

			<footer class="menucraft-offcanvas-footer">
				<button type="button" class="button button-primary" data-menucraft-panel-close>
					<?php esc_html_e( 'Done', 'menucraft' ); ?>
				</button>
			</footer>
		</div>
	</aside>

	<?php // -------- Confirm delete modal -------- ?>
	<div class="menucraft-modal" id="menucraft-modal-delete-offer" aria-hidden="true" role="dialog" aria-modal="true">
		<div class="menucraft-modal-backdrop" data-menucraft-modal-close></div>
		<div class="menucraft-modal-dialog" aria-labelledby="menucraft-modal-delete-offer-title">
			<header class="menucraft-modal-header">
				<h2 class="menucraft-modal-title" id="menucraft-modal-delete-offer-title">
					<?php esc_html_e( 'Delete offer?', 'menucraft' ); ?>
				</h2>
			</header>
			<div class="menucraft-modal-body">
				<p>
					<?php
					printf(
						/* translators: %s: offer name placeholder replaced by JS. */
						esc_html__( 'Are you sure you want to delete %s? This also removes its line items. This cannot be undone.', 'menucraft' ),
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
