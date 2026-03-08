<?php

/**
 * Forms Plugin — Admin Form Builder
 *
 * Hooks into the edit-page screen to render form-specific configuration
 * sections (field builder, notifications, confirmation) when editing
 * content of type "form".
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Return the sanitized 'type' GET parameter (edit-form context helper).
 *
 * @since 1.0.0
 * @return string
 */
function forms_get_edit_type(): string
{
	return mc_sanitize_slug(mc_input('type', 'get') ?? '');
}

/**
 * Enqueue form builder assets on admin pages when editing a form.
 *
 * @since 1.0.0
 * @return void
 */
function forms_admin_head(): void
{

	if ('form' !== forms_get_edit_type()) {
		return;
	}

	$plugin_url = mc_site_url('mc-content/plugins/forms/');

	echo '<link rel="stylesheet" href="' . mc_esc_url($plugin_url . 'assets/css/form-builder.css') . '">' . "\n";
}
mc_add_action('mc_admin_head', 'forms_admin_head');

/**
 * Enqueue form builder JS in footer when editing a form.
 *
 * @since 1.0.0
 * @return void
 */
function forms_admin_footer(): void
{

	if ('form' !== forms_get_edit_type()) {
		return;
	}

	$plugin_url = mc_site_url('mc-content/plugins/forms/');

	// Pass allowed field types as JSON for the JS builder.
	$allowed_types = forms_get_allowed_field_types();
	echo '<script>window.FormsConfig = ' . forms_json_encode_safe(
		array(
			'fieldTypes' => $allowed_types,
		)
	) . ';</script>' . "\n";
	echo '<script src="' . mc_esc_url($plugin_url . 'assets/js/form-builder.js') . '"></script>' . "\n";
}
mc_add_action('mc_admin_footer', 'forms_admin_footer');

/**
 * Safe JSON encode helper.
 *
 * @since 1.0.0
 *
 * @param mixed $data Data to encode.
 * @return string
 */
