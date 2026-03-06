<?php

/**
 * Unit tests for the roles and capabilities system.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CapabilitiesTest extends TestCase
{
	protected function setUp(): void
	{
		// Reset hooks for mc_apply_filters used inside capabilities.
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];

		// Re-initialise default roles with clean hooks.
		mc_initialise_roles();
	}

	// ── mc_initialise_roles ──────────────────────────────────────────────

	public function test_initialise_roles_creates_defaults(): void
	{
		$roles = mc_get_roles();
		$this->assertArrayHasKey('administrator', $roles);
		$this->assertArrayHasKey('editor', $roles);
		$this->assertArrayHasKey('author', $roles);
		$this->assertArrayHasKey('contributor', $roles);
	}

	public function test_administrator_has_all_caps(): void
	{
		$role = mc_get_role('administrator');
		$this->assertNotNull($role);
		$this->assertTrue($role['capabilities']['manage_users']);
		$this->assertTrue($role['capabilities']['manage_settings']);
		$this->assertTrue($role['capabilities']['edit_content']);
	}

	public function test_contributor_limited_caps(): void
	{
		$role = mc_get_role('contributor');
		$this->assertTrue($role['capabilities']['edit_content']);
		$this->assertArrayNotHasKey('manage_users', $role['capabilities']);
	}

	// ── mc_add_role ──────────────────────────────────────────────────────

	public function test_add_role(): void
	{
		mc_add_role('custom', 'Custom Role', [ 'read' => true ]);
		$role = mc_get_role('custom');
		$this->assertNotNull($role);
		$this->assertSame('Custom Role', $role['label']);
		$this->assertTrue($role['capabilities']['read']);
	}

	// ── mc_remove_role ───────────────────────────────────────────────────

	public function test_remove_role(): void
	{
		mc_add_role('temp', 'Temporary', []);
		mc_remove_role('temp');
		$this->assertNull(mc_get_role('temp'));
	}

	// ── mc_get_role ──────────────────────────────────────────────────────

	public function test_get_role_null_for_nonexistent(): void
	{
		$this->assertNull(mc_get_role('nonexistent'));
	}

	// ── mc_get_roles ─────────────────────────────────────────────────────

	public function test_get_roles_returns_all(): void
	{
		$roles = mc_get_roles();
		$this->assertIsArray($roles);
		$this->assertGreaterThanOrEqual(4, count($roles));
	}

	// ── mc_add_cap ───────────────────────────────────────────────────────

	public function test_add_cap_to_existing_role(): void
	{
		mc_add_cap('author', 'manage_themes');
		$this->assertTrue(mc_role_has_cap('author', 'manage_themes'));
	}

	public function test_add_cap_with_deny(): void
	{
		mc_add_cap('editor', 'secret_cap', false);
		$this->assertFalse(mc_role_has_cap('editor', 'secret_cap'));
	}

	public function test_add_cap_to_nonexistent_role(): void
	{
		// Should not throw.
		mc_add_cap('ghost', 'something');
		$this->assertNull(mc_get_role('ghost'));
	}

	// ── mc_remove_cap ────────────────────────────────────────────────────

	public function test_remove_cap(): void
	{
		mc_add_cap('author', 'temp_cap');
		mc_remove_cap('author', 'temp_cap');
		$this->assertFalse(mc_role_has_cap('author', 'temp_cap'));
	}

	// ── mc_role_has_cap ──────────────────────────────────────────────────

	public function test_role_has_cap_true(): void
	{
		$this->assertTrue(mc_role_has_cap('administrator', 'manage_settings'));
	}

	public function test_role_has_cap_false(): void
	{
		$this->assertFalse(mc_role_has_cap('contributor', 'manage_users'));
	}

	public function test_role_has_cap_nonexistent_role(): void
	{
		$this->assertFalse(mc_role_has_cap('nobody', 'anything'));
	}

	// ── mc_user_can ──────────────────────────────────────────────────────

	public function test_user_can_admin(): void
	{
		$user = [
			'username' => 'admin',
			'role'     => 'administrator',
		];
		$this->assertTrue(mc_user_can($user, 'manage_settings'));
	}

	public function test_user_can_contributor_limited(): void
	{
		$user = [
			'username' => 'joe',
			'role'     => 'contributor',
		];
		$this->assertFalse(mc_user_can($user, 'manage_users'));
		$this->assertTrue(mc_user_can($user, 'edit_content'));
	}

	public function test_user_can_no_role(): void
	{
		$user = [ 'username' => 'norole' ];
		$this->assertFalse(mc_user_can($user, 'anything'));
	}

	public function test_user_can_filter_override(): void
	{
		mc_add_filter(
			'mc_user_can',
			function () {
				return true;
			},
			10,
			3
		);

		$user = [
			'username' => 'nobody',
			'role'     => 'contributor',
		];
		$this->assertTrue(mc_user_can($user, 'manage_settings'));
	}
}
