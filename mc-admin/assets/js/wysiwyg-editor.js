/**
 * MinimalCMS WYSIWYG Editor (Squire)
 *
 * Initialises Squire on every <div class="mc-wysiwyg-editor"> found on the
 * page with a custom toolbar. Each instance syncs its HTML back to a sibling
 * hidden <input> so the content is included in the form POST.
 *
 * Editors with data-autosave="1" get localStorage draft auto-save.
 * Instances are stored in window.mcEditors keyed by the hidden input id.
 *
 * The editor preserves raw HTML faithfully: hiddenInput.value is the canonical
 * source. Squire only receives content via setHTML and returns it via getHTML
 * during visual mode; the HTML source textarea provides direct editing.
 *
 * @package MinimalCMS
 * @since   {version}
 */
( function () {
	'use strict';

	if ( typeof Squire === 'undefined' ) {
		return;
	}

	window.mcEditors = window.mcEditors || {};

	var form = document.querySelector( 'form[data-slug]' );

	/**
	 * Toolbar button definitions.
	 *
	 * Each group is an array of { label, title, action, tag? } objects.
	 * - label:  button innerHTML (supports HTML entities).
	 * - title:  tooltip text.
	 * - action: function( editor ) called on click.
	 * - tag:    optional — active-state is toggled when this tag appears in the
	 *           path returned by editor.getPath().
	 */
	var toolbarGroups = [
		[
			{ label: '<b>B</b>',  title: 'Bold',          action: function ( e ) { e.bold(); },          tag: 'B' },
			{ label: '<i>I</i>',  title: 'Italic',        action: function ( e ) { e.italic(); },        tag: 'I' },
			{ label: '<u>U</u>',  title: 'Underline',     action: function ( e ) { e.underline(); },     tag: 'U' },
			{ label: '<s>S</s>',  title: 'Strikethrough',  action: function ( e ) { e.strikethrough(); }, tag: 'S' }
		],
		[
			{ label: 'H1', title: 'Heading 1', action: function ( e ) { toggleHeading( e, 'H1' ); }, tag: 'H1' },
			{ label: 'H2', title: 'Heading 2', action: function ( e ) { toggleHeading( e, 'H2' ); }, tag: 'H2' },
			{ label: 'H3', title: 'Heading 3', action: function ( e ) { toggleHeading( e, 'H3' ); }, tag: 'H3' }
		],
		[
			{ label: 'OL', title: 'Ordered list',   action: function ( e ) { e.makeOrderedList(); },   tag: 'OL' },
			{ label: 'UL', title: 'Unordered list',  action: function ( e ) { e.makeUnorderedList(); }, tag: 'UL' },
			{ label: 'BQ', title: 'Blockquote',      action: function ( e ) { toggleBlockquote( e ); }, tag: 'BLOCKQUOTE' }
		],
		[
			{ label: '&#128279;', title: 'Insert link',     action: insertLink },
			{ label: '&#128247;', title: 'Insert image',    action: insertImage },
			{ label: '&#8212;',   title: 'Horizontal rule', action: function ( e ) { e.insertHTML( '<hr>' ); } }
		],
		[
			{ label: '&#8617;', title: 'Undo', action: function ( e ) { e.undo(); } },
			{ label: '&#8618;', title: 'Redo', action: function ( e ) { e.redo(); } }
		],
		[
			{ label: 'T&#8336;', title: 'Remove formatting', action: function ( e ) { e.removeAllFormatting(); } }
		]
	];

	/** Toggle a heading tag — remove it if already active, else apply. */
	function toggleHeading( editor, tag ) {
		var path = editor.getPath();
		if ( path.indexOf( tag ) !== -1 ) {
			editor.modifyBlocks( function ( frag ) {
				var doc   = frag.ownerDocument;
				var out   = doc.createDocumentFragment();
				Array.prototype.forEach.call( frag.childNodes, function ( node ) {
					if ( node.nodeName === tag ) {
						var p = doc.createElement( 'P' );
						while ( node.firstChild ) {
							p.appendChild( node.firstChild );
						}
						out.appendChild( p );
					} else {
						out.appendChild( node );
					}
				} );
				return out;
			} );
		} else {
			editor.modifyBlocks( function ( frag ) {
				var doc   = frag.ownerDocument;
				var out   = doc.createDocumentFragment();
				Array.prototype.forEach.call( frag.childNodes, function ( node ) {
					var h = doc.createElement( tag );
					while ( node.firstChild ) {
						h.appendChild( node.firstChild );
					}
					out.appendChild( h );
				} );
				return out;
			} );
		}
	}

	/** Toggle blockquote — decrease if already inside one, else increase. */
	function toggleBlockquote( editor ) {
		if ( editor.getPath().indexOf( 'BLOCKQUOTE' ) !== -1 ) {
			editor.decreaseQuoteLevel();
		} else {
			editor.increaseQuoteLevel();
		}
	}

	function insertLink( editor ) {
		var url = prompt( 'Enter URL:' );
		if ( url ) {
			editor.makeLink( url );
		}
	}

	function insertImage( editor ) {
		var src = prompt( 'Enter image URL:' );
		if ( src ) {
			editor.insertImage( src, { alt: '' } );
		}
	}

	/**
	 * Build toolbar DOM inside the designated toolbar container.
	 *
	 * @param {HTMLElement} toolbarEl  The empty toolbar div.
	 * @param {Squire}      editor     Squire instance.
	 * @return {HTMLElement[]}         All created button elements (for path updates).
	 */
	function buildToolbar( toolbarEl, editor ) {
		var buttons = [];

		toolbarGroups.forEach( function ( group, gi ) {
			if ( gi > 0 ) {
				var sep = document.createElement( 'span' );
				sep.className = 'mc-toolbar-sep';
				toolbarEl.appendChild( sep );
			}

			group.forEach( function ( btn ) {
				var button        = document.createElement( 'button' );
				button.type       = 'button';
				button.className  = 'mc-toolbar-btn';
				button.innerHTML  = btn.label;
				button.title      = btn.title;
				button._tag       = btn.tag || null;
				button.addEventListener( 'click', function ( ev ) {
					ev.preventDefault();
					btn.action( editor );
					editor.focus();
				} );
				toolbarEl.appendChild( button );
				buttons.push( button );
			} );
		} );

		return buttons;
	}

	/**
	 * Update active states on toolbar buttons based on the editor path.
	 */
	function updateToolbarState( buttons, path ) {
		buttons.forEach( function ( btn ) {
			if ( btn._tag ) {
				var re = new RegExp( '(?:>|^)' + btn._tag + '(?:>|$|\\.)' );
				if ( re.test( path ) ) {
					btn.classList.add( 'active' );
				} else {
					btn.classList.remove( 'active' );
				}
			}
		} );
	}

	/**
	 * Parse an HTML string → DocumentFragment without DOMPurify.
	 * Server-side sanitize_html() handles security.
	 */
	function htmlToFragment( html ) {
		var doc  = new DOMParser().parseFromString( html, 'text/html' );
		var frag = document.createDocumentFragment();
		var body = doc.body;
		var child;
		while ( ( child = body.firstChild ) ) {
			frag.appendChild( document.adoptNode( child ) );
		}
		return frag;
	}

	// ── Initialise each editor instance ──────────────────────────────────────

	document.querySelectorAll( '.mc-wysiwyg-editor' ).forEach( function ( container ) {
		var wrap       = container.closest( '.mc-wysiwyg-wrap' );
		var toolbarEl  = wrap ? wrap.querySelector( '.mc-wysiwyg-toolbar' ) : null;
		var hiddenInput = wrap ? wrap.querySelector( 'input[type="hidden"]' ) : container.nextElementSibling;

		if ( ! hiddenInput || hiddenInput.tagName !== 'INPUT' ) {
			return;
		}

		var inputId   = hiddenInput.id;
		var isMain    = container.dataset.autosave === '1';
		var minHeight = container.dataset.minHeight || '384px';

		container.style.minHeight = minHeight;

		// Create Squire editor.
		var editor = new Squire( container, {
			blockTag: 'P',
			sanitizeToDOMFragment: htmlToFragment
		} );

		// ── HTML source textarea ─────────────────────────────────────────────
		var sourceWrap = document.createElement( 'div' );
		sourceWrap.className = 'mc-source-wrap';
		sourceWrap.style.minHeight = minHeight;

		var sourceEl = document.createElement( 'textarea' );
		sourceEl.className = 'mc-wysiwyg-source';
		sourceEl.spellcheck = false;
		sourceEl.setAttribute( 'autocomplete', 'off' );
		sourceEl.setAttribute( 'autocorrect', 'off' );
		sourceEl.setAttribute( 'autocapitalize', 'off' );

		sourceWrap.appendChild( sourceEl );
		container.parentNode.insertBefore( sourceWrap, container.nextSibling );

		/** Auto-grow the textarea to fit its content. */
		function autoResize() {
			sourceEl.style.height = 'auto';
			sourceEl.style.height = sourceEl.scrollHeight + 'px';
		}

		var isSourceMode = false;

		// Build toolbar + append the HTML toggle button.
		var buttons = [];
		if ( toolbarEl ) {
			buttons = buildToolbar( toolbarEl, editor );

			// Formatting commands may not fire Squire's 'input' event, so
			// sync the hidden input after every toolbar button click.
			buttons.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					hiddenInput.value = editor.getHTML();
				} );
			} );

			// Separator before the HTML toggle.
			var sep = document.createElement( 'span' );
			sep.className = 'mc-toolbar-sep';
			toolbarEl.appendChild( sep );

			var htmlBtn       = document.createElement( 'button' );
			htmlBtn.type      = 'button';
			htmlBtn.className = 'mc-toolbar-btn mc-toolbar-btn-html';
			htmlBtn.innerHTML = '&lt;/&gt;';
			htmlBtn.title     = 'HTML source';
			toolbarEl.appendChild( htmlBtn );

			htmlBtn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				toggleSourceMode();
			} );
		}

		/** Switch between visual and HTML source views. */
		function toggleSourceMode() {
			if ( isSourceMode ) {
				// Source → Visual: push textarea value into Squire.
				hiddenInput.value = sourceEl.value;
				editor.setHTML( hiddenInput.value );
				sourceWrap.style.display = 'none';
				container.style.display  = 'block';
				htmlBtn.classList.remove( 'active' );
				buttons.forEach( function ( b ) { b.disabled = false; } );
			} else {
				// Visual → Source: read from hiddenInput (canonical).
				sourceEl.value = hiddenInput.value;
				container.style.display  = 'none';
				sourceWrap.style.display = 'block';
				autoResize();
				htmlBtn.classList.add( 'active' );
				sourceEl.focus();
				buttons.forEach( function ( b ) { b.disabled = true; } );
			}
			isSourceMode = ! isSourceMode;
		}

		// Sync source textarea → hidden input on typing.
		sourceEl.addEventListener( 'input', function () {
			hiddenInput.value = sourceEl.value;
			autoResize();

			if ( isMain && form ) {
				var draftKey = 'mc_draft_' + ( form.dataset.slug || 'new' );
				localStorage.setItem( draftKey, hiddenInput.value );
			}
		} );

		// Sync Squire content → hidden input on every change.
		editor.addEventListener( 'input', function () {
			if ( ! isSourceMode ) {
				hiddenInput.value = editor.getHTML();

				if ( isMain && form ) {
					var draftKey = 'mc_draft_' + ( form.dataset.slug || 'new' );
					localStorage.setItem( draftKey, hiddenInput.value );
				}
			}
		} );

		// Update active toolbar states on cursor movement.
		editor.addEventListener( 'pathChange', function ( e ) {
			if ( buttons.length && ! isSourceMode ) {
				var detail = e.detail || e;
				updateToolbarState( buttons, detail.path || '' );
			}
		} );

		// Load existing value into the editor (hiddenInput is canonical).
		if ( hiddenInput.value ) {
			editor.setHTML( hiddenInput.value );
		}

		// Restore auto-saved draft if main editor is empty.
		if ( isMain && form ) {
			var draftKey = 'mc_draft_' + ( form.dataset.slug || 'new' );
			var saved    = localStorage.getItem( draftKey );

			if ( saved && ! hiddenInput.value.trim() ) {
				editor.setHTML( saved );
				hiddenInput.value = saved;
			}

			form.addEventListener( 'submit', function () {
				localStorage.removeItem( draftKey );
			} );
		}

		// Store reference so other scripts can interact.
		window.mcEditors[ inputId ] = {
			editor:  editor,
			getValue: function () {
				return hiddenInput.value;
			},
			setValue: function ( html ) {
				hiddenInput.value = html;
				if ( isSourceMode ) {
					sourceEl.value = html;
				} else {
					editor.setHTML( html );
				}
			}
		};
	} );
} )();
