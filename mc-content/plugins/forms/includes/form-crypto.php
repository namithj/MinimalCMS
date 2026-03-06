<?php

/**
 * Forms Plugin — Submission Encryption
 *
 * Provides authenticated encryption (AES-256-GCM) for form submission
 * payloads stored on disk.  The 32-byte key is derived via HKDF from
 * MC_ENCRYPTION_KEY so the raw config value is never used directly as a
 * cipher key.
 *
 * Storage format (binary-safe base64 envelope):
 *   base64( iv[12] . tag[16] . ciphertext )
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Cipher used for submission encryption.
 */
define('MC_FORMS_CIPHER', 'aes-256-gcm');

/**
 * Derive a 32-byte AES key from the configured encryption key.
 *
 * Uses HKDF-SHA256 with a fixed info string to domain-separate this
 * key from any other key derived from the same master.
 *
 * @since 1.0.0
 *
 * @return string 32-byte binary key.
 * @throws \RuntimeException When no encryption key is configured.
 */
function forms_derive_key(): string
{
	$master = defined('MC_ENCRYPTION_KEY') ? MC_ENCRYPTION_KEY : '';

	if ( '' === $master ) {
		throw new \RuntimeException(
			'Forms: MC_ENCRYPTION_KEY is not configured. Cannot encrypt/decrypt submissions.'
		);
	}

	// hash_hkdf produces a binary key of the requested length.
	return hash_hkdf( 'sha256', $master, 32, 'minimalcms-forms-v1' );
}

/**
 * Encrypt a plaintext submission payload.
 *
 * Returns a base64-encoded string (safe for file storage) containing the
 * IV, authentication tag, and ciphertext.
 *
 * @since 1.0.0
 *
 * @param string $plaintext Raw JSON or any string payload.
 * @return string Base64-encoded encrypted envelope.
 * @throws \RuntimeException On encryption failure.
 */
function forms_encrypt_submission( string $plaintext ): string
{
	$key        = forms_derive_key();
	$iv_length  = openssl_cipher_iv_length( MC_FORMS_CIPHER );
	$iv         = random_bytes( $iv_length );
	$tag        = '';

	$ciphertext = openssl_encrypt(
		$plaintext,
		MC_FORMS_CIPHER,
		$key,
		OPENSSL_RAW_DATA,
		$iv,
		$tag,
		'',
		16
	);

	if ( false === $ciphertext ) {
		throw new \RuntimeException( 'Forms: Submission encryption failed.' );
	}

	// Prepend IV and tag so the envelope is self-contained.
	return base64_encode( $iv . $tag . $ciphertext );
}

/**
 * Decrypt an encrypted submission envelope.
 *
 * @since 1.0.0
 *
 * @param string $envelope Base64-encoded encrypted envelope from disk.
 * @return string|false Decrypted plaintext, or false on failure (wrong key /
 *                      tampered data / invalid envelope).
 */
function forms_decrypt_submission( string $envelope ): string|false
{
	$raw = base64_decode( $envelope, true );

	if ( false === $raw ) {
		return false;
	}

	$iv_length = openssl_cipher_iv_length( MC_FORMS_CIPHER );

	// Minimum envelope: IV (12) + tag (16) + at least 1 byte ciphertext.
	if ( strlen( $raw ) < $iv_length + 16 + 1 ) {
		return false;
	}

	$iv         = substr( $raw, 0, $iv_length );
	$tag        = substr( $raw, $iv_length, 16 );
	$ciphertext = substr( $raw, $iv_length + 16 );

	try {
		$key = forms_derive_key();
	} catch ( \RuntimeException $e ) {
		return false;
	}

	$plaintext = openssl_decrypt(
		$ciphertext,
		MC_FORMS_CIPHER,
		$key,
		OPENSSL_RAW_DATA,
		$iv,
		$tag
	);

	return $plaintext;
}
