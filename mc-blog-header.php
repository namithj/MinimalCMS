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

// Step 2: Route — parse the request and resolve content.
$app->router()->parse_request();

// Step 3: Render.
if ( $app->router()->is_admin() ) {
	// Determine which admin page file to load.
	$query          = $app->router()->get_query();
	$admin_path     = preg_replace( '#^mc-admin/?#', '', $query['path'] ?? '' );
	$admin_page_file = ( '' === $admin_path ) ? 'index.php' : $admin_path;
	$admin_file      = MC_ABSPATH . 'mc-admin/' . $admin_page_file;

	if ( is_file( $admin_file ) ) {
		require_once $admin_file;
	} else {
		http_response_code( 404 );
		echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Admin page not found</h1></body></html>';
	}
} else {
	// Front-end: resolve and include the appropriate template.
	$app->template_loader()->load();
}
