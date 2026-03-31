<?php

/**
 * MinimalCMS File Cache
 *
 * File-based object cache with a runtime memory layer.
 * Replaces the procedural cache.php with an encapsulated class.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_Cache
 *
 * Simple file-based object cache. Runtime memory avoids hitting disk
 * repeatedly within a single request. No global state.
 *
 * @since {version}
 */
class MC_Cache
{
	/**
	 * In-memory runtime cache.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $runtime = array();

	/**
	 * Filesystem directory for cache files.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $cache_dir;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param string $cache_dir Absolute path to the cache directory (with trailing slash).
	 */
	public function __construct(string $cache_dir)
	{

		$this->cache_dir = rtrim($cache_dir, '/') . '/';
	}

	/**
	 * Retrieve a cached value.
	 *
	 * Checks runtime memory first, then the filesystem cache.
	 *
	 * @since {version}
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return mixed Cached value or false if not found / expired.
	 */
	public function get(string $key, string $group = 'default'): mixed
	{

		$runtime_key = $group . ':' . $key;

		if (isset($this->runtime[$runtime_key])) {
			return $this->runtime[$runtime_key];
		}

		$file = $this->file_path($key, $group);

		if (!is_file($file)) {
			return false;
		}

		$data = @include $file;

		if (!is_array($data) || !isset($data['expires'], $data['value'])) {
			@unlink($file);
			return false;
		}

		if (0 !== $data['expires'] && time() > $data['expires']) {
			@unlink($file);
			return false;
		}

		$this->runtime[$runtime_key] = $data['value'];
		return $data['value'];
	}

	/**
	 * Store a value in the cache.
	 *
	 * @since {version}
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache (must be serialisable).
	 * @param string $group Cache group. Default 'default'.
	 * @param int    $ttl   Time-to-live in seconds. 0 = no expiry. Default 3600.
	 * @return bool True on success.
	 */
	public function set(string $key, mixed $value, string $group = 'default', int $ttl = 3600): bool
	{

		$runtime_key                  = $group . ':' . $key;
		$this->runtime[$runtime_key]  = $value;

		$file = $this->file_path($key, $group);
		$dir  = dirname($file);

		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$expires = (0 === $ttl) ? 0 : time() + $ttl;

		$content = '<?php return ' . var_export(
			array(
				'expires' => $expires,
				'value'   => $value,
			),
			true
		) . ';' . "\n";

		return false !== file_put_contents($file, $content, LOCK_EX);
	}

	/**
	 * Delete a single cache entry.
	 *
	 * @since {version}
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Default 'default'.
	 * @return bool True if deleted.
	 */
	public function delete(string $key, string $group = 'default'): bool
	{

		$runtime_key = $group . ':' . $key;
		unset($this->runtime[$runtime_key]);

		$file = $this->file_path($key, $group);

		if (is_file($file)) {
			return @unlink($file);
		}

		return false;
	}

	/**
	 * Flush the entire file cache or a specific group.
	 *
	 * @since {version}
	 *
	 * @param string $group Optional. Specific group to flush. Empty flushes all.
	 * @return void
	 */
	public function flush(string $group = ''): void
	{

		$this->runtime = array();

		if ('' !== $group) {
			$dir = $this->cache_dir . $this->sanitize_group($group);
		} else {
			$dir = $this->cache_dir;
		}

		if (is_dir($dir)) {
			$this->rmdir_recursive($dir);
			mkdir($dir, 0755, true);
		}
	}

	/**
	 * Build the filesystem path for a cache file.
	 *
	 * @since {version}
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return string Absolute file path.
	 */
	public function file_path(string $key, string $group): string
	{

		$safe_group = $this->sanitize_group($group);
		$hash       = md5($key);

		return $this->cache_dir . $safe_group . '/' . $hash . '.php';
	}

	/**
	 * Sanitise a cache group name for safe use as a directory name.
	 *
	 * @since {version}
	 *
	 * @param string $group Raw group name.
	 * @return string Safe directory name.
	 */
	private function sanitize_group(string $group): string
	{

		$group = preg_replace('/[^a-zA-Z0-9._\-]/', '-', $group);
		return trim($group, '.-');
	}

	/**
	 * Recursively remove a directory and its contents.
	 *
	 * @since {version}
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function rmdir_recursive(string $dir): void
	{

		if (!is_dir($dir)) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			if ($item->isDir()) {
				rmdir($item->getPathname());
			} else {
				unlink($item->getPathname());
			}
		}

		rmdir($dir);
	}
}
