<?php
/**
 * Tests for MC_Capabilities class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Capabilities;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Capabilities
 */
class MCCapabilitiesClassTest extends TestCase {

	private MC_Capabilities $caps;
	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks = new MC_Hooks();
		$this->caps  = new MC_Capabilities($this->hooks);
		$this->caps->initialise_roles();
	}

	public function test_default_roles_exist(): void {

		$this->assertNotNull($this->caps->get_role('administrator'));
		$this->assertNotNull($this->caps->get_role('editor'));
		$this->assertNotNull($this->caps->get_role('author'));
		$this->assertNotNull($this->caps->get_role('contributor'));
	}

	public function test_administrator_has_manage_settings(): void {

		$user = array('role' => 'administrator');
		$this->assertTrue($this->caps->user_can($user, 'manage_settings'));
	}

	public function test_contributor_cannot_manage_users(): void {

		$user = array('role' => 'contributor');
		$this->assertFalse($this->caps->user_can($user, 'manage_users'));
	}

	public function test_add_custom_role(): void {

		$this->caps->add_role('reviewer', 'Reviewer', array('view_admin' => true));
		$role = $this->caps->get_role('reviewer');
		$this->assertNotNull($role);
		$this->assertSame('Reviewer', $role['label']);
		$this->assertTrue($role['capabilities']['view_admin']);
	}

	public function test_remove_role(): void {

		$this->caps->add_role('temp', 'Temp', array());
		$this->caps->remove_role('temp');
		$this->assertNull($this->caps->get_role('temp'));
	}

	public function test_role_has_cap_returns_true(): void {

		$this->assertTrue($this->caps->role_has_cap('administrator', 'manage_settings'));
	}

	public function test_role_has_cap_returns_false_for_missing(): void {

		$this->assertFalse($this->caps->role_has_cap('contributor', 'manage_settings'));
	}

	public function test_role_has_cap_returns_false_for_unknown_role(): void {

		$this->assertFalse($this->caps->role_has_cap('nonexistent', 'view_admin'));
	}

	public function test_get_roles_returns_all_built_in(): void {

		$roles = $this->caps->get_roles();
		$this->assertArrayHasKey('administrator', $roles);
		$this->assertArrayHasKey('editor', $roles);
		$this->assertArrayHasKey('author', $roles);
		$this->assertArrayHasKey('contributor', $roles);
	}

	public function test_add_cap_to_existing_role(): void {

		$this->caps->add_cap('contributor', 'upload_files');
		$this->assertTrue($this->caps->role_has_cap('contributor', 'upload_files'));
	}

	public function test_remove_cap_from_role(): void {

		$this->assertTrue($this->caps->role_has_cap('author', 'upload_files'));
		$this->caps->remove_cap('author', 'upload_files');
		$this->assertFalse($this->caps->role_has_cap('author', 'upload_files'));
	}

	public function test_user_can_with_unknown_role(): void {

		$user = array('role' => 'ghost');
		$this->assertFalse($this->caps->user_can($user, 'view_admin'));
	}

	public function test_user_can_filter_short_circuits(): void {

		$this->hooks->add_filter('mc_user_can', function ($result, $user, $cap) {
			if ('view_admin' === $cap) {
				return false;
			}
			return $result;
		}, 10, 3);

		$user = array('role' => 'administrator');
		$this->assertFalse($this->caps->user_can($user, 'view_admin'));
	}
}
