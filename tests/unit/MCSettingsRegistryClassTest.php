<?php
/**
 * Tests for MC_Settings_Registry class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Field_Registry;
use MC_Formatter;
use MC_Hooks;
use MC_Http;
use MC_Settings;
use MC_Settings_Registry;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Settings_Registry
 */
class MCSettingsRegistryClassTest extends TestCase {

	private MC_Settings_Registry $registry;
	private MC_Hooks $hooks;
	private string $settings_dir;

	protected function setUp(): void {

		$this->settings_dir = sys_get_temp_dir() . '/mc_sr_test_' . uniqid() . '/';
		mkdir($this->settings_dir, 0755, true);

		$this->hooks = new MC_Hooks();
		$formatter   = new MC_Formatter($this->hooks);
		$settings    = new MC_Settings($this->hooks, $this->settings_dir);
		$fields      = new MC_Field_Registry($this->hooks, $formatter);
		$fields->register_core_types();
		$http        = new MC_Http($this->hooks);

		$this->registry = new MC_Settings_Registry($this->hooks, $settings, $fields, $http);
	}

	protected function tearDown(): void {

		$this->rm_recursive($this->settings_dir);
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

	public function test_register_page(): void {

		$this->registry->register_page('core', array(
			'title' => 'General',
			'group' => 'core',
		));

		$page = $this->registry->get_page('core');
		$this->assertNotNull($page);
		$this->assertSame('General', $page['title']);
	}

	public function test_get_page_unknown(): void {

		$this->assertNull($this->registry->get_page('nonexistent'));
	}

	public function test_get_pages(): void {

		$this->registry->register_page('one', array('title' => 'One'));
		$this->registry->register_page('two', array('title' => 'Two'));

		$pages = $this->registry->get_pages();
		$this->assertArrayHasKey('one', $pages);
		$this->assertArrayHasKey('two', $pages);
	}

	public function test_register_section(): void {

		$this->registry->register_page('core', array('title' => 'Core'));
		$this->registry->register_section('core', 'general', array(
			'title' => 'General Settings',
		));

		$sections = $this->registry->get_sections('core');
		$this->assertArrayHasKey('core:general', $sections);
	}

	public function test_register_field(): void {

		$this->registry->register_page('core', array('title' => 'Core'));
		$this->registry->register_section('core', 'general', array('title' => 'General'));
		$this->registry->register_field('core', 'general', 'site_name', array(
			'type'  => 'text',
			'label' => 'Site Name',
		));

		$fields = $this->registry->get_section_fields('core', 'general');
		$this->assertArrayHasKey('site_name', $fields);
	}

	public function test_get_page_fields(): void {

		$this->registry->register_page('core', array('title' => 'Core'));
		$this->registry->register_section('core', 'sec1', array('title' => 'S1'));
		$this->registry->register_field('core', 'sec1', 'f1', array('type' => 'text', 'label' => 'F1'));
		$this->registry->register_field('core', 'sec1', 'f2', array('type' => 'text', 'label' => 'F2'));

		$all = $this->registry->get_page_fields('core');
		$this->assertArrayHasKey('f1', $all);
		$this->assertArrayHasKey('f2', $all);
	}

	public function test_get_page_values(): void {

		$this->registry->register_page('test', array('title' => 'Test', 'group' => 'test'));
		$this->registry->register_section('test', 'main', array('title' => 'Main', 'section' => 'main'));
		$this->registry->register_field('test', 'main', 'name', array(
			'type'    => 'text',
			'label'   => 'Name',
			'default' => 'Default',
		));

		$values = $this->registry->get_page_values('test');
		$this->assertIsArray($values);
	}
}
