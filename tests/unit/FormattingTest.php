<?php
/**
 * Unit tests for formatting (escape and sanitise) functions.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormattingTest extends TestCase {

	// ── mc_esc_html ──────────────────────────────────────────────────────

	public function test_esc_html_basic(): void {
		$this->assertSame( '&lt;script&gt;alert(1)&lt;/script&gt;', mc_esc_html( '<script>alert(1)</script>' ) );
	}

	public function test_esc_html_quotes(): void {
		$this->assertSame( '&quot;quotes&quot; &amp; &#039;apos&#039;', mc_esc_html( '"quotes" & \'apos\'' ) );
	}

	public function test_esc_html_plain_text(): void {
		$this->assertSame( 'Hello World', mc_esc_html( 'Hello World' ) );
	}

	// ── mc_esc_attr ──────────────────────────────────────────────────────

	public function test_esc_attr_escapes_quotes(): void {
		$this->assertSame( '&quot;attr&quot;', mc_esc_attr( '"attr"' ) );
	}

	// ── mc_esc_url ───────────────────────────────────────────────────────

	public function test_esc_url_http(): void {
		$this->assertSame( 'https://example.com/path?q=1&amp;b=2', mc_esc_url( 'https://example.com/path?q=1&b=2' ) );
	}

	public function test_esc_url_relative(): void {
		$this->assertSame( '/path/to/page', mc_esc_url( '/path/to/page' ) );
	}

	public function test_esc_url_hash(): void {
		$this->assertSame( '#section', mc_esc_url( '#section' ) );
	}

	public function test_esc_url_query(): void {
		$this->assertSame( '?foo=bar', mc_esc_url( '?foo=bar' ) );
	}

	public function test_esc_url_mailto(): void {
		$this->assertSame( 'mailto:a@b.com', mc_esc_url( 'mailto:a@b.com' ) );
	}

	public function test_esc_url_tel(): void {
		$this->assertSame( 'tel:+1234567890', mc_esc_url( 'tel:+1234567890' ) );
	}

	public function test_esc_url_rejects_javascript(): void {
		$this->assertSame( '', mc_esc_url( 'javascript:alert(1)' ) );
	}

	public function test_esc_url_rejects_data(): void {
		$this->assertSame( '', mc_esc_url( 'data:text/html,<h1>Bad</h1>' ) );
	}

	public function test_esc_url_empty(): void {
		$this->assertSame( '', mc_esc_url( '' ) );
	}

	public function test_esc_url_trims_whitespace(): void {
		$this->assertSame( 'https://example.com', mc_esc_url( '  https://example.com  ' ) );
	}

	// ── mc_esc_js ────────────────────────────────────────────────────────

	public function test_esc_js_basic(): void {
		$result = mc_esc_js( "it's a \"test\"" );
		$this->assertStringNotContainsString( '<', $result );
		$this->assertStringNotContainsString( '>', $result );
	}

	public function test_esc_js_plain(): void {
		$this->assertSame( 'hello', mc_esc_js( 'hello' ) );
	}

	// ── mc_esc_textarea ──────────────────────────────────────────────────

	public function test_esc_textarea(): void {
		$this->assertSame( '&lt;b&gt;bold&lt;/b&gt;', mc_esc_textarea( '<b>bold</b>' ) );
	}

	// ── mc_sanitize_text ─────────────────────────────────────────────────

	public function test_sanitize_text_strips_tags(): void {
		$this->assertSame( 'Hello World', mc_sanitize_text( '<b>Hello</b> World' ) );
	}

	public function test_sanitize_text_normalises_whitespace(): void {
		$this->assertSame( 'a b c', mc_sanitize_text( "a  \t b  \n c" ) );
	}

	public function test_sanitize_text_trims(): void {
		$this->assertSame( 'trim', mc_sanitize_text( '  trim  ' ) );
	}

	// ── mc_sanitize_slug ─────────────────────────────────────────────────

	public function test_sanitize_slug_lowercase(): void {
		$this->assertSame( 'hello-world', mc_sanitize_slug( 'Hello World' ) );
	}

	public function test_sanitize_slug_removes_special_chars(): void {
		$this->assertSame( 'test-123', mc_sanitize_slug( 'test_@#$_123' ) );
	}

	public function test_sanitize_slug_trims_hyphens(): void {
		$this->assertSame( 'slug', mc_sanitize_slug( '--slug--' ) );
	}

	public function test_sanitize_slug_collapses_hyphens(): void {
		$this->assertSame( 'a-b', mc_sanitize_slug( 'a---b' ) );
	}

	// ── mc_sanitize_filename ─────────────────────────────────────────────

	public function test_sanitize_filename_basic(): void {
		$this->assertSame( 'file.txt', mc_sanitize_filename( 'file.txt' ) );
	}

	public function test_sanitize_filename_removes_traversal(): void {
		$this->assertSame( 'etc-passwd', mc_sanitize_filename( '../etc/passwd' ) );
	}

	public function test_sanitize_filename_removes_bad_chars(): void {
		$this->assertSame( 'my-file--2-.jpg', mc_sanitize_filename( 'my file (2).jpg' ) );
	}

	// ── mc_sanitize_email ────────────────────────────────────────────────

	public function test_sanitize_email_valid(): void {
		$this->assertSame( 'user@example.com', mc_sanitize_email( 'user@example.com' ) );
	}

	public function test_sanitize_email_trims(): void {
		$this->assertSame( 'a@b.com', mc_sanitize_email( '  a@b.com  ' ) );
	}

	public function test_sanitize_email_invalid(): void {
		$this->assertSame( '', mc_sanitize_email( 'not-an-email' ) );
	}

	public function test_sanitize_email_empty(): void {
		$this->assertSame( '', mc_sanitize_email( '' ) );
	}

	// ── mc_sanitize_html ─────────────────────────────────────────────────

	public function test_sanitize_html_allows_safe_tags(): void {
		$input = '<p>Hello <strong>world</strong></p>';
		$this->assertSame( $input, mc_sanitize_html( $input ) );
	}

	public function test_sanitize_html_strips_script(): void {
		$this->assertSame( 'alert(1)', mc_sanitize_html( '<script>alert(1)</script>' ) );
	}

	public function test_sanitize_html_strips_div(): void {
		$this->assertSame( 'content', mc_sanitize_html( '<div>content</div>' ) );
	}

	// ── mc_slugify ───────────────────────────────────────────────────────

	public function test_slugify_delegates_to_sanitize_slug(): void {
		$this->assertSame( mc_sanitize_slug( 'Test Title' ), mc_slugify( 'Test Title' ) );
	}

	// ── mc_truncate ──────────────────────────────────────────────────────

	public function test_truncate_short_text(): void {
		$this->assertSame( 'Short', mc_truncate( 'Short', 150 ) );
	}

	public function test_truncate_long_text(): void {
		$long   = str_repeat( 'word ', 50 );
		$result = mc_truncate( $long, 20 );
		$this->assertLessThanOrEqual( 30, mb_strlen( $result ) ); // word boundary + hellip
		$this->assertStringEndsWith( '&hellip;', $result );
	}

	public function test_truncate_custom_suffix(): void {
		$long   = str_repeat( 'abc ', 50 );
		$result = mc_truncate( $long, 10, '...' );
		$this->assertStringEndsWith( '...', $result );
	}

	public function test_truncate_strips_tags_first(): void {
		$result = mc_truncate( '<b>Hello</b> World is here', 10 );
		$this->assertStringNotContainsString( '<b>', $result );
	}

	// ── mc_input ─────────────────────────────────────────────────────────

	public function test_input_get(): void {
		$_GET['test_key'] = ' value ';
		$this->assertSame( ' value ', mc_input( 'test_key', 'GET' ) );
		unset( $_GET['test_key'] );
	}

	public function test_input_post(): void {
		$_POST['foo'] = 'bar';
		$this->assertSame( 'bar', mc_input( 'foo', 'POST' ) );
		unset( $_POST['foo'] );
	}

	public function test_input_missing_key(): void {
		$this->assertNull( mc_input( 'nonexistent_key_xyz', 'GET' ) );
	}

	public function test_input_with_sanitize(): void {
		$_REQUEST['dirty'] = '  spaced  ';
		$this->assertSame( 'spaced', mc_input( 'dirty', 'REQUEST', 'trim' ) );
		unset( $_REQUEST['dirty'] );
	}

	public function test_input_default_method_is_request(): void {
		$_REQUEST['rkey'] = 'rval';
		$this->assertSame( 'rval', mc_input( 'rkey' ) );
		unset( $_REQUEST['rkey'] );
	}
}
