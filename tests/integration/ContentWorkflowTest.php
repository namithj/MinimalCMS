<?php
/**
 * Integration tests for the content workflow (create → read → update → delete).
 *
 * @package MinimalCMS\Tests\Integration
 */

namespace MinimalCMS\Tests\Integration;

use MC_Error;
use PHPUnit\Framework\TestCase;

class ContentWorkflowTest extends TestCase {

	protected function setUp(): void {
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter,
				$mc_content_types;

		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];
		$mc_content_types  = [];
		$_SESSION          = [];

		// Clean content area.
		if ( is_dir( MC_CONTENT_DIR ) ) {
			mc_rmdir_recursive( MC_CONTENT_DIR );
		}
		mkdir( MC_CONTENT_DIR, 0755, true );

		// Register content type.
		mc_register_content_type(
			'page',
			[
				'label'    => 'Pages',
				'singular' => 'Page',
				'public'   => true,
			]
		);
	}

	// ── Full CRUD lifecycle ──────────────────────────────────────────────

	public function test_full_content_lifecycle(): void {
		// 1. Create.
		$result = mc_save_content(
			'page',
			'lifecycle',
			[
				'title'  => 'Lifecycle Page',
				'status' => 'publish',
			],
			'# Lifecycle Content'
		);
		$this->assertTrue( $result );

		// 2. Read.
		$content = mc_get_content( 'page', 'lifecycle' );
		$this->assertSame( 'Lifecycle Page', $content['title'] );
		$this->assertSame( '# Lifecycle Content', $content['body_raw'] );
		$this->assertSame( 'publish', $content['status'] );
		$this->assertTrue( mc_content_exists( 'page', 'lifecycle' ) );

		// 3. Update.
		$update = mc_save_content(
			'page',
			'lifecycle',
			[
				'title'  => 'Updated Page',
				'status' => 'draft',
			],
			'# Updated Content'
		);
		$this->assertTrue( $update );

		$updated = mc_get_content( 'page', 'lifecycle' );
		$this->assertSame( 'Updated Page', $updated['title'] );
		$this->assertSame( 'draft', $updated['status'] );

		// 4. Delete.
		$this->assertTrue( mc_delete_content( 'page', 'lifecycle' ) );
		$this->assertNull( mc_get_content( 'page', 'lifecycle' ) );
		$this->assertFalse( mc_content_exists( 'page', 'lifecycle' ) );
	}

	// ── Multiple content types ───────────────────────────────────────────

	public function test_multiple_content_types(): void {
		mc_register_content_type(
			'post',
			[
				'label'       => 'Posts',
				'has_archive' => true,
			]
		);

		mc_save_content(
			'page',
			'about',
			[
				'title'  => 'About',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'post',
			'hello',
			[
				'title'  => 'Hello',
				'status' => 'publish',
			]
		);

		$pages = mc_query_content( [ 'type' => 'page' ] );
		$posts = mc_query_content( [ 'type' => 'post' ] );

		$this->assertCount( 1, $pages );
		$this->assertCount( 1, $posts );
		$this->assertSame( 'About', $pages[0]['title'] );
		$this->assertSame( 'Hello', $posts[0]['title'] );
	}

	// ── Querying with sorting and pagination ─────────────────────────────

	public function test_query_pagination_and_sorting(): void {
		for ( $i = 1; $i <= 10; $i++ ) {
			mc_save_content(
				'page',
				"page-{$i}",
				[
					'title'   => "Page {$i}",
					'status'  => 'publish',
					'created' => sprintf( '2024-01-%02dT00:00:00+00:00', $i ),
				]
			);
		}

		// First page of 3.
		$page1 = mc_query_content(
			[
				'type'     => 'page',
				'order_by' => 'created',
				'order'    => 'ASC',
				'limit'    => 3,
				'offset'   => 0,
			]
		);
		$this->assertCount( 3, $page1 );
		$this->assertSame( 'Page 1', $page1[0]['title'] );
		$this->assertSame( 'Page 3', $page1[2]['title'] );

		// Second page.
		$page2 = mc_query_content(
			[
				'type'     => 'page',
				'order_by' => 'created',
				'order'    => 'ASC',
				'limit'    => 3,
				'offset'   => 3,
			]
		);
		$this->assertCount( 3, $page2 );
		$this->assertSame( 'Page 4', $page2[0]['title'] );
	}

	// ── Content with Markdown rendering ──────────────────────────────────

	public function test_content_markdown_body(): void {
		mc_save_content(
			'page',
			'md-page',
			[
				'title'  => 'Markdown Page',
				'status' => 'publish',
			],
			"# Hello\n\nThis is **bold** text."
		);

		$content = mc_get_content( 'page', 'md-page' );
		$html    = mc_parse_markdown( $content['body_raw'] );

		$this->assertStringContainsString( '<h1>Hello</h1>', $html );
		$this->assertStringContainsString( '<strong>bold</strong>', $html );
	}

	// ── Hooks fire during content operations ─────────────────────────────

	public function test_hooks_fire_on_save(): void {
		$fired = false;
		mc_add_action(
			'mc_content_saved',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		mc_save_content( 'page', 'hook-test', [ 'title' => 'Hook' ] );
		$this->assertTrue( $fired );
	}

	public function test_hooks_fire_on_delete(): void {
		mc_save_content( 'page', 'del-hook', [ 'title' => 'Del' ] );

		$fired = false;
		mc_add_action(
			'mc_content_deleted',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		mc_delete_content( 'page', 'del-hook' );
		$this->assertTrue( $fired );
	}

	public function test_get_content_filter(): void {
		mc_save_content( 'page', 'filt', [ 'title' => 'Original' ] );

		mc_add_filter(
			'mc_get_content',
			function ( $content ) {
				$content['title'] = 'Filtered';
				return $content;
			}
		);

		$content = mc_get_content( 'page', 'filt' );
		$this->assertSame( 'Filtered', $content['title'] );
	}

	// ── Content count integration ────────────────────────────────────────

	public function test_count_after_create_and_delete(): void {
		mc_save_content(
			'page',
			'cnt1',
			[
				'title'  => 'A',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'page',
			'cnt2',
			[
				'title'  => 'B',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'page',
			'cnt3',
			[
				'title'  => 'C',
				'status' => 'draft',
			]
		);

		$this->assertSame( 3, mc_count_content( 'page' ) );
		$this->assertSame( 2, mc_count_content( 'page', 'publish' ) );

		mc_delete_content( 'page', 'cnt1' );
		$this->assertSame( 2, mc_count_content( 'page' ) );
		$this->assertSame( 1, mc_count_content( 'page', 'publish' ) );
	}
}
