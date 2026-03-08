<?php

/**
 * Tests for MC_Session class.
 *
 * Session management relies on PHP session internals which are hard to
 * exercise in CLI PHPUnit, so these tests only verify construction
 * and non-session-dependent behaviour.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Hooks;
use MC_Session;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Session
 */
class MCSessionClassTest extends TestCase
{
	private string $session_dir;

	protected function setUp(): void
	{

		$this->session_dir = sys_get_temp_dir() . '/mc_test_sessions_' . uniqid();
	}

	protected function tearDown(): void
	{

		if (is_dir($this->session_dir)) {
			array_map('unlink', glob($this->session_dir . '/*'));
			rmdir($this->session_dir);
		}
	}

	public function test_instantiation(): void
	{

		$session = new MC_Session(new MC_Hooks(), $this->session_dir);
		$this->assertInstanceOf(MC_Session::class, $session);
	}

	public function test_custom_lifetime(): void
	{

		$session = new MC_Session(new MC_Hooks(), $this->session_dir, 3600);
		$this->assertInstanceOf(MC_Session::class, $session);
	}
}
