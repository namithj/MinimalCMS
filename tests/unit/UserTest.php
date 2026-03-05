<?php
/**
 * Unit tests for the user system (CRUD, encryption, authentication).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Error;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {

	protected function setUp(): void {
		// Reset hooks.
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];

		// Ensure a clean data directory.
		$file = mc_users_file_path();
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		if ( ! is_dir( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0755, true );
		}
	}

	// ── mc_derive_encryption_key ─────────────────────────────────────────

	public function test_derive_encryption_key_returns_32_bytes(): void {
		$key = mc_derive_encryption_key();
		$this->assertSame( SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen( $key ) );
	}

	// ── mc_read_users / mc_write_users ───────────────────────────────────

	public function test_read_users_empty_when_no_file(): void {
		$this->assertSame( [], mc_read_users() );
	}

	public function test_write_and_read_users(): void {
		$users = [
			[
				'username' => 'alice',
				'email'    => 'alice@test.com',
				'password' => password_hash( 'secret', PASSWORD_BCRYPT ),
				'role'     => 'administrator',
			],
		];

		$this->assertTrue( mc_write_users( $users ) );

		$read = mc_read_users();
		$this->assertCount( 1, $read );
		$this->assertSame( 'alice', $read[0]['username'] );
	}

	public function test_write_users_is_encrypted(): void {
		mc_write_users( [ [ 'username' => 'bob' ] ] );
		$raw = file_get_contents( mc_users_file_path() );

		// Should start with a PHP die() guard.
		$this->assertStringStartsWith( '<?php die(); ?>', $raw );
		// The actual data should be base64-encoded, not plain JSON.
		$this->assertStringNotContainsString( '"bob"', $raw );
	}

	// ── mc_create_user ───────────────────────────────────────────────────

	public function test_create_user_success(): void {
		$result = mc_create_user(
			[
				'username' => 'testuser',
				'email'    => 'test@example.com',
				'password' => 'password123',
				'role'     => 'author',
			]
		);

		$this->assertTrue( $result );
	}

	public function test_create_user_missing_fields(): void {
		$result = mc_create_user( [ 'username' => 'only_name' ] );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'missing_fields', $result->get_error_code() );
	}

	public function test_create_user_invalid_email(): void {
		$result = mc_create_user(
			[
				'username' => 'user',
				'email'    => 'invalid',
				'password' => 'pass',
			]
		);

		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'invalid_email', $result->get_error_code() );
	}

	public function test_create_user_duplicate_username(): void {
		mc_create_user(
			[
				'username' => 'dupe',
				'email'    => 'a@test.com',
				'password' => 'pass',
			]
		);

		$result = mc_create_user(
			[
				'username' => 'dupe',
				'email'    => 'b@test.com',
				'password' => 'pass',
			]
		);

		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'duplicate_username', $result->get_error_code() );
	}

	public function test_create_user_duplicate_email(): void {
		mc_create_user(
			[
				'username' => 'user1',
				'email'    => 'same@test.com',
				'password' => 'pass',
			]
		);

		$result = mc_create_user(
			[
				'username' => 'user2',
				'email'    => 'same@test.com',
				'password' => 'pass',
			]
		);

		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'duplicate_email', $result->get_error_code() );
	}

	public function test_create_user_default_role(): void {
		mc_create_user(
			[
				'username' => 'norole',
				'email'    => 'nr@test.com',
				'password' => 'pass',
			]
		);

		$user = mc_get_user( 'norole' );
		$this->assertSame( 'contributor', $user['role'] );
	}

	// ── mc_get_user ──────────────────────────────────────────────────────

	public function test_get_user_exists(): void {
		mc_create_user(
			[
				'username' => 'findme',
				'email'    => 'find@test.com',
				'password' => 'pass',
			]
		);

		$user = mc_get_user( 'findme' );
		$this->assertNotNull( $user );
		$this->assertSame( 'findme', $user['username'] );
	}

	public function test_get_user_not_found(): void {
		$this->assertNull( mc_get_user( 'ghost' ) );
	}

	// ── mc_update_user ───────────────────────────────────────────────────

	public function test_update_user_display_name(): void {
		mc_create_user(
			[
				'username'     => 'upd',
				'email'        => 'upd@test.com',
				'password'     => 'pass',
				'display_name' => 'Old Name',
			]
		);

		$result = mc_update_user( 'upd', [ 'display_name' => 'New Name' ] );
		$this->assertTrue( $result );

		$user = mc_get_user( 'upd' );
		$this->assertSame( 'New Name', $user['display_name'] );
	}

	public function test_update_user_password(): void {
		mc_create_user(
			[
				'username' => 'pwupd',
				'email'    => 'pw@test.com',
				'password' => 'old_pass',
			]
		);

		mc_update_user( 'pwupd', [ 'password' => 'new_pass' ] );
		$user = mc_get_user( 'pwupd' );

		$this->assertTrue( password_verify( 'new_pass', $user['password'] ) );
		$this->assertFalse( password_verify( 'old_pass', $user['password'] ) );
	}

	public function test_update_user_not_found(): void {
		$result = mc_update_user( 'ghost', [ 'display_name' => 'X' ] );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'user_not_found', $result->get_error_code() );
	}

	// ── mc_delete_user ───────────────────────────────────────────────────

	public function test_delete_user_success(): void {
		mc_create_user(
			[
				'username' => 'delme',
				'email'    => 'del@test.com',
				'password' => 'pass',
			]
		);

		$this->assertTrue( mc_delete_user( 'delme' ) );
		$this->assertNull( mc_get_user( 'delme' ) );
	}

	public function test_delete_user_not_found(): void {
		$result = mc_delete_user( 'nope' );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'user_not_found', $result->get_error_code() );
	}

	// ── mc_get_users ─────────────────────────────────────────────────────

	public function test_get_users_excludes_passwords(): void {
		mc_create_user(
			[
				'username' => 'safe',
				'email'    => 'safe@test.com',
				'password' => 'secret',
			]
		);

		$users = mc_get_users();
		$this->assertCount( 1, $users );
		$this->assertArrayNotHasKey( 'password', $users[0] );
	}

	public function test_get_users_empty(): void {
		$this->assertSame( [], mc_get_users() );
	}

	// ── mc_authenticate ──────────────────────────────────────────────────

	public function test_authenticate_success(): void {
		mc_create_user(
			[
				'username' => 'auth_user',
				'email'    => 'auth@test.com',
				'password' => 'correct_password',
			]
		);

		$result = mc_authenticate( 'auth-user', 'correct_password' );
		$this->assertIsArray( $result );
		$this->assertSame( 'auth-user', $result['username'] );
	}

	public function test_authenticate_wrong_password(): void {
		mc_create_user(
			[
				'username' => 'auth2',
				'email'    => 'auth2@test.com',
				'password' => 'right',
			]
		);

		$result = mc_authenticate( 'auth2', 'wrong' );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'invalid_password', $result->get_error_code() );
	}

	public function test_authenticate_unknown_user(): void {
		$result = mc_authenticate( 'nonexistent', 'pass' );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'invalid_username', $result->get_error_code() );
	}
}
