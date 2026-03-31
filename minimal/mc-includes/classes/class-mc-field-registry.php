<?php

/**
 * MC_Field_Registry — Form field type registration, rendering, sanitisation.
 *
 * Replaces the procedural fields.php. Plugins register custom field types
 * via register_type(); the built-in set is registered in register_core_types().
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Field type registry.
 *
 * @since {version}
 */
class MC_Field_Registry
{
	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Formatter
	 */
	private MC_Formatter $formatter;

	/**
	 * Registered field type definitions keyed by type slug.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $types = array();

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks     $hooks     Hooks engine.
	 * @param MC_Formatter $formatter Formatter for escaping/sanitising.
	 */
	public function __construct(MC_Hooks $hooks, MC_Formatter $formatter)
	{

		$this->hooks     = $hooks;
		$this->formatter = $formatter;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register a field type.
	 *
	 * @since {version}
	 *
	 * @param string $type Type slug.
	 * @param array  $args {
	 *     @type callable $render_admin Required. Outputs HTML.
	 *     @type callable $sanitize     Optional. Cleans raw input.
	 *     @type callable $validate     Optional. Returns true or error string.
	 *     @type array    $admin_assets Optional. CSS/JS URLs to enqueue.
	 * }
	 * @return true|MC_Error
	 */
	public function register_type(string $type, array $args): true|MC_Error
	{

		if ('' === $type) {
			return new MC_Error('invalid_field_type', 'Field type slug cannot be empty.');
		}

		if (!isset($args['render_admin']) || !is_callable($args['render_admin'])) {
			return new MC_Error('missing_render', "Field type '{$type}' must provide a callable render_admin.");
		}

		$this->types[$type] = array(
			'render_admin' => $args['render_admin'],
			'sanitize'     => isset($args['sanitize']) && is_callable($args['sanitize'])
				? $args['sanitize']
				: array($this, 'default_sanitize'),
			'validate'     => isset($args['validate']) && is_callable($args['validate'])
				? $args['validate']
				: array($this, 'default_validate'),
			'admin_assets' => $args['admin_assets'] ?? array(),
		);

		/**
		 * Fires after a field type is registered.
		 *
		 * @since {version}
		 *
		 * @param string $type Slug.
		 * @param array  $args Registered definition.
		 */
		$this->hooks->do_action('mc_registered_field_type', $type, $this->types[$type]);

		return true;
	}

	/**
	 * Get a registered field type definition.
	 *
	 * @since {version}
	 *
	 * @param string $type Type slug.
	 * @return array|null
	 */
	public function get_type(string $type): ?array
	{

		return $this->types[$type] ?? null;
	}

	/**
	 * Get all registered field types.
	 *
	 * @since {version}
	 *
	 * @return array
	 */
	public function get_types(): array
	{

		return $this->types;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Rendering
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Render a field's admin HTML with standard wrapper markup.
	 *
	 * @since {version}
	 *
	 * @param array       $field Field definition.
	 * @param mixed       $value Current value.
	 * @param string|null $error Error message for this field.
	 * @return void
	 */
	public function render(array $field, mixed $value = null, ?string $error = null): void
	{

		$type_def = $this->get_type($field['type'] ?? 'text');
		if (null === $type_def) {
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

		if (null === $value) {
			$value = $field['default'];
		}

		$field_id    = $this->formatter->esc_attr($field['id']);
		$has_error   = null !== $error && '' !== $error;
		$error_class = $has_error ? ' field-has-error' : '';

		/**
		 * Filter the field definition before rendering.
		 *
		 * @since {version}
		 *
		 * @param array $field Field definition.
		 * @param mixed $value Current value.
		 */
		$field = $this->hooks->apply_filters('mc_render_field', $field, $value);

		echo '<div class="form-group mc-field' . $error_class . '" data-field-type="' . $this->formatter->esc_attr($field['type']) . '">' . "\n";

		if ('' !== $field['label'] && 'checkbox' !== $field['type']) {
			echo '<label for="field-' . $field_id . '">' . $this->formatter->esc_html($field['label']);
			if ($field['required']) {
				echo ' <span class="required">*</span>';
			}
			echo '</label>' . "\n";
		}

		call_user_func($type_def['render_admin'], $field, $value);

		if ($has_error) {
			echo '<p class="field-error">' . $this->formatter->esc_html($error) . '</p>' . "\n";
		}

		if ('' !== $field['description']) {
			echo '<p class="description">' . $this->formatter->esc_html($field['description']) . '</p>' . "\n";
		}

		echo '</div>' . "\n";
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Sanitisation & Validation
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Sanitise a field value through its registered sanitizer.
	 *
	 * @since {version}
	 *
	 * @param string $type  Field type slug.
	 * @param mixed  $value Raw input value.
	 * @param array  $field Full field definition.
	 * @return mixed Sanitised value.
	 */
	public function sanitize(string $type, mixed $value, array $field = array()): mixed
	{

		$type_def = $this->get_type($type);
		if (null === $type_def) {
			return $this->default_sanitize($value, $field);
		}

		/**
		 * Filter sanitised value for a specific field type.
		 *
		 * @since {version}
		 *
		 * @param mixed $value     Sanitised value.
		 * @param array $field     Field definition.
		 */
		return $this->hooks->apply_filters(
			'mc_sanitize_field_' . $type,
			call_user_func($type_def['sanitize'], $value, $field),
			$field
		);
	}

	/**
	 * Validate a field value through its registered validator.
	 *
	 * @since {version}
	 *
	 * @param string $type  Field type slug.
	 * @param mixed  $value Already sanitised value.
	 * @param array  $field Full field definition.
	 * @return true|string True if valid or error message.
	 */
	public function validate(string $type, mixed $value, array $field = array()): true|string
	{

		if (!empty($field['required']) && ('' === $value || null === $value)) {
			$label = $field['label'] ?? $field['id'] ?? 'This field';
			return $label . ' is required.';
		}

		$type_def = $this->get_type($type);
		if (null === $type_def) {
			return $this->default_validate($value, $field);
		}

		/**
		 * Filter validation result for a specific field type.
		 *
		 * @since {version}
		 *
		 * @param true|string $result Validation result.
		 * @param mixed       $value  Value.
		 * @param array       $field  Field definition.
		 */
		return $this->hooks->apply_filters(
			'mc_validate_field_' . $type,
			call_user_func($type_def['validate'], $value, $field),
			$value,
			$field
		);
	}

	/**
	 * Sanitise and validate a batch of fields.
	 *
	 * @since {version}
	 *
	 * @param array $fields Array of field definitions keyed by field ID.
	 * @param array $raw    Raw input values keyed by field ID.
	 * @return array{values: array, errors: array}
	 */
	public function process(array $fields, array $raw): array
	{

		$values = array();
		$errors = array();

		foreach ($fields as $id => $field) {
			$field['id'] = $id;
			$type        = $field['type'] ?? 'text';
			$raw_value   = $raw[$id] ?? ($field['default'] ?? '');

			if ('checkbox' === $type && !array_key_exists($id, $raw)) {
				$raw_value = '';
			}

			$clean = $this->sanitize($type, $raw_value, $field);
			$valid = $this->validate($type, $clean, $field);

			$values[$id] = $clean;

			if (true !== $valid) {
				$errors[$id] = $valid;
			}
		}

		/**
		 * Filter processed field values.
		 *
		 * @since {version}
		 *
		 * @param array $values Sanitised values.
		 * @param array $errors Validation errors.
		 * @param array $fields Field definitions.
		 */
		$values = $this->hooks->apply_filters('mc_process_fields_values', $values, $errors, $fields);

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Default handlers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Default sanitizer: plain text.
	 *
	 * @since {version}
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field definition.
	 * @return string
	 */
	public function default_sanitize(mixed $value, array $field = array()): string
	{

		return $this->formatter->sanitize_text((string) $value);
	}

	/**
	 * Default validator: always passes.
	 *
	 * @since {version}
	 *
	 * @param mixed $value Value.
	 * @param array $field Field definition.
	 * @return true
	 */
	public function default_validate(mixed $value, array $field = array()): true
	{

		return true;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Core built-in types
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register the built-in field types (text, textarea, number, url, checkbox, select, markdown, html).
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function register_core_types(): void
	{

		$fmt = $this->formatter;

		// Text.
		$this->register_type('text', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$attrs = $this->build_attributes($field, array('type' => 'text', 'value' => (string) $value));
				echo '<input ' . $attrs . '>' . "\n";
			},
			'sanitize' => function (mixed $value) use ($fmt): string {
				return $fmt->sanitize_text((string) $value);
			},
		));

		// Textarea.
		$this->register_type('textarea', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$rows  = $field['options']['rows'] ?? 5;
				$attrs = $this->build_attributes($field, array('rows' => (int) $rows), true);
				echo '<textarea ' . $attrs . '>' . $fmt->esc_textarea((string) $value) . '</textarea>' . "\n";
			},
			'sanitize' => function (mixed $value): string {
				$value = strip_tags((string) $value);
				$value = str_replace("\r\n", "\n", $value);
				$value = str_replace("\r", "\n", $value);
				return trim($value);
			},
		));

		// Number.
		$this->register_type('number', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$extra = array('type' => 'number', 'value' => (string) $value);
				if (isset($field['options']['min'])) {
					$extra['min'] = (string) $field['options']['min'];
				}
				if (isset($field['options']['max'])) {
					$extra['max'] = (string) $field['options']['max'];
				}
				if (isset($field['options']['step'])) {
					$extra['step'] = (string) $field['options']['step'];
				}
				echo '<input ' . $this->build_attributes($field, $extra) . '>' . "\n";
			},
			'sanitize' => function (mixed $value): int|float {
				if ('' === $value || null === $value) {
					return 0;
				}
				return is_numeric($value) ? $value + 0 : 0;
			},
			'validate' => function (mixed $value, array $field): true|string {
				if (!is_numeric($value) && '' !== $value) {
					return ($field['label'] ?? 'Value') . ' must be a number.';
				}
				if (isset($field['options']['min']) && $value < $field['options']['min']) {
					return ($field['label'] ?? 'Value') . ' must be at least ' . $field['options']['min'] . '.';
				}
				if (isset($field['options']['max']) && $value > $field['options']['max']) {
					return ($field['label'] ?? 'Value') . ' must be at most ' . $field['options']['max'] . '.';
				}
				return true;
			},
		));

		// URL.
		$this->register_type('url', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$attrs = $this->build_attributes($field, array('type' => 'url', 'value' => (string) $value));
				echo '<input ' . $attrs . '>' . "\n";
			},
			'sanitize' => function (mixed $value): string {
				$value = trim((string) $value);
				if ('' === $value) {
					return '';
				}
				return filter_var($value, FILTER_SANITIZE_URL) ?: '';
			},
			'validate' => function (mixed $value, array $field): true|string {
				if ('' === $value) {
					return true;
				}
				if (!filter_var($value, FILTER_VALIDATE_URL)) {
					return ($field['label'] ?? 'Value') . ' must be a valid URL.';
				}
				return true;
			},
		));

		// Checkbox.
		$this->register_type('checkbox', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$checked = ('1' === (string) $value || true === $value) ? ' checked' : '';
				$id      = $fmt->esc_attr($field['id']);
				echo '<label class="checkbox-label">';
				echo '<input type="hidden" name="' . $id . '" value="">';
				echo '<input type="checkbox" id="field-' . $id . '" name="' . $id . '" value="1"' . $checked . '>';
				echo ' ' . $fmt->esc_html($field['label'] ?? '');
				echo '</label>' . "\n";
			},
			'sanitize' => function (mixed $value): string {
				return ('1' === (string) $value || true === $value) ? '1' : '';
			},
		));

		// Select.
		$this->register_type('select', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$id      = $fmt->esc_attr($field['id']);
				$choices = $field['options']['choices'] ?? array();
				echo '<select id="field-' . $id . '" name="' . $id . '">' . "\n";
				foreach ($choices as $opt_val => $opt_label) {
					$selected = ((string) $opt_val === (string) $value) ? ' selected' : '';
					echo '<option value="' . $fmt->esc_attr((string) $opt_val) . '"' . $selected . '>';
					echo $fmt->esc_html($opt_label) . '</option>' . "\n";
				}
				echo '</select>' . "\n";
			},
			'sanitize' => function (mixed $value, array $field) use ($fmt): string {
				return $fmt->sanitize_text((string) $value);
			},
			'validate' => function (mixed $value, array $field): true|string {
				$choices = $field['options']['choices'] ?? array();
				if ('' !== (string) $value && !array_key_exists((string) $value, $choices)) {
					return ($field['label'] ?? 'Value') . ' is not a valid choice.';
				}
				return true;
			},
		));

		// Markdown editor.
		$this->register_type('markdown', array(
			'render_admin' => function (array $field, mixed $value) use ($fmt): void {
				$rows  = $field['options']['rows'] ?? 12;
				$attrs = $this->build_attributes($field, array('rows' => (int) $rows), true);
				echo '<textarea ' . $attrs . ' class="mc-markdown-editor">' . $fmt->esc_textarea((string) $value) . '</textarea>' . "\n";
			},
			'sanitize' => function (mixed $value): string {
				$value = str_replace("\r\n", "\n", (string) $value);
				$value = str_replace("\r", "\n", $value);
				return trim($value);
			},
		));

		// HTML (read-only info block).
		$this->register_type('html', array(
			'render_admin' => function (array $field, mixed $value): void {
				echo '<div class="field-html">' . ($field['options']['html'] ?? '') . '</div>' . "\n";
			},
			'sanitize' => function (mixed $value): string {
				return (string) $value;
			},
		));

		/**
		 * Fires after core field types are registered.
		 *
		 * @since {version}
		 */
		$this->hooks->do_action('mc_field_types_registered');
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Attribute builder
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Build an HTML attribute string for an input element.
	 *
	 * @since {version}
	 *
	 * @param array $field   Field definition.
	 * @param array $extras  Extra key-value attributes.
	 * @param bool  $is_textarea Whether the element is a textarea (skip value attr).
	 * @return string
	 */
	public function build_attributes(array $field, array $extras = array(), bool $is_textarea = false): string
	{

		$id   = $this->formatter->esc_attr($field['id'] ?? '');
		$name = $id;

		$attrs = array(
			'id'   => 'field-' . $id,
			'name' => $name,
		);

		if (!$is_textarea && isset($extras['type'])) {
			$attrs['type'] = $extras['type'];
		}

		if (!$is_textarea && isset($extras['value'])) {
			$attrs['value'] = $extras['value'];
		}

		foreach ($extras as $key => $val) {
			if ('type' !== $key && 'value' !== $key && !isset($attrs[$key])) {
				$attrs[$key] = $val;
			}
		}

		// Merge in custom attributes.
		foreach ($field['attributes'] ?? array() as $key => $val) {
			$attrs[$key] = $val;
		}

		if (!empty($field['required'])) {
			$attrs['required'] = 'required';
		}

		$parts = array();
		foreach ($attrs as $key => $val) {
			if ($is_textarea && 'value' === $key) {
				continue;
			}
			$parts[] = $this->formatter->esc_attr($key) . '="' . $this->formatter->esc_attr((string) $val) . '"';
		}

		return implode(' ', $parts);
	}
}
