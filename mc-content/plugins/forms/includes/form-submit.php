<?php

/**
 * Forms Plugin — Submission Handler
 *
 * Processes frontend form submissions: validates nonce, checks honeypot,
 * runs field-level sanitization/validation via the Fields API, and persists
 * valid submissions to disk.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Process a form submission.
 *
 * @since 1.0.0
 *
 * @param string $form_slug The submitted form's slug.
 * @return void Redirects or sets flash data; never returns on success.
 */
function forms_process_submission(string $form_slug): void
{

	$form = mc_get_content('form', $form_slug);

	if (! $form || mc_is_error($form) || 'publish' !== ( $form['status'] ?? '' )) {
		return;
	}

	// Verify nonce.
	if (! mc_verify_nonce(mc_input('_mc_nonce', 'post'), 'form_submit_' . $form_slug)) {
		forms_set_flash($form_slug, 'error', 'Security check failed. Please try again.');
		forms_redirect_back($form_slug);
		return;
	}

	// Honeypot check.
	$honeypot_enabled = (bool) mc_get_setting('plugin.forms', 'enable_honeypot', true);
	if ($honeypot_enabled) {
		$hp_value = mc_input('_mc_hp', 'post') ?? '';
		if ('' !== $hp_value) {
			// Silently discard — looks like spam.
			forms_set_flash($form_slug, 'success', '');
			forms_redirect_back($form_slug);
			return;
		}
	}

	$meta = forms_normalize_meta($form['meta'] ?? array());

	if (empty($meta['fields'])) {
		return;
	}

	// Build runtime field definitions for Fields API processing.
	$runtime_fields = forms_build_runtime_fields($meta['fields']);

	// Collect raw input.
	$raw = array();
	foreach ($runtime_fields as $field_id => $field_def) {
		$raw[ $field_id ] = mc_input($field_id, 'post');
	}

	// Sanitize + validate through Fields API.
	$processed = mc_process_fields($runtime_fields, $raw);

	/**
	 * Filter processed submission values before persistence.
	 *
	 * @since 1.0.0
	 *
	 * @param array $values Sanitized values.
	 * @param array $errors Validation errors.
	 * @param array $form   Full form content.
	 */
	$processed['values'] = mc_apply_filters('forms_submission_values', $processed['values'], $processed['errors'], $form);

	// Validation failed — redirect back with errors.
	if (! empty($processed['errors'])) {
		$error_messages = implode(' ', $processed['errors']);
		forms_set_flash($form_slug, 'error', $error_messages);
		forms_redirect_back($form_slug);
		return;
	}

	// Persist submission.
	$submission_id = forms_save_submission($form_slug, $processed['values']);

	if (false === $submission_id) {
		forms_set_flash($form_slug, 'error', 'An error occurred saving your submission. Please try again.');
		forms_redirect_back($form_slug);
		return;
	}

	/**
	 * Fires after a form submission is successfully persisted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $form_slug     Form slug.
	 * @param string $submission_id Unique submission identifier.
	 * @param array  $values        Submitted field values.
	 * @param array  $form          Full form content array.
	 */
	mc_do_action('forms_after_submit', $form_slug, $submission_id, $processed['values'], $form);

	// Send notifications (non-blocking — errors are logged, not shown).
	forms_send_notifications($form, $processed['values']);

	// Resolve confirmation action.
	forms_resolve_confirmation($form_slug, $meta['confirmation']);
}

/**
 * Persist a submission to disk.
 *
 * @since 1.0.0
 *
 * @param string $form_slug Form slug.
 * @param array  $values    Sanitized field values.
 * @return string|false Submission ID on success, false on failure.
 */
