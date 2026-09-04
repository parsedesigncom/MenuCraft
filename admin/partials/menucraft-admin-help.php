<?php
/**
 * Help & Docs admin screen.
 *
 * One accordion per topic — only one item may be open at a time (JS
 * closes siblings on `toggle`). Every text fragment lives in its own
 * short __() / esc_html_e() call so translation tools stay within their
 * per-string length limits.
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
				<?php esc_html_e( 'Short guides for every feature.', 'menucraft' ); ?>
				<?php esc_html_e( 'Click a title to open a section. Only one is open at a time.', 'menucraft' ); ?>
			</p>
			<hr class="menucraft-page-sep">
		</header>

		<div class="menucraft-page-body">
			<div class="menucraft-accordion" data-menucraft-accordion>

				<?php // ---------- Onboarding / Getting started ---------- ?>
				<details class="menucraft-accordion-item">
					<summary class="menucraft-accordion-summary">
						<span class="menucraft-accordion-chevron" aria-hidden="true"></span>
						<span class="menucraft-accordion-title"><?php esc_html_e( 'Getting started', 'menucraft' ); ?></span>
					</summary>
					<div class="menucraft-accordion-body">

						<h3><?php esc_html_e( 'What this plugin does', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'MenuCraft lets you build the menu of a restaurant, café or bar.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You add items with a price, image, allergens and tags.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Then you show the menu on any page with a shortcode or a block.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'The best order to set things up', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'You can create things in any order, but this order is the easiest:', 'menucraft' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Allergens', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Categories', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Tags', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Items', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Offers', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Options', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Put the menu on a page (shortcode or block)', 'menucraft' ); ?></li>
						</ol>
						<p><?php esc_html_e( 'The reason: items depend on categories, tags and allergens. Offers depend on items.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Step 1 — Allergens', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Small letter codes (A, B, C…) that mark ingredients guests must know about.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Each allergen has a code and a readable name (e.g. G — Gluten).', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Where to find:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft → Allergens', 'menucraft' ); ?></strong></p>
						<p><?php esc_html_e( 'On the frontend the codes appear next to each item title and a small legend at the end of the menu explains them.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Step 2 — Categories', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The main groups of your menu.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'For example: Starters, Main courses, Desserts, Drinks.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Where to find:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft → Categories', 'menucraft' ); ?></strong></p>
						<p><?php esc_html_e( 'On the frontend every category becomes a filter button that visitors can tap.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Tip: for large menus, mark one category as "Default". Its filter is pre-selected so the page opens focused on that section instead of listing every item.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Step 3 — Tags', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Extra labels that cross the categories.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'For example: Vegan, Vegetarian, New, Spicy.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Where to find:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft → Tags', 'menucraft' ); ?></strong></p>
						<p><?php esc_html_e( 'On the frontend tags become their own filter buttons and appear as small pills on each item.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Step 4 — Items', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The actual food and drinks on your menu.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Each item has a name, a short description and a price.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'If the same dish comes in several sizes, add variants (e.g. Small, Medium, Large) with their own prices.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You can also add an image, a longer description, and assign categories, tags and allergens.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Where to find:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft → Items', 'menucraft' ); ?></strong></p>

						<h3><?php esc_html_e( 'Step 5 — Offers (optional)', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'A special deal that combines several items at a fixed total price.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'For example: "Lunch menu — pizza + drink for 9,90 €".', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You can set start and end dates and add conditions like "from 20€ order value".', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Requires items to exist first.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Where to find:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft → Offers', 'menucraft' ); ?></strong></p>

						<h3><?php esc_html_e( 'Step 6 — Options', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Plugin-wide settings.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Right now only the currency symbol lives here (€, $, CHF…).', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Where to find:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft → Options', 'menucraft' ); ?></strong></p>

						<h3><?php esc_html_e( 'Step 7 — Show the menu on your site', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Two ways, same result:', 'menucraft' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Shortcode:', 'menucraft' ); ?> <code>[menucraft]</code></li>
							<li><?php esc_html_e( 'Block: search "MenuCraft Menu" in the block inserter.', 'menucraft' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'For a separate offers page:', 'menucraft' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Shortcode:', 'menucraft' ); ?> <code>[menucraft_offers]</code></li>
							<li><?php esc_html_e( 'Block: search "MenuCraft Offers" in the block inserter.', 'menucraft' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'The other sections below explain the attributes and sidebar settings in detail.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Tips for daily use', 'menucraft' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Turn any item off with the Active toggle instead of deleting it.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Use the filters and search on the Items page to find things fast.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Bulk-edit on the Items page changes many items at once (prices, tags, active state).', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Items that are used in an offer cannot be deleted until the offer is changed.', 'menucraft' ); ?></li>
						</ul>

					</div>
				</details>

				<?php // ---------- Shortcode ---------- ?>
				<details class="menucraft-accordion-item">
					<summary class="menucraft-accordion-summary">
						<span class="menucraft-accordion-chevron" aria-hidden="true"></span>
						<span class="menucraft-accordion-title"><?php esc_html_e( 'Shortcode', 'menucraft' ); ?></span>
					</summary>
					<div class="menucraft-accordion-body">

						<h3><?php esc_html_e( 'What is the shortcode?', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The shortcode shows your menu on any page or post.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Add it to the content of a page or post:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft]</pre>
						<p><?php esc_html_e( 'That is enough for a full menu with filters.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'How visitors filter', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Above the list your visitors see filter buttons.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'They can filter by category, by tag, and by allergen.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Click a button to keep only matching items.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Selected buttons turn dark.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Click again to remove that filter.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Change the look with attributes', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'You can add attributes to the shortcode.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'All attributes are optional.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'If you leave them out, the default design is used.', 'menucraft' ); ?></p>

						<h4><code>image</code></h4>
						<p><?php esc_html_e( 'Where the picture sits on each item.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>left</code>, <code>right</code>, <code>top</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>left</code></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft image="top"]</pre>

						<h4><code>variants</code></h4>
						<p><?php esc_html_e( 'Where the sizes and prices are shown.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>inline</code>, <code>modal</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>inline</code></p>
						<p><code>inline</code> — <?php esc_html_e( 'shown directly on the item card.', 'menucraft' ); ?></p>
						<p><code>modal</code> — <?php esc_html_e( 'hidden on the card, shown only in the details window.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft variants="modal"]</pre>

						<h4><code>categories_title</code>, <code>tags_title</code></h4>
						<p><?php esc_html_e( 'Change the label above each filter group.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Use your own words.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft categories_title="Speisen" tags_title="Merkmale"]</pre>

						<h4><code>allergens_title</code></h4>
						<p><?php esc_html_e( 'Change the label of the allergen legend at the end of the menu.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft allergens_title="Allergene"]</pre>

						<h4><code>columns</code></h4>
						<p><?php esc_html_e( 'Show the items as a grid with more than one column.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Without this attribute the items stay in a single-column list.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Format:', 'menucraft' ); ?> <code>&lt;screen-width&gt;__&lt;columns&gt;</code></p>
						<p><?php esc_html_e( 'Screen width is in pixels.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Separate rules with a space.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft columns="720__1 1024__2 1400__3"]</pre>
						<p><?php esc_html_e( 'This means:', 'menucraft' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Screens up to 720 pixels wide: 1 column.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Screens up to 1024 pixels wide: 2 columns.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Screens up to 1400 pixels wide: 3 columns.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Wider screens keep the highest column count.', 'menucraft' ); ?></li>
						</ul>

						<h4><code>class</code></h4>
						<p><?php esc_html_e( 'Add your own CSS class to the outer wrapper.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'A web developer can then style this menu from the theme CSS.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You can add more than one class, separated by a space.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft class="my-menu"]</pre>

						<h4><code>allergens_legend</code></h4>
						<p><?php esc_html_e( 'A small allergen list is printed at the end of the menu.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'It explains what each code letter means.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>show</code>, <code>hide</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>show</code></p>
						<p><?php esc_html_e( 'Use', 'menucraft' ); ?> <code>hide</code> <?php esc_html_e( 'if you do not want the list.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Note: with', 'menucraft' ); ?> <code>hide</code> <?php esc_html_e( 'the code letters next to each item are hidden as well.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Without the legend, the letters would have no meaning for visitors.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft allergens_legend="hide"]</pre>

						<h3><?php esc_html_e( 'Combine attributes', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'You can use several attributes together, in any order.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft image="top" columns="600__1 1200__2" class="lunch-menu"]</pre>

						<h3><?php esc_html_e( 'Tips', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Use straight double quotes around values, like this:', 'menucraft' ); ?> <code>image="top"</code></p>
						<p><?php esc_html_e( 'Some editors change straight quotes into curly ones. That breaks the shortcode.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'If a value has no spaces, the quotes are optional.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You can place several shortcodes on the same page.', 'menucraft' ); ?></p>

					</div>
				</details>

				<?php // ---------- Offers shortcode ---------- ?>
				<details class="menucraft-accordion-item">
					<summary class="menucraft-accordion-summary">
						<span class="menucraft-accordion-chevron" aria-hidden="true"></span>
						<span class="menucraft-accordion-title"><?php esc_html_e( 'Offers shortcode', 'menucraft' ); ?></span>
					</summary>
					<div class="menucraft-accordion-body">

						<h3><?php esc_html_e( 'What is the offers shortcode?', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'A second shortcode that shows your offers.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'It looks similar to the menu shortcode but has no filter buttons.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Add it to any page or post:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft_offers]</pre>

						<h3><?php esc_html_e( 'What is shown by default', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Only active offers.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'An offer shows if it is running now, or if it starts within the next 7 days.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Expired offers are hidden.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Change the look with attributes', 'menucraft' ); ?></h3>

						<h4><code>image</code>, <code>columns</code>, <code>class</code></h4>
						<p><?php esc_html_e( 'Same meaning as in the menu shortcode.', 'menucraft' ); ?></p>

						<h4><code>validity</code></h4>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>preview</code>, <code>all</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>preview</code></p>
						<p><code>preview</code> — <?php esc_html_e( 'active offers running now or starting within 7 days.', 'menucraft' ); ?></p>
						<p><code>all</code> — <?php esc_html_e( 'every active offer, regardless of dates.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft_offers validity="all"]</pre>

						<h4><code>show_items</code></h4>
						<p><?php esc_html_e( 'Where to show the list of included items.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>inline</code>, <code>modal</code>, <code>hide</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>inline</code></p>
						<p><code>inline</code> — <?php esc_html_e( 'shown directly on the offer card.', 'menucraft' ); ?></p>
						<p><code>modal</code> — <?php esc_html_e( 'shown only inside the details window.', 'menucraft' ); ?></p>
						<p><code>hide</code> — <?php esc_html_e( 'not shown at all.', 'menucraft' ); ?></p>

						<h4><code>show_desc</code></h4>
						<p><?php esc_html_e( 'Where to show the offer description.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>inline</code>, <code>modal</code>, <code>hide</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>inline</code></p>
						<p><?php esc_html_e( 'Set to', 'menucraft' ); ?> <code>modal</code> <?php esc_html_e( 'for a minimal card (image + title + price only) with everything else inside the details window.', 'menucraft' ); ?></p>

						<h4><code>show_dates</code></h4>
						<p><?php esc_html_e( 'Toggle the "Valid X – Y" line under each offer.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>show</code>, <code>hide</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>show</code></p>

						<h4><code>conditions</code></h4>
						<p><?php esc_html_e( 'Where to show the small conditions text (e.g. "from 20€ order value").', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Possible values:', 'menucraft' ); ?> <code>modal</code>, <code>inline</code>, <code>hide</code></p>
						<p><?php esc_html_e( 'Default:', 'menucraft' ); ?> <code>modal</code></p>

						<h3><?php esc_html_e( 'When is the card clickable?', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Only when the modal actually has something to show.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'That is the case if any of these applies:', 'menucraft' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'conditions set to modal AND the offer has a conditions text', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'show_items set to modal AND the offer has items', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'show_desc set to modal AND the offer has a description', 'menucraft' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Otherwise the card is static and shows everything it has directly.', 'menucraft' ); ?></p>

					</div>
				</details>

				<?php // ---------- Gutenberg block ---------- ?>
				<details class="menucraft-accordion-item">
					<summary class="menucraft-accordion-summary">
						<span class="menucraft-accordion-chevron" aria-hidden="true"></span>
						<span class="menucraft-accordion-title"><?php esc_html_e( 'Menu block (Gutenberg)', 'menucraft' ); ?></span>
					</summary>
					<div class="menucraft-accordion-body">

						<h3><?php esc_html_e( 'What is the menu block?', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The menu block does the same thing as the shortcode.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You use it inside the block editor without typing any code.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'How to add the block', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Open a page or post in the block editor.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Click the plus button to add a new block.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Search for:', 'menucraft' ); ?> <code>MenuCraft</code></p>
						<p><?php esc_html_e( 'Pick the block named:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft Menu', 'menucraft' ); ?></strong></p>
						<p><?php esc_html_e( 'You will see a live preview right away.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'How to configure the block', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Click the block once to select it.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'The settings appear on the right in the sidebar.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You can change:', 'menucraft' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Layout: image position, variant display, allergen legend.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Filter titles: labels above each filter group.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Grid layout: turn on and set how many columns per screen width.', 'menucraft' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'For an extra CSS class, use the standard "Advanced" section at the bottom of the sidebar.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Block-only styling', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The block has extra options that are not available in the shortcode.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'These live in the sidebar on the right when the block is selected.', 'menucraft' ); ?></p>

						<h4><?php esc_html_e( 'Font size', 'menucraft' ); ?></h4>
						<p><?php esc_html_e( 'Choose Small, Medium or Large.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'This scales the whole menu at once — text, chips, prices, everything.', 'menucraft' ); ?></p>

						<h4><?php esc_html_e( 'Alignment', 'menucraft' ); ?></h4>
						<p><?php esc_html_e( 'Two separate settings:', 'menucraft' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Filter alignment: pushes the filter chips to left, center or right.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Item content alignment: aligns the text inside each item card.', 'menucraft' ); ?></li>
						</ul>

						<h4><?php esc_html_e( 'Border radius', 'menucraft' ); ?></h4>
						<p><?php esc_html_e( 'One slider for the whole block.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'The value in pixels is applied everywhere: container, filter, items, image and tag pills.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Leave empty (reset button) to keep the default rounded shapes.', 'menucraft' ); ?></p>

						<h4><?php esc_html_e( 'Colors', 'menucraft' ); ?></h4>
						<p><?php esc_html_e( 'The sidebar has separate color panels for the different parts of the menu.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Container, filter, items, tags, allergen legend — each has its own colors.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Any color you leave empty keeps its default.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'The color picker also shows your theme colors, so you can match the site look with one click.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'Same content as the shortcode', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The block uses the shortcode under the hood for the actual menu.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'You can mix and match:', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Use the block for easy visual editing, and the shortcode when you edit content in code or via a page builder that only accepts shortcodes.', 'menucraft' ); ?></p>

					</div>
				</details>

				<?php // ---------- Offers block ---------- ?>
				<details class="menucraft-accordion-item">
					<summary class="menucraft-accordion-summary">
						<span class="menucraft-accordion-chevron" aria-hidden="true"></span>
						<span class="menucraft-accordion-title"><?php esc_html_e( 'Offers block (Gutenberg)', 'menucraft' ); ?></span>
					</summary>
					<div class="menucraft-accordion-body">

						<h3><?php esc_html_e( 'What is the offers block?', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The visual companion to the offers shortcode.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'It does the same thing but with settings in the sidebar instead of shortcode attributes.', 'menucraft' ); ?></p>

						<h3><?php esc_html_e( 'How to add', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Open a page or post in the block editor.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Click the plus button, search for:', 'menucraft' ); ?> <code>MenuCraft</code></p>
						<p><?php esc_html_e( 'Pick the block named:', 'menucraft' ); ?> <strong><?php esc_html_e( 'MenuCraft Offers', 'menucraft' ); ?></strong></p>

						<h3><?php esc_html_e( 'Sidebar panels', 'menucraft' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Content & layout: which offers, image position, validity dates toggle.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Card vs. modal: choose per piece where description, items and conditions live.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Grid layout: turn on and set the columns per screen width.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Alignment & size: font size, card content alignment, border radius.', 'menucraft' ); ?></li>
							<li><?php esc_html_e( 'Colors: container, cards, offer details — each with its own picker set.', 'menucraft' ); ?></li>
						</ul>

						<h3><?php esc_html_e( 'Same content as the shortcode', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'Under the hood the block uses [menucraft_offers].', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Use whichever is easier: the block for visual editing, the shortcode inside page builders or code.', 'menucraft' ); ?></p>

					</div>
				</details>

			</div>
		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>
</div>
