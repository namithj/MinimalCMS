<?php

/**
 * MC_Keystore — Encrypted key management.
 *
 * Cryptographic keys (secret_key, encryption_key) are stored encrypted in
 * a PHP-guarded file using sodium secretbox. The master key used to encrypt
 * them is resolved from (in priority order):
 *
 *   1. Environment variable MC_MASTER_KEY
 *   2. Above-webroot file  ../mc-master-key.php
 *   3. In-webroot file     mc-data/.master-key.php
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Encrypted application key storage.
 *
 * @since {version}
 */
class MC_Keystore
{
	/**
	 * Environment variable name for the master key.
	 *
	 * @since {version}
	 * @var string
	 */
	public const ENV_VAR = 'MC_MASTER_KEY';

	/**
	 * Filename for the above-webroot master key file.
	 *
	 * @since {version}
	 * @var string
	 */
	public const ABOVE_WEBROOT_FILE = 'mc-master-key.php';

	/**
	 * Filename for the in-webroot master key file inside the data directory.
	 *
	 * @since {version}
	 * @var string
	 */
	public const WEBROOT_FILE = '.master-key.php';

	/**
	 * Filename for the encrypted keystore inside the data directory.
	 *
	 * @since {version}
	 * @var string
	 */
	public const KEYS_FILE = 'keys.php';

	/**
	 * Resolve the master key from the configured sources.
	 *
	 * @since {version}
	 *
	 * @param string $data_dir Absolute path to the data directory (with trailing slash).
	 * @param string $abspath  Absolute path to the webroot (with trailing slash).
	 * @return string 32-byte binary master key.
	 * @throws \RuntimeException When no master key can be found.
	 */
	public static function resolve_master_key(string $data_dir, string $abspath): string
	{

		// 1. Environment variable.
		$env = getenv(self::ENV_VAR);

		if (false !== $env && '' !== $env) {
			return self::normalize_key($env);
		}

		// 2. Above-webroot file.
		$above_path = dirname(rtrim($abspath, '/')) . '/' . self::ABOVE_WEBROOT_FILE;

		if (is_file($above_path)) {
			$raw = MC_File_Guard::read($above_path);

			if (false !== $raw && '' !== $raw) {
				return self::normalize_key($raw);
			}
		}

		// 3. In-webroot data directory.
		$local_path = $data_dir . self::WEBROOT_FILE;

		if (is_file($local_path)) {
			$raw = MC_File_Guard::read($local_path);

			if (false !== $raw && '' !== $raw) {
				return self::normalize_key($raw);
			}
		}

		throw new \RuntimeException(
			'No master key found. Set the MC_MASTER_KEY environment variable, '
			. 'place a mc-master-key.php above the webroot, '
			. 'or run the setup wizard.'
		);
	}

	/**
	 * Generate a new master key and persist it to the data directory.
	 *
	 * @since {version}
	 *
	 * @param string $data_dir Absolute path to the data directory (with trailing slash).
	 * @return string The 64-character hex master key string.
	 */
	public static function generate_master_key(string $data_dir): string
	{

		$key_hex = bin2hex(random_bytes(32));

		MC_File_Guard::write($data_dir . self::WEBROOT_FILE, $key_hex);

		return $key_hex;
	}

	/**
	 * Load and decrypt application keys from the keystore file.
	 *
	 * @since {version}
	 *
	 * @param string $data_dir   Absolute path to the data directory.
	 * @param string $master_key 32-byte binary master key.
	 * @return array{secret_key: string, encryption_key: string}
	 */
	public static function load_keys(string $data_dir, string $master_key): array
	{

		$path    = $data_dir . self::KEYS_FILE;
		$encoded = MC_File_Guard::read($path);

		if (false === $encoded || '' === $encoded) {
			return array('secret_key' => '', 'encryption_key' => '');
		}

		$decoded = base64_decode($encoded, true);

		if (false === $decoded) {
			return array('secret_key' => '', 'encryption_key' => '');
		}

		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

		if (strlen($decoded) < $nonce_length) {
			return array('secret_key' => '', 'encryption_key' => '');
		}

		$nonce      = substr($decoded, 0, $nonce_length);
		$ciphertext = substr($decoded, $nonce_length);

		try {
			$plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $master_key);
		} catch (\SodiumException $e) {
			return array('secret_key' => '', 'encryption_key' => '');
		}

		if (false === $plaintext) {
			return array('secret_key' => '', 'encryption_key' => '');
		}

		$keys = json_decode($plaintext, true);

		if (!is_array($keys)) {
			return array('secret_key' => '', 'encryption_key' => '');
		}

		return array(
			'secret_key'     => $keys['secret_key'] ?? '',
			'encryption_key' => $keys['encryption_key'] ?? '',
		);
	}

	/**
	 * Encrypt and persist application keys to the keystore file.
	 *
	 * @since {version}
	 *
	 * @param string $data_dir   Absolute path to the data directory.
	 * @param string $master_key 32-byte binary master key.
	 * @param array  $keys       Associative array with 'secret_key' and 'encryption_key'.
	 * @return bool True on success.
	 */
	public static function save_keys(string $data_dir, string $master_key, array $keys): bool
	{

		$plaintext = json_encode($keys, JSON_UNESCAPED_UNICODE);
		$nonce     = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		try {
			$ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $master_key);
		} catch (\SodiumException $e) {
			return false;
		}

		$blob = base64_encode($nonce . $ciphertext);

		return MC_File_Guard::write($data_dir . self::KEYS_FILE, $blob);
	}

	/**
	 * Normalize a key string (hex or raw) into a 32-byte binary key.
	 *
	 * @since {version}
	 *
	 * @param string $key Hex string (64 chars) or raw binary (32 bytes).
	 * @return string 32-byte binary key.
	 * @throws \RuntimeException When the key format is invalid.
	 */
	private static function normalize_key(string $key): string
	{

		$key = trim($key);

		// 64-char hex string → 32 bytes.
		if (64 === strlen($key) && ctype_xdigit($key)) {
			return hex2bin($key);
		}

		// Already 32 raw bytes.
		if (SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($key)) {
			return $key;
		}

		// Try base64.
		$decoded = base64_decode($key, true);

		if (false !== $decoded && SODIUM_CRYPTO_SECRETBOX_KEYBYTES === strlen($decoded)) {
			return $decoded;
		}

		throw new \RuntimeException(
			'Master key must be 64 hex characters, 32 raw bytes, or a valid base64-encoded 32-byte value.'
		);
	}
}
