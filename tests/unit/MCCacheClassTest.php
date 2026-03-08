<?php
/**
 * Tests for MC_Cache class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Cache;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Cache
 */
class MCCacheClassTest extends TestCase {

	private MC_Cache $cache;
	private string $cache_dir;

	protected function setUp(): void {

		$this->cache_dir = MC_TEST_TMP . 'v2_cache/';

		if (!is_dir($this->cache_dir)) {
			mkdir($this->cache_dir, 0755, true);
		}

		$this->cache = new MC_Cache($this->cache_dir);
	}

	protected function tearDown(): void {

		$this->cache->flush();
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Basic get/set/delete
	 * -------------------------------------------------------------------------
	 */

	public function test_get_returns_false_for_missing_key(): void {

		$this->assertFalse($this->cache->get('nonexistent'));
	}

	public function test_set_and_get(): void {

		$this->assertTrue($this->cache->set('key1', 'value1'));
		$this->assertSame('value1', $this->cache->get('key1'));
	}

	public function test_set_and_get_with_group(): void {

		$this->cache->set('key', 'group_val', 'my_group');
		$this->assertSame('group_val', $this->cache->get('key', 'my_group'));
		$this->assertFalse($this->cache->get('key', 'other_group'));
	}

	public function test_set_array_value(): void {

		$data = array('foo' => 'bar', 'num' => 42);
		$this->cache->set('arr', $data);
		$this->assertSame($data, $this->cache->get('arr'));
	}

	public function test_delete(): void {

		$this->cache->set('del_me', 'exists');
		$this->assertTrue($this->cache->delete('del_me'));
		$this->assertFalse($this->cache->get('del_me'));
	}

	public function test_delete_nonexistent(): void {

		$this->assertFalse($this->cache->delete('nope'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  TTL / Expiration
	 * -------------------------------------------------------------------------
	 */

	public function test_expired_cache_returns_false(): void {

		// Set with 1-second TTL and immediately expire it by modifying the file.
		$this->cache->set('exp', 'val', 'default', 1);

		$file = $this->cache->file_path('exp', 'default');
		$content = '<?php return ' . var_export(
			array('expires' => time() - 10, 'value' => 'val'),
			true
		) . ';' . "\n";
		file_put_contents($file, $content, LOCK_EX);

		// Clear runtime cache by creating a new instance.
		$fresh = new MC_Cache($this->cache_dir);
		$this->assertFalse($fresh->get('exp'));
	}

	public function test_zero_ttl_never_expires(): void {

		$this->cache->set('forever', 'eternal', 'default', 0);

		$file = $this->cache->file_path('forever', 'default');
		$this->assertFileExists($file);

		// Read from disk (new instance has no runtime cache).
		$fresh = new MC_Cache($this->cache_dir);
		$this->assertSame('eternal', $fresh->get('forever'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Flush
	 * -------------------------------------------------------------------------
	 */

	public function test_flush_clears_all(): void {

		$this->cache->set('a', 1);
		$this->cache->set('b', 2, 'group2');
		$this->cache->flush();

		$fresh = new MC_Cache($this->cache_dir);
		$this->assertFalse($fresh->get('a'));
		$this->assertFalse($fresh->get('b', 'group2'));
	}

	public function test_flush_specific_group(): void {

		$this->cache->set('a', 1, 'keep');
		$this->cache->set('b', 2, 'nuke');
		$this->cache->flush('nuke');

		$fresh = new MC_Cache($this->cache_dir);
		$this->assertSame(1, $fresh->get('a', 'keep'));
		$this->assertFalse($fresh->get('b', 'nuke'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Runtime cache layer
	 * -------------------------------------------------------------------------
	 */

	public function test_runtime_cache_hit(): void {

		$this->cache->set('rt', 'val');

		// Delete the file, but runtime should still serve it.
		$file = $this->cache->file_path('rt', 'default');
		if (is_file($file)) {
			unlink($file);
		}

		$this->assertSame('val', $this->cache->get('rt'));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  File path
	 * -------------------------------------------------------------------------
	 */

	public function test_file_path_structure(): void {

		$path = $this->cache->file_path('mykey', 'mygroup');
		$this->assertStringContainsString('mygroup/', $path);
		$this->assertStringEndsWith('.php', $path);
		$this->assertStringContainsString(md5('mykey'), $path);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Isolation
	 * -------------------------------------------------------------------------
	 */

	public function test_independent_instances(): void {

		$dir2   = MC_TEST_TMP . 'v2_cache2/';
		mkdir($dir2, 0755, true);
		$cache2 = new MC_Cache($dir2);

		$this->cache->set('isolated', 'yes');
		$this->assertFalse($cache2->get('isolated'));

		// Cleanup.
		$cache2->flush();
		if (is_dir($dir2)) {
			rmdir($dir2);
		}
	}
}
