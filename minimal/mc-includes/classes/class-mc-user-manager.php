<?php

/**
 * MC_User_Manager — User CRUD, authentication, and encrypted storage.
 *
 * Replaces the procedural user.php (minus session logic which lives in MC_Session).
 * All user data is stored in a sodium-encrypted PHP file.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * User CRUD and authentication manager.
 *
 * @since {version}
 */
class MC_User_Manager
{
	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Formatter
	 */
	private MC_Formatter $formatter;

	/**
	 * @since {version}
	 * @var MC_Capabilities
	 */
	private MC_Capabilities $capabilities;

	/**
	 * @since {version}
	 * @var MC_Session
	 */
	private MC_Session $session;

	/**
	 * Absolute path to the encrypted users file.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $users_file;

	/**
	 * Raw encryption key string (from config).
	 *
	 * @since {version}
	 * @var string
	 */
	private string $encryption_key;

	/**
	 * Absolute path to auth-attempt tracking file.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $auth_attempts_file;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks        $hooks          Hooks engine.
	 * @param MC_Formatter    $formatter      Formatter for sanitisation.
	 * @param MC_Capabilities $capabilities   Capabilities engine.
	 * @param MC_Session      $session        Session manager.
	 * @param string          $users_file     Absolute path to the users data file.
	 * @param string          $encryption_key Raw encryption key string.
	 */
	public function __construct(
		MC_Hooks $hooks,
		MC_Formatter $formatter,
		MC_Capabilities $capabilities,
		MC_Session $session,
		string $users_file,
		string $encryption_key
	) {

		$this->hooks          = $hooks;
		$this->formatter      = $formatter;
		$this->capabilities   = $capabilities;
		$this->session        = $session;
		$this->users_file     = $users_file;
		$this->encryption_key = $encryption_key;
		$this->auth_attempts_file = dirname($users_file) . '/auth-attempts.php';
	}

	/**
	 * Update the encryption key at runtime.
	 *
	 * Used during setup when a new key is generated after the service
	 * was already instantiated with the old (empty) key.
	 *
	 * @since {version}
	 *
	 * @param string $key New encryption key string.
	 * @return void
	 */
	public function set_encryption_key(string $key): void
	{
		$this->encryption_key = $key;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Encrypted storage
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Read and decrypt the users data file.
	 *
	 * @since {version}
	 *
	 * @return array List of user arrays, or empty array on failure.
	 */
	public function read_users(): array
	{

		if (!is_file($this->users_file) || !is_readable($this->users_file)) {
			return array();
		}

		$raw = file_get_contents($this->users_file);
		if (false === $raw) {
			return array();
		}

		// Strip the PHP die() header line.
		$lines = explode("\n", $raw, 2);
		if (count($lines) < 2) {
			return array();
		}

		$encoded = trim($lines[1]);
		if ('' === $encoded) {
			return array();
		}

		$decoded = base64_decode($encoded, true);
		if (false === $decoded) {
			return array();
		}

		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if (strlen($decoded) < $nonce_length) {
			return array();
		}

		$nonce      = substr($decoded, 0, $nonce_length);
		$ciphertext = substr($decoded, $nonce_length);
		$key        = $this->derive_encryption_key();

		try {
			$plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
		} catch (\SodiumException $e) {
			return array();
		}

		if (false === $plaintext) {
			return array();
		}

		$users = json_decode($plaintext, true);
		return is_array($users) ? $users : array();
	}

	/**
	 * Encrypt and write the users data file.
	 *
	 * @since {version}
	 *
	 * @param array $users List of user arrays.
	 * @return bool True on success.
	 */
	public function write_users(array $users): bool
	{

		$plaintext = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		$key       = $this->derive_encryption_key();
		$nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		try {
			$ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
		} catch (\SodiumException $e) {
			return false;
		}

		$blob    = base64_encode($nonce . $ciphertext);
		$content = "<?php die(); ?>\n" . $blob . "\n";

		$dir = dirname($this->users_file);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		return false !== file_put_contents($this->users_file, $content, LOCK_EX);
	}

	/**
	 * Derive a 32-byte sodium key from the configured encryption key string.
	 *
	 * @since {version}
	 *
	 * @return string 32-byte binary key.
	 */
	private function derive_encryption_key(): string
	{

		$decoded = base64_decode($this->encryption_key, true);
		if (false !== $decoded && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($decoded)) {
			return $decoded;
		}

		return hash('sha256', $this->encryption_key, true);
	}

	/**
	 * Read authentication-attempt records.
	 *
	 * @since {version}
	 *
	 * @return array<string, array<string, int>>
	 */
	private function read_auth_attempts(): array
	{

		if (!is_file($this->auth_attempts_file) || !is_readable($this->auth_attempts_file)) {
			return array();
		}

		$raw = file_get_contents($this->auth_attempts_file);
		if (false === $raw) {
			return array();
		}

		$lines = explode("\n", $raw, 2);
		if (count($lines) < 2) {
			return array();
		}

		$decoded = json_decode(trim($lines[1]), true);
		if (!is_array($decoded)) {
			return array();
		}

		return $decoded;
	}

	/**
	 * Write authentication-attempt records.
	 *
	 * @since {version}
	 *
	 * @param array<string, array<string, int>> $records Attempt map.
	 * @return void
	 */
	private function write_auth_attempts(array $records): void
	{

		$dir = dirname($this->auth_attempts_file);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$content = "<?php die(); ?>\n" . json_encode($records, JSON_UNESCAPED_UNICODE) . "\n";
		file_put_contents($this->auth_attempts_file, $content, LOCK_EX);
	}

	/**
	 * Build a stable key for auth throttling.
	 *
	 * @since {version}
	 *
	 * @param string $username Attempted username.
	 * @return string
	 */
	private function get_auth_attempt_key(string $username): string
	{

		$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			$ip = 'unknown';
		}

		return hash('sha256', strtolower($username) . '|' . $ip);
	}

