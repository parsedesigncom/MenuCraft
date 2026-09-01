<?php
/**
 * Data-access layer for menucraft_items.
 *
 * Items own three M2M relations (categories, tags, allergens) and one
 * 1:N child (variants). Junction and variant rows are replaced whole
 * on every write to keep the API simple. Callers pass the full desired
 * state; the repository reconciles.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Item repository.
 */
class MenuCraft_Item_Repository {

	/**
	 * Return the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = MenuCraft_Schema::tables();
		return $tables['items'];
	}

	/**
	 * Fetch a single item by primary key, fully hydrated.
	 *
	 * @param int $id Item ID.
	 * @return array<string,mixed>|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", (int) $id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Fetch all items, ordered by sort_order then id, each hydrated with
	 * its relations and variants.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM `{$table}` ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'hydrate' ), $rows );
	}

	/**
	 * Check whether a slug is already taken.
	 *
	 * @param string $slug       Candidate slug.
	 * @param int    $exclude_id Item ID to ignore (for update-flow reuse).
	 * @return bool
	 */
	public static function slug_exists( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::table();

		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE slug = %s AND id <> %d", // phpcs:ignore WordPress.DB
				$slug,
				(int) $exclude_id
			)
		);

		return $count > 0;
	}

	/**
	 * Insert a new item along with relations and variants.
	 *
	 * @param array<string,mixed> $data Sanitized attributes. Recognized keys:
	 *   name, slug, description_short, description_long, price (float|null),
	 *   media_id, sort_order, is_active,
	 *   category_ids (int[]), tag_ids (int[]), allergen_ids (int[]),
	 *   variants (array<array{label,price,sort_order?}>).
	 * @return array<string,mixed>|null Fresh hydrated row or null on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', 1 );

		$row     = array(
			'name'       => (string) $data['name'],
			'slug'       => (string) $data['slug'],
			'sort_order' => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'is_active'  => ! empty( $data['is_active'] ) ? 1 : 0,
			'created_at' => $now,
			'updated_at' => $now,
		);
		$formats = array( '%s', '%s', '%d', '%d', '%s', '%s' );

		if ( isset( $data['description_short'] ) && '' !== $data['description_short'] ) {
			$row['description_short'] = (string) $data['description_short'];
			$formats[]                = '%s';
		}

		if ( isset( $data['description_long'] ) && '' !== $data['description_long'] ) {
			$row['description_long'] = (string) $data['description_long'];
			$formats[]               = '%s';
		}

		if ( array_key_exists( 'price', $data ) && null !== $data['price'] ) {
			$row['price'] = (float) $data['price'];
			$formats[]    = '%f';
		}

		if ( ! empty( $data['media_id'] ) ) {
			$row['media_id'] = (int) $data['media_id'];
			$formats[]       = '%d';
		}

		$result = $wpdb->insert( self::table(), $row, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return null;
		}

		$item_id = (int) $wpdb->insert_id;

		self::sync_junction( $item_id, 'item_categories', 'category_id', isset( $data['category_ids'] ) ? $data['category_ids'] : array() );
		self::sync_junction( $item_id, 'item_tags', 'tag_id', isset( $data['tag_ids'] ) ? $data['tag_ids'] : array() );
		self::sync_junction( $item_id, 'item_allergens', 'allergen_id', isset( $data['allergen_ids'] ) ? $data['allergen_ids'] : array() );

		if ( isset( $data['variants'] ) && is_array( $data['variants'] ) ) {
			self::replace_variants( $item_id, $data['variants'] );
		}

		return self::find( $item_id );
	}

	/**
	 * Partial update of an item. Only keys present in $data are touched.
	 * Passing an empty array for a relation clears it; omitting the key
	 * leaves it untouched.
	 *
	 * @param int                  $id   Item ID.
	 * @param array<string,mixed>  $data Partial attributes.
	 * @return array<string,mixed>|null Updated row or null on failure.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$table = self::table();
		$id    = (int) $id;

		$sets   = array();
		$values = array();

		$assign = function ( $column, $format, $nullable = false ) use ( $data, &$sets, &$values ) {
			if ( ! array_key_exists( $column, $data ) ) {
				return;
			}
			$value = $data[ $column ];

			$is_empty = ( null === $value || '' === $value || 0 === $value || '0' === $value );
			if ( $nullable && $is_empty ) {
				$sets[] = "`{$column}` = NULL";
			} else {
				$sets[]   = "`{$column}` = {$format}";
				$values[] = $value;
			}
		};

		$assign( 'name', '%s' );
		$assign( 'slug', '%s' );
		$assign( 'description_short', '%s' );
		$assign( 'description_long', '%s' );

		// Price: nullable float. When $data['price'] is null → SET NULL.
		if ( array_key_exists( 'price', $data ) ) {
			if ( null === $data['price'] || '' === $data['price'] ) {
				$sets[] = '`price` = NULL';
			} else {
				$sets[]   = '`price` = %f';
				$values[] = (float) $data['price'];
			}
		}

		$assign( 'media_id', '%d', true );
		$assign( 'sort_order', '%d' );
		$assign( 'is_active', '%d' );

		if ( ! empty( $sets ) ) {
			$sets[]   = '`updated_at` = %s';
			$values[] = current_time( 'mysql', 1 );
			$values[] = $id;

			$sql = sprintf(
				'UPDATE `%s` SET %s WHERE id = %%d',
				$table,
				implode( ', ', $sets )
			);

			$result = $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB
			if ( false === $result ) {
				return null;
			}
		}

		if ( array_key_exists( 'category_ids', $data ) ) {
			self::sync_junction( $id, 'item_categories', 'category_id', $data['category_ids'] );
		}
		if ( array_key_exists( 'tag_ids', $data ) ) {
			self::sync_junction( $id, 'item_tags', 'tag_id', $data['tag_ids'] );
		}
		if ( array_key_exists( 'allergen_ids', $data ) ) {
			self::sync_junction( $id, 'item_allergens', 'allergen_id', $data['allergen_ids'] );
		}
		if ( array_key_exists( 'variants', $data ) && is_array( $data['variants'] ) ) {
			self::replace_variants( $id, $data['variants'] );
		}

		return self::find( $id );
	}

	/**
	 * Delete an item and all its dependent rows (junctions + variants).
	 *
	 * @param int $id Item ID.
	 * @return bool True on success.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$id     = (int) $id;

		$wpdb->delete( $tables['item_categories'], array( 'item_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tables['item_tags'], array( 'item_id' => $id ), array( '%d' ) );       // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tables['item_allergens'], array( 'item_id' => $id ), array( '%d' ) );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $tables['item_variants'], array( 'item_id' => $id ), array( '%d' ) );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$deleted = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (bool) $deleted;
	}

	/**
	 * Apply bulk operations to a list of items and return the fresh items.
	 *
	 * The $operations shape:
	 *   [
	 *     'categories'     => ['mode' => 'replace|add|remove', 'ids' => int[]],
	 *     'tags'           => ['mode' => 'replace|add|remove', 'ids' => int[]],
	 *     'allergens'      => ['mode' => 'replace|add|remove', 'ids' => int[]],
	 *     'base_price'     => ['mode' => 'replace|increase|decrease', 'value' => float],
	 *     'variant_prices' => ['mode' => 'increase|decrease', 'value' => float],
	 *     'is_active'      => bool,
	 *   ]
	 * Absent keys are no-ops.
	 *
	 * @param array<int,int>      $item_ids   Item IDs to touch.
	 * @param array<string,mixed> $operations Operation dictionary (see above).
	 * @return array<int,array<string,mixed>> Fresh hydrated items that were actually updated.
	 */
	public static function bulk_edit( array $item_ids, array $operations ) {
		$updated = array();

		foreach ( $item_ids as $raw_id ) {
			$id = (int) $raw_id;
			if ( $id <= 0 || null === self::find( $id ) ) {
				continue;
			}

			self::apply_operations( $id, $operations );

			$fresh = self::find( $id );
			if ( $fresh ) {
				$updated[] = $fresh;
			}
		}

		return $updated;
	}

	/**
	 * Apply the operations dictionary to a single item.
	 *
	 * @param int                 $item_id    Item ID.
	 * @param array<string,mixed> $operations See bulk_edit().
	 */
	private static function apply_operations( $item_id, array $operations ) {
		$relation_map = array(
			'categories' => array( 'item_categories', 'category_id' ),
			'tags'       => array( 'item_tags', 'tag_id' ),
			'allergens'  => array( 'item_allergens', 'allergen_id' ),
		);

		foreach ( $relation_map as $key => $info ) {
			if ( empty( $operations[ $key ] ) || ! is_array( $operations[ $key ] ) ) {
				continue;
			}
			$mode = isset( $operations[ $key ]['mode'] ) ? (string) $operations[ $key ]['mode'] : '';
			$ids  = isset( $operations[ $key ]['ids'] ) ? (array) $operations[ $key ]['ids'] : array();
			list( $junction_key, $fk_column ) = $info;

			if ( 'replace' === $mode ) {
				self::sync_junction( $item_id, $junction_key, $fk_column, $ids );
			} elseif ( 'add' === $mode ) {
				self::junction_add( $item_id, $junction_key, $fk_column, $ids );
			} elseif ( 'remove' === $mode ) {
				self::junction_remove( $item_id, $junction_key, $fk_column, $ids );
			}
		}

		if ( ! empty( $operations['base_price'] ) && is_array( $operations['base_price'] ) ) {
			$mode  = isset( $operations['base_price']['mode'] ) ? (string) $operations['base_price']['mode'] : '';
			$value = isset( $operations['base_price']['value'] ) ? (float) $operations['base_price']['value'] : 0.0;

			if ( 'replace' === $mode ) {
				self::update( $item_id, array( 'price' => $value ) );
			} elseif ( 'increase' === $mode ) {
				self::adjust_base_price( $item_id, $value );
			} elseif ( 'decrease' === $mode ) {
				self::adjust_base_price( $item_id, -1 * $value );
			}
		}

		if ( ! empty( $operations['variant_prices'] ) && is_array( $operations['variant_prices'] ) ) {
			$mode  = isset( $operations['variant_prices']['mode'] ) ? (string) $operations['variant_prices']['mode'] : '';
			$value = isset( $operations['variant_prices']['value'] ) ? (float) $operations['variant_prices']['value'] : 0.0;

			if ( 'increase' === $mode ) {
				self::adjust_variant_prices( $item_id, $value );
			} elseif ( 'decrease' === $mode ) {
				self::adjust_variant_prices( $item_id, -1 * $value );
			}
		}

		if ( array_key_exists( 'is_active', $operations ) ) {
			self::update( $item_id, array( 'is_active' => $operations['is_active'] ? 1 : 0 ) );
		}
	}

	/**
	 * Adjust an item's base price by a delta (positive or negative). NULL
	 * base prices are left untouched — "increase by X" on an item that has
	 * no base price does nothing.
	 *
	 * @param int   $item_id Item ID.
	 * @param float $delta   Signed amount to add.
	 */
	private static function adjust_base_price( $item_id, $delta ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', 1 );

		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE `{$table}` SET price = GREATEST(0, price + %f), updated_at = %s WHERE id = %d AND price IS NOT NULL", // phpcs:ignore WordPress.DB
				$delta,
				$now,
				(int) $item_id
			)
		);
	}

	/**
	 * Adjust every variant's price for an item by a delta, floored at 0.
	 *
	 * @param int   $item_id Item ID.
	 * @param float $delta   Signed amount to add.
	 */
	private static function adjust_variant_prices( $item_id, $delta ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['item_variants'];
		$now    = current_time( 'mysql', 1 );

		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"UPDATE `{$table}` SET price = GREATEST(0, price + %f), updated_at = %s WHERE item_id = %d", // phpcs:ignore WordPress.DB
				$delta,
				$now,
				(int) $item_id
			)
		);
	}

	/**
	 * INSERT IGNORE junction rows — silently skips existing pairs so
	 * "add" doesn't error on already-linked entities.
	 *
	 * @param int    $item_id      Item ID.
	 * @param string $junction_key Schema tables[] key.
	 * @param string $fk_column    Foreign-key column.
	 * @param array  $ids          Related entity IDs.
	 */
	private static function junction_add( $item_id, $junction_key, $fk_column, $ids ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables[ $junction_key ];

		$clean = array_unique( array_filter( array_map( 'intval', (array) $ids ) ) );
		foreach ( $clean as $related_id ) {
			$wpdb->query( // phpcs:ignore WordPress.DB
				$wpdb->prepare(
					"INSERT IGNORE INTO `{$table}` (item_id, {$fk_column}) VALUES (%d, %d)", // phpcs:ignore WordPress.DB
					(int) $item_id,
					(int) $related_id
				)
			);
		}
	}

	/**
	 * DELETE selected junction rows for an item.
	 *
	 * @param int    $item_id      Item ID.
	 * @param string $junction_key Schema tables[] key.
	 * @param string $fk_column    Foreign-key column.
	 * @param array  $ids          Related entity IDs to detach.
	 */
	private static function junction_remove( $item_id, $junction_key, $fk_column, $ids ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables[ $junction_key ];

		$clean = array_unique( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( empty( $clean ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $clean ), '%d' ) );
		$params       = array_merge( array( (int) $item_id ), $clean );

		$wpdb->query( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE item_id = %d AND {$fk_column} IN ({$placeholders})", // phpcs:ignore WordPress.DB
				$params
			)
		);
	}

	/**
	 * Replace all rows in a junction table for a given item.
	 *
	 * @param int              $item_id      Item ID.
	 * @param string           $junction_key Schema tables[] key for the junction table.
	 * @param string           $fk_column    Foreign-key column pointing at the related entity.
	 * @param array<int,mixed> $ids          Related entity IDs to persist.
	 */
	private static function sync_junction( $item_id, $junction_key, $fk_column, $ids ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables[ $junction_key ];

		$wpdb->delete( $table, array( 'item_id' => (int) $item_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! is_array( $ids ) ) {
			return;
		}

		$unique = array_unique( array_filter( array_map( 'intval', $ids ) ) );
		foreach ( $unique as $related_id ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'item_id'  => (int) $item_id,
					$fk_column => (int) $related_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Replace all variants for an item with the provided list.
	 *
	 * @param int                                     $item_id  Item ID.
	 * @param array<int,array<string,mixed>>          $variants Variant payloads
	 *                                                          with keys label, price, sort_order?, is_active?.
	 */
	private static function replace_variants( $item_id, array $variants ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['item_variants'];

		$wpdb->delete( $table, array( 'item_id' => (int) $item_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$now      = current_time( 'mysql', 1 );
		$position = 0;
		foreach ( $variants as $variant ) {
			$label = isset( $variant['label'] ) ? trim( (string) $variant['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$price = isset( $variant['price'] ) ? (float) $variant['price'] : 0.0;
			$sort  = isset( $variant['sort_order'] ) ? (int) $variant['sort_order'] : $position;
			$active = ! isset( $variant['is_active'] ) || ! empty( $variant['is_active'] ) ? 1 : 0;

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'item_id'    => (int) $item_id,
					'label'      => $label,
					'price'      => $price,
					'sort_order' => $sort,
					'is_active'  => $active,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%f', '%d', '%d', '%s', '%s' )
			);
			$position++;
		}
	}

	/**
	 * Load related entity IDs and variant rows for a given item.
	 *
	 * @param int $item_id Item ID.
	 * @return array<string,mixed>
	 */
	private static function load_relations( $item_id ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$item_id = (int) $item_id;

		$cat  = $tables['item_categories'];
		$tag  = $tables['item_tags'];
		$alg  = $tables['item_allergens'];
		$var  = $tables['item_variants'];

		$category_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT category_id FROM `{$cat}` WHERE item_id = %d ORDER BY category_id ASC", $item_id ) // phpcs:ignore WordPress.DB
		);
		$tag_ids      = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT tag_id FROM `{$tag}` WHERE item_id = %d ORDER BY tag_id ASC", $item_id ) // phpcs:ignore WordPress.DB
		);
		$allergen_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT allergen_id FROM `{$alg}` WHERE item_id = %d ORDER BY allergen_id ASC", $item_id ) // phpcs:ignore WordPress.DB
		);
		$variants     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM `{$var}` WHERE item_id = %d ORDER BY sort_order ASC, id ASC", $item_id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);

		return array(
			'category_ids' => array_map( 'intval', (array) $category_ids ),
			'tag_ids'      => array_map( 'intval', (array) $tag_ids ),
			'allergen_ids' => array_map( 'intval', (array) $allergen_ids ),
			'variants'     => array_map( array( __CLASS__, 'hydrate_variant' ), is_array( $variants ) ? $variants : array() ),
		);
	}

	/**
	 * Cast a variant row to API shape.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	private static function hydrate_variant( array $row ) {
		return array(
			'id'         => (int) $row['id'],
			'label'      => (string) $row['label'],
			'price'      => (float) $row['price'],
			'sort_order' => (int) $row['sort_order'],
			'is_active'  => (int) $row['is_active'] === 1,
		);
	}

	/**
	 * Cast raw DB strings + related-entity IDs to the API shape.
	 *
	 * @param array<string,mixed> $row Raw row from wpdb.
	 * @return array<string,mixed>
	 */
	private static function hydrate( array $row ) {
		$item_id  = (int) $row['id'];
		$relations = self::load_relations( $item_id );

		$hydrated = array(
			'id'                => $item_id,
			'name'              => (string) $row['name'],
			'slug'              => (string) $row['slug'],
			'description_short' => isset( $row['description_short'] ) ? (string) $row['description_short'] : '',
			'description_long'  => isset( $row['description_long'] ) ? (string) $row['description_long'] : '',
			'price'             => isset( $row['price'] ) && null !== $row['price'] ? (float) $row['price'] : null,
			'media_id'          => isset( $row['media_id'] ) ? (int) $row['media_id'] : 0,
			'sort_order'        => (int) $row['sort_order'],
			'is_active'         => (int) $row['is_active'] === 1,
			'created_at'        => (string) $row['created_at'],
			'updated_at'        => (string) $row['updated_at'],
		);

		return array_merge( $hydrated, $relations );
	}
}
