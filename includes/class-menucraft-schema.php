<?php
/**
 * Database schema for MenuCraft.
 *
 * Owns creation and removal of all plugin tables and exposes the
 * canonical table-name map used by the rest of the plugin.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema definitions and migrations.
 */
class MenuCraft_Schema {

	/**
	 * Full table names (with WordPress prefix) keyed by short identifier.
	 *
	 * @return array<string,string>
	 */
	public static function tables() {
		global $wpdb;
		$p = $wpdb->prefix;

		return array(
			'categories'      => $p . 'menucraft_categories',
			'tags'            => $p . 'menucraft_tags',
			'allergens'       => $p . 'menucraft_allergens',
			'items'           => $p . 'menucraft_items',
			'item_variants'   => $p . 'menucraft_item_variants',
			'offers'          => $p . 'menucraft_offers',
			'item_categories' => $p . 'menucraft_item_categories',
			'item_tags'       => $p . 'menucraft_item_tags',
			'item_allergens'  => $p . 'menucraft_item_allergens',
			'offer_items'     => $p . 'menucraft_offer_items',
			'options'         => $p . 'menucraft_options',
		);
	}

	/**
	 * Create or upgrade all plugin tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t       = self::tables();
		$charset = $wpdb->get_charset_collate();

		$statements = array();

		// Categories.
		$statements[] = "CREATE TABLE {$t['categories']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			slug varchar(191) NOT NULL,
			description text NULL,
			color varchar(20) NULL,
			media_id bigint(20) unsigned NULL,
			parent_id bigint(20) unsigned NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY parent_id (parent_id),
			KEY is_active (is_active)
		) {$charset};";

		// Tags (identical structure to categories per product decision).
		$statements[] = "CREATE TABLE {$t['tags']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			slug varchar(191) NOT NULL,
			description text NULL,
			color varchar(20) NULL,
			media_id bigint(20) unsigned NULL,
			parent_id bigint(20) unsigned NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY parent_id (parent_id),
			KEY is_active (is_active)
		) {$charset};";

		// Allergens (code-driven, e.g. EU codes A/B/C).
		$statements[] = "CREATE TABLE {$t['allergens']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(20) NOT NULL,
			name varchar(200) NOT NULL,
			description text NULL,
			media_id bigint(20) unsigned NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY is_active (is_active)
		) {$charset};";

		// Items. Price is NULL when only variants define pricing.
		$statements[] = "CREATE TABLE {$t['items']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			slug varchar(191) NOT NULL,
			description_short varchar(500) NULL,
			description_long text NULL,
			price decimal(10,2) NULL DEFAULT NULL,
			media_id bigint(20) unsigned NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active)
		) {$charset};";

		// Item variants (size / portion → price).
		$statements[] = "CREATE TABLE {$t['item_variants']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_id bigint(20) unsigned NOT NULL,
			label varchar(100) NOT NULL,
			price decimal(10,2) NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY item_id (item_id),
			KEY is_active (is_active)
		) {$charset};";

		// Offers / bundles.
		$statements[] = "CREATE TABLE {$t['offers']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(200) NOT NULL,
			slug varchar(191) NOT NULL,
			description text NULL,
			price decimal(10,2) NOT NULL,
			media_id bigint(20) unsigned NULL,
			valid_from datetime NULL DEFAULT NULL,
			valid_until datetime NULL DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY is_active (is_active),
			KEY valid_from (valid_from),
			KEY valid_until (valid_until)
		) {$charset};";

		// Junction: item ↔ category.
		$statements[] = "CREATE TABLE {$t['item_categories']} (
			item_id bigint(20) unsigned NOT NULL,
			category_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (item_id,category_id),
			KEY category_id (category_id)
		) {$charset};";

		// Junction: item ↔ tag.
		$statements[] = "CREATE TABLE {$t['item_tags']} (
			item_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (item_id,tag_id),
			KEY tag_id (tag_id)
		) {$charset};";

		// Junction: item ↔ allergen.
		$statements[] = "CREATE TABLE {$t['item_allergens']} (
			item_id bigint(20) unsigned NOT NULL,
			allergen_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (item_id,allergen_id),
			KEY allergen_id (allergen_id)
		) {$charset};";

		// Junction: offer ↔ item (bundle contents with quantity).
		$statements[] = "CREATE TABLE {$t['offer_items']} (
			offer_id bigint(20) unsigned NOT NULL,
			item_id bigint(20) unsigned NOT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (offer_id,item_id),
			KEY item_id (item_id)
		) {$charset};";

		// Plugin options (self-contained key/value store, independent of wp_options).
		$statements[] = "CREATE TABLE {$t['options']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			option_key varchar(191) NOT NULL,
			option_value longtext NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY option_key (option_key)
		) {$charset};";

		foreach ( $statements as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop all plugin tables (child/junction tables first).
	 */
	public static function drop_tables() {
		global $wpdb;

		$t     = self::tables();
		$order = array(
			'offer_items',
			'item_allergens',
			'item_tags',
			'item_categories',
			'item_variants',
			'offers',
			'items',
			'allergens',
			'tags',
			'categories',
			'options',
		);

		foreach ( $order as $key ) {
			$table = $t[ $key ];
			// Table names cannot be prepared, they are built from the WP prefix + literal suffix.
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB
		}
	}

	/**
	 * Run schema when the persisted db_version is older than MENUCRAFT_DB_VERSION.
	 *
	 * Called on admin_init so end-user page loads are not penalized by a version check.
	 * dbDelta is idempotent, so calling it on existing schema only issues ALTERs when needed.
	 */
	public static function maybe_upgrade() {
		$current = MenuCraft_Options::get( 'db_version', '0' );

		if ( version_compare( $current, MENUCRAFT_DB_VERSION, '<' ) ) {
			self::create_tables();
			MenuCraft_Options::update( 'db_version', MENUCRAFT_DB_VERSION );
		}
	}
}
