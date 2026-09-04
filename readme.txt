=== MenuCraft ===
Contributors: parsedesign
Tags: menu, restaurant, cafe, food, drinks, offers, gutenberg, shortcode, block
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, filterable menus for restaurants and cafés. Items, variants, allergens, offers — with a shortcode and a Gutenberg block.

== Description ==

MenuCraft is a free, self-contained menu manager for restaurants, cafés and bars. It stays out of your theme's way: your data lives in its own plugin tables, and the front-end output is a single CSS + a single JavaScript file, only loaded on pages that actually show the menu.

**What you can build**

* A full menu with categories (Starters, Mains, Drinks…), tags (Vegan, New, Spicy…) and allergen codes.
* Items with sizes and prices ("Pizza — Small 6€, Medium 8€, Large 10€"), a short description, a long description, an image, and any combination of tags/allergens.
* Combined offers ("Lunch menu — pizza + drink for 9,90€") with validity dates and free-form conditions ("from 20€ order value").
* A focused view of just one category or one tag on its own page ("Wine list", "Vegan options").

**Ways to show the menu**

* Shortcodes: `[menucraft]`, `[menucraft_offers]`, `[menucraft_group]`.
* Gutenberg blocks: "MenuCraft Menu", "MenuCraft Offers", "MenuCraft Group" — each with a live preview and a sidebar of settings (image position, grid layout, colors with alpha, alignment, font size, border radius).

**Made for real menus**

* Multi-select for categories, tags and allergens with search — even a menu with hundreds of items stays manageable.
* Bulk-edit for items: assign or remove tags in one go, adjust base or variant prices by a fixed amount, activate or deactivate at scale.
* Delete-guard: an item that's used in an offer can't be accidentally deleted; you have to change the offer first.
* Marketing-friendly offer visibility: current offers plus the ones starting in the next 7 days show automatically, so promos get a lead-in.
* Default category: mark one category as "Default" and its filter chip is pre-selected on the frontend — long menus open focused instead of listing everything.
* Item-level allergen codes shown as small superscript letters after the item title, with a fine-print legend at the end of the menu.

**Made for developers**

Every rendered region flows through filter and action hooks so you can restyle or extend without editing plugin files. Templates live in `templates/` and can be overridden theme-side by copying to `theme/menucraft/`. Full REST API under `/wp-json/menucraft/v1/`.

**Made for WordPress.org**

* No external services. No calls out. No tracking.
* No jQuery. No build tools. Ships uncompiled.
* Single CSS + single JS on the front end, conditionally enqueued.
* Every user-visible string translatable, kept short so translation tools handle them cleanly.

== Installation ==

1. Upload the `menucraft` folder to `/wp-content/plugins/`, or install via **Plugins → Add New**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. In the WordPress admin sidebar, open **MenuCraft**.
4. Open **Help & Docs** first — the "Getting started" section walks you through the recommended order to set things up.

**Recommended setup order**

1. **Allergens** — the small code letters (A, B, C…) that appear next to items and are explained in the legend at the bottom of the menu.
2. **Categories** — the main groups of your menu (Starters, Mains, Drinks…).
3. **Tags** — cross-cutting labels (Vegan, Vegetarian, New, Spicy…).
4. **Items** — the actual food and drinks, with names, prices (or variants for sizes), images, and the categories/tags/allergens they belong to.
5. **Offers** (optional) — special deals that combine several items at a fixed total price, with validity dates and optional conditions.
6. **Options** — currency symbol shown next to every price.
7. **Put the menu on a page** — insert the shortcode `[menucraft]` or add the **MenuCraft Menu** block in the block editor.

== Frequently Asked Questions ==

= Is MenuCraft free? =

Yes. GPLv2 (or later), no premium version, no paid add-ons.

= Does it work with my theme? =

Yes. The front-end output uses class-scoped CSS (`.menucraft-*`) so it inherits your theme's fonts and colors and doesn't leak into your theme. Every template can also be overridden by copying it to `your-theme/menucraft/`.

= Does it slow down my site? =

MenuCraft loads its front-end assets only on pages that actually render the shortcode or block. No JavaScript library dependency (no jQuery, no React). One CSS file, one JS file.

= Can visitors filter the menu? =

Yes. Category and tag buttons appear above the item list. Only one filter per group can be active at a time, and filters across groups combine with AND. Allergens are shown as small letter codes on each item and explained in a legend, but not used as a filter — visitors typically scan them, not filter by them.

= Can I show only one section of the menu on a page? =

Use `[menucraft_group category="drinks"]` or the **MenuCraft Group** block. It shows every item in one category (or one tag), with that taxonomy's own image and description as a hero header — no filter bar needed.

= Where do I set the currency? =

**MenuCraft → Options**. The symbol you set there is used everywhere in the plugin (item prices, variant prices, offer prices).

= What happens to an item if I delete a category/tag/allergen it uses? =

The link is removed cleanly. The item stays; it just no longer belongs to that group.

= Can I delete an item that's used in an offer? =

No — you'll get a message telling you which offers reference it. Change the offer first, then delete.

= Can I translate MenuCraft? =

Yes. All user-facing strings use WordPress's `__()` and are kept short so translation tools handle them well. A German translation is planned.

= Where can I get help? =

The **MenuCraft → Help & Docs** page inside your WordPress admin has short walkthroughs for every feature. For developers: see `README.md` in the plugin folder or on GitHub for the full hook reference and code examples.

== Screenshots ==

1. Items admin screen with filters, bulk-edit and multi-select for categories/tags/allergens.
2. Offer edit panel with the items sub-panel (chip picker + line configuration).
3. Getting Started section in the Help & Docs accordion.
4. Front-end menu with category and tag filters, item cards, and allergen legend.
5. Gutenberg "MenuCraft Menu" block with the sidebar showing layout, colors and grid options.

== Changelog ==

= 0.1.1 =
* Added `[menucraft_group]` shortcode and **MenuCraft Group** block for focused single-category or single-tag pages, with hero header (image + name + description) and optional start-collapsed mode.
* Added multi-select with search for item categories/tags/allergens (Select2-style, no library).
* Added default-category flag: mark one category as default; its filter chip is auto-activated on the frontend.
* Frontend filter behaviour: single-select per group (Category, Tag), AND across groups.
* Fixed a bug where items with multiple tags rendered fewer pills than expected.
* Improved: Gutenberg blocks color panels now collapse consistently with the other sidebar sections.

= 0.1.0 =
* Initial release.
* Admin CRUD for Categories, Tags, Allergens, Items (with variants), Offers, Options.
* Frontend shortcodes `[menucraft]` and `[menucraft_offers]`.
* Gutenberg blocks **MenuCraft Menu** and **MenuCraft Offers** with per-block colors (alpha channel), alignment, font size, border radius, custom grid layout.
* Help & Docs accordion with onboarding + per-feature guides.

== Upgrade Notice ==

= 0.1.1 =
Adds the new group shortcode/block, multi-select for item relations, default-category flag, and fixes multi-tag rendering. No manual steps needed.
