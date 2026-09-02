<?php
/**
 * Dependency + version manifest for blocks/menu/index.js.
 *
 * WordPress reads this automatically when the block.json editorScript
 * uses a `file:` path — the returned deps determine what runs before
 * our script, and the returned version is used for cache-busting.
 *
 * @package MenuCraft
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-block-editor',
		'wp-components',
		'wp-element',
		'wp-i18n',
		'wp-server-side-render',
	),
	'version'      => defined( 'MENUCRAFT_VERSION' ) ? MENUCRAFT_VERSION : '0.0.0',
);
