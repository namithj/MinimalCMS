<?php

/**
 * MC_File_Guard — PHP-guarded file I/O utility.
 *
 * Every data file in MinimalCMS uses a `<?php die('Access denied'); ?>`
 * header line so that direct HTTP requests execute the die() instead of
 * serving raw data. This class centralises reading and writing that format.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Static helper for reading/writing PHP-guarded data files.
 *
 * File format:
 *   Line 1: <?php die('Access denied'); ?>
 *   Line 2+: arbitrary body (typically JSON)
 *
 * @since {version}
 */
class MC_File_Guard
{
	/**
	 * The guard line prepended to every data file.
	 *
	 * @since {version}
	 * @var string
	 */
	public const GUARD = "<?php die('Access denied'); ?>";

	/**
	 * JSON encoding flags used throughout MinimalCMS.
	 *
	 * @since {version}
	 * @var int
	 */
	public const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

	/**
	 * Read a PHP-guarded file, stripping the guard line.
	 *
	 * @since {version}
	 *
	 * @param string $path Absolute path to the guarded file.
	 * @return string|false Raw body after the guard, or false on failure.
	 */
	public static function read(string $path): string|false
	{

		if (!is_file($path) || !is_readable($path)) {
			return false;
		}

		$raw = file_get_contents($path);

		if (false === $raw) {
			return false;
		}

		// Split at the first newline to separate guard from body.
		$parts = explode("\n", $raw, 2);

		if (count($parts) < 2) {
			return false;
		}

		return trim($parts[1]);
	}

	/**
	 * Write a PHP-guarded file.
	 *
	 * @since {version}
	 *
	 * @param string $path Absolute file path.
	 * @param string $body Content to write after the guard line.
	 * @return bool True on success.
	 */
	public static function write(string $path, string $body): bool
	{

		$dir = dirname($path);

		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$content = self::GUARD . "\n" . $body . "\n";

		return false !== file_put_contents($path, $content, LOCK_EX);
	}

	/**
	 * Read and JSON-decode a PHP-guarded file.
	 *
	 * @since {version}
	 *
	 * @param string $path Absolute file path.
	 * @return array|null Decoded data, or null on failure.
	 */
	public static function read_json(string $path): ?array
	{

		$body = self::read($path);

		if (false === $body || '' === $body) {
			return null;
		}

		$data = json_decode($body, true);

		return is_array($data) ? $data : null;
	}

	/**
	 * JSON-encode and write a PHP-guarded file.
	 *
	 * @since {version}
	 *
	 * @param string $path Absolute file path.
	 * @param array  $data Data to encode.
	 * @return bool True on success.
	 */
	public static function write_json(string $path, array $data): bool
	{

		$json = json_encode($data, self::JSON_FLAGS);

		if (false === $json) {
			return false;
		}

		return self::write($path, $json);
	}
}
