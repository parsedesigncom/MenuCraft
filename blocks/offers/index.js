/**
 * MenuCraft — Gutenberg "offers" block editor script.
 *
 * Dynamic block: the frontend HTML is produced server-side (by wrapping
 * the [menucraft_offers] shortcode); this script only wires up the block-
 * inserter entry, the inspector controls in the sidebar, and a
 * ServerSideRender preview so authors see the real offers inside the
 * editor.
 *
 * Written in plain JS (no JSX / no build step) so the plugin ships
 * uncompiled — WP.org compliant out of the box.
 */
( function ( wp ) {
	'use strict';

	var el       = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __       = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;

	var InspectorControls  = wp.blockEditor.InspectorControls;
	var PanelColorSettings = wp.blockEditor.PanelColorSettings;
	var useBlockProps      = wp.blockEditor.useBlockProps;

	var PanelBody     = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl  = wp.components.RangeControl;

	var ServerSideRender = wp.serverSideRender
		|| ( wp.editor && wp.editor.ServerSideRender )
		|| ( wp.components && wp.components.ServerSideRender );

	// Grouped color slots — must mirror class-menucraft-offers-block.php.
	var colorGroups = [
		{
			title: __( 'Colors — Container', 'menucraft' ),
			colors: [
				{ attr: 'bgColor',   label: __( 'Background', 'menucraft' ) },
				{ attr: 'textColor', label: __( 'Text (fallback)', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Cards', 'menucraft' ),
			colors: [
				{ attr: 'cardBg',         label: __( 'Card background', 'menucraft' ) },
				{ attr: 'cardBorder',     label: __( 'Card border', 'menucraft' ) },
				{ attr: 'cardTitleColor', label: __( 'Title', 'menucraft' ) },
				{ attr: 'cardDescColor',  label: __( 'Description', 'menucraft' ) },
				{ attr: 'cardPriceColor', label: __( 'Price', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Offer details', 'menucraft' ),
			colors: [
				{ attr: 'linesColor',      label: __( 'Composition list', 'menucraft' ) },
				{ attr: 'validityColor',   label: __( 'Validity dates', 'menucraft' ) },
				{ attr: 'conditionsColor', label: __( 'Conditions text', 'menucraft' ) },
			],
		},
	];

	function colorSettingsFor( group, attrs, set ) {
		return group.colors.map( function ( slot ) {
			return {
				label:       slot.label,
				value:       attrs[ slot.attr ] || '',
				enableAlpha: true,
				onChange: function ( v ) {
					var upd = {};
					upd[ slot.attr ] = v || '';
					set( upd );
				},
			};
		} );
	}

	registerBlockType( 'menucraft/offers', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var set   = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			var gridEnabled = attrs.columns && attrs.columns.length > 0;

			var inspectorChildren = [
				el(
					PanelBody,
					{ title: __( 'Content & layout', 'menucraft' ), initialOpen: true, key: 'content' },
					el( SelectControl, {
						label: __( 'Which offers?', 'menucraft' ),
						help:  __( 'Preview: running now or starting within 7 days. All: every active offer.', 'menucraft' ),
						value: attrs.validity,
						options: [
							{ label: __( 'Preview (default)', 'menucraft' ), value: 'preview' },
							{ label: __( 'All active', 'menucraft' ),        value: 'all' }
						],
						onChange: function ( v ) { set( { validity: v } ); }
					} ),
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
					el( ToggleControl, {
						label:   __( 'Show validity dates', 'menucraft' ),
						checked: 'show' === attrs.showDates,
						onChange: function ( on ) { set( { showDates: on ? 'show' : 'hide' } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Card vs. modal', 'menucraft' ), initialOpen: false, key: 'placement' },
					el( SelectControl, {
						label: __( 'Description', 'menucraft' ),
						value: attrs.showDesc,
						options: [
							{ label: __( 'On the card',   'menucraft' ), value: 'inline' },
							{ label: __( 'Only in modal', 'menucraft' ), value: 'modal'  },
							{ label: __( "Don't show",    'menucraft' ), value: 'hide'   }
						],
						onChange: function ( v ) { set( { showDesc: v } ); }
					} ),
					el( SelectControl, {
						label: __( 'Items list', 'menucraft' ),
						value: attrs.showItems,
						options: [
							{ label: __( 'On the card',   'menucraft' ), value: 'inline' },
							{ label: __( 'Only in modal', 'menucraft' ), value: 'modal'  },
							{ label: __( "Don't show",    'menucraft' ), value: 'hide'   }
						],
						onChange: function ( v ) { set( { showItems: v } ); }
					} ),
					el( SelectControl, {
						label: __( 'Conditions text', 'menucraft' ),
						value: attrs.conditions,
						options: [
							{ label: __( 'Only in modal', 'menucraft' ), value: 'modal'  },
							{ label: __( 'On the card',   'menucraft' ), value: 'inline' },
							{ label: __( "Don't show",    'menucraft' ), value: 'hide'   }
						],
						onChange: function ( v ) { set( { conditions: v } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Grid layout', 'menucraft' ), initialOpen: false, key: 'grid' },
					el( ToggleControl, {
						label: __( 'Show offers as a grid', 'menucraft' ),
						checked: gridEnabled,
						onChange: function ( on ) {
							set( { columns: on ? '720__1 1024__2 1400__3' : '' } );
						}
					} ),
					gridEnabled && el( TextControl, {
						label: __( 'Columns spec', 'menucraft' ),
						help:  __( 'Format: <max-width>__<columns>, space-separated.', 'menucraft' ),
						value: attrs.columns,
						onChange: function ( v ) { set( { columns: v } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Alignment & size', 'menucraft' ), initialOpen: false, key: 'align' },
					el( SelectControl, {
						label: __( 'Font size', 'menucraft' ),
						help:  __( 'Scales the whole block up or down.', 'menucraft' ),
						value: attrs.fontScale,
						options: [
							{ label: __( 'Small',  'menucraft' ), value: 'small'  },
							{ label: __( 'Medium', 'menucraft' ), value: 'medium' },
							{ label: __( 'Large',  'menucraft' ), value: 'large'  }
						],
						onChange: function ( v ) { set( { fontScale: v } ); }
					} ),
					el( SelectControl, {
						label: __( 'Card content alignment', 'menucraft' ),
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
						help:  __( 'One value for container, cards, image and modal.', 'menucraft' ),
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
							block: 'menucraft/offers',
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
