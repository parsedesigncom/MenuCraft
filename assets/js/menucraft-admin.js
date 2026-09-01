/**
 * MenuCraft admin behaviour — vanilla JS, no library dependencies.
 *
 * Off-canvas panel:
 *  - open on click of [data-menucraft-panel-open="<panel-id>"]
 *  - close on click of [data-menucraft-panel-close] (X, Cancel, backdrop)
 *  - close on Escape key
 *
 * Confirm modal:
 *  - open programmatically via openModal(id, ctx)
 *  - close on click of [data-menucraft-modal-close] (backdrop, Cancel)
 *  - Escape closes the topmost open modal/panel
 *
 * Media picker:
 *  - opens native wp.media library (loaded by wp_enqueue_media())
 *  - stores attachment id in a hidden input, updates preview
 *
 * Categories screen:
 *  - fetches /categories on load, renders table rows
 *  - inline Active toggle, Edit (opens panel in edit mode), Delete
 *    (opens confirm modal), Create (POST) and Update (PUT) via REST
 */
( function () {
	'use strict';

	var settings = ( typeof window.menucraftAdmin === 'object' && window.menucraftAdmin ) || {};
	var i18n     = settings.i18n || {};

	var OPEN_CLASS  = 'menucraft-offcanvas-is-open';
	var MODAL_OPEN  = 'menucraft-modal-is-open';
	var BODY_LOCK   = 'menucraft-offcanvas-open';
	var lastFocused = null;

	var deleteContext = null; // { id, name } while the delete modal is open.

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
			// Reset panel form to create-mode when opened via the header button.
			var panelId = opener.getAttribute( 'data-menucraft-panel-open' );
			var mode    = opener.getAttribute( 'data-menucraft-panel-mode' );
			if ( 'create' === mode ) {
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

	// -------- Escape closes topmost open surface (modal or panel) --------

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

				// Refresh list for the resource we just mutated.
				if ( 'categories' === endpoint ) {
					if ( 'edit' === mode ) {
						replaceRow( entity );
					} else {
						appendRow( entity );
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
		if ( submit && submit.dataset.originalText ) {
			// If save is in-flight state, don't clobber.
			return;
		}
		if ( submit ) {
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

		// Populate fields — treat form as the shape source of truth.
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

	// ==================================== Categories list & actions ====

	var listBody     = null;
	var categoryList = []; // Cached list, used for parent-dropdown + row lookups.

	function initCategoriesScreen() {
		var table = document.querySelector( '[data-menucraft-list="categories"]' );
		if ( ! table ) {
			return;
		}
		listBody = table.querySelector( '[data-menucraft-list-body]' );

		fetchCategories();
	}

	function fetchCategories() {
		rest( 'categories' )
			.then( function ( rows ) {
				categoryList = Array.isArray( rows ) ? rows : [];
				renderCategoriesTable( categoryList );
				refreshParentDropdown();
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.listError || 'Could not load list.', 'error' );
				renderListError();
			} );
	}

	function renderCategoriesTable( rows ) {
		if ( ! listBody ) {
			return;
		}
		listBody.innerHTML = '';

		if ( ! rows.length ) {
			listBody.appendChild( buildStatusRow( i18n.empty || 'No entries yet.' ) );
			return;
		}

		rows.forEach( function ( cat ) {
			listBody.appendChild( buildRow( cat ) );
		} );
	}

	function renderListError() {
		if ( ! listBody ) {
			return;
		}
		listBody.innerHTML = '';
		listBody.appendChild( buildStatusRow( i18n.listError || 'Could not load list.' ) );
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

	function buildRow( cat ) {
		var tr = document.createElement( 'tr' );
		tr.setAttribute( 'data-menucraft-row-id', String( cat.id ) );

		// Thumbnail.
		var tdThumb = document.createElement( 'td' );
		tdThumb.className = 'menucraft-col-thumb';
		if ( cat.media_url ) {
			var img = document.createElement( 'img' );
			img.src = cat.media_url;
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

		// Name.
		var tdName = document.createElement( 'td' );
		tdName.className = 'menucraft-col-name';
		var nameStrong   = document.createElement( 'strong' );
		nameStrong.textContent = cat.name;
		tdName.appendChild( nameStrong );
		if ( cat.slug ) {
			var slugSmall = document.createElement( 'div' );
			slugSmall.className   = 'menucraft-cell-sub';
			slugSmall.textContent = cat.slug;
			tdName.appendChild( slugSmall );
		}
		tr.appendChild( tdName );

		// Color swatch.
		var tdColor = document.createElement( 'td' );
		tdColor.className = 'menucraft-col-color';
		if ( cat.color ) {
			var swatch = document.createElement( 'span' );
			swatch.className          = 'menucraft-color-swatch';
			swatch.style.background   = cat.color;
			swatch.title              = cat.color;
			tdColor.appendChild( swatch );
		} else {
			tdColor.textContent = '—';
		}
		tr.appendChild( tdColor );

		// Description (truncated).
		var tdDesc = document.createElement( 'td' );
		tdDesc.className = 'menucraft-col-desc';
		tdDesc.textContent = truncate( cat.description || '', 15 );
		if ( cat.description ) {
			tdDesc.title = cat.description;
		}
		tr.appendChild( tdDesc );

		// Active toggle.
		var tdActive = document.createElement( 'td' );
		tdActive.className = 'menucraft-col-active';
		tdActive.appendChild( buildActiveToggle( cat ) );
		tr.appendChild( tdActive );

		// Dates.
		var tdDates = document.createElement( 'td' );
		tdDates.className = 'menucraft-col-dates';
		tdDates.appendChild( buildDatesCell( cat ) );
		tr.appendChild( tdDates );

		// Actions.
		var tdActions = document.createElement( 'td' );
		tdActions.className = 'menucraft-col-actions';
		tdActions.appendChild( buildActionsCell( cat ) );
		tr.appendChild( tdActions );

		return tr;
	}

	function buildActiveToggle( cat ) {
		var btn = document.createElement( 'button' );
		btn.type      = 'button';
		btn.className = 'menucraft-toggle' + ( cat.is_active ? ' menucraft-toggle-on' : ' menucraft-toggle-off' );
		btn.setAttribute( 'data-menucraft-toggle-active', String( cat.id ) );
		btn.setAttribute( 'aria-pressed', cat.is_active ? 'true' : 'false' );
		btn.textContent = cat.is_active ? ( i18n.active || 'Active' ) : ( i18n.inactive || 'Inactive' );
		return btn;
	}

	function buildDatesCell( cat ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'menucraft-cell-dates';

		var created = document.createElement( 'div' );
		created.textContent = cat.created_at || '';
		wrap.appendChild( created );

		if ( cat.updated_at && cat.updated_at !== cat.created_at ) {
			var updated = document.createElement( 'div' );
			updated.className   = 'menucraft-cell-sub';
			updated.textContent = '↻ ' + cat.updated_at;
			wrap.appendChild( updated );
		}

		return wrap;
	}

	function buildActionsCell( cat ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'menucraft-cell-actions';

		var edit = document.createElement( 'button' );
		edit.type      = 'button';
		edit.className = 'button-link menucraft-btn-icon menucraft-btn-edit';
		edit.title     = i18n.edit || 'Edit';
		edit.setAttribute( 'aria-label', i18n.edit || 'Edit' );
		edit.setAttribute( 'data-menucraft-edit', String( cat.id ) );
		edit.innerHTML = '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';
		wrap.appendChild( edit );

		var del = document.createElement( 'button' );
		del.type      = 'button';
		del.className = 'button-link menucraft-btn-icon menucraft-btn-delete';
		del.title     = i18n.delete || 'Delete';
		del.setAttribute( 'aria-label', i18n.delete || 'Delete' );
		del.setAttribute( 'data-menucraft-delete', String( cat.id ) );
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

	function refreshParentDropdown() {
		var select = document.querySelector( '#menucraft-cat-parent' );
		if ( ! select ) {
			return;
		}
		var currentValue = select.value;
		while ( select.options.length > 1 ) {
			select.remove( 1 );
		}
		categoryList.forEach( function ( cat ) {
			var opt = document.createElement( 'option' );
			opt.value       = String( cat.id );
			opt.textContent = cat.name;
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
			handleEditClick( editBtn.getAttribute( 'data-menucraft-edit' ) );
			return;
		}

		var delBtn = event.target.closest( '[data-menucraft-delete]' );
		if ( delBtn ) {
			event.preventDefault();
			handleDeleteClick( delBtn.getAttribute( 'data-menucraft-delete' ) );
			return;
		}

		var confirm = event.target.closest( '[data-menucraft-modal-confirm="delete-category"]' );
		if ( confirm ) {
			event.preventDefault();
			handleDeleteConfirm( confirm );
		}
	} );

	function handleToggleActive( button ) {
		var id  = parseInt( button.getAttribute( 'data-menucraft-toggle-active' ), 10 );
		var cat = findCategory( id );
		if ( ! cat ) {
			return;
		}
		var next = ! cat.is_active;
		button.disabled = true;

		rest( 'categories/' + id, { method: 'PUT', body: { is_active: next } } )
			.then( function ( updated ) {
				updateCachedCategory( updated );
				replaceRow( updated );
				showToast( i18n.updateSuccess || 'Updated.', 'success' );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.saveError || 'Save failed.', 'error' );
			} )
			.then( function () {
				button.disabled = false;
			} );
	}

	function handleEditClick( idAttr ) {
		var id  = parseInt( idAttr, 10 );
		var cat = findCategory( id );
		// If not cached (odd), fetch it — otherwise use the cached copy.
		if ( cat ) {
			openPanelInEditMode( 'menucraft-panel-category-form', cat );
			return;
		}
		rest( 'categories/' + id )
			.then( function ( entity ) {
				openPanelInEditMode( 'menucraft-panel-category-form', entity );
			} )
			.catch( function ( err ) {
				showToast( err.message || i18n.listError, 'error' );
			} );
	}

	function handleDeleteClick( idAttr ) {
		var id  = parseInt( idAttr, 10 );
		var cat = findCategory( id );
		if ( ! cat ) {
			return;
		}
		deleteContext = { id: id, name: cat.name };
		var modal = document.getElementById( 'menucraft-modal-delete-category' );
		if ( ! modal ) {
			return;
		}
		var nameTarget = modal.querySelector( '[data-menucraft-modal-target-name]' );
		if ( nameTarget ) {
			nameTarget.textContent = '"' + cat.name + '"';
		}
		openModal( 'menucraft-modal-delete-category' );
	}

	function handleDeleteConfirm( button ) {
		if ( ! deleteContext ) {
			return;
		}
		var id = deleteContext.id;
		button.disabled = true;

		rest( 'categories/' + id, { method: 'DELETE' } )
			.then( function () {
				removeCachedCategory( id );
				removeRow( id );
				refreshParentDropdown();
				showToast( i18n.deleteSuccess || 'Deleted.', 'success' );
				closeModal( document.getElementById( 'menucraft-modal-delete-category' ) );
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

	function findCategory( id ) {
		for ( var i = 0; i < categoryList.length; i++ ) {
			if ( categoryList[ i ].id === id ) {
				return categoryList[ i ];
			}
		}
		return null;
	}

	function updateCachedCategory( entity ) {
		for ( var i = 0; i < categoryList.length; i++ ) {
			if ( categoryList[ i ].id === entity.id ) {
				categoryList[ i ] = entity;
				return;
			}
		}
		categoryList.push( entity );
	}

	function removeCachedCategory( id ) {
		categoryList = categoryList.filter( function ( c ) { return c.id !== id; } );
	}

	function appendRow( entity ) {
		if ( ! listBody ) {
			return;
		}
		updateCachedCategory( entity );

		// Remove the empty-state row if present.
		var status = listBody.querySelector( '.menucraft-row-status' );
		if ( status ) {
			status.remove();
		}

		listBody.appendChild( buildRow( entity ) );
		refreshParentDropdown();
	}

	function replaceRow( entity ) {
		if ( ! listBody ) {
			return;
		}
		updateCachedCategory( entity );

		var existing = listBody.querySelector( '[data-menucraft-row-id="' + entity.id + '"]' );
		var fresh    = buildRow( entity );
		if ( existing ) {
			existing.replaceWith( fresh );
		} else {
			listBody.appendChild( fresh );
		}
		refreshParentDropdown();
	}

	function removeRow( id ) {
		if ( ! listBody ) {
			return;
		}
		var existing = listBody.querySelector( '[data-menucraft-row-id="' + id + '"]' );
		if ( existing ) {
			existing.remove();
		}
		if ( ! listBody.querySelector( 'tr' ) ) {
			listBody.appendChild( buildStatusRow( i18n.empty || 'No entries yet.' ) );
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
		document.addEventListener( 'DOMContentLoaded', initCategoriesScreen );
	} else {
		initCategoriesScreen();
	}
}() );
