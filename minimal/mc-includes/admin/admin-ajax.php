<?php

/**
 * MinimalCMS — Admin AJAX Handler
 *
 * Dispatches AJAX requests to registered action callbacks.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once MC_INC . 'admin/bootstrap.php';

// Verify this is an AJAX request.
if (! mc_is_ajax_request() && ! mc_is_post_request()) {
	mc_send_json_error('Invalid request.', 400);
}

$action = mc_sanitize_slug(mc_input('action', 'post') ?: mc_input('action', 'get'));

if (empty($action)) {
	mc_send_json_error('Missing action parameter.', 400);
}

$priv_hook   = 'mc_ajax_' . $action;
$nopriv_hook = 'mc_ajax_nopriv_' . $action;
$nonce       = (string) (mc_input('_mc_nonce', 'post') ?: mc_input('_mc_nonce', 'get') ?: '');

/*
 * Logged-in user actions.
 * Convention: mc_ajax_{action}
 */
if (mc_is_logged_in()) {
	if (!mc_has_action($priv_hook)) {
		mc_send_json_error('Unknown action: ' . $action, 400);
	}

	if (!mc_verify_nonce($nonce, 'ajax_' . $action)) {
		mc_send_json_error('Invalid security token.', 403);
	}

	/**
	 * Filter required capability for a logged-in AJAX action.
	 *
	 * @since {version}
	 *
	 * @param string $capability Capability name. Empty means no additional gate.
	 * @param string $action     Action slug.
	 */
	$capability = (string) mc_apply_filters('mc_ajax_required_capability', '', $action);

	if ('' !== $capability && !mc_current_user_can($capability)) {
		mc_send_json_error('Insufficient permissions.', 403);
	}

	mc_do_action($priv_hook);
	exit;
} else {
	/*
	 * No-priv (public) actions.
	 * Convention: mc_ajax_nopriv_{action}
	 */
	if (!mc_has_action($nopriv_hook)) {
		mc_send_json_error('Unknown action: ' . $action, 400);
	}

	/**
	 * Filter whether a public AJAX action is allowed.
	 *
	 * @since {version}
	 *
	 * @param bool   $allowed Default false (explicit opt-in).
	 * @param string $action  Action slug.
	 */
	$allow_nopriv = (bool) mc_apply_filters('mc_ajax_allow_nopriv', false, $action);

	if (!$allow_nopriv) {
		mc_send_json_error('Authentication required.', 403);
	}

	/**
	 * Filter whether public AJAX action requires nonce.
	 *
	 * @since {version}
	 *
	 * @param bool   $required Default true.
	 * @param string $action   Action slug.
	 */
	$require_nopriv_nonce = (bool) mc_apply_filters('mc_ajax_nopriv_requires_nonce', true, $action);

	if ($require_nopriv_nonce && !mc_verify_nonce($nonce, 'ajax_nopriv_' . $action)) {
		mc_send_json_error('Invalid security token.', 403);
	}

	mc_do_action($nopriv_hook);
}

// If we reach here, no handler ran.
mc_send_json_error('Unknown action: ' . $action, 400);
