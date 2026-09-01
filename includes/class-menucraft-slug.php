<?php
/**
 * Slug generator shared across MenuCraft repositories.
 *
 * Slug format: {prefix}-{sanitize_title(name)}. On collision the counter
 * suffix "-2", "-3", … is appended, mirroring wp_unique_post_slug().
 *
 * @package MenuCraft
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slug helper.
 */
class MenuCraft_Slug {

	/**
	 * Generate a unique slug.
	 *
	 * @param string   $prefix Table-level prefix, e.g. "categories".
	 * @param string   $name   Human-readable name to slugify.
	 * @param callable $exists Callback that takes a candidate slug and returns
	 *                         true when it collides with an existing row.
	 * @return string
	 */
	public static function generate( $prefix, $name, callable $exists ) {
		$base = $prefix . '-' . sanitize_title( $name );

		if ( ! $exists( $base ) ) {
			return $base;
		}

		$suffix = 2;
		while ( $exists( $base . '-' . $suffix ) ) {
			$suffix++;
		}

		return $base . '-' . $suffix;
	}
}
