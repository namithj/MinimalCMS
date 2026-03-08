<?php
/**
 * Tests for MC_Field_Registry class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Field_Registry;
use MC_Formatter;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Field_Registry
 */
class MCFieldRegistryClassTest extends TestCase {

	private MC_Field_Registry $fields;
	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks  = new MC_Hooks();
		$formatter    = new MC_Formatter($this->hooks);
		$this->fields = new MC_Field_Registry($this->hooks, $formatter);
		$this->fields->register_core_types();
	}

	public function test_core_types_registered(): void {

		$types = $this->fields->get_types();
		$this->assertArrayHasKey('text', $types);
		$this->assertArrayHasKey('textarea', $types);
		$this->assertArrayHasKey('number', $types);
		$this->assertArrayHasKey('url', $types);
		$this->assertArrayHasKey('checkbox', $types);
		$this->assertArrayHasKey('select', $types);
	}

	public function test_get_type(): void {

		$type = $this->fields->get_type('text');
		$this->assertIsArray($type);
		$this->assertArrayHasKey('render_admin', $type);
		$this->assertArrayHasKey('sanitize', $type);
	}

	public function test_get_type_unknown(): void {

		$this->assertNull($this->fields->get_type('nonexistent'));
	}

	public function test_register_custom_type(): void {

		$result = $this->fields->register_type('color', array(
			'render_admin' => function (array $field, mixed $value): void { echo '<input type="color">'; },
			'sanitize'     => function ($val) { return $val; },
		));

		$this->assertTrue($result);
		$this->assertNotNull($this->fields->get_type('color'));
	}

	public function test_sanitize_text(): void {

		$result = $this->fields->sanitize('text', '<script>alert("xss")</script>');
		$this->assertStringNotContainsString('<script>', $result);
	}

	public function test_sanitize_number(): void {

		$result = $this->fields->sanitize('number', 'abc');
		$this->assertSame(0, $result);
	}

	public function test_sanitize_checkbox(): void {

		$this->assertSame('1', $this->fields->sanitize('checkbox', '1'));
		$this->assertSame('', $this->fields->sanitize('checkbox', ''));
	}

	public function test_render_text_field(): void {

		ob_start();
		$this->fields->render(
			array('id' => 'test_field', 'type' => 'text', 'label' => 'Test'),
			'hello'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString('<input', $html);
		$this->assertStringContainsString('test_field', $html);
	}

	public function test_render_textarea_field(): void {

		ob_start();
		$this->fields->render(
			array('id' => 'desc', 'type' => 'textarea', 'label' => 'Description'),
			'some text'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString('<textarea', $html);
		$this->assertStringContainsString('some text', $html);
	}

	public function test_render_select_field(): void {

		ob_start();
		$this->fields->render(
			array(
				'id'      => 'choice',
				'type'    => 'select',
				'label'   => 'Choice',
				'options' => array('choices' => array('a' => 'Option A', 'b' => 'Option B')),
			),
			'b'
		);
		$html = ob_get_clean();

		$this->assertStringContainsString('<select', $html);
		$this->assertStringContainsString('selected', $html);
	}

	public function test_process_fields(): void {

		$result = $this->fields->process(
			array('title' => array('type' => 'text', 'label' => 'Title')),
			array('title' => '<b>Bold</b>')
		);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('values', $result);
		$this->assertArrayHasKey('errors', $result);
		$this->assertArrayHasKey('title', $result['values']);
	}
}
