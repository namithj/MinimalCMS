<?php
/**
 * MinimalCMS Fields API
 *
 * Provides a field type registry and helpers for rendering, sanitising,
 * and validating form fields in admin screens. Plugins and themes can
 * register custom field types via mc_register_field_type().
 *
 * @package MinimalCMS
 * @since   1.1.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * Registered field types keyed by type slug.
 *
 * Each entry: array{
 *   render_admin:  callable( array $field, mixed $value ): void,
 *   sanitize:      callable( mixed $value, array $field ): mixed,
 *   validate:      callable( mixed $value, array $field ): true|string,
 *   admin_assets:  array{ css?: string[], js?: string[] },
 * }
 *
 * @global array $mc_field_types
 */
global $mc_field_types;
$mc_field_types = array();

/*
 * -------------------------------------------------------------------------
 *  Field type registration
 * -------------------------------------------------------------------------
 */

/**
 * Register a field type.
 *
 * @since 1.1.0
 *
 * @param string $type Type slug (e.g. 'text', 'select', 'color_picker').
 * @param array  $args {
 *     Handler callbacks and metadata.
 *
 *     @type callable $render_admin  Required. Outputs the HTML for the field in an admin form.
 *                                    Receives ( array $field, mixed $value ).
 *     @type callable $sanitize      Optional. Cleans raw input. Receives ( mixed $value, array $field ).
 *                                    Default: mc_sanitize_text().
 *     @type callable $validate      Optional. Validates a value. Returns true or an error message string.
 *                                    Receives ( mixed $value, array $field ).
 *     @type array    $admin_assets  Optional. CSS/JS URLs to enqueue when this type is used.
 *                                    Keys: 'css' => string[], 'js' => string[].
 * }
 * @return true|MC_Error True on success.
 */
function mc_register_field_type( string $type, array $args ): true|MC_Error {

	global $mc_field_types;

	if ( '' === $type ) {
		return new MC_Error( 'invalid_field_type', 'Field type slug cannot be empty.' );
	}

	if ( ! isset( $args['render_admin'] ) || ! is_callable( $args['render_admin'] ) ) {
		return new MC_Error( 'missing_render', "Field type '{$type}' must provide a callable render_admin." );
	}

	$mc_field_types[ $type ] = array(
		'render_admin' => $args['render_admin'],
		'sanitize'     => isset( $args['sanitize'] ) && is_callable( $args['sanitize'] )
			? $args['sanitize']
			: 'mc_field_default_sanitize',
		'validate'     => isset( $args['validate'] ) && is_callable( $args['validate'] )
			? $args['validate']
			: 'mc_field_default_validate',
		'admin_assets' => $args['admin_assets'] ?? array(),
	);

	/**
	 * Fires after a field type is registered.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type The field type slug.
	 * @param array  $args The registered handler array.
	 */
	mc_do_action( 'mc_registered_field_type', $type, $mc_field_types[ $type ] );

	return true;
}

/**
 * Get a registered field type definition.
 *
 * @since 1.1.0
 *
 * @param string $type Type slug.
 * @return array|null Type definition or null.
 */
function mc_get_field_type( string $type ): ?array {

	global $mc_field_types;
	return $mc_field_types[ $type ] ?? null;
}

/**
 * Get all registered field types.
 *
 * @since 1.1.0
 *
 * @return array Associative array of type_slug => definition.
 */
function mc_get_field_types(): array {

	global $mc_field_types;
	return $mc_field_types;
}

/*
 * -------------------------------------------------------------------------
 *  Rendering
 * -------------------------------------------------------------------------
 */

/**
 * Render a single field's admin HTML.
 *
 * Wraps the field-type renderer with standard label, description, and
 * error markup so that all fields share a consistent DOM structure.
 *
 * @since 1.1.0
 *
 * @param array $field {
 *     Field definition.
 *
 *     @type string $id          Unique field identifier (used as name/id).
 *     @type string $type        Registered field type slug.
 *     @type string $label       Human-readable label.
 *     @type string $description Optional help text.
 *     @type mixed  $default     Default value.
 *     @type array  $options     Extra type-specific options (e.g. choices for select).
 *     @type array  $attributes  Extra HTML attributes for the input.
 *     @type bool   $required    Whether the field is required. Default false.
 * }
 * @param mixed        $value  Current value.
 * @param string|null  $error  Error message for this field, if any.
 * @return void Outputs HTML.
 */
