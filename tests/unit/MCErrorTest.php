<?php
/**
 * Unit tests for MC_Error class and mc_is_error() function.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Error;
use PHPUnit\Framework\TestCase;

class MCErrorTest extends TestCase {

	public function test_constructor_empty(): void {
		$error = new MC_Error();
		$this->assertFalse( $error->has_errors() );
		$this->assertSame( [], $error->get_error_codes() );
	}

	public function test_constructor_with_code_and_message(): void {
		$error = new MC_Error( 'test_code', 'Test message' );
		$this->assertTrue( $error->has_errors() );
		$this->assertSame( 'test_code', $error->get_error_code() );
		$this->assertSame( 'Test message', $error->get_error_message() );
	}

	public function test_constructor_with_data(): void {
		$error = new MC_Error( 'test_code', 'msg', [ 'key' => 'val' ] );
		$this->assertSame( [ 'key' => 'val' ], $error->get_error_data( 'test_code' ) );
	}

	public function test_add_multiple_codes(): void {
		$error = new MC_Error();
		$error->add( 'code_a', 'Message A' );
		$error->add( 'code_b', 'Message B' );

		$this->assertSame( [ 'code_a', 'code_b' ], $error->get_error_codes() );
	}

	public function test_add_multiple_messages_same_code(): void {
		$error = new MC_Error();
		$error->add( 'code', 'First' );
		$error->add( 'code', 'Second' );

		$this->assertSame( [ 'First', 'Second' ], $error->get_error_messages( 'code' ) );
		// get_error_message returns the first.
		$this->assertSame( 'First', $error->get_error_message( 'code' ) );
	}

	public function test_get_error_code_returns_first(): void {
		$error = new MC_Error();
		$error->add( 'alpha', 'A' );
		$error->add( 'beta', 'B' );

		$this->assertSame( 'alpha', $error->get_error_code() );
	}

	public function test_get_error_code_empty(): void {
		$error = new MC_Error();
		$this->assertSame( '', $error->get_error_code() );
	}

	public function test_get_error_messages_default_code(): void {
		$error = new MC_Error( 'first', 'Hello' );
		// Empty string should use the first error code.
		$this->assertSame( [ 'Hello' ], $error->get_error_messages() );
	}

	public function test_get_error_messages_unknown_code(): void {
		$error = new MC_Error( 'known', 'Hi' );
		$this->assertSame( [], $error->get_error_messages( 'unknown' ) );
	}

	public function test_get_error_message_default_code(): void {
		$error = new MC_Error( 'c', 'M' );
		$this->assertSame( 'M', $error->get_error_message() );
	}

	public function test_get_error_message_empty_when_no_errors(): void {
		$error = new MC_Error();
		$this->assertSame( '', $error->get_error_message() );
	}

	public function test_get_error_data_default_code(): void {
		$error = new MC_Error( 'c', 'm', 42 );
		$this->assertSame( 42, $error->get_error_data() );
	}

	public function test_get_error_data_missing(): void {
		$error = new MC_Error( 'c', 'm' );
		$this->assertSame( '', $error->get_error_data( 'c' ) );
	}

	public function test_get_error_data_for_unknown_code(): void {
		$error = new MC_Error( 'c', 'm', 'data' );
		$this->assertSame( '', $error->get_error_data( 'nonexistent' ) );
	}

	public function test_has_errors(): void {
		$error = new MC_Error();
		$this->assertFalse( $error->has_errors() );

		$error->add( 'c', 'm' );
		$this->assertTrue( $error->has_errors() );
	}

	public function test_remove(): void {
		$error = new MC_Error( 'a', 'msg_a', 'data_a' );
		$error->add( 'b', 'msg_b' );

		$error->remove( 'a' );

		$this->assertSame( [ 'b' ], $error->get_error_codes() );
		$this->assertSame( '', $error->get_error_data( 'a' ) );
	}

	public function test_remove_nonexistent_code(): void {
		$error = new MC_Error( 'a', 'msg' );
		$error->remove( 'nonexistent' );

		// Should not affect existing codes.
		$this->assertTrue( $error->has_errors() );
		$this->assertSame( [ 'a' ], $error->get_error_codes() );
	}

	public function test_mc_is_error_true(): void {
		$this->assertTrue( mc_is_error( new MC_Error( 'c', 'm' ) ) );
	}

	public function test_mc_is_error_false_for_string(): void {
		$this->assertFalse( mc_is_error( 'not an error' ) );
	}

	public function test_mc_is_error_false_for_null(): void {
		$this->assertFalse( mc_is_error( null ) );
	}

	public function test_mc_is_error_false_for_bool(): void {
		$this->assertFalse( mc_is_error( true ) );
		$this->assertFalse( mc_is_error( false ) );
	}

	public function test_data_overwritten_by_later_add(): void {
		$error = new MC_Error();
		$error->add( 'c', 'First', 'data_1' );
		$error->add( 'c', 'Second', 'data_2' );

		// Data should reflect the latest add.
		$this->assertSame( 'data_2', $error->get_error_data( 'c' ) );
	}
}
