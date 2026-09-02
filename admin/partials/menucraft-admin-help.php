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

				<?php // ---------- Shortcode ---------- ?>
				<details class="menucraft-accordion-item" open>
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

						<h4><code>categories_title</code>, <code>tags_title</code>, <code>allergens_title</code></h4>
						<p><?php esc_html_e( 'Change the label above each filter group.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Use your own words.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Example:', 'menucraft' ); ?></p>
						<pre class="menucraft-help-code">[menucraft categories_title="Speisen" tags_title="Merkmale"]</pre>

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

						<h3><?php esc_html_e( 'Same as the shortcode', 'menucraft' ); ?></h3>
						<p><?php esc_html_e( 'The block uses the shortcode under the hood.', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Frontend output is identical, so you can mix and match:', 'menucraft' ); ?></p>
						<p><?php esc_html_e( 'Use the block for easy visual editing, and the shortcode when you edit content in code or via a page builder that only accepts shortcodes.', 'menucraft' ); ?></p>

					</div>
				</details>

			</div>
		</div>

		<footer class="menucraft-page-footer">
			<hr class="menucraft-page-sep">
		</footer>
	</div>
</div>
