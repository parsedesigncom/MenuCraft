<?php
/**
 * Dependency + version manifest for blocks/offers/index.js.
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
