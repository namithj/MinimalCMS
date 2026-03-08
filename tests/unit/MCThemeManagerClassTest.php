<?php

/**
 * Tests for MC_Theme_Manager class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Config;
use MC_Hooks;
use MC_Theme_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Theme_Manager
 */
class MCThemeManagerClassTest extends TestCase
{
	private MC_Theme_Manager $themes;
	private MC_Config $config;
	private MC_Hooks $hooks;
	private string $themes_dir;
	private string $temp_dir;

	protected function setUp(): void
	{

		$this->temp_dir   = sys_get_temp_dir() . '/mc_theme_test_' . uniqid() . '/';
		$this->themes_dir = $this->temp_dir . 'themes/';
		mkdir($this->themes_dir, 0755, true);

		// Create a minimal theme.
		$theme_path = $this->themes_dir . 'default/';
		mkdir($theme_path, 0755, true);
		file_put_contents($theme_path . 'theme.json', json_encode(array(
			'name'    => 'Default',
			'version' => '1.0',
		)));
		file_put_contents($theme_path . 'index.php', '<?php // index');

		// Create a config.
		$config_path = $this->temp_dir . 'config.json';
		file_put_contents($config_path, json_encode(array(
			'active_theme' => 'default',
		)));

		$this->hooks  = new MC_Hooks();
		$this->config = new MC_Config($config_path, $config_path);
		$this->config->load();

		$this->themes = new MC_Theme_Manager($this->hooks, $this->config, $this->themes_dir);
		$this->themes->load();
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

		$themes = $this->themes->discover();
		$this->assertIsArray($themes);
		$this->assertArrayHasKey('default', $themes);
	}

	public function test_get_active(): void
	{

		$active = $this->themes->get_active();
		$this->assertIsArray($active);
		$this->assertSame('Default', $active['name'] ?? '');
	}

	public function test_get_active_dir(): void
	{

		$dir = $this->themes->get_active_dir();
		$this->assertStringEndsWith('default/', $dir);
	}

	public function test_get_theme_data(): void
	{

		$data = $this->themes->get_theme_data($this->themes_dir . 'default/');
		$this->assertIsArray($data);
		$this->assertSame('Default', $data['name']);
	}

	public function test_get_theme_data_nonexistent(): void
	{

		$data = $this->themes->get_theme_data($this->themes_dir . 'nonexistent/');
		// get_theme_data always returns an array with defaults.
		$this->assertIsArray($data);
		$this->assertSame('nonexistent', $data['name']);
	}

	public function test_switch_theme(): void
	{

		// Create another theme.
		$alt_path = $this->themes_dir . 'alt/';
		mkdir($alt_path, 0755, true);
		file_put_contents($alt_path . 'theme.json', json_encode(array(
			'name' => 'Alt Theme',
		)));

		$result = $this->themes->switch_theme('alt');
		$this->assertTrue($result);
		// Config is updated; reload to populate internal state.
		$this->assertSame('alt', $this->config->get('active_theme'));
	}

	public function test_get_parent_dir_returns_empty_when_no_parent(): void
	{

		$parent = $this->themes->get_parent_dir();
		$this->assertSame('', $parent);
	}
}
