<?php

/**
 * MC_Setup — First-run setup logic.
 *
 * Extracted from mc-admin/setup.php. Handles initial installation:
 * config seeding, master key + keystore generation, and first user creation.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * First-run setup.
 *
 * @since {version}
 */
class MC_Setup
{
	/**
	 * @since {version}
	 * @var MC_Config
	 */
	private MC_Config $config;

	/**
	 * @since {version}
	 * @var MC_User_Manager
	 */
	private MC_User_Manager $users;

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Absolute path to the site root (with trailing slash).
	 *
	 * @since {version}
	 * @var string
	 */
	private string $abspath;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Config       $config  Configuration.
	 * @param MC_User_Manager $users   User manager.
	 * @param MC_Hooks        $hooks   Hooks engine.
	 * @param string          $abspath Absolute path to the site root.
	 */
	public function __construct(MC_Config $config, MC_User_Manager $users, MC_Hooks $hooks, string $abspath = '')
	{

		$this->config  = $config;
		$this->users   = $users;
		$this->hooks   = $hooks;
		$this->abspath = '' !== $abspath ? rtrim($abspath, '/') . '/' : '';
	}

	/**
	 * Check if setup is needed (no users exist).
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function needs_setup(): bool
	{

		if ($this->config->is_fresh_install()) {
			return true;
		}

		$all_users = $this->users->get_users();
		return empty($all_users);
	}

	/**
	 * Generate cryptographic keys for a new installation.
	 *
	 * @since {version}
	 *
	 * @return array{secret_key: string, encryption_key: string}
	 */
	public function generate_keys(): array
	{

		return array(
			'secret_key'     => bin2hex(random_bytes(32)),
			'encryption_key' => bin2hex(random_bytes(32)),
		);
	}

	/**
	 * Provision the master key and keystore for a new installation.
	 *
	 * Generates a master key, then creates the encrypted keystore
	 * containing the application secret_key and encryption_key.
	 *
	 * @since {version}
	 *
	 * @return array{secret_key: string, encryption_key: string, master_key: string}|MC_Error
	 */
	public function provision_keystore(): array|MC_Error
	{

		$data_dir = defined('MC_DATA_DIR') ? MC_DATA_DIR : $this->abspath . 'content/data/';

		// Generate or resolve master key.
		try {
			$master_key = MC_Keystore::resolve_master_key($data_dir, $this->abspath);
		} catch (\RuntimeException $e) {
			$master_key = '';
		}

		if ('' === $master_key) {
			$master_key_hex = MC_Keystore::generate_master_key($data_dir);
			if ('' === $master_key_hex) {
				return new MC_Error('master_key_failed', 'Failed to generate master key.');
			}
			$master_key = hex2bin($master_key_hex);
		}

		// Generate application keys.
		$keys = $this->generate_keys();

		// Save to encrypted keystore.
		if (!MC_Keystore::save_keys($data_dir, $master_key, $keys)) {
			return new MC_Error('keystore_write_failed', 'Failed to write encrypted keystore.');
		}

		return array_merge($keys, array('master_key' => $master_key));
	}

	/**
	 * Generate an encrypted key recovery bundle for setup.
	 *
	 * The bundle includes only the master key (hex) and the encrypted
	 * keystore payload from the data directory keys file. The payload is encrypted with
	 * a passphrase-derived key so it can be downloaded and stored offline.
	 *
	 * @since {version}
	 *
	 * @param string $master_key_hex Master key in 64-char hex format.
	 * @param string $passphrase     Backup passphrase.
	 * @return array{filename: string, content: string}|MC_Error
	 */
	public function generate_backup_bundle(string $master_key_hex, string $passphrase): array|MC_Error
	{

		$master_key_hex = trim($master_key_hex);
		$passphrase     = trim($passphrase);

		if (64 !== strlen($master_key_hex) || !ctype_xdigit($master_key_hex)) {
			return new MC_Error('invalid_master_key', 'Invalid master key format for backup bundle.');
		}

		if ('' === $passphrase) {
			return new MC_Error('missing_passphrase', 'Backup passphrase is required.');
		}

		$data_dir      = defined('MC_DATA_DIR') ? MC_DATA_DIR : $this->abspath . 'content/data/';
		$keystore_blob = MC_File_Guard::read($data_dir . MC_Keystore::KEYS_FILE);

		if (false === $keystore_blob || '' === $keystore_blob) {
			return new MC_Error('missing_keystore', 'Could not read encrypted keystore for backup.');
		}

		$payload = array(
			'format'      => 'minimalcms-recovery-data-v1',
			'created_at'  => gmdate('c'),
			'master_key'  => strtolower($master_key_hex),
			'keystore'    => $keystore_blob,
			'integrity'   => hash('sha256', strtolower($master_key_hex) . '|' . $keystore_blob),
		);

		$payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES);
		if (false === $payload_json) {
			return new MC_Error('backup_encode_failed', 'Could not encode backup payload.');
		}

