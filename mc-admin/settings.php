<?php

/**
 * MinimalCMS — Site Settings (Admin)
 *
 * Thin controller that delegates to the Settings API form engine.
 * Fields and sections are defined in mc_register_core_settings_pages().
 *
 * Also supports sub-pages via ?page=<slug> for plugin/theme settings.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if (! mc_current_user_can('manage_settings')) {
	mc_redirect(mc_admin_url());
	exit;
}

/*
 * ── Determine which settings page to render ────────────────────────────────
 */
$page_slug = mc_sanitize_slug(mc_input('page', 'get') ?? '');

// Default to core "general" settings page.
if ('' === $page_slug) {
	$page_slug = 'general';
}

$page_def = mc_get_settings_page($page_slug);
if (null === $page_def) {
	mc_redirect(mc_admin_url());
	exit;
}

// Capability check for the specific page.
if (! mc_current_user_can($page_def['capability'])) {
	mc_redirect(mc_admin_url());
	exit;
}

/*
 * ── Populate dynamic field choices ─────────────────────────────────────────
 */
if ('general' === $page_slug) {
	mc_populate_front_page_choices();
}

/**
 * Fires before a settings page is rendered/processed. Plugins can use this
 * to populate dynamic choices or modify field definitions.
 *
 * @since 1.1.0
 *
 * @param string $page_slug The settings page being loaded.
 */
mc_do_action('mc_settings_page_load', $page_slug);

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';
$values      = mc_get_settings_page_values($page_slug);
$errors      = array();

if (mc_is_post_request()) {
	$result = mc_handle_settings_post($page_slug, $_POST);

	$values      = $result['values'] ?: $values;
	$errors      = $result['errors'];
	$notice      = $result['notice'];
	$notice_type = $result['notice_type'];

	// On success, reload stored values to pick up any filter modifications.
	if ($result['saved']) {
		$values = mc_get_settings_page_values($page_slug);
	}
}

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = $page_def['title'];
require MC_ABSPATH . 'mc-admin/admin-header.php';

mc_render_settings_page($page_slug, $values, $errors, $notice, $notice_type);

require MC_ABSPATH . 'mc-admin/admin-footer.php';
