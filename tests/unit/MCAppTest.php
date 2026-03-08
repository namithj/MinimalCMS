<?php

/**
 * Tests for MC_App container.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_App;
use MC_Hooks;
use MC_Formatter;
use MC_Http;
use MC_Cache;
use MC_Config;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_App
 */
class MCAppTest extends TestCase
{
	protected function setUp(): void
	{

		MC_App::reset();
	}

	protected function tearDown(): void
	{

		MC_App::reset();
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Singleton
	 * -------------------------------------------------------------------------
	 */

	public function test_instance_returns_same_object(): void
	{

		$a = MC_App::instance();
		$b = MC_App::instance();
		$this->assertSame($a, $b);
	}

	public function test_reset_clears_singleton(): void
	{

		$a = MC_App::instance();
		MC_App::reset();
		$b = MC_App::instance();

		$this->assertNotSame($a, $b);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Service registration
	 * -------------------------------------------------------------------------
	 */

	public function test_set_and_get(): void
	{

		$app   = MC_App::instance();
		$hooks = new MC_Hooks();
		$app->set('hooks', $hooks);

		$this->assertSame($hooks, $app->get('hooks'));
	}

	public function test_has(): void
	{

		$app = MC_App::instance();
		$this->assertFalse($app->has('nonexistent'));

		$app->set('test_svc', new MC_Hooks());
		$this->assertTrue($app->has('test_svc'));
	}

	public function test_get_throws_for_missing_service(): void
	{

		$this->expectException(\RuntimeException::class);
		MC_App::instance()->get('nonexistent');
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Boot
	 * -------------------------------------------------------------------------
	 */

	public function test_boot_creates_foundation_services(): void
	{

		$config_path = MC_TEST_TMP . 'v2_app_config.json';
		file_put_contents($config_path, json_encode(array(
			'site_url'   => 'http://test.example.com',
			'secret_key' => 'test-secret',
		)));

		$app = MC_App::instance();
		$app->boot($config_path);

		$this->assertTrue($app->is_booted());
		$this->assertInstanceOf(MC_Config::class, $app->config());
		$this->assertInstanceOf(MC_Hooks::class, $app->hooks());
		$this->assertInstanceOf(MC_Formatter::class, $app->formatter());
		$this->assertInstanceOf(MC_Http::class, $app->http());
		$this->assertInstanceOf(MC_Cache::class, $app->cache());

		unlink($config_path);
	}

	public function test_boot_is_idempotent(): void
	{

		$config_path = MC_TEST_TMP . 'v2_app_config2.json';
		file_put_contents($config_path, '{}');

		$app = MC_App::instance();
		$app->boot($config_path);
		$hooks_first = $app->hooks();

		$app->boot($config_path);
		$this->assertSame($hooks_first, $app->hooks());

		unlink($config_path);
	}

	public function test_boot_loads_config_data(): void
	{

		$config_path = MC_TEST_TMP . 'v2_app_config3.json';
		file_put_contents($config_path, json_encode(array(
			'site_name' => 'Boot Test',
		)));

		$app = MC_App::instance();
		$app->boot($config_path);

		$this->assertSame('Boot Test', $app->config()->get('site_name'));

		unlink($config_path);
	}

	public function test_mc_config_loaded_filter(): void
	{

		$config_path = MC_TEST_TMP . 'v2_app_config4.json';
		file_put_contents($config_path, json_encode(array(
			'site_name' => 'Original',
		)));

		// We can't hook before boot, but we can test the filter ran by checking the data.
		$app = MC_App::instance();
		$app->boot($config_path);

		// The filter was applied — verify the hook was fired by checking did_filter.
		$this->assertGreaterThanOrEqual(1, $app->hooks()->did_filter('mc_config_loaded'));

		unlink($config_path);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Typed accessors (Phase 1)
	 * -------------------------------------------------------------------------
	 */

	public function test_typed_accessors_after_boot(): void
	{

		$config_path = MC_TEST_TMP . 'v2_app_config5.json';
		file_put_contents($config_path, '{}');

		$app = MC_App::instance();
		$app->boot($config_path);

		// All foundation accessors should return correct types.
		$this->assertInstanceOf(MC_Config::class, $app->config());
		$this->assertInstanceOf(MC_Hooks::class, $app->hooks());
		$this->assertInstanceOf(MC_Formatter::class, $app->formatter());
		$this->assertInstanceOf(MC_Http::class, $app->http());
		$this->assertInstanceOf(MC_Cache::class, $app->cache());

		unlink($config_path);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Version constants
	 * -------------------------------------------------------------------------
	 */

	public function test_version_constant(): void
	{

		$this->assertNotEmpty(MC_App::VERSION);
		$this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', MC_App::VERSION);
	}

	public function test_required_php_constant(): void
	{

		$this->assertNotEmpty(MC_App::REQUIRED_PHP);
		$this->assertTrue(version_compare(PHP_VERSION, MC_App::REQUIRED_PHP, '>='));
	}
}
