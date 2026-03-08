<?php
/**
 * Tests for MC_Hooks class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Hooks
 */
class MCHooksClassTest extends TestCase {

	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks = new MC_Hooks();
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Filters
	 * -------------------------------------------------------------------------
	 */

	public function test_add_filter_returns_true(): void {

		$result = $this->hooks->add_filter('test_hook', 'strtoupper');
		$this->assertTrue($result);
	}

	public function test_apply_filters_returns_unmodified_value_when_no_callbacks(): void {

		$this->assertSame('hello', $this->hooks->apply_filters('empty_hook', 'hello'));
	}

	public function test_apply_filters_modifies_value(): void {

		$this->hooks->add_filter('upper', 'strtoupper');
		$this->assertSame('HELLO', $this->hooks->apply_filters('upper', 'hello'));
	}

	public function test_apply_filters_respects_priority(): void {

		$this->hooks->add_filter('prio', function ($v) {
			return $v . 'B';
		}, 20);

		$this->hooks->add_filter('prio', function ($v) {
			return $v . 'A';
		}, 10);

		$this->assertSame('xAB', $this->hooks->apply_filters('prio', 'x'));
	}

	public function test_apply_filters_passes_extra_args(): void {

		$this->hooks->add_filter('multi', function ($v, $a, $b) {
			return $v . $a . $b;
		}, 10, 3);

		$this->assertSame('foobarqux', $this->hooks->apply_filters('multi', 'foo', 'bar', 'qux'));
	}

	public function test_apply_filters_respects_accepted_args(): void {

		$this->hooks->add_filter('limited', function ($v) {
			return $v . '!';
		}, 10, 1);

		$this->assertSame('hi!', $this->hooks->apply_filters('limited', 'hi', 'extra-ignored'));
	}

	public function test_remove_filter(): void {

		$cb = function ($v) {
			return 'modified';
		};

		$this->hooks->add_filter('rem', $cb);
		$this->assertTrue($this->hooks->remove_filter('rem', $cb));
		$this->assertSame('original', $this->hooks->apply_filters('rem', 'original'));
	}

	public function test_remove_filter_returns_false_when_not_found(): void {

		$this->assertFalse($this->hooks->remove_filter('nope', 'strtoupper'));
	}

	public function test_has_filter_returns_false_for_unregistered(): void {

		$this->assertFalse($this->hooks->has_filter('nope'));
	}

	public function test_has_filter_returns_true_for_registered(): void {

		$this->hooks->add_filter('exists', 'strlen');
		$this->assertTrue($this->hooks->has_filter('exists'));
	}

	public function test_has_filter_returns_priority_for_specific_callback(): void {

		$this->hooks->add_filter('prio_check', 'strtolower', 25);
		$this->assertSame(25, $this->hooks->has_filter('prio_check', 'strtolower'));
	}

	public function test_remove_all_filters(): void {

		$this->hooks->add_filter('nuke', 'strtoupper');
		$this->hooks->add_filter('nuke', 'strtolower', 20);
		$this->hooks->remove_all_filters('nuke');
		$this->assertSame('original', $this->hooks->apply_filters('nuke', 'original'));
	}

	public function test_remove_all_filters_specific_priority(): void {

		$this->hooks->add_filter('nuke_p', function ($v) {
			return $v . 'A';
		}, 10);

		$this->hooks->add_filter('nuke_p', function ($v) {
			return $v . 'B';
		}, 20);

		$this->hooks->remove_all_filters('nuke_p', 10);
		$this->assertSame('xB', $this->hooks->apply_filters('nuke_p', 'x'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Actions
	 * -------------------------------------------------------------------------
	 */

	public function test_add_action_and_do_action(): void {

		$called = false;
		$this->hooks->add_action('test_action', function () use (&$called) {
			$called = true;
		});

		$this->hooks->do_action('test_action');
		$this->assertTrue($called);
	}

	public function test_do_action_passes_args(): void {

		$received = array();
		$this->hooks->add_action('args_action', function ($a, $b) use (&$received) {
			$received = array($a, $b);
		}, 10, 2);

		$this->hooks->do_action('args_action', 'foo', 'bar');
		$this->assertSame(array('foo', 'bar'), $received);
	}

	public function test_do_action_respects_priority(): void {

		$order = array();
		$this->hooks->add_action('order', function () use (&$order) {
			$order[] = 'second';
		}, 20);
		$this->hooks->add_action('order', function () use (&$order) {
			$order[] = 'first';
		}, 10);

		$this->hooks->do_action('order');
		$this->assertSame(array('first', 'second'), $order);
	}

	public function test_remove_action(): void {

		$called = false;
		$cb     = function () use (&$called) {
			$called = true;
		};

		$this->hooks->add_action('rem_act', $cb);
		$this->hooks->remove_action('rem_act', $cb);
		$this->hooks->do_action('rem_act');
		$this->assertFalse($called);
	}

	public function test_has_action(): void {

		$this->assertFalse($this->hooks->has_action('nope'));
		$this->hooks->add_action('yep', 'strlen');
		$this->assertTrue($this->hooks->has_action('yep'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Introspection
	 * -------------------------------------------------------------------------
	 */

	public function test_did_action(): void {

		$this->assertSame(0, $this->hooks->did_action('counted'));
		$this->hooks->do_action('counted');
		$this->assertSame(1, $this->hooks->did_action('counted'));
		$this->hooks->do_action('counted');
		$this->assertSame(2, $this->hooks->did_action('counted'));
	}

	public function test_did_filter(): void {

		$this->assertSame(0, $this->hooks->did_filter('counted_f'));
		$this->hooks->apply_filters('counted_f', 'val');
		$this->assertSame(1, $this->hooks->did_filter('counted_f'));
	}

	public function test_doing_filter_during_execution(): void {

		$inside = false;
		$this->hooks->add_filter('introspect', function ($v) use (&$inside) {
			$inside = $this->hooks->doing_filter('introspect');
			return $v;
		});

		$this->hooks->apply_filters('introspect', 'val');
		$this->assertTrue($inside);
		$this->assertFalse($this->hooks->doing_filter('introspect'));
	}

	public function test_doing_action_during_execution(): void {

		$inside = false;
		$this->hooks->add_action('intro_act', function () use (&$inside) {
			$inside = $this->hooks->doing_action('intro_act');
		});

		$this->hooks->do_action('intro_act');
		$this->assertTrue($inside);
	}

	public function test_current_filter(): void {

		$name = false;
		$this->hooks->add_filter('cf_test', function ($v) use (&$name) {
			$name = $this->hooks->current_filter();
			return $v;
		});

		$this->hooks->apply_filters('cf_test', 'val');
		$this->assertSame('cf_test', $name);
	}

	public function test_current_filter_returns_false_when_idle(): void {

		$this->assertFalse($this->hooks->current_filter());
	}

	public function test_doing_filter_any(): void {

		$any = false;
		$this->hooks->add_action('any_test', function () use (&$any) {
			$any = $this->hooks->doing_filter();
		});

		$this->hooks->do_action('any_test');
		$this->assertTrue($any);
		$this->assertFalse($this->hooks->doing_filter());
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Isolation — class instances are independent
	 * -------------------------------------------------------------------------
	 */

	public function test_independent_instances(): void {

		$hooks2 = new MC_Hooks();
		$this->hooks->add_filter('isolated', function ($v) {
			return 'modified';
		});

		$this->assertSame('untouched', $hooks2->apply_filters('isolated', 'untouched'));
	}
}
