<?php
/**
 * MinimalCMS User System
 *
 * Manages user data stored in a sodium-encrypted PHP file.
 * Handles authentication, sessions, and user CRUD operations.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 *  Encrypted user file I/O
 * -------------------------------------------------------------------------
 */

/**
 * Get the path to the encrypted users file.
 *
 * @since 1.0.0
 *
 * @return string Absolute path.
 */
function mc_users_file_path(): string {

	return MC_DATA_DIR . 'users.php';
}

/**
 * Read and decrypt the users data file.
 *
 * @since 1.0.0
 *
 * @return array List of user arrays, or empty array on failure.
 */
function mc_read_users(): array {

	$file = mc_users_file_path();

	if ( ! is_file( $file ) || ! is_readable( $file ) ) {
		return array();
	}

	$raw = file_get_contents( $file );
	if ( false === $raw ) {
		return array();
	}

	// Strip the PHP die() header line.
	$lines = explode( "\n", $raw, 2 );

	if ( count( $lines ) < 2 ) {
		return array();
	}

	$encoded = trim( $lines[1] );

	if ( '' === $encoded ) {
		return array();
	}

	$decoded = base64_decode( $encoded, true );
	if ( false === $decoded ) {
		return array();
	}

	$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

	if ( strlen( $decoded ) < $nonce_length ) {
		return array();
	}

	$nonce      = substr( $decoded, 0, $nonce_length );
	$ciphertext = substr( $decoded, $nonce_length );
	$key        = mc_derive_encryption_key();

	try {
		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
	} catch ( \SodiumException $e ) {
		return array();
	}

	if ( false === $plaintext ) {
		return array();
	}

	$users = json_decode( $plaintext, true );

	if ( ! is_array( $users ) ) {
		return array();
	}

	return $users;
}

/**
 * Encrypt and write the users data file.
 *
 * @since 1.0.0
 *
 * @param array $users List of user arrays.
 * @return bool True on success.
 */
function mc_write_users( array $users ): bool {

	$plaintext = json_encode( $users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	$key       = mc_derive_encryption_key();
	$nonce     = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

	try {
		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
	} catch ( \SodiumException $e ) {
		return false;
	}

	$blob    = base64_encode( $nonce . $ciphertext );
	$content = "<?php die(); ?>\n" . $blob . "\n";

	$file = mc_users_file_path();
	$dir  = dirname( $file );

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}

	return false !== file_put_contents( $file, $content, LOCK_EX );
}

/**
 * Derive a 32-byte sodium key from the configured encryption key string.
 *
 * @since 1.0.0
 *
 * @return string 32-byte binary key.
 */
function mc_derive_encryption_key(): string {

	// Allow runtime override (e.g. setup wizard generates keys mid-request
	// after the MC_ENCRYPTION_KEY constant was already defined with the old value).
	$configured = $GLOBALS['mc_config']['encryption_key'] ?? MC_ENCRYPTION_KEY;

	// If it looks like a valid base64-encoded 32-byte key, decode it.
	$decoded = base64_decode( $configured, true );
	if ( false !== $decoded && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen( $decoded ) ) {
		return $decoded;
	}

	// Otherwise derive a key using a hash.
	return hash( 'sha256', $configured, true );
}

/*
 * -------------------------------------------------------------------------
 *  User CRUD
 * -------------------------------------------------------------------------
 */

/**
 * Get a user by username.
 *
 * @since 1.0.0
 *
 * @param string $username The username to look up.
 * @return array|null User data array or null if not found.
 */
function mc_get_user( string $username ): ?array {

	$users = mc_read_users();

	foreach ( $users as $user ) {
		if ( ( $user['username'] ?? '' ) === $username ) {
			return $user;
		}
	}

	return null;
}

/**
 * Get a user by email address.
 *
 * @since 1.0.0
 *
 * @param string $email Email to search for.
 * @return array|null User data or null.
 */
