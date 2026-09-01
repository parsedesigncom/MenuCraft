<?php
/**
 * Data-access layer for menucraft_tags.
 *
 * Structurally identical to categories (per product decision) but kept
 * as its own class so callers use the right table and junction (item_tags).
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tag repository.
 */
class MenuCraft_Tag_Repository {

	/**
	 * Return the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = MenuCraft_Schema::tables();
		return $tables['tags'];
	}

	/**
	 * Fetch a single tag by primary key.
	 *
	 * @param int $id Tag ID.
	 * @return array<string,mixed>|null Row as associative array or null.
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
	 * Fetch every tag ordered by sort_order then id.
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
	 * @param int    $exclude_id Tag ID to ignore (for update-flow reuse).
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
	 * Insert a new tag row.
	 *
	 * @param array<string,mixed> $data Sanitized attributes.
	 * @return array<string,mixed>|null The freshly loaded row or null on failure.
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

		if ( ! empty( $data['description'] ) ) {
			$row['description'] = (string) $data['description'];
			$formats[]          = '%s';
		}

		if ( ! empty( $data['color'] ) ) {
			$row['color'] = (string) $data['color'];
			$formats[]    = '%s';
		}

		if ( ! empty( $data['parent_id'] ) ) {
			$row['parent_id'] = (int) $data['parent_id'];
			$formats[]        = '%d';
		}

		if ( ! empty( $data['media_id'] ) ) {
			$row['media_id'] = (int) $data['media_id'];
			$formats[]       = '%d';
		}

		$result = $wpdb->insert( self::table(), $row, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return null;
		}

		return self::find( (int) $wpdb->insert_id );
	}

	/**
	 * Update a tag by id. Partial payloads supported; NULL for empty
	 * nullable fields.
	 *
	 * @param int                  $id   Tag ID.
	 * @param array<string,mixed>  $data Partial attributes to update.
	 * @return array<string,mixed>|null The updated row or null on failure.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$table = self::table();

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
		$assign( 'description', '%s' );
		$assign( 'color', '%s' );
		$assign( 'media_id', '%d', true );
		$assign( 'parent_id', '%d', true );
		$assign( 'sort_order', '%d' );
		$assign( 'is_active', '%d' );

		if ( empty( $sets ) ) {
			return self::find( (int) $id );
		}

		$sets[]   = '`updated_at` = %s';
		$values[] = current_time( 'mysql', 1 );
		$values[] = (int) $id;

		$sql = sprintf(
			'UPDATE `%s` SET %s WHERE id = %%d',
			$table,
			implode( ', ', $sets )
		);

		// phpcs:ignore WordPress.DB
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return null;
		}

		return self::find( (int) $id );
	}

	/**
	 * Delete a tag and its item-junction rows.
	 *
	 * @param int $id Tag ID.
	 * @return bool True on success.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();

		$junction = $tables['item_tags'];
		$wpdb->delete( $junction, array( 'tag_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$deleted = $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return (bool) $deleted;
	}

	/**
	 * Cast raw DB strings to the types the API should hand back.
	 *
	 * @param array<string,mixed> $row Raw row from wpdb.
	 * @return array<string,mixed>
	 */
	private static function hydrate( array $row ) {
		return array(
			'id'          => (int) $row['id'],
			'name'        => (string) $row['name'],
			'slug'        => (string) $row['slug'],
			'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
			'color'       => isset( $row['color'] ) ? (string) $row['color'] : '',
			'media_id'    => isset( $row['media_id'] ) ? (int) $row['media_id'] : 0,
			'parent_id'   => isset( $row['parent_id'] ) ? (int) $row['parent_id'] : 0,
			'sort_order'  => (int) $row['sort_order'],
			'is_active'   => (int) $row['is_active'] === 1,
			'created_at'  => (string) $row['created_at'],
			'updated_at'  => (string) $row['updated_at'],
		);
	}
}
