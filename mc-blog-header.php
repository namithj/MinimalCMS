<?php
/**
 * MinimalCMS Blog Header
 *
 * Orchestrates the three-step lifecycle: Boot → Route → Render.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

// Step 1: Boot — load the entire CMS environment.
require_once __DIR__ . '/mc-load.php';

// Step 2: Route — parse the request and resolve content.
mc_parse_request();

global $mc_query;

// Step 3: Render.
if ( ! empty( $mc_query['is_admin'] ) ) {
	// Determine which admin page file to load.
	$request_path   = mc_get_request_path();
	$admin_path     = preg_replace( '#^mc-admin/?#', '', $request_path );
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
	mc_load_template();
}
