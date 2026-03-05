<?php
/**
 * Integration tests for the cache system interacting with content.
 *
 * @package MinimalCMS\Tests\Integration
 */

namespace MinimalCMS\Tests\Integration;

use PHPUnit\Framework\TestCase;

class CacheIntegrationTest extends TestCase {

	protected function setUp(): void {
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter, $mc_runtime_cache, $mc_content_types;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];
		$mc_runtime_cache  = [];
		$mc_content_types  = [];

		// Ensure clean directories.
		foreach ( [ MC_CONTENT_DIR, MC_CACHE_DIR ] as $dir ) {
			if ( is_dir( $dir ) ) {
				mc_rmdir_recursive( $dir );
			}
			mkdir( $dir, 0755, true );
		}

		mc_register_content_type( 'page', [ 'label' => 'Pages' ] );
	}

	protected function tearDown(): void {
		foreach ( [ MC_CONTENT_DIR, MC_CACHE_DIR ] as $dir ) {
			if ( is_dir( $dir ) ) {
				mc_rmdir_recursive( $dir );
			}
		}
	}

	// ── Cache set and retrieve ───────────────────────────────────────────

	public function test_cache_stores_complex_data(): void {
		$data = [
			'items' => [ 'one', 'two', 'three' ],
			'meta'  => [
				'count' => 3,
				'page'  => 1,
			],
		];

		mc_cache_set( 'complex', $data, 'test', 3600 );
		$cached = mc_cache_get( 'complex', 'test' );

		$this->assertSame( $data, $cached );
	}

	// ── Cache groups ─────────────────────────────────────────────────────

	public function test_flush_group_isolates_other_groups(): void {
		mc_cache_set( 'key_a', 'val_a', 'group1', 3600 );
		mc_cache_set( 'key_b', 'val_b', 'group2', 3600 );

		mc_cache_flush( 'group1' );

		$this->assertFalse( mc_cache_get( 'key_a', 'group1' ) );
		$this->assertSame( 'val_b', mc_cache_get( 'key_b', 'group2' ) );
	}

	// ── Runtime and file persistence ─────────────────────────────────────

	public function test_runtime_cache_used_before_file(): void {
		mc_cache_set( 'speed', 'fast', 'default', 3600 );

		// Runtime cache should have it.
		global $mc_runtime_cache;
		$this->assertArrayHasKey( 'default:speed', $mc_runtime_cache );

		// Value from runtime (no file I/O needed).
		$this->assertSame( 'fast', mc_cache_get( 'speed', 'default' ) );
	}

	public function test_file_cache_persists_when_runtime_cleared(): void {
		mc_cache_set( 'persist', 'data', 'files', 3600 );

		// Clear runtime.
		global $mc_runtime_cache;
		$mc_runtime_cache = [];

		// Should read back from file.
		$this->assertSame( 'data', mc_cache_get( 'persist', 'files' ) );
	}

	// ── Cache with content operations ────────────────────────────────────

	public function test_cache_content_query_results(): void {
		// Create content.
		mc_save_content(
			'page',
			'p1',
			[
				'title'  => 'Page 1',
				'status' => 'published',
			],
			'Body 1'
		);
		mc_save_content(
			'page',
			'p2',
			[
				'title'  => 'Page 2',
				'status' => 'published',
			],
			'Body 2'
		);

		// Query and cache the results.
		$results = mc_query_content(
			[
				'type'   => 'page',
				'status' => 'published',
			]
		);
		mc_cache_set( 'page_query', $results, 'content', 3600 );

		// Verify cache returns same data.
		$cached = mc_cache_get( 'page_query', 'content' );
		$this->assertCount( 2, $cached );
		$this->assertSame( 'Page 1', $cached[0]['title'] ?? $cached[0]['meta']['title'] ?? '' );
	}

	// ── Cache delete ─────────────────────────────────────────────────────

	public function test_delete_removes_from_runtime_and_file(): void {
		mc_cache_set( 'delme', 'val', 'default', 3600 );
		$this->assertSame( 'val', mc_cache_get( 'delme', 'default' ) );

		mc_cache_delete( 'delme', 'default' );
		$this->assertFalse( mc_cache_get( 'delme', 'default' ) );

		// Also fully gone from file.
		global $mc_runtime_cache;
		$mc_runtime_cache = [];
		$this->assertFalse( mc_cache_get( 'delme', 'default' ) );
	}

	// ── Flush all ────────────────────────────────────────────────────────

	public function test_flush_all_clears_everything(): void {
		mc_cache_set( 'a', '1', 'g1', 3600 );
		mc_cache_set( 'b', '2', 'g2', 3600 );
		mc_cache_set( 'c', '3', 'g3', 3600 );

		mc_cache_flush();

		$this->assertFalse( mc_cache_get( 'a', 'g1' ) );
		$this->assertFalse( mc_cache_get( 'b', 'g2' ) );
		$this->assertFalse( mc_cache_get( 'c', 'g3' ) );
	}

	// ── Cache path uniqueness ────────────────────────────────────────────

	public function test_same_key_different_groups_are_independent(): void {
		mc_cache_set( 'key', 'group_a_val', 'group_a', 3600 );
		mc_cache_set( 'key', 'group_b_val', 'group_b', 3600 );

		$this->assertSame( 'group_a_val', mc_cache_get( 'key', 'group_a' ) );
		$this->assertSame( 'group_b_val', mc_cache_get( 'key', 'group_b' ) );
	}
}
