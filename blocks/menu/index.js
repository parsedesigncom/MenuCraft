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

	var InspectorControls   = wp.blockEditor.InspectorControls;
	var PanelColorSettings  = wp.blockEditor.PanelColorSettings;
	var useBlockProps       = wp.blockEditor.useBlockProps;

	var PanelBody     = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl  = wp.components.RangeControl;

	// ServerSideRender lives at different locations across WP versions;
	// fall back through the known ones.
	var ServerSideRender = wp.serverSideRender
		|| ( wp.editor && wp.editor.ServerSideRender )
		|| ( wp.components && wp.components.ServerSideRender );

	// -------- Color-slot registry --------
	// Grouped for the sidebar UI. Each `attr` name must exist in
	// block.json and be mirrored in class-menucraft-block.php::color_slots().
	var colorGroups = [
		{
			title: __( 'Colors — Container', 'menucraft' ),
			colors: [
				{ attr: 'bgColor',   label: __( 'Background', 'menucraft' ) },
				{ attr: 'textColor', label: __( 'Text (fallback)', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Filter', 'menucraft' ),
			colors: [
				{ attr: 'filterBarBg',      label: __( 'Filter bar background', 'menucraft' ) },
				{ attr: 'filterBarBorder',  label: __( 'Filter bar border', 'menucraft' ) },
				{ attr: 'filterLabelColor', label: __( 'Filter labels', 'menucraft' ) },
				{ attr: 'chipBg',           label: __( 'Chip background', 'menucraft' ) },
				{ attr: 'chipText',         label: __( 'Chip text', 'menucraft' ) },
				{ attr: 'chipBorder',       label: __( 'Chip border', 'menucraft' ) },
				{ attr: 'chipActiveBg',     label: __( 'Chip active background', 'menucraft' ) },
				{ attr: 'chipActiveText',   label: __( 'Chip active text', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Items', 'menucraft' ),
			colors: [
				{ attr: 'itemBg',              label: __( 'Card background', 'menucraft' ) },
				{ attr: 'itemBorder',          label: __( 'Card border', 'menucraft' ) },
				{ attr: 'itemTitleColor',      label: __( 'Title', 'menucraft' ) },
				{ attr: 'itemDescColor',       label: __( 'Description', 'menucraft' ) },
				{ attr: 'itemPriceColor',      label: __( 'Price', 'menucraft' ) },
				{ attr: 'allergenSupColor',    label: __( 'Allergen superscript', 'menucraft' ) },
				{ attr: 'variantDividerColor', label: __( 'Variant divider', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Tags', 'menucraft' ),
			colors: [
				{ attr: 'tagBorder', label: __( 'Tag pill border', 'menucraft' ) },
				{ attr: 'tagText',   label: __( 'Tag pill text', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Allergen legend', 'menucraft' ),
			colors: [
				{ attr: 'legendBg',   label: __( 'Legend background', 'menucraft' ) },
				{ attr: 'legendText', label: __( 'Legend text', 'menucraft' ) },
			],
		},
	];

	function colorSettingsFor( group, attrs, set ) {
		return group.colors.map( function ( slot ) {
			return {
				label:       slot.label,
				value:       attrs[ slot.attr ] || '',
				// Show the alpha slider in the color picker so authors
				// can dial in transparency (e.g. rgba(0,0,0,0.5) or a
				// #RRGGBBAA hex). Values pass through the PHP sanitizer
				// which already accepts hex-with-alpha, rgba() and hsla().
				enableAlpha: true,
				onChange: function ( v ) {
					var upd = {};
					upd[ slot.attr ] = v || '';
					set( upd );
				},
			};
		} );
	}

	registerBlockType( 'menucraft/menu', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var set   = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var gridEnabled = attrs.columns && attrs.columns.length > 0;

			// Build the ordered list of inspector children.
			var inspectorChildren = [
				el(
					PanelBody,
					{ title: __( 'Layout', 'menucraft' ), initialOpen: true, key: 'layout' },
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
					{ title: __( 'Filter titles', 'menucraft' ), initialOpen: false, key: 'titles' },
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
					{ title: __( 'Grid layout', 'menucraft' ), initialOpen: false, key: 'grid' },
					el( ToggleControl, {
						label: __( 'Show items as a grid', 'menucraft' ),
						checked: gridEnabled,
						onChange: function ( on ) {
							set( { columns: on ? '720__1 1024__2 1400__3' : '' } );
						}
					} ),
					gridEnabled && el( TextControl, {
						label: __( 'Columns spec', 'menucraft' ),
						help:  __( 'Format: <max-width>__<columns>, space-separated. Example: 720__1 1024__2 1400__3', 'menucraft' ),
						value: attrs.columns,
						onChange: function ( v ) { set( { columns: v } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Alignment & size', 'menucraft' ), initialOpen: false, key: 'align' },
					el( SelectControl, {
						label: __( 'Font size', 'menucraft' ),
						help:  __( 'Scales the whole menu up or down.', 'menucraft' ),
						value: attrs.fontScale,
						options: [
							{ label: __( 'Small',  'menucraft' ), value: 'small'  },
							{ label: __( 'Medium', 'menucraft' ), value: 'medium' },
							{ label: __( 'Large',  'menucraft' ), value: 'large'  }
						],
						onChange: function ( v ) { set( { fontScale: v } ); }
					} ),
					el( SelectControl, {
						label: __( 'Filter alignment', 'menucraft' ),
						value: attrs.filterAlign,
						options: [
							{ label: __( 'Left',   'menucraft' ), value: 'left'   },
							{ label: __( 'Center', 'menucraft' ), value: 'center' },
							{ label: __( 'Right',  'menucraft' ), value: 'right'  }
						],
						onChange: function ( v ) { set( { filterAlign: v } ); }
					} ),
					el( SelectControl, {
						label: __( 'Item content alignment', 'menucraft' ),
						value: attrs.itemAlign,
						options: [
							{ label: __( 'Left',   'menucraft' ), value: 'left'   },
							{ label: __( 'Center', 'menucraft' ), value: 'center' },
							{ label: __( 'Right',  'menucraft' ), value: 'right'  }
						],
						onChange: function ( v ) { set( { itemAlign: v } ); }
					} ),
					el( RangeControl, {
						label: __( 'Border radius (px)', 'menucraft' ),
						help:  __( 'One value for the whole block — container, filter, items, image and tags.', 'menucraft' ),
						value: '' === attrs.borderRadius ? undefined : parseInt( attrs.borderRadius, 10 ),
						min:   0,
						max:   40,
						step:  1,
						allowReset: true,
						onChange: function ( v ) {
							set( { borderRadius: ( v === undefined || v === null ) ? '' : String( v ) } );
						}
					} )
				),
			];

			// Append one PanelColorSettings per color group.
			colorGroups.forEach( function ( group, idx ) {
				inspectorChildren.push(
					el( PanelColorSettings, {
						title: group.title,
						initialOpen: false,
						key: 'colors-' + idx,
						colorSettings: colorSettingsFor( group, attrs, set )
					} )
				);
			} );

			return el(
				Fragment,
				{},
				el( InspectorControls, {}, inspectorChildren ),
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
