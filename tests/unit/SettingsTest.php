<?php
/**
 * Unit tests for the Settings API (mc-includes/settings.php).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

	protected function setUp(): void {

		parent::setUp();

		// Reset registries.
		$GLOBALS['mc_field_types']       = array();
		$GLOBALS['mc_settings_cache']    = array();
		$GLOBALS['mc_settings_pages']    = array();
		$GLOBALS['mc_settings_sections'] = array();
		$GLOBALS['mc_settings_fields']   = array();

		// Ensure settings directory exists in test tmp.
		$dir = MC_DATA_DIR . 'settings/';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0755, true );
		}

		// Register core field types for tests that need them.
		mc_register_core_field_types();
	}

	protected function tearDown(): void {

		// Clean up any settings files.
		$dir = MC_DATA_DIR . 'settings/';
		if ( is_dir( $dir ) ) {
			$files = glob( $dir . '*.json' );
			if ( $files ) {
				foreach ( $files as $f ) {
					unlink( $f );
				}
			}
		}

		parent::tearDown();
	}

	/*
	 * ── Settings storage ─────────────────────────────────────────────────
	 */

	public function test_settings_path_returns_json_path(): void {

		$path = mc_settings_path( 'core.general' );
		$this->assertStringEndsWith( 'settings/core.general.json', $path );
	}

	public function test_settings_path_sanitises_namespace(): void {

		$path = mc_settings_path( 'Plugin/Bad Name!' );
		$this->assertStringEndsWith( 'settings/plugin-bad-name-.json', $path );
	}

	public function test_get_settings_empty_namespace(): void {

		$data = mc_get_settings( 'nonexistent' );
		$this->assertSame( array(), $data );
	}

	public function test_update_and_get_settings(): void {

		$result = mc_update_settings( 'test.ns', array( 'key1' => 'value1', 'key2' => 42 ) );
		$this->assertTrue( $result );

		$data = mc_get_settings( 'test.ns' );
		$this->assertSame( 'value1', $data['key1'] );
		$this->assertSame( 42, $data['key2'] );
	}

	public function test_update_settings_merges(): void {

		mc_update_settings( 'test.merge', array( 'a' => 1, 'b' => 2 ) );
		mc_update_settings( 'test.merge', array( 'b' => 99, 'c' => 3 ) );

		// Clear cache to read from disk.
		$GLOBALS['mc_settings_cache'] = array();
		$data = mc_get_settings( 'test.merge' );

		$this->assertSame( 1, $data['a'] );
		$this->assertSame( 99, $data['b'] );
		$this->assertSame( 3, $data['c'] );
	}

	public function test_get_setting_with_default(): void {

		$this->assertSame( 'fallback', mc_get_setting( 'empty.ns', 'missing', 'fallback' ) );
	}

	public function test_get_setting_returns_stored(): void {

		mc_update_settings( 'test.single', array( 'foo' => 'bar' ) );
		$this->assertSame( 'bar', mc_get_setting( 'test.single', 'foo' ) );
	}

	public function test_delete_setting_key(): void {

		mc_update_settings( 'test.del', array( 'keep' => 1, 'remove' => 2 ) );
		mc_delete_setting( 'test.del', 'remove' );

		$GLOBALS['mc_settings_cache'] = array();
		$data = mc_get_settings( 'test.del' );

		$this->assertArrayHasKey( 'keep', $data );
		$this->assertArrayNotHasKey( 'remove', $data );
	}

	public function test_delete_settings_removes_file(): void {

		mc_update_settings( 'test.killme', array( 'x' => 1 ) );
		$this->assertTrue( is_file( mc_settings_path( 'test.killme' ) ) );

		mc_delete_settings( 'test.killme' );
		$this->assertFalse( is_file( mc_settings_path( 'test.killme' ) ) );
	}

	/*
	 * ── Settings page registration ───────────────────────────────────────
	 */

	public function test_register_settings_page(): void {

		mc_register_settings_page( 'my-page', array(
			'title'      => 'My Page',
			'capability' => 'manage_settings',
		) );

		$page = mc_get_settings_page( 'my-page' );
		$this->assertNotNull( $page );
		$this->assertSame( 'My Page', $page['title'] );
	}

	public function test_get_settings_page_null_for_unknown(): void {

		$this->assertNull( mc_get_settings_page( 'nope' ) );
	}

	public function test_get_settings_pages_returns_all(): void {

		mc_register_settings_page( 'a', array( 'title' => 'A' ) );
		mc_register_settings_page( 'b', array( 'title' => 'B' ) );

		$all = mc_get_settings_pages();
		$this->assertArrayHasKey( 'a', $all );
		$this->assertArrayHasKey( 'b', $all );
	}

	/*
	 * ── Section registration ─────────────────────────────────────────────
	 */

	public function test_register_settings_section(): void {

		mc_register_settings_page( 'pg', array( 'title' => 'Page' ) );
		mc_register_settings_section( 'pg', 'sec1', array(
			'title'    => 'Section 1',
			'priority' => 5,
		) );

		$sections = mc_get_settings_page_sections( 'pg' );
		$this->assertCount( 1, $sections );

		$first = reset( $sections );
		$this->assertSame( 'Section 1', $first['title'] );
	}

	public function test_sections_sort_by_priority(): void {

		mc_register_settings_page( 'pg', array() );
		mc_register_settings_section( 'pg', 'low', array( 'title' => 'Low', 'priority' => 20 ) );
		mc_register_settings_section( 'pg', 'high', array( 'title' => 'High', 'priority' => 5 ) );

		$sections = mc_get_settings_page_sections( 'pg' );
		$keys     = array_keys( $sections );

		$this->assertSame( 'pg:high', $keys[0] );
		$this->assertSame( 'pg:low', $keys[1] );
	}

	/*
	 * ── Field registration ───────────────────────────────────────────────
	 */

	public function test_register_setting_field(): void {

		mc_register_settings_page( 'pg', array() );
		mc_register_settings_section( 'pg', 'sec', array( 'title' => 'Sec' ) );
		mc_register_setting_field( 'pg', 'sec', 'my_field', array(
			'type'  => 'text',
			'label' => 'My Field',
		) );

		$fields = mc_get_settings_section_fields( 'pg', 'sec' );
		$this->assertArrayHasKey( 'my_field', $fields );
		$this->assertSame( 'My Field', $fields['my_field']['label'] );
	}

	public function test_get_settings_page_fields(): void {

		mc_register_settings_page( 'pg', array() );
		mc_register_settings_section( 'pg', 's1', array( 'title' => 'S1' ) );
		mc_register_settings_section( 'pg', 's2', array( 'title' => 'S2' ) );
		mc_register_setting_field( 'pg', 's1', 'f1', array( 'type' => 'text' ) );
		mc_register_setting_field( 'pg', 's2', 'f2', array( 'type' => 'number' ) );

		$all = mc_get_settings_page_fields( 'pg' );
		$this->assertArrayHasKey( 'f1', $all );
		$this->assertArrayHasKey( 'f2', $all );
	}

	/*
	 * ── Settings page values ─────────────────────────────────────────────
	 */

	public function test_settings_page_values_uses_defaults(): void {

		mc_register_settings_page( 'test-page', array( 'namespace' => 'test.vals' ) );
		mc_register_settings_section( 'test-page', 'sec', array( 'title' => 'S' ) );
		mc_register_setting_field( 'test-page', 'sec', 'name', array(
			'type'    => 'text',
			'default' => 'Default Name',
		) );

		$values = mc_get_settings_page_values( 'test-page' );
		$this->assertSame( 'Default Name', $values['name'] );
	}

	public function test_settings_page_values_uses_stored(): void {

		mc_register_settings_page( 'test-page', array( 'namespace' => 'test.stored' ) );
		mc_register_settings_section( 'test-page', 'sec', array( 'title' => 'S' ) );
		mc_register_setting_field( 'test-page', 'sec', 'name', array(
			'type'    => 'text',
			'default' => 'Default',
		) );

		mc_update_settings( 'test.stored', array( 'name' => 'Custom' ) );

		$values = mc_get_settings_page_values( 'test-page' );
		$this->assertSame( 'Custom', $values['name'] );
	}

	/*
	 * ── Settings page rendering ──────────────────────────────────────────
	 */

	public function test_render_settings_page_outputs_form(): void {

		mc_register_settings_page( 'render-test', array( 'title' => 'Render' ) );
		mc_register_settings_section( 'render-test', 'sec', array( 'title' => 'Section' ) );
		mc_register_setting_field( 'render-test', 'sec', 'field1', array(
			'type'  => 'text',
			'label' => 'Field One',
		) );

		ob_start();
		mc_render_settings_page( 'render-test', array( 'field1' => 'val' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'Field One', $html );
		$this->assertStringContainsString( 'Section', $html );
		$this->assertStringContainsString( 'Save Settings', $html );
	}

	public function test_render_settings_page_shows_notice(): void {

		mc_register_settings_page( 'notice-test', array() );

		ob_start();
		mc_render_settings_page( 'notice-test', array(), array(), 'Saved!', 'success' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Saved!', $html );
		$this->assertStringContainsString( 'notice-success', $html );
	}

	/*
	 * ── Core settings pages ──────────────────────────────────────────────
	 */

	public function test_core_settings_pages_registered(): void {

		mc_register_core_settings_pages();

		$page = mc_get_settings_page( 'general' );
		$this->assertNotNull( $page );
		$this->assertSame( 'Settings', $page['title'] );
	}

	public function test_core_general_has_expected_sections(): void {

		mc_register_core_settings_pages();

		$sections = mc_get_settings_page_sections( 'general' );
		$ids      = array_map( fn( $s ) => $s['id'], $sections );

		$this->assertContains( 'general', $ids );
		$this->assertContains( 'reading', $ids );
		$this->assertContains( 'advanced', $ids );
	}

	public function test_core_general_has_expected_fields(): void {

		mc_register_core_settings_pages();

		$fields = mc_get_settings_page_fields( 'general' );

		$expected = array(
			'site_name', 'site_description', 'site_url', 'timezone',
			'front_page', 'posts_per_page', 'permalink_structure', 'debug',
		);

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $fields, "Expected field '{$key}' on general page." );
		}
	}
}
