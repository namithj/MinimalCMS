<?php

/**
 * Tests for MC_Setup class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Capabilities;
use MC_Config;
use MC_Error;
use MC_Formatter;
use MC_Hooks;
use MC_Session;
use MC_Setup;
use MC_User_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Setup
 */
class MCSetupClassTest extends TestCase
{
	private MC_Setup $setup;
	private MC_User_Manager $users;
	private MC_Config $config;
	private MC_Hooks $hooks;
	private string $temp_dir;

	protected function setUp(): void
	{

		$this->temp_dir = sys_get_temp_dir() . '/mc_setup_test_' . uniqid() . '/';
		mkdir($this->temp_dir, 0755, true);

		$config_path   = $this->temp_dir . 'config.json';
		$sample_path   = $this->temp_dir . 'config.sample.json';

		file_put_contents($sample_path, json_encode(array(
			'site_name' => 'Sample Site',
		)));
		file_put_contents($config_path, json_encode(array()));

		$this->hooks  = new MC_Hooks();
		$this->config = new MC_Config($config_path, $sample_path);
		$this->config->load();

		$formatter = new MC_Formatter($this->hooks);
		$caps      = new MC_Capabilities($this->hooks);
		$caps->initialise_roles();
		$session   = new MC_Session($this->hooks, $this->temp_dir . 'sessions');

		$this->users = new MC_User_Manager(
			$this->hooks,
			$formatter,
			$caps,
			$session,
			$this->temp_dir . 'users.php',
			base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))
		);

		$this->setup = new MC_Setup($this->config, $this->users, $this->hooks);
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
			is_dir($path) ? $this->rm_recursive($path) : unlink($path);
		}
		rmdir($dir);
	}

	public function test_needs_setup_true_when_no_users(): void
	{

		$this->assertTrue($this->setup->needs_setup());
	}

	public function test_needs_setup_false_after_user_created(): void
	{

		$this->users->create_user(array(
			'username' => 'admin',
			'password' => 'pass1234',
			'email'    => 'admin@example.com',
			'role'     => 'administrator',
		));

		$this->assertFalse($this->setup->needs_setup());
	}

	public function test_generate_keys(): void
	{

		$keys = $this->setup->generate_keys();
		$this->assertArrayHasKey('secret_key', $keys);
		$this->assertArrayHasKey('encryption_key', $keys);
		$this->assertSame(64, strlen($keys['secret_key'])); // 32 bytes hex-encoded.
	}

	public function test_run_success(): void
	{

		$result = $this->setup->run(array(
			'site_name' => 'My New Site',
			'username'  => 'admin',
			'password'  => 'securepass',
			'email'     => 'admin@example.com',
		));

		$this->assertTrue($result);

		// Verify user exists.
		$this->assertNotNull($this->users->get_user('admin'));
	}

	public function test_run_fires_complete_hook(): void
	{

		$fired = false;
		$this->hooks->add_action('mc_setup_complete', function () use (&$fired) {
			$fired = true;
		});

		$this->setup->run(array(
			'site_name' => 'Hook Test',
			'username'  => 'hookuser',
			'password'  => 'pass1234',
			'email'     => 'hook@example.com',
		));

		$this->assertTrue($fired);
	}

	public function test_run_missing_username_returns_error(): void
	{

		$result = $this->setup->run(array(
			'site_name' => 'Test',
			'password'  => 'pass1234',
			'email'     => 'test@example.com',
		));

		$this->assertInstanceOf(MC_Error::class, $result);
	}
}
