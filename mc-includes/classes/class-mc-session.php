<?php
/**
 * MC_Session — PHP session lifecycle management.
 *
 * Extracted from the procedural user.php. Handles session start, auth
 * storage, and secure cookie configuration.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Session lifecycle management.
 *
 * @since {version}
 */
class MC_Session {

	/**
	 * Hooks instance.
	 *
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Session save path.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $session_dir;

	/**
	 * Session lifetime in seconds.
	 *
	 * @since {version}
	 * @var int
	 */
	private int $lifetime;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks $hooks       Hooks engine.
	 * @param string   $session_dir Absolute path to session save directory.
	 * @param int      $lifetime    Session lifetime in seconds. Default 7200 (2 hours).
	 */
	public function __construct(MC_Hooks $hooks, string $session_dir, int $lifetime = 7200) {

		$this->hooks       = $hooks;
		$this->session_dir = $session_dir;
		$this->lifetime    = $lifetime;
	}

	/**
	 * Start PHP session with secure cookie parameters.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function start(): void {

		if (PHP_SESSION_ACTIVE === session_status()) {
			return;
		}

		if (!is_dir($this->session_dir)) {
			mkdir($this->session_dir, 0700, true);
		}

		session_save_path($this->session_dir);

		$cookie_params = array(
			'lifetime' => $this->lifetime,
			'path'     => '/',
			'secure'   => (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']),
			'httponly'  => true,
			'samesite' => 'Strict',
		);

		/**
		 * Filter session cookie parameters.
		 *
		 * @since {version}
		 *
		 * @param array $cookie_params Cookie parameter array.
		 */
		$cookie_params = $this->hooks->apply_filters('mc_session_cookie_params', $cookie_params);

		session_set_cookie_params($cookie_params);
		session_name('mc_session');
		session_start();

		$this->hooks->do_action('mc_session_started');
	}

	/**
	 * Store authenticated user in session.
	 *
	 * @since {version}
	 *
	 * @param string $username The authenticated username.
	 * @return void
	 */
	public function set_auth(string $username): void {

		$this->start();

		$_SESSION['mc_user']       = $username;
		$_SESSION['mc_login_time'] = time();

		session_regenerate_id(true);
	}

	/**
	 * Destroy session and clear cookies.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function destroy(): void {

		$this->start();

		$_SESSION = array();

		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		session_destroy();

		$this->hooks->do_action('mc_session_destroyed');
	}

	/**
	 * Get currently authenticated username from session.
	 *
	 * @since {version}
	 *
	 * @return string|null Username or null if not authenticated.
	 */
	public function get_current_username(): ?string {

		$this->start();

		if (empty($_SESSION['mc_user'])) {
			return null;
		}

		// Check session timeout.
		$login_time = $_SESSION['mc_login_time'] ?? 0;
		if ((time() - $login_time) > $this->lifetime) {
			$this->destroy();
			return null;
		}

		return $_SESSION['mc_user'];
	}

	/**
	 * Check if a user session is active.
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function is_active(): bool {

		return null !== $this->get_current_username();
	}
}
