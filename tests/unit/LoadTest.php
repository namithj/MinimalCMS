<?php
/**
 * Unit tests for load helper functions.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LoadTest extends TestCase {

	// ── mc_load_config ───────────────────────────────────────────────────

	public function test_load_config_valid_json(): void {
		$file = MC_TEST_TMP . 'test_config.json';
		file_put_contents( $file, json_encode( [ 'key' => 'value' ] ) );

		$config = mc_load_config( $file );
		$this->assertSame( [ 'key' => 'value' ], $config );

		unlink( $file );
	}

	public function test_load_config_invalid_json(): void {
		$file = MC_TEST_TMP . 'bad_config.json';
		file_put_contents( $file, 'not json at all' );

		$this->assertSame( [], mc_load_config( $file ) );

		unlink( $file );
	}

	public function test_load_config_missing_file(): void {
		$this->assertSame( [], mc_load_config( '/nonexistent/path/config.json' ) );
	}

	public function test_load_config_empty_file(): void {
		$file = MC_TEST_TMP . 'empty_config.json';
		file_put_contents( $file, '' );

		$this->assertSame( [], mc_load_config( $file ) );

		unlink( $file );
	}

	// ── mc_maybe_define ──────────────────────────────────────────────────

	public function test_maybe_define_new(): void {
		$name = 'MC_TEST_CONST_' . uniqid();
		mc_maybe_define( $name, 'test_value' );
		$this->assertTrue( defined( $name ) );
		$this->assertSame( 'test_value', constant( $name ) );
	}

	public function test_maybe_define_does_not_overwrite(): void {
		$name = 'MC_TEST_EXISTING_' . uniqid();
		define( $name, 'original' );
		mc_maybe_define( $name, 'new_value' );
		$this->assertSame( 'original', constant( $name ) );
	}

	// ── trailingslashit ──────────────────────────────────────────────────

	public function test_trailingslashit_adds_slash(): void {
		$this->assertSame( '/path/', trailingslashit( '/path' ) );
	}

	public function test_trailingslashit_no_double(): void {
		$this->assertSame( '/path/', trailingslashit( '/path/' ) );
	}

	public function test_trailingslashit_backslash(): void {
		$this->assertSame( 'C:/', trailingslashit( 'C:\\' ) );
	}

	// ── untrailingslashit ────────────────────────────────────────────────

	public function test_untrailingslashit_removes_slash(): void {
		$this->assertSame( '/path', untrailingslashit( '/path/' ) );
	}

	public function test_untrailingslashit_no_slash(): void {
		$this->assertSame( '/path', untrailingslashit( '/path' ) );
	}

	public function test_untrailingslashit_backslash(): void {
		$this->assertSame( 'C:', untrailingslashit( 'C:\\' ) );
	}

	// ── mc_site_url ──────────────────────────────────────────────────────

	public function test_site_url_no_path(): void {
		$result = mc_site_url();
		$this->assertStringEndsWith( '/', $result );
	}

	public function test_site_url_with_path(): void {
		$result = mc_site_url( 'page/about' );
		$this->assertStringEndsWith( 'page/about', $result );
	}

	public function test_site_url_strips_leading_slash(): void {
		$r1 = mc_site_url( '/path' );
		$r2 = mc_site_url( 'path' );
		$this->assertSame( $r1, $r2 );
	}

	// ── mc_content_url ───────────────────────────────────────────────────

	public function test_content_url(): void {
		$url = mc_content_url( 'uploads/img.jpg' );
		$this->assertStringContainsString( 'mc-content/uploads/img.jpg', $url );
	}

	public function test_content_url_empty(): void {
		$url = mc_content_url();
		$this->assertStringContainsString( 'mc-content/', $url );
	}

	// ── mc_theme_url ─────────────────────────────────────────────────────

	public function test_theme_url(): void {
		$url = mc_theme_url( 'style.css' );
		$this->assertStringContainsString( 'themes/default/style.css', $url );
	}

	// ── mc_admin_url ─────────────────────────────────────────────────────

	public function test_admin_url(): void {
		$url = mc_admin_url( 'settings.php' );
		$this->assertStringContainsString( 'mc-admin/settings.php', $url );
	}

	public function test_admin_url_empty(): void {
		$url = mc_admin_url();
		$this->assertStringContainsString( 'mc-admin/', $url );
	}
}
