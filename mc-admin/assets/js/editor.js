/**
 * MinimalCMS Markdown Editor (EasyMDE)
 *
 * Initialises a WYSIWYG Markdown editor with toolbar, preview,
 * keyboard shortcuts, and localStorage auto-save.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */
( function () {
	'use strict';

	const textarea = document.getElementById( 'editor-markdown' );
	if ( ! textarea || typeof EasyMDE === 'undefined' ) {
		return;
	}

	/* ── Initialise EasyMDE ───────────────────────────────────────────── */
	const easyMDE = new EasyMDE( {
		element: textarea,
		spellChecker: false,
		autoDownloadFontAwesome: true,
		placeholder: 'Write your content in Markdown…',
		minHeight: '400px',
		autofocus: false,
		status: [ 'lines', 'words', 'cursor' ],
		forceSync: true,       // Keep the hidden textarea in sync.
		tabSize: 4,
		toolbar: [
			'bold', 'italic', 'strikethrough', '|',
			'heading-1', 'heading-2', 'heading-3', '|',
			'unordered-list', 'ordered-list', 'checklist', '|',
			'link', 'image', 'table', 'horizontal-rule', '|',
			'code', 'quote', '|',
			'preview', 'side-by-side', 'fullscreen', '|',
			'undo', 'redo', '|',
			'guide'
		],
		renderingConfig: {
			singleLineBreaks: false,
			codeSyntaxHighlighting: false
		}
	} );

	/* ── Auto-save draft to localStorage ──────────────────────────────── */
	const form = textarea.closest( 'form' );
	if ( form ) {
		const draftKey = 'mc_draft_' + ( form.dataset.slug || 'new' );

		// Restore draft only if the editor is empty (new content).
		const saved = localStorage.getItem( draftKey );
		if ( saved && ! easyMDE.value().trim() ) {
			easyMDE.value( saved );
		}

		// Save on change.
		easyMDE.codemirror.on( 'change', function () {
			localStorage.setItem( draftKey, easyMDE.value() );
		} );

		// Clear on submit.
		form.addEventListener( 'submit', function () {
			localStorage.removeItem( draftKey );
		} );
	}
} )();
