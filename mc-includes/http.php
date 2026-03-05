<?php
/**
 * MinimalCMS HTTP Helpers
 *
 * Request handling, nonce system, redirects, and header utilities.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 *  Nonce system (CSRF protection)
 * -------------------------------------------------------------------------
 */

/**
 * Generate a nonce token tied to an action and the current session.
 *
 * @since 1.0.0
 *
 * @param string $action The action name the nonce protects.
 * @return string The nonce token (hex string).
 */
function mc_create_nonce( string $action = '-1' ): string {

	$tick = mc_nonce_tick();
	$uid  = mc_get_current_user_id();
	$key  = MC_SECRET_KEY;

	return substr(
		hash_hmac( 'sha256', $tick . '|' . $action . '|' . $uid, $key ),
		0,
		20
	);
}

/**
 * Verify a nonce token.
 *
 * @since 1.0.0
 *
 * @param string $nonce  The nonce to verify.
 * @param string $action The expected action name.
 * @return bool True if the nonce is valid.
 */
function mc_verify_nonce( string $nonce, string $action = '-1' ): bool {

	if ( '' === $nonce ) {
		return false;
	}

	$uid = mc_get_current_user_id();
	$key = MC_SECRET_KEY;

	// Check current tick.
	$tick     = mc_nonce_tick();
	$expected = substr(
		hash_hmac( 'sha256', $tick . '|' . $action . '|' . $uid, $key ),
		0,
		20
	);

	if ( hash_equals( $expected, $nonce ) ) {
		return true;
	}

	// Check previous tick (allows a 1-tick grace period).
	$expected_prev = substr(
		hash_hmac( 'sha256', ( $tick - 1 ) . '|' . $action . '|' . $uid, $key ),
		0,
		20
	);

	return hash_equals( $expected_prev, $nonce );
}

/**
 * Return a nonce tick value that changes every 12 hours.
 *
 * @since 1.0.0
 *
 * @return int Current tick.
 */
function mc_nonce_tick(): int {

	$nonce_life = 86400; // 24 hours total (two 12-hour ticks).
	return (int) ceil( time() / ( $nonce_life / 2 ) );
}

/**
 * Output a hidden form field containing a nonce.
 *
 * @since 1.0.0
 *
 * @param string $action The action to protect.
 * @param string $name   The field name. Default '_mc_nonce'.
 * @return void
 */
function mc_nonce_field( string $action = '-1', string $name = '_mc_nonce' ): void {

	$nonce = mc_create_nonce( $action );
	echo '<input type="hidden" name="' . mc_esc_attr( $name ) . '" value="' . mc_esc_attr( $nonce ) . '" />' . "\n";
}

/**
 * Build a URL with a nonce query parameter appended.
 *
 * @since 1.0.0
 *
 * @param string $url    The base URL.
 * @param string $action The nonce action.
 * @param string $name   Query parameter name. Default '_mc_nonce'.
 * @return string URL with nonce.
 */
function mc_nonce_url( string $url, string $action = '-1', string $name = '_mc_nonce' ): string {

	$nonce     = mc_create_nonce( $action );
	$separator = str_contains( $url, '?' ) ? '&' : '?';

	return $url . $separator . rawurlencode( $name ) . '=' . rawurlencode( $nonce );
}

/*
 * -------------------------------------------------------------------------
 *  Redirect helpers
 * -------------------------------------------------------------------------
 */

/**
 * Send an HTTP redirect and exit.
 *
 * @since 1.0.0
 *
 * @param string $url    Destination URL.
 * @param int    $status HTTP status code. Default 302.
 * @return never
 */
function mc_redirect( string $url, int $status = 302 ): never {

	header( 'Location: ' . $url, true, $status );
	exit;
}

/**
 * Redirect with a "safe" status (303 See Other) after a POST handler.
 *
 * @since 1.0.0
 *
 * @param string $url Destination URL.
 * @return never
 */
function mc_safe_redirect( string $url ): never {

	mc_redirect( $url, 303 );
}

/*
 * -------------------------------------------------------------------------
 *  Request introspection
 * -------------------------------------------------------------------------
 */

/**
 * Get the current request method.
 *
 * @since 1.0.0
 *
 * @return string Uppercase method name (GET, POST, etc.).
 */
function mc_request_method(): string {

	return strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
}

/**
 * Check whether the current request is a POST.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_post_request(): bool {

	return 'POST' === mc_request_method();
}

/**
 * Check whether the current request is an AJAX request.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_ajax_request(): bool {

	if ( defined( 'MC_DOING_AJAX' ) && MC_DOING_AJAX ) {
		return true;
	}

	return 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '' );
}

/**
 * Send a JSON response and exit.
 *
 * @since 1.0.0
 *
 * @param mixed $data   Data to encode.
 * @param int   $status HTTP status code. Default 200.
 * @return never
 */
function mc_send_json( mixed $data, int $status = 200 ): never {

	http_response_code( $status );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	exit;
}

/**
 * Send a JSON success response.
 *
 * @since 1.0.0
 *
 * @param mixed $data Optional data.
 * @return never
 */
function mc_send_json_success( mixed $data = null ): never {

	mc_send_json(
		array(
			'success' => true,
			'data'    => $data,
		),
		200
	);
}

/**
 * Send a JSON error response.
 *
 * @since 1.0.0
 *
 * @param mixed $data   Optional error data.
 * @param int   $status HTTP status. Default 400.
 * @return never
 */
function mc_send_json_error( mixed $data = null, int $status = 400 ): never {

	mc_send_json(
		array(
			'success' => false,
			'data'    => $data,
		),
		$status
	);
}

/*
 * -------------------------------------------------------------------------
 *  Header helpers
 * -------------------------------------------------------------------------
 */

/**
 * Send a 404 Not Found status header.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_send_404(): void {

	http_response_code( 404 );
}

/**
 * Prevent browsers from caching the response.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_no_cache_headers(): void {

	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
}
