<?php
/**
 * Tests for MC_Shortcodes class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Shortcodes;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Shortcodes
 */
class MCShortcodesClassTest extends TestCase {

	private MC_Shortcodes $shortcodes;

	protected function setUp(): void {

		$this->shortcodes = new MC_Shortcodes();
	}

	public function test_add_and_exists(): void {

		$this->shortcodes->add('test', function () {
			return 'output';
		});

		$this->assertTrue($this->shortcodes->exists('test'));
	}

	public function test_exists_returns_false_for_unknown(): void {

		$this->assertFalse($this->shortcodes->exists('nope'));
	}

	public function test_remove(): void {

		$this->shortcodes->add('removeme', function () {
			return '';
		});

		$this->shortcodes->remove('removeme');
		$this->assertFalse($this->shortcodes->exists('removeme'));
	}

	public function test_do_shortcode_simple(): void {

		$this->shortcodes->add('hello', function () {
			return 'Hi!';
		});

		$result = $this->shortcodes->do_shortcode('[hello]');
		$this->assertSame('Hi!', $result);
	}

	public function test_do_shortcode_with_attributes(): void {

		$this->shortcodes->add('greet', function ($atts) {
			return 'Hello ' . ($atts['name'] ?? 'World');
		});

		$result = $this->shortcodes->do_shortcode('[greet name="Alice"]');
		$this->assertSame('Hello Alice', $result);
	}

	public function test_do_shortcode_with_content(): void {

		$this->shortcodes->add('wrap', function ($atts, $content) {
			return '<div>' . $content . '</div>';
		});

		$result = $this->shortcodes->do_shortcode('[wrap]inner[/wrap]');
		$this->assertSame('<div>inner</div>', $result);
	}

	public function test_do_shortcode_no_match(): void {

		$text = 'No shortcodes here.';
		$this->assertSame($text, $this->shortcodes->do_shortcode($text));
	}

	public function test_do_shortcode_multiple(): void {

		$this->shortcodes->add('a', function () {
			return 'A';
		});
		$this->shortcodes->add('b', function () {
			return 'B';
		});

		$result = $this->shortcodes->do_shortcode('[a] and [b]');
		$this->assertSame('A and B', $result);
	}

	public function test_parse_attrs(): void {

		$this->shortcodes->add('attrs', function ($atts) {
			return ($atts['x'] ?? '') . '-' . ($atts['y'] ?? '');
		});

		$result = $this->shortcodes->do_shortcode('[attrs x="1" y="2"]');
		$this->assertSame('1-2', $result);
	}
}
