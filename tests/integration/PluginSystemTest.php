<?php
/**
 * Integration tests for the plugin / hook system extensibility.
 *
 * @package MinimalCMS\Tests\Integration
 */

namespace MinimalCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;

class PluginSystemTest extends TestCase {

	protected function setUp(): void {
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter, $mc_shortcodes, $mc_content_types;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];
		$mc_shortcodes     = [];
		$mc_content_types  = [];

		// Content dir for content-related hook tests.
		if ( ! is_dir( MC_CONTENT_DIR ) ) {
			mkdir( MC_CONTENT_DIR, 0755, true );
		}

		mc_register_content_type(
			'post',
			[
				'label'       => 'Posts',
				'has_archive' => true,
			]
		);
	}

	protected function tearDown(): void {
		if ( is_dir( MC_CONTENT_DIR ) ) {
			mc_rmdir_recursive( MC_CONTENT_DIR );
		}
	}

	// ── Filter chaining ──────────────────────────────────────────────────

	public function test_filter_chain_modifies_value_sequentially(): void {
		mc_add_filter(
			'greet',
			function ( string $val ): string {
				return $val . ' World';
			}
		);
		mc_add_filter(
			'greet',
			function ( string $val ): string {
				return $val . '!';
			}
		);

		$this->assertSame( 'Hello World!', mc_apply_filters( 'greet', 'Hello' ) );
	}

	public function test_filter_priority_determines_order(): void {
		mc_add_filter(
			'order',
			function ( string $v ): string {
				return $v . 'B';
			},
			20
		);
		mc_add_filter(
			'order',
			function ( string $v ): string {
				return $v . 'A';
			},
			10
		);

		$this->assertSame( 'AB', mc_apply_filters( 'order', '' ) );
	}

	// ── Action firing with data ──────────────────────────────────────────

	public function test_action_receives_multiple_arguments(): void {
		$captured = [];
		mc_add_action(
			'multi_arg',
			function ( $a, $b, $c ) use ( &$captured ) {
				$captured = [ $a, $b, $c ];
			},
			10,
			3
		);

		mc_do_action( 'multi_arg', 'x', 'y', 'z' );
		$this->assertSame( [ 'x', 'y', 'z' ], $captured );
	}

	// ── Shortcodes within content body ───────────────────────────────────

	public function test_shortcode_processed_in_content_body(): void {
		mc_add_shortcode(
			'greeting',
			function ( $atts, $content, $tag ) {
				$name = $atts['name'] ?? 'Guest';
				return "<span>Hello, {$name}!</span>";
			}
		);

		$raw    = 'Welcome: [greeting name="Alice"]';
		$result = mc_do_shortcode( $raw );
		$this->assertStringContainsString( '<span>Hello, Alice!</span>', $result );
	}

	public function test_shortcode_with_content(): void {
		mc_add_shortcode(
			'wrap',
			function ( $atts, $content ) {
				return '<div>' . $content . '</div>';
			}
		);

		$result = mc_do_shortcode( '[wrap]inner text[/wrap]' );
		$this->assertSame( '<div>inner text</div>', $result );
	}

	// ── Hook-based content extension ─────────────────────────────────────

	public function test_content_save_hook_allows_side_effects(): void {
		$recorded = [];
		mc_add_action(
			'mc_content_saved',
			function ( $type, $slug ) use ( &$recorded ) {
				$recorded[] = "{$type}:{$slug}";
			},
			10,
			2
		);

		mc_save_content(
			'post',
			'alpha',
			[
				'title'  => 'Alpha',
				'status' => 'published',
			],
			'Body A'
		);
		mc_save_content(
			'post',
			'beta',
			[
				'title'  => 'Beta',
				'status' => 'published',
			],
			'Body B'
		);

		$this->assertSame( [ 'post:alpha', 'post:beta' ], $recorded );
	}

	public function test_content_filter_modifies_returned_content(): void {
		mc_save_content(
			'post',
			'filtered',
			[
				'title'  => 'Title',
				'status' => 'published',
			],
			'Body'
		);

		mc_add_filter(
			'mc_get_content',
			function ( $content ) {
				$content['meta']['custom'] = 'injected';
				return $content;
			}
		);

		$content = mc_get_content( 'post', 'filtered' );
		$this->assertSame( 'injected', $content['meta']['custom'] );
	}

	// ── Removing hooks ───────────────────────────────────────────────────

	public function test_remove_filter_stops_modification(): void {
		$fn = function ( string $v ): string {
			return $v . '_modified';
		};

		mc_add_filter( 'removeme', $fn );
		$this->assertSame( 'val_modified', mc_apply_filters( 'removeme', 'val' ) );

		mc_remove_filter( 'removeme', $fn );
		$this->assertSame( 'val', mc_apply_filters( 'removeme', 'val' ) );
	}

	// ── did_action tracking ──────────────────────────────────────────────

	public function test_did_action_counts_cumulative_fires(): void {
		$this->assertSame( 0, mc_did_action( 'ping' ) );

		mc_do_action( 'ping' );
		$this->assertSame( 1, mc_did_action( 'ping' ) );

		mc_do_action( 'ping' );
		mc_do_action( 'ping' );
		$this->assertSame( 3, mc_did_action( 'ping' ) );
	}

	// ── Cross-system: Markdown → Shortcode ───────────────────────────────

	public function test_markdown_then_shortcode_pipeline(): void {
		mc_add_shortcode(
			'highlight',
			function ( $atts, $content ) {
				return '<mark>' . $content . '</mark>';
			}
		);

		$markdown = "**Bold text**\n\n[highlight]important[/highlight]";
		$html     = mc_parse_markdown( $markdown );
		$final    = mc_do_shortcode( $html );

		$this->assertStringContainsString( '<strong>Bold text</strong>', $final );
		$this->assertStringContainsString( '<mark>important</mark>', $final );
	}

	// ── Filter introspection ─────────────────────────────────────────────

	public function test_has_filter_returns_priority(): void {
		$fn = function () {};
		mc_add_filter( 'introspect', $fn, 25 );

		$this->assertSame( 25, mc_has_filter( 'introspect', $fn ) );
	}

	public function test_doing_filter_during_apply(): void {
		$inside = null;
		mc_add_filter(
			'check_doing',
			function ( $v ) use ( &$inside ) {
				$inside = mc_doing_filter( 'check_doing' );
				return $v;
			}
		);

		mc_apply_filters( 'check_doing', 'x' );
		$this->assertTrue( $inside );
		$this->assertFalse( mc_doing_filter( 'check_doing' ) );
	}
}
