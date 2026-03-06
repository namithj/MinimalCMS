<?php
/**
 * Unit tests for the Fields API (mc-includes/fields.php).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FieldsTest extends TestCase {

	protected function setUp(): void {

		parent::setUp();
		// Reset the field type registry before each test.
		$GLOBALS['mc_field_types'] = array();
	}

	/*
	 * ── Registration ─────────────────────────────────────────────────────
	 */

	public function test_register_field_type_success(): void {

		$result = mc_register_field_type( 'my_type', array(
			'render_admin' => function () {},
		) );

		$this->assertTrue( $result );
		$this->assertNotNull( mc_get_field_type( 'my_type' ) );
	}

	public function test_register_field_type_empty_slug_returns_error(): void {

		$result = mc_register_field_type( '', array(
			'render_admin' => function () {},
		) );

		$this->assertInstanceOf( \MC_Error::class, $result );
	}

	public function test_register_field_type_missing_render_returns_error(): void {

		$result = mc_register_field_type( 'bad', array() );

		$this->assertInstanceOf( \MC_Error::class, $result );
	}

	public function test_get_field_type_returns_null_for_unknown(): void {

		$this->assertNull( mc_get_field_type( 'nonexistent' ) );
	}

	public function test_get_field_types_returns_all(): void {

		mc_register_field_type( 'a', array( 'render_admin' => function () {} ) );
		mc_register_field_type( 'b', array( 'render_admin' => function () {} ) );

		$all = mc_get_field_types();

		$this->assertArrayHasKey( 'a', $all );
		$this->assertArrayHasKey( 'b', $all );
	}

	public function test_register_core_field_types(): void {

		mc_register_core_field_types();

		$expected = array( 'text', 'textarea', 'number', 'url', 'checkbox', 'select' );

		foreach ( $expected as $type ) {
			$this->assertNotNull( mc_get_field_type( $type ), "Core type '{$type}' should be registered." );
		}
	}

	/*
	 * ── Text field sanitization ──────────────────────────────────────────
	 */

	public function test_sanitize_field_text_strips_tags(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'text', '<b>Bold</b> text' );
		$this->assertSame( 'Bold text', $result );
	}

	/*
	 * ── Textarea field sanitization ──────────────────────────────────────
	 */

	public function test_sanitize_field_textarea_preserves_newlines(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'textarea', "Line 1\nLine 2" );
		$this->assertSame( "Line 1\nLine 2", $result );
	}

	public function test_sanitize_field_textarea_strips_tags(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'textarea', "<script>xss</script>\nHello" );
		$this->assertSame( "xss\nHello", $result );
	}

	/*
	 * ── Number field ─────────────────────────────────────────────────────
	 */

	public function test_sanitize_field_number_returns_int(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'number', '42' );
		$this->assertSame( 42, $result );
	}

	public function test_sanitize_field_number_empty_returns_default(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'number', '', array( 'default' => 5 ) );
		$this->assertSame( 5, $result );
	}

	public function test_validate_field_number_min_max(): void {

		mc_register_core_field_types();

		$field = array(
			'label'   => 'Count',
			'options' => array( 'min' => 1, 'max' => 10 ),
		);

		$this->assertTrue( mc_validate_field( 'number', 5, $field ) );
		$this->assertIsString( mc_validate_field( 'number', 0, $field ) );
		$this->assertIsString( mc_validate_field( 'number', 11, $field ) );
	}

	/*
	 * ── URL field ────────────────────────────────────────────────────────
	 */

	public function test_sanitize_field_url_valid(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'url', 'https://example.com/path' );
		$this->assertSame( 'https://example.com/path', $result );
	}

	public function test_sanitize_field_url_empty(): void {

		mc_register_core_field_types();

		$result = mc_sanitize_field( 'url', '' );
		$this->assertSame( '', $result );
	}

	public function test_validate_field_url_invalid(): void {

		mc_register_core_field_types();

		$result = mc_validate_field( 'url', 'not-a-url' );
		$this->assertIsString( $result );
	}

	public function test_validate_field_url_valid(): void {

		mc_register_core_field_types();

		$this->assertTrue( mc_validate_field( 'url', 'https://example.com' ) );
	}

	/*
	 * ── Checkbox field ───────────────────────────────────────────────────
	 */

	public function test_sanitize_field_checkbox_truthy(): void {

		mc_register_core_field_types();

		$this->assertTrue( mc_sanitize_field( 'checkbox', '1' ) );
		$this->assertTrue( mc_sanitize_field( 'checkbox', 'on' ) );
	}

	public function test_sanitize_field_checkbox_falsy(): void {

		mc_register_core_field_types();

		$this->assertFalse( mc_sanitize_field( 'checkbox', '0' ) );
		$this->assertFalse( mc_sanitize_field( 'checkbox', '' ) );
	}

	/*
	 * ── Select field ─────────────────────────────────────────────────────
	 */

	public function test_sanitize_field_select_valid_choice(): void {

		mc_register_core_field_types();

		$field = array(
			'options' => array( 'choices' => array( 'a' => 'Alpha', 'b' => 'Beta' ) ),
			'default' => 'a',
		);

		$this->assertSame( 'b', mc_sanitize_field( 'select', 'b', $field ) );
	}

	public function test_sanitize_field_select_invalid_choice_returns_default(): void {

		mc_register_core_field_types();

		$field = array(
			'options' => array( 'choices' => array( 'a' => 'Alpha', 'b' => 'Beta' ) ),
			'default' => 'a',
		);

		$this->assertSame( 'a', mc_sanitize_field( 'select', 'INVALID', $field ) );
	}

	public function test_validate_field_select_invalid(): void {

		mc_register_core_field_types();

		$field = array(
			'label'   => 'Color',
			'options' => array( 'choices' => array( 'red' => 'Red' ) ),
		);

		$this->assertIsString( mc_validate_field( 'select', 'blue', $field ) );
	}

	/*
	 * ── Required validation ──────────────────────────────────────────────
	 */

	public function test_validate_required_field_empty_fails(): void {

		mc_register_core_field_types();

		$field = array( 'required' => true, 'label' => 'Name', 'id' => 'name' );

		$this->assertIsString( mc_validate_field( 'text', '', $field ) );
	}

	public function test_validate_required_field_present_passes(): void {

		mc_register_core_field_types();

		$field = array( 'required' => true, 'label' => 'Name', 'id' => 'name' );

		$this->assertTrue( mc_validate_field( 'text', 'John', $field ) );
	}

	/*
	 * ── mc_process_fields ────────────────────────────────────────────────
	 */

	public function test_process_fields_batch(): void {

		mc_register_core_field_types();

		$fields = array(
			'name'  => array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
			'count' => array( 'type' => 'number', 'label' => 'Count', 'options' => array( 'min' => 1 ) ),
		);

		$raw = array( 'name' => 'Test', 'count' => '5' );

		$result = mc_process_fields( $fields, $raw );

		$this->assertSame( 'Test', $result['values']['name'] );
		$this->assertSame( 5, $result['values']['count'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_process_fields_catches_errors(): void {

		mc_register_core_field_types();

		$fields = array(
			'name' => array( 'type' => 'text', 'label' => 'Name', 'required' => true ),
		);

		$raw = array( 'name' => '' );

		$result = mc_process_fields( $fields, $raw );

		$this->assertArrayHasKey( 'name', $result['errors'] );
	}

	/*
	 * ── Rendering ────────────────────────────────────────────────────────
	 */

	public function test_render_field_text_outputs_input(): void {

		mc_register_core_field_types();

		ob_start();
		mc_render_field(
			array( 'id' => 'test', 'type' => 'text', 'label' => 'Test Label' ),
			'hello'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'name="test"', $html );
		$this->assertStringContainsString( 'value="hello"', $html );
		$this->assertStringContainsString( 'Test Label', $html );
	}

	public function test_render_field_select_outputs_options(): void {

		mc_register_core_field_types();

		ob_start();
		mc_render_field(
			array(
				'id'      => 'color',
				'type'    => 'select',
				'label'   => 'Color',
				'options' => array( 'choices' => array( 'r' => 'Red', 'g' => 'Green' ) ),
			),
			'g'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'selected', $html );
		$this->assertStringContainsString( 'Green', $html );
	}

	public function test_render_field_checkbox_checked(): void {

		mc_register_core_field_types();

		ob_start();
		mc_render_field(
			array( 'id' => 'debug', 'type' => 'checkbox', 'label' => 'Debug' ),
			true
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'checked', $html );
	}

	public function test_render_field_shows_error(): void {

		mc_register_core_field_types();

		ob_start();
		mc_render_field(
			array( 'id' => 'test', 'type' => 'text', 'label' => 'Test' ),
			'',
			'This field is invalid.'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'field-has-error', $html );
		$this->assertStringContainsString( 'This field is invalid.', $html );
	}

	public function test_render_field_shows_description(): void {

		mc_register_core_field_types();

		ob_start();
		mc_render_field(
			array( 'id' => 'test', 'type' => 'text', 'label' => 'Test', 'description' => 'Help text here.' ),
			''
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Help text here.', $html );
	}

	/*
	 * ── Asset collection ─────────────────────────────────────────────────
	 */

	public function test_get_field_type_assets_collects_urls(): void {

		mc_register_field_type( 'fancy', array(
			'render_admin' => function () {},
			'admin_assets' => array(
				'css' => array( '/css/fancy.css' ),
				'js'  => array( '/js/fancy.js' ),
			),
		) );

		$assets = mc_get_field_type_assets( array( 'fancy' ) );

		$this->assertContains( '/css/fancy.css', $assets['css'] );
		$this->assertContains( '/js/fancy.js', $assets['js'] );
	}

	/*
	 * ── Custom field type ────────────────────────────────────────────────
	 */

	public function test_custom_field_type_sanitize_and_validate(): void {

		mc_register_field_type( 'email', array(
			'render_admin' => function () {},
			'sanitize'     => function ( $value ) {
				return mc_sanitize_email( $value );
			},
			'validate'     => function ( $value ) {
				if ( '' !== $value && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
					return 'Invalid email address.';
				}
				return true;
			},
		) );

		$this->assertSame( 'test@example.com', mc_sanitize_field( 'email', 'test@example.com' ) );
		$this->assertTrue( mc_validate_field( 'email', 'test@example.com' ) );
		$this->assertIsString( mc_validate_field( 'email', 'notanemail' ) );
	}
}