function mc_render_field( array $field, mixed $value = null, ?string $error = null ): void {

	$type_def = mc_get_field_type( $field['type'] ?? 'text' );
	if ( null === $type_def ) {
		return;
	}

	$field = array_merge(
		array(
			'id'          => '',
			'type'        => 'text',
			'label'       => '',
			'description' => '',
			'default'     => '',
			'options'     => array(),
			'attributes'  => array(),
			'required'    => false,
		),
		$field
	);

	if ( null === $value ) {
		$value = $field['default'];
	}

	$field_id    = mc_esc_attr( $field['id'] );
	$has_error   = null !== $error && '' !== $error;
	$error_class = $has_error ? ' field-has-error' : '';

	echo '<div class="form-group mc-field' . $error_class . '" data-field-type="' . mc_esc_attr( $field['type'] ) . '">' . "\n";

	if ( '' !== $field['label'] && 'checkbox' !== $field['type'] ) {
		echo '<label for="field-' . $field_id . '">' . mc_esc_html( $field['label'] );
		if ( $field['required'] ) {
			echo ' <span class="required">*</span>';
		}
		echo '</label>' . "\n";
	}

	// Delegate to the type-specific renderer.
	call_user_func( $type_def['render_admin'], $field, $value );

	if ( $has_error ) {
		echo '<p class="field-error">' . mc_esc_html( $error ) . '</p>' . "\n";
	}

	if ( '' !== $field['description'] ) {
		echo '<p class="description">' . mc_esc_html( $field['description'] ) . '</p>' . "\n";
	}

	echo '</div>' . "\n";
}

/*
 * -------------------------------------------------------------------------
 *  Sanitisation & validation
 * -------------------------------------------------------------------------
 */

/**
 * Sanitise a field value through its registered sanitizer.
 *
 * @since 1.1.0
 *
 * @param string $type  Field type slug.
 * @param mixed  $value Raw input value.
 * @param array  $field Full field definition.
 * @return mixed Sanitised value.
 */
function mc_sanitize_field( string $type, mixed $value, array $field = array() ): mixed {

	$type_def = mc_get_field_type( $type );
	if ( null === $type_def ) {
		return mc_field_default_sanitize( $value, $field );
	}

	return call_user_func( $type_def['sanitize'], $value, $field );
}

/**
 * Validate a field value through its registered validator.
 *
 * @since 1.1.0
 *
 * @param string $type  Field type slug.
 * @param mixed  $value The value to validate (should already be sanitised).
 * @param array  $field Full field definition.
 * @return true|string True if valid, or an error message string.
 */
function mc_validate_field( string $type, mixed $value, array $field = array() ): true|string {

	// Check required first.
	if ( ! empty( $field['required'] ) && ( '' === $value || null === $value ) ) {
		$label = $field['label'] ?? $field['id'] ?? 'This field';
		return $label . ' is required.';
	}

	$type_def = mc_get_field_type( $type );
	if ( null === $type_def ) {
		return mc_field_default_validate( $value, $field );
	}

	return call_user_func( $type_def['validate'], $value, $field );
}

/**
 * Sanitise and validate a batch of fields, returning clean values and errors.
 *
 * @since 1.1.0
 *
 * @param array $fields Array of field definitions (keyed by field ID).
 * @param array $raw    Raw input values keyed by field ID.
 * @return array{values: array, errors: array} Clean values and field-keyed errors.
 */
function mc_process_fields( array $fields, array $raw ): array {

	$values = array();
	$errors = array();

	foreach ( $fields as $id => $field ) {
		$field['id'] = $id;
		$type        = $field['type'] ?? 'text';
		$raw_value   = $raw[ $id ] ?? ( $field['default'] ?? '' );

		// Checkboxes: absent from POST means unchecked.
		if ( 'checkbox' === $type && ! array_key_exists( $id, $raw ) ) {
			$raw_value = '';
		}

		$clean = mc_sanitize_field( $type, $raw_value, $field );
		$valid = mc_validate_field( $type, $clean, $field );

		$values[ $id ] = $clean;

		if ( true !== $valid ) {
			$errors[ $id ] = $valid;
		}
	}

	/**
	 * Filter processed field values before they are returned.
	 *
	 * @since 1.1.0
	 *
	 * @param array $values Sanitised field values.
	 * @param array $errors Validation errors.
	 * @param array $fields Field definitions.
	 */
	$values = mc_apply_filters( 'mc_process_fields_values', $values, $errors, $fields );

	return array(
		'values' => $values,
		'errors' => $errors,
	);
}

