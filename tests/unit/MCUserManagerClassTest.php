<?php

/**
 * Tests for MC_User_Manager class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Capabilities;
use MC_Error;
use MC_Formatter;
use MC_Hooks;
use MC_Session;
use MC_User_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_User_Manager
 */
class MCUserManagerClassTest extends TestCase
{
	private MC_User_Manager $users;
	private string $users_file;
	private string $temp_dir;
	private string $encryption_key;

	protected function setUp(): void
	{

		$this->temp_dir = sys_get_temp_dir() . '/mc_user_test_' . uniqid();
		mkdir($this->temp_dir, 0755, true);

		$this->users_file     = $this->temp_dir . '/users.php';
		$this->encryption_key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

		$hooks   = new MC_Hooks();
		$fmt     = new MC_Formatter($hooks);
		$caps    = new MC_Capabilities($hooks);
		$caps->initialise_roles();
		$session = new MC_Session($hooks, $this->temp_dir . '/sessions');

		$this->users = new MC_User_Manager($hooks, $fmt, $caps, $session, $this->users_file, $this->encryption_key);
	}

	protected function tearDown(): void
	{

		$this->rm_recursive($this->temp_dir);
	}

	private function rm_recursive(string $dir): void
	{

		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}
			$path = $dir . '/' . $item;
			if (is_dir($path)) {
				$this->rm_recursive($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}

	public function test_get_users_empty(): void
	{

		$this->assertSame(array(), $this->users->get_users());
	}

	public function test_create_user_success(): void
	{

		$result = $this->users->create_user(array(
			'username' => 'alice',
			'password' => 'secretpass',
			'email'    => 'alice@example.com',
			'role'     => 'administrator',
		));

		$this->assertTrue($result);
	}

	public function test_create_user_and_get(): void
	{

		$this->users->create_user(array(
			'username' => 'bob',
			'password' => 'pass1234',
			'email'    => 'bob@example.com',
			'role'     => 'editor',
		));

		$user = $this->users->get_user('bob');
		$this->assertNotNull($user);
		$this->assertSame('bob', $user['username']);
		$this->assertSame('bob@example.com', $user['email']);
		$this->assertSame('editor', $user['role']);
	}

	public function test_create_user_missing_username(): void
	{

		$result = $this->users->create_user(array(
			'password' => 'pass1234',
			'email'    => 'no-name@example.com',
			'role'     => 'editor',
		));

		$this->assertInstanceOf(MC_Error::class, $result);
	}

	public function test_create_user_missing_password(): void
	{

		$result = $this->users->create_user(array(
			'username' => 'carol',
			'email'    => 'carol@example.com',
			'role'     => 'editor',
		));

		$this->assertInstanceOf(MC_Error::class, $result);
	}

	public function test_create_duplicate_user(): void
	{

		$this->users->create_user(array(
			'username' => 'dave',
			'password' => 'pass1234',
			'email'    => 'dave@example.com',
			'role'     => 'author',
		));

		$result = $this->users->create_user(array(
			'username' => 'dave',
			'password' => 'other1234',
			'email'    => 'dave2@example.com',
			'role'     => 'author',
		));

		$this->assertInstanceOf(MC_Error::class, $result);
	}

	public function test_delete_user(): void
	{

		$this->users->create_user(array(
			'username' => 'eve',
			'password' => 'pass1234',
			'email'    => 'eve@example.com',
			'role'     => 'author',
		));

		$result = $this->users->delete_user('eve');
		$this->assertTrue($result);
		$this->assertNull($this->users->get_user('eve'));
	}

	public function test_authenticate_success(): void
	{

		$this->users->create_user(array(
			'username' => 'frank',
			'password' => 'correcthorse',
			'email'    => 'frank@example.com',
			'role'     => 'editor',
		));

		$result = $this->users->authenticate('frank', 'correcthorse');
		$this->assertIsArray($result);
		$this->assertSame('frank', $result['username']);
	}

	public function test_authenticate_wrong_password(): void
	{

		$this->users->create_user(array(
			'username' => 'grace',
			'password' => 'rightpass',
			'email'    => 'grace@example.com',
			'role'     => 'editor',
		));

		$result = $this->users->authenticate('grace', 'wrongpass');
		$this->assertInstanceOf(MC_Error::class, $result);
	}

	public function test_update_user(): void
	{

		$this->users->create_user(array(
			'username' => 'hank',
			'password' => 'pass1234',
			'email'    => 'hank@example.com',
			'role'     => 'author',
		));

		$this->users->update_user('hank', array('email' => 'hank_new@example.com'));
		$user = $this->users->get_user('hank');
		$this->assertSame('hank_new@example.com', $user['email']);
	}

	public function test_get_user_by_email(): void
	{

		$this->users->create_user(array(
			'username' => 'iris',
			'password' => 'pass1234',
			'email'    => 'iris@example.com',
			'role'     => 'contributor',
		));

		$user = $this->users->get_user_by_email('iris@example.com');
		$this->assertNotNull($user);
		$this->assertSame('iris', $user['username']);
	}
}
