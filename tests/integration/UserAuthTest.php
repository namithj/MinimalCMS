<?php
/**
 * Integration tests for user authentication workflow.
 *
 * @package MinimalCMS\Tests\Integration
 */

namespace MinimalCMS\Tests\Integration;

use MC_Error;
use PHPUnit\Framework\TestCase;

class UserAuthTest extends TestCase {

	protected function setUp(): void {
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];

		// Clean user data.
		$file = mc_users_file_path();
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		if ( ! is_dir( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0755, true );
		}

		$_SESSION = [];
	}

	// ── Full authentication lifecycle ────────────────────────────────────

	public function test_register_authenticate_lifecycle(): void {
		// 1. Register user.
		$result = mc_create_user(
			[
				'username'     => 'jane',
				'email'        => 'jane@example.com',
				'password'     => 'secureP@ss1',
				'role'         => 'editor',
				'display_name' => 'Jane Doe',
			]
		);
		$this->assertTrue( $result );

		// 2. Authenticate.
		$user = mc_authenticate( 'jane', 'secureP@ss1' );
		$this->assertIsArray( $user );
		$this->assertSame( 'jane', $user['username'] );
		$this->assertSame( 'jane@example.com', $user['email'] );
		$this->assertSame( 'editor', $user['role'] );

		// 3. Password stored as hash.
		$this->assertNotSame( 'secureP@ss1', $user['password'] );
		$this->assertTrue( password_verify( 'secureP@ss1', $user['password'] ) );
	}

	// ── Multiple users ───────────────────────────────────────────────────

	public function test_multiple_users(): void {
		mc_create_user(
			[
				'username' => 'u1',
				'email'    => 'u1@t.com',
				'password' => 'p',
				'role'     => 'administrator',
			]
		);
		mc_create_user(
			[
				'username' => 'u2',
				'email'    => 'u2@t.com',
				'password' => 'p',
				'role'     => 'editor',
			]
		);
		mc_create_user(
			[
				'username' => 'u3',
				'email'    => 'u3@t.com',
				'password' => 'p',
				'role'     => 'author',
			]
		);

		$users = mc_get_users();
		$this->assertCount( 3, $users );

		// Each user should have different roles.
		$roles = array_column( $users, 'role' );
		$this->assertContains( 'administrator', $roles );
		$this->assertContains( 'editor', $roles );
		$this->assertContains( 'author', $roles );
	}

	// ── User update then authenticate ────────────────────────────────────

	public function test_update_password_and_reauthenticate(): void {
		mc_create_user(
			[
				'username' => 'pw',
				'email'    => 'pw@t.com',
				'password' => 'old',
			]
		);

		// Authenticate with old password.
		$this->assertIsArray( mc_authenticate( 'pw', 'old' ) );

		// Change password.
		mc_update_user( 'pw', [ 'password' => 'new' ] );

		// Old password fails.
		$this->assertInstanceOf( MC_Error::class, mc_authenticate( 'pw', 'old' ) );

		// New password works.
		$this->assertIsArray( mc_authenticate( 'pw', 'new' ) );
	}

	// ── Delete user prevents auth ────────────────────────────────────────

	public function test_delete_user_prevents_auth(): void {
		mc_create_user(
			[
				'username' => 'del',
				'email'    => 'del@t.com',
				'password' => 'p',
			]
		);
		$this->assertIsArray( mc_authenticate( 'del', 'p' ) );

		mc_delete_user( 'del' );
		$result = mc_authenticate( 'del', 'p' );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'invalid_username', $result->get_error_code() );
	}

	// ── User capabilities integration ────────────────────────────────────

	public function test_user_role_capabilities(): void {
		mc_initialise_roles();

		mc_create_user(
			[
				'username' => 'admin',
				'email'    => 'a@t.com',
				'password' => 'p',
				'role'     => 'administrator',
			]
		);
		mc_create_user(
			[
				'username' => 'contrib',
				'email'    => 'c@t.com',
				'password' => 'p',
				'role'     => 'contributor',
			]
		);

		$admin   = mc_get_user( 'admin' );
		$contrib = mc_get_user( 'contrib' );

		$this->assertTrue( mc_user_can( $admin, 'manage_settings' ) );
		$this->assertTrue( mc_user_can( $admin, 'manage_users' ) );

		$this->assertFalse( mc_user_can( $contrib, 'manage_settings' ) );
		$this->assertFalse( mc_user_can( $contrib, 'manage_users' ) );
		$this->assertTrue( mc_user_can( $contrib, 'edit_content' ) );
	}

	// ── User action hooks ────────────────────────────────────────────────

	public function test_user_created_hook_fires(): void {
		$fired_with = null;
		mc_add_action(
			'mc_user_created',
			function ( $username ) use ( &$fired_with ) {
				$fired_with = $username;
			}
		);

		mc_create_user(
			[
				'username' => 'hookuser',
				'email'    => 'h@t.com',
				'password' => 'p',
			]
		);
		$this->assertSame( 'hookuser', $fired_with );
	}

	public function test_user_updated_hook_fires(): void {
		mc_create_user(
			[
				'username' => 'updhook',
				'email'    => 'uh@t.com',
				'password' => 'p',
			]
		);

		$fired = false;
		mc_add_action(
			'mc_user_updated',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		mc_update_user( 'updhook', [ 'display_name' => 'New' ] );
		$this->assertTrue( $fired );
	}

	public function test_user_deleted_hook_fires(): void {
		mc_create_user(
			[
				'username' => 'delhook',
				'email'    => 'dh@t.com',
				'password' => 'p',
			]
		);

		$fired = false;
		mc_add_action(
			'mc_user_deleted',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		mc_delete_user( 'delhook' );
		$this->assertTrue( $fired );
	}

	// ── Pre-authentication filter ────────────────────────────────────────

	public function test_pre_authenticate_filter(): void {
		mc_add_filter(
			'mc_pre_authenticate',
			function ( $result, $username, $password ) {
				if ( $username === 'magic' && $password === 'open' ) {
					return [
						'username' => 'magic',
						'role'     => 'administrator',
					];
				}
				return $result;
			},
			10,
			3
		);

		$user = mc_authenticate( 'magic', 'open' );
		$this->assertIsArray( $user );
		$this->assertSame( 'magic', $user['username'] );
	}

	// ── Data encryption integrity ────────────────────────────────────────

	public function test_encrypted_data_survives_roundtrip(): void {
		$users = [
			[
				'username' => 'alpha',
				'email'    => 'a@t.com',
				'role'     => 'editor',
			],
			[
				'username' => 'beta',
				'email'    => 'b@t.com',
				'role'     => 'author',
			],
		];

		mc_write_users( $users );

		// Raw file should not contain plain JSON.
		$raw = file_get_contents( mc_users_file_path() );
		$this->assertStringNotContainsString( '"alpha"', $raw );

		// But decrypted roundtrip should match.
		$read = mc_read_users();
		$this->assertCount( 2, $read );
		$this->assertSame( 'alpha', $read[0]['username'] );
		$this->assertSame( 'beta', $read[1]['username'] );
	}
}
