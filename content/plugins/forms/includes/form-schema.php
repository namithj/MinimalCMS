<?php

/**
 * Forms Plugin — Schema Normalization
 *
 * Defines the canonical schema for form definitions stored in content meta,
 * normalization helpers, and runtime field-map builders for mc_process_fields().
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Allowed form element types for the builder.
 *
 * @since 1.0.0
 *
 * @return array<string, string> type_slug => label
 */
function forms_get_allowed_field_types(): array
{

	$types = array(
		'text'     => 'Text',
		'email'    => 'Email',
		'textarea' => 'Textarea',
		'number'   => 'Number',
		'url'      => 'URL',
		'select'   => 'Dropdown',
		'checkbox' => 'Checkbox',
		'hidden'   => 'Hidden',
	);

	return mc_apply_filters('forms_allowed_field_types', $types);
}

/**
 * Default structure for a single form field element.
 *
 * @since 1.0.0
 *
 * @return array
 */
function forms_field_defaults(): array
{

	return array(
		'name'        => '',
		'type'        => 'text',
		'label'       => '',
		'placeholder' => '',
		'required'    => false,
		'options'     => array(),
	);
}

/**
 * Default structure for form notification settings.
 *
 * @since 1.0.0
 *
 * @return array
 */
function forms_notification_defaults(): array
{

	return array(
		'enabled'        => true,
		'to'             => '',
		'subject'        => 'New Form Submission: {form_title}',
		'from_name'      => '',
		'from_email'     => '',
		'reply_to_field' => '',
	);
}

/**
 * Default structure for form confirmation settings.
 *
 * @since 1.0.0
 *
 * @return array
 */
function forms_confirmation_defaults(): array
{

	return array(
		'type'     => 'message',
		'message'  => 'Thank you! Your submission has been received.',
		'redirect' => '',
		'page'     => '',
	);
}

/**
 * Normalize a full form meta array, filling in defaults.
 *
 * @since 1.0.0
 *
 * @param array $meta Raw form meta from content storage.
 * @return array Normalized meta.
 */
function forms_normalize_meta(array $meta): array
{

	$meta = array_merge(
		array(
			'fields'        => array(),
			'notifications' => array(),
			'confirmation'  => array(),
		),
		$meta
	);

	// Normalize each field element.
	$meta['fields'] = array_values(array_map('forms_normalize_field', $meta['fields']));

	// Normalize notifications: ensure array-of-arrays.
	if (! empty($meta['notifications']) && ! isset($meta['notifications'][0])) {
		$meta['notifications'] = array( $meta['notifications'] );
	}
	$meta['notifications'] = array_map(
		fn(array $n) => array_merge(forms_notification_defaults(), $n),
		$meta['notifications']
	);

	// Normalize confirmation.
	$meta['confirmation'] = array_merge(forms_confirmation_defaults(), $meta['confirmation']);

	return $meta;
}

/**
 * Normalize a single field element definition.
 *
 * @since 1.0.0
 *
 * @param array $field Raw field definition.
 * @return array Normalized definition.
 */
function forms_normalize_field(array $field): array
{

	$field = array_merge(forms_field_defaults(), $field);

	$allowed = forms_get_allowed_field_types();
	if (! isset($allowed[ $field['type'] ])) {
		$field['type'] = 'text';
	}

	$field['name']     = mc_sanitize_slug($field['name']);
	$field['required'] = (bool) $field['required'];

	return $field;
}

/**
 * Build a Fields API compatible field-definitions array from form schema.
 *
 * Used at runtime to validate frontend submissions via mc_process_fields().
 *
 * @since 1.0.0
 *
 * @param array $form_fields Array of normalized form field definitions.
 * @return array Keyed by field name, compatible with mc_process_fields().
 */
function forms_build_runtime_fields(array $form_fields): array
{

	$runtime = array();

	foreach ($form_fields as $field) {
		if ('' === $field['name']) {
			continue;
		}

		$def = array(
			'id'       => $field['name'],
			'type'     => $field['type'],
			'label'    => '' !== $field['label'] ? $field['label'] : $field['name'],
			'required' => $field['required'],
			'default'  => '',
			'options'  => array(),
		);

		// Map select choices into Fields API format.
		if ('select' === $field['type'] && ! empty($field['options'])) {
			$choices = array();
			foreach ($field['options'] as $opt) {
				$key             = is_array($opt) ? ( $opt['value'] ?? '' ) : (string) $opt;
				$label           = is_array($opt) ? ( $opt['label'] ?? $key ) : (string) $opt;
				$choices[ $key ] = $label;
			}
			$def['options']['choices'] = $choices;
		}

		$runtime[ $field['name'] ] = $def;
	}

	return $runtime;
}

/**
 * Validate a form definition and return errors.
 *
 * @since 1.0.0
 *
 * @param array $meta Normalized form meta.
 * @return array Array of error messages (empty if valid).
 */
function forms_validate_definition(array $meta): array
{

	$errors = array();

	if (empty($meta['fields'])) {
		$errors[] = 'A form must have at least one field.';
	}

	$names = array();
	foreach ($meta['fields'] as $i => $field) {
		if ('' === $field['name']) {
			$errors[] = 'Field #' . ( $i + 1 ) . ' is missing a name.';
			continue;
		}

		if ('' === $field['label'] && 'hidden' !== $field['type']) {
			$errors[] = 'Field "' . $field['name'] . '" is missing a label.';
		}

		if (in_array($field['name'], $names, true)) {
			$errors[] = 'Duplicate field name: "' . $field['name'] . '".';
		}
		$names[] = $field['name'];
	}

	// Validate confirmation type.
	$valid_confirmations = array( 'message', 'redirect', 'page' );
	if (! in_array($meta['confirmation']['type'] ?? '', $valid_confirmations, true)) {
		$errors[] = 'Invalid confirmation type.';
	}

	return $errors;
}
