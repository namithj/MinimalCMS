<?php
/**
 * Integration tests for the Fields & Settings API extensibility.
 *
 * Validates that plugins can register custom field types and settings pages,
 * and that the complete form processing pipeline works end-to-end.
 *
 * @package MinimalCMS\Tests\Integration
 */

namespace MinimalCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;

class FieldsSettingsTest extends TestCase {

	protected function setUp(): void {

		parent::setUp();

		// Reset all registries.
		$GLOBALS['mc_field_types']            = array();
		$GLOBALS['mc_settings_cache']         = array();
		$GLOBALS['mc_settings_pages']         = array();
		$GLOBALS['mc_settings_sections']      = array();
		$GLOBALS['mc_settings_fields']        = array();
		$GLOBALS['mc_registered_admin_pages'] = array();
		$GLOBALS['mc_filters']                = array();
		$GLOBALS['mc_actions']                = array();
		$GLOBALS['mc_filters_run']            = array();
		$GLOBALS['mc_current_filter']         = array();
		$GLOBALS['mc_content_types']          = array();

		// Ensure directories exist.
		$dirs = array(
			MC_CONTENT_DIR,
			MC_DATA_DIR,
			MC_DATA_DIR . 'settings/',
		);
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
		}

		mc_register_core_field_types();
	}

	protected function tearDown(): void {

		// Clean up settings files.
		$dir = MC_DATA_DIR . 'settings/';
		if ( is_dir( $dir ) ) {
			$files = glob( $dir . '*.json' );
			if ( $files ) {
				foreach ( $files as $f ) {
					unlink( $f );
				}
			}
		}

		if ( is_dir( MC_CONTENT_DIR ) ) {
			mc_rmdir_recursive( MC_CONTENT_DIR );
		}

		parent::tearDown();
	}

	/*
	 * ── Plugin registers custom field type ───────────────────────────────
	 */

	public function test_plugin_registers_custom_field_type(): void {

		// Simulate a plugin registering a "color" field type.
		mc_register_field_type( 'color', array(
			'render_admin' => function ( array $field, mixed $value ): void {
				echo '<input type="color" name="' . mc_esc_attr( $field['id'] ) . '" value="' . mc_esc_attr( (string) $value ) . '">';
			},
			'sanitize' => function ( mixed $value ): string {
				$value = trim( (string) $value );
				if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
					return strtolower( $value );
				}
				return '#000000';
			},
			'validate' => function ( mixed $value ): true|string {
				if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $value ) ) {
					return 'Invalid hex color.';
				}
				return true;
			},
		) );

		// Verify registration.
		$this->assertNotNull( mc_get_field_type( 'color' ) );

		// Verify sanitization.
		$this->assertSame( '#ff0000', mc_sanitize_field( 'color', '#FF0000' ) );
		$this->assertSame( '#000000', mc_sanitize_field( 'color', 'invalid' ) );

		// Verify validation.
		$this->assertTrue( mc_validate_field( 'color', '#ff0000' ) );
		$this->assertIsString( mc_validate_field( 'color', 'bad' ) );

		// Verify rendering.
		ob_start();
		mc_render_field(
			array( 'id' => 'brand_color', 'type' => 'color', 'label' => 'Brand Color' ),
			'#336699'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'type="color"', $html );
		$this->assertStringContainsString( '#336699', $html );
	}

	/*
	 * ── Plugin registers a custom settings page ──────────────────────────
	 */

	public function test_plugin_registers_settings_page(): void {

		// Simulate a plugin registering a settings page.
		mc_register_settings_page( 'my-plugin', array(
			'title'      => 'My Plugin Settings',
			'capability' => 'manage_settings',
			'namespace'  => 'plugin.my-plugin',
		) );

		mc_register_settings_section( 'my-plugin', 'main', array(
			'title' => 'Main Settings',
		) );

		mc_register_setting_field( 'my-plugin', 'main', 'api_key', array(
			'type'     => 'text',
			'label'    => 'API Key',
			'required' => true,
		) );

		mc_register_setting_field( 'my-plugin', 'main', 'limit', array(
			'type'    => 'number',
			'label'   => 'Limit',
			'default' => 25,
			'options' => array( 'min' => 1, 'max' => 100 ),
		) );

		// Verify registration.
		$page = mc_get_settings_page( 'my-plugin' );
		$this->assertNotNull( $page );
		$this->assertSame( 'plugin.my-plugin', $page['namespace'] );

		// Verify default values load.
		$values = mc_get_settings_page_values( 'my-plugin' );
		$this->assertSame( '', $values['api_key'] );
		$this->assertSame( 25, $values['limit'] );

		// Save values.
		mc_update_settings( 'plugin.my-plugin', array( 'api_key' => 'abc123', 'limit' => 50 ) );

		// Verify stored values.
		$GLOBALS['mc_settings_cache'] = array();
		$values = mc_get_settings_page_values( 'my-plugin' );
		$this->assertSame( 'abc123', $values['api_key'] );
		$this->assertSame( 50, $values['limit'] );

		// Verify rendering.
		ob_start();
		mc_render_settings_page( 'my-plugin', $values );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Main Settings', $html );
		$this->assertStringContainsString( 'API Key', $html );
		$this->assertStringContainsString( 'abc123', $html );
	}

	/*
	 * ── Field processing with validation errors ──────────────────────────
	 */

	public function test_field_processing_captures_validation_errors(): void {

		$fields = array(
			'name'  => array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
			'email' => array( 'type' => 'url', 'label' => 'Website' ),
			'count' => array(
				'type'    => 'number',
				'label'   => 'Count',
				'options' => array( 'min' => 1, 'max' => 10 ),
			),
		);

		// All invalid.
		$result = mc_process_fields( $fields, array(
			'name'  => '',
			'email' => 'not-a-url',
			'count' => '999',
		) );

		$this->assertArrayHasKey( 'name', $result['errors'] );
		$this->assertArrayHasKey( 'email', $result['errors'] );
		$this->assertArrayHasKey( 'count', $result['errors'] );

		// All valid.
		$result = mc_process_fields( $fields, array(
			'name'  => 'Valid Name',
			'email' => 'https://example.com',
			'count' => '5',
		) );

		$this->assertEmpty( $result['errors'] );
		$this->assertSame( 'Valid Name', $result['values']['name'] );
		$this->assertSame( 5, $result['values']['count'] );
	}

	/*
	 * ── Settings persist and reload ──────────────────────────────────────
	 */

	public function test_settings_persist_and_hydrate(): void {

		mc_register_settings_page( 'persist-test', array(
			'title'     => 'Persist',
			'namespace' => 'test.persist',
		) );

		mc_register_settings_section( 'persist-test', 'sec', array( 'title' => 'S' ) );
		mc_register_setting_field( 'persist-test', 'sec', 'site_name', array(
			'type'    => 'text',
			'default' => 'Default',
		) );
		mc_register_setting_field( 'persist-test', 'sec', 'debug', array(
			'type'    => 'checkbox',
			'default' => false,
		) );

		// Persist values.
		mc_update_settings( 'test.persist', array( 'site_name' => 'My Site', 'debug' => true ) );

		// Clear cache to simulate a fresh page load.
		$GLOBALS['mc_settings_cache'] = array();

		// Reload and verify.
		$values = mc_get_settings_page_values( 'persist-test' );
		$this->assertSame( 'My Site', $values['site_name'] );
		$this->assertTrue( $values['debug'] );
	}

	/*
	 * ── Hook extensibility on mc_register_settings ───────────────────────
	 */

	public function test_plugin_can_add_fields_via_hook(): void {

		// Simulate core registering its pages.
		mc_register_core_settings_pages();

		// A plugin adds a field to the core general page.
		mc_register_setting_field( 'general', 'advanced', 'plugin_toggle', array(
			'type'    => 'checkbox',
			'label'   => 'Plugin Feature',
			'default' => false,
		) );

		$fields = mc_get_settings_page_fields( 'general' );
		$this->assertArrayHasKey( 'plugin_toggle', $fields );
	}

	/*
	 * ── Content editor extensibility via filter ──────────────────────────
	 */

	public function test_content_editor_fields_filter(): void {

		// Register a content type.
		mc_register_content_type( 'page', array(
			'label'        => 'Pages',
			'hierarchical' => true,
		) );

		// Base attribute fields (simulating what edit-page.php defines).
		$base_fields = array(
			'slug'     => array( 'id' => 'slug', 'type' => 'text', 'label' => 'Slug' ),
			'template' => array( 'id' => 'template', 'type' => 'text', 'label' => 'Template' ),
			'order'    => array( 'id' => 'order', 'type' => 'number', 'label' => 'Order' ),
		);

		// Simulate a plugin hooking mc_edit_content_fields.
		mc_add_filter( 'mc_edit_content_fields', function ( array $fields, string $type ) {
			if ( 'page' === $type ) {
				$fields['seo_title'] = array(
					'id'          => 'seo_title',
					'type'        => 'text',
					'label'       => 'SEO Title',
					'description' => 'Custom title for search engines.',
				);
			}
			return $fields;
		}, 10, 2 );

		$filtered = mc_apply_filters( 'mc_edit_content_fields', $base_fields, 'page', array(), true );

		$this->assertArrayHasKey( 'seo_title', $filtered );
		$this->assertSame( 'SEO Title', $filtered['seo_title']['label'] );
	}
}
