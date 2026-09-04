/**
 * MenuCraft — public / front-end behaviour.
 *
 * One file per WP.org guidance. No dependencies (no jQuery). Handles:
 *  - client-side filter chips: category chips (OR within), tag chips
 *    (OR within), AND across the two groups. No "All" button — no active
 *    chip in a group means that group doesn't restrict.
 *  - long-description modal: any item marked with .menucraft-item-has-details
 *    opens a modal populated from an inline JSON payload sibling.
 */
( function () {
	'use strict';

	var ROOT_SEL     = '[data-menucraft-root]';
	var ITEM_SEL     = '[data-menucraft-item]';
	var CHIP_SEL     = '[data-menucraft-filter]';
	var MODAL_SEL    = '[data-menucraft-modal]';
	var OPENER_SEL   = '[data-menucraft-open-details]';
	var PAYLOAD_ATTR = 'data-menucraft-details';
	var MODAL_OPEN   = 'is-open';
	var BODY_LOCK    = 'menucraft-modal-lock';

	var lastFocus = null;

	function init() {
		var roots = document.querySelectorAll( ROOT_SEL );
		Array.prototype.forEach.call( roots, initRoot );
	}

	function initRoot( root ) {
		wireFilters( root );
		wireDetails( root );
	}

	// ============================================================ Filters ==

	function wireFilters( root ) {
		var chips = root.querySelectorAll( CHIP_SEL );
		Array.prototype.forEach.call( chips, function ( chip ) {
			chip.addEventListener( 'click', function () {
				chip.classList.toggle( 'is-active' );
				applyFilters( root );
			} );
		} );
	}

	function collectActive( root, kind ) {
		var out  = [];
		var sel  = '[data-menucraft-filter="' + kind + '"].is-active';
		var els  = root.querySelectorAll( sel );
		Array.prototype.forEach.call( els, function ( el ) {
			var v = parseInt( el.getAttribute( 'data-menucraft-value' ), 10 );
			if ( ! isNaN( v ) ) out.push( v );
		} );
		return out;
	}

	function itemHasAny( ids, activeSet ) {
		if ( ! activeSet.length ) return true;
		for ( var i = 0; i < activeSet.length; i++ ) {
			if ( ids.indexOf( activeSet[ i ] ) > -1 ) return true;
		}
		return false;
	}

	function parseIdList( attr ) {
		if ( ! attr ) return [];
		return attr.split( ',' ).map( function ( s ) {
			return parseInt( s, 10 );
		} ).filter( function ( n ) { return ! isNaN( n ); } );
	}

	function applyFilters( root ) {
		var activeCats = collectActive( root, 'category' );
		var activeTags = collectActive( root, 'tag' );
		var items      = root.querySelectorAll( ITEM_SEL );

		Array.prototype.forEach.call( items, function ( item ) {
			var cats = parseIdList( item.getAttribute( 'data-menucraft-categories' ) );
			var tags = parseIdList( item.getAttribute( 'data-menucraft-tags' ) );
			var show = itemHasAny( cats, activeCats )
				&& itemHasAny( tags, activeTags );
			if ( show ) {
				item.removeAttribute( 'hidden' );
			} else {
				item.setAttribute( 'hidden', '' );
			}
		} );
	}

	// ============================================================== Modal ==

	function wireDetails( root ) {
		var modal = root.querySelector( MODAL_SEL );

		// Any element with data-menucraft-open-details is a clickable
		// details opener — works for both items ([menucraft]) and
		// offers ([menucraft_offers]) with the same wiring.
		var openers = root.querySelectorAll( OPENER_SEL );
		Array.prototype.forEach.call( openers, function ( opener ) {
			opener.addEventListener( 'click', function ( e ) {
				// Don't intercept clicks on native interactive children.
				if ( e.target.closest( 'a, button, input, select, textarea' ) ) return;
				openDetailsFor( opener, modal );
			} );
			opener.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					openDetailsFor( opener, modal );
				}
			} );
		} );

		if ( ! modal ) return;

		var closers = modal.querySelectorAll( '[data-menucraft-modal-close]' );
		Array.prototype.forEach.call( closers, function ( c ) {
			c.addEventListener( 'click', function () { closeModal( modal ); } );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && modal.classList.contains( MODAL_OPEN ) ) {
				closeModal( modal );
			}
		} );
	}

	function openDetailsFor( opener, modal ) {
		if ( ! modal ) return;
		var id      = opener.getAttribute( 'data-menucraft-open-details' );
		var payload = opener.querySelector( '[' + PAYLOAD_ATTR + '="' + id + '"]' );
		if ( ! payload ) return;

		var data;
		try {
			data = JSON.parse( payload.textContent || '{}' );
		} catch ( err ) {
			return;
		}

		var titleEl = modal.querySelector( '.menucraft-modal-title' );
		var bodyEl  = modal.querySelector( '[data-menucraft-modal-body]' );
		if ( titleEl ) titleEl.textContent = data.title || '';
		// data.html is server-rendered with wp_kses_post + esc_*, safe to inject.
		if ( bodyEl ) bodyEl.innerHTML = data.html || '';

		openModal( modal );
	}

	function openModal( modal ) {
		lastFocus = document.activeElement;
		modal.classList.add( MODAL_OPEN );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( BODY_LOCK );

		var closer = modal.querySelector( '[data-menucraft-modal-close]' );
		if ( closer ) closer.focus();
	}

	function closeModal( modal ) {
		modal.classList.remove( MODAL_OPEN );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( BODY_LOCK );
		if ( lastFocus && typeof lastFocus.focus === 'function' ) {
			lastFocus.focus();
		}
	}

	// ================================================================ Boot ==

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