function mc_get_user_by_email( string $email ): ?array {

	$users = mc_read_users();

	foreach ( $users as $user ) {
		if ( ( $user['email'] ?? '' ) === $email ) {
			return $user;
		}
	}

	return null;
}

/**
 * Create a new user.
 *
 * @since 1.0.0
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
function mc_create_user( array $data ): true|MC_Error {

	if ( empty( $data['username'] ) || empty( $data['email'] ) || empty( $data['password'] ) ) {
		return new MC_Error( 'missing_fields', 'Username, email, and password are required.' );
	}

	$username = mc_sanitize_slug( $data['username'] );
	$email    = mc_sanitize_email( $data['email'] );

	if ( '' === $email ) {
		return new MC_Error( 'invalid_email', 'The email address is not valid.' );
	}

	$users = mc_read_users();

	// Check for duplicates.
	foreach ( $users as $existing ) {
		if ( ( $existing['username'] ?? '' ) === $username ) {
			return new MC_Error( 'duplicate_username', 'A user with that username already exists.' );
		}
		if ( ( $existing['email'] ?? '' ) === $email ) {
			return new MC_Error( 'duplicate_email', 'A user with that email already exists.' );
		}
	}

	$users[] = array(
		'username'     => $username,
		'email'        => $email,
		'password'     => password_hash( $data['password'], PASSWORD_BCRYPT ),
		'role'         => $data['role'] ?? 'contributor',
		'display_name' => $data['display_name'] ?? $username,
		'created'      => gmdate( 'c' ),
		'meta'         => $data['meta'] ?? array(),
	);

	if ( ! mc_write_users( $users ) ) {
		return new MC_Error( 'write_failed', 'Failed to write user data.' );
	}

	mc_do_action( 'mc_user_created', $username );

	return true;
}

/**
 * Update an existing user.
 *
 * @since 1.0.0
 *
 * @param string $username The user to update.
 * @param array  $data     Fields to update (email, display_name, role, password, meta).
 * @return true|MC_Error True on success.
 */
function mc_update_user( string $username, array $data ): true|MC_Error {

	$users = mc_read_users();
	$found = false;

	foreach ( $users as &$user ) {
		if ( ( $user['username'] ?? '' ) !== $username ) {
			continue;
		}

		$found = true;

		if ( isset( $data['email'] ) ) {
			$user['email'] = mc_sanitize_email( $data['email'] );
		}
		if ( isset( $data['display_name'] ) ) {
			$user['display_name'] = mc_sanitize_text( $data['display_name'] );
		}
		if ( isset( $data['role'] ) ) {
			$user['role'] = mc_sanitize_slug( $data['role'] );
		}
		if ( ! empty( $data['password'] ) ) {
			$user['password'] = password_hash( $data['password'], PASSWORD_BCRYPT );
		}
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$user['meta'] = array_merge( $user['meta'] ?? array(), $data['meta'] );
		}

		break;
	}
	unset( $user );

	if ( ! $found ) {
		return new MC_Error( 'user_not_found', 'User not found.' );
	}

	if ( ! mc_write_users( $users ) ) {
		return new MC_Error( 'write_failed', 'Failed to write user data.' );
	}

	mc_do_action( 'mc_user_updated', $username );

	return true;
}

/**
 * Delete a user.
 *
 * @since 1.0.0
 *
 * @param string $username Username to delete.
 * @return true|MC_Error True on success.
 */
function mc_delete_user( string $username ): true|MC_Error {

	$users    = mc_read_users();
	$filtered = array();
	$found    = false;

	foreach ( $users as $user ) {
		if ( ( $user['username'] ?? '' ) === $username ) {
			$found = true;
			continue;
		}
		$filtered[] = $user;
	}

	if ( ! $found ) {
		return new MC_Error( 'user_not_found', 'User not found.' );
	}

	if ( ! mc_write_users( $filtered ) ) {
		return new MC_Error( 'write_failed', 'Failed to write user data.' );
	}

	mc_do_action( 'mc_user_deleted', $username );

	return true;
}

