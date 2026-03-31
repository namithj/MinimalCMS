/**
 * MinimalCMS Markdown Editor (EasyMDE)
 *
 * Initialises EasyMDE on every <textarea class="mc-markdown-editor"> found
 * on the page. Instances are stored in window.mcEditors keyed by textarea id
 * so that other scripts (e.g. template-section toggles) can call refresh().
 *
 * Textareas with data-autosave="1" get the full toolbar and localStorage
 * auto-save. All others (e.g. template sections) get a compact toolbar.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */
( function () {
	'use strict';

	if ( typeof EasyMDE === 'undefined' ) {
		return;
	}

	window.mcEditors = window.mcEditors || {};

	const TOOLBAR_FULL = [
		'bold', 'italic', 'strikethrough', '|',
		'heading-1', 'heading-2', 'heading-3', '|',
		'unordered-list', 'ordered-list', 'checklist', '|',
		'link', 'image', 'table', 'horizontal-rule', '|',
		'code', 'quote', '|',
		'preview', 'side-by-side', 'fullscreen', '|',
		'undo', 'redo', '|',
		'guide'
	];

	const TOOLBAR_COMPACT = [
		'bold', 'italic', '|',
		'unordered-list', 'ordered-list', '|',
		'link', '|',
		'preview'
	];

	const form = document.querySelector( 'form[data-slug]' );

	document.querySelectorAll( 'textarea.mc-markdown-editor' ).forEach( function ( textarea ) {
		const isMain   = textarea.dataset.autosave === '1';
		const noToolbar = textarea.dataset.toolbar === 'none';

		const mde = new EasyMDE( {
			element:   textarea,
			spellChecker: false,
			autoDownloadFontAwesome: ! noToolbar,
			placeholder: textarea.placeholder || '',
			minHeight:  isMain ? '400px' : '180px',
			autofocus:  false,
			forceSync:  true,
			tabSize:    4,
			toolbar:    noToolbar ? false : ( isMain ? TOOLBAR_FULL : TOOLBAR_COMPACT ),
			status:     noToolbar ? false : ( isMain ? [ 'lines', 'words', 'cursor' ] : false ),
			renderingConfig: {
				singleLineBreaks: false,
				codeSyntaxHighlighting: false,
			},
		} );

		window.mcEditors[ textarea.id ] = mde;

		/* ── Auto-save draft to localStorage (main editors only) ──────── */
		if ( isMain && form ) {
			const draftKey = 'mc_draft_' + ( form.dataset.slug || 'new' );

			const saved = localStorage.getItem( draftKey );
			if ( saved && ! mde.value().trim() ) {
				mde.value( saved );
			}

			mde.codemirror.on( 'change', function () {
				localStorage.setItem( draftKey, mde.value() );
			} );

			form.addEventListener( 'submit', function () {
				localStorage.removeItem( draftKey );
			} );
		}
	} );

	/* ── Deferred refresh pass ─────────────────────────────────────────── */
	// Editors initialised inside hidden containers won't render correctly
	// until their container becomes visible. Refresh all instances once on
	// the next tick so that any that were hidden during init are corrected
	// when they become visible later (the toggle script calls refresh too).
	setTimeout( function () {
		Object.values( window.mcEditors ).forEach( function ( mde ) {
			mde.codemirror.refresh();
		} );
	}, 0 );
} )();
