<?php
/**
 * Tests for MC_Formatter class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Formatter;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Formatter
 */
class MCFormatterClassTest extends TestCase {

	private MC_Formatter $fmt;
	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks = new MC_Hooks();
		$this->fmt   = new MC_Formatter($this->hooks);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Escaping
	 * -------------------------------------------------------------------------
	 */

	public function test_esc_html(): void {

		$this->assertSame('&lt;b&gt;bold&lt;/b&gt;', $this->fmt->esc_html('<b>bold</b>'));
	}

	public function test_esc_html_with_quotes(): void {

		$this->assertSame('a &quot;test&quot; &amp; &#039;value&#039;', $this->fmt->esc_html('a "test" & \'value\''));
	}

	public function test_esc_attr(): void {

		$this->assertSame('&lt;script&gt;', $this->fmt->esc_attr('<script>'));
	}

	public function test_esc_url_http(): void {

		$this->assertSame('https://example.com', $this->fmt->esc_url('https://example.com'));
	}

	public function test_esc_url_relative(): void {

		$this->assertSame('/path', $this->fmt->esc_url('/path'));
		$this->assertSame('#anchor', $this->fmt->esc_url('#anchor'));
		$this->assertSame('?q=1', $this->fmt->esc_url('?q=1'));
	}

	public function test_esc_url_rejects_javascript(): void {

		$this->assertSame('', $this->fmt->esc_url('javascript:alert(1)'));
	}

	public function test_esc_url_empty(): void {

		$this->assertSame('', $this->fmt->esc_url(''));
		$this->assertSame('', $this->fmt->esc_url('  '));
	}

	public function test_esc_url_protocols_filter(): void {

		// Add ftp to allowed protocols.
		$this->hooks->add_filter('mc_esc_url_protocols', function ($protocols) {
			$protocols[] = 'ftp';
			return $protocols;
		});

		$this->assertSame('ftp://files.example.com', $this->fmt->esc_url('ftp://files.example.com'));
	}

	public function test_esc_js_encodes_special_chars(): void {

		$result = $this->fmt->esc_js("line1\nline2");
		$this->assertStringNotContainsString("\n", $result);
		$this->assertStringContainsString('\\n', $result);
	}

	public function test_esc_js_encodes_angle_brackets(): void {

		$result = $this->fmt->esc_js('<script>');
		$this->assertStringNotContainsString('<', $result);
	}

	public function test_esc_textarea(): void {

		$this->assertSame('&lt;textarea&gt;', $this->fmt->esc_textarea('<textarea>'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Sanitisation
	 * -------------------------------------------------------------------------
	 */

	public function test_sanitize_text_strips_tags(): void {

		$this->assertSame('hello world', $this->fmt->sanitize_text('<b>hello</b> world'));
	}

	public function test_sanitize_text_normalises_whitespace(): void {

		$this->assertSame('a b c', $this->fmt->sanitize_text("a  \n  b\t  c"));
	}

	public function test_sanitize_slug(): void {

		$this->assertSame('hello-world', $this->fmt->sanitize_slug('Hello World!'));
	}

	public function test_sanitize_slug_collapses_hyphens(): void {

		$this->assertSame('a-b', $this->fmt->sanitize_slug('a---b'));
	}

	public function test_sanitize_slug_trims_hyphens(): void {

		$this->assertSame('slug', $this->fmt->sanitize_slug('-slug-'));
	}

	public function test_sanitize_filename(): void {

		$this->assertSame('my-file.txt', $this->fmt->sanitize_filename('my file.txt'));
	}

	public function test_sanitize_filename_blocks_traversal(): void {

		$this->assertSame('etc-passwd', $this->fmt->sanitize_filename('../etc/passwd'));
	}

	public function test_sanitize_email_valid(): void {

		$this->assertSame('user@example.com', $this->fmt->sanitize_email('user@example.com'));
	}

	public function test_sanitize_email_invalid(): void {

		$this->assertSame('', $this->fmt->sanitize_email('not-an-email'));
	}

	public function test_sanitize_html_strips_disallowed_tags(): void {

		$result = $this->fmt->sanitize_html('<script>alert(1)</script><p>ok</p>');
		$this->assertStringNotContainsString('<script>', $result);
		$this->assertStringContainsString('<p>ok</p>', $result);
	}

	public function test_sanitize_html_strips_event_handlers(): void {

		$result = $this->fmt->sanitize_html('<p onclick="alert(1)">text</p>');
		$this->assertStringNotContainsString('onclick', $result);
		$this->assertStringContainsString('<p', $result);
	}

	public function test_sanitize_html_strips_javascript_href(): void {

		$result = $this->fmt->sanitize_html('<a href="javascript:alert(1)">link</a>');
		$this->assertStringNotContainsString('javascript:', $result);
	}

	public function test_sanitize_html_allowed_tags_filter(): void {

		$this->hooks->add_filter('mc_sanitize_html_allowed_tags', function () {
			return '<b>';
		});

		$result = $this->fmt->sanitize_html('<b>bold</b><i>italic</i>');
		$this->assertStringContainsString('<b>bold</b>', $result);
		$this->assertStringNotContainsString('<i>', $result);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Helpers
	 * -------------------------------------------------------------------------
	 */

	public function test_slugify(): void {

		$this->assertSame('hello-world', $this->fmt->slugify('Hello World'));
	}

	public function test_truncate_short_text_unchanged(): void {

		$this->assertSame('short', $this->fmt->truncate('short', 100));
	}

	public function test_truncate_long_text(): void {

		$long = 'This is a very long sentence that should be truncated at a word boundary';
		$result = $this->fmt->truncate($long, 30);

		$this->assertLessThanOrEqual(40, mb_strlen($result));
		$this->assertStringEndsWith('&hellip;', $result);
	}

	public function test_truncate_respects_word_boundary(): void {

		$result = $this->fmt->truncate('Hello World Foo', 12);
		// Should cut at "Hello World" (11 chars) + suffix, not in the middle of "Foo".
		$this->assertStringStartsWith('Hello World', $result);
	}
}
