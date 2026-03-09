<?php

/**
 * Tests for MC_Template_Loader class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Cache;
use MC_Config;
use MC_Content_Manager;
use MC_Content_Type_Registry;
use MC_Formatter;
use MC_Hooks;
use MC_Http;
use MC_Router;
use MC_Template_Loader;
use MC_Theme_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Template_Loader
 */
class MCTemplateLoaderClassTest extends TestCase
{
	private MC_Template_Loader $loader;
	private MC_Router $router;
	private MC_Theme_Manager $themes;
	private MC_Hooks $hooks;
	private string $temp_dir;

	protected function setUp(): void
	{

		$this->temp_dir = sys_get_temp_dir() . '/mc_tpl_test_' . uniqid() . '/';
		$content_dir    = $this->temp_dir . 'content/';
		$themes_dir     = $this->temp_dir . 'themes/';

		mkdir($content_dir, 0755, true);
		mkdir($themes_dir . 'default/', 0755, true);

		// Create theme files.
		file_put_contents($themes_dir . 'default/theme.json', json_encode(array('name' => 'Default')));
		file_put_contents($themes_dir . 'default/index.php', '<?php echo "index";');
		file_put_contents($themes_dir . 'default/front-page.php', '<?php echo "front";');
		file_put_contents($themes_dir . 'default/single.php', '<?php echo "single";');
		file_put_contents($themes_dir . 'default/404.php', '<?php echo "404";');

		$config_path = $this->temp_dir . 'config.json';
		file_put_contents($config_path, json_encode(array('active_theme' => 'default')));

		$this->hooks = new MC_Hooks();
		$formatter   = new MC_Formatter($this->hooks);
		$cache       = new MC_Cache($content_dir . 'cache/');
		$config      = new MC_Config($config_path, $config_path);
		$config->load();

		$types = new MC_Content_Type_Registry($this->hooks, $content_dir);
		$types->register('page', array('label' => 'Page'));

		$content      = new MC_Content_Manager($types, $this->hooks, $cache, $formatter, $content_dir);
		$http         = new MC_Http($this->hooks, 'test-secret-key');
		$this->router = new MC_Router($this->hooks, $content, $types, $http);
		$this->themes = new MC_Theme_Manager($this->hooks, $config, $themes_dir);

		$this->loader = new MC_Template_Loader($this->hooks, $this->router, $this->themes);
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

	public function test_get_hierarchy_default(): void
	{

		$templates = $this->loader->get_hierarchy();
		$this->assertIsArray($templates);
		$this->assertContains('index.php', $templates);
	}

	public function test_locate_finds_index(): void
	{

		$file = $this->loader->locate(array('index.php'));
		$this->assertStringEndsWith('index.php', $file);
		$this->assertFileExists($file);
	}

	public function test_locate_finds_front_page(): void
	{

		$file = $this->loader->locate(array('front-page.php', 'index.php'));
		$this->assertStringEndsWith('front-page.php', $file);
	}

	public function test_locate_returns_empty_for_missing(): void
	{

		$file = $this->loader->locate(array('nonexistent.php'));
		$this->assertSame('', $file);
	}

	public function test_locate_falls_back(): void
	{

		// No archive.php exists, should return empty for just that.
		$file = $this->loader->locate(array('archive.php'));
		$this->assertSame('', $file);

		// Falls back to index.php.
		$file = $this->loader->locate(array('archive.php', 'index.php'));
		$this->assertStringEndsWith('index.php', $file);
	}
}
