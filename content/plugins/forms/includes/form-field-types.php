<?php

/**
 * Forms Plugin — Custom Field Types (Fields API)
 *
 * Registers form-domain-specific field types through the Fields API.
 * These are used both for admin settings rendering and for extending
 * the available types for form element validation.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Register custom field types for the forms domain.
 *
 * Called from the mc_register_settings hook in the main plugin file.
 *
 * @since 1.0.0
 * @return void
 */
function forms_register_field_types(): void
{
	// Email field type (for admin settings and form builder).
	// Renders an email input, sanitizes via mc_sanitize_email,
	// validates with filter_var.
	if (null === mc_get_field_type('email')) {
		mc_register_field_type(
			'email',
			array(
				'render_admin' => 'forms_render_field_email',
				'sanitize'     => 'forms_sanitize_field_email',
				'validate'     => 'forms_validate_field_email',
			)
		);
	}

	/*
	 * Hidden field type — not rendered in admin but used by form builder.
	 */
	if (null === mc_get_field_type('hidden')) {
		mc_register_field_type(
			'hidden',
			array(
				'render_admin' => 'forms_render_field_hidden',
				'sanitize'     => 'mc_sanitize_field_text',
			)
		);
	}
}

/*
 * -------------------------------------------------------------------------
 *  Email field type callbacks
 * -------------------------------------------------------------------------
 */

/**
 * Render an email input field.
 *
 * @param array $field Field definition.
 * @param mixed $value Current value.
 */
function forms_render_field_email(array $field, mixed $value): void
{

	echo '<input ' . mc_build_field_attributes(
		$field,
		array(
			'type'  => 'email',
			'value' => (string) $value,
		)
	) . '>' . "\n";
}

/**
 * Sanitize an email field value.
 *
 * @param mixed $value Raw value.
 * @param array $field Field definition.
 * @return string
 */
function forms_sanitize_field_email(mixed $value, array $field = array()): string
{

	return mc_sanitize_email((string) $value);
}

/**
 * Validate an email field value.
 *
 * @param mixed $value Sanitized value.
 * @param array $field Field definition.
 * @return true|string
 */
function forms_validate_field_email(mixed $value, array $field = array()): true|string
{

	if ('' === $value || null === $value) {
		return true;
	}

	if (false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
		return ( $field['label'] ?? 'Email' ) . ' must be a valid email address.';
	}

	return true;
}

/*
 * -------------------------------------------------------------------------
 *  Hidden field type callbacks
 * -------------------------------------------------------------------------
 */

/**
 * Render a hidden input field.
 *
 * @param array $field Field definition.
 * @param mixed $value Current value.
 */
function forms_render_field_hidden(array $field, mixed $value): void
{

	echo '<input type="hidden" id="field-' . mc_esc_attr($field['id'] ?? '') . '" name="' . mc_esc_attr($field['id'] ?? '') . '" value="' . mc_esc_attr((string) $value) . '">' . "\n";
}
