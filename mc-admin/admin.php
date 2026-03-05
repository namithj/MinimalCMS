<?php
/**
 * MinimalCMS Admin Bootstrap
 *
 * Boots the CMS (when accessed directly) and handles authentication checks.
 * Each admin page includes this file at the top — similar to WordPress's
 * wp-admin/admin.php.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

// Load the CMS environment if not already loaded (direct access).
if ( ! defined( 'MC_ABSPATH' ) ) {
	require_once dirname( __DIR__ ) . '/mc-load.php';
}

if ( ! defined( 'MC_ADMIN' ) ) {
	define( 'MC_ADMIN', true );
}

mc_start_session();

// Determine which admin page is being requested — used for auth decisions.
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$admin_page  = 'index.php';
if ( preg_match( '#/mc-admin/([a-zA-Z0-9_-]+\.php)#', $request_uri, $_m ) ) {
	$admin_page = $_m[1];
}
unset( $_m );

// ── First-run detection: redirect to setup if no users exist ───────────
$existing_users = mc_get_users();
$needs_setup    = ! is_array( $existing_users ) || 0 === count( $existing_users );

if ( $needs_setup && 'setup.php' !== $admin_page ) {
	mc_redirect( mc_admin_url( 'setup.php' ) );
	exit;
}

// Login and setup pages are accessible without authentication.
$public_pages = array( 'login.php', 'setup.php' );

if ( ! in_array( $admin_page, $public_pages, true ) && ! mc_is_logged_in() ) {
	mc_redirect( mc_admin_url( 'login.php' ) );
}

// Check basic admin capability for non-public pages.
if ( ! in_array( $admin_page, $public_pages, true ) && ! mc_current_user_can( 'view_admin' ) ) {
	http_response_code( 403 );
	echo '<!DOCTYPE html><html><head><title>Forbidden</title></head><body><h1>403 — Access Denied</h1><p>You do not have permission to access this page.</p></body></html>';
	exit;
}

/**
 * Fires at the start of every admin page load (after auth check).
 *
 * @since 1.0.0
 */
mc_do_action( 'mc_admin_init' );
