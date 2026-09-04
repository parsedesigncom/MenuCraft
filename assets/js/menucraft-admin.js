/**
 * MenuCraft admin behaviour — vanilla JS, no library dependencies.
 *
 * Off-canvas panel (with sub-panel stacking):
 *  - open on click of [data-menucraft-panel-open="<id>"]
 *  - open a sub-panel on top of the current one via
 *    [data-menucraft-subpanel-open="<id>"]. The parent panel gets the
 *    `menucraft-offcanvas-behind` class so the user can visually tell
 *    they are one level deeper.
 *  - close on click of [data-menucraft-panel-close] or Escape.
 *
 * Confirm modal:
 *  - open programmatically via openModal(id).
 *
 * Media picker: opens wp.media, stores attachment ID in hidden input.
 *
 * Lists (resource-agnostic): every <table data-menucraft-list="<resource>">
 * is initialised on load with a resource-specific row builder.
 *
 * Chips: [data-menucraft-chips="<resource>"] renders selectable chips from
 * the cached list of that resource, collected into a hidden multi-value.
 *
 * Item variants: managed in-memory in the item form's JS state, edited in
 * a sub-panel, submitted alongside the item payload.
 */
( function () {
	'use strict';

	var settings = ( typeof window.menucraftAdmin === 'object' && window.menucraftAdmin ) || {};
	var i18n     = settings.i18n || {};

	var OPEN_CLASS   = 'menucraft-offcanvas-is-open';
	var BEHIND_CLASS = 'menucraft-offcanvas-behind';
	var MODAL_OPEN   = 'menucraft-modal-is-open';
	var BODY_LOCK    = 'menucraft-offcanvas-open';

	var panelStack   = []; // Ordered list of currently-open panel IDs (bottom → top).
	var focusStack   = []; // Element to focus when each panel closes.
	var listStates   = {}; // Per-resource state (cache + DOM refs + row builder).
	var deleteContext = null; // { id, name, resource } while the delete modal is open.

	// State that only the item form uses — variants live in JS memory
	// between "Manage Variants" clicks and the eventual form submit.
	var itemFormState = { variants: [] };

	// Same idea for the offer form — line items live in memory between
	// "Manage Items" clicks and submit.
	var offerFormState = { items: [] };

	// Per-resource selection state (Set of selected IDs). Only tables with
	// data-menucraft-selectable participate.
	var selections = {};

	// Per-resource client-side filter state.
	var filterState = {};

	function emptyFilterState() {
		return {
			search: '',
			category_ids: [],
			tag_ids: [],
			allergen_ids: [],
			status: '',
			price_min: null,
			price_max: null,
			image: '',
			validity: '',
		};
	}

	// ============================================================ Panel ==

	function openPanel( id ) {
		var panel = document.getElementById( id );
		if ( ! panel ) {
			return;
		}

		// Dim the panel that was on top before this one opens.
		if ( panelStack.length ) {
			var top = document.getElementById( panelStack[ panelStack.length - 1 ] );
			if ( top ) {
				top.classList.add( BEHIND_CLASS );
			}
		}

		focusStack.push( document.activeElement );
		panelStack.push( id );

		panel.classList.add( OPEN_CLASS );
		panel.classList.remove( BEHIND_CLASS );
		panel.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( BODY_LOCK );

		var target = panel.querySelector(
			'input:not([type="hidden"]), textarea, select, button:not([data-menucraft-panel-close])'
		);
		if ( target ) {
			requestAnimationFrame( function () { target.focus(); } );
		}
	}

	function closePanel( panel ) {
		if ( ! panel ) {
			return;
		}
		var id = panel.id;
		panel.classList.remove( OPEN_CLASS );
		panel.setAttribute( 'aria-hidden', 'true' );

		// Pop this panel off the stack (should be the top).
		var idx = panelStack.lastIndexOf( id );
		if ( idx > -1 ) {
			panelStack.splice( idx, 1 );
		}

		// Un-dim the new top-of-stack, if any.
		if ( panelStack.length ) {
			var newTop = document.getElementById( panelStack[ panelStack.length - 1 ] );
			if ( newTop ) {
				newTop.classList.remove( BEHIND_CLASS );
			}
		}

		if ( ! document.querySelector( '.' + OPEN_CLASS + ', .' + MODAL_OPEN ) ) {
			document.body.classList.remove( BODY_LOCK );
		}

		var toRestore = focusStack.pop();
		if ( toRestore && typeof toRestore.focus === 'function' ) {
			toRestore.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var opener = event.target.closest( '[data-menucraft-panel-open]' );
		if ( opener ) {
			event.preventDefault();
			var panelId = opener.getAttribute( 'data-menucraft-panel-open' );
			if ( 'create' === opener.getAttribute( 'data-menucraft-panel-mode' ) ) {
				resetPanelToCreateMode( panelId );
			}
			openPanel( panelId );
			return;
		}

		var subOpener = event.target.closest( '[data-menucraft-subpanel-open]' );
		if ( subOpener ) {
			event.preventDefault();
			var subPanelId = subOpener.getAttribute( 'data-menucraft-subpanel-open' );
			// Offer items sub-panel: make sure the items cache is loaded
			// (and chips + list are rendered) before the user sees an
			// empty picker, in case they open it before the initial fetch
			// resolved.
			if ( subPanelId === 'menucraft-panel-offer-items' ) {
				ensureResourceLoaded( 'items' );
				renderOfferItemsChips();
				renderOfferItemsList();
			}
			openPanel( subPanelId );
			return;
		}

		var closer = event.target.closest( '[data-menucraft-panel-close]' );
		if ( closer ) {
			event.preventDefault();
			closePanel( closer.closest( '.menucraft-offcanvas' ) );
		}
	} );

	// ============================================================ Modal ==

	function openModal( id ) {
		var modal = document.getElementById( id );
		if ( ! modal ) {
			return;
		}
		focusStack.push( document.activeElement );
		modal.classList.add( MODAL_OPEN );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( BODY_LOCK );

		var target = modal.querySelector( 'button:not([data-menucraft-modal-close])' );
		if ( target ) {
			requestAnimationFrame( function () { target.focus(); } );
		}
	}

	function closeModal( modal ) {
		if ( ! modal ) {
			return;
		}
		modal.classList.remove( MODAL_OPEN );
		modal.setAttribute( 'aria-hidden', 'true' );

		if ( ! document.querySelector( '.' + OPEN_CLASS + ', .' + MODAL_OPEN ) ) {
			document.body.classList.remove( BODY_LOCK );
		}
		var toRestore = focusStack.pop();
		if ( toRestore && typeof toRestore.focus === 'function' ) {
			toRestore.focus();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var closer = event.target.closest( '[data-menucraft-modal-close]' );
		if ( closer ) {
			event.preventDefault();
			closeModal( closer.closest( '.menucraft-modal' ) );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Escape' ) {
			return;
		}
		var modal = document.querySelector( '.menucraft-modal.' + MODAL_OPEN );
		if ( modal ) {
			closeModal( modal );
			return;
		}
		if ( panelStack.length ) {
			closePanel( document.getElementById( panelStack[ panelStack.length - 1 ] ) );
		}
	} );

	// ============================================================ Media ==

	document.addEventListener( 'click', function ( event ) {
		var chooseBtn = event.target.closest( '[data-menucraft-media-choose]' );
		if ( chooseBtn ) {
			event.preventDefault();
			openMediaPicker( chooseBtn.closest( '[data-menucraft-media-picker]' ) );
			return;
		}

		var removeBtn = event.target.closest( '[data-menucraft-media-remove]' );
		if ( removeBtn ) {
			event.preventDefault();
			clearMedia( removeBtn.closest( '[data-menucraft-media-picker]' ) );
		}
	} );

	function openMediaPicker( picker ) {
		if ( ! picker || typeof window.wp === 'undefined' || ! window.wp.media ) {
			showToast( i18n.mediaUnavail || 'Media library unavailable.', 'error' );
			return;
		}

		var frame = window.wp.media( {
			title:    i18n.mediaTitle || 'Select Image',
			button:   { text: i18n.mediaButton || 'Use this image' },
			library:  { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			setMedia( picker, attachment );
		} );

		frame.open();
	}

	function setMedia( picker, attachment ) {
		var input   = picker.querySelector( '[data-menucraft-media-input]' );
		var preview = picker.querySelector( '[data-menucraft-media-preview]' );
		var remove  = picker.querySelector( '[data-menucraft-media-remove]' );

		if ( input ) {
			input.value = attachment.id;
		}
		if ( preview ) {
			var url = attachment.url;
			if ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
				url = attachment.sizes.thumbnail.url;
			}
			preview.innerHTML = '';
			var img = document.createElement( 'img' );
			img.src = url;
			img.alt = attachment.alt || attachment.title || '';
			preview.appendChild( img );
		}
		if ( remove ) {
			remove.hidden = false;
		}
	}

	function setMediaByUrl( picker, mediaId, mediaUrl ) {
		var input   = picker.querySelector( '[data-menucraft-media-input]' );
		var preview = picker.querySelector( '[data-menucraft-media-preview]' );
		var remove  = picker.querySelector( '[data-menucraft-media-remove]' );

		if ( input ) {
			input.value = String( mediaId || 0 );
		}
		if ( preview ) {
			preview.innerHTML = '';
			if ( mediaUrl ) {
				var img = document.createElement( 'img' );
				img.src = mediaUrl;
				img.alt = '';
				preview.appendChild( img );
			}
		}
		if ( remove ) {
			remove.hidden = ! mediaId;
		}
	}

	function clearMedia( picker ) {
		var input   = picker.querySelector( '[data-menucraft-media-input]' );
		var preview = picker.querySelector( '[data-menucraft-media-preview]' );
		var remove  = picker.querySelector( '[data-menucraft-media-remove]' );

		if ( input ) {
			input.value = '0';
		}
		if ( preview ) {
			preview.innerHTML = '';
		}
		if ( remove ) {
			remove.hidden = true;
		}
	}

	// ======================================================== REST base ==

	function rest( path, options ) {
		options = options || {};
		var url  = settings.restUrl + path.replace( /^\//, '' );
		var init = {
			method:      options.method || 'GET',
			credentials: 'same-origin',
			headers:     {
				'Accept':     'application/json',
				'X-WP-Nonce': settings.restNonce,
			},
		};
		if ( options.body !== undefined ) {
			init.headers[ 'Content-Type' ] = 'application/json';
			init.body                      = JSON.stringify( options.body );
		}

		return fetch( url, init ).then( function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok ) {
					var err = new Error( ( body && body.message ) || 'Request failed.' );
					err.body   = body;
					err.status = response.status;
					throw err;
				}
				return body;
			} );
		} );
	}

	// =================================================== Form submit ====

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		var endpoint = form.getAttribute && form.getAttribute( 'data-menucraft-endpoint' );
		if ( ! endpoint ) {
			return;
		}
		event.preventDefault();
		submitForm( form, endpoint );
	} );

	function submitForm( form, endpoint ) {
		if ( ! settings.restUrl || ! settings.restNonce ) {
			showToast( 'REST settings missing.', 'error' );
			return;
		}

		var mode       = form.getAttribute( 'data-menucraft-mode' ) || 'create';
		var editId     = form.getAttribute( 'data-menucraft-id' );
		var panel      = form.closest( '.menucraft-offcanvas' );
		var saveButton = form.querySelector( '[data-menucraft-submit]' ) || form.querySelector( 'button[type="submit"]' );
		var payload    = collectFormData( form );

		// Chip selectors add their arrays to the payload.
		collectChipSelections( form, payload );
		collectSelectSelections( form, payload );

		// Items include their in-memory variants list. Existing variants
		// carry their DB id so the backend can UPDATE in place rather than
		// re-insert (which would invalidate offer_items references).
		if ( 'items' === endpoint ) {
			payload.variants = itemFormState.variants.map( function ( v ) {
				var out = {
					label:      v.label,
					price:      v.price,
					sort_order: v.sort_order,
				};
				if ( v.id ) {
					out.id = v.id;
				}
				return out;
			} );
			// Price: empty string means "unset" — send explicit null.
			if ( payload.price === '' ) {
				payload.price = null;
			}
		}

		// Offers include their in-memory line items. Empty date fields
		// become null so the backend's sanitizer treats them as "no limit"
		// instead of parsing an empty string.
		if ( 'offers' === endpoint ) {
			payload.items = offerFormState.items.map( function ( line, idx ) {
				return {
					item_id:    line.item_id,
					variant_id: line.variant_id || null,
					quantity:   line.quantity || 1,
					sort_order: idx,
				};
			} );
			if ( payload.valid_from === '' ) {
				payload.valid_from = null;
			}
			if ( payload.valid_until === '' ) {
				payload.valid_until = null;
			}
		}

		var path   = 'edit' === mode && editId ? endpoint + '/' + editId : endpoint;
		var method = 'edit' === mode && editId ? 'PUT' : 'POST';

		setBusy( saveButton, true );

		rest( path, { method: method, body: payload } )
			.then( function ( entity ) {
				var successMsg = 'edit' === mode ? ( i18n.updateSuccess || 'Updated.' ) : ( i18n.saveSuccess || 'Saved.' );
				showToast( successMsg, 'success' );

				form.reset();
				Array.prototype.forEach.call(
					form.querySelectorAll( '[data-menucraft-media-picker]' ),
					clearMedia
				);
				itemFormState.variants = [];
				offerFormState.items   = [];

				closePanel( panel );

				var state = listStates[ endpoint ];
				if ( state ) {
					if ( 'edit' === mode ) {
						replaceRow( state, entity );
					} else {
						appendRow( state, entity );
					}
				}
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.saveError || 'Save failed.', 'error' );
			} )
			.then( function () {
				setBusy( saveButton, false );
			} );
	}

	function collectFormData( form ) {
		var data = {};
		Array.prototype.forEach.call( form.elements, function ( el ) {
			if ( ! el.name || el.disabled ) {
				return;
			}
			if ( el.type === 'submit' || el.type === 'button' ) {
				return;
			}
			if ( el.type === 'checkbox' ) {
				data[ el.name ] = el.checked;
				return;
			}
			if ( el.type === 'radio' ) {
				if ( el.checked ) {
					data[ el.name ] = el.value;
				}
				return;
			}
			data[ el.name ] = el.value;
		} );
		return data;
	}

	function collectChipSelections( form, payload ) {
		var containers = form.querySelectorAll( '[data-menucraft-chips-name]' );
		Array.prototype.forEach.call( containers, function ( container ) {
			var name = container.getAttribute( 'data-menucraft-chips-name' );
			var ids  = Array.prototype.map.call(
				container.querySelectorAll( '.menucraft-chip.menucraft-chip-selected' ),
				function ( chip ) { return parseInt( chip.getAttribute( 'data-id' ), 10 ); }
			).filter( function ( n ) { return ! isNaN( n ); } );
			payload[ name ] = ids;
		} );
	}

	function setBusy( button, busy ) {
		if ( ! button ) {
			return;
		}
		if ( busy ) {
			button.disabled = true;
			button.dataset.originalText = button.textContent;
			button.textContent = i18n.saving || 'Saving…';
		} else {
			button.disabled = false;
			if ( button.dataset.originalText ) {
				button.textContent = button.dataset.originalText;
				delete button.dataset.originalText;
			}
		}
	}

	// ================================================== Panel modes ====

	function resetPanelToCreateMode( panelId ) {
		var panel = document.getElementById( panelId );
		if ( ! panel ) {
			return;
		}
		var form = panel.querySelector( '.menucraft-form' );
		if ( ! form ) {
			return;
		}
		form.reset();
		form.setAttribute( 'data-menucraft-mode', 'create' );
		form.removeAttribute( 'data-menucraft-id' );

		Array.prototype.forEach.call(
			form.querySelectorAll( '[data-menucraft-media-picker]' ),
			clearMedia
		);

		// Reset chips (deselect all) and re-render from cache.
		Array.prototype.forEach.call(
			form.querySelectorAll( '[data-menucraft-chips]' ),
			function ( container ) {
				renderChips( container, [] );
			}
		);

		// Reset select-boxes (clear pills + search input).
		Array.prototype.forEach.call(
			form.querySelectorAll( '[data-menucraft-select]' ),
			function ( container ) {
				resetSelect( container );
			}
		);

		// Reset item variants state + refresh UI counter.
		if ( form.getAttribute( 'data-menucraft-endpoint' ) === 'items' ) {
			itemFormState.variants = [];
			renderVariantsSummary();
			renderVariantsList();
		}

		// Same treatment for offers — clear line-items state + UI.
		if ( form.getAttribute( 'data-menucraft-endpoint' ) === 'offers' ) {
			offerFormState.items = [];
			renderOfferItemsSummary();
			renderOfferItemsChips();
			renderOfferItemsList();
		}

		var title = panel.querySelector( '[data-menucraft-title-create]' );
		if ( title ) {
			title.textContent = title.getAttribute( 'data-menucraft-title-create' );
		}

		var submit = form.querySelector( '[data-menucraft-submit]' );
		if ( submit && ! submit.dataset.originalText ) {
			submit.textContent = submit.getAttribute( 'data-menucraft-label-create' ) || submit.textContent;
		}
	}

	function openPanelInEditMode( panelId, entity ) {
		var panel = document.getElementById( panelId );
		if ( ! panel ) {
			return;
		}
		var form = panel.querySelector( '.menucraft-form' );
		if ( ! form ) {
			return;
		}
		form.reset();
		form.setAttribute( 'data-menucraft-mode', 'edit' );
		form.setAttribute( 'data-menucraft-id', String( entity.id ) );

		setFieldValue( form, 'code', entity.code || '' );
		setFieldValue( form, 'name', entity.name || '' );
		setFieldValue( form, 'description', entity.description || '' );
		setFieldValue( form, 'description_short', entity.description_short || '' );
		setFieldValue( form, 'description_long', entity.description_long || '' );
		setFieldValue( form, 'color', entity.color || '#3858e9' );
		setFieldValue( form, 'sort_order', String( entity.sort_order || 0 ) );
		setFieldValue( form, 'price', entity.price === null || entity.price === undefined ? '' : String( entity.price ) );

		var activeBox = form.querySelector( '[name="is_active"]' );
		if ( activeBox ) {
			activeBox.checked = !! entity.is_active;
		}

		var defaultBox = form.querySelector( '[name="is_default"]' );
		if ( defaultBox ) {
			defaultBox.checked = !! entity.is_default;
		}

		var picker = form.querySelector( '[data-menucraft-media-picker]' );
		if ( picker ) {
			setMediaByUrl( picker, entity.media_id, entity.media_url );
		}

		// Chip selections.
		Array.prototype.forEach.call(
			form.querySelectorAll( '[data-menucraft-chips]' ),
			function ( container ) {
				var name = container.getAttribute( 'data-menucraft-chips-name' );
				var selected = ( entity[ name ] || [] ).map( function ( n ) { return parseInt( n, 10 ); } );
				renderChips( container, selected );
			}
		);

		// Select-box selections — same data source as chips, different UI.
		Array.prototype.forEach.call(
			form.querySelectorAll( '[data-menucraft-select]' ),
			function ( container ) {
				var name = container.getAttribute( 'data-menucraft-select-name' );
				var ids  = ( entity[ name ] || [] ).map( function ( n ) { return parseInt( n, 10 ); } );
				setSelectSelection( container, ids );
			}
		);

		// Item variants → in-memory state. The DB id is kept so the save
		// path can echo it back and the backend performs UPDATE, not
		// re-insert (which would break offer_items references).
		if ( form.getAttribute( 'data-menucraft-endpoint' ) === 'items' ) {
			itemFormState.variants = ( entity.variants || [] ).map( function ( v ) {
				return {
					id:         v.id || 0,
					label:      v.label || '',
					price:      typeof v.price === 'number' ? v.price : parseFloat( v.price ) || 0,
					sort_order: v.sort_order || 0,
				};
			} );
			renderVariantsSummary();
			renderVariantsList();
		}

		// Offer line items → in-memory state; datetime-local wants
		// "Y-m-dTH:i", DB gives "Y-m-d H:i:s".
		if ( form.getAttribute( 'data-menucraft-endpoint' ) === 'offers' ) {
			offerFormState.items = ( entity.items || [] ).map( function ( line, idx ) {
				return {
					id:         line.id || 0,
					item_id:    parseInt( line.item_id, 10 ) || 0,
					variant_id: line.variant_id ? parseInt( line.variant_id, 10 ) : null,
					quantity:   parseInt( line.quantity, 10 ) || 1,
					sort_order: line.sort_order || idx,
				};
			} );
			setFieldValue( form, 'valid_from', mysqlToDatetimeLocal( entity.valid_from || '' ) );
			setFieldValue( form, 'valid_until', mysqlToDatetimeLocal( entity.valid_until || '' ) );
			setFieldValue( form, 'conditions_text', entity.conditions_text || '' );
			renderOfferItemsSummary();
			renderOfferItemsChips();
			renderOfferItemsList();
		}

		var title = panel.querySelector( '[data-menucraft-title-edit]' );
		if ( title ) {
			title.textContent = title.getAttribute( 'data-menucraft-title-edit' );
		}

		var submit = form.querySelector( '[data-menucraft-submit]' );
		if ( submit ) {
			submit.textContent = submit.getAttribute( 'data-menucraft-label-edit' ) || submit.textContent;
		}

		openPanel( panelId );
	}

	function setFieldValue( form, name, value ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		if ( el ) {
			el.value = value;
		}
	}

	// =========================================== Lists (multi-resource) ==

	var listConfigs = {
		categories: { buildRow: buildTermRow,     colspan: 7 },
		tags:       { buildRow: buildTermRow,     colspan: 7 },
		allergens:  { buildRow: buildAllergenRow, colspan: 6 },
		items:      { buildRow: buildItemRow,     colspan: 7 },
		offers:     { buildRow: buildOfferRow,    colspan: 8 },
	};

	function initLists() {
		var tables = document.querySelectorAll( '[data-menucraft-list]' );
		Array.prototype.forEach.call( tables, function ( table ) {
			var resource = table.getAttribute( 'data-menucraft-list' );
			var body     = table.querySelector( '[data-menucraft-list-body]' );
			if ( ! resource || ! body ) {
				return;
			}
			var config = listConfigs[ resource ] || { buildRow: buildTermRow, colspan: 7 };
			var selectable = table.hasAttribute( 'data-menucraft-selectable' );
			listStates[ resource ] = {
				resource:      resource,
				table:         table,
				body:          body,
				cache:         [],
				panelId:       table.getAttribute( 'data-menucraft-panel' ) || '',
				deleteModalId: table.getAttribute( 'data-menucraft-modal-delete' ) || '',
				buildRow:      config.buildRow,
				colspan:       config.colspan + ( selectable ? 1 : 0 ),
				selectable:    selectable,
			};
			if ( selectable ) {
				selections[ resource ] = {};
			}
			fetchList( listStates[ resource ] );
		} );

		// If the items screen is present, prefetch relation resources so
		// chips are ready by the time the item panel is opened.
		if ( listStates.items ) {
			ensureResourceLoaded( 'categories' );
			ensureResourceLoaded( 'tags' );
			ensureResourceLoaded( 'allergens' );
		}

		// Offers screen needs the items cache for the picker chips.
		if ( listStates.offers ) {
			ensureResourceLoaded( 'items' );
		}

		initFilters();
	}

	// =========================================================== Filters ==

	function initFilters() {
		var containers = document.querySelectorAll( '[data-menucraft-filters]' );
		Array.prototype.forEach.call( containers, function ( container ) {
			var resource = container.getAttribute( 'data-menucraft-filters' );
			if ( ! resource ) return;

			filterState[ resource ] = emptyFilterState();

			// Render filter chip containers (empty selection initially).
			Array.prototype.forEach.call(
				container.querySelectorAll( '[data-menucraft-chips]' ),
				function ( c ) { renderChips( c, [] ); }
			);
		} );
	}

	document.addEventListener( 'input', function ( event ) {
		var input = event.target.closest( '[data-menucraft-filter]' );
		if ( ! input ) return;
		var container = input.closest( '[data-menucraft-filters]' );
		if ( ! container ) return;
		refreshFilterFor( container.getAttribute( 'data-menucraft-filters' ), container );
	} );

	document.addEventListener( 'change', function ( event ) {
		var input = event.target.closest( '[data-menucraft-filter]' );
		if ( ! input ) return;
		var container = input.closest( '[data-menucraft-filters]' );
		if ( ! container ) return;
		refreshFilterFor( container.getAttribute( 'data-menucraft-filters' ), container );
	} );

	document.addEventListener( 'click', function ( event ) {
		var resetBtn = event.target.closest( '[data-menucraft-filters-reset]' );
		if ( resetBtn ) {
			event.preventDefault();
			event.stopPropagation();
			var container = resetBtn.closest( '[data-menucraft-filters]' );
			if ( ! container ) return;
			resetFilters( container );
			return;
		}

		var toggle = event.target.closest( '[data-menucraft-filters-toggle]' );
		if ( toggle ) {
			event.preventDefault();
			toggleFiltersPanel( toggle );
			return;
		}

		// Filter-chip clicks are handled by the global chip toggle; we
		// need to re-apply the filter afterwards. RAF lets the toggle
		// class settle before we read state.
		var chip = event.target.closest( '.menucraft-chip' );
		if ( chip ) {
			var chipsContainer = chip.closest( '[data-menucraft-chips]' );
			if ( ! chipsContainer ) return;
			var filterContainer = chipsContainer.closest( '[data-menucraft-filters]' );
			if ( filterContainer ) {
				requestAnimationFrame( function () {
					refreshFilterFor( filterContainer.getAttribute( 'data-menucraft-filters' ), filterContainer );
				} );
			}
		}
	} );

	function toggleFiltersPanel( header ) {
		var container = header.closest( '[data-menucraft-filters]' );
		if ( ! container ) return;
		var collapsed = container.classList.toggle( 'menucraft-filters-collapsed' );
		header.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
	}

	// Space / Enter on the header behaves like a click.
	document.addEventListener( 'keydown', function ( event ) {
		if ( event.key !== 'Enter' && event.key !== ' ' ) {
			return;
		}
		var toggle = event.target.closest( '[data-menucraft-filters-toggle]' );
		if ( ! toggle ) return;
		if ( event.target.closest( '[data-menucraft-filters-reset]' ) ) return;
		event.preventDefault();
		toggleFiltersPanel( toggle );
	} );

	function refreshFilterFor( resource, container ) {
		var f = collectFilterState( container );
		filterState[ resource ] = f;
		updateFilterCount( container, f );
		var state = listStates[ resource ];
		if ( state ) {
			renderTable( state );
		}
	}

	function collectFilterState( container ) {
		var s = emptyFilterState();

		var inputs = container.querySelectorAll( '[data-menucraft-filter]' );
		Array.prototype.forEach.call( inputs, function ( el ) {
			var key = el.getAttribute( 'data-menucraft-filter' );
			var raw = ( el.value || '' ).toString().trim();
			if ( key === 'price_min' || key === 'price_max' ) {
				s[ key ] = raw === '' ? null : parseFloat( raw );
				if ( isNaN( s[ key ] ) ) s[ key ] = null;
			} else {
				s[ key ] = raw;
			}
		} );

		var chips = container.querySelectorAll( '[data-menucraft-chips]' );
		Array.prototype.forEach.call( chips, function ( c ) {
			var resource = c.getAttribute( 'data-menucraft-chips' );
			var ids = Array.prototype.map.call(
				c.querySelectorAll( '.menucraft-chip.menucraft-chip-selected' ),
				function ( chip ) { return parseInt( chip.getAttribute( 'data-id' ), 10 ); }
			).filter( function ( n ) { return ! isNaN( n ); } );

			if ( resource === 'categories' ) s.category_ids = ids;
			else if ( resource === 'tags' ) s.tag_ids = ids;
			else if ( resource === 'allergens' ) s.allergen_ids = ids;
		} );

		return s;
	}

	function updateFilterCount( container, f ) {
		var badge = container.querySelector( '[data-menucraft-filters-count]' );
		if ( ! badge ) return;
		var resource = container.getAttribute( 'data-menucraft-filters' );
		var n = resource === 'offers' ? countActiveOfferFilters( f ) : countActiveFilters( f );
		if ( n === 0 ) {
			badge.hidden = true;
			badge.textContent = '';
			return;
		}
		badge.hidden = false;
		var template = i18n.filtersActive || '%d filter(s) active';
		badge.textContent = template.replace( '%d', String( n ) );
	}

	function resetFilters( container ) {
		var resource = container.getAttribute( 'data-menucraft-filters' );

		Array.prototype.forEach.call(
			container.querySelectorAll( '[data-menucraft-filter]' ),
			function ( el ) { el.value = ''; }
		);
		Array.prototype.forEach.call(
			container.querySelectorAll( '[data-menucraft-chips]' ),
			function ( c ) {
				Array.prototype.forEach.call(
					c.querySelectorAll( '.menucraft-chip.menucraft-chip-selected' ),
					function ( chip ) { chip.classList.remove( 'menucraft-chip-selected' ); }
				);
			}
		);

		filterState[ resource ] = emptyFilterState();
		updateFilterCount( container, filterState[ resource ] );

		var state = listStates[ resource ];
		if ( state ) {
			renderTable( state );
		}
	}

	function ensureResourceLoaded( resource ) {
		if ( listStates[ resource ] ) {
			return;
		}
		listStates[ resource ] = {
			resource: resource,
			table:    null,
			body:     null,
			cache:    [],
			panelId:  '',
			deleteModalId: '',
			buildRow: function () { return null; },
			colspan:  0,
		};
		rest( resource )
			.then( function ( rows ) {
				listStates[ resource ].cache = Array.isArray( rows ) ? rows : [];
				refreshChipsFor( resource );
			} )
			.catch( function () {
				// Silent — chips will just render empty state.
			} );
	}

	function fetchList( state ) {
		rest( state.resource )
			.then( function ( rows ) {
				state.cache = Array.isArray( rows ) ? rows : [];
				renderTable( state );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.listError || 'Could not load list.', 'error' );
				renderStatus( state, i18n.listError || 'Could not load list.' );
			} );
	}

	function renderTable( state ) {
		state.body.innerHTML = '';

		var rows = state.cache;
		var filters = filterState[ state.resource ];
		if ( filters ) {
			if ( state.resource === 'offers' ) {
				if ( hasActiveOfferFilters( filters ) ) {
					rows = rows.filter( function ( row ) { return matchesOfferFilters( row, filters ); } );
				}
			} else if ( hasActiveFilters( filters ) ) {
				rows = rows.filter( function ( row ) { return matchesFilters( row, filters ); } );
			}
		}

		if ( ! state.cache.length ) {
			state.body.appendChild( buildStatusRow( i18n.empty || 'No entries yet.', state.colspan ) );
			updateSelectAllCheckbox( state );
			return;
		}

		if ( ! rows.length ) {
			state.body.appendChild( buildStatusRow( i18n.noMatches || 'No matches.', state.colspan ) );
			updateSelectAllCheckbox( state );
			return;
		}

		rows.forEach( function ( row ) {
			state.body.appendChild( buildRowFor( state, row ) );
		} );
		updateSelectAllCheckbox( state );
	}

	function hasActiveFilters( f ) {
		return !! (
			f.search ||
			( f.category_ids && f.category_ids.length ) ||
			( f.tag_ids && f.tag_ids.length ) ||
			( f.allergen_ids && f.allergen_ids.length ) ||
			f.status ||
			f.price_min !== null ||
			f.price_max !== null ||
			f.image
		);
	}

	function countActiveFilters( f ) {
		var n = 0;
		if ( f.search ) n++;
		if ( f.category_ids && f.category_ids.length ) n++;
		if ( f.tag_ids && f.tag_ids.length ) n++;
		if ( f.allergen_ids && f.allergen_ids.length ) n++;
		if ( f.status ) n++;
		if ( f.price_min !== null || f.price_max !== null ) n++;
		if ( f.image ) n++;
		return n;
	}

	function matchesFilters( item, f ) {
		if ( f.search ) {
			var q = f.search.toLowerCase();
			var hay = ( ( item.name || '' ) + ' ' + ( item.description_short || '' ) + ' ' + ( item.description_long || '' ) ).toLowerCase();
			if ( hay.indexOf( q ) === -1 ) {
				return false;
			}
		}
		if ( f.category_ids && f.category_ids.length ) {
			if ( ! f.category_ids.some( function ( id ) { return ( item.category_ids || [] ).indexOf( id ) > -1; } ) ) {
				return false;
			}
		}
		if ( f.tag_ids && f.tag_ids.length ) {
			if ( ! f.tag_ids.some( function ( id ) { return ( item.tag_ids || [] ).indexOf( id ) > -1; } ) ) {
				return false;
			}
		}
		if ( f.allergen_ids && f.allergen_ids.length ) {
			if ( ! f.allergen_ids.some( function ( id ) { return ( item.allergen_ids || [] ).indexOf( id ) > -1; } ) ) {
				return false;
			}
		}
		if ( f.status === 'active' && ! item.is_active ) return false;
		if ( f.status === 'inactive' && item.is_active ) return false;

		if ( f.price_min !== null || f.price_max !== null ) {
			var price = itemEffectivePrice( item );
			if ( price === null ) return false;
			if ( f.price_min !== null && price < f.price_min ) return false;
			if ( f.price_max !== null && price > f.price_max ) return false;
		}

		if ( f.image === 'with' && ! item.media_id ) return false;
		if ( f.image === 'without' && item.media_id ) return false;

		return true;
	}

	function itemEffectivePrice( item ) {
		var variants = ( item.variants || [] ).filter( function ( v ) {
			var p = typeof v.price === 'number' ? v.price : parseFloat( v.price );
			return ! isNaN( p );
		} );
		if ( variants.length ) {
			return variants.reduce( function ( min, v ) {
				var p = typeof v.price === 'number' ? v.price : parseFloat( v.price );
				return p < min ? p : min;
			}, Infinity );
		}
		if ( item.price !== null && item.price !== undefined && item.price !== '' ) {
			var base = typeof item.price === 'number' ? item.price : parseFloat( item.price );
			return isNaN( base ) ? null : base;
		}
		return null;
	}

	/**
	 * Wrap the resource-specific row with a selection checkbox cell when
	 * the table opted in via data-menucraft-selectable.
	 */
	function buildRowFor( state, entity ) {
		var tr = state.buildRow( entity );
		if ( state.selectable ) {
			var td = document.createElement( 'td' );
			td.className = 'menucraft-col-select';
			var cb = document.createElement( 'input' );
			cb.type = 'checkbox';
			cb.setAttribute( 'data-menucraft-select-row', String( entity.id ) );
			cb.setAttribute( 'aria-label', 'Select item ' + entity.id );
			cb.checked = !! ( selections[ state.resource ] && selections[ state.resource ][ entity.id ] );
			td.appendChild( cb );
			tr.insertBefore( td, tr.firstChild );
			if ( cb.checked ) {
				tr.classList.add( 'menucraft-row-selected' );
			}
		}
		return tr;
	}

	function renderStatus( state, text ) {
		state.body.innerHTML = '';
		state.body.appendChild( buildStatusRow( text, state.colspan ) );
	}

	function buildStatusRow( text, colspan ) {
		var tr = document.createElement( 'tr' );
		tr.className = 'menucraft-row-status';
		var td = document.createElement( 'td' );
		td.colSpan     = colspan || 7;
		td.textContent = text;
		tr.appendChild( td );
		return tr;
	}

	function buildTermRow( entity ) {
		var tr = document.createElement( 'tr' );
		tr.setAttribute( 'data-menucraft-row-id', String( entity.id ) );

		tr.appendChild( buildThumbCell( entity ) );

		var tdName = document.createElement( 'td' );
		tdName.className = 'menucraft-col-name';
		var nameStrong   = document.createElement( 'strong' );
		nameStrong.textContent = entity.name;
		tdName.appendChild( nameStrong );
		if ( entity.is_default ) {
			tdName.appendChild( document.createTextNode( ' ' ) );
			var defBadge = document.createElement( 'span' );
			defBadge.className   = 'menucraft-default-badge';
			defBadge.title       = i18n.defaultCategoryTitle || 'Pre-selected in the frontend filter';
			defBadge.textContent = i18n.defaultCategory || 'Default';
			tdName.appendChild( defBadge );
		}
		if ( entity.slug ) {
			var slugSmall = document.createElement( 'div' );
			slugSmall.className   = 'menucraft-cell-sub';
			slugSmall.textContent = entity.slug;
			tdName.appendChild( slugSmall );
		}
		tr.appendChild( tdName );

		var tdColor = document.createElement( 'td' );
		tdColor.className = 'menucraft-col-color';
		if ( entity.color ) {
			var swatch = document.createElement( 'span' );
			swatch.className          = 'menucraft-color-swatch';
			swatch.style.background   = entity.color;
			swatch.title              = entity.color;
			tdColor.appendChild( swatch );
		} else {
			tdColor.textContent = '—';
		}
		tr.appendChild( tdColor );

		var tdDesc = document.createElement( 'td' );
		tdDesc.className   = 'menucraft-col-desc';
		tdDesc.textContent = truncate( entity.description || '', 15 );
		if ( entity.description ) {
			tdDesc.title = entity.description;
		}
		tr.appendChild( tdDesc );

		tr.appendChild( buildActiveCell( entity ) );
		tr.appendChild( buildDatesCellWrapped( entity ) );
		tr.appendChild( buildActionsCellWrapped( entity ) );

		return tr;
	}

	function buildAllergenRow( entity ) {
		var tr = document.createElement( 'tr' );
		tr.setAttribute( 'data-menucraft-row-id', String( entity.id ) );

		var tdCode = document.createElement( 'td' );
		tdCode.className = 'menucraft-col-code';
		var codeBadge    = document.createElement( 'span' );
		codeBadge.className   = 'menucraft-code-badge';
		codeBadge.textContent = entity.code || '—';
		tdCode.appendChild( codeBadge );
		tr.appendChild( tdCode );

		var tdName = document.createElement( 'td' );
		tdName.className = 'menucraft-col-name';
		var nameStrong   = document.createElement( 'strong' );
		nameStrong.textContent = entity.name;
		tdName.appendChild( nameStrong );
		tr.appendChild( tdName );

		var tdDesc = document.createElement( 'td' );
		tdDesc.className   = 'menucraft-col-desc';
		tdDesc.textContent = truncate( entity.description || '', 15 );
		if ( entity.description ) {
			tdDesc.title = entity.description;
		}
		tr.appendChild( tdDesc );

		tr.appendChild( buildActiveCell( entity ) );
		tr.appendChild( buildDatesCellWrapped( entity ) );
		tr.appendChild( buildActionsCellWrapped( entity ) );

		return tr;
	}

	function buildItemRow( entity ) {
		var tr = document.createElement( 'tr' );
		tr.setAttribute( 'data-menucraft-row-id', String( entity.id ) );

		tr.appendChild( buildThumbCell( entity ) );

		var tdName = document.createElement( 'td' );
		tdName.className = 'menucraft-col-name';
		var nameStrong   = document.createElement( 'strong' );
		nameStrong.textContent = entity.name;
		tdName.appendChild( nameStrong );
		if ( entity.description_short ) {
			var sub = document.createElement( 'div' );
			sub.className   = 'menucraft-cell-sub';
			sub.textContent = truncate( entity.description_short, 60 );
			tdName.appendChild( sub );
		}
		tr.appendChild( tdName );

		var tdCats = document.createElement( 'td' );
		tdCats.className = 'menucraft-col-categories';
		tdCats.appendChild( buildRelatedNames( entity.category_ids, 'categories' ) );
		tr.appendChild( tdCats );

		var tdPrice = document.createElement( 'td' );
		tdPrice.className = 'menucraft-col-price';
		tdPrice.appendChild( buildPriceCell( entity ) );
		tr.appendChild( tdPrice );

		tr.appendChild( buildActiveCell( entity ) );
		tr.appendChild( buildDatesCellWrapped( entity ) );
		tr.appendChild( buildActionsCellWrapped( entity ) );

		return tr;
	}

	function buildThumbCell( entity ) {
		var td = document.createElement( 'td' );
		td.className = 'menucraft-col-thumb';
		if ( entity.media_url ) {
			var img = document.createElement( 'img' );
			img.src = entity.media_url;
			img.alt = '';
			img.className = 'menucraft-thumb';
			td.appendChild( img );
		} else {
			var placeholder = document.createElement( 'span' );
			placeholder.className = 'menucraft-thumb menucraft-thumb-empty';
			placeholder.setAttribute( 'aria-hidden', 'true' );
			td.appendChild( placeholder );
		}
		return td;
	}

	function buildActiveCell( entity ) {
		var td = document.createElement( 'td' );
		td.className = 'menucraft-col-active';
		td.appendChild( buildActiveToggle( entity ) );
		return td;
	}

	function buildDatesCellWrapped( entity ) {
		var td = document.createElement( 'td' );
		td.className = 'menucraft-col-dates';
		td.appendChild( buildDatesCell( entity ) );
		return td;
	}

	function buildActionsCellWrapped( entity ) {
		var td = document.createElement( 'td' );
		td.className = 'menucraft-col-actions';
		td.appendChild( buildActionsCell( entity ) );
		return td;
	}

	function buildPriceCell( entity ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'menucraft-cell-price';

		var variants = ( entity.variants || [] ).filter( function ( v ) {
			return v && ( typeof v.price === 'number' ? true : ! isNaN( parseFloat( v.price ) ) );
		} );

		if ( variants.length ) {
			var min = variants.reduce( function ( acc, v ) {
				var p = typeof v.price === 'number' ? v.price : parseFloat( v.price );
				return p < acc ? p : acc;
			}, Infinity );
			var fromSpan = document.createElement( 'span' );
			fromSpan.className = 'menucraft-cell-sub';
			fromSpan.textContent = i18n.from || 'from';
			wrap.appendChild( fromSpan );
			wrap.appendChild( document.createTextNode( ' ' ) );
			var strong = document.createElement( 'strong' );
			strong.textContent = formatPrice( min );
			wrap.appendChild( strong );
			return wrap;
		}

		if ( entity.price !== null && entity.price !== undefined && entity.price !== '' ) {
			var strong2 = document.createElement( 'strong' );
			strong2.textContent = formatPrice( entity.price );
			wrap.appendChild( strong2 );
			return wrap;
		}

		var muted = document.createElement( 'span' );
		muted.className   = 'menucraft-cell-sub';
		muted.textContent = i18n.noPrice || 'no price';
		wrap.appendChild( muted );
		return wrap;
	}

	function buildRelatedNames( ids, resource ) {
		var wrap  = document.createElement( 'div' );
		wrap.className = 'menucraft-related-list';
		var state = listStates[ resource ];
		if ( ! ids || ! ids.length || ! state || ! state.cache.length ) {
			wrap.textContent = '—';
			return wrap;
		}
		var byId = {};
		state.cache.forEach( function ( r ) { byId[ r.id ] = r; } );
		ids.forEach( function ( id ) {
			var row = byId[ id ];
			if ( ! row ) {
				return;
			}
			var pill = document.createElement( 'span' );
			pill.className = 'menucraft-related-pill';
			if ( row.color ) {
				pill.style.borderColor = row.color;
			}
			pill.textContent = row.name;
			wrap.appendChild( pill );
		} );
		if ( ! wrap.children.length ) {
			wrap.textContent = '—';
		}
		return wrap;
	}

	function buildActiveToggle( entity ) {
		var btn = document.createElement( 'button' );
		btn.type      = 'button';
		btn.className = 'menucraft-toggle' + ( entity.is_active ? ' menucraft-toggle-on' : ' menucraft-toggle-off' );
		btn.setAttribute( 'data-menucraft-toggle-active', String( entity.id ) );
		btn.setAttribute( 'aria-pressed', entity.is_active ? 'true' : 'false' );
		btn.textContent = entity.is_active ? ( i18n.active || 'Active' ) : ( i18n.inactive || 'Inactive' );
		return btn;
	}

	function buildDatesCell( entity ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'menucraft-cell-dates';

		var created = document.createElement( 'div' );
		created.textContent = entity.created_at || '';
		wrap.appendChild( created );

		if ( entity.updated_at && entity.updated_at !== entity.created_at ) {
			var updated = document.createElement( 'div' );
			updated.className   = 'menucraft-cell-sub';
			updated.textContent = '↻ ' + entity.updated_at;
			wrap.appendChild( updated );
		}

		return wrap;
	}

	function buildActionsCell( entity ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'menucraft-cell-actions';

		var edit = document.createElement( 'button' );
		edit.type      = 'button';
		edit.className = 'button-link menucraft-btn-icon menucraft-btn-edit';
		edit.title     = i18n.edit || 'Edit';
		edit.setAttribute( 'aria-label', i18n.edit || 'Edit' );
		edit.setAttribute( 'data-menucraft-edit', String( entity.id ) );
		edit.innerHTML = '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';
		wrap.appendChild( edit );

		var del = document.createElement( 'button' );
		del.type      = 'button';
		del.className = 'button-link menucraft-btn-icon menucraft-btn-delete';
		del.title     = i18n.delete || 'Delete';
		del.setAttribute( 'aria-label', i18n.delete || 'Delete' );
		del.setAttribute( 'data-menucraft-delete', String( entity.id ) );
		del.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
		wrap.appendChild( del );

		return wrap;
	}

	function truncate( text, max ) {
		if ( ! text ) {
			return '—';
		}
		if ( text.length <= max ) {
			return text;
		}
		return text.substring( 0, max ) + '…';
	}

	function formatPrice( value ) {
		if ( value === null || value === '' || value === undefined ) {
			return '—';
		}
		var num = typeof value === 'number' ? value : parseFloat( value );
		if ( isNaN( num ) ) {
			return '—';
		}
		return num.toFixed( 2 ) + ' ' + ( settings.currency || '' );
	}

	// -------- Chips (relation selector) -------- //

	function renderChips( container, selectedIds ) {
		var resource = container.getAttribute( 'data-menucraft-chips' );
		var emptyMsg = container.getAttribute( 'data-menucraft-chips-empty' ) || '';
		var state    = listStates[ resource ];

		container.innerHTML = '';

		if ( ! state || ! state.cache.length ) {
			var empty = document.createElement( 'span' );
			empty.className   = 'menucraft-chips-empty';
			empty.textContent = emptyMsg;
			container.appendChild( empty );
			return;
		}

		var selectedSet = {};
		( selectedIds || [] ).forEach( function ( id ) { selectedSet[ id ] = true; } );

		state.cache.forEach( function ( row ) {
			var chip = document.createElement( 'button' );
			chip.type      = 'button';
			chip.className = 'menucraft-chip';
			if ( selectedSet[ row.id ] ) {
				chip.classList.add( 'menucraft-chip-selected' );
			}
			chip.setAttribute( 'data-id', String( row.id ) );
			if ( row.color ) {
				chip.style.setProperty( '--menucraft-chip-color', row.color );
			}
			if ( row.code ) {
				var code = document.createElement( 'span' );
				code.className   = 'menucraft-chip-code';
				code.textContent = row.code;
				chip.appendChild( code );
			}
			var label = document.createElement( 'span' );
			label.className   = 'menucraft-chip-label';
			label.textContent = row.name;
			chip.appendChild( label );
			container.appendChild( chip );
		} );
	}

	function refreshChipsFor( resource ) {
		var containers = document.querySelectorAll( '[data-menucraft-chips="' + resource + '"]' );
		Array.prototype.forEach.call( containers, function ( container ) {
			// Preserve any current selection.
			var selected = Array.prototype.map.call(
				container.querySelectorAll( '.menucraft-chip.menucraft-chip-selected' ),
				function ( c ) { return parseInt( c.getAttribute( 'data-id' ), 10 ); }
			);
			renderChips( container, selected );
		} );

		// Offer-items picker uses its own chip container backed by the
		// items cache — re-render it whenever items load or change.
		if ( resource === 'items' ) {
			renderOfferItemsChips();
			renderOfferItemsList();
			// The offers table shows a pill per line item resolved via the
			// items cache. When offers render before items are fetched,
			// every row shows "—"; re-render once items are ready.
			if ( listStates.offers && listStates.offers.body ) {
				renderTable( listStates.offers );
			}
		}

		// Select-boxes (item edit panel) draw from the same cache — refresh
		// their dropdowns so freshly-created categories/tags/allergens show
		// up without a page reload.
		refreshSelectsFor( resource );
	}

	document.addEventListener( 'click', function ( event ) {
		var chip = event.target.closest( '.menucraft-chip' );
		if ( ! chip ) {
			return;
		}
		if ( ! chip.closest( '[data-menucraft-chips]' ) ) {
			return;
		}
		event.preventDefault();
		chip.classList.toggle( 'menucraft-chip-selected' );
	} );

	// -------- Row-level event delegation ---------

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '[data-menucraft-toggle-active]' );
		if ( toggle ) {
			event.preventDefault();
			handleToggleActive( toggle );
			return;
		}

		var editBtn = event.target.closest( '[data-menucraft-edit]' );
		if ( editBtn ) {
			event.preventDefault();
			handleEditClick( editBtn );
			return;
		}

		var delBtn = event.target.closest( '[data-menucraft-delete]' );
		if ( delBtn ) {
			event.preventDefault();
			handleDeleteClick( delBtn );
			return;
		}

		var confirm = event.target.closest( '[data-menucraft-modal-confirm-delete]' );
		if ( confirm ) {
			event.preventDefault();
			handleDeleteConfirm( confirm );
		}
	} );

	function stateForElement( el ) {
		var table = el.closest( '[data-menucraft-list]' );
		if ( ! table ) {
			return null;
		}
		return listStates[ table.getAttribute( 'data-menucraft-list' ) ] || null;
	}

	function findEntity( state, id ) {
		for ( var i = 0; i < state.cache.length; i++ ) {
			if ( state.cache[ i ].id === id ) {
				return state.cache[ i ];
			}
		}
		return null;
	}

	function handleToggleActive( button ) {
		var state = stateForElement( button );
		if ( ! state ) {
			return;
		}
		var id     = parseInt( button.getAttribute( 'data-menucraft-toggle-active' ), 10 );
		var entity = findEntity( state, id );
		if ( ! entity ) {
			return;
		}
		var next = ! entity.is_active;
		button.disabled = true;

		rest( state.resource + '/' + id, { method: 'PUT', body: { is_active: next } } )
			.then( function ( updated ) {
				updateCache( state, updated );
				replaceRow( state, updated );
				showToast( i18n.updateSuccess || 'Updated.', 'success' );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.saveError || 'Save failed.', 'error' );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	function handleEditClick( button ) {
		var state = stateForElement( button );
		if ( ! state ) {
			return;
		}
		var id     = parseInt( button.getAttribute( 'data-menucraft-edit' ), 10 );
		var entity = findEntity( state, id );
		if ( entity ) {
			openPanelInEditMode( state.panelId, entity );
			return;
		}
		rest( state.resource + '/' + id )
			.then( function ( fetched ) {
				openPanelInEditMode( state.panelId, fetched );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.listError, 'error' );
			} );
	}

	function handleDeleteClick( button ) {
		var state = stateForElement( button );
		if ( ! state || ! state.deleteModalId ) {
			return;
		}
		var id     = parseInt( button.getAttribute( 'data-menucraft-delete' ), 10 );
		var entity = findEntity( state, id );
		if ( ! entity ) {
			return;
		}
		deleteContext = { id: id, name: entity.name, resource: state.resource };
		var modal = document.getElementById( state.deleteModalId );
		if ( ! modal ) {
			return;
		}
		var nameTarget = modal.querySelector( '[data-menucraft-modal-target-name]' );
		if ( nameTarget ) {
			nameTarget.textContent = '"' + entity.name + '"';
		}
		openModal( state.deleteModalId );
	}

	function handleDeleteConfirm( button ) {
		if ( ! deleteContext ) {
			return;
		}
		var ctx   = deleteContext;
		var state = listStates[ ctx.resource ];
		button.disabled = true;

		rest( ctx.resource + '/' + ctx.id, { method: 'DELETE' } )
			.then( function () {
				if ( state ) {
					removeFromCache( state, ctx.id );
					removeRow( state, ctx.id );
				}
				showToast( i18n.deleteSuccess || 'Deleted.', 'success' );
				closeModal( button.closest( '.menucraft-modal' ) );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.deleteError || 'Delete failed.', 'error' );
			} )
			.then( function () {
				button.disabled = false;
				deleteContext   = null;
			} );
	}

	function updateCache( state, entity ) {
		for ( var i = 0; i < state.cache.length; i++ ) {
			if ( state.cache[ i ].id === entity.id ) {
				state.cache[ i ] = entity;
				return;
			}
		}
		state.cache.push( entity );
	}

	function removeFromCache( state, id ) {
		state.cache = state.cache.filter( function ( c ) { return c.id !== id; } );
	}

	function appendRow( state, entity ) {
		updateCache( state, entity );
		renderTable( state );
		refreshChipsFor( state.resource );
	}

	function replaceRow( state, entity ) {
		updateCache( state, entity );
		renderTable( state );
		refreshChipsFor( state.resource );
	}

	function removeRow( state, id ) {
		removeFromCache( state, id );
		if ( state.selectable && selections[ state.resource ] ) {
			delete selections[ state.resource ][ id ];
			refreshBulkToolbar();
		}
		renderTable( state );
		refreshChipsFor( state.resource );
	}

	// =============================================== Selection + bulk ==

	function selectableStates() {
		var out = [];
		for ( var key in listStates ) {
			if ( listStates.hasOwnProperty( key ) && listStates[ key ].selectable ) {
				out.push( listStates[ key ] );
			}
		}
		return out;
	}

	function selectionCountFor( resource ) {
		var map = selections[ resource ];
		if ( ! map ) return 0;
		var n = 0;
		for ( var k in map ) { if ( map.hasOwnProperty( k ) && map[ k ] ) n++; }
		return n;
	}

	function selectedIdsFor( resource ) {
		var map = selections[ resource ];
		if ( ! map ) return [];
		var ids = [];
		for ( var k in map ) { if ( map.hasOwnProperty( k ) && map[ k ] ) ids.push( parseInt( k, 10 ) ); }
		return ids;
	}

	function updateSelectAllCheckbox( state ) {
		if ( ! state || ! state.selectable ) {
			return;
		}
		var master = state.table.querySelector( '[data-menucraft-select-all]' );
		if ( ! master ) return;
		var boxes = state.body.querySelectorAll( '[data-menucraft-select-row]' );
		if ( ! boxes.length ) {
			master.checked       = false;
			master.indeterminate = false;
			return;
		}
		var all  = true;
		var some = false;
		Array.prototype.forEach.call( boxes, function ( b ) {
			if ( b.checked ) some = true;
			else all = false;
		} );
		master.checked       = all;
		master.indeterminate = ! all && some;
	}

	function refreshBulkToolbar() {
		// Current impl only wires items → single toolbar per plugin.
		var state = listStates.items;
		if ( ! state ) return;
		var toolbar = document.querySelector( '[data-menucraft-bulk-toolbar]' );
		if ( ! toolbar ) return;
		var count = selectionCountFor( 'items' );
		var countEl = toolbar.querySelector( '[data-menucraft-bulk-count]' );
		if ( countEl ) countEl.textContent = String( count );
		toolbar.hidden = count === 0;
	}

	document.addEventListener( 'change', function ( event ) {
		var master = event.target.closest( '[data-menucraft-select-all]' );
		if ( master ) {
			var table = master.closest( '[data-menucraft-list]' );
			if ( ! table ) return;
			var resource = table.getAttribute( 'data-menucraft-list' );
			var state    = listStates[ resource ];
			if ( ! state ) return;
			var checked = master.checked;
			var boxes   = state.body.querySelectorAll( '[data-menucraft-select-row]' );
			Array.prototype.forEach.call( boxes, function ( b ) {
				b.checked = checked;
				var id    = parseInt( b.getAttribute( 'data-menucraft-select-row' ), 10 );
				if ( isNaN( id ) ) return;
				if ( checked ) {
					selections[ resource ][ id ] = true;
					b.closest( 'tr' ).classList.add( 'menucraft-row-selected' );
				} else {
					delete selections[ resource ][ id ];
					b.closest( 'tr' ).classList.remove( 'menucraft-row-selected' );
				}
			} );
			refreshBulkToolbar();
			return;
		}

		var row = event.target.closest( '[data-menucraft-select-row]' );
		if ( row ) {
			var table2 = row.closest( '[data-menucraft-list]' );
			if ( ! table2 ) return;
			var resource2 = table2.getAttribute( 'data-menucraft-list' );
			if ( ! selections[ resource2 ] ) return;
			var id = parseInt( row.getAttribute( 'data-menucraft-select-row' ), 10 );
			if ( isNaN( id ) ) return;
			if ( row.checked ) {
				selections[ resource2 ][ id ] = true;
				row.closest( 'tr' ).classList.add( 'menucraft-row-selected' );
			} else {
				delete selections[ resource2 ][ id ];
				row.closest( 'tr' ).classList.remove( 'menucraft-row-selected' );
			}
			refreshBulkToolbar();
			updateSelectAllCheckbox( listStates[ resource2 ] );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var clearBtn = event.target.closest( '[data-menucraft-bulk-clear]' );
		if ( clearBtn ) {
			event.preventDefault();
			selectableStates().forEach( function ( state ) {
				selections[ state.resource ] = {};
				var boxes = state.body.querySelectorAll( '[data-menucraft-select-row]' );
				Array.prototype.forEach.call( boxes, function ( b ) {
					b.checked = false;
					b.closest( 'tr' ).classList.remove( 'menucraft-row-selected' );
				} );
				updateSelectAllCheckbox( state );
			} );
			refreshBulkToolbar();
			return;
		}

		var openBtn = event.target.closest( '[data-menucraft-bulk-open]' );
		if ( openBtn ) {
			event.preventDefault();
			openBulkEditPanel( openBtn.getAttribute( 'data-menucraft-bulk-open' ) );
		}
	} );

	function openBulkEditPanel( panelId ) {
		var count = selectionCountFor( 'items' );
		if ( count === 0 ) {
			showToast( i18n.bulkNoSelection || 'Select at least one item first.', 'error' );
			return;
		}

		var panel = document.getElementById( panelId );
		if ( ! panel ) return;
		var form  = panel.querySelector( '.menucraft-form' );
		if ( form ) {
			form.reset();
			Array.prototype.forEach.call(
				form.querySelectorAll( '[data-menucraft-chips]' ),
				function ( container ) { renderChips( container, [] ); }
			);
		}
		var badge = panel.querySelector( '[data-menucraft-bulk-panel-count]' );
		if ( badge ) badge.textContent = String( count );

		openPanel( panelId );
	}

	// -------- Bulk submit path (separate from single-item submit) --------

	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( ! form.hasAttribute || ! form.hasAttribute( 'data-menucraft-bulk-form' ) ) {
			return;
		}
		event.preventDefault();
		submitBulkForm( form );
	}, true );

	function submitBulkForm( form ) {
		var itemIds = selectedIdsFor( 'items' );
		if ( ! itemIds.length ) {
			showToast( i18n.bulkNoSelection || 'Select at least one item first.', 'error' );
			return;
		}

		var operations = collectBulkOperations( form );
		if ( ! Object.keys( operations ).length ) {
			showToast( i18n.bulkNoOps || 'Nothing to apply — pick at least one operation.', 'error' );
			return;
		}

		var panel      = form.closest( '.menucraft-offcanvas' );
		var saveButton = form.querySelector( '[data-menucraft-submit]' );
		setBusy( saveButton, true );

		rest( 'items/bulk-edit', {
			method: 'POST',
			body:   { item_ids: itemIds, operations: operations },
		} )
			.then( function ( response ) {
				var updated = ( response && response.updated ) || [];
				var state   = listStates.items;
				if ( state ) {
					updated.forEach( function ( entity ) { replaceRow( state, entity ); } );
				}

				var template = i18n.bulkApplied || 'Applied to %d item(s).';
				showToast( template.replace( '%d', String( updated.length ) ), 'success' );

				// Clear selection after a successful apply.
				if ( selections.items ) {
					selections.items = {};
					var boxes = state.body.querySelectorAll( '[data-menucraft-select-row]' );
					Array.prototype.forEach.call( boxes, function ( b ) {
						b.checked = false;
						b.closest( 'tr' ).classList.remove( 'menucraft-row-selected' );
					} );
					refreshBulkToolbar();
					updateSelectAllCheckbox( state );
				}

				closePanel( panel );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.saveError || 'Save failed.', 'error' );
			} )
			.then( function () {
				setBusy( saveButton, false );
			} );
	}

	function collectBulkOperations( form ) {
		var ops = {};

		var relationSpecs = [
			{ key: 'categories', mode: 'categories_mode', chips: 'categories' },
			{ key: 'tags',       mode: 'tags_mode',       chips: 'tags' },
			{ key: 'allergens',  mode: 'allergens_mode',  chips: 'allergens' },
		];
		relationSpecs.forEach( function ( spec ) {
			var modeEl = form.querySelector( '[name="' + spec.mode + '"]' );
			var mode   = modeEl ? modeEl.value : '';
			if ( ! mode ) return;
			var container = form.querySelector( '[data-menucraft-chips="' + spec.chips + '"]' );
			var ids       = container ? Array.prototype.map.call(
				container.querySelectorAll( '.menucraft-chip.menucraft-chip-selected' ),
				function ( chip ) { return parseInt( chip.getAttribute( 'data-id' ), 10 ); }
			).filter( function ( n ) { return ! isNaN( n ); } ) : [];
			ops[ spec.key ] = { mode: mode, ids: ids };
		} );

		var priceSpecs = [
			{ key: 'base_price',     mode: 'base_price_mode',     value: 'base_price_value' },
			{ key: 'variant_prices', mode: 'variant_prices_mode', value: 'variant_prices_value' },
		];
		priceSpecs.forEach( function ( spec ) {
			var modeEl = form.querySelector( '[name="' + spec.mode + '"]' );
			var mode   = modeEl ? modeEl.value : '';
			if ( ! mode ) return;
			var valueEl = form.querySelector( '[name="' + spec.value + '"]' );
			var value   = valueEl ? parseFloat( valueEl.value ) : 0;
			ops[ spec.key ] = { mode: mode, value: isNaN( value ) ? 0 : value };
		} );

		var activeEl = form.querySelector( '[name="is_active_mode"]' );
		if ( activeEl && activeEl.value !== '' ) {
			ops.is_active = activeEl.value === '1';
		}

		return ops;
	}

	// ======================================== Item variants (sub-panel) ==

	function renderVariantsSummary() {
		var counter = document.querySelector( '[data-menucraft-variants-count]' );
		if ( ! counter ) {
			return;
		}
		var n = itemFormState.variants.length;
		if ( n === 0 ) {
			counter.textContent = i18n.variantsNone || 'None';
		} else {
			var template = i18n.variantsCount || '%d variant(s)';
			counter.textContent = template.replace( '%d', String( n ) );
		}
	}

	function renderVariantsList() {
		var container = document.querySelector( '[data-menucraft-variants-list]' );
		if ( ! container ) {
			return;
		}
		container.innerHTML = '';

		itemFormState.variants.forEach( function ( variant, index ) {
			var row = document.createElement( 'div' );
			row.className = 'menucraft-variant-row';
			row.setAttribute( 'data-menucraft-variant-index', String( index ) );

			var labelField = document.createElement( 'div' );
			labelField.className = 'menucraft-variant-cell menucraft-variant-cell-label';
			var labelInput       = document.createElement( 'input' );
			labelInput.type        = 'text';
			labelInput.value       = variant.label || '';
			labelInput.placeholder = i18n.variantLabelHint || '';
			labelInput.setAttribute( 'data-menucraft-variant-field', 'label' );
			labelInput.setAttribute( 'aria-label', i18n.variantLabel || 'Label' );
			labelField.appendChild( labelInput );

			var priceField = document.createElement( 'div' );
			priceField.className = 'menucraft-variant-cell menucraft-variant-cell-price';
			var priceInput       = document.createElement( 'input' );
			priceInput.type  = 'number';
			priceInput.step  = '0.01';
			priceInput.min   = '0';
			priceInput.value = typeof variant.price === 'number' ? variant.price.toFixed( 2 ) : String( variant.price || 0 );
			priceInput.setAttribute( 'data-menucraft-variant-field', 'price' );
			priceInput.setAttribute( 'data-menucraft-price', '' );
			priceInput.setAttribute( 'aria-label', i18n.variantPrice || 'Price' );
			priceField.appendChild( wrapPriceInput( priceInput ) );

			var removeCell = document.createElement( 'div' );
			removeCell.className = 'menucraft-variant-cell menucraft-variant-cell-remove';
			var removeBtn        = document.createElement( 'button' );
			removeBtn.type      = 'button';
			removeBtn.className = 'button-link menucraft-btn-icon';
			removeBtn.title     = i18n.variantRemove || 'Remove';
			removeBtn.setAttribute( 'aria-label', i18n.variantRemove || 'Remove' );
			removeBtn.setAttribute( 'data-menucraft-variant-remove', '' );
			removeBtn.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
			removeCell.appendChild( removeBtn );

			row.appendChild( labelField );
			row.appendChild( priceField );
			row.appendChild( removeCell );
			container.appendChild( row );
		} );

		var empty = document.querySelector( '[data-menucraft-variants-empty]' );
		if ( empty ) {
			empty.hidden = itemFormState.variants.length > 0;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var addBtn = event.target.closest( '[data-menucraft-variant-add]' );
		if ( addBtn ) {
			event.preventDefault();
			itemFormState.variants.push( { label: '', price: 0, sort_order: itemFormState.variants.length } );
			renderVariantsList();
			renderVariantsSummary();
			// Focus new row's label field.
			var container = document.querySelector( '[data-menucraft-variants-list]' );
			if ( container ) {
				var rows = container.querySelectorAll( '.menucraft-variant-row' );
				var last = rows[ rows.length - 1 ];
				if ( last ) {
					var input = last.querySelector( 'input' );
					if ( input ) input.focus();
				}
			}
			return;
		}

		var rmBtn = event.target.closest( '[data-menucraft-variant-remove]' );
		if ( rmBtn ) {
			event.preventDefault();
			var row = rmBtn.closest( '[data-menucraft-variant-index]' );
			if ( ! row ) {
				return;
			}
			var idx = parseInt( row.getAttribute( 'data-menucraft-variant-index' ), 10 );
			if ( isNaN( idx ) ) {
				return;
			}
			itemFormState.variants.splice( idx, 1 );
			renderVariantsList();
			renderVariantsSummary();
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		var field = event.target.closest( '[data-menucraft-variant-field]' );
		if ( ! field ) {
			return;
		}
		var row = field.closest( '[data-menucraft-variant-index]' );
		if ( ! row ) {
			return;
		}
		var idx = parseInt( row.getAttribute( 'data-menucraft-variant-index' ), 10 );
		if ( isNaN( idx ) || ! itemFormState.variants[ idx ] ) {
			return;
		}
		var name = field.getAttribute( 'data-menucraft-variant-field' );
		if ( name === 'price' ) {
			itemFormState.variants[ idx ].price = parseFloat( field.value ) || 0;
		} else {
			itemFormState.variants[ idx ][ name ] = field.value;
		}
		if ( name === 'label' ) {
			renderVariantsSummary();
		}
	} );

	// =========================================================== Offers ==

	function mysqlToDatetimeLocal( s ) {
		if ( ! s ) return '';
		// "2026-09-15 14:30:00" → "2026-09-15T14:30"
		return String( s ).replace( ' ', 'T' ).substring( 0, 16 );
	}

	function currentMySQLString() {
		var d   = new Date();
		var pad = function ( n ) { return n < 10 ? '0' + n : String( n ); };
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ) +
			' ' + pad( d.getHours() ) + ':' + pad( d.getMinutes() ) + ':' + pad( d.getSeconds() );
	}

	function findItemInCache( id ) {
		var state = listStates.items;
		if ( ! state ) return null;
		for ( var i = 0; i < state.cache.length; i++ ) {
			if ( state.cache[ i ].id === id ) return state.cache[ i ];
		}
		return null;
	}

	function offerValidityStatus( offer, now ) {
		var from  = offer.valid_from;
		var until = offer.valid_until;
		if ( ! from && ! until ) return 'always';
		if ( from && from > now ) return 'upcoming';
		if ( until && until < now ) return 'expired';
		return 'current';
	}

	function hasActiveOfferFilters( f ) {
		return !! (
			f.search ||
			f.status ||
			f.validity ||
			f.price_min !== null ||
			f.price_max !== null ||
			f.image
		);
	}

	function countActiveOfferFilters( f ) {
		var n = 0;
		if ( f.search ) n++;
		if ( f.status ) n++;
		if ( f.validity ) n++;
		if ( f.price_min !== null || f.price_max !== null ) n++;
		if ( f.image ) n++;
		return n;
	}

	function matchesOfferFilters( offer, f ) {
		if ( f.search ) {
			var q   = f.search.toLowerCase();
			var hay = (
				( offer.name || '' ) + ' ' +
				( offer.description || '' ) + ' ' +
				( offer.conditions_text || '' )
			).toLowerCase();
			if ( hay.indexOf( q ) === -1 ) return false;
		}
		if ( f.status === 'active' && ! offer.is_active ) return false;
		if ( f.status === 'inactive' && offer.is_active ) return false;

		if ( f.validity ) {
			var status = offerValidityStatus( offer, currentMySQLString() );
			if ( status !== f.validity ) return false;
		}

		if ( f.price_min !== null && offer.price < f.price_min ) return false;
		if ( f.price_max !== null && offer.price > f.price_max ) return false;

		if ( f.image === 'with' && ! offer.media_id ) return false;
		if ( f.image === 'without' && offer.media_id ) return false;

		return true;
	}

	// -------- Offer row (list-table) --------

	function buildOfferRow( entity ) {
		var tr = document.createElement( 'tr' );
		tr.setAttribute( 'data-menucraft-row-id', String( entity.id ) );

		tr.appendChild( buildThumbCell( entity ) );

		var tdName = document.createElement( 'td' );
		tdName.className   = 'menucraft-col-name';
		var nameStrong     = document.createElement( 'strong' );
		nameStrong.textContent = entity.name;
		tdName.appendChild( nameStrong );
		if ( entity.description ) {
			var sub = document.createElement( 'div' );
			sub.className   = 'menucraft-cell-sub';
			sub.textContent = truncate( entity.description, 60 );
			tdName.appendChild( sub );
		}
		if ( entity.conditions_text ) {
			var cond = document.createElement( 'div' );
			cond.className   = 'menucraft-cell-sub menucraft-cell-conditions';
			cond.textContent = truncate( entity.conditions_text, 60 );
			cond.title       = entity.conditions_text;
			tdName.appendChild( cond );
		}
		tr.appendChild( tdName );

		var tdPrice = document.createElement( 'td' );
		tdPrice.className = 'menucraft-col-price';
		var priceStrong   = document.createElement( 'strong' );
		priceStrong.textContent = formatPrice( entity.price );
		tdPrice.appendChild( priceStrong );
		tr.appendChild( tdPrice );

		var tdValidity = document.createElement( 'td' );
		tdValidity.className = 'menucraft-col-validity';
		tdValidity.appendChild( buildOfferValidityCell( entity ) );
		tr.appendChild( tdValidity );

		var tdItems = document.createElement( 'td' );
		tdItems.className = 'menucraft-col-items';
		tdItems.appendChild( buildOfferItemsCell( entity ) );
		tr.appendChild( tdItems );

		tr.appendChild( buildActiveCell( entity ) );
		tr.appendChild( buildDatesCellWrapped( entity ) );
		tr.appendChild( buildActionsCellWrapped( entity ) );

		return tr;
	}

	function buildOfferValidityCell( offer ) {
		var wrap   = document.createElement( 'div' );
		wrap.className = 'menucraft-cell-validity';

		var from  = offer.valid_from ? String( offer.valid_from ).substring( 0, 16 ).replace( 'T', ' ' ) : '';
		var until = offer.valid_until ? String( offer.valid_until ).substring( 0, 16 ).replace( 'T', ' ' ) : '';

		var range = document.createElement( 'div' );
		if ( from && until ) {
			var tmpl = i18n.offerBetween || '%1$s – %2$s';
			range.textContent = tmpl.replace( '%1$s', from ).replace( '%2$s', until );
		} else if ( from ) {
			range.textContent = ( i18n.offerFrom || 'From %s' ).replace( '%s', from );
		} else if ( until ) {
			range.textContent = ( i18n.offerUntil || 'Until %s' ).replace( '%s', until );
		} else {
			range.textContent = i18n.offerAlways || 'Always';
		}
		wrap.appendChild( range );

		var status = offerValidityStatus( offer, currentMySQLString() );
		if ( status !== 'always' ) {
			var badge = document.createElement( 'span' );
			badge.className = 'menucraft-cell-sub menucraft-validity-badge menucraft-validity-' + status;
			if ( status === 'current' ) badge.textContent = i18n.offerCurrent || 'Currently valid';
			else if ( status === 'upcoming' ) badge.textContent = i18n.offerUpcoming || 'Upcoming';
			else if ( status === 'expired' ) badge.textContent = i18n.offerExpired || 'Expired';
			wrap.appendChild( badge );
		}

		return wrap;
	}

	function buildOfferItemsCell( offer ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'menucraft-related-list';

		var lines = ( offer.items || [] );
		if ( ! lines.length ) {
			wrap.textContent = '—';
			return wrap;
		}

		lines.forEach( function ( line ) {
			var item = findItemInCache( parseInt( line.item_id, 10 ) );
			if ( ! item ) return;

			var label = item.name;
			if ( line.variant_id ) {
				var variant = ( item.variants || [] ).filter( function ( v ) {
					return v.id === parseInt( line.variant_id, 10 );
				} )[ 0 ];
				if ( variant ) label += ' (' + variant.label + ')';
			}
			if ( line.quantity && line.quantity > 1 ) {
				label = line.quantity + '× ' + label;
			}
			var pill = document.createElement( 'span' );
			pill.className   = 'menucraft-related-pill';
			pill.textContent = label;
			wrap.appendChild( pill );
		} );

		if ( ! wrap.children.length ) {
			wrap.textContent = '—';
		}
		return wrap;
	}

	// -------- Offer items sub-panel --------

	function renderOfferItemsSummary() {
		var counter = document.querySelector( '[data-menucraft-offer-items-count]' );
		if ( ! counter ) return;
		var n = offerFormState.items.length;
		if ( n === 0 ) {
			counter.textContent = i18n.offerLinesNone || 'None';
		} else {
			var tmpl = i18n.offerLinesCount || '%d line(s)';
			counter.textContent = tmpl.replace( '%d', String( n ) );
		}
	}

	function renderOfferItemsChips() {
		var container = document.querySelector( '[data-menucraft-offer-items-chips]' );
		if ( ! container ) return;

		var emptyMsg = container.getAttribute( 'data-menucraft-chips-empty' ) || '';
		var state    = listStates.items;

		container.innerHTML = '';

		if ( ! state || ! state.cache.length ) {
			var empty = document.createElement( 'span' );
			empty.className   = 'menucraft-chips-empty';
			empty.textContent = emptyMsg;
			container.appendChild( empty );
			return;
		}

		// Count how many lines each item currently has (for the "×N" badge).
		var counts = {};
		offerFormState.items.forEach( function ( line ) {
			counts[ line.item_id ] = ( counts[ line.item_id ] || 0 ) + 1;
		} );

		state.cache.forEach( function ( item ) {
			if ( ! item.is_active ) return;
			var chip = document.createElement( 'button' );
			chip.type      = 'button';
			chip.className = 'menucraft-chip';
			if ( counts[ item.id ] ) {
				chip.classList.add( 'menucraft-chip-selected' );
			}
			chip.setAttribute( 'data-menucraft-offer-items-chip', String( item.id ) );

			var label = document.createElement( 'span' );
			label.className   = 'menucraft-chip-label';
			label.textContent = item.name;
			chip.appendChild( label );

			if ( counts[ item.id ] > 1 ) {
				var badge = document.createElement( 'span' );
				badge.className   = 'menucraft-chip-code';
				badge.textContent = '×' + counts[ item.id ];
				chip.appendChild( badge );
			}
			container.appendChild( chip );
		} );
	}

	function renderOfferItemsList() {
		var container = document.querySelector( '[data-menucraft-offer-items-list]' );
		if ( ! container ) return;
		container.innerHTML = '';

		offerFormState.items.forEach( function ( line, index ) {
			var item = findItemInCache( parseInt( line.item_id, 10 ) );
			if ( ! item ) return;

			var row = document.createElement( 'div' );
			row.className = 'menucraft-offer-line';
			row.setAttribute( 'data-menucraft-offer-line-index', String( index ) );

			var nameCell = document.createElement( 'div' );
			nameCell.className   = 'menucraft-offer-line-cell menucraft-offer-line-cell-name';
			nameCell.textContent = item.name;
			row.appendChild( nameCell );

			var variantCell = document.createElement( 'div' );
			variantCell.className = 'menucraft-offer-line-cell menucraft-offer-line-cell-variant';
			var variants = ( item.variants || [] ).filter( function ( v ) { return v.is_active; } );
			if ( variants.length ) {
				var select = document.createElement( 'select' );
				select.setAttribute( 'data-menucraft-offer-line-variant', '' );
				select.setAttribute( 'aria-label', 'Variant' );
				var opt0 = document.createElement( 'option' );
				opt0.value       = '';
				opt0.textContent = i18n.offerPickVariant || '— pick variant —';
				select.appendChild( opt0 );
				variants.forEach( function ( v ) {
					var opt = document.createElement( 'option' );
					opt.value       = String( v.id );
					opt.textContent = v.label + ' — ' + formatPrice( v.price );
					if ( line.variant_id === v.id ) opt.selected = true;
					select.appendChild( opt );
				} );
				variantCell.appendChild( select );
			} else {
				var noVariant = document.createElement( 'span' );
				noVariant.className   = 'menucraft-cell-sub';
				noVariant.textContent = i18n.offerNoVariant || '(no variant)';
				variantCell.appendChild( noVariant );
			}
			row.appendChild( variantCell );

			var qtyCell = document.createElement( 'div' );
			qtyCell.className = 'menucraft-offer-line-cell menucraft-offer-line-cell-qty';
			var qtyLabel      = document.createElement( 'span' );
			qtyLabel.className   = 'menucraft-cell-sub';
			qtyLabel.textContent = ( i18n.offerQuantity || 'Qty' ) + ' ';
			qtyCell.appendChild( qtyLabel );
			var qtyInput = document.createElement( 'input' );
			qtyInput.type  = 'number';
			qtyInput.min   = '1';
			qtyInput.step  = '1';
			qtyInput.value = String( line.quantity || 1 );
			qtyInput.setAttribute( 'data-menucraft-offer-line-qty', '' );
			qtyInput.setAttribute( 'aria-label', i18n.offerQuantity || 'Qty' );
			qtyCell.appendChild( qtyInput );
			row.appendChild( qtyCell );

			var removeCell = document.createElement( 'div' );
			removeCell.className = 'menucraft-offer-line-cell menucraft-offer-line-cell-remove';
			var removeBtn        = document.createElement( 'button' );
			removeBtn.type      = 'button';
			removeBtn.className = 'button-link menucraft-btn-icon';
			removeBtn.title     = i18n.offerRemoveLine || 'Remove line';
			removeBtn.setAttribute( 'aria-label', i18n.offerRemoveLine || 'Remove line' );
			removeBtn.setAttribute( 'data-menucraft-offer-line-remove', '' );
			removeBtn.innerHTML = '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
			removeCell.appendChild( removeBtn );
			row.appendChild( removeCell );

			container.appendChild( row );
		} );

		var empty = document.querySelector( '[data-menucraft-offer-items-empty]' );
		if ( empty ) {
			empty.hidden = offerFormState.items.length > 0;
		}
	}

	// Chip click → add a new line for that item. Same item can appear
	// multiple times (different variants or quantities).
	document.addEventListener( 'click', function ( event ) {
		var chip = event.target.closest( '[data-menucraft-offer-items-chip]' );
		if ( ! chip ) return;
		event.preventDefault();
		var itemId = parseInt( chip.getAttribute( 'data-menucraft-offer-items-chip' ), 10 );
		if ( ! itemId ) return;
		offerFormState.items.push( {
			id:         0,
			item_id:    itemId,
			variant_id: null,
			quantity:   1,
			sort_order: offerFormState.items.length,
		} );
		renderOfferItemsChips();
		renderOfferItemsList();
		renderOfferItemsSummary();
	} );

	// Remove a line by index.
	document.addEventListener( 'click', function ( event ) {
		var removeBtn = event.target.closest( '[data-menucraft-offer-line-remove]' );
		if ( ! removeBtn ) return;
		event.preventDefault();
		var row = removeBtn.closest( '[data-menucraft-offer-line-index]' );
		if ( ! row ) return;
		var idx = parseInt( row.getAttribute( 'data-menucraft-offer-line-index' ), 10 );
		if ( isNaN( idx ) ) return;
		offerFormState.items.splice( idx, 1 );
		renderOfferItemsChips();
		renderOfferItemsList();
		renderOfferItemsSummary();
	} );

	// Variant selection or quantity change on an offer line.
	document.addEventListener( 'change', function ( event ) {
		var select = event.target.closest( '[data-menucraft-offer-line-variant]' );
		if ( select ) {
			var row = select.closest( '[data-menucraft-offer-line-index]' );
			if ( ! row ) return;
			var idx = parseInt( row.getAttribute( 'data-menucraft-offer-line-index' ), 10 );
			if ( ! isNaN( idx ) && offerFormState.items[ idx ] ) {
				var v = parseInt( select.value, 10 );
				offerFormState.items[ idx ].variant_id = v ? v : null;
			}
		}
	} );

	document.addEventListener( 'input', function ( event ) {
		var qty = event.target.closest( '[data-menucraft-offer-line-qty]' );
		if ( qty ) {
			var row = qty.closest( '[data-menucraft-offer-line-index]' );
			if ( ! row ) return;
			var idx = parseInt( row.getAttribute( 'data-menucraft-offer-line-index' ), 10 );
			if ( ! isNaN( idx ) && offerFormState.items[ idx ] ) {
				var n = parseInt( qty.value, 10 );
				offerFormState.items[ idx ].quantity = ( isNaN( n ) || n < 1 ) ? 1 : n;
			}
		}
	} );

	// ========================================================= Select-box ==
	//
	// Select2-style multi-select: text input with search, dropdown of
	// filtered options, pills for chosen values. Same data source as chips
	// (listStates.<resource>.cache) so the two widgets can coexist on
	// different pages.
	//
	// DOM (built by initSelects on boot):
	//   <div data-menucraft-select="{resource}"
	//        data-menucraft-select-name="{payload_key}"
	//        data-menucraft-select-placeholder="…"
	//        data-menucraft-select-empty="…">
	//     <div class="menucraft-select-control">
	//       <span class="menucraft-select-pill" data-id="…">…<button>×</button></span>
	//       …
	//       <input type="text" class="menucraft-select-input">
	//     </div>
	//     <div class="menucraft-select-dropdown" hidden>
	//       <div class="menucraft-select-option" data-id="…">…</div>
	//     </div>
	//   </div>

	function initSelects() {
		var containers = document.querySelectorAll( '[data-menucraft-select]' );
		Array.prototype.forEach.call( containers, buildSelectShell );
	}

	function buildSelectShell( container ) {
		if ( container.querySelector( '.menucraft-select-control' ) ) {
			return; // already initialised
		}
		var placeholder = container.getAttribute( 'data-menucraft-select-placeholder' ) || '';

		var control = document.createElement( 'div' );
		control.className = 'menucraft-select-control';

		// Pills area (top): may wrap across several rows when many are picked.
		var pills = document.createElement( 'div' );
		pills.className = 'menucraft-select-pills';
		control.appendChild( pills );

		// Search input (bottom): always on its own line under the pills.
		var input = document.createElement( 'input' );
		input.type              = 'text';
		input.className         = 'menucraft-select-input';
		input.placeholder       = placeholder;
		input.autocomplete      = 'off';
		input.setAttribute( 'spellcheck', 'false' );
		control.appendChild( input );

		var dropdown = document.createElement( 'div' );
		dropdown.className = 'menucraft-select-dropdown';
		dropdown.hidden    = true;

		container.appendChild( control );
		container.appendChild( dropdown );
	}

	function getSelectSelectedIds( container ) {
		var pills = container.querySelectorAll( '.menucraft-select-pill' );
		return Array.prototype.map.call( pills, function ( p ) {
			return parseInt( p.getAttribute( 'data-id' ), 10 );
		} ).filter( function ( n ) { return ! isNaN( n ); } );
	}

	function renderSelectDropdown( container, filter ) {
		var dropdown = container.querySelector( '.menucraft-select-dropdown' );
		if ( ! dropdown ) return;

		var resource = container.getAttribute( 'data-menucraft-select' );
		var state    = listStates[ resource ];
		var emptyMsg = container.getAttribute( 'data-menucraft-select-empty' ) || '';

		dropdown.innerHTML = '';

		if ( ! state || ! state.cache.length ) {
			var e = document.createElement( 'div' );
			e.className   = 'menucraft-select-empty';
			e.textContent = emptyMsg;
			dropdown.appendChild( e );
			return;
		}

		var selected = getSelectSelectedIds( container );
		var q        = ( filter || '' ).toLowerCase().trim();

		var matches = state.cache.filter( function ( row ) {
			if ( selected.indexOf( row.id ) > -1 ) return false;
			if ( '' === q ) return true;
			return row.name.toLowerCase().indexOf( q ) > -1
				|| ( row.code && String( row.code ).toLowerCase().indexOf( q ) > -1 );
		} );

		if ( ! matches.length ) {
			var n = document.createElement( 'div' );
			n.className   = 'menucraft-select-empty';
			n.textContent = i18n.noMatches || 'No matches.';
			dropdown.appendChild( n );
			return;
		}

		matches.forEach( function ( row ) {
			var opt = document.createElement( 'div' );
			opt.className = 'menucraft-select-option';
			opt.setAttribute( 'data-id', String( row.id ) );
			if ( row.code ) {
				var code = document.createElement( 'span' );
				code.className   = 'menucraft-select-option-code';
				code.textContent = row.code;
				opt.appendChild( code );
			}
			var lbl = document.createElement( 'span' );
			lbl.className   = 'menucraft-select-option-label';
			lbl.textContent = row.name;
			opt.appendChild( lbl );
			dropdown.appendChild( opt );
		} );
	}

	function addSelectPill( container, id ) {
		var resource = container.getAttribute( 'data-menucraft-select' );
		var state    = listStates[ resource ];
		if ( ! state ) return;
		var row = null;
		for ( var i = 0; i < state.cache.length; i++ ) {
			if ( state.cache[ i ].id === id ) { row = state.cache[ i ]; break; }
		}
		if ( ! row ) return;

		var pillsArea = container.querySelector( '.menucraft-select-pills' );
		if ( ! pillsArea ) return;

		var pill = document.createElement( 'span' );
		pill.className = 'menucraft-select-pill';
		pill.setAttribute( 'data-id', String( id ) );

		if ( row.color ) {
			pill.style.setProperty( '--menucraft-chip-color', row.color );
		}

		if ( row.code ) {
			var code = document.createElement( 'span' );
			code.className   = 'menucraft-select-pill-code';
			code.textContent = row.code;
			pill.appendChild( code );
		}

		var label = document.createElement( 'span' );
		label.className   = 'menucraft-select-pill-label';
		label.textContent = row.name;
		pill.appendChild( label );

		var rm = document.createElement( 'button' );
		rm.type      = 'button';
		rm.className = 'menucraft-select-pill-remove';
		rm.textContent = '×';
		rm.setAttribute( 'aria-label', i18n.delete || 'Remove' );
		pill.appendChild( rm );

		pillsArea.appendChild( pill );
	}

	// Public helpers used from the form-lifecycle code above.
	function resetSelect( container ) {
		var pills = container.querySelectorAll( '.menucraft-select-pill' );
		Array.prototype.forEach.call( pills, function ( p ) { p.parentNode.removeChild( p ); } );
		var input = container.querySelector( '.menucraft-select-input' );
		if ( input ) input.value = '';
		var dropdown = container.querySelector( '.menucraft-select-dropdown' );
		if ( dropdown ) dropdown.hidden = true;
	}

	function setSelectSelection( container, ids ) {
		resetSelect( container );
		ids.forEach( function ( id ) { addSelectPill( container, id ); } );
	}

	function collectSelectSelections( form, payload ) {
		var containers = form.querySelectorAll( '[data-menucraft-select-name]' );
		Array.prototype.forEach.call( containers, function ( container ) {
			var name = container.getAttribute( 'data-menucraft-select-name' );
			payload[ name ] = getSelectSelectedIds( container );
		} );
	}

	function refreshSelectsFor( resource ) {
		var containers = document.querySelectorAll( '[data-menucraft-select="' + resource + '"]' );
		Array.prototype.forEach.call( containers, function ( container ) {
			// If dropdown is currently open, re-render options against fresh
			// cache. If it's closed, nothing to do — options are rebuilt on
			// next focus.
			var dropdown = container.querySelector( '.menucraft-select-dropdown' );
			if ( dropdown && ! dropdown.hidden ) {
				var input = container.querySelector( '.menucraft-select-input' );
				renderSelectDropdown( container, input ? input.value : '' );
			}
			// Also rebuild any pill that has stale data (e.g. label change).
			var pills = container.querySelectorAll( '.menucraft-select-pill' );
			if ( pills.length ) {
				var ids = getSelectSelectedIds( container );
				setSelectSelection( container, ids );
			}
		} );
	}

	// ---- Event delegation ----

	document.addEventListener( 'focusin', function ( event ) {
		var input = event.target.closest( '.menucraft-select-input' );
		if ( ! input ) return;
		var container = input.closest( '[data-menucraft-select]' );
		if ( ! container ) return;
		var dropdown = container.querySelector( '.menucraft-select-dropdown' );
		if ( dropdown ) dropdown.hidden = false;
		renderSelectDropdown( container, input.value );
	} );

	document.addEventListener( 'input', function ( event ) {
		var input = event.target.closest( '.menucraft-select-input' );
		if ( ! input ) return;
		var container = input.closest( '[data-menucraft-select]' );
		if ( ! container ) return;
		var dropdown = container.querySelector( '.menucraft-select-dropdown' );
		if ( dropdown ) dropdown.hidden = false;
		renderSelectDropdown( container, input.value );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		var input = event.target.closest( '.menucraft-select-input' );
		if ( ! input ) return;
		var container = input.closest( '[data-menucraft-select]' );
		if ( ! container ) return;

		if ( 'Escape' === event.key ) {
			var dropdown = container.querySelector( '.menucraft-select-dropdown' );
			if ( dropdown ) dropdown.hidden = true;
			return;
		}

		// Backspace on empty input removes the last pill — muscle-memory
		// familiar from Select2 / Chosen.
		if ( 'Backspace' === event.key && '' === input.value ) {
			var pills = container.querySelectorAll( '.menucraft-select-pill' );
			if ( pills.length ) {
				pills[ pills.length - 1 ].parentNode.removeChild( pills[ pills.length - 1 ] );
				var dd = container.querySelector( '.menucraft-select-dropdown' );
				if ( dd && ! dd.hidden ) renderSelectDropdown( container, '' );
			}
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		// Click on an option — add pill + reset input + refocus.
		var opt = event.target.closest( '.menucraft-select-option' );
		if ( opt ) {
			var optContainer = opt.closest( '[data-menucraft-select]' );
			if ( optContainer ) {
				event.preventDefault();
				var id = parseInt( opt.getAttribute( 'data-id' ), 10 );
				if ( ! isNaN( id ) ) {
					addSelectPill( optContainer, id );
					var input = optContainer.querySelector( '.menucraft-select-input' );
					if ( input ) {
						input.value = '';
						input.focus();
					}
					renderSelectDropdown( optContainer, '' );
				}
			}
			return;
		}

		// Click on pill remove.
		var rm = event.target.closest( '.menucraft-select-pill-remove' );
		if ( rm ) {
			event.preventDefault();
			var pill = rm.closest( '.menucraft-select-pill' );
			var pillContainer = pill && pill.closest( '[data-menucraft-select]' );
			if ( pill && pill.parentNode ) pill.parentNode.removeChild( pill );
			if ( pillContainer ) {
				var pillInput = pillContainer.querySelector( '.menucraft-select-input' );
				var pillDd    = pillContainer.querySelector( '.menucraft-select-dropdown' );
				if ( pillDd && ! pillDd.hidden ) {
					renderSelectDropdown( pillContainer, pillInput ? pillInput.value : '' );
				}
				if ( pillInput ) pillInput.focus();
			}
			return;
		}

		// Click on control (empty area) — focus the input.
		var ctrl = event.target.closest( '.menucraft-select-control' );
		if ( ctrl && event.target === ctrl ) {
			var ctrlInput = ctrl.querySelector( '.menucraft-select-input' );
			if ( ctrlInput ) ctrlInput.focus();
			return;
		}

		// Click anywhere outside any select — close all open dropdowns.
		if ( ! event.target.closest( '[data-menucraft-select]' ) ) {
			var open = document.querySelectorAll( '.menucraft-select-dropdown' );
			Array.prototype.forEach.call( open, function ( d ) { d.hidden = true; } );
		}
	} );

	// =========================================================== Toast ==

	function showToast( message, type ) {
		var toast = document.createElement( 'div' );
		toast.className   = 'menucraft-toast menucraft-toast-' + ( type || 'info' );
		toast.textContent = message;
		toast.setAttribute( 'role', type === 'error' ? 'alert' : 'status' );
		document.body.appendChild( toast );

		toast.addEventListener( 'click', function () { dismissToast( toast ); } );

		requestAnimationFrame( function () {
			toast.classList.add( 'menucraft-toast-visible' );
		} );

		setTimeout( function () { dismissToast( toast ); }, 3200 );
	}

	function dismissToast( toast ) {
		if ( ! toast || ! toast.parentNode ) {
			return;
		}
		toast.classList.remove( 'menucraft-toast-visible' );
		setTimeout( function () {
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}, 220 );
	}

	// =================================================== Currency prefix ==

	/**
	 * Wrap a price input in an input-group with a non-editable currency
	 * prefix chip. Idempotent: if the input is already wrapped, returns
	 * the existing wrapper.
	 */
	function wrapPriceInput( input ) {
		if ( ! input ) return input;
		var parent = input.parentNode;
		if ( parent && parent.classList && parent.classList.contains( 'menucraft-input-group' ) ) {
			return parent;
		}

		var group = document.createElement( 'span' );
		group.className = 'menucraft-input-group';

		var prefix = document.createElement( 'span' );
		prefix.className    = 'menucraft-input-group-prefix';
		prefix.textContent  = settings.currency || '';
		prefix.setAttribute( 'aria-hidden', 'true' );

		input.classList.add( 'menucraft-input-group-input' );

		if ( parent ) {
			parent.replaceChild( group, input );
		}
		group.appendChild( prefix );
		group.appendChild( input );
		return group;
	}

	function applyCurrencyPrefix( root ) {
		var scope  = root || document;
		var inputs = scope.querySelectorAll( '[data-menucraft-price]' );
		Array.prototype.forEach.call( inputs, wrapPriceInput );
	}

	// ============================================================ Options ==

	function initOptionsForm() {
		var form = document.querySelector( '[data-menucraft-options-form]' );
		if ( ! form ) return;

		rest( 'options' )
			.then( function ( opts ) {
				Object.keys( opts || {} ).forEach( function ( key ) {
					var el = form.querySelector( '[name="' + key + '"]' );
					if ( el ) el.value = opts[ key ] == null ? '' : String( opts[ key ] );
				} );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.listError || 'Could not load options.', 'error' );
			} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var payload = collectFormData( form );
			var saveBtn = form.querySelector( '[data-menucraft-submit]' );
			setBusy( saveBtn, true );
			rest( 'options', { method: 'POST', body: payload } )
				.then( function () {
					showToast( i18n.saveSuccess || 'Saved.', 'success' );
				} )
				.catch( function ( err ) {
					showToast( err.message || i18n.saveError || 'Save failed.', 'error' );
				} )
				.then( function () {
					setBusy( saveBtn, false );
				} );
		} );
	}

	// ========================================================= Accordion ==

	// One-open-at-a-time behaviour for [data-menucraft-accordion] blocks.
	// Uses the `toggle` event on <details>; that event doesn't bubble, so
	// we listen in the capture phase.
	document.addEventListener( 'toggle', function ( event ) {
		var d = event.target;
		if ( ! d || 'DETAILS' !== d.tagName ) return;
		var group = d.closest( '[data-menucraft-accordion]' );
		if ( ! group ) return;
		if ( ! d.open ) return;
		var siblings = group.querySelectorAll( ':scope > details' );
		Array.prototype.forEach.call( siblings, function ( s ) {
			if ( s !== d && s.open ) s.open = false;
		} );
	}, true );

	// =========================================================== Boot ===

	// -------- Dashboard counter animation --------
	// Any element with data-menucraft-counter="N" animates its textContent
	// from 0 to N (integer) with an ease-out curve. Cheap, dependency-free.
	function animateCounters() {
		var els = document.querySelectorAll( '[data-menucraft-counter]' );
		if ( ! els.length ) return;

		Array.prototype.forEach.call( els, function ( el ) {
			var target = parseInt( el.getAttribute( 'data-menucraft-counter' ), 10 );
			if ( isNaN( target ) || target < 0 ) {
				el.textContent = '0';
				return;
			}
			if ( 0 === target ) {
				el.textContent = '0';
				return;
			}
			// Duration scales gently with the target so tiny numbers don't
			// feel sluggish and huge ones aren't over in a blink.
			var duration = Math.min( 1200, 400 + target * 6 );
			var start    = null;

			function step( now ) {
				if ( null === start ) start = now;
				var progress = Math.min( ( now - start ) / duration, 1 );
				// ease-out cubic
				var eased = 1 - Math.pow( 1 - progress, 3 );
				el.textContent = String( Math.round( target * eased ) );
				if ( progress < 1 ) requestAnimationFrame( step );
			}
			requestAnimationFrame( step );
		} );
	}

	function boot() {
		applyCurrencyPrefix( document );
		initSelects();
		initLists();
		initOptionsForm();
		animateCounters();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
