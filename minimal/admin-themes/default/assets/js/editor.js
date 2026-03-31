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

		function toolbarButton( name, title, action, iconClass ) {
		return {
			name: name,
			title: title,
			action: action,
						className: 'mc-easymde-icon ' + iconClass,
		};
	}

		const TOOLBAR_FULL = [
				toolbarButton( 'bold', 'Bold', EasyMDE.toggleBold, 'mc-icon-bold' ),
				toolbarButton( 'italic', 'Italic', EasyMDE.toggleItalic, 'mc-icon-italic' ),
				toolbarButton( 'strikethrough', 'Strikethrough', EasyMDE.toggleStrikethrough, 'mc-icon-strikethrough' ),
		'|',
				toolbarButton( 'heading-1', 'Heading 1', EasyMDE.toggleHeading1, 'mc-icon-h1' ),
				toolbarButton( 'heading-2', 'Heading 2', EasyMDE.toggleHeading2, 'mc-icon-h2' ),
				toolbarButton( 'heading-3', 'Heading 3', EasyMDE.toggleHeading3, 'mc-icon-h3' ),
		'|',
				toolbarButton( 'unordered-list', 'Bullet list', EasyMDE.toggleUnorderedList, 'mc-icon-ul' ),
				toolbarButton( 'ordered-list', 'Numbered list', EasyMDE.toggleOrderedList, 'mc-icon-ol' ),
		'|',
				toolbarButton( 'link', 'Insert link', EasyMDE.drawLink, 'mc-icon-link' ),
				toolbarButton( 'image', 'Insert image', EasyMDE.drawImage, 'mc-icon-image' ),
				toolbarButton( 'table', 'Insert table', EasyMDE.drawTable, 'mc-icon-table' ),
				toolbarButton( 'horizontal-rule', 'Horizontal rule', EasyMDE.drawHorizontalRule, 'mc-icon-hr' ),
		'|',
				toolbarButton( 'code', 'Code block', EasyMDE.toggleCodeBlock, 'mc-icon-code' ),
				toolbarButton( 'quote', 'Blockquote', EasyMDE.toggleBlockquote, 'mc-icon-quote' ),
		'|',
				toolbarButton( 'undo', 'Undo', EasyMDE.undo, 'mc-icon-undo' ),
				toolbarButton( 'redo', 'Redo', EasyMDE.redo, 'mc-icon-redo' ),
		'|',
				toolbarButton( 'guide', 'Markdown guide', 'https://www.markdownguide.org/basic-syntax/', 'mc-icon-guide' ),
	];

		const TOOLBAR_COMPACT = [
				toolbarButton( 'bold', 'Bold', EasyMDE.toggleBold, 'mc-icon-bold' ),
				toolbarButton( 'italic', 'Italic', EasyMDE.toggleItalic, 'mc-icon-italic' ),
		'|',
				toolbarButton( 'unordered-list', 'Bullet list', EasyMDE.toggleUnorderedList, 'mc-icon-ul' ),
				toolbarButton( 'ordered-list', 'Numbered list', EasyMDE.toggleOrderedList, 'mc-icon-ol' ),
		'|',
				toolbarButton( 'link', 'Insert link', EasyMDE.drawLink, 'mc-icon-link' ),
	];

	const form = document.querySelector( 'form[data-slug]' );

	document.querySelectorAll( 'textarea.mc-markdown-editor' ).forEach( function ( textarea ) {
		const isMain   = textarea.dataset.autosave === '1';
		const noToolbar = textarea.dataset.toolbar === 'none';

		const mde = new EasyMDE( {
			element:   textarea,
			spellChecker: false,
			autoDownloadFontAwesome: false,
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