function forms_json_encode_safe(mixed $data): string
{
	return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

/**
 * Intercept form content saves to process form builder fields.
 *
 * Hooked to mc_content_saved to update form meta with builder data.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @param array  $meta Saved meta.
 * @return void
 */
function forms_handle_save(string $type, string $slug, array $meta): void
{

	static $saving = false;

	if ('form' !== $type || ! mc_is_post_request() || $saving) {
		return;
	}

	// Build notification settings from POST.
	$notifications = array(
		array(
			'enabled'        => ! empty(mc_input('notif_enabled', 'post')),
			'to'             => mc_sanitize_text(mc_input('notif_to', 'post') ?? ''),
			'subject'        => mc_sanitize_text(mc_input('notif_subject', 'post') ?? ''),
			'from_name'      => mc_sanitize_text(mc_input('notif_from_name', 'post') ?? ''),
			'from_email'     => mc_sanitize_email(mc_input('notif_from_email', 'post') ?? ''),
			'reply_to_field' => mc_sanitize_slug(mc_input('notif_reply_to_field', 'post') ?? ''),
		),
	);

	// Build confirmation settings from POST.
	$confirmation = array(
		'type'     => mc_sanitize_slug(mc_input('confirm_type', 'post') ?? 'message'),
		'message'  => mc_sanitize_text(mc_input('confirm_message', 'post') ?? ''),
		'redirect' => mc_input('confirm_redirect', 'post') ?? '',
		'page'     => mc_sanitize_slug(mc_input('confirm_page', 'post') ?? ''),
	);

	$confirmation['redirect'] = filter_var($confirmation['redirect'], FILTER_VALIDATE_URL);
	if (false === $confirmation['redirect']) {
		$confirmation['redirect'] = '';
	}

	// Re-save content with updated form meta — use static guard to prevent recursion.
	$existing = mc_get_content('form', $slug);
	$body     = $existing['body_raw'] ?? '';

	// Read field definitions from form builder POST data, falling back to existing saved fields.
	$fields_raw = mc_input('form_fields_json', 'post') ?? '';
	$decoded    = json_decode($fields_raw, true);
	$fields     = is_array($decoded) ? $decoded : ($existing['meta']['fields'] ?? array());

	// Normalize everything.
	$form_meta = forms_normalize_meta(
		array(
			'fields'        => $fields,
			'notifications' => $notifications,
			'confirmation'  => $confirmation,
		)
	);

	// Validate the form definition; skip re-save if invalid.
	if (forms_validate_definition($form_meta)) {
		$saving = false;
		return;
	}

	// Remove runtime keys that shouldn't be persisted to JSON.
	unset($existing['body_raw'], $existing['body_html']);

	$existing['meta'] = $form_meta;

	$saving = true;
	mc_save_content('form', $slug, $existing, $body);
	$saving = false;
}
mc_add_action('mc_content_saved', 'forms_handle_save', 10, 3);

/**
 * Render form builder sections below the main editor area.
 *
 * Hooked to mc_edit_content_after_editor so the builder cards are rendered
 * directly inside the <form> element, below the excerpt field.
 *
 * @since 1.0.0
 *
 * @param string $content_type Content type slug.
 * @param array  $item         Current content item data.
 * @return void
 */
function forms_render_builder_sections(string $content_type, array $item): void
{

	// Only on form edit screens.
	if ('form' !== $content_type) {
		return;
	}

	$slug = $item['slug'] ?? '';
	$meta = forms_normalize_meta(array());

	if ('' !== $slug) {
		$form = mc_get_content('form', $slug);
		if ($form && ! mc_is_error($form)) {
			$meta = forms_normalize_meta($form['meta'] ?? array());
		}
	}

	// Apply global defaults for empty notification settings.
	if (empty($meta['notifications'])) {
		$meta['notifications'] = array( forms_notification_defaults() );
	}
	$notif = $meta['notifications'][0] ?? forms_notification_defaults();

	// Fill from global defaults if per-form settings are empty.
	if ('' === $notif['to']) {
		$notif['to'] = mc_get_setting('plugin.forms', 'default_to_email', '');
	}
	if ('' === $notif['subject']) {
		$notif['subject'] = mc_get_setting('plugin.forms', 'default_subject', 'New Form Submission: {form_title}');
	}
	if ('' === $notif['from_name']) {
		$notif['from_name'] = mc_get_setting('plugin.forms', 'default_from_name', '');
	}
	if ('' === $notif['from_email']) {
		$notif['from_email'] = mc_get_setting('plugin.forms', 'default_from_email', '');
	}

	$confirm = $meta['confirmation'];
	if ('' === ($confirm['message'] ?? '')) {
		$confirm['message'] = mc_get_setting('plugin.forms', 'default_confirmation_message', 'Thank you! Your submission has been received.');
	}
	if ('' === ($confirm['redirect'] ?? '')) {
		$confirm['redirect'] = mc_get_setting('plugin.forms', 'default_redirect_url', '');
	}

	// Get field names for reply-to mapping.
	$email_fields  = array_filter($meta['fields'], fn($f) => 'email' === $f['type']);
	$allowed_types = forms_get_allowed_field_types();
	$fields_json   = forms_json_encode_safe($meta['fields']);

	?>
	<!-- Forms Settings Sections — inserted by forms plugin -->
	<div id="forms-builder-container">

		<!-- ── Field Builder ────────────────────────────────────── -->
		<div class="card" id="forms-fields-card">
			<div class="card-header">
				Form Fields
				<button type="button" id="forms-toggle-source" class="btn btn-secondary btn-sm">JSON Source</button>
			</div>

			<div id="forms-fields-repeater"></div>

			<div id="forms-fields-source" style="display:none;">
				<textarea id="form-fields-json-editor" rows="10" class="form-control" style="font-family:monospace;"></textarea>
			</div>

			<input type="hidden" id="form-fields-json" name="form_fields_json" value="<?php echo mc_esc_attr($fields_json); ?>">

			<div style="padding: 12px 16px;">
				<button type="button" id="forms-add-field" class="btn btn-secondary">+ Add Field</button>
			</div>
		</div>

		<!-- ── Notification Settings ────────────────────────────── -->
		<div class="card" id="forms-notifications-card">
			<div class="card-header">Notification Settings</div>

			<div class="form-group">
				<label>
					<input type="hidden" name="notif_enabled" value="0">
					<input type="checkbox" name="notif_enabled" value="1" <?php echo $notif['enabled'] ? 'checked' : ''; ?>>
					Enable Email Notifications
				</label>
			</div>

			<div class="form-group">
				<label for="notif-to">Recipient Email(s)</label>
				<input type="text" id="notif-to" name="notif_to" class="form-control" value="<?php echo mc_esc_attr($notif['to']); ?>" placeholder="admin@example.com">
				<p class="description">Comma-separate multiple addresses.</p>
			</div>

			<div class="form-group">
				<label for="notif-subject">Email Subject</label>
				<input type="text" id="notif-subject" name="notif_subject" class="form-control" value="<?php echo mc_esc_attr($notif['subject']); ?>">
				<p class="description">Use {form_title} for the form name.</p>
			</div>

			<div class="form-group">
				<label for="notif-from-name">"From" Name</label>
				<input type="text" id="notif-from-name" name="notif_from_name" class="form-control" value="<?php echo mc_esc_attr($notif['from_name']); ?>">
			</div>

			<div class="form-group">
				<label for="notif-from-email">"From" Email</label>
				<input type="email" id="notif-from-email" name="notif_from_email" class="form-control" value="<?php echo mc_esc_attr($notif['from_email']); ?>">
			</div>

			<div class="form-group">
				<label for="notif-reply-to">Reply-To Field</label>
				<select name="notif_reply_to_field" id="notif-reply-to" class="form-control">
					<option value="">— None (static) —</option>
					<?php foreach ($email_fields as $ef) : ?>
						<option value="<?php echo mc_esc_attr($ef['name']); ?>"
							<?php echo $notif['reply_to_field'] === $ef['name'] ? 'selected' : ''; ?>>
							<?php echo mc_esc_html('' !== $ef['label'] ? $ef['label'] : $ef['name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">Route reply-to to a submitted email field value.</p>
			</div>
		</div>

		<!-- ── Confirmation Settings ────────────────────────────── -->
		<div class="card" id="forms-confirmation-card">
			<div class="card-header">Confirmation Settings</div>

			<div class="form-group">
				<label for="confirm-type">Confirmation Type</label>
				<select name="confirm_type" id="confirm-type" class="form-control">
					<option value="message" <?php echo 'message' === ($confirm['type'] ?? '') ? 'selected' : ''; ?>>Success Message</option>
					<option value="redirect" <?php echo 'redirect' === ($confirm['type'] ?? '') ? 'selected' : ''; ?>>Redirect URL</option>
					<option value="page" <?php echo 'page' === ($confirm['type'] ?? '') ? 'selected' : ''; ?>>Thank-You Page</option>
				</select>
			</div>

			<div class="form-group forms-confirm-opt" data-show-for="message">
				<label for="confirm-message">Success Message</label>
				<textarea id="confirm-message" name="confirm_message" class="form-control" rows="3"><?php echo mc_esc_textarea($confirm['message'] ?? ''); ?></textarea>
			</div>

			<div class="form-group forms-confirm-opt" data-show-for="redirect">
				<label for="confirm-redirect">Redirect URL</label>
				<input type="url" id="confirm-redirect" name="confirm_redirect" class="form-control" value="<?php echo mc_esc_attr($confirm['redirect'] ?? ''); ?>" placeholder="https://example.com/thank-you">
			</div>

			<div class="form-group forms-confirm-opt" data-show-for="page">
				<label for="confirm-page">Thank-You Page Slug</label>
				<input type="text" id="confirm-page" name="confirm_page" class="form-control" value="<?php echo mc_esc_attr($confirm['page']); ?>" placeholder="thank-you">
				<p class="description">Slug of an existing page to display after submission.</p>
			</div>
		</div>

		<!-- ── Embed Info ───────────────────────────────────────── -->
		<?php if ('' !== $slug) : ?>
		<div class="card" id="forms-embed-card">
			<div class="card-header">Embed</div>
			<div class="form-group">
				<label>Shortcode</label>
				<input type="text" class="form-control" readonly value='[form id="<?php echo mc_esc_attr($slug); ?>"]' onclick="this.select();">
			</div>
			<div class="form-group">
				<label>Direct URL</label>
				<input type="text" class="form-control" readonly value="<?php echo mc_esc_attr(mc_site_url('form/' . $slug)); ?>" onclick="this.select();">
			</div>
		</div>
		<?php endif; ?>

	</div>
	<?php
}
mc_add_action('mc_edit_content_after_editor', 'forms_render_builder_sections', 10, 2);
