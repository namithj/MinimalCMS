/**
 * MinimalCMS Admin JavaScript
 *
 * @package MinimalCMS
 * @since   1.0.0
 */
( function () {
	'use strict';

	/* ── Sidebar toggle (mobile) ──────────────────────────────────────── */
	const toggle = document.querySelector( '.sidebar-toggle' );
	const sidebar = document.querySelector( '.admin-sidebar' );

	if ( toggle && sidebar ) {
		toggle.addEventListener( 'click', function () {
			sidebar.classList.toggle( 'open' );
		} );

		// Close sidebar when clicking outside on mobile.
		document.addEventListener( 'click', function ( e ) {
			if (
				sidebar.classList.contains( 'open' ) &&
				! sidebar.contains( e.target ) &&
				! toggle.contains( e.target )
			) {
				sidebar.classList.remove( 'open' );
			}
		} );
	}

	/* ── Submenu toggles ─────────────────────────────────────────────────── */
	document.querySelectorAll( '.submenu-toggle' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			const li = btn.closest( 'li' );
			if ( ! li ) { return; }
			li.classList.toggle( 'open' );
		} );
	} );

	// Auto-open the submenu that contains the active child on page load.
	document.querySelectorAll( '.has-submenu' ).forEach( function ( li ) {
		if ( li.querySelector( '.submenu .active' ) || li.classList.contains( 'active' ) ) {
			li.classList.add( 'open' );
		}
	} );

	/* ── Confirm delete actions ───────────────────────────────────────── */
	document.querySelectorAll( '.confirm-delete' ).forEach( function ( el ) {
		el.addEventListener( 'click', function ( e ) {
			if ( ! confirm( 'Are you sure you want to delete this item? This cannot be undone.' ) ) {
				e.preventDefault();
			}
		} );
	} );

	/* ── Auto-dismiss notices ─────────────────────────────────────────── */
	document.querySelectorAll( '.notice[data-dismiss]' ).forEach( function ( el ) {
		setTimeout( function () {
			el.style.transition = 'opacity .3s';
			el.style.opacity = '0';
			setTimeout( function () {
				el.remove();
			}, 300 );
		}, 4000 );
	} );

	/* ── Slug auto-generation ─────────────────────────────────────────── */
	const titleInput = document.getElementById( 'field-title' );
	const slugInput  = document.getElementById( 'field-slug' );

	if ( titleInput && slugInput && ! slugInput.dataset.manual ) {
		titleInput.addEventListener( 'input', function () {
			if ( slugInput.dataset.manual ) {
				return;
			}
			slugInput.value = titleInput.value
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-|-$/g, '' );
		} );

		slugInput.addEventListener( 'input', function () {
			slugInput.dataset.manual = '1';
		} );
	}
} )();
