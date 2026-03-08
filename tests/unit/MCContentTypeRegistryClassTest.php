<?php
/**
 * Tests for MC_Content_Type_Registry class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Content_Type_Registry;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Content_Type_Registry
 */
class MCContentTypeRegistryClassTest extends TestCase {

	private MC_Content_Type_Registry $registry;
	private MC_Hooks $hooks;
	private string $content_dir;

	protected function setUp(): void {

		$this->content_dir = sys_get_temp_dir() . '/mc_ctr_test_' . uniqid() . '/';
		mkdir($this->content_dir, 0755, true);

		$this->hooks    = new MC_Hooks();
		$this->registry = new MC_Content_Type_Registry($this->hooks, $this->content_dir);
	}

	protected function tearDown(): void {

		$this->rm_recursive($this->content_dir);
	}

	private function rm_recursive(string $dir): void {

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

	public function test_register_content_type(): void {

		$this->registry->register('article', array(
			'label'  => 'Article',
			'plural' => 'Articles',
		));

		$type = $this->registry->get('article');
		$this->assertNotNull($type);
		$this->assertSame('Article', $type['label']);
	}

	public function test_get_returns_null_for_unregistered(): void {

		$this->assertNull($this->registry->get('nonexistent'));
	}

	public function test_all_returns_registered_types(): void {

		$this->registry->register('page', array('label' => 'Page'));
		$this->registry->register('post', array('label' => 'Post'));

		$all = $this->registry->all();
		$this->assertArrayHasKey('page', $all);
		$this->assertArrayHasKey('post', $all);
	}

	public function test_type_folder_returns_folder_name(): void {

		$this->registry->register('page', array('label' => 'Page'));
		$folder = $this->registry->type_folder('page');
		$this->assertSame('page', $folder);
	}

	public function test_register_defaults(): void {

		$this->registry->register_defaults();
		$this->assertNotNull($this->registry->get('page'));
	}

	public function test_register_fires_hook(): void {

		$fired = false;
		$this->hooks->add_action('mc_registered_content_type', function () use (&$fired) {
			$fired = true;
		});

		$this->registry->register('event', array('label' => 'Event'));
		$this->assertTrue($fired);
	}

	public function test_register_args_filter(): void {

		$this->hooks->add_filter('mc_register_content_type_args', function ($args) {
			$args['label'] = 'Filtered';
			return $args;
		});

		$this->registry->register('news', array('label' => 'News'));
		$type = $this->registry->get('news');
		$this->assertSame('Filtered', $type['label']);
	}
}
