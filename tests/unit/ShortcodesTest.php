<?php

/**
 * Unit tests for the shortcode system.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ShortcodesTest extends TestCase
{
	protected function setUp(): void
	{
		global $mc_shortcodes;
		$mc_shortcodes = [];
	}

	// ── mc_add_shortcode / mc_shortcode_exists ───────────────────────────

	public function test_add_shortcode(): void
	{
		mc_add_shortcode(
			'test',
			function () {
				return '';
			}
		);
		$this->assertTrue(mc_shortcode_exists('test'));
	}

	public function test_shortcode_exists_false(): void
	{
		$this->assertFalse(mc_shortcode_exists('nope'));
	}

	// ── mc_remove_shortcode ──────────────────────────────────────────────

	public function test_remove_shortcode(): void
	{
		mc_add_shortcode(
			'removable',
			function () {
				return '';
			}
		);
		mc_remove_shortcode('removable');
		$this->assertFalse(mc_shortcode_exists('removable'));
	}

	// ── mc_do_shortcode ──────────────────────────────────────────────────

	public function test_do_shortcode_self_closing(): void
	{
		mc_add_shortcode(
			'hello',
			function ($attrs, $content, $tag) {
				return 'Hello!';
			}
		);

		$this->assertSame('Hello!', mc_do_shortcode('[hello]'));
	}

	public function test_do_shortcode_with_content(): void
	{
		mc_add_shortcode(
			'wrap',
			function ($attrs, $content) {
				return '<div>' . $content . '</div>';
			}
		);

		$this->assertSame('<div>inner</div>', mc_do_shortcode('[wrap]inner[/wrap]'));
	}

	public function test_do_shortcode_with_attrs(): void
	{
		mc_add_shortcode(
			'greet',
			function ($attrs) {
				return 'Hi ' . ( $attrs['name'] ?? 'anon' );
			}
		);

		$this->assertSame('Hi Alice', mc_do_shortcode('[greet name="Alice"]'));
	}

	public function test_do_shortcode_no_shortcodes_returns_content(): void
	{
		$input = 'Just plain text.';
		$this->assertSame($input, mc_do_shortcode($input));
	}

	public function test_do_shortcode_no_registered_returns_original(): void
	{
		// Even with brackets, if no shortcodes are registered it returns as-is.
		$input = '[unknown]stuff[/unknown]';
		$this->assertSame($input, mc_do_shortcode($input));
	}

	public function test_do_shortcode_multiple(): void
	{
		mc_add_shortcode(
			'a',
			function () {
				return 'A';
			}
		);
		mc_add_shortcode(
			'b',
			function () {
				return 'B';
			}
		);

		$this->assertSame('A and B', mc_do_shortcode('[a] and [b]'));
	}

	public function test_do_shortcode_passes_tag(): void
	{
		$received_tag = '';
		mc_add_shortcode(
			'tag_test',
			function ($attrs, $content, $tag) use (&$received_tag) {
				$received_tag = $tag;
				return '';
			}
		);

		mc_do_shortcode('[tag_test]');
		$this->assertSame('tag_test', $received_tag);
	}

	// ── mc_parse_shortcode_attrs ─────────────────────────────────────────

	public function test_parse_attrs_double_quotes(): void
	{
		$attrs = mc_parse_shortcode_attrs('name="Alice" age="30"');
		$this->assertSame(
			[
				'name' => 'Alice',
				'age'  => '30',
			],
			$attrs
		);
	}

	public function test_parse_attrs_single_quotes(): void
	{
		$attrs = mc_parse_shortcode_attrs("color='red'");
		$this->assertSame([ 'color' => 'red' ], $attrs);
	}

	public function test_parse_attrs_no_quotes(): void
	{
		$attrs = mc_parse_shortcode_attrs('size=large');
		$this->assertSame([ 'size' => 'large' ], $attrs);
	}

	public function test_parse_attrs_empty(): void
	{
		$this->assertSame([], mc_parse_shortcode_attrs(''));
	}

	public function test_parse_attrs_mixed(): void
	{
		$attrs = mc_parse_shortcode_attrs('a="1" b=\'2\' c=3');
		$this->assertSame(
			[
				'a' => '1',
				'b' => '2',
				'c' => '3',
			],
			$attrs
		);
	}
}
