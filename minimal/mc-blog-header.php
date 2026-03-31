<?php

/**
 * MinimalCMS Blog Header
 *
 * Orchestrates the three-step lifecycle: Boot → Route → Render.
 *
 * @package MinimalCMS
 * @since   {version}
 */

// Step 1: Boot — load the entire CMS environment via MC_App.
require_once __DIR__ . '/mc-load.php';

$app = MC_App::instance();

// Step 1b: Redirect to setup wizard when setup is needed.
if ($app->setup()->needs_setup()) {
	mc_redirect(mc_admin_url('setup.php'));
	exit;
}

$app->http()->send_security_headers();

// Step 2: Route — parse the request and resolve content.
// Start the session for preview requests so the nonce can be tied to the user.
if (isset($_GET['preview']) && isset($_GET['key'])) {
	mc_start_session();
}

$app->router()->parse_request();

// Step 3: Render.
if ($app->router()->is_admin()) {
	// Determine which admin page file to load.
	$query           = $app->router()->get_query();
	$admin_route     = defined('MC_ADMIN_ROUTE') ? MC_ADMIN_ROUTE : 'admin';
	$admin_path      = preg_replace('#^' . preg_quote($admin_route, '#') . '/?#', '', $query['path'] ?? '');
	$admin_page_file = ('' === $admin_path) ? 'index.php' : $admin_path;
	$admin_page_file = basename(rawurldecode(str_replace('\\', '/', (string) $admin_page_file)));

	if (!preg_match('/^[a-z0-9][a-z0-9\-]*\.php$/i', $admin_page_file)) {
		http_response_code(404);
		echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Admin page not found</h1></body></html>';
		exit;
	}

	if ('admin-ajax.php' === $admin_page_file) {
		require_once MC_INC . 'admin/admin-ajax.php';
		exit;
	}

	$template = mc_get_admin_theme_page_template($admin_page_file);

	if (false !== $template) {
		require_once $template;
	} else {
		http_response_code(404);
		echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Admin page not found</h1></body></html>';
	}
} else {
	// Front-end: resolve and include the appropriate template.
	$app->template_loader()->load();
}
