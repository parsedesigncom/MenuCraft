<?php
/**
 * Key/value options helper backed by the plugin-owned menucraft_options table.
 *
 * Values are serialized transparently so arrays and objects can be stored.
 * Kept intentionally independent from wp_options so the plugin data set can
 * be exported/imported as a single SQL dump.
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Options accessor.
 */
class MenuCraft_Options {

	/**
	 * Fetch a stored option or the provided default when missing.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Value returned when the key does not exist.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['options'];

		// The table may not exist on the very first activation before create_tables() ran.
		$raw = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT option_value FROM `{$table}` WHERE option_key = %s LIMIT 1", $key ) // phpcs:ignore WordPress.DB
		);

		if ( null === $raw ) {
			return $default;
		}

		return maybe_unserialize( $raw );
	}

	/**
	 * Insert or update an option value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Value to persist (will be serialized if needed).
	 * @return bool True on success, false on failure.
	 */
	public static function update( $key, $value ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['options'];

		$serialized = maybe_serialize( $value );
		$now        = current_time( 'mysql', 1 );

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO `{$table}` (option_key, option_value, updated_at) VALUES (%s, %s, %s) " . // phpcs:ignore WordPress.DB
				'ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), updated_at = VALUES(updated_at)',
				$key,
				$serialized,
				$now
			)
		);

		return false !== $result;
	}

	/**
	 * Remove an option.
	 *
	 * @param string $key Option key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		global $wpdb;
		$tables = MenuCraft_Schema::tables();
		$table  = $tables['options'];

		$result = $wpdb->delete( $table, array( 'option_key' => $key ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return false !== $result;
	}
}