		$iterations = 200000;
		$salt       = random_bytes(16);
		$nonce      = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$key        = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, true);

		try {
			$ciphertext = sodium_crypto_secretbox($payload_json, $nonce, $key);
		} catch (\SodiumException $e) {
			return new MC_Error('backup_encrypt_failed', 'Could not encrypt backup payload.');
		}

		$envelope = array(
			'format'     => 'minimalcms-key-backup-v1',
			'kdf'        => 'pbkdf2-sha256',
			'iterations' => $iterations,
			'salt'       => base64_encode($salt),
			'nonce'      => base64_encode($nonce),
			'ciphertext' => base64_encode($ciphertext),
		);

		$content = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if (false === $content) {
			return new MC_Error('backup_encode_failed', 'Could not encode backup bundle.');
		}

		return array(
			'filename' => 'minimalcms-key-backup-' . gmdate('Ymd-His') . '.json',
			'content'  => $content,
		);
	}

	/**
	 * Restore key files from an encrypted recovery backup bundle.
	 *
	 * @since {version}
	 *
	 * @param string $abspath        Absolute path to site root.
	 * @param string $bundle_content JSON backup bundle content.
	 * @param string $passphrase     Backup passphrase.
	 * @return true|MC_Error
	 */
	public static function restore_backup_bundle(string $abspath, string $bundle_content, string $passphrase): true|MC_Error
	{

		$passphrase = trim($passphrase);

		if ('' === $passphrase) {
			return new MC_Error('missing_passphrase', 'Backup passphrase is required.');
		}

		$bundle = json_decode($bundle_content, true);
		if (!is_array($bundle)) {
			return new MC_Error('invalid_bundle', 'Backup bundle is not valid JSON.');
		}

		$iterations = (int) ($bundle['iterations'] ?? 0);
		if ('minimalcms-key-backup-v1' !== ($bundle['format'] ?? '') || 'pbkdf2-sha256' !== ($bundle['kdf'] ?? '') || $iterations < 100000) {
			return new MC_Error('invalid_bundle', 'Backup bundle format is invalid or unsupported.');
		}

		$salt       = base64_decode((string) ($bundle['salt'] ?? ''), true);
		$nonce      = base64_decode((string) ($bundle['nonce'] ?? ''), true);
		$ciphertext = base64_decode((string) ($bundle['ciphertext'] ?? ''), true);

		if (false === $salt || false === $nonce || false === $ciphertext) {
			return new MC_Error('invalid_bundle', 'Backup bundle payload is malformed.');
		}

		$key = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, true);

		try {
			$payload_json = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
		} catch (\SodiumException $e) {
			return new MC_Error('backup_decrypt_failed', 'Could not decrypt backup bundle with the provided passphrase.');
		}

		if (false === $payload_json) {
			return new MC_Error('backup_decrypt_failed', 'Could not decrypt backup bundle with the provided passphrase.');
		}

		$payload = json_decode($payload_json, true);
		if (!is_array($payload)) {
			return new MC_Error('invalid_bundle_payload', 'Backup payload is invalid.');
		}

		$master_key_hex = strtolower((string) ($payload['master_key'] ?? ''));
		$keystore_blob  = (string) ($payload['keystore'] ?? '');
		$integrity      = (string) ($payload['integrity'] ?? '');

		if ('minimalcms-recovery-data-v1' !== ($payload['format'] ?? '') || 64 !== strlen($master_key_hex) || !ctype_xdigit($master_key_hex) || '' === $keystore_blob || '' === $integrity) {
			return new MC_Error('invalid_bundle_payload', 'Backup payload is incomplete or corrupted.');
		}

		$expected_integrity = hash('sha256', $master_key_hex . '|' . $keystore_blob);
		if (!hash_equals($expected_integrity, $integrity)) {
			return new MC_Error('invalid_bundle_payload', 'Backup payload integrity check failed.');
		}

		$master_key = hex2bin($master_key_hex);
		if (false === $master_key) {
			return new MC_Error('invalid_bundle_payload', 'Backup payload master key is invalid.');
		}

		$decoded_keystore = base64_decode($keystore_blob, true);
		if (false === $decoded_keystore || strlen($decoded_keystore) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			return new MC_Error('invalid_bundle_payload', 'Backup payload keystore is invalid.');
		}

		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		$ks_nonce     = substr($decoded_keystore, 0, $nonce_length);
		$ks_cipher    = substr($decoded_keystore, $nonce_length);

		try {
			$keys_json = sodium_crypto_secretbox_open($ks_cipher, $ks_nonce, $master_key);
		} catch (\SodiumException $e) {
			return new MC_Error('invalid_bundle_payload', 'Keystore in backup bundle failed decryption validation.');
		}

		if (false === $keys_json) {
			return new MC_Error('invalid_bundle_payload', 'Keystore in backup bundle failed decryption validation.');
		}

		$keys = json_decode($keys_json, true);
		if (!is_array($keys) || empty($keys['secret_key']) || empty($keys['encryption_key'])) {
			return new MC_Error('invalid_bundle_payload', 'Backup payload does not contain valid application keys.');
		}

		$data_dir = rtrim($abspath, '/') . '/content/data/';

		if (!MC_File_Guard::write($data_dir . MC_Keystore::WEBROOT_FILE, $master_key_hex)) {
			return new MC_Error('restore_write_failed', 'Could not write recovered master key file.');
		}

		if (!MC_File_Guard::write($data_dir . MC_Keystore::KEYS_FILE, $keystore_blob)) {
			return new MC_Error('restore_write_failed', 'Could not write recovered keystore file.');
		}

		return true;
	}

	/**
	 * Seed config.php from the sample file with overrides.
	 *
	 * @since {version}
	 *
	 * @param array $overrides Key-value overrides to apply.
	 * @return true|MC_Error
	 */
	public function seed_config(array $overrides = array()): true|MC_Error
	{

		$sample = $this->config->all();
		if (empty($sample)) {
			$this->config->load();
			$sample = $this->config->all();
		}

		/**
		 * Filter config data before saving during setup.
		 *
		 * @since {version}
		 *
		 * @param array $config Merged config data.
		 */
		$merged = $this->hooks->apply_filters(
			'mc_setup_config',
			array_merge($sample, $overrides)
		);

		foreach ($merged as $key => $value) {
			$this->config->set($key, $value);
		}

		if (!$this->config->save()) {
			return new MC_Error('config_write_failed', 'Failed to write config.php.');
		}

		return true;
	}

	/**
	 * Run the complete setup process.
	 *
	 * Expects $data to contain at minimum:
	 *   - site_name:  string
	 *   - username:   string
	 *   - password:   string
	 *   - email:      string
	 *
	 * @since {version}
	 *
	 * @param array $data Setup data.
	 * @return true|MC_Error
	 */
	public function run(array $data): true|MC_Error
	{

		// Provision keystore (master key + app keys).
		$keystore_result = $this->provision_keystore();
		if (is_a($keystore_result, 'MC_Error')) {
			return $keystore_result;
		}

		// Seed config (without keys — they live in the keystore).
		$config_overrides = array(
			'site_name' => $data['site_name'] ?? 'My Site',
		);

		$result = $this->seed_config($config_overrides);
		if (is_a($result, 'MC_Error')) {
			return $result;
		}

		// Create the first admin user — set the encryption key from keystore.
		$this->users->set_encryption_key($keystore_result['encryption_key']);

		$user_result = $this->users->create_user(array(
			'username' => $data['username'] ?? '',
			'password' => $data['password'] ?? '',
			'email'    => $data['email'] ?? '',
			'role'     => 'administrator',
		));

		if (is_a($user_result, 'MC_Error')) {
			return $user_result;
		}

		/**
		 * Fires after setup finishes successfully.
		 *
		 * @since {version}
		 *
		 * @param array $data Setup data.
		 */
		$this->hooks->do_action('mc_setup_complete', $data);

		return true;
	}
}
