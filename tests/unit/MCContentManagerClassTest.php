<?php

/**
 * Tests for MC_Content_Manager class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Cache;
use MC_Content_Manager;
use MC_Content_Type_Registry;
use MC_Error;
use MC_Formatter;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Content_Manager
 */
class MCContentManagerClassTest extends TestCase
{
	private MC_Content_Manager $content;
	private MC_Content_Type_Registry $types;
	private MC_Hooks $hooks;
	private string $content_dir;

	protected function setUp(): void
	{

		$this->content_dir = sys_get_temp_dir() . '/mc_cm_test_' . uniqid() . '/';
		mkdir($this->content_dir, 0755, true);

		$this->hooks = new MC_Hooks();
		$formatter   = new MC_Formatter($this->hooks);
		$cache       = new MC_Cache($this->content_dir . 'cache/');

		$this->types = new MC_Content_Type_Registry($this->hooks, $this->content_dir);
		$this->types->register('page', array('label' => 'Page', 'plural' => 'Pages'));
		$this->types->register('post', array('label' => 'Post', 'plural' => 'Posts'));

		$this->content = new MC_Content_Manager(
			$this->types,
			$this->hooks,
			$cache,
			$formatter,
			$this->content_dir
		);
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

	public function test_save_and_get(): void
	{

		$result = $this->content->save('page', 'hello', array(
			'title' => 'Hello World',
			'body'  => '# Hello',
		));

		$this->assertTrue($result);

		$item = $this->content->get('page', 'hello');
		$this->assertIsArray($item);
		$this->assertSame('Hello World', $item['title']);
	}

	public function test_get_nonexistent(): void
	{

		$item = $this->content->get('page', 'no-such-slug');
		$this->assertNull($item);
	}

	public function test_exists(): void
	{

		$this->content->save('page', 'test-exists', array(
			'title' => 'Exists',
			'body'  => '',
		));

		$this->assertTrue($this->content->exists('page', 'test-exists'));
		$this->assertFalse($this->content->exists('page', 'nonexistent'));
	}

	public function test_delete(): void
	{

		$this->content->save('page', 'deleteme', array(
			'title' => 'Delete Me',
			'body'  => '',
		));

		$result = $this->content->delete('page', 'deleteme');
		$this->assertTrue($result);
		$this->assertFalse($this->content->exists('page', 'deleteme'));
	}

	public function test_query_returns_array(): void
	{

		$this->content->save('page', 'page-a', array('title' => 'A', 'body' => ''));
		$this->content->save('page', 'page-b', array('title' => 'B', 'body' => ''));

		$items = $this->content->query(array('type' => 'page'));
		$this->assertIsArray($items);
		$this->assertGreaterThanOrEqual(2, count($items));
	}

	public function test_count(): void
	{

		$this->content->save('post', 'post-1', array('title' => 'Post 1', 'body' => ''));
		$this->content->save('post', 'post-2', array('title' => 'Post 2', 'body' => ''));

		$this->assertGreaterThanOrEqual(2, $this->content->count('post'));
	}

	public function test_save_fires_hook(): void
	{

		$fired = false;
		$this->hooks->add_action('mc_content_saved', function () use (&$fired) {
			$fired = true;
		});

		$this->content->save('page', 'hooked', array('title' => 'Hooked', 'body' => ''));
		$this->assertTrue($fired);
	}

	public function test_delete_fires_hook(): void
	{

		$this->content->save('page', 'will-delete', array('title' => 'X', 'body' => ''));

		$fired = false;
		$this->hooks->add_action('mc_content_deleted', function () use (&$fired) {
			$fired = true;
		});

		$this->content->delete('page', 'will-delete');
		$this->assertTrue($fired);
	}
}
