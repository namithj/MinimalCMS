<?php

/**
 * Tests for MC_Keystore class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_File_Guard;
use MC_Keystore;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Keystore
 */
class MCKeystoreClassTest extends TestCase
{
	private string $temp_dir;
	private string $data_dir;

	protected function setUp(): void
	{

		$this->temp_dir = sys_get_temp_dir() . '/mc_keystore_test_' . uniqid() . '/';
		$this->data_dir = $this->temp_dir . 'mc-data/';
		mkdir($this->data_dir, 0755, true);
	}

	protected function tearDown(): void
	{

		// Remove any env var we might have set.
		putenv(MC_Keystore::ENV_VAR);

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

	/*
	 * -------------------------------------------------------------------------
	 *  Master key generation
	 * -------------------------------------------------------------------------
	 */

	public function test_generate_master_key_creates_file(): void
	{

		$hex = MC_Keystore::generate_master_key($this->data_dir);
		$this->assertSame(64, strlen($hex));
		$this->assertTrue(ctype_xdigit($hex));
		$this->assertFileExists($this->data_dir . MC_Keystore::WEBROOT_FILE);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Master key resolution
	 * -------------------------------------------------------------------------
	 */

	public function test_resolve_from_env_var(): void
	{

		$key_hex = bin2hex(random_bytes(32));
		putenv(MC_Keystore::ENV_VAR . '=' . $key_hex);

		$resolved = MC_Keystore::resolve_master_key($this->data_dir, $this->temp_dir);
		$this->assertSame(hex2bin($key_hex), $resolved);
	}

	public function test_resolve_from_webroot_file(): void
	{

		$key_hex = MC_Keystore::generate_master_key($this->data_dir);
		$resolved = MC_Keystore::resolve_master_key($this->data_dir, $this->temp_dir);
		$this->assertSame(hex2bin($key_hex), $resolved);
	}

	public function test_resolve_throws_when_no_key(): void
	{

		$this->expectException(\RuntimeException::class);
		MC_Keystore::resolve_master_key($this->data_dir, $this->temp_dir);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Keystore save / load
	 * -------------------------------------------------------------------------
	 */

	public function test_save_and_load_keys(): void
	{

		$master_hex = MC_Keystore::generate_master_key($this->data_dir);
		$master_key = hex2bin($master_hex);

		$keys = array(
			'secret_key'     => bin2hex(random_bytes(32)),
			'encryption_key' => bin2hex(random_bytes(32)),
		);

		$this->assertTrue(MC_Keystore::save_keys($this->data_dir, $master_key, $keys));

		$loaded = MC_Keystore::load_keys($this->data_dir, $master_key);
		$this->assertSame($keys['secret_key'], $loaded['secret_key']);
		$this->assertSame($keys['encryption_key'], $loaded['encryption_key']);
	}

	public function test_load_returns_empty_for_missing_keystore(): void
	{

		$master_key = random_bytes(32);
		$loaded = MC_Keystore::load_keys($this->data_dir, $master_key);
		$this->assertSame('', $loaded['secret_key']);
		$this->assertSame('', $loaded['encryption_key']);
	}

	public function test_load_returns_empty_for_wrong_master_key(): void
	{

		$master_key = random_bytes(32);
		$wrong_key  = random_bytes(32);

		$keys = array(
			'secret_key'     => 'test_secret',
			'encryption_key' => 'test_enc',
		);

		MC_Keystore::save_keys($this->data_dir, $master_key, $keys);
		$loaded = MC_Keystore::load_keys($this->data_dir, $wrong_key);
		$this->assertSame('', $loaded['secret_key']);
	}

	public function test_keystore_file_is_guarded(): void
	{

		$master_key = random_bytes(32);
		MC_Keystore::save_keys($this->data_dir, $master_key, array(
			'secret_key'     => 'sk',
			'encryption_key' => 'ek',
		));

		$raw = file_get_contents($this->data_dir . MC_Keystore::KEYS_FILE);
		$this->assertStringStartsWith(MC_File_Guard::GUARD, $raw);
	}

	public function test_master_key_file_is_guarded(): void
	{

		MC_Keystore::generate_master_key($this->data_dir);

		$raw = file_get_contents($this->data_dir . MC_Keystore::WEBROOT_FILE);
		$this->assertStringStartsWith(MC_File_Guard::GUARD, $raw);
	}
}