	/**
	 * Apply adaptive delay and lockout checks before auth.
	 *
	 * @since {version}
	 *
	 * @param string $username Attempted username.
	 * @return true|MC_Error
	 */
	private function preflight_auth_attempt(string $username): true|MC_Error
	{

		$records = $this->read_auth_attempts();
		$key     = $this->get_auth_attempt_key($username);
		$now     = time();
		$record  = $records[$key] ?? array();
		$attempts = (int) ($record['attempts'] ?? 0);
		$last_failed = (int) ($record['last_failed'] ?? 0);
		$locked_until = (int) ($record['locked_until'] ?? 0);

		if ($locked_until > $now) {
			return new MC_Error('too_many_attempts', 'Too many login attempts. Please try again later.');
		}

		if ($last_failed > 0 && ($now - $last_failed) > 900) {
			$attempts = 0;
		}

		if ($attempts > 0) {
			$delay = min(5, $attempts);
			sleep($delay);
		}

		return true;
	}

	/**
	 * Record a failed login attempt and update lockout window.
	 *
	 * @since {version}
	 *
	 * @param string $username Attempted username.
	 * @return void
	 */
	private function register_auth_failure(string $username): void
	{

		$records     = $this->read_auth_attempts();
		$key         = $this->get_auth_attempt_key($username);
		$now         = time();
		$record      = $records[$key] ?? array();
		$attempts    = (int) ($record['attempts'] ?? 0);
		$last_failed = (int) ($record['last_failed'] ?? 0);

		if ($last_failed > 0 && ($now - $last_failed) > 900) {
			$attempts = 0;
		}

		$attempts++;

		$records[$key] = array(
			'attempts'     => $attempts,
			'last_failed'  => $now,
			'locked_until' => $attempts >= 5 ? $now + 900 : 0,
		);

		$this->write_auth_attempts($records);
	}

