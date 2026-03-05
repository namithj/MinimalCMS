<?php
/**
 * MinimalCMS — Admin AJAX Handler
 *
 * Dispatches AJAX requests to registered action callbacks.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

// Verify this is an AJAX request.
if ( ! mc_is_ajax_request() && ! mc_is_post_request() ) {
	mc_send_json_error( 'Invalid request.', 400 );
}

$action = mc_sanitize_slug( mc_input( 'action', 'post' ) ?: mc_input( 'action', 'get' ) );

if ( empty( $action ) ) {
	mc_send_json_error( 'Missing action parameter.', 400 );
}

/*
 * Logged-in user actions.
 * Convention: mc_ajax_{action}
 */
if ( mc_is_logged_in() ) {
	mc_do_action( 'mc_ajax_' . $action );
}

/*
 * No-priv (public) actions.
 * Convention: mc_ajax_nopriv_{action}
 */
mc_do_action( 'mc_ajax_nopriv_' . $action );

// If we reach here, no handler ran.
mc_send_json_error( 'Unknown action: ' . $action, 400 );
