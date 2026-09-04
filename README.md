# MenuCraft

A self-contained WordPress plugin for restaurant, café and bar menus. Items, variants, allergens, offers — with shortcodes, Gutenberg blocks, a REST API, and an extensive filter/action hook layer for developers.

- **Zero external dependencies.** No jQuery, no build tools, no CDN.
- **One CSS + one JS on the front end**, conditionally enqueued only when the shortcode or block is on the page.
- **Templates overridable** by theme (`theme/menucraft/<name>.php`).
- **Every rendered region flows through hooks** so you can restyle or extend without touching plugin files.
- **Own database tables** — plugin data can be exported as a single SQL dump; nothing is smeared into `wp_options` or `wp_posts`.

Requires WordPress 6.0+, PHP 7.4+. GPL-2.0-or-later.

## Table of Contents

- [Install & activate](#install--activate)
- [Admin overview](#admin-overview)
- [Shortcodes](#shortcodes)
  - [`[menucraft]`](#menucraft)
  - [`[menucraft_offers]`](#menucraft_offers)
  - [`[menucraft_group]`](#menucraft_group)
- [Gutenberg blocks](#gutenberg-blocks)
- [Template overrides](#template-overrides)
- [Filter hooks](#filter-hooks)
- [Action hooks](#action-hooks)
- [REST API](#rest-api)
- [Data model](#data-model)
- [Front-end DOM contract](#front-end-dom-contract)
- [Development](#development)
- [License](#license)

---

## Install & activate

Clone or copy into `wp-content/plugins/menucraft/`, then activate through **Plugins** in WordPress admin. On activation the plugin creates its own tables (`wp_menucraft_*`) via `dbDelta` and stamps the current DB version.

```bash
cd wp-content/plugins
git clone https://github.com/parsedesigncom/MenuCraft.git menucraft
```

No `composer install`, no `npm install`, no build step — everything ships ready to run.

## Admin overview

`MenuCraft` menu in the WordPress sidebar. Sub-screens:

- **Items** — CRUD with variants (size + price), M2M relations to categories/tags/allergens, client-side filters (search, price range, image, status), bulk-edit, guarded delete (blocked while referenced by an offer).
- **Categories** — flat CRUD (name, slug, description, color, image, sort order, active) plus a `is_default` flag: exactly one category can be default, and its filter chip is auto-activated on the front end.
- **Tags** — flat CRUD, same shape as Categories minus `is_default`.
- **Allergens** — CRUD with `code` + name + description (no image).
- **Offers** — total-price bundles of items or item-variants, optional validity window (from/until), free-form conditions text.
- **Options** — currency symbol.
- **Help & Docs** — accordion with a Getting Started onboarding + per-shortcode / per-block guides.

## Shortcodes

### `[menucraft]`

Full menu view with filter chips.

```
[menucraft]                                        Default: rows, image left, filters on
[menucraft image="top" columns="720__1 1200__2"]   Grid with responsive columns
[menucraft variants="modal" class="dinner-menu"]   Variants inside details window, extra CSS class
```

**Attributes** (all optional):

| Attribute | Values | Default | Description |
| --- | --- | --- | --- |
| `image` | `left` \| `right` \| `top` | `left` | Image placement per item card. |
| `variants` | `inline` \| `modal` | `inline` | Show variants on the card or only in the details modal. |
| `categories_title` | string | localized | Label above the categories filter group. |
| `tags_title` | string | localized | Label above the tags filter group. |
| `allergens_title` | string | localized | Label of the allergen legend at the bottom. |
| `columns` | `"720__1 1024__2 1400__3"` | *(none = rows)* | Space-separated `max-width__cols` tokens; enables responsive grid. |
| `class` | space-separated class names | *(empty)* | Extra CSS classes on the outer wrapper, sanitized via `sanitize_html_class()`. |
| `allergens_legend` | `show` \| `hide` | `show` | Fine-print legend at the end of the menu. Hiding it also removes the code letters next to items. |

Front-end filter semantics: single-select per group (Categories, Tags), AND across groups.

### `[menucraft_offers]`

Offers list — no filter bar; offers with visible items are picked server-side.

```
[menucraft_offers]                                     Preview mode (running now OR starting in 7 days)
[menucraft_offers validity="all"]                      All active offers regardless of dates
[menucraft_offers show_items="modal" conditions="modal" show_desc="modal" show_dates="hide"]
```

**Attributes**:

| Attribute | Values | Default | Description |
| --- | --- | --- | --- |
| `image` | `left` \| `right` \| `top` | `left` | |
| `columns` | `"720__1 …"` | *(rows)* | |
| `class` | classes | | |
| `validity` | `preview` \| `all` | `preview` | `preview` = currently valid OR starting in the next 7 days; `all` = every `is_active=1` offer ignoring dates. |
| `show_items` | `inline` \| `modal` \| `hide` | `inline` | Where the composition list ("2× Pizza (Small)") shows. |
| `show_desc` | `inline` \| `modal` \| `hide` | `inline` | Where the offer description shows. |
| `show_dates` | `show` \| `hide` | `show` | Toggle the "Valid X – Y" line. |
| `conditions` | `modal` \| `inline` \| `hide` | `modal` | Where the conditions text ("from 20€ order value") shows. |

The card is only clickable → modal when the modal actually has content (description-in-modal OR items-in-modal OR conditions-in-modal).

### `[menucraft_group]`

Focused list of one category or one tag with a hero header.

```
[menucraft_group category="drinks"]
[menucraft_group tag="vegan" show_header="hide"]
[menucraft_group category="pizza" collapsed="yes"]
[menucraft_group category="5" image="top" columns="720__1 1200__2"]
```

**Attributes**:

| Attribute | Values | Default | Description |
| --- | --- | --- | --- |
| `category` | slug or numeric id | *(none)* | Show items belonging to this category. |
| `tag` | slug or numeric id | *(none)* | Show items belonging to this tag. |
| `image` | `left` \| `right` \| `top` | `left` | |
| `variants` | `inline` \| `modal` | `inline` | |
| `columns` | `"720__1 …"` | *(rows)* | |
| `class` | classes | | |
| `allergens_legend` | `show` \| `hide` | `show` | |
| `show_header` | `show` \| `hide` | `show` | Toggle the hero header (image + name + description). |
| `collapsed` | `no` \| `yes` | `no` | `yes` renders the group inside a `<details>` element with only title + description visible; click reveals the image and items. |

Exactly one of `category` or `tag` is required. If both are set, `category` wins. An item that lives in several categories still appears in the group as long as one of them matches.

## Gutenberg blocks

Three dynamic blocks live under a dedicated **MenuCraft** category in the inserter. Each block wraps its corresponding shortcode via `do_shortcode()`, plus it exposes block-only decoration (font scale, alignment, border radius, ~10–20 color slots with alpha, custom grid) via a scoped inline `<style>` on the outer wrapper. Editing is a live preview through `wp.serverSideRender`.

| Block | Wraps | Sidebar panels |
| --- | --- | --- |
| `menucraft/menu` | `[menucraft]` | Layout · Filter titles · Grid layout · Alignment & size · 5 color panels |
| `menucraft/offers` | `[menucraft_offers]` | Content & layout · Card vs. modal · Grid layout · Alignment & size · 3 color panels |
| `menucraft/group` | `[menucraft_group]` | Source · Header · Layout · Grid layout · Alignment & size · 5 color panels |

Written in plain JS via `wp.element.createElement` — no JSX, no build step, no committed compiled output.

## Template overrides

Copy any file from `templates/` into `your-theme/menucraft/` and it wins.

```
plugin/templates/shortcode.php          → your-theme/menucraft/shortcode.php
plugin/templates/shortcode-item.php     → your-theme/menucraft/shortcode-item.php
plugin/templates/shortcode-offers.php   → your-theme/menucraft/shortcode-offers.php
plugin/templates/shortcode-offer.php    → your-theme/menucraft/shortcode-offer.php
plugin/templates/shortcode-group.php    → your-theme/menucraft/shortcode-group.php
```

The loader (`MenuCraft_Public::locate_template()`) always checks the theme first, then falls back to the plugin.

## Filter hooks

Filter hooks are `apply_filters()` calls — the developer returns a modified value (usually HTML or a data array). Return the input value untouched to opt out.

### Full render override

Every shortcode has an "html" filter that short-circuits the entire render. Return a non-empty string to replace the whole output.

```php
add_filter( 'menucraft_shortcode_html', function ( $html, $context ) {
    // $context = [ 'atts', 'config', 'items', 'categories', 'tags', 'allergens' ]
    if ( isset( $context['atts']['class'] ) && 'drinks-page' === $context['atts']['class'] ) {
        return '<div class="my-drinks-list">…custom HTML…</div>';
    }
    return $html;
}, 10, 2 );
```

The offers and group shortcodes have parallel filters: `menucraft_offers_shortcode_html`, `menucraft_group_shortcode_html`.

### Data-level filters

Modify the underlying data arrays before they hit the template.

```php
// Menu shortcode: hide items with an "internal" tag from the front end.
add_filter( 'menucraft_shortcode_items', function ( $items ) {
    return array_values( array_filter( $items, function ( $item ) {
        return ! in_array( 999, $item['tag_ids'], true ); // 999 = "internal" tag id
    } ) );
} );

// Group shortcode: replace the resolved source with a custom-loaded row.
add_filter( 'menucraft_group_shortcode_source', function ( $source, $type, $ref ) {
    if ( 'category' === $type && 'featured' === $ref ) {
        return [ 'id' => 0, 'name' => 'Featured today', 'description' => 'Chef selection.', 'media_id' => 42 ];
    }
    return $source;
}, 10, 3 );

// Offers shortcode: reorder or slice offers.
add_filter( 'menucraft_offers_shortcode_offers', function ( $offers, $config ) {
    // Show newest first.
    usort( $offers, function ( $a, $b ) { return strcmp( $b['created_at'], $a['created_at'] ); } );
    return $offers;
}, 10, 2 );
```

### HTML-section filters

Replace or wrap a specific HTML region. Return `''` to keep the default.

| Filter | Signature | Where it fires |
| --- | --- | --- |
| `menucraft_shortcode_filters_html` | `($html, $categories, $tags)` | Menu shortcode filter bar. |
| `menucraft_shortcode_categories_html` | `($html, $categories)` | Category chip group. |
| `menucraft_shortcode_tags_html` | `($html, $tags)` | Tag chip group. |
| `menucraft_shortcode_items_html` | `($html, $items)` | Items list wrapper (menu). |
| `menucraft_shortcode_item_html` | `($html, $item, $allergens, $tags, $config)` | Every single item card (shared across all three shortcodes). |
| `menucraft_shortcode_allergens_legend_html` | `($html, $allergens)` | End-of-menu legend. |
| `menucraft_offers_shortcode_items_html` | `($html, $offers)` | Offers list wrapper. |
| `menucraft_offers_shortcode_item_html` | `($html, $offer, $items_map, $config)` | Every single offer card. |
| `menucraft_group_shortcode_header_html` | `($html, $source, $config)` | Group hero header. |
| `menucraft_group_shortcode_items_html` | `($html, $items)` | Group items list wrapper. |
| `menucraft_group_shortcode_allergens_legend_html` | `($html, $allergens)` | Group legend. |

Example — brand the filter bar:

```php
add_filter( 'menucraft_shortcode_filters_html', function ( $html, $categories, $tags ) {
    ob_start();
    ?>
    <nav class="restaurant-filter-nav">
        <?php foreach ( $categories as $cat ) : ?>
            <a href="#cat-<?php echo esc_attr( $cat['slug'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
    return ob_get_clean();
}, 10, 3 );
```

Example — add a "chef's badge" to specific items:

```php
add_filter( 'menucraft_shortcode_item_html', function ( $html, $item ) {
    if ( in_array( 5, $item['tag_ids'], true ) ) { // 5 = "chef's pick" tag id
        $html = '<span class="chef-badge">Chef\'s pick</span>' . $html;
    }
    return $html;
}, 10, 2 );
```

## Action hooks

Action hooks are `do_action()` calls — the developer runs side effects (typically `echo`) at that point. No return value.

### Wrapper actions

Fire before / after the whole shortcode render.

| Action | Fired by | Args |
| --- | --- | --- |
| `menucraft_before_shortcode` / `menucraft_after_shortcode` | `[menucraft]` | `$context` |
| `menucraft_before_offers_shortcode` / `menucraft_after_offers_shortcode` | `[menucraft_offers]` | `$context` |
| `menucraft_before_group_shortcode` / `menucraft_after_group_shortcode` | `[menucraft_group]` | `$context` |

Example — insert a Google-schema JSON-LD block before every menu render:

```php
add_action( 'menucraft_before_shortcode', function ( $context ) {
    echo '<script type="application/ld+json">' . wp_json_encode( [
        '@context' => 'https://schema.org',
        '@type'    => 'Menu',
        'name'     => get_the_title(),
        'hasMenuSection' => array_map( function ( $cat ) {
            return [ '@type' => 'MenuSection', 'name' => $cat['name'] ];
        }, $context['categories'] ),
    ] ) . '</script>';
} );
```

### Section actions

Fire at strategic points inside the template — inject a heading, a note, a promo box, whatever you need.

| Action | Args | Fired by |
| --- | --- | --- |
| `menucraft_before_filters` / `menucraft_after_filters` | `$categories, $tags` | Menu |
| `menucraft_before_items` / `menucraft_after_items` | `$items` | Menu, Group |
| `menucraft_before_item` / `menucraft_after_item` | `$item, $config` | Every item (all shortcodes) |
| `menucraft_before_offers` / `menucraft_after_offers` | `$offers` | Offers |
| `menucraft_before_offer` / `menucraft_after_offer` | `$offer, $config` | Every offer card |
| `menucraft_before_group_header` / `menucraft_after_group_header` | `$source, $config` | Group hero header |
| `menucraft_before_allergens_legend` / `menucraft_after_allergens_legend` | `$allergens` | Legend (Menu, Group) |

Example — add a "New" ribbon to items created in the last 7 days:

```php
add_action( 'menucraft_before_item', function ( $item ) {
    $created = strtotime( $item['created_at'] );
    if ( $created && ( time() - $created ) < 7 * DAY_IN_SECONDS ) {
        echo '<span class="mc-new-ribbon">NEW</span>';
    }
} );
```

Example — inject a promo box after the filter bar:

```php
add_action( 'menucraft_after_filters', function () {
    echo '<div class="promo-box">Ask about today\'s dessert special!</div>';
} );
```

## REST API

All routes require the `manage_options` capability.

```
GET    /wp-json/menucraft/v1/categories
POST   /wp-json/menucraft/v1/categories
GET    /wp-json/menucraft/v1/categories/{id}
PUT    /wp-json/menucraft/v1/categories/{id}
DELETE /wp-json/menucraft/v1/categories/{id}
```

Same shape (five verbs) for `/tags`, `/allergens`, `/items`, `/offers`, `/options`.

Item bulk operations:

```
POST /wp-json/menucraft/v1/items/bulk-edit
{
    "item_ids":   [1, 3, 7],
    "operations": {
        "tags":       { "mode": "add", "ids": [5] },
        "base_price": { "mode": "increase", "value": 0.5 }
    }
}
```

## Data model

Custom tables (`$wpdb->prefix . 'menucraft_*'`):

- `categories`, `tags`, `allergens` — flat taxonomies.
- `items` — with a nullable base `price`; when null the item is priced from its variants.
- `item_variants` — 1:N children of items, each with a label + price.
- `offers` — total-price bundles with optional `valid_from` / `valid_until` and free-form `conditions_text`.
- `offer_items` — the composition; `variant_id` is nullable so a line can pin either the whole item or one specific variant. Same item may appear multiple times with different variants.
- `item_categories`, `item_tags`, `item_allergens` — M2M junctions.
- `menucraft_options` — plugin-owned key/value store, independent of `wp_options`.

Schema is versioned (`MENUCRAFT_DB_VERSION`) with idempotent migrations in `MenuCraft_Schema::run_migrations()`. Latest is 1.5.

## Front-end DOM contract

If you're writing custom CSS or JS against MenuCraft's front-end output, these are the stable selectors:

- Wrapper: `[data-menucraft-root]` (all three shortcodes). Also carries `data-menucraft-menu` for backwards compat when it's the menu shortcode.
- Filter chip: `.menucraft-filter-chip[data-menucraft-filter="category|tag"]`. Active state: `.is-active`.
- Item card: `.menucraft-item[data-menucraft-item="{id}"]`. When clickable (long description or variants-in-modal): also `.menucraft-item-has-details` + `[data-menucraft-open-details="item-{id}"]`.
- Offer card: `.menucraft-offer[data-menucraft-offer="{id}"]` + optional `.menucraft-offer-has-details` + `[data-menucraft-open-details="offer-{id}"]`.
- Modal: `[data-menucraft-modal]` inside the wrapper. Body populated via `[data-menucraft-modal-body]` from JSON in `[data-menucraft-details="{prefix-id}"]` (`prefix` = `item` or `offer`).

## Development

**Structure**:

```
menucraft.php                      Plugin bootstrap (constants + activation hook).
uninstall.php                      Opt-in table drop.
includes/                          Core PHP: schema, repositories, REST, blocks, main class.
admin/                             Admin controller + settings screens (partials/).
public/class-menucraft-public.php  Front-end controller (shortcodes, asset registration).
templates/                         Overridable shortcode templates.
blocks/{menu,offers,group}/        Gutenberg block metadata + editor JS (vanilla, no build).
assets/css/menucraft-admin.css     Single admin CSS.
assets/css/menucraft-public.css    Single front-end CSS.
assets/js/menucraft-admin.js       Single admin JS.
assets/js/menucraft-public.js      Single front-end JS.
languages/                         Translation files (menucraft.pot + menucraft-{locale}.po/.mo).
```

**Coding standards**: WordPress PHP Coding Standards. Prefixes:

- Constants: `MENUCRAFT_`
- Classes:   `MenuCraft_`
- Functions: `menucraft_`
- Options / meta keys: `menucraft_`

**No build step**: JavaScript is hand-written and committed as-is. That's a deliberate choice — the plugin ships and installs without `node_modules` or a bundler.

**Testing local changes**: activate the plugin, then reload. Migrations run on `admin_init` via `MenuCraft_Schema::maybe_upgrade()`, so bumping `MENUCRAFT_DB_VERSION` triggers your migration on the next admin page load.

## License

GPL-2.0-or-later. See `LICENSE`.
