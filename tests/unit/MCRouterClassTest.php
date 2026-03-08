<?php

/**
 * Tests for MC_Router class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Cache;
use MC_Content_Manager;
use MC_Content_Type_Registry;
use MC_Formatter;
use MC_Hooks;
use MC_Router;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Router
 */
class MCRouterClassTest extends TestCase
{
	private MC_Router $router;
	private MC_Hooks $hooks;
	private MC_Content_Manager $content;
	private MC_Content_Type_Registry $types;
	private string $content_dir;

	protected function setUp(): void
	{

		$this->content_dir = sys_get_temp_dir() . '/mc_router_test_' . uniqid() . '/';
		mkdir($this->content_dir, 0755, true);

		$this->hooks = new MC_Hooks();
		$formatter   = new MC_Formatter($this->hooks);
		$cache       = new MC_Cache($this->content_dir . 'cache/');

		$this->types = new MC_Content_Type_Registry($this->hooks, $this->content_dir);
		$this->types->register('page', array('label' => 'Page'));

		$this->content = new MC_Content_Manager(
			$this->types,
			$this->hooks,
			$cache,
			$formatter,
			$this->content_dir
		);

		$this->router = new MC_Router($this->hooks, $this->content, $this->types);
	}

	protected function tearDown(): void
	{

		$this->rm_recursive($this->content_dir);
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

	public function test_instantiation(): void
	{

		$this->assertInstanceOf(MC_Router::class, $this->router);
	}

	public function test_add_route(): void
	{

		$called = false;
		$this->router->add_route('/test', function () use (&$called) {
			$called = true;
		});

		// Routes are stored internally; no getter to assert.
		$this->assertFalse($called, 'Callback should not execute on registration alone');
	}

	public function test_get_query_returns_array(): void
	{

		$query = $this->router->get_query();
		$this->assertIsArray($query);
	}

	public function test_is_404_default(): void
	{

		$this->assertFalse($this->router->is_404());
	}

	public function test_is_front_page_default(): void
	{

		$this->assertFalse($this->router->is_front_page());
	}

	public function test_is_single_default(): void
	{

		$this->assertFalse($this->router->is_single());
	}

	public function test_is_archive_default(): void
	{

		$this->assertFalse($this->router->is_archive());
	}

	public function test_get_page_num_default(): void
	{

		$this->assertSame(1, $this->router->get_page_num());
	}
}
