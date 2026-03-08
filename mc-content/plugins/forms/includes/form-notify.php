<?php

/**
 * Forms Plugin — Notification Engine
 *
 * Builds and sends email notifications for form submissions.
 * Supports static recipients and route-to-field mapping for reply-to.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Send email notifications for a form submission.
 *
 * @since 1.0.0
 *
 * @param array $form   Full form content array.
 * @param array $values Sanitized submitted field values.
 * @return void
 */
function forms_send_notifications(array $form, array $values): void
{

	$meta = forms_normalize_meta($form['meta'] ?? array());

	if (empty($meta['notifications'])) {
		return;
	}

	$form_title = mc_esc_html($form['title'] ?? $form['slug'] ?? 'Form');

	foreach ($meta['notifications'] as $notif) {
		if (empty($notif['enabled'])) {
			continue;
		}

		$to = forms_resolve_recipients($notif['to']);

		if (empty($to)) {
			continue;
		}

		$subject = forms_replace_placeholders($notif['subject'], $form_title, $values);
		$body    = forms_build_email_body($form_title, $values);

		// Headers.
		$headers = array();

		$from_name  = '' !== $notif['from_name'] ? $notif['from_name'] : 'Forms';
		$from_email = $notif['from_email'];

		if ('' !== $from_email) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		// Route reply-to to a submitted field value.
		if ('' !== $notif['reply_to_field'] && isset($values[ $notif['reply_to_field'] ])) {
			$reply_email = filter_var($values[ $notif['reply_to_field'] ], FILTER_VALIDATE_EMAIL);
			if ($reply_email) {
				$headers[] = 'Reply-To: ' . $reply_email;
			}
		}

		$headers[] = 'Content-Type: text/plain; charset=UTF-8';

		/**
		 * Filter notification arguments before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args    {to, subject, body, headers}
		 * @param array $form    Full form content.
		 * @param array $values  Submitted values.
		 */
		$args = mc_apply_filters(
			'forms_notification_args',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			),
			$form,
			$values
		);

		$header_str = implode("\r\n", $args['headers']);

		// Send via PHP mail() — non-blocking failure handling.
		mail($args['to'], $args['subject'], $args['body'], $header_str);
	}
}

/**
 * Resolve recipient string to a clean, validated email list.
 *
 * @since 1.0.0
 *
 * @param string $to Comma-separated recipient list.
 * @return string Cleaned comma-separated list, or empty string.
 */
function forms_resolve_recipients(string $to): string
{

	$parts = array_map('trim', explode(',', $to));

	$valid = array();
	foreach ($parts as $email) {
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$valid[] = $email;
		}
	}

	return implode(', ', $valid);
}

/**
 * Replace placeholder tokens in a string.
 *
 * @since 1.0.0
 *
 * @param string $text       Input text with {placeholders}.
 * @param string $form_title Form title.
 * @param array  $values     Submitted field values.
 * @return string
 */
function forms_replace_placeholders(string $text, string $form_title, array $values): string
{

	$text = str_replace('{form_title}', $form_title, $text);

	// Replace {field_name} tokens with submitted values.
	foreach ($values as $key => $val) {
		$text = str_replace('{' . $key . '}', (string) $val, $text);
	}

	return $text;
}

/**
 * Build the plain-text email body from submitted values.
 *
 * @since 1.0.0
 *
 * @param string $form_title Form title.
 * @param array  $values     Submitted field values.
 * @return string
 */
function forms_build_email_body(string $form_title, array $values): string
{

	$lines   = array();
	$lines[] = 'New submission for: ' . $form_title;
	$lines[] = str_repeat('-', 40);

	foreach ($values as $label => $value) {
		$display_val = is_array($value) ? implode(', ', $value) : (string) $value;
		$lines[]     = ucfirst(str_replace('_', ' ', $label)) . ': ' . $display_val;
	}

	$lines[] = str_repeat('-', 40);
	$lines[] = 'Submitted: ' . gmdate('Y-m-d H:i:s') . ' UTC';

	return implode("\n", $lines);
}
