/**
 * MenuCraft admin behaviour — vanilla JS, no library dependencies.
 *
 * Off-canvas panel:
 *  - open on click of [data-menucraft-panel-open="<panel-id>"]
 *  - close on click of [data-menucraft-panel-close] (X, Cancel, backdrop)
 *  - close on Escape key
 *
 * Confirm modal:
 *  - open programmatically via openModal(id)
 *  - close on click of [data-menucraft-modal-close]
 *
 * Media picker:
 *  - opens native wp.media library (loaded by wp_enqueue_media())
 *  - stores attachment id in a hidden input, updates preview
 *
 * Lists (resource-agnostic):
 *  - every <table data-menucraft-list="<resource>"> is initialized on load;
 *    the table also declares its panel + delete-modal via data-attrs.
 *  - inline is_active toggle (PUT), Edit (opens panel pre-filled), Delete
 *    (opens confirm modal → DELETE), Create (POST) via REST.
 *  - form submit uses data-menucraft-endpoint + data-menucraft-mode to
 *    choose between POST and PUT.
 */
( function () {
	'use strict';

	var settings = ( typeof window.menucraftAdmin === 'object' && window.menucraftAdmin ) || {};
	var i18n     = settings.i18n || {};

	var OPEN_CLASS  = 'menucraft-offcanvas-is-open';
	var MODAL_OPEN  = 'menucraft-modal-is-open';
	var BODY_LOCK   = 'menucraft-offcanvas-open';
	var lastFocused = null;

	// Per-resource state: cache of rows + DOM refs.
	var listStates = {};

	// Delete-flow context (set when the confirm modal is opened).
	var deleteContext = null;

	// ============================================================ Panel ==

	function openPanel( id ) {
		var panel = document.getElementById( id );
		if ( ! panel ) {
			return;
		}

		lastFocused = document.activeElement;
		panel.classList.add( OPEN_CLASS );
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
		panel.classList.remove( OPEN_CLASS );
		panel.setAttribute( 'aria-hidden', 'true' );

		if ( ! document.querySelector( '.' + OPEN_CLASS + ', .' + MODAL_OPEN ) ) {
			document.body.classList.remove( BODY_LOCK );
		}

		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
		lastFocused = null;
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
		lastFocused = document.activeElement;
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

		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
		lastFocused = null;
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
		var panel = document.querySelector( '.menucraft-offcanvas.' + OPEN_CLASS );
		if ( panel ) {
			closePanel( panel );
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
				closePanel( panel );

				// Reflect the mutation in the associated list if any.
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

		setFieldValue( form, 'name', entity.name || '' );
		setFieldValue( form, 'description', entity.description || '' );
		setFieldValue( form, 'color', entity.color || '#3858e9' );
		setFieldValue( form, 'parent_id', entity.parent_id ? String( entity.parent_id ) : '' );
		setFieldValue( form, 'sort_order', String( entity.sort_order || 0 ) );

		var activeBox = form.querySelector( '[name="is_active"]' );
		if ( activeBox ) {
			activeBox.checked = !! entity.is_active;
		}

		var picker = form.querySelector( '[data-menucraft-media-picker]' );
		if ( picker ) {
			setMediaByUrl( picker, entity.media_id, entity.media_url );
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

	function initLists() {
		var tables = document.querySelectorAll( '[data-menucraft-list]' );
		Array.prototype.forEach.call( tables, function ( table ) {
			var resource = table.getAttribute( 'data-menucraft-list' );
			var body     = table.querySelector( '[data-menucraft-list-body]' );
			if ( ! resource || ! body ) {
				return;
			}
			listStates[ resource ] = {
				resource:      resource,
				table:         table,
				body:          body,
				cache:         [],
				panelId:       table.getAttribute( 'data-menucraft-panel' ) || '',
				deleteModalId: table.getAttribute( 'data-menucraft-modal-delete' ) || '',
			};
			fetchList( listStates[ resource ] );
		} );
	}

	function fetchList( state ) {
		rest( state.resource )
			.then( function ( rows ) {
				state.cache = Array.isArray( rows ) ? rows : [];
				renderTable( state );
				refreshParentDropdown( state );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.listError || 'Could not load list.', 'error' );
				renderStatus( state, i18n.listError || 'Could not load list.' );
			} );
	}

	function renderTable( state ) {
		state.body.innerHTML = '';
		if ( ! state.cache.length ) {
			state.body.appendChild( buildStatusRow( i18n.empty || 'No entries yet.' ) );
			return;
		}
		state.cache.forEach( function ( row ) {
			state.body.appendChild( buildRow( row ) );
		} );
	}

	function renderStatus( state, text ) {
		state.body.innerHTML = '';
		state.body.appendChild( buildStatusRow( text ) );
	}

	function buildStatusRow( text ) {
		var tr = document.createElement( 'tr' );
		tr.className = 'menucraft-row-status';
		var td = document.createElement( 'td' );
		td.colSpan     = 7;
		td.textContent = text;
		tr.appendChild( td );
		return tr;
	}

	function buildRow( entity ) {
		var tr = document.createElement( 'tr' );
		tr.setAttribute( 'data-menucraft-row-id', String( entity.id ) );

		// Thumbnail.
		var tdThumb = document.createElement( 'td' );
		tdThumb.className = 'menucraft-col-thumb';
		if ( entity.media_url ) {
			var img = document.createElement( 'img' );
			img.src = entity.media_url;
			img.alt = '';
			img.className = 'menucraft-thumb';
			tdThumb.appendChild( img );
		} else {
			var placeholder = document.createElement( 'span' );
			placeholder.className = 'menucraft-thumb menucraft-thumb-empty';
			placeholder.setAttribute( 'aria-hidden', 'true' );
			tdThumb.appendChild( placeholder );
		}
		tr.appendChild( tdThumb );

		// Name (+ slug sub).
		var tdName = document.createElement( 'td' );
		tdName.className = 'menucraft-col-name';
		var nameStrong   = document.createElement( 'strong' );
		nameStrong.textContent = entity.name;
		tdName.appendChild( nameStrong );
		if ( entity.slug ) {
			var slugSmall = document.createElement( 'div' );
			slugSmall.className   = 'menucraft-cell-sub';
			slugSmall.textContent = entity.slug;
			tdName.appendChild( slugSmall );
		}
		tr.appendChild( tdName );

		// Color swatch.
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

		// Description (truncated).
		var tdDesc = document.createElement( 'td' );
		tdDesc.className   = 'menucraft-col-desc';
		tdDesc.textContent = truncate( entity.description || '', 15 );
		if ( entity.description ) {
			tdDesc.title = entity.description;
		}
		tr.appendChild( tdDesc );

		// Active toggle.
		var tdActive = document.createElement( 'td' );
		tdActive.className = 'menucraft-col-active';
		tdActive.appendChild( buildActiveToggle( entity ) );
		tr.appendChild( tdActive );

		// Dates.
		var tdDates = document.createElement( 'td' );
		tdDates.className = 'menucraft-col-dates';
		tdDates.appendChild( buildDatesCell( entity ) );
		tr.appendChild( tdDates );

		// Actions.
		var tdActions = document.createElement( 'td' );
		tdActions.className = 'menucraft-col-actions';
		tdActions.appendChild( buildActionsCell( entity ) );
		tr.appendChild( tdActions );

		return tr;
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

	function refreshParentDropdown( state ) {
		if ( ! state.panelId ) {
			return;
		}
		var panel = document.getElementById( state.panelId );
		if ( ! panel ) {
			return;
		}
		var select = panel.querySelector( '[data-menucraft-parent-select]' );
		if ( ! select ) {
			return;
		}
		var currentValue = select.value;
		while ( select.options.length > 1 ) {
			select.remove( 1 );
		}
		state.cache.forEach( function ( row ) {
			var opt = document.createElement( 'option' );
			opt.value       = String( row.id );
			opt.textContent = row.name;
			select.appendChild( opt );
		} );
		select.value = currentValue;
	}

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
					refreshParentDropdown( state );
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

	// -------- Cache + DOM helpers ---------

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

		var status = state.body.querySelector( '.menucraft-row-status' );
		if ( status ) {
			status.remove();
		}

		state.body.appendChild( buildRow( entity ) );
		refreshParentDropdown( state );
	}

	function replaceRow( state, entity ) {
		updateCache( state, entity );

		var existing = state.body.querySelector( '[data-menucraft-row-id="' + entity.id + '"]' );
		var fresh    = buildRow( entity );
		if ( existing ) {
			existing.replaceWith( fresh );
		} else {
			state.body.appendChild( fresh );
		}
		refreshParentDropdown( state );
	}

	function removeRow( state, id ) {
		var existing = state.body.querySelector( '[data-menucraft-row-id="' + id + '"]' );
		if ( existing ) {
			existing.remove();
		}
		if ( ! state.body.querySelector( 'tr' ) ) {
			state.body.appendChild( buildStatusRow( i18n.empty || 'No entries yet.' ) );
		}
	}

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

	// =========================================================== Boot ===

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initLists );
	} else {
		initLists();
	}
}() );