/*
 * -------------------------------------------------------------------------
 *  Default sanitizer / validator
 * -------------------------------------------------------------------------
 */

/**
 * Default sanitizer: treat as plain text.
 *
 * @since 1.1.0
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition (unused by default).
 * @return string
 */
function mc_field_default_sanitize( mixed $value, array $field = array() ): string {

	return mc_sanitize_text( (string) $value );
}

/**
 * Default validator: always passes.
 *
 * @since 1.1.0
 *
 * @param mixed $value The value.
 * @param array $field Field definition (unused by default).
 * @return true
 */
function mc_field_default_validate( mixed $value, array $field = array() ): true {

	return true;
}

/*
 * -------------------------------------------------------------------------
 *  Built-in field types
 * -------------------------------------------------------------------------
 */

/**
 * Register all core built-in field types.
 *
 * Called during bootstrap. Plugins can override or extend after this runs.
 *
 * @since 1.1.0
 *
 * @return void
 */
function mc_register_core_field_types(): void {

	/*
	 * Text field.
	 */
	mc_register_field_type(
		'text',
		array(
			'render_admin' => 'mc_render_field_text',
			'sanitize'     => 'mc_sanitize_field_text',
		)
	);

	/*
	 * Textarea field.
	 */
	mc_register_field_type(
		'textarea',
		array(
			'render_admin' => 'mc_render_field_textarea',
			'sanitize'     => 'mc_sanitize_field_textarea',
		)
	);

	/*
	 * Number field.
	 */
	mc_register_field_type(
		'number',
		array(
			'render_admin' => 'mc_render_field_number',
			'sanitize'     => 'mc_sanitize_field_number',
			'validate'     => 'mc_validate_field_number',
		)
	);

	/*
	 * URL field.
	 */
	mc_register_field_type(
		'url',
		array(
			'render_admin' => 'mc_render_field_url',
			'sanitize'     => 'mc_sanitize_field_url',
			'validate'     => 'mc_validate_field_url',
		)
	);

	/*
	 * Checkbox field.
	 */
	mc_register_field_type(
		'checkbox',
		array(
			'render_admin' => 'mc_render_field_checkbox',
			'sanitize'     => 'mc_sanitize_field_checkbox',
		)
	);

	/*
	 * Select field.
	 */
	mc_register_field_type(
		'select',
		array(
			'render_admin' => 'mc_render_field_select',
			'sanitize'     => 'mc_sanitize_field_select',
			'validate'     => 'mc_validate_field_select',
		)
	);

	/**
	 * Fires after core field types are registered.
	 *
	 * Plugins should use this hook to register additional field types.
	 *
	 * @since 1.1.0
	 */
	mc_do_action( 'mc_field_types_registered' );
}

/*
 * -------------------------------------------------------------------------
 *  Text field type
 * -------------------------------------------------------------------------
 */

/**
 * Render a text input.
 *
 * @param array  $field Field definition.
 * @param string $value Current value.
 */
function mc_render_field_text( array $field, mixed $value ): void {

	$attrs = mc_build_field_attributes( $field, array(
		'type'  => 'text',
		'value' => (string) $value,
	) );

	echo '<input ' . $attrs . '>' . "\n";
}

/**
 * Sanitise a text field value.
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return string
 */
function mc_sanitize_field_text( mixed $value, array $field = array() ): string {

	return mc_sanitize_text( (string) $value );
}

/*
 * -------------------------------------------------------------------------
 *  Textarea field type
 * -------------------------------------------------------------------------
 */

/**
 * Render a textarea.
 *
 * @param array  $field Field definition.
 * @param string $value Current value.
 */
function mc_render_field_textarea( array $field, mixed $value ): void {

	$rows = $field['options']['rows'] ?? 5;
	$attrs = mc_build_field_attributes( $field, array(
		'rows' => (int) $rows,
	), true );

	echo '<textarea ' . $attrs . '>' . mc_esc_textarea( (string) $value ) . '</textarea>' . "\n";
}

/**
 * Sanitise a textarea field value (preserve newlines).
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return string
 */
function mc_sanitize_field_textarea( mixed $value, array $field = array() ): string {

	$value = (string) $value;
	// Strip tags but preserve newlines.
	$value = strip_tags( $value );
	// Normalise line endings.
	$value = str_replace( "\r\n", "\n", $value );
	$value = str_replace( "\r", "\n", $value );

	return trim( $value );
}

