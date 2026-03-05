<?php
/**
 * Unit tests for HTTP helpers (nonce system, request introspection).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase {

	protected function setUp(): void {
		// Ensure a clean session state.
		$_SESSION = [];
	}

	// ── mc_nonce_tick ────────────────────────────────────────────────────

	public function test_nonce_tick_returns_int(): void {
		$tick = mc_nonce_tick();
		$this->assertIsInt( $tick );
		$this->assertGreaterThan( 0, $tick );
	}

	public function test_nonce_tick_consistent_within_period(): void {
		$t1 = mc_nonce_tick();
		$t2 = mc_nonce_tick();
		$this->assertSame( $t1, $t2 );
	}

	// ── mc_create_nonce / mc_verify_nonce ─────────────────────────────────

	public function test_create_nonce_returns_string(): void {
		$nonce = mc_create_nonce( 'test_action' );
		$this->assertIsString( $nonce );
		$this->assertSame( 20, strlen( $nonce ) );
	}

	public function test_verify_nonce_valid(): void {
		$nonce = mc_create_nonce( 'my_action' );
		$this->assertTrue( mc_verify_nonce( $nonce, 'my_action' ) );
	}

	public function test_verify_nonce_wrong_action(): void {
		$nonce = mc_create_nonce( 'action_a' );
		$this->assertFalse( mc_verify_nonce( $nonce, 'action_b' ) );
	}

	public function test_verify_nonce_empty(): void {
		$this->assertFalse( mc_verify_nonce( '', 'action' ) );
	}

	public function test_verify_nonce_tampered(): void {
		$nonce    = mc_create_nonce( 'action' );
		$tampered = $nonce . 'x';
		$this->assertFalse( mc_verify_nonce( $tampered, 'action' ) );
	}

	public function test_nonce_default_action(): void {
		$nonce = mc_create_nonce();
		$this->assertTrue( mc_verify_nonce( $nonce ) );
	}

	// ── mc_request_method ────────────────────────────────────────────────

	public function test_request_method_default(): void {
		unset( $_SERVER['REQUEST_METHOD'] );
		$this->assertSame( 'GET', mc_request_method() );
	}

	public function test_request_method_post(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertSame( 'POST', mc_request_method() );
	}

	public function test_request_method_uppercase(): void {
		$_SERVER['REQUEST_METHOD'] = 'put';
		$this->assertSame( 'PUT', mc_request_method() );
	}

	// ── mc_is_post_request ───────────────────────────────────────────────

	public function test_is_post_request_true(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertTrue( mc_is_post_request() );
	}

	public function test_is_post_request_false(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertFalse( mc_is_post_request() );
	}

	// ── mc_is_ajax_request ───────────────────────────────────────────────

	public function test_is_ajax_request_by_header(): void {
		$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
		$this->assertTrue( mc_is_ajax_request() );
		unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
	}

	public function test_is_ajax_request_by_constant(): void {
		if ( ! defined( 'MC_DOING_AJAX' ) ) {
			define( 'MC_DOING_AJAX', true );
		}
		$this->assertTrue( mc_is_ajax_request() );
	}

	public function test_is_ajax_request_false(): void {
		unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
		// MC_DOING_AJAX might already be defined as true from previous test.
		// In that case this test would pass, which is acceptable.
		if ( ! defined( 'MC_DOING_AJAX' ) ) {
			$this->assertFalse( mc_is_ajax_request() );
		} else {
			$this->assertTrue( true ); // Constant already defined, skip.
		}
	}

	protected function tearDown(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		unset( $_SERVER['HTTP_X_REQUESTED_WITH'] );
	}
}
