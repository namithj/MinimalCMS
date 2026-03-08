<?php

/**
 * Tests for MC_Settings class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Hooks;
use MC_Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Settings
 */
class MCSettingsClassTest extends TestCase
{
	private MC_Settings $settings;
	private MC_Hooks $hooks;
	private string $settings_dir;

	protected function setUp(): void
	{

		$this->settings_dir = sys_get_temp_dir() . '/mc_settings_test_' . uniqid() . '/';
		mkdir($this->settings_dir, 0755, true);

		$this->hooks    = new MC_Hooks();
		$this->settings = new MC_Settings($this->hooks, $this->settings_dir);
	}

	protected function tearDown(): void
	{

		$this->rm_recursive($this->settings_dir);
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

	public function test_get_all_empty(): void
	{

		$result = $this->settings->get_all('core.general');
		$this->assertIsArray($result);
	}

	public function test_update_and_get(): void
	{

		$this->settings->update('core.general', array('site_name' => 'Test Site'));
		$value = $this->settings->get('core.general', 'site_name');
		$this->assertSame('Test Site', $value);
	}

	public function test_get_default_value(): void
	{

		$value = $this->settings->get('core.general', 'nonexistent', 'default_val');
		$this->assertSame('default_val', $value);
	}

	public function test_get_all_returns_saved(): void
	{

		$this->settings->update('core.general', array(
			'key1' => 'val1',
			'key2' => 'val2',
		));

		$all = $this->settings->get_all('core.general');
		$this->assertSame('val1', $all['key1']);
		$this->assertSame('val2', $all['key2']);
	}

	public function test_delete_key(): void
	{

		$this->settings->update('core.general', array(
			'keep'   => 'yes',
			'remove' => 'no',
		));

		$this->settings->delete_key('core.general', 'remove');
		$all = $this->settings->get_all('core.general');
		$this->assertArrayNotHasKey('remove', $all);
		$this->assertArrayHasKey('keep', $all);
	}

	public function test_delete_group(): void
	{

		$this->settings->update('temp.section', array('key' => 'value'));
		$this->settings->delete('temp.section');
		$all = $this->settings->get_all('temp.section');
		$this->assertEmpty($all);
	}

	public function test_update_fires_hook(): void
	{

		$fired = false;
		$this->hooks->add_action('mc_settings_updated', function () use (&$fired) {
			$fired = true;
		});

		$this->settings->update('core.general', array('x' => '1'));
		$this->assertTrue($fired);
	}

	public function test_path_generation(): void
	{

		$path = $this->settings->path('core.general');
		$this->assertStringEndsWith('core.general.json', $path);
	}
}