/*
 * -------------------------------------------------------------------------
 *  Number field type
 * -------------------------------------------------------------------------
 */

/**
 * Render a number input.
 *
 * @param array $field Field definition.
 * @param mixed $value Current value.
 */
function mc_render_field_number( array $field, mixed $value ): void {

	$extra = array( 'type' => 'number', 'value' => (string) $value );

	if ( isset( $field['options']['min'] ) ) {
		$extra['min'] = (string) $field['options']['min'];
	}
	if ( isset( $field['options']['max'] ) ) {
		$extra['max'] = (string) $field['options']['max'];
	}
	if ( isset( $field['options']['step'] ) ) {
		$extra['step'] = (string) $field['options']['step'];
	}

	$attrs = mc_build_field_attributes( $field, $extra );

	echo '<input ' . $attrs . '>' . "\n";
}

/**
 * Sanitise a number field value.
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return int|float
 */
function mc_sanitize_field_number( mixed $value, array $field = array() ): int|float {

	if ( '' === $value || null === $value ) {
		return $field['default'] ?? 0;
	}

	$step = $field['options']['step'] ?? 1;

	// If step implies integers, cast to int.
	if ( is_int( $step ) || '1' === (string) $step ) {
		return (int) $value;
	}

	return (float) $value;
}

/**
 * Validate a number field value (min/max bounds).
 *
 * @param mixed $value Sanitised value.
 * @param array $field Field definition.
 * @return true|string
 */
function mc_validate_field_number( mixed $value, array $field = array() ): true|string {

	if ( '' === $value || null === $value ) {
		return true;
	}

	if ( ! is_numeric( $value ) ) {
		return ( $field['label'] ?? 'Value' ) . ' must be a number.';
	}

	if ( isset( $field['options']['min'] ) && $value < $field['options']['min'] ) {
		return ( $field['label'] ?? 'Value' ) . ' must be at least ' . $field['options']['min'] . '.';
	}

	if ( isset( $field['options']['max'] ) && $value > $field['options']['max'] ) {
		return ( $field['label'] ?? 'Value' ) . ' must be at most ' . $field['options']['max'] . '.';
	}

	return true;
}

/*
 * -------------------------------------------------------------------------
 *  URL field type
 * -------------------------------------------------------------------------
 */

/**
 * Render a URL input.
 *
 * @param array  $field Field definition.
 * @param string $value Current value.
 */
function mc_render_field_url( array $field, mixed $value ): void {

	$attrs = mc_build_field_attributes( $field, array(
		'type'  => 'url',
		'value' => (string) $value,
	) );

	echo '<input ' . $attrs . '>' . "\n";
}

/**
 * Sanitise a URL field value.
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return string
 */
function mc_sanitize_field_url( mixed $value, array $field = array() ): string {

	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	return filter_var( $value, FILTER_SANITIZE_URL ) ?: '';
}

/**
 * Validate a URL field value.
 *
 * @param mixed $value Sanitised value.
 * @param array $field Field definition.
 * @return true|string
 */
function mc_validate_field_url( mixed $value, array $field = array() ): true|string {

	if ( '' === $value || null === $value ) {
		return true;
	}

	if ( false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return ( $field['label'] ?? 'URL' ) . ' must be a valid URL.';
	}

	return true;
}

/*
 * -------------------------------------------------------------------------
 *  Checkbox field type
 * -------------------------------------------------------------------------
 */

/**
 * Render a checkbox input.
 *
 * @param array $field Field definition.
 * @param mixed $value Current value (truthy = checked).
 */
function mc_render_field_checkbox( array $field, mixed $value ): void {

	$checked = ! empty( $value ) && '0' !== $value ? ' checked' : '';
	$id      = mc_esc_attr( $field['id'] ?? '' );
	$name    = mc_esc_attr( $field['id'] ?? '' );

	echo '<label>' . "\n";
	echo '  <input type="hidden" name="' . $name . '" value="0">' . "\n";
	echo '  <input type="checkbox" id="field-' . $id . '" name="' . $name . '" value="1"' . $checked . '>' . "\n";
	if ( ! empty( $field['label'] ) ) {
		echo '  ' . mc_esc_html( $field['label'] ) . "\n";
	}
	echo '</label>' . "\n";
}

/**
 * Sanitise a checkbox field value.
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return bool
 */
function mc_sanitize_field_checkbox( mixed $value, array $field = array() ): bool {

	return ! empty( $value ) && '0' !== $value;
}

