<?php

/**
 * Tests for MC_File_Guard class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_File_Guard;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_File_Guard
 */
class MCFileGuardClassTest extends TestCase
{
	private string $temp_dir;

	protected function setUp(): void
	{

		$this->temp_dir = sys_get_temp_dir() . '/mc_guard_test_' . uniqid() . '/';
		mkdir($this->temp_dir, 0755, true);
	}

	protected function tearDown(): void
	{

		$this->rm_recursive($this->temp_dir);
	}

	private function rm_recursive(string $dir): void
	{

		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir($path) ? $this->rm_recursive($path) : unlink($path);
		}
		rmdir($dir);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  write / read
	 * -------------------------------------------------------------------------
	 */

	public function test_write_creates_guarded_file(): void
	{

		$path = $this->temp_dir . 'data.php';
		$this->assertTrue(MC_File_Guard::write($path, 'hello world'));
		$this->assertFileExists($path);

		$raw = file_get_contents($path);
		$this->assertStringStartsWith(MC_File_Guard::GUARD, $raw);
	}

	public function test_read_strips_guard(): void
	{

		$path = $this->temp_dir . 'data.php';
		MC_File_Guard::write($path, 'hello world');

		$body = MC_File_Guard::read($path);
		$this->assertSame('hello world', $body);
	}

	public function test_read_returns_false_for_missing_file(): void
	{

		$this->assertFalse(MC_File_Guard::read('/nonexistent/path.php'));
	}

	public function test_read_returns_false_for_guardless_file(): void
	{

		$path = $this->temp_dir . 'plain.php';
		file_put_contents($path, 'no guard here');

		$this->assertFalse(MC_File_Guard::read($path));
	}

	public function test_write_creates_parent_directories(): void
	{

		$path = $this->temp_dir . 'sub/dir/item.php';
		$this->assertTrue(MC_File_Guard::write($path, 'nested'));
		$this->assertSame('nested', MC_File_Guard::read($path));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  write_json / read_json
	 * -------------------------------------------------------------------------
	 */

	public function test_write_json_and_read_json(): void
	{

		$path = $this->temp_dir . 'config.php';
		$data = array('site_name' => 'Test', 'debug' => true);

		$this->assertTrue(MC_File_Guard::write_json($path, $data));

		$result = MC_File_Guard::read_json($path);
		$this->assertSame($data, $result);
	}

	public function test_read_json_returns_null_for_missing_file(): void
	{

		$this->assertNull(MC_File_Guard::read_json('/nonexistent/file.php'));
	}

	public function test_read_json_returns_null_for_invalid_json(): void
	{

		$path = $this->temp_dir . 'bad.php';
		MC_File_Guard::write($path, 'not valid json');

		$this->assertNull(MC_File_Guard::read_json($path));
	}

	public function test_roundtrip_preserves_complex_data(): void
	{

		$path = $this->temp_dir . 'complex.php';
		$data = array(
			'nested' => array('a' => 1, 'b' => array('c' => true)),
			'list'   => array(1, 2, 3),
			'empty'  => array(),
			'string' => 'hello/world',
			'unicode' => 'café ☕',
		);

		MC_File_Guard::write_json($path, $data);
		$this->assertSame($data, MC_File_Guard::read_json($path));
	}
}