/**
 * Get all users.
 *
 * @since 1.0.0
 *
 * @return array List of user arrays (passwords excluded from return).
 */
function mc_get_users(): array {

	$users     = mc_read_users();
	$sanitised = array();

	foreach ( $users as $user ) {
		unset( $user['password'] );
		$sanitised[] = $user;
	}

	return $sanitised;
}

/*
 * -------------------------------------------------------------------------
 *  Authentication and sessions
 * -------------------------------------------------------------------------
 */

/**
 * Authenticate a user by username and password.
 *
 * @since 1.0.0
 *
 * @param string $username The username.
 * @param string $password The plaintext password.
 * @return array|MC_Error User data on success, MC_Error on failure.
 */
function mc_authenticate( string $username, string $password ): array|MC_Error {

	/**
	 * Pre-authentication filter.
	 *
	 * @since 1.0.0
	 *
	 * @param null|array|MC_Error $result   Null to proceed. Return user array to short-circuit.
	 * @param string              $username Attempted username.
	 * @param string              $password Attempted password.
	 */
	$pre = mc_apply_filters( 'mc_pre_authenticate', null, $username, $password );

	if ( null !== $pre ) {
		return $pre;
	}

	$user = mc_get_user( $username );

	if ( null === $user ) {
		return new MC_Error( 'invalid_username', 'Unknown username.' );
	}

	if ( ! password_verify( $password, $user['password'] ?? '' ) ) {
		return new MC_Error( 'invalid_password', 'Incorrect password.' );
	}

	mc_do_action( 'mc_login', $username );

	return $user;
}

/**
 * Start or resume a MinimalCMS session.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_start_session(): void {

	if ( PHP_SESSION_ACTIVE === session_status() ) {
		return;
	}

	$session_dir = MC_SESSION_DIR;

	if ( ! is_dir( $session_dir ) ) {
		mkdir( $session_dir, 0700, true );
	}

	session_save_path( $session_dir );

	session_set_cookie_params(
		array(
			'lifetime' => 7200,
			'path'     => '/',
			'secure'   => ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ),
			'httponly' => true,
			'samesite' => 'Strict',
		)
	);

	session_name( 'mc_session' );
	session_start();
}

/**
 * Log a user in by setting session data.
 *
 * @since 1.0.0
 *
 * @param string $username The authenticated username.
 * @return void
 */
function mc_set_auth_session( string $username ): void {

	mc_start_session();

	$_SESSION['mc_user']       = $username;
	$_SESSION['mc_login_time'] = time();

	session_regenerate_id( true );
}

/**
 * Destroy the current authentication session (log out).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_destroy_session(): void {

	mc_start_session();

	$_SESSION = array();

	if ( ini_get( 'session.use_cookies' ) ) {
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
}

/**
 * Check whether a user is currently logged in.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_logged_in(): bool {

	mc_start_session();

	if ( empty( $_SESSION['mc_user'] ) ) {
		return false;
	}

	// Check session timeout (2 hours).
	$login_time = $_SESSION['mc_login_time'] ?? 0;
	if ( ( time() - $login_time ) > 7200 ) {
		mc_destroy_session();
		return false;
	}

	return true;
}

/**
 * Get the currently logged-in user's data.
 *
 * @since 1.0.0
 *
 * @return array|null User data or null if not logged in.
 */
function mc_get_current_user(): ?array {

	if ( ! mc_is_logged_in() ) {
		return null;
	}

	return mc_get_user( $_SESSION['mc_user'] );
}

/**
 * Get the username of the currently logged-in user.
 *
 * @since 1.0.0
 *
 * @return string Username or empty string.
 */
function mc_get_current_user_id(): string {

	mc_start_session();

	return $_SESSION['mc_user'] ?? '';
}
