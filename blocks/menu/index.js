/**
 * MenuCraft — Gutenberg "menu" block editor script.
 *
 * Dynamic block: the frontend HTML is produced server-side (by wrapping
 * the [menucraft] shortcode); this script only wires up the block-inserter
 * entry, the inspector controls in the sidebar, and a ServerSideRender
 * preview so authors see the real menu inside the editor.
 *
 * Written in plain JS (no JSX / no build step) so the plugin ships
 * uncompiled — WP.org compliant out of the box.
 */
( function ( wp ) {
	'use strict';

	var el          = wp.element.createElement;
	var Fragment    = wp.element.Fragment;
	var __          = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;

	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;

	var PanelBody     = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;

	// ServerSideRender lives at different locations across WP versions;
	// fall back through the known ones.
	var ServerSideRender = wp.serverSideRender
		|| ( wp.editor && wp.editor.ServerSideRender )
		|| ( wp.components && wp.components.ServerSideRender );

	registerBlockType( 'menucraft/menu', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var set   = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var setColumns = function ( value ) {
				set( { columns: value } );
			};

			var gridEnabled = attrs.columns && attrs.columns.length > 0;

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Layout', 'menucraft' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Image position', 'menucraft' ),
							value: attrs.image,
							options: [
								{ label: __( 'Left', 'menucraft' ),  value: 'left'  },
								{ label: __( 'Right', 'menucraft' ), value: 'right' },
								{ label: __( 'Top', 'menucraft' ),   value: 'top'   }
							],
							onChange: function ( v ) { set( { image: v } ); }
						} ),
						el( SelectControl, {
							label: __( 'Variants', 'menucraft' ),
							help:  __( 'Show variants directly on the item, or only in the details window.', 'menucraft' ),
							value: attrs.variants,
							options: [
								{ label: __( 'Inline on the card', 'menucraft' ), value: 'inline' },
								{ label: __( 'Only in modal', 'menucraft' ),      value: 'modal'  }
							],
							onChange: function ( v ) { set( { variants: v } ); }
						} ),
						el( ToggleControl, {
							label:   __( 'Show allergen legend', 'menucraft' ),
							help:    __( 'A small allergen list is printed at the end of the menu. Hiding it also removes the code letters next to each item.', 'menucraft' ),
							checked: 'show' === attrs.allergensLegend,
							onChange: function ( on ) {
								set( { allergensLegend: on ? 'show' : 'hide' } );
							}
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Filter titles', 'menucraft' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Categories label', 'menucraft' ),
							value: attrs.categoriesTitle,
							onChange: function ( v ) { set( { categoriesTitle: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Tags label', 'menucraft' ),
							value: attrs.tagsTitle,
							onChange: function ( v ) { set( { tagsTitle: v } ); }
						} ),
						el( TextControl, {
							label: __( 'Allergens label', 'menucraft' ),
							value: attrs.allergensTitle,
							onChange: function ( v ) { set( { allergensTitle: v } ); }
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Grid layout', 'menucraft' ), initialOpen: false },
						el( ToggleControl, {
							label: __( 'Show items as a grid', 'menucraft' ),
							checked: gridEnabled,
							onChange: function ( on ) {
								setColumns( on ? '720__1 1024__2 1400__3' : '' );
							}
						} ),
						gridEnabled && el( TextControl, {
							label: __( 'Columns spec', 'menucraft' ),
							help:  __( 'Format: <max-width>__<columns>, space-separated. Example: 720__1 1024__2 1400__3', 'menucraft' ),
							value: attrs.columns,
							onChange: setColumns
						} )
					)
				),

				el(
					'div',
					blockProps,
					ServerSideRender
						? el( ServerSideRender, {
							block: 'menucraft/menu',
							attributes: attrs
						} )
						: el( 'p', {}, __( 'Preview unavailable in this WordPress version.', 'menucraft' ) )
				)
			);
		},

		// Dynamic block — server prints the HTML at request time.
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
