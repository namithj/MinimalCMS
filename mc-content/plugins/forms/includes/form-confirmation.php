<?php

/**
 * Forms Plugin — Confirmation Resolver
 *
 * Determines and executes the correct post-submission confirmation action:
 * success message, redirect URL, or thank-you page.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Resolve the confirmation action after a successful submission.
 *
 * Sets a flash message or redirects as appropriate, then exits.
 *
 * @since 1.0.0
 *
 * @param string $form_slug   Form slug.
 * @param array  $confirmation Confirmation settings from form meta.
 * @return void Exits after redirect or flash set.
 */
function forms_resolve_confirmation(string $form_slug, array $confirmation): void
{

	$confirmation = array_merge(forms_confirmation_defaults(), $confirmation);

	// Fall back to global defaults for empty per-form settings.
	$type = '' !== $confirmation['type'] ? $confirmation['type'] : mc_get_setting('plugin.forms', 'default_confirmation_type', 'message');

	switch ($type) {
		case 'redirect':
			$url = $confirmation['redirect'];
			if ('' === $url) {
				$url = mc_get_setting('plugin.forms', 'default_redirect_url', '');
			}
			if ('' !== $url && filter_var($url, FILTER_VALIDATE_URL)) {
				mc_redirect($url);
				exit;
			}
			// Fall through to message if URL is invalid.
			// No break.
		case 'page':
			$page_slug = $confirmation['page'];
			if ('' !== $page_slug) {
				$page = mc_get_content('page', $page_slug);
				if ($page && ! mc_is_error($page)) {
					mc_redirect(mc_site_url($page_slug));
					exit;
				}
			}
			// Fall through to message if page not found.
			// No break.
		case 'message':
		default:
			$message = $confirmation['message'];
			if ('' === $message) {
				$message = mc_get_setting(
					'plugin.forms',
					'default_confirmation_message',
					'Thank you! Your submission has been received.'
				);
			}
			forms_set_flash($form_slug, 'success', $message);
			forms_redirect_back($form_slug);
			exit;
	}
}

/**
 * Get any pending flash confirmation HTML for a form.
 *
 * If a success flash exists, return the confirmation markup and consume
 * the flash. If an error flash exists, return the error markup.
 *
 * @since 1.0.0
 *
 * @param string $form_slug Form slug.
 * @return string HTML or empty string.
 */
function forms_get_flash_confirmation(string $form_slug): string
{

	$flash = forms_get_flash($form_slug);

	if (null === $flash) {
		return '';
	}

	$type = $flash['type'] ?? 'success';

	// Error flashes are shown inline above the form by the renderer —
	// don't replace the entire form with just an error message.
	if ('error' === $type) {
		return '';
	}

	// Resolve the success message from the stored form configuration
	// rather than from URL parameters.
	$message = forms_resolve_success_message($form_slug);

	return '<div class="mc-form-message mc-form-success">'
		. '<p>' . mc_esc_html($message) . '</p>'
		. '</div>';
}

/**
 * Resolve the success confirmation message from stored form settings.
 *
 * Looks up the per-form confirmation message, falling back to the
 * global plugin default.
 *
 * @since {version}
 *
 * @param string $form_slug Form slug.
 * @return string Confirmation message text.
 */
function forms_resolve_success_message(string $form_slug): string
{

	$form = mc_get_content('form', $form_slug);

	if ($form && ! mc_is_error($form)) {
		$meta    = forms_normalize_meta($form['meta'] ?? array());
		$message = $meta['confirmation']['message'] ?? '';

		if ('' !== $message) {
			return $message;
		}
	}

	return mc_get_setting(
		'plugin.forms',
		'default_confirmation_message',
		'Thank you! Your submission has been received.'
	);
}
