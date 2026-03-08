<?php
/**
 * MinimalCMS HTTP Helpers
 *
 * Request handling, nonce system, redirects, and header utilities.
 * Replaces the procedural http.php with a hookable class.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_Http
 *
 * Nonces, redirects, request introspection, and JSON responses.
 *
 * @since {version}
 */
class MC_Http {

	/**
	 * The hooks engine.
	 *
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Secret key for HMAC operations (nonces).
	 *
	 * @since {version}
	 * @var string
	 */
	private string $secret_key;

	/**
	 * Current authenticated user ID for nonce generation.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $current_user_id = '';

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks $hooks      The hooks engine.
	 * @param string   $secret_key Secret key for HMAC operations.
	 */
	public function __construct(MC_Hooks $hooks, string $secret_key = '') {

		$this->hooks      = $hooks;
		$this->secret_key = $secret_key;
	}

	/**
	 * Set the current user ID for nonce generation/verification.
	 *
	 * Called by MC_App after user manager is initialised.
	 *
	 * @since {version}
	 *
	 * @param string $user_id The current user's identifier.
	 * @return void
	 */
	public function set_current_user_id(string $user_id): void {

		$this->current_user_id = $user_id;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Nonce system (CSRF protection)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Generate a nonce token tied to an action and the current session.
	 *
	 * @since {version}
	 *
	 * @param string $action The action name the nonce protects.
	 * @return string The nonce token (hex string).
	 */
	public function create_nonce(string $action = '-1'): string {

		$tick = $this->nonce_tick();

		return substr(
			hash_hmac('sha256', $tick . '|' . $action . '|' . $this->current_user_id, $this->secret_key),
			0,
			20
		);
	}

	/**
	 * Verify a nonce token.
	 *
	 * @since {version}
	 *
	 * @param string $nonce  The nonce to verify.
	 * @param string $action The expected action name.
	 * @return bool True if the nonce is valid.
	 */
	public function verify_nonce(string $nonce, string $action = '-1'): bool {

		if ('' === $nonce) {
			return false;
		}

		$tick = $this->nonce_tick();

		// Check current tick.
		$expected = substr(
			hash_hmac('sha256', $tick . '|' . $action . '|' . $this->current_user_id, $this->secret_key),
			0,
			20
		);

		if (hash_equals($expected, $nonce)) {
			return true;
		}

		// Check previous tick (allows a 1-tick grace period).
		$expected_prev = substr(
			hash_hmac('sha256', ($tick - 1) . '|' . $action . '|' . $this->current_user_id, $this->secret_key),
			0,
			20
		);

		return hash_equals($expected_prev, $nonce);
	}

	/**
	 * Return a nonce tick value that changes every 12 hours.
	 *
	 * @since {version}
	 *
	 * @return int Current tick.
	 */
	public function nonce_tick(): int {

		/**
		 * Filter the nonce tick length in seconds.
		 *
		 * @since {version}
		 *
		 * @param int $nonce_life Full nonce lifetime in seconds (two ticks). Default 86400.
		 */
		$nonce_life = $this->hooks->apply_filters('mc_nonce_tick_length', 86400);

		return (int) ceil(time() / ($nonce_life / 2));
	}

	/**
	 * Output a hidden form field containing a nonce.
	 *
	 * @since {version}
	 *
	 * @param string $action The action to protect.
	 * @param string $name   The field name. Default '_mc_nonce'.
	 * @return void
	 */
	public function nonce_field(string $action = '-1', string $name = '_mc_nonce'): void {

		$nonce     = $this->create_nonce($action);
		$safe_name = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$safe_val  = htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		echo '<input type="hidden" name="' . $safe_name . '" value="' . $safe_val . '" />' . "\n";
	}

	/**
	 * Build a URL with a nonce query parameter appended.
	 *
	 * @since {version}
	 *
	 * @param string $url    The base URL.
	 * @param string $action The nonce action.
	 * @param string $name   Query parameter name. Default '_mc_nonce'.
	 * @return string URL with nonce.
	 */
	public function nonce_url(string $url, string $action = '-1', string $name = '_mc_nonce'): string {

		$nonce     = $this->create_nonce($action);
		$separator = str_contains($url, '?') ? '&' : '?';

		return $url . $separator . rawurlencode($name) . '=' . rawurlencode($nonce);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Redirect helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Send an HTTP redirect and exit.
	 *
	 * @since {version}
	 *
	 * @param string $url    Destination URL.
	 * @param int    $status HTTP status code. Default 302.
	 * @return never
	 */
	public function redirect(string $url, int $status = 302): never {

		header('Location: ' . $url, true, $status);
		exit;
	}

	/**
	 * Redirect with safe status (303 See Other) after a POST handler.
	 *
	 * Validates the target against allowed redirect hosts.
	 *
	 * @since {version}
	 *
	 * @param string $url Destination URL.
	 * @return never
	 */
	public function safe_redirect(string $url): never {

		/**
		 * Filter the allowed hosts for safe redirects.
		 *
		 * @since {version}
		 *
		 * @param string[] $hosts Allowed hostnames.
		 */
		$allowed = $this->hooks->apply_filters('mc_allowed_redirect_hosts', array());

		if (!empty($allowed)) {
			$parsed = parse_url($url);

			if (isset($parsed['host']) && !in_array($parsed['host'], $allowed, true)) {
				$url = '/';
			}
		}

		$this->redirect($url, 303);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Request introspection
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the current request method.
	 *
	 * @since {version}
	 *
	 * @return string Uppercase method name (GET, POST, etc.).
	 */
	public function request_method(): string {

		return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
	}

	/**
	 * Check whether the current request is a POST.
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function is_post_request(): bool {

		return 'POST' === $this->request_method();
	}

	/**
	 * Check whether the current request is an AJAX request.
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function is_ajax_request(): bool {

		if (defined('MC_DOING_AJAX') && MC_DOING_AJAX) {
			return true;
		}

		return 'xmlhttprequest' === strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
	}

	/**
	 * Retrieve and optionally sanitise a value from a superglobal.
	 *
	 * @since {version}
	 *
	 * @param string        $key      Parameter key.
	 * @param string        $method   'GET', 'POST', 'REQUEST', 'COOKIE', or 'SERVER'.
	 * @param callable|null $sanitize Optional sanitisation callback.
	 * @return mixed Raw or sanitised value, or null if not present.
	 */
	public function input(string $key, string $method = 'REQUEST', ?callable $sanitize = null): mixed {

		$source = match (strtoupper($method)) {
			'GET'    => $_GET,
			'POST'   => $_POST,
			'COOKIE' => $_COOKIE,
			'SERVER' => $_SERVER,
			default  => $_REQUEST,
		};

		if (!isset($source[$key])) {
			return null;
		}

		$value = $source[$key];

		if (null !== $sanitize) {
			$value = call_user_func($sanitize, $value);
		}

		return $value;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Responses
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Send a JSON response and exit.
	 *
	 * @since {version}
	 *
	 * @param mixed $data   Data to encode.
	 * @param int   $status HTTP status code. Default 200.
	 * @return never
	 */
	public function send_json(mixed $data, int $status = 200): never {

		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	/**
	 * Send a JSON success response.
	 *
	 * @since {version}
	 *
	 * @param mixed $data Optional data.
	 * @return never
	 */
	public function send_json_success(mixed $data = null): never {

		$this->send_json(
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
	 * @since {version}
	 *
	 * @param mixed $data   Optional error data.
	 * @param int   $status HTTP status. Default 400.
	 * @return never
	 */
	public function send_json_error(mixed $data = null, int $status = 400): never {

		$this->send_json(
			array(
				'success' => false,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Send a 404 Not Found status header.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function send_404(): void {

		http_response_code(404);
	}

	/**
	 * Prevent browsers from caching the response.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function no_cache_headers(): void {

		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');
	}
}
