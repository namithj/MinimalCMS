<?php
/**
 * Unit tests for the hook system (filters and actions).
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HooksTest extends TestCase {

	protected function setUp(): void {
		// Reset global hook state before each test.
		global $mc_filters, $mc_actions, $mc_filters_run, $mc_current_filter;
		$mc_filters        = [];
		$mc_actions        = [];
		$mc_filters_run    = [];
		$mc_current_filter = [];
	}

	// ── mc_add_filter / mc_apply_filters ─────────────────────────────────

	public function test_add_filter_returns_true(): void {
		$this->assertTrue( mc_add_filter( 'test_hook', 'strtoupper' ) );
	}

	public function test_apply_filters_with_no_callbacks(): void {
		$this->assertSame( 'hello', mc_apply_filters( 'empty_hook', 'hello' ) );
	}

	public function test_apply_filters_modifies_value(): void {
		mc_add_filter( 'upper', 'strtoupper' );
		$this->assertSame( 'HELLO', mc_apply_filters( 'upper', 'hello' ) );
	}

	public function test_apply_filters_multiple_callbacks(): void {
		mc_add_filter( 'chain', 'strtoupper' );
		mc_add_filter(
			'chain',
			function ( $v ) {
				return $v . '!';
			}
		);

		$this->assertSame( 'HELLO!', mc_apply_filters( 'chain', 'hello' ) );
	}

	public function test_apply_filters_respects_priority(): void {
		mc_add_filter(
			'prio',
			function ( $v ) {
				return $v . '_late';
			},
			20
		);
		mc_add_filter(
			'prio',
			function ( $v ) {
				return $v . '_early';
			},
			5
		);

		$this->assertSame( 'start_early_late', mc_apply_filters( 'prio', 'start' ) );
	}

	public function test_apply_filters_extra_args(): void {
		mc_add_filter(
			'multi_arg',
			function ( $val, $extra ) {
				return $val . $extra;
			},
			10,
			2
		);

		$this->assertSame( 'ab', mc_apply_filters( 'multi_arg', 'a', 'b' ) );
	}

	public function test_apply_filters_accepted_args_limit(): void {
		mc_add_filter(
			'limited',
			function ( $val ) {
				// Should only receive $val even if extra args are passed.
				return $val . '_done';
			},
			10,
			1
		);

		$this->assertSame( 'x_done', mc_apply_filters( 'limited', 'x', 'ignored' ) );
	}

	// ── mc_remove_filter ─────────────────────────────────────────────────

	public function test_remove_filter(): void {
		$cb = function ( $v ) {
			return $v . '_added';
		};
		mc_add_filter( 'removable', $cb );
		$this->assertTrue( mc_remove_filter( 'removable', $cb, 10 ) );

		// Callback no longer fires.
		$this->assertSame( 'original', mc_apply_filters( 'removable', 'original' ) );
	}

	public function test_remove_filter_nonexistent(): void {
		$this->assertFalse( mc_remove_filter( 'nope', 'strtolower', 10 ) );
	}

	public function test_remove_filter_wrong_priority(): void {
		$cb = function ( $v ) {
			return $v;
		};
		mc_add_filter( 'wrong_prio', $cb, 5 );

		// Try to remove at default priority 10 – should fail.
		$this->assertFalse( mc_remove_filter( 'wrong_prio', $cb, 10 ) );
	}

	// ── mc_has_filter ────────────────────────────────────────────────────

	public function test_has_filter_no_callback(): void {
		$this->assertFalse( mc_has_filter( 'nonexistent' ) );
	}

	public function test_has_filter_exists(): void {
		mc_add_filter( 'exists', 'strtolower' );
		$this->assertTrue( mc_has_filter( 'exists' ) );
	}

	public function test_has_filter_specific_callback(): void {
		mc_add_filter( 'spec', 'strtoupper', 15 );
		$this->assertSame( 15, mc_has_filter( 'spec', 'strtoupper' ) );
	}

	public function test_has_filter_specific_callback_not_found(): void {
		mc_add_filter( 'spec2', 'strtoupper' );
		$this->assertFalse( mc_has_filter( 'spec2', 'strtolower' ) );
	}

	// ── Action API ───────────────────────────────────────────────────────

	public function test_add_action_returns_true(): void {
		$this->assertTrue( mc_add_action( 'act', function () {} ) );
	}

	public function test_do_action_fires_callback(): void {
		$called = false;
		mc_add_action(
			'fire',
			function () use ( &$called ) {
				$called = true;
			}
		);
		mc_do_action( 'fire' );
		$this->assertTrue( $called );
	}

	public function test_do_action_passes_args(): void {
		$received = null;
		mc_add_action(
			'args_test',
			function ( $val ) use ( &$received ) {
				$received = $val;
			}
		);
		mc_do_action( 'args_test', 'hello' );
		$this->assertSame( 'hello', $received );
	}

	public function test_do_action_respects_priority(): void {
		$order = [];
		mc_add_action(
			'prio_act',
			function () use ( &$order ) {
				$order[] = 'B';
			},
			20
		);
		mc_add_action(
			'prio_act',
			function () use ( &$order ) {
				$order[] = 'A';
			},
			5
		);

		mc_do_action( 'prio_act' );
		$this->assertSame( [ 'A', 'B' ], $order );
	}

	public function test_do_action_with_no_callbacks(): void {
		// Should not throw.
		mc_do_action( 'empty_action' );
		$this->assertTrue( true );
	}

	public function test_remove_action(): void {
		$cb = function () {};
		mc_add_action( 'rem_act', $cb );
		$this->assertTrue( mc_remove_action( 'rem_act', $cb, 10 ) );
	}

	public function test_has_action(): void {
		$this->assertFalse( mc_has_action( 'nope' ) );

		mc_add_action( 'yes', 'strtolower' );
		$this->assertTrue( (bool) mc_has_action( 'yes' ) );
	}

	// ── Introspection ────────────────────────────────────────────────────

	public function test_did_action(): void {
		$this->assertSame( 0, mc_did_action( 'counted' ) );

		mc_do_action( 'counted' );
		$this->assertSame( 1, mc_did_action( 'counted' ) );

		mc_do_action( 'counted' );
		$this->assertSame( 2, mc_did_action( 'counted' ) );
	}

	public function test_did_filter(): void {
		$this->assertSame( 0, mc_did_filter( 'filt_count' ) );

		mc_apply_filters( 'filt_count', 'val' );
		$this->assertSame( 1, mc_did_filter( 'filt_count' ) );
	}

	public function test_doing_action_inside_callback(): void {
		$inside = false;
		mc_add_action(
			'introspect',
			function () use ( &$inside ) {
				$inside = mc_doing_action( 'introspect' );
			}
		);
		mc_do_action( 'introspect' );
		$this->assertTrue( $inside );
	}

	public function test_doing_action_outside(): void {
		$this->assertFalse( mc_doing_action( 'not_running' ) );
	}

	public function test_doing_filter_no_hook(): void {
		$this->assertFalse( mc_doing_filter() );
	}

	public function test_doing_filter_inside(): void {
		$inside = false;
		mc_add_filter(
			'df_test',
			function ( $v ) use ( &$inside ) {
				$inside = mc_doing_filter( 'df_test' );
				return $v;
			}
		);
		mc_apply_filters( 'df_test', 'x' );
		$this->assertTrue( $inside );
	}

	public function test_current_filter(): void {
		$name = '';
		mc_add_action(
			'cf_test',
			function () use ( &$name ) {
				$name = mc_current_filter();
			}
		);
		mc_do_action( 'cf_test' );
		$this->assertSame( 'cf_test', $name );
	}

	public function test_current_filter_empty_when_none_running(): void {
		$this->assertSame( '', mc_current_filter() );
	}
}