/*
 * -------------------------------------------------------------------------
 *  Select field type
 * -------------------------------------------------------------------------
 */

/**
 * Render a select dropdown.
 *
 * @param array  $field Field definition. $field['options']['choices'] is a
 *                      key => label array of option values.
 * @param string $value Current value.
 */
function mc_render_field_select( array $field, mixed $value ): void {

	$id      = mc_esc_attr( $field['id'] ?? '' );
	$name    = mc_esc_attr( $field['id'] ?? '' );
	$choices = $field['options']['choices'] ?? array();
	$style   = '';
	if ( ! empty( $field['attributes']['style'] ) ) {
		$style = ' style="' . mc_esc_attr( $field['attributes']['style'] ) . '"';
	}

	echo '<select id="field-' . $id . '" name="' . $name . '" class="form-control"' . $style . '>' . "\n";

	if ( ! empty( $field['options']['placeholder'] ) ) {
		echo '<option value="">' . mc_esc_html( $field['options']['placeholder'] ) . '</option>' . "\n";
	}

	foreach ( $choices as $opt_value => $opt_label ) {
		$selected = ( (string) $opt_value === (string) $value ) ? ' selected' : '';
		echo '<option value="' . mc_esc_attr( (string) $opt_value ) . '"' . $selected . '>' . mc_esc_html( $opt_label ) . '</option>' . "\n";
	}

	echo '</select>' . "\n";
}

/**
 * Sanitise a select field value (must be one of the allowed choices).
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return string
 */
function mc_sanitize_field_select( mixed $value, array $field = array() ): string {

	$value   = (string) $value;
	$choices = $field['options']['choices'] ?? array();

	if ( ! array_key_exists( $value, $choices ) && '' !== $value ) {
		return (string) ( $field['default'] ?? '' );
	}

	return $value;
}

/**
 * Validate a select field value.
 *
 * @param mixed $value Sanitised value.
 * @param array $field Field definition.
 * @return true|string
 */
function mc_validate_field_select( mixed $value, array $field = array() ): true|string {

	if ( '' === $value ) {
		return true;
	}

	$choices = $field['options']['choices'] ?? array();

	if ( ! array_key_exists( (string) $value, $choices ) ) {
		return ( $field['label'] ?? 'Selection' ) . ' is not a valid choice.';
	}

	return true;
}

/*
 * -------------------------------------------------------------------------
 *  Attribute builder helper
 * -------------------------------------------------------------------------
 */

/**
 * Build an HTML attribute string for a field input element.
 *
 * @since 1.1.0
 *
 * @param array $field      Field definition.
 * @param array $extra      Additional attributes to merge (e.g. type, value).
 * @param bool  $skip_value If true, omit the 'value' attribute (used for textarea).
 * @return string The HTML attributes string.
 */
function mc_build_field_attributes( array $field, array $extra = array(), bool $skip_value = false ): string {

	$id   = $field['id'] ?? '';
	$base = array(
		'id'    => 'field-' . $id,
		'name'  => $id,
		'class' => 'form-control',
	);

	$attrs = array_merge( $base, $extra, $field['attributes'] ?? array() );

	if ( $skip_value ) {
		unset( $attrs['value'] );
	}

	$parts = array();
	foreach ( $attrs as $key => $val ) {
		$parts[] = mc_esc_attr( $key ) . '="' . mc_esc_attr( (string) $val ) . '"';
	}

	return implode( ' ', $parts );
}

/*
 * -------------------------------------------------------------------------
 *  Admin asset helpers
 * -------------------------------------------------------------------------
 */

/**
 * Collect CSS/JS assets needed by the field types present on the current page.
 *
 * @since 1.1.0
 *
 * @param array $field_types Array of field type slugs used on the page.
 * @return array{ css: string[], js: string[] }
 */
function mc_get_field_type_assets( array $field_types ): array {

	$css = array();
	$js  = array();

	foreach ( array_unique( $field_types ) as $type ) {
		$type_def = mc_get_field_type( $type );
		if ( null === $type_def || empty( $type_def['admin_assets'] ) ) {
			continue;
		}

		if ( ! empty( $type_def['admin_assets']['css'] ) ) {
			$css = array_merge( $css, (array) $type_def['admin_assets']['css'] );
		}
		if ( ! empty( $type_def['admin_assets']['js'] ) ) {
			$js = array_merge( $js, (array) $type_def['admin_assets']['js'] );
		}
	}

	return array(
		'css' => array_unique( $css ),
		'js'  => array_unique( $js ),
	);
}
