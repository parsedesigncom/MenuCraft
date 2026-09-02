<?php
/**
 * Data-access layer for menucraft_offers.
 *
 * An offer owns a 1:N list of line items (offer_items). Each line points at
 * an item and, when that item has variants, at a specific variant. Lines
 * are replaced whole on every write to keep the API simple — callers pass
 * the full desired state; the repository reconciles.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offer repository.
 */
class MenuCraft_Offer_Repository {

	/**
	 * Return the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = MenuCraft_Schema::tables();
		return $tables['offers'];
	}

	/**
	 * Fetch a single offer by primary key, fully hydrated.
	 *
	 * @param int $id Offer ID.
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
	 * Fetch all offers, ordered by sort_order then id, each hydrated with
	 * its line items.
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
	 * @param int    $exclude_id Offer ID to ignore (for update-flow reuse).
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
	 * Insert a new offer along with its line items.
	 *
	 * @param array<string,mixed> $data Sanitized attributes. Recognized keys:
	 *   name, slug, description, conditions_text, price (float), media_id,
	 *   valid_from (Y-m-d H:i:s|null), valid_until (Y-m-d H:i:s|null),
	 *   sort_order, is_active,
	 *   items (array<array{item_id,variant_id?,quantity?,sort_order?}>).
	 * @return array<string,mixed>|null Fresh hydrated row or null on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', 1 );

		// All columns are listed explicitly — wpdb->insert writes NULL for
		// null values regardless of format, so nullable fields (media_id,
		// valid_from/until, description, conditions_text) collapse to NULL
		// cleanly without conditional array assembly.
		$row = array(
			'name'            => (string) $data['name'],
			'slug'            => (string) $data['slug'],
			'description'     => isset( $data['description'] ) && '' !== $data['description'] ? (string) $data['description'] : null,
			'conditions_text' => isset( $data['conditions_text'] ) && '' !== $data['conditions_text'] ? (string) $data['conditions_text'] : null,
			'price'           => isset( $data['price'] ) ? (float) $data['price'] : 0.0,
			'media_id'        => ! empty( $data['media_id'] ) ? (int) $data['media_id'] : null,
			'valid_from'      => ! empty( $data['valid_from'] ) ? (string) $data['valid_from'] : null,
			'valid_until'     => ! empty( $data['valid_until'] ) ? (string) $data['valid_until'] : null,
			'sort_order'      => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'is_active'       => ! empty( $data['is_active'] ) ? 1 : 0,
			'created_at'      => $now,
			'updated_at'      => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%d', '%d', '%s', '%s' );

		$result = $wpdb->insert( self::table(), $row, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return null;
		}

		$offer_id = (int) $wpdb->insert_id;

		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			self::replace_items( $offer_id, $data['items'] );
		}

		return self::find( $offer_id );
	}

	/**
	 * Partial update of an offer. Only keys present in $data are touched.
	 *
	 * @param int                 $id   Offer ID.
	 * @param array<string,mixed> $data Partial attributes.
	 * @return array<string,mixed>|null Updated row or null on failure.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$id = (int) $id;

		// Nullable-aware update spec. Each entry describes a column and how
		// to coerce the incoming value. Only keys present in $data are
		// touched (partial update); the rest are left untouched.
		$spec = array(
			'name'            => array(
				'coerce' => function ( $v ) { return (string) $v; },
			),
			'slug'            => array(
				'coerce' => function ( $v ) { return (string) $v; },
			),
			'description'     => array(
				'nullable' => true,
				'coerce'   => function ( $v ) { return (string) $v; },
			),
			'conditions_text' => array(
				'nullable' => true,
				'coerce'   => function ( $v ) { return (string) $v; },
			),
			'price'           => array(
				'coerce' => function ( $v ) { return (float) $v; },
			),
			'media_id'        => array(
				'nullable' => true,
				'coerce'   => function ( $v ) { return (int) $v; },
			),
			'valid_from'      => array(
				'nullable' => true,
				'coerce'   => function ( $v ) { return (string) $v; },
			),
			'valid_until'     => array(
				'nullable' => true,
				'coerce'   => function ( $v ) { return (string) $v; },
			),
			'sort_order'      => array(
				'coerce' => function ( $v ) { return (int) $v; },
			),
			'is_active'       => array(
				'coerce' => function ( $v ) { return ! empty( $v ) ? 1 : 0; },
			),
		);

		$row     = array();
		$formats = array();

		foreach ( $spec as $column => $meta ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}
			$value    = $data[ $column ];
			$nullable = ! empty( $meta['nullable'] );
			$is_empty = ( null === $value || '' === $value );

			if ( $nullable && $is_empty ) {
				$row[ $column ]     = null;
				$formats[ $column ] = '%s';
			} else {
				$row[ $column ]     = $meta['coerce']( $value );
				if ( 'price' === $column ) {
					$formats[ $column ] = '%f';
				} elseif ( in_array( $column, array( 'media_id', 'sort_order', 'is_active' ), true ) ) {
					$formats[ $column ] = '%d';
				} else {
					$formats[ $column ] = '%s';
				}
			}
		}

		if ( ! empty( $row ) ) {
			$row['updated_at']     = current_time( 'mysql', 1 );
			$formats['updated_at'] = '%s';

			$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				$row,
				array( 'id' => $id ),
				array_values( $formats ),
				array( '%d' )
			);
			if ( false === $result ) {
				return null;
			}
		}

		if ( array_key_exists( 'items', $data ) && is_array( $data['items'] ) ) {
			self::replace_items( $id, $data['items'] );
		}

		return self::find( $id );
	}

	/**
	 * Delete an offer and all its line items.
	 *
	 * @param int $id Offer ID.
	 * @return bool True on success.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$id     = (int) $id;

		$wpdb->delete( $tables['offer_items'], array( 'offer_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$deleted = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (bool) $deleted;
	}

	/**
	 * Names of offers that currently reference a given item — used by the
	 * item-delete guard so users see which offers block the delete.
	 *
	 * @param int $item_id Item ID.
	 * @return array<int,string> Offer names, keyed by offer id.
	 */
	public static function offers_using_item( $item_id ) {
		global $wpdb;
		$tables    = MenuCraft_Schema::tables();
		$offers    = self::table();
		$junction  = $tables['offer_items'];
		$item_id   = (int) $item_id;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT o.id, o.name FROM `{$offers}` o INNER JOIN `{$junction}` oi ON oi.offer_id = o.id WHERE oi.item_id = %d ORDER BY o.name ASC", // phpcs:ignore WordPress.DB
				$item_id
			),
			ARRAY_A
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$out[ (int) $r['id'] ] = (string) $r['name'];
			}
		}
		return $out;
	}

	/**
	 * Names of offers that currently reference a given variant — used by
	 * the variant-delete guard.
	 *
	 * @param int $variant_id Variant ID.
	 * @return array<int,string>
	 */
	public static function offers_using_variant( $variant_id ) {
		global $wpdb;
		$tables    = MenuCraft_Schema::tables();
		$offers    = self::table();
		$junction  = $tables['offer_items'];
		$variant_id = (int) $variant_id;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT o.id, o.name FROM `{$offers}` o INNER JOIN `{$junction}` oi ON oi.offer_id = o.id WHERE oi.variant_id = %d ORDER BY o.name ASC", // phpcs:ignore WordPress.DB
				$variant_id
			),
			ARRAY_A
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$out[ (int) $r['id'] ] = (string) $r['name'];
			}
		}
		return $out;
	}

	/**
	 * Replace all line items for an offer with the provided list. Rows
	 * with an invalid item_id are silently dropped; variant_id is stored
	 * as-is (validation lives at the REST layer).
	 *
	 * @param int                            $offer_id Offer ID.
	 * @param array<int,array<string,mixed>> $items    Line-item payloads.
	 */
	private static function replace_items( $offer_id, array $items ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['offer_items'];

		$wpdb->delete( $table, array( 'offer_id' => (int) $offer_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$position = 0;
		foreach ( $items as $line ) {
			$item_id = isset( $line['item_id'] ) ? (int) $line['item_id'] : 0;
			if ( $item_id <= 0 ) {
				continue;
			}

			$variant_id = isset( $line['variant_id'] ) && $line['variant_id'] ? (int) $line['variant_id'] : null;
			$quantity   = isset( $line['quantity'] ) ? max( 1, (int) $line['quantity'] ) : 1;
			$sort       = isset( $line['sort_order'] ) ? (int) $line['sort_order'] : $position;

			$row     = array(
				'offer_id'   => (int) $offer_id,
				'item_id'    => $item_id,
				'quantity'   => $quantity,
				'sort_order' => $sort,
			);
			$formats = array( '%d', '%d', '%d', '%d' );

			if ( null !== $variant_id ) {
				$row['variant_id'] = $variant_id;
				$formats[]         = '%d';
			}

			$wpdb->insert( $table, $row, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$position++;
		}
	}

	/**
	 * Load line items for an offer.
	 *
	 * @param int $offer_id Offer ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function load_items( $offer_id ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['offer_items'];

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE offer_id = %d ORDER BY sort_order ASC, id ASC", (int) $offer_id ), // phpcs:ignore WordPress.DB
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'hydrate_item' ), $rows );
	}

	/**
	 * Cast an offer_items row to API shape.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	private static function hydrate_item( array $row ) {
		return array(
			'id'         => (int) $row['id'],
			'item_id'    => (int) $row['item_id'],
			'variant_id' => isset( $row['variant_id'] ) && null !== $row['variant_id'] ? (int) $row['variant_id'] : null,
			'quantity'   => (int) $row['quantity'],
			'sort_order' => (int) $row['sort_order'],
		);
	}

	/**
	 * Cast raw DB strings to the API shape, adding items[].
	 *
	 * @param array<string,mixed> $row Raw row from wpdb.
	 * @return array<string,mixed>
	 */
	private static function hydrate( array $row ) {
		$offer_id = (int) $row['id'];

		return array(
			'id'              => $offer_id,
			'name'            => (string) $row['name'],
			'slug'            => (string) $row['slug'],
			'description'     => isset( $row['description'] ) ? (string) $row['description'] : '',
			'conditions_text' => isset( $row['conditions_text'] ) ? (string) $row['conditions_text'] : '',
			'price'           => isset( $row['price'] ) ? (float) $row['price'] : 0.0,
			'media_id'        => isset( $row['media_id'] ) ? (int) $row['media_id'] : 0,
			'valid_from'      => isset( $row['valid_from'] ) && null !== $row['valid_from'] ? (string) $row['valid_from'] : null,
			'valid_until'     => isset( $row['valid_until'] ) && null !== $row['valid_until'] ? (string) $row['valid_until'] : null,
			'sort_order'      => (int) $row['sort_order'],
			'is_active'       => (int) $row['is_active'] === 1,
			'created_at'      => (string) $row['created_at'],
			'updated_at'      => (string) $row['updated_at'],
			'items'           => self::load_items( $offer_id ),
		);
	}
}
