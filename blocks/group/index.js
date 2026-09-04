/**
 * MenuCraft — Gutenberg "group" block editor script.
 *
 * Dynamic block: the render_callback (in class-menucraft-group-block.php)
 * wraps the [menucraft_group] shortcode. This script drives the sidebar
 * and shows a live ServerSideRender preview.
 *
 * The Source panel loads Categories and Tags via REST so the author can
 * pick a specific one from a dropdown instead of typing a slug.
 *
 * Vanilla JS, no JSX, no build step.
 */
( function ( wp ) {
	'use strict';

	var el        = wp.element.createElement;
	var Fragment  = wp.element.Fragment;
	var useState  = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __        = wp.i18n.__;
	var apiFetch  = wp.apiFetch;
	var registerBlockType = wp.blocks.registerBlockType;

	var InspectorControls  = wp.blockEditor.InspectorControls;
	var PanelColorSettings = wp.blockEditor.PanelColorSettings;
	var useBlockProps      = wp.blockEditor.useBlockProps;

	var PanelBody     = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl   = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl  = wp.components.RangeControl;
	var Spinner       = wp.components.Spinner;

	var ServerSideRender = wp.serverSideRender
		|| ( wp.editor && wp.editor.ServerSideRender )
		|| ( wp.components && wp.components.ServerSideRender );

	var colorGroups = [
		{
			title: __( 'Colors — Container', 'menucraft' ),
			colors: [
				{ attr: 'bgColor',   label: __( 'Background', 'menucraft' ) },
				{ attr: 'textColor', label: __( 'Text (fallback)', 'menucraft' ) },
			],
		},
		{
			title: __( 'Colors — Header', 'menucraft' ),
			colors: [
				{ attr: 'headerBg',         label: __( 'Header background', 'menucraft' ) },
				{ attr: 'headerTitleColor', label: __( 'Header title', 'menucraft' ) },
				{ attr: 'headerDescColor',  label: __( 'Header description', 'menucraft' ) },
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
				enableAlpha: true,
				onChange: function ( v ) {
					var upd = {};
					upd[ slot.attr ] = v || '';
					set( upd );
				},
			};
		} );
	}

	registerBlockType( 'menucraft/group', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var set   = props.setAttributes;
			var blockProps = useBlockProps ? useBlockProps() : {};

			// Load source lists once per editor session.
			var sourcesState = useState( { categories: null, tags: null } );
			var sources      = sourcesState[ 0 ];
			var setSources   = sourcesState[ 1 ];

			useEffect( function () {
				if ( ! apiFetch ) return;
				apiFetch( { path: 'menucraft/v1/categories' } ).then( function ( rows ) {
					setSources( function ( prev ) {
						return { categories: rows || [], tags: prev.tags };
					} );
				} ).catch( function () {
					setSources( function ( prev ) { return { categories: [], tags: prev.tags }; } );
				} );
				apiFetch( { path: 'menucraft/v1/tags' } ).then( function ( rows ) {
					setSources( function ( prev ) {
						return { categories: prev.categories, tags: rows || [] };
					} );
				} ).catch( function () {
					setSources( function ( prev ) { return { categories: prev.categories, tags: [] }; } );
				} );
			}, [] );

			var currentList = 'tag' === attrs.source ? sources.tags : sources.categories;
			var loading     = currentList === null;

			var sourceOptions = [ { label: __( '— pick one —', 'menucraft' ), value: 0 } ];
			if ( currentList && currentList.length ) {
				currentList.forEach( function ( row ) {
					sourceOptions.push( { label: row.name, value: row.id } );
				} );
			}

			var gridEnabled = attrs.columns && attrs.columns.length > 0;

			var inspectorChildren = [
				el(
					PanelBody,
					{ title: __( 'Source', 'menucraft' ), initialOpen: true, key: 'source' },
					el( SelectControl, {
						label: __( 'Show items from', 'menucraft' ),
						value: attrs.source,
						options: [
							{ label: __( 'Category', 'menucraft' ), value: 'category' },
							{ label: __( 'Tag',      'menucraft' ), value: 'tag'      }
						],
						onChange: function ( v ) {
							set( { source: v, sourceId: 0 } );
						}
					} ),
					loading
						? el( 'p', {}, el( Spinner ), ' ', __( 'Loading…', 'menucraft' ) )
						: el( SelectControl, {
							label: 'tag' === attrs.source
								? __( 'Tag', 'menucraft' )
								: __( 'Category', 'menucraft' ),
							value: attrs.sourceId,
							options: sourceOptions,
							onChange: function ( v ) { set( { sourceId: parseInt( v, 10 ) || 0 } ); }
						} )
				),
				el(
					PanelBody,
					{ title: __( 'Header', 'menucraft' ), initialOpen: false, key: 'header' },
					el( ToggleControl, {
						label: __( 'Show header (image, title, description)', 'menucraft' ),
						checked: 'show' === attrs.showHeader,
						onChange: function ( on ) { set( { showHeader: on ? 'show' : 'hide' } ); }
					} ),
					el( ToggleControl, {
						label: __( 'Start collapsed', 'menucraft' ),
						help:  __( 'Show only title + description; visitors click to reveal image and items.', 'menucraft' ),
						checked: 'yes' === attrs.collapsed,
						onChange: function ( on ) { set( { collapsed: on ? 'yes' : 'no' } ); }
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Layout', 'menucraft' ), initialOpen: false, key: 'layout' },
					el( SelectControl, {
						label: __( 'Image position (per item)', 'menucraft' ),
						value: attrs.image,
						options: [
							{ label: __( 'Left',  'menucraft' ), value: 'left'  },
							{ label: __( 'Right', 'menucraft' ), value: 'right' },
							{ label: __( 'Top',   'menucraft' ), value: 'top'   }
						],
						onChange: function ( v ) { set( { image: v } ); }
					} ),
					el( SelectControl, {
						label: __( 'Variants', 'menucraft' ),
						value: attrs.variants,
						options: [
							{ label: __( 'Inline on the card', 'menucraft' ), value: 'inline' },
							{ label: __( 'Only in modal', 'menucraft' ),      value: 'modal'  }
						],
						onChange: function ( v ) { set( { variants: v } ); }
					} ),
					el( ToggleControl, {
						label: __( 'Show allergen legend', 'menucraft' ),
						checked: 'show' === attrs.allergensLegend,
						onChange: function ( on ) { set( { allergensLegend: on ? 'show' : 'hide' } ); }
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
					el(
						PanelBody,
						{ title: group.title, initialOpen: false, key: 'colors-panel-' + idx },
						el( PanelColorSettings, {
							title: '',
							colorSettings: colorSettingsFor( group, attrs, set )
						} )
					)
				);
			} );

			return el(
				Fragment,
				{},
				el( InspectorControls, {}, inspectorChildren ),
				el(
					'div',
					blockProps,
					attrs.sourceId
						? ( ServerSideRender
							? el( ServerSideRender, { block: 'menucraft/group', attributes: attrs } )
							: el( 'p', {}, __( 'Preview unavailable in this WordPress version.', 'menucraft' ) )
						)
						: el( 'p', { className: 'menucraft-block-hint' }, __( 'Pick a category or tag in the sidebar to see the preview.', 'menucraft' ) )
				)
			);
		},

		save: function () {
			return null;
		}
	} );
}( window.wp ) );
