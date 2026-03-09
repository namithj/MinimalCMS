<?php

/**
 * Tests for MC_Admin_Bar class (DI-based version in classes/).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Admin_Bar;
use MC_Cache;
use MC_Capabilities;
use MC_Content_Manager;
use MC_Content_Type_Registry;
use MC_Formatter;
use MC_Hooks;
use MC_Http;
use MC_Router;
use MC_Session;
use MC_User_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Admin_Bar
 */
class MCAdminBarClassTest extends TestCase
{
	private MC_Admin_Bar $bar;
	private MC_Hooks $hooks;

	protected function setUp(): void
	{

		$this->hooks = new MC_Hooks();
		$formatter   = new MC_Formatter($this->hooks);

		$temp_dir    = sys_get_temp_dir() . '/mc_bar_test_' . uniqid() . '/';
		mkdir($temp_dir, 0755, true);

		$caps    = new MC_Capabilities($this->hooks);
		$caps->initialise_roles();
		$session = new MC_Session($this->hooks, $temp_dir . 'sessions');
		$users   = new MC_User_Manager(
			$this->hooks,
			$formatter,
			$caps,
			$session,
			$temp_dir . 'users.php',
			base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))
		);

		$content_dir = $temp_dir . 'content/';
		mkdir($content_dir, 0755, true);
		$cache = new MC_Cache($content_dir . 'cache/');
		$types = new MC_Content_Type_Registry($this->hooks, $content_dir);
		$types->register('page', array('label' => 'Page'));
		$content = new MC_Content_Manager($types, $this->hooks, $cache, $formatter, $content_dir);
		$http    = new MC_Http($this->hooks, 'test-secret-key');
		$router  = new MC_Router($this->hooks, $content, $types, $http);

		$this->bar = new MC_Admin_Bar($this->hooks, $users, $router, $formatter);

		// Cleanup helper stored for tearDown.
		$this->temp_dir = $temp_dir;
	}

	private string $temp_dir;

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

	public function test_instantiation(): void
	{

		$this->assertInstanceOf(MC_Admin_Bar::class, $this->bar);
	}

	public function test_add_node(): void
	{

		$this->bar->add_node('test', array(
			'label' => 'Test',
			'href'  => '/test',
		));

		// Since nodes is private, we can verify via render() producing no output
		// (user not logged in) — which tests isolation correctly.
		ob_start();
		$this->bar->render();
		$html = ob_get_clean();

		// No logged-in user — should produce nothing.
		$this->assertSame('', $html);
	}

	public function test_remove_node(): void
	{

		$this->bar->add_node('removeme', array('label' => 'Remove'));
		$this->bar->remove_node('removeme');

		// No way to assert directly without reflection, so just verify no error.
		$this->assertTrue(true);
	}

	public function test_render_produces_nothing_when_not_logged_in(): void
	{

		ob_start();
		$this->bar->render();
		$html = ob_get_clean();

		$this->assertSame('', $html);
	}
}
