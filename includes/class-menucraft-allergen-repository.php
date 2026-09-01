<?php
/**
 * Data-access layer for menucraft_allergens.
 *
 * Allergens are shaped differently from categories/tags: the stable
 * identifier is the admin-provided `code` (unique), there is no slug,
 * color, media_id or parent_id.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allergen repository.
 */
class MenuCraft_Allergen_Repository {

	/**
	 * Return the fully prefixed table name.
	 *
	 * @return string
	 */
	public static function table() {
		$tables = MenuCraft_Schema::tables();
		return $tables['allergens'];
	}

	/**
	 * Fetch a single allergen by primary key.
	 *
	 * @param int $id Allergen ID.
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
	 * Fetch every allergen ordered by sort_order then id.
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
	 * Check whether a code is already taken.
	 *
	 * @param string $code       Candidate code (case-sensitive match against DB).
	 * @param int    $exclude_id Allergen ID to ignore (for update-flow reuse).
	 * @return bool
	 */
	public static function code_exists( $code, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::table();

		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE code = %s AND id <> %d", // phpcs:ignore WordPress.DB
				$code,
				(int) $exclude_id
			)
		);

		return $count > 0;
	}

	/**
	 * Insert a new allergen row.
	 *
	 * @param array<string,mixed> $data Sanitized attributes: code, name,
	 *                                  description, sort_order, is_active.
	 * @return array<string,mixed>|null The freshly loaded row or null on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', 1 );

		$row = array(
			'code'       => (string) $data['code'],
			'name'       => (string) $data['name'],
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

		$result = $wpdb->insert( self::table(), $row, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return null;
		}

		return self::find( (int) $wpdb->insert_id );
	}

	/**
	 * Update an allergen by id. Partial payloads supported.
	 *
	 * @param int                  $id   Allergen ID.
	 * @param array<string,mixed>  $data Partial attributes to update.
	 * @return array<string,mixed>|null The updated row or null on failure.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$table = self::table();

		$sets   = array();
		$values = array();

		$assign = function ( $column, $format ) use ( $data, &$sets, &$values ) {
			if ( ! array_key_exists( $column, $data ) ) {
				return;
			}
			$sets[]   = "`{$column}` = {$format}";
			$values[] = $data[ $column ];
		};

		$assign( 'code', '%s' );
		$assign( 'name', '%s' );
		$assign( 'description', '%s' );
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

		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return null;
		}

		return self::find( (int) $id );
	}

	/**
	 * Delete an allergen and its item-junction rows.
	 *
	 * @param int $id Allergen ID.
	 * @return bool True on success.
	 */
	public static function delete( $id ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();

		$junction = $tables['item_allergens'];
		$wpdb->delete( $junction, array( 'allergen_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

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
			'code'        => (string) $row['code'],
			'name'        => (string) $row['name'],
			'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
			'sort_order'  => (int) $row['sort_order'],
			'is_active'   => (int) $row['is_active'] === 1,
			'created_at'  => (string) $row['created_at'],
			'updated_at'  => (string) $row['updated_at'],
		);
	}
}
