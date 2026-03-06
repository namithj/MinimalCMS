<?php

/**
 * Forms Plugin — Frontend Rendering
 *
 * Provides the shortcode handler and dedicated endpoint handler that
 * render forms on the public site.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Shortcode handler for [form id="slug"].
 *
 * @since 1.0.0
 *
 * @param array  $attrs   Shortcode attributes.
 * @param string $content Enclosed content (unused).
 * @param string $tag     Shortcode tag.
 * @return string Rendered HTML or empty string on failure.
 */
function forms_shortcode_handler(array $attrs, string $content = '', string $tag = ''): string
{

	$slug = mc_sanitize_slug($attrs['id'] ?? '');

	if ('' === $slug) {
		return '';
	}

	$form = mc_get_content('form', $slug);

	if (! $form || mc_is_error($form) || 'publish' !== ( $form['status'] ?? '' )) {
		return '';
	}

	return forms_render_form($form);
}

/**
 * Dedicated endpoint handler for /form/{slug}.
 *
 * Renders the form inside the active theme's layout by loading
 * a minimal template with the form output.
 *
 * @since 1.0.0
 *
 * @param array $matches Regex matches from mc_add_route.
 * @return void
 */
function forms_endpoint_handler(array $matches): void
{

	global $mc_query;

	$slug = mc_sanitize_slug($matches[1] ?? '');

	if ('' === $slug) {
		$mc_query['is_404'] = true;
		return;
	}

	$form = mc_get_content('form', $slug);

	if (! $form || mc_is_error($form) || 'publish' !== ( $form['status'] ?? '' )) {
		$mc_query['is_404'] = true;
		return;
	}

	// Set up query so template-loader picks the right template.
	$mc_query['type']      = 'form';
	$mc_query['slug']      = $slug;
	$mc_query['content']   = $form;
	$mc_query['is_single'] = true;

	// Inject rendered form as body_html for template tags to output.
	$mc_query['content']['body_html'] = forms_render_form($form);
}

/**
 * Render a form to HTML.
 *
 * Shared renderer used by both shortcode and endpoint handlers.
 *
 * @since 1.0.0
 *
 * @param array $form Full form content array (from mc_get_content).
 * @return string Rendered HTML.
 */
function forms_render_form(array $form): string
{

	$meta = forms_normalize_meta($form['meta'] ?? array());
	$slug = mc_sanitize_slug($form['slug'] ?? '');

	if ('' === $slug || empty($meta['fields'])) {
		return '';
	}

	// Check for flash confirmation message.
	$confirmation_html = forms_get_flash_confirmation($slug);
	if ('' !== $confirmation_html) {
		return $confirmation_html;
	}

	$honeypot_enabled = (bool) mc_get_setting('plugin.forms', 'enable_honeypot', true);

	ob_start();
	?>
	<div class="mc-form-wrapper" id="mc-form-<?php echo mc_esc_attr($slug); ?>">
		<form method="post" action="" class="mc-form" novalidate>
			<input type="hidden" name="_mc_form_slug" value="<?php echo mc_esc_attr($slug); ?>">
			<?php mc_nonce_field('form_submit_' . $slug); ?>

			<?php if ($honeypot_enabled) : ?>
				<div style="position:absolute;left:-9999px;" aria-hidden="true">
					<label for="mc-hp-<?php echo mc_esc_attr($slug); ?>">Leave empty</label>
					<input type="text" id="mc-hp-<?php echo mc_esc_attr($slug); ?>" name="_mc_hp" value="" tabindex="-1" autocomplete="off">
				</div>
			<?php endif; ?>

			<?php foreach ($meta['fields'] as $field) : ?>
				<?php forms_render_form_field($field); ?>
			<?php endforeach; ?>

			<div class="mc-form-actions">
				<button type="submit" class="btn btn-primary">Submit</button>
			</div>
		</form>
	</div>
	<?php
	$html = ob_get_clean();

	return mc_apply_filters('forms_render_output', $html, $form);
}

/**
 * Render a single form field on the frontend.
 *
 * @since 1.0.0
 *
 * @param array $field Normalized field definition.
 * @return void
 */
function forms_render_form_field(array $field): void
{

	echo '<div class="mc-form-field mc-form-field--' . mc_esc_attr($field['type']) . '">' . "\n";

	if ('hidden' === $field['type']) {
		echo '<input type="hidden" name="' . mc_esc_attr($field['name']) . '" value="">' . "\n";
		echo '</div>' . "\n";
		return;
	}

	if ('checkbox' !== $field['type']) {
		echo '<label for="field-' . mc_esc_attr($field['name']) . '">';
		echo mc_esc_html($field['label']);
		echo $field['required'] ? ' <span class="required">*</span>' : '';
		echo '</label>' . "\n";
	}

	switch ($field['type']) {
		case 'textarea':
			echo '<textarea id="field-' . mc_esc_attr($field['name']) . '" name="' . mc_esc_attr($field['name']) . '" placeholder="' . mc_esc_attr($field['placeholder'] ?? '') . '"';
			echo $field['required'] ? ' required' : '';
			echo '></textarea>' . "\n";
			break;

		case 'select':
			echo '<select id="field-' . mc_esc_attr($field['name']) . '" name="' . mc_esc_attr($field['name']) . '"';
			echo $field['required'] ? ' required' : '';
			echo '>' . "\n";
			echo '<option value="">— Select —</option>' . "\n";
			foreach ($field['options'] as $opt) {
				if (is_array($opt)) {
					echo '<option value="' . mc_esc_attr($opt['value'] ?? '') . '">' . mc_esc_html($opt['label'] ?? $opt['value'] ?? '') . '</option>' . "\n";
				} else {
					echo '<option value="' . mc_esc_attr((string) $opt) . '">' . mc_esc_html((string) $opt) . '</option>' . "\n";
				}
			}
			echo '</select>' . "\n";
			break;

		case 'checkbox':
			echo '<label>' . "\n";
			echo '  <input type="checkbox" id="field-' . mc_esc_attr($field['name']) . '" name="' . mc_esc_attr($field['name']) . '" value="1"';
			echo $field['required'] ? ' required' : '';
			echo '>' . "\n";
			echo '  ' . mc_esc_html($field['label']);
			echo $field['required'] ? ' <span class="required">*</span>' : '';
			echo "\n";
			echo '</label>' . "\n";
			break;

		default: // text, email, number, url.
			echo '<input type="' . mc_esc_attr($field['type']) . '" id="field-' . mc_esc_attr($field['name']) . '" name="' . mc_esc_attr($field['name']) . '" placeholder="' . mc_esc_attr($field['placeholder'] ?? '') . '"';
			echo $field['required'] ? ' required' : '';
			echo '>' . "\n";
			break;
	}

	echo '</div>' . "\n";
}
