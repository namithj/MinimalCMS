<?php
/**
 * Unit tests for the file cache system.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase {

	protected function setUp(): void {
		// Ensure the cache dir exists and is clean.
		global $mc_runtime_cache;
		$mc_runtime_cache = [];

		if ( is_dir( MC_CACHE_DIR ) ) {
			mc_rmdir_recursive( MC_CACHE_DIR );
		}
		mkdir( MC_CACHE_DIR, 0755, true );
	}

	protected function tearDown(): void {
		global $mc_runtime_cache;
		$mc_runtime_cache = [];
	}

	// ── mc_cache_set / mc_cache_get ──────────────────────────────────────

	public function test_set_and_get(): void {
		mc_cache_set( 'key1', 'value1', 'default', 3600 );
		$this->assertSame( 'value1', mc_cache_get( 'key1', 'default' ) );
	}

	public function test_get_from_runtime_cache(): void {
		mc_cache_set( 'rt', 'runtime_val' );

		// Value should be in runtime cache without hitting disk.
		$this->assertSame( 'runtime_val', mc_cache_get( 'rt' ) );
	}

	public function test_get_from_file_when_runtime_cleared(): void {
		mc_cache_set( 'file_test', 'file_val' );

		// Clear runtime cache to force file read.
		global $mc_runtime_cache;
		$mc_runtime_cache = [];

		$this->assertSame( 'file_val', mc_cache_get( 'file_test' ) );
	}

	public function test_get_returns_false_for_missing(): void {
		$this->assertFalse( mc_cache_get( 'missing_key' ) );
	}

	public function test_cache_with_array_value(): void {
		$data = [
			'a' => 1,
			'b' => [ 2, 3 ],
		];
		mc_cache_set( 'arr', $data );
		$this->assertSame( $data, mc_cache_get( 'arr' ) );
	}

	public function test_cache_with_different_groups(): void {
		mc_cache_set( 'key', 'a', 'group_a' );
		mc_cache_set( 'key', 'b', 'group_b' );

		$this->assertSame( 'a', mc_cache_get( 'key', 'group_a' ) );
		$this->assertSame( 'b', mc_cache_get( 'key', 'group_b' ) );
	}

	public function test_cache_no_expiry(): void {
		mc_cache_set( 'forever', 'val', 'default', 0 );

		// Clear runtime and re-fetch from file.
		global $mc_runtime_cache;
		$mc_runtime_cache = [];

		$this->assertSame( 'val', mc_cache_get( 'forever' ) );
	}

	// ── mc_cache_delete ──────────────────────────────────────────────────

	public function test_delete(): void {
		mc_cache_set( 'del', 'x' );
		$this->assertTrue( mc_cache_delete( 'del' ) );
		$this->assertFalse( mc_cache_get( 'del' ) );
	}

	public function test_delete_nonexistent(): void {
		$this->assertFalse( mc_cache_delete( 'not_here' ) );
	}

	// ── mc_cache_flush ───────────────────────────────────────────────────

	public function test_flush_all(): void {
		mc_cache_set( 'a', '1', 'g1' );
		mc_cache_set( 'b', '2', 'g2' );

		mc_cache_flush();

		global $mc_runtime_cache;
		$mc_runtime_cache = [];

		$this->assertFalse( mc_cache_get( 'a', 'g1' ) );
		$this->assertFalse( mc_cache_get( 'b', 'g2' ) );
	}

	public function test_flush_specific_group(): void {
		mc_cache_set( 'x', '1', 'keep' );
		mc_cache_set( 'y', '2', 'flush_me' );

		mc_cache_flush( 'flush_me' );

		global $mc_runtime_cache;
		$mc_runtime_cache = [];

		// 'keep' group should still have data on disk.
		$this->assertSame( '1', mc_cache_get( 'x', 'keep' ) );
		$this->assertFalse( mc_cache_get( 'y', 'flush_me' ) );
	}

	// ── mc_cache_file_path ───────────────────────────────────────────────

	public function test_cache_file_path_structure(): void {
		$path = mc_cache_file_path( 'my_key', 'my_group' );

		$this->assertStringStartsWith( MC_CACHE_DIR, $path );
		$this->assertStringContainsString( 'my_group', $path );
		$this->assertStringEndsWith( '.php', $path );
	}

	public function test_cache_file_path_uses_md5(): void {
		$path = mc_cache_file_path( 'test', 'default' );
		$this->assertStringContainsString( md5( 'test' ), $path );
	}

	// ── mc_rmdir_recursive ───────────────────────────────────────────────

	public function test_rmdir_recursive(): void {
		$dir = MC_CACHE_DIR . 'rm_test/';
		mkdir( $dir, 0755, true );
		file_put_contents( $dir . 'file.txt', 'hello' );
		mkdir( $dir . 'subdir', 0755 );
		file_put_contents( $dir . 'subdir/nested.txt', 'world' );

		mc_rmdir_recursive( $dir );

		$this->assertDirectoryDoesNotExist( $dir );
	}

	public function test_rmdir_recursive_nonexistent(): void {
		// Should not throw.
		mc_rmdir_recursive( MC_CACHE_DIR . 'nonexistent_dir_xyz/' );
		$this->assertTrue( true );
	}
}
