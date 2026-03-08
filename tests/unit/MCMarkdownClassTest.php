<?php
/**
 * Tests for MC_Markdown class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Hooks;
use MC_Markdown;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Markdown
 */
class MCMarkdownClassTest extends TestCase {

	private MC_Markdown $markdown;
	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks    = new MC_Hooks();
		$this->markdown = new MC_Markdown($this->hooks);
	}

	public function test_parse_heading(): void {

		$html = $this->markdown->parse('# Hello');
		$this->assertStringContainsString('<h1>', $html);
		$this->assertStringContainsString('Hello', $html);
	}

	public function test_parse_paragraph(): void {

		$html = $this->markdown->parse('Some text here');
		$this->assertStringContainsString('<p>', $html);
		$this->assertStringContainsString('Some text here', $html);
	}

	public function test_parse_bold(): void {

		$html = $this->markdown->parse('**bold text**');
		$this->assertStringContainsString('<strong>', $html);
	}

	public function test_parse_link(): void {

		$html = $this->markdown->parse('[example](https://example.com)');
		$this->assertStringContainsString('href="https://example.com"', $html);
	}

	public function test_parse_empty_string(): void {

		$html = $this->markdown->parse('');
		$this->assertSame('', $html);
	}

	public function test_pre_parse_filter(): void {

		$this->hooks->add_filter('mc_pre_parse_markdown', function ($text) {
			return str_replace('REPLACE', 'replaced', $text);
		});

		$html = $this->markdown->parse('REPLACE');
		$this->assertStringContainsString('replaced', $html);
	}

	public function test_post_parse_filter(): void {

		$this->hooks->add_filter('mc_parse_markdown', function ($html) {
			return $html . '<!-- filtered -->';
		});

		$html = $this->markdown->parse('test');
		$this->assertStringEndsWith('<!-- filtered -->', $html);
	}
}
