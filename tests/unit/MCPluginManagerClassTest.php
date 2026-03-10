<?php

/**
 * Tests for MC_Plugin_Manager class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Config;
use MC_File_Guard;
use MC_Hooks;
use MC_Plugin_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Plugin_Manager
 */
class MCPluginManagerClassTest extends TestCase
{
	private MC_Plugin_Manager $plugins;
	private MC_Config $config;
	private MC_Hooks $hooks;
	private string $temp_dir;
	private string $plugins_dir;
	private string $mu_plugins_dir;

	protected function setUp(): void
	{

		$this->temp_dir       = sys_get_temp_dir() . '/mc_plugin_test_' . uniqid() . '/';
		$this->plugins_dir    = $this->temp_dir . 'plugins/';
		$this->mu_plugins_dir = $this->temp_dir . 'mu-plugins/';
		mkdir($this->plugins_dir, 0755, true);
		mkdir($this->mu_plugins_dir, 0755, true);

		// Create a test plugin.
		$plugin_path = $this->plugins_dir . 'test-plugin/';
		mkdir($plugin_path, 0755, true);
		file_put_contents($plugin_path . 'test-plugin.php', "<?php\n/**\n * Plugin Name: Test Plugin\n * Description: A test plugin.\n * Version: 1.0\n */\n");

		// Create config.
		$config_path = $this->temp_dir . 'config.php';
		MC_File_Guard::write_json($config_path, array(
			'active_plugins' => array('test-plugin/test-plugin.php'),
		));

		$this->hooks  = new MC_Hooks();
		$this->config = new MC_Config($config_path, $config_path);
		$this->config->load();

		$this->plugins = new MC_Plugin_Manager($this->hooks, $this->config, $this->plugins_dir, $this->mu_plugins_dir);
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

	public function test_discover(): void
	{

		$plugins = $this->plugins->discover();
		$this->assertIsArray($plugins);
		$this->assertArrayHasKey('test-plugin/test-plugin.php', $plugins);
	}

	public function test_get_active(): void
	{

		$active = $this->plugins->get_active();
		$this->assertContains('test-plugin/test-plugin.php', $active);
	}

	public function test_is_active(): void
	{

		$this->assertTrue($this->plugins->is_active('test-plugin/test-plugin.php'));
		$this->assertFalse($this->plugins->is_active('nonexistent'));
	}

	public function test_get_plugin_data(): void
	{

		$data = $this->plugins->get_plugin_data($this->plugins_dir . 'test-plugin/test-plugin.php');
		$this->assertIsArray($data);
		$this->assertSame('Test Plugin', $data['name']);
	}

	public function test_activate(): void
	{

		// Create another plugin.
		$path = $this->plugins_dir . 'other/';
		mkdir($path, 0755, true);
		file_put_contents($path . 'other.php', "<?php\n/**\n * Plugin Name: Other Plugin\n * Version: 1.0\n */\n");

		$result = $this->plugins->activate('other/other.php');
		$this->assertTrue($result);
		$this->assertTrue($this->plugins->is_active('other/other.php'));
	}

	public function test_deactivate(): void
	{

		$result = $this->plugins->deactivate('test-plugin/test-plugin.php');
		$this->assertTrue($result);
		$this->assertFalse($this->plugins->is_active('test-plugin/test-plugin.php'));
	}
}