	/**
	 * Clear throttling state after successful authentication.
	 *
	 * @since {version}
	 *
	 * @param string $username Authenticated username.
	 * @return void
	 */
	private function clear_auth_failures(string $username): void
	{

		$records = $this->read_auth_attempts();
		$key     = $this->get_auth_attempt_key($username);

		if (isset($records[$key])) {
			unset($records[$key]);
			$this->write_auth_attempts($records);
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 *  User CRUD
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get a user by username.
	 *
	 * @since {version}
	 *
	 * @param string $username The username to look up.
	 * @return array|null User data array or null if not found.
	 */
	public function get_user(string $username): ?array
	{

		$users = $this->read_users();

		foreach ($users as $user) {
			if (($user['username'] ?? '') === $username) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Get a user by email address.
	 *
	 * @since {version}
	 *
	 * @param string $email Email to search for.
	 * @return array|null User data or null.
	 */
	public function get_user_by_email(string $email): ?array
	{

		$users = $this->read_users();

		foreach ($users as $user) {
			if (($user['email'] ?? '') === $email) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Create a new user.
	 *
	 * @since {version}
	 *
	 * @param array $data {
	 *     User data.
	 *
	 *     @type string $username     Required.
	 *     @type string $email        Required.
	 *     @type string $password     Required (plaintext, will be hashed).
	 *     @type string $role         Optional. Default 'contributor'.
	 *     @type string $display_name Optional.
	 * }
	 * @return true|MC_Error True on success, MC_Error on failure.
	 */
	public function create_user(array $data): true|MC_Error
	{

		if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
			return new MC_Error('missing_fields', 'Username, email, and password are required.');
		}

		$username = $this->formatter->sanitize_slug($data['username']);
		$email    = $this->formatter->sanitize_email($data['email']);

		if ('' === $email) {
			return new MC_Error('invalid_email', 'The email address is not valid.');
		}

		/**
		 * Filter user data before creation.
		 *
		 * @since {version}
		 *
		 * @param array $data User data.
		 */
		$data = $this->hooks->apply_filters('mc_pre_create_user', $data);

		$users = $this->read_users();

		foreach ($users as $existing) {
			if (($existing['username'] ?? '') === $username) {
				return new MC_Error('duplicate_username', 'A user with that username already exists.');
			}
			if (($existing['email'] ?? '') === $email) {
				return new MC_Error('duplicate_email', 'A user with that email already exists.');
			}
		}

		$users[] = array(
			'username'     => $username,
			'email'        => $email,
			'password'     => password_hash($data['password'], PASSWORD_BCRYPT),
			'role'         => $data['role'] ?? 'contributor',
			'display_name' => $data['display_name'] ?? $username,
			'created'      => gmdate('c'),
			'meta'         => $data['meta'] ?? array(),
		);

		if (!$this->write_users($users)) {
			return new MC_Error('write_failed', 'Failed to write user data.');
		}

		$this->hooks->do_action('mc_user_created', $username);

		return true;
	}

	/**
	 * Update an existing user.
	 *
	 * @since {version}
	 *
	 * @param string $username The user to update.
	 * @param array  $data     Fields to update (email, display_name, role, password, meta).
	 * @return true|MC_Error True on success.
	 */
	public function update_user(string $username, array $data): true|MC_Error
	{

		/**
		 * Filter user data before update.
		 *
		 * @since {version}
		 *
		 * @param array  $data     Update data.
		 * @param string $username Username being updated.
		 */
		$data = $this->hooks->apply_filters('mc_pre_update_user', $data, $username);

		$users = $this->read_users();
		$found = false;

		foreach ($users as &$user) {
			if (($user['username'] ?? '') !== $username) {
				continue;
			}

			$found = true;

			if (isset($data['email'])) {
				$user['email'] = $this->formatter->sanitize_email($data['email']);
			}
			if (isset($data['display_name'])) {
				$user['display_name'] = $this->formatter->sanitize_text($data['display_name']);
			}
			if (isset($data['role'])) {
				$user['role'] = $this->formatter->sanitize_slug($data['role']);
			}
			if (!empty($data['password'])) {
				$user['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
			}
			if (isset($data['meta']) && is_array($data['meta'])) {
				$user['meta'] = array_merge($user['meta'] ?? array(), $data['meta']);
			}

			break;
		}
		unset($user);

		if (!$found) {
			return new MC_Error('user_not_found', 'User not found.');
		}

		if (!$this->write_users($users)) {
			return new MC_Error('write_failed', 'Failed to write user data.');
		}

		$this->hooks->do_action('mc_user_updated', $username);

		return true;
	}

	/**
	 * Delete a user.
	 *
	 * @since {version}
	 *
	 * @param string $username Username to delete.
	 * @return true|MC_Error True on success.
	 */
	public function delete_user(string $username): true|MC_Error
	{

		$users    = $this->read_users();
		$filtered = array();
		$found    = false;

		foreach ($users as $user) {
			if (($user['username'] ?? '') === $username) {
				$found = true;
				continue;
			}
			$filtered[] = $user;
		}

		if (!$found) {
			return new MC_Error('user_not_found', 'User not found.');
		}

		if (!$this->write_users($filtered)) {
			return new MC_Error('write_failed', 'Failed to write user data.');
		}

		$this->hooks->do_action('mc_user_deleted', $username);

		return true;
	}

	/**
	 * Get all users (passwords excluded).
	 *
	 * @since {version}
	 *
	 * @return array List of user arrays.
	 */
	public function get_users(): array
	{

		$users     = $this->read_users();
		$sanitised = array();

		foreach ($users as $user) {
			unset($user['password']);
			$sanitised[] = $user;
		}

		return $sanitised;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Authentication
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Authenticate a user by username and password.
	 *
	 * @since {version}
	 *
	 * @param string $username The username.
	 * @param string $password The plaintext password.
	 * @return array|MC_Error User data on success, MC_Error on failure.
	 */
	public function authenticate(string $username, string $password): array|MC_Error
	{

		$throttle = $this->preflight_auth_attempt($username);
		if (is_a($throttle, 'MC_Error')) {
			return $throttle;
		}

		/**
		 * Pre-authentication filter.
		 *
		 * @since {version}
		 *
		 * @param null|array|MC_Error $result   Null to proceed.
		 * @param string              $username Attempted username.
		 * @param string              $password Attempted password.
		 */
		$pre = $this->hooks->apply_filters('mc_pre_authenticate', null, $username, $password);

		if (null !== $pre) {
			return $pre;
		}

		$user = $this->get_user($username);

		if (null === $user) {
			$this->register_auth_failure($username);
			return new MC_Error('invalid_credentials', 'Invalid username or password.');
		}

		if (!password_verify($password, $user['password'] ?? '')) {
			$this->register_auth_failure($username);
			return new MC_Error('invalid_credentials', 'Invalid username or password.');
		}

		$this->clear_auth_failures($username);

		return $user;
	}

	/**
	 * Log a user in (authenticate + set session).
	 *
	 * @since {version}
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 * @return array|MC_Error User data on success, MC_Error on failure.
	 */
	public function login(string $username, string $password): array|MC_Error
	{

		$result = $this->authenticate($username, $password);

		if (is_a($result, 'MC_Error')) {
			return $result;
		}

		$this->session->set_auth($username);

		$this->hooks->do_action('mc_login', $username);

		return $result;
	}

	/**
	 * Log the current user out.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function logout(): void
	{

		$username = $this->get_current_user_id();

		$this->session->destroy();

		$this->hooks->do_action('mc_logout', $username);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Current user helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the currently logged-in user's data.
	 *
	 * @since {version}
	 *
	 * @return array|null User data or null if not logged in.
	 */
	public function get_current_user(): ?array
	{

		$username = $this->session->get_current_username();

		if (null === $username) {
			return null;
		}

		return $this->get_user($username);
	}

	/**
	 * Get the username of the currently logged-in user.
	 *
	 * @since {version}
	 *
	 * @return string Username or empty string.
	 */
	public function get_current_user_id(): string
	{

		return $this->session->get_current_username() ?? '';
	}

	/**
	 * Check if a user is logged in.
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function is_logged_in(): bool
	{

		return $this->session->is_active();
	}

	/**
	 * Check whether the currently logged-in user has a capability.
	 *
	 * @since {version}
	 *
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function current_user_can(string $capability): bool
	{

		$user = $this->get_current_user();

		if (null === $user) {
			return false;
		}

		return $this->capabilities->user_can($user, $capability);
	}

	/**
	 * Get the logout URL.
	 *
	 * @since {version}
	 *
	 * @return string Logout URL.
	 */
	public function logout_url(): string
	{

		return mc_admin_url('login.php?action=logout');
	}
}
