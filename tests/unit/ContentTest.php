<?php
/**
 * Unit tests for the content system (types, CRUD, queries).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Error;
use PHPUnit\Framework\TestCase;

class ContentTest extends TestCase {

	protected function setUp(): void {
		// Reset hook state.
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];

		// Reset content types and session state.
		global $mc_content_types;
		$mc_content_types = [];
		$_SESSION         = [];

		// Clean content directory.
		$content_dir = MC_CONTENT_DIR;
		if ( is_dir( $content_dir ) ) {
			mc_rmdir_recursive( $content_dir );
		}
		mkdir( $content_dir, 0755, true );

		// Register a test content type.
		mc_register_content_type(
			'page',
			[
				'label'    => 'Pages',
				'singular' => 'Page',
			]
		);
	}

	// ── mc_register_content_type ─────────────────────────────────────────

	public function test_register_content_type(): void {
		mc_register_content_type( 'post', [ 'label' => 'Posts' ] );
		$type = mc_get_content_type( 'post' );

		$this->assertNotNull( $type );
		$this->assertSame( 'Posts', $type['label'] );
		$this->assertTrue( $type['public'] );
	}

	public function test_register_content_type_creates_directory(): void {
		mc_register_content_type( 'article' );
		$this->assertDirectoryExists( MC_CONTENT_DIR . 'articles/' );
	}

	public function test_register_content_type_defaults(): void {
		mc_register_content_type( 'doc' );
		$type = mc_get_content_type( 'doc' );

		$this->assertSame( 'Docs', $type['label'] );
		$this->assertSame( 'Doc', $type['singular'] );
		$this->assertFalse( $type['hierarchical'] );
		$this->assertFalse( $type['has_archive'] );
	}

	// ── mc_get_content_type ──────────────────────────────────────────────

	public function test_get_content_type_null(): void {
		$this->assertNull( mc_get_content_type( 'nonexistent' ) );
	}

	// ── mc_get_content_types ─────────────────────────────────────────────

	public function test_get_content_types(): void {
		$types = mc_get_content_types();
		$this->assertArrayHasKey( 'page', $types );
	}

	// ── mc_save_content ──────────────────────────────────────────────────

	public function test_save_content_success(): void {
		$result = mc_save_content(
			'page',
			'hello-world',
			[
				'title'  => 'Hello World',
				'status' => 'publish',
			],
			'# Hello'
		);

		$this->assertTrue( $result );
	}

	public function test_save_content_creates_files(): void {
		mc_save_content( 'page', 'test-page', [ 'title' => 'Test' ], 'Body' );

		$this->assertFileExists( mc_content_json_path( 'page', 'test-page' ) );
		$this->assertFileExists( mc_content_md_path( 'page', 'test-page' ) );
	}

	public function test_save_content_empty_slug(): void {
		$result = mc_save_content( 'page', '', [ 'title' => 'No Slug' ] );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'invalid_slug', $result->get_error_code() );
	}

	public function test_save_content_invalid_type(): void {
		$result = mc_save_content( 'unknown_type', 'slug', [ 'title' => 'X' ] );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'invalid_type', $result->get_error_code() );
	}

	public function test_save_content_overwrites(): void {
		mc_save_content( 'page', 'ow', [ 'title' => 'V1' ], 'Body V1' );
		mc_save_content( 'page', 'ow', [ 'title' => 'V2' ], 'Body V2' );

		$content = mc_get_content( 'page', 'ow' );
		$this->assertSame( 'V2', $content['title'] );
		$this->assertSame( 'Body V2', $content['body_raw'] );
	}

	// ── mc_get_content ───────────────────────────────────────────────────

	public function test_get_content_success(): void {
		mc_save_content( 'page', 'about', [ 'title' => 'About Us' ], 'About body' );

		$content = mc_get_content( 'page', 'about' );
		$this->assertIsArray( $content );
		$this->assertSame( 'About Us', $content['title'] );
		$this->assertSame( 'About body', $content['body_raw'] );
		$this->assertSame( 'page', $content['type'] );
		$this->assertSame( 'about', $content['slug'] );
	}

	public function test_get_content_not_found(): void {
		$this->assertNull( mc_get_content( 'page', 'nonexistent' ) );
	}

	public function test_get_content_default_fields(): void {
		mc_save_content( 'page', 'defaults', [ 'title' => 'D' ] );
		$content = mc_get_content( 'page', 'defaults' );

		$this->assertArrayHasKey( 'status', $content );
		$this->assertArrayHasKey( 'author', $content );
		$this->assertArrayHasKey( 'created', $content );
		$this->assertArrayHasKey( 'modified', $content );
		$this->assertArrayHasKey( 'excerpt', $content );
		$this->assertArrayHasKey( 'body_html', $content );
	}

	// ── mc_query_content ─────────────────────────────────────────────────

	public function test_query_content_empty(): void {
		$this->assertSame( [], mc_query_content( [ 'type' => 'page' ] ) );
	}

	public function test_query_content_returns_items(): void {
		mc_save_content(
			'page',
			'q1',
			[
				'title'  => 'Q1',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'page',
			'q2',
			[
				'title'  => 'Q2',
				'status' => 'publish',
			]
		);

		$results = mc_query_content( [ 'type' => 'page' ] );
		$this->assertCount( 2, $results );
	}

	public function test_query_content_filters_by_status(): void {
		mc_save_content(
			'page',
			'pub',
			[
				'title'  => 'Pub',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'page',
			'dft',
			[
				'title'  => 'Draft',
				'status' => 'draft',
			]
		);

		$published = mc_query_content(
			[
				'type'   => 'page',
				'status' => 'publish',
			]
		);
		$this->assertCount( 1, $published );
		$this->assertSame( 'Pub', $published[0]['title'] );

		$drafts = mc_query_content(
			[
				'type'   => 'page',
				'status' => 'draft',
			]
		);
		$this->assertCount( 1, $drafts );
	}

	public function test_query_content_limit(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			mc_save_content(
				'page',
				"p{$i}",
				[
					'title'  => "P{$i}",
					'status' => 'publish',
				]
			);
		}

		$results = mc_query_content(
			[
				'type'  => 'page',
				'limit' => 3,
			]
		);
		$this->assertCount( 3, $results );
	}

	public function test_query_content_offset(): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			mc_save_content(
				'page',
				"off{$i}",
				[
					'title'   => "Off{$i}",
					'status'  => 'publish',
					'created' => "2024-01-0{$i}T00:00:00+00:00",
				]
			);
		}

		$results = mc_query_content(
			[
				'type'   => 'page',
				'limit'  => 2,
				'offset' => 2,
			]
		);
		$this->assertCount( 2, $results );
	}

	public function test_query_content_order_asc(): void {
		mc_save_content(
			'page',
			'oa',
			[
				'title'   => 'A',
				'status'  => 'publish',
				'created' => '2024-01-01T00:00:00+00:00',
			]
		);
		mc_save_content(
			'page',
			'ob',
			[
				'title'   => 'B',
				'status'  => 'publish',
				'created' => '2024-01-02T00:00:00+00:00',
			]
		);

		$results = mc_query_content(
			[
				'type'     => 'page',
				'order_by' => 'created',
				'order'    => 'ASC',
			]
		);

		$this->assertSame( 'A', $results[0]['title'] );
		$this->assertSame( 'B', $results[1]['title'] );
	}

	public function test_query_content_nonexistent_type(): void {
		$this->assertSame( [], mc_query_content( [ 'type' => 'nonexistent' ] ) );
	}

	// ── mc_count_content ─────────────────────────────────────────────────

	public function test_count_content_all(): void {
		mc_save_content(
			'page',
			'c1',
			[
				'title'  => 'C1',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'page',
			'c2',
			[
				'title'  => 'C2',
				'status' => 'draft',
			]
		);

		$this->assertSame( 2, mc_count_content( 'page' ) );
	}

	public function test_count_content_by_status(): void {
		mc_save_content(
			'page',
			's1',
			[
				'title'  => 'S1',
				'status' => 'publish',
			]
		);
		mc_save_content(
			'page',
			's2',
			[
				'title'  => 'S2',
				'status' => 'draft',
			]
		);
		mc_save_content(
			'page',
			's3',
			[
				'title'  => 'S3',
				'status' => 'publish',
			]
		);

		$this->assertSame( 2, mc_count_content( 'page', 'publish' ) );
		$this->assertSame( 1, mc_count_content( 'page', 'draft' ) );
	}

	public function test_count_content_nonexistent_type(): void {
		$this->assertSame( 0, mc_count_content( 'ghost_type' ) );
	}

	// ── mc_delete_content ────────────────────────────────────────────────

	public function test_delete_content_success(): void {
		mc_save_content( 'page', 'del-me', [ 'title' => 'Delete Me' ] );
		$this->assertTrue( mc_delete_content( 'page', 'del-me' ) );
		$this->assertNull( mc_get_content( 'page', 'del-me' ) );
	}

	public function test_delete_content_not_found(): void {
		$result = mc_delete_content( 'page', 'nope' );
		$this->assertInstanceOf( MC_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	// ── mc_content_exists ────────────────────────────────────────────────

	public function test_content_exists_true(): void {
		mc_save_content( 'page', 'exists', [ 'title' => 'Here' ] );
		$this->assertTrue( mc_content_exists( 'page', 'exists' ) );
	}

	public function test_content_exists_false(): void {
		$this->assertFalse( mc_content_exists( 'page', 'nope' ) );
	}

	// ── Path helpers ─────────────────────────────────────────────────────

	public function test_content_item_dir(): void {
		$dir = mc_content_item_dir( 'page', 'my-slug' );
		$this->assertStringEndsWith( 'pages/my-slug/', $dir );
	}

	public function test_content_md_path(): void {
		$path = mc_content_md_path( 'page', 'test' );
		$this->assertStringEndsWith( 'test/test.md', $path );
	}

	public function test_content_json_path(): void {
		$path = mc_content_json_path( 'page', 'test' );
		$this->assertStringEndsWith( 'test/test.json', $path );
	}
}
