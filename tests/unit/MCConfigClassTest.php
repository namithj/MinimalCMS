<?php
/**
 * Tests for MC_Config class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Config;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Config
 */
class MCConfigClassTest extends TestCase {

	private string $config_path;
	private string $sample_path;

	protected function setUp(): void {

		$this->config_path = MC_TEST_TMP . 'v2_config.json';
		$this->sample_path = MC_TEST_TMP . 'v2_config.sample.json';

		// Create a sample config.
		file_put_contents($this->sample_path, json_encode(array(
			'site_url'  => 'http://default.example.com',
			'site_name' => 'Sample Site',
			'debug'     => false,
		)));
	}

	protected function tearDown(): void {

		if (is_file($this->config_path)) {
			unlink($this->config_path);
		}
		if (is_file($this->sample_path)) {
			unlink($this->sample_path);
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Loading
	 * -------------------------------------------------------------------------
	 */

	public function test_load_from_config_file(): void {

		file_put_contents($this->config_path, json_encode(array(
			'site_url'  => 'http://test.example.com',
			'site_name' => 'Test Site',
		)));

		$config = new MC_Config($this->config_path, $this->sample_path);
		$data   = $config->load();

		$this->assertSame('http://test.example.com', $data['site_url']);
		$this->assertSame('Test Site', $data['site_name']);
	}

	public function test_load_falls_back_to_sample(): void {

		$config = new MC_Config($this->config_path, $this->sample_path);
		$data   = $config->load();

		$this->assertSame('http://default.example.com', $data['site_url']);
		$this->assertSame('Sample Site', $data['site_name']);
	}

	public function test_load_returns_empty_when_no_files(): void {

		$config = new MC_Config('/nonexistent/config.json', '/nonexistent/sample.json');
		$data   = $config->load();

		$this->assertSame(array(), $data);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Get / Set / All
	 * -------------------------------------------------------------------------
	 */

	public function test_get_after_load(): void {

		file_put_contents($this->config_path, json_encode(array(
			'site_url' => 'http://test.example.com',
			'nested'   => array('key' => 'value'),
		)));

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();

		$this->assertSame('http://test.example.com', $config->get('site_url'));
	}

	public function test_get_with_default(): void {

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();

		$this->assertSame('fallback', $config->get('nonexistent', 'fallback'));
	}

	public function test_get_dot_notation(): void {

		file_put_contents($this->config_path, json_encode(array(
			'nested' => array('deep' => array('key' => 'found')),
		)));

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();

		$this->assertSame('found', $config->get('nested.deep.key'));
	}

	public function test_get_dot_notation_missing(): void {

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();

		$this->assertNull($config->get('a.b.c'));
	}

	public function test_set_in_memory(): void {

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();
		$config->set('new_key', 'new_value');

		$this->assertSame('new_value', $config->get('new_key'));
	}

	public function test_all(): void {

		file_put_contents($this->config_path, json_encode(array('a' => 1, 'b' => 2)));

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();

		$this->assertSame(array('a' => 1, 'b' => 2), $config->all());
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Save
	 * -------------------------------------------------------------------------
	 */

	public function test_save_persists_to_disk(): void {

		$config = new MC_Config($this->config_path, $this->sample_path);
		$config->load();
		$config->set('saved_key', 'saved_value');

		$this->assertTrue($config->save());

		// Re-read from disk.
		$config2 = new MC_Config($this->config_path, $this->sample_path);
		$config2->load();

		$this->assertSame('saved_value', $config2->get('saved_key'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Fresh install detection
	 * -------------------------------------------------------------------------
	 */

	public function test_is_fresh_install_true(): void {

		$config = new MC_Config($this->config_path, $this->sample_path);
		$this->assertTrue($config->is_fresh_install());
	}

	public function test_is_fresh_install_false(): void {

		file_put_contents($this->config_path, '{}');
		$config = new MC_Config($this->config_path, $this->sample_path);
		$this->assertFalse($config->is_fresh_install());
	}
}
