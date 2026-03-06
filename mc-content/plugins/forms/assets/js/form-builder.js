/**
 * Forms Plugin — Form Builder JS
 *
 * Provides the field repeater UI, JSON source mode toggle with
 * bidirectional sync, and confirmation type switching.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */
( function () {
	'use strict';

	/* ── Bootstrap ────────────────────────────────────────────────────── */
	const container   = document.getElementById( 'forms-builder-container' );
	const repeater    = document.getElementById( 'forms-fields-repeater' );
	const jsonHidden  = document.getElementById( 'form-fields-json' );
	const jsonEditor  = document.getElementById( 'form-fields-json-editor' );
	const sourceWrap  = document.getElementById( 'forms-fields-source' );
	const toggleBtn   = document.getElementById( 'forms-toggle-source' );
	const addFieldBtn = document.getElementById( 'forms-add-field' );

	if ( ! container || ! repeater || ! jsonHidden ) {
		return;
	}

	const config = window.FormsConfig || {};
	const rawTypes = config.fieldTypes || {};
	// fieldTypes is an object (slug => label); build an array of {value, label} pairs.
	const FIELD_TYPES = Array.isArray( rawTypes )
		? rawTypes.map( function ( t ) { return { value: t, label: t.charAt( 0 ).toUpperCase() + t.slice( 1 ) }; } )
		: Object.keys( rawTypes ).map( function ( k ) { return { value: k, label: rawTypes[ k ] }; } );

	/* ── Field data ───────────────────────────────────────────────────── */
	let fields = [];
	try {
		fields = JSON.parse( jsonHidden.value || '[]' );
	} catch ( e ) {
		fields = [];
	}
	if ( ! Array.isArray( fields ) ) {
		fields = [];
	}

	/* ── Render repeater rows ─────────────────────────────────────────── */
	function renderRepeater() {
		repeater.innerHTML = '';

		if ( fields.length === 0 ) {
			repeater.innerHTML = '<div id="forms-fields-empty">No fields yet. Click <strong>+ Add Field</strong> below.</div>';
			syncToJSON();
			return;
		}

		fields.forEach( function ( field, index ) {
			const row = buildRow( field, index );
			repeater.appendChild( row );
		} );

		syncToJSON();
	}

	function buildRow( field, index ) {
		const row = document.createElement( 'div' );
		row.className = 'forms-field-row';
		row.dataset.index = index;

		// Type cell.
		const typeCell = cell( 'type', 'Type' );
		const typeSelect = document.createElement( 'select' );
		typeSelect.name = '__field_type_' + index;
		FIELD_TYPES.forEach( function ( t ) {
			const opt = document.createElement( 'option' );
			opt.value = t.value;
			opt.textContent = t.label;
			if ( t.value === field.type ) {
				opt.selected = true;
			}
			typeSelect.appendChild( opt );
		} );
		typeSelect.addEventListener( 'change', function () {
			fields[ index ].type = typeSelect.value;
			renderRepeater();
		} );
		typeCell.appendChild( typeSelect );
		row.appendChild( typeCell );

		// Label cell.
		const labelCell = cell( 'label', 'Label' );
		const labelInput = textInput( field.label || '', function ( val ) {
			fields[ index ].label = val;
			syncToJSON();
		} );
		labelCell.appendChild( labelInput );
		row.appendChild( labelCell );

		// Name cell.
		const nameCell = cell( 'name', 'Name' );
		const nameInput = textInput( field.name || '', function ( val ) {
			fields[ index ].name = val;
			syncToJSON();
		} );
		nameInput.placeholder = 'field_name';
		nameCell.appendChild( nameInput );
		row.appendChild( nameCell );

		// Placeholder cell (not for checkbox/select/hidden).
		if ( ! [ 'checkbox', 'select', 'hidden' ].includes( field.type ) ) {
			const phCell = cell( 'placeholder', 'Placeholder' );
			const phInput = textInput( field.placeholder || '', function ( val ) {
				fields[ index ].placeholder = val;
				syncToJSON();
			} );
			phCell.appendChild( phInput );
			row.appendChild( phCell );
		}

		// Required checkbox.
		const reqCell = cell( 'required', 'Req.' );
		reqCell.classList.add( 'field-cell--required' );
		const reqCheck = document.createElement( 'input' );
		reqCheck.type = 'checkbox';
		reqCheck.checked = !! field.required;
		reqCheck.addEventListener( 'change', function () {
			fields[ index ].required = reqCheck.checked;
			syncToJSON();
		} );
		reqCell.appendChild( reqCheck );
		row.appendChild( reqCell );

		// Action buttons.
		const actCell = document.createElement( 'div' );
		actCell.className = 'field-cell field-cell--actions';

		if ( index > 0 ) {
			actCell.appendChild( iconBtn( '↑', 'Move up', function () {
				swapFields( index, index - 1 );
			} ) );
		}
		if ( index < fields.length - 1 ) {
			actCell.appendChild( iconBtn( '↓', 'Move down', function () {
				swapFields( index, index + 1 );
			} ) );
		}
		actCell.appendChild( iconBtn( '×', 'Remove', function () {
			fields.splice( index, 1 );
			renderRepeater();
		}, true ) );

		row.appendChild( actCell );

		// Options sub-editor for select type.
		if ( field.type === 'select' ) {
			const optionsWrap = document.createElement( 'div' );
			optionsWrap.className = 'field-cell';
			optionsWrap.style.flex = '1 0 100%';

			const optLabel = document.createElement( 'span' );
			optLabel.className = 'cell-label';
			optLabel.textContent = 'Options (one per line, value|Label)';
			optionsWrap.appendChild( optLabel );

			const optArea = document.createElement( 'textarea' );
			optArea.rows = 3;
			optArea.style.width = '100%';
			optArea.style.fontSize = '0.85rem';
			optArea.style.fontFamily = 'monospace';
			optArea.value = ( field.options || [] ).map( function ( o ) {
				if ( typeof o === 'object' ) {
					return ( o.value || '' ) + '|' + ( o.label || '' );
				}
				return String( o );
			} ).join( '\n' );

			optArea.addEventListener( 'input', function () {
				fields[ index ].options = optArea.value.split( '\n' )
					.filter( function ( l ) { return l.trim() !== ''; } )
					.map( function ( l ) {
						var parts = l.split( '|' );
						return parts.length > 1
							? { value: parts[ 0 ].trim(), label: parts.slice( 1 ).join( '|' ).trim() }
							: parts[ 0 ].trim();
					} );
				syncToJSON();
			} );

			optionsWrap.appendChild( optArea );
			row.appendChild( optionsWrap );
		}

		return row;
	}

	/* ── Helpers ──────────────────────────────────────────────────────── */
	function cell( cls, labelText ) {
		const el = document.createElement( 'div' );
		el.className = 'field-cell field-cell--' + cls;
		const lbl = document.createElement( 'span' );
		lbl.className = 'cell-label';
		lbl.textContent = labelText;
		el.appendChild( lbl );
		return el;
	}

	function textInput( value, onChange ) {
		const inp = document.createElement( 'input' );
		inp.type = 'text';
		inp.value = value;
		inp.addEventListener( 'input', function () {
			onChange( inp.value );
		} );
		return inp;
	}

	function iconBtn( symbol, title, onClick, danger ) {
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'btn-icon' + ( danger ? ' btn-icon--danger' : '' );
		btn.title = title;
		btn.textContent = symbol;
		btn.addEventListener( 'click', onClick );
		return btn;
	}

	function swapFields( a, b ) {
		var tmp = fields[ a ];
		fields[ a ] = fields[ b ];
		fields[ b ] = tmp;
		renderRepeater();
	}

	/* ── JSON sync ────────────────────────────────────────────────────── */
	function syncToJSON() {
		var json = JSON.stringify( fields, null, 2 );
		jsonHidden.value = json;
		if ( jsonEditor && jsonEditor !== document.activeElement ) {
			jsonEditor.value = json;
		}
	}

	function syncFromJSON() {
		try {
			var parsed = JSON.parse( jsonEditor.value );
			if ( Array.isArray( parsed ) ) {
				fields = parsed;
				renderRepeater();
			}
		} catch ( e ) {
			// invalid JSON — don't sync.
		}
	}

	/* ── Source mode toggle ───────────────────────────────────────────── */
	var sourceMode = false;

	if ( toggleBtn ) {
		toggleBtn.addEventListener( 'click', function () {
			sourceMode = ! sourceMode;
			repeater.style.display = sourceMode ? 'none' : '';
			sourceWrap.style.display = sourceMode ? '' : 'none';
			addFieldBtn.style.display = sourceMode ? 'none' : '';
			toggleBtn.textContent = sourceMode ? 'Visual Builder' : 'JSON Source';

			if ( ! sourceMode ) {
				syncFromJSON();
			} else {
				syncToJSON();
			}
		} );
	}

	if ( jsonEditor ) {
		jsonEditor.addEventListener( 'blur', function () {
			syncFromJSON();
		} );
	}

	/* ── Add field ────────────────────────────────────────────────────── */
	if ( addFieldBtn ) {
		addFieldBtn.addEventListener( 'click', function () {
			fields.push( {
				type: 'text',
				name: 'field_' + ( fields.length + 1 ),
				label: 'Field ' + ( fields.length + 1 ),
				placeholder: '',
				required: false,
				options: []
			} );
			renderRepeater();
		} );
	}

	/* ── Confirmation type switcher ───────────────────────────────────── */
	const confirmType = document.getElementById( 'confirm-type' );
	const confirmOpts = document.querySelectorAll( '.forms-confirm-opt' );

	function toggleConfirmOpts() {
		if ( ! confirmType ) {
			return;
		}
		var selected = confirmType.value;
		confirmOpts.forEach( function ( el ) {
			if ( el.dataset.showFor === selected ) {
				el.classList.add( 'active' );
			} else {
				el.classList.remove( 'active' );
			}
		} );
	}

	if ( confirmType ) {
		confirmType.addEventListener( 'change', toggleConfirmOpts );
		toggleConfirmOpts();
	}

	/* ── Init ─────────────────────────────────────────────────────────── */
	renderRepeater();

} )();