function forms_save_submission(string $form_slug, array $values): string|false
{

	$dir = MC_FORMS_SUBMISSIONS_DIR . $form_slug . '/';

	if (! is_dir($dir)) {
		mkdir($dir, 0755, true);
	}

	$submission_id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));

	$data = array(
		'id'         => $submission_id,
		'form_slug'  => $form_slug,
		'submitted'  => gmdate('c'),
		'ip'         => forms_get_client_ip(),
		'user_agent' => mb_substr(mc_sanitize_text($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 256),
		'values'     => $values,
	);

	$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	$path = $dir . $submission_id . '.json';

	try {
		$payload = forms_encrypt_submission($json);
	} catch (\RuntimeException $e) {
		return false;
	}

	if (false === file_put_contents($path, $payload . "\n", LOCK_EX)) {
		return false;
	}

	return $submission_id;
}

/**
 * Get the client IP address with basic proxy awareness.
 *
 * @since 1.0.0
 *
 * @return string
 */
function forms_get_client_ip(): string
{

	// Only trust REMOTE_ADDR — X-Forwarded-For can be spoofed.
	return mc_sanitize_text($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/*
 * -------------------------------------------------------------------------
 *  Flash message system (URL query-param based — no session required)
 * -------------------------------------------------------------------------
 */

/**
 * Queue a flash message for the upcoming redirect URL.
 *
 * @since 1.0.0
 *
 * @param string $form_slug Form slug.
 * @param string $type      'success' or 'error'.
 * @param string $message   Message text.
 * @return void
 */
function forms_set_flash(string $form_slug, string $type, string $message): void
{
	global $mc_forms_flash_pending;
	$mc_forms_flash_pending[ $form_slug ] = array(
		'type'    => $type,
		'message' => $message,
	);
}

/**
 * Get the flash result for a form from URL query params.
 *
 * Reads mc_form / mc_result / mc_msg params written by forms_redirect_back().
 *
 * @since 1.0.0
 *
 * @param string $form_slug Form slug.
 * @return array|null Array with 'type' and 'message', or null.
 */
function forms_get_flash(string $form_slug): ?array
{
	$param_slug = mc_sanitize_slug(mc_input('mc_form', 'get') ?? '');
	$result     = mc_sanitize_slug(mc_input('mc_result', 'get') ?? '');

	if ($param_slug !== $form_slug || '' === $result) {
		return null;
	}

	$type    = ('success' === $result) ? 'success' : 'error';
	$message = mc_sanitize_text(mc_input('mc_msg', 'get') ?? '');

	return array( 'type' => $type, 'message' => $message );
}

/**
 * Redirect back to the referring page, embedding pending flash as URL params.
 *
 * Protects against open redirects by validating the referer host.
 *
 * @since 1.0.0
 *
 * @param string $form_slug Form slug.
 * @return void
 */
function forms_redirect_back(string $form_slug): void
{
	global $mc_forms_flash_pending;

	$raw_referer = $_SERVER['HTTP_REFERER'] ?? '';
	$parts       = ( '' !== $raw_referer ) ? parse_url($raw_referer) : array();
	$site_host   = parse_url(mc_site_url(), PHP_URL_HOST);

	// Open-redirect protection: only redirect to the same host.
	if (( $parts['host'] ?? '' ) !== $site_host) {
		$parts = parse_url(mc_site_url()) ?: array();
	}

	parse_str($parts['query'] ?? '', $qp);
	unset($qp['mc_form'], $qp['mc_result'], $qp['mc_msg']);

	$flash = $mc_forms_flash_pending[ $form_slug ] ?? null;
	if (null !== $flash) {
		$qp['mc_form']   = $form_slug;
		$qp['mc_result'] = $flash['type'];
		if ('' !== $flash['message']) {
			$qp['mc_msg'] = $flash['message'];
		}
		unset($mc_forms_flash_pending[ $form_slug ]);
	}

	$url = ( $parts['scheme'] ?? 'https' ) . '://'
		. ( $parts['host'] ?? '' )
		. ( isset($parts['port']) ? ':' . $parts['port'] : '' )
		. ( $parts['path'] ?? '/' );

	if (! empty($qp)) {
		$url .= '?' . http_build_query($qp);
	}

	mc_redirect($url);
	exit;
}
