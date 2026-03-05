<?php
/**
 * Unit tests for the Markdown parser wrapper.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MarkdownTest extends TestCase {

	protected function setUp(): void {
		// Reset hooks so filters don't leak between tests.
		global $mc_filters, $mc_filters_run, $mc_current_filter;
		$mc_filters        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];
	}

	public function test_parse_heading(): void {
		$html = mc_parse_markdown( '# Hello' );
		$this->assertSame( '<h1>Hello</h1>', $html );
	}

	public function test_parse_paragraph(): void {
		$html = mc_parse_markdown( 'A paragraph.' );
		$this->assertSame( '<p>A paragraph.</p>', $html );
	}

	public function test_parse_bold(): void {
		$html = mc_parse_markdown( '**bold**' );
		$this->assertStringContainsString( '<strong>bold</strong>', $html );
	}

	public function test_parse_italic(): void {
		$html = mc_parse_markdown( '*italic*' );
		$this->assertStringContainsString( '<em>italic</em>', $html );
	}

	public function test_parse_link(): void {
		$html = mc_parse_markdown( '[Link](https://example.com)' );
		$this->assertStringContainsString( '<a href="https://example.com">Link</a>', $html );
	}

	public function test_parse_code_block(): void {
		$html = mc_parse_markdown( "```\ncode\n```" );
		$this->assertStringContainsString( '<code>', $html );
	}

	public function test_parse_inline_code(): void {
		$html = mc_parse_markdown( 'Use `code` here' );
		$this->assertStringContainsString( '<code>code</code>', $html );
	}

	public function test_parse_unordered_list(): void {
		$html = mc_parse_markdown( "- Item 1\n- Item 2" );
		$this->assertStringContainsString( '<ul>', $html );
		$this->assertStringContainsString( '<li>Item 1</li>', $html );
	}

	public function test_parse_ordered_list(): void {
		$html = mc_parse_markdown( "1. First\n2. Second" );
		$this->assertStringContainsString( '<ol>', $html );
	}

	public function test_parse_blockquote(): void {
		$html = mc_parse_markdown( '> Quote' );
		$this->assertStringContainsString( '<blockquote>', $html );
	}

	public function test_parse_empty_string(): void {
		$html = mc_parse_markdown( '' );
		$this->assertSame( '', $html );
	}

	public function test_filter_modifies_output(): void {
		mc_add_filter(
			'mc_parse_markdown',
			function ( $html ) {
				return $html . '<!-- filtered -->';
			}
		);

		$html = mc_parse_markdown( 'Test' );
		$this->assertStringContainsString( '<!-- filtered -->', $html );
	}

	public function test_parse_horizontal_rule(): void {
		$html = mc_parse_markdown( '---' );
		$this->assertStringContainsString( '<hr', $html );
	}

	public function test_parse_image(): void {
		$html = mc_parse_markdown( '![Alt](img.jpg)' );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( 'alt="Alt"', $html );
	}
}
