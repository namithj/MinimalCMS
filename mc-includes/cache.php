<?php
/**
 * MinimalCMS File Cache
 *
 * Simple file-based object cache to avoid repeated filesystem scans.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * In-memory runtime cache to avoid hitting disk repeatedly within a request.
 *
 * @global array $mc_runtime_cache
 */
global $mc_runtime_cache;
$mc_runtime_cache = array();

/**
 * Retrieve a cached value.
 *
 * Checks runtime memory first, then the filesystem cache.
 *
 * @since 1.0.0
 *
 * @param string $key   Cache key.
 * @param string $group Cache group. Default 'default'.
 * @return mixed Cached value or false if not found / expired.
 */
function mc_cache_get( string $key, string $group = 'default' ): mixed {

	global $mc_runtime_cache;

	$runtime_key = $group . ':' . $key;

	if ( isset( $mc_runtime_cache[ $runtime_key ] ) ) {
		return $mc_runtime_cache[ $runtime_key ];
	}

	$file = mc_cache_file_path( $key, $group );

	if ( ! is_file( $file ) ) {
		return false;
	}

	$data = @include $file;

	if ( ! is_array( $data ) || ! isset( $data['expires'], $data['value'] ) ) {
		@unlink( $file );
		return false;
	}

	// Check expiration (0 means no expiry).
	if ( 0 !== $data['expires'] && time() > $data['expires'] ) {
		@unlink( $file );
		return false;
	}

	$mc_runtime_cache[ $runtime_key ] = $data['value'];
	return $data['value'];
}

/**
 * Store a value in the cache.
 *
 * @since 1.0.0
 *
 * @param string $key   Cache key.
 * @param mixed  $value Value to cache (must be serialisable).
 * @param string $group Cache group. Default 'default'.
 * @param int    $ttl   Time-to-live in seconds. 0 = no expiry. Default 3600.
 * @return bool True on success.
 */
function mc_cache_set( string $key, mixed $value, string $group = 'default', int $ttl = 3600 ): bool {

	global $mc_runtime_cache;

	$runtime_key                      = $group . ':' . $key;
	$mc_runtime_cache[ $runtime_key ] = $value;

	$file = mc_cache_file_path( $key, $group );
	$dir  = dirname( $file );

	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}

	$expires = ( 0 === $ttl ) ? 0 : time() + $ttl;

	$content = '<?php return ' . var_export(
		array(
			'expires' => $expires,
			'value'   => $value,
		),
		true
	) . ';' . "\n";

	return false !== file_put_contents( $file, $content, LOCK_EX );
}

/**
 * Delete a single cache entry.
 *
 * @since 1.0.0
 *
 * @param string $key   Cache key.
 * @param string $group Cache group. Default 'default'.
 * @return bool True if deleted.
 */
function mc_cache_delete( string $key, string $group = 'default' ): bool {

	global $mc_runtime_cache;

	$runtime_key = $group . ':' . $key;
	unset( $mc_runtime_cache[ $runtime_key ] );

	$file = mc_cache_file_path( $key, $group );

	if ( is_file( $file ) ) {
		return @unlink( $file );
	}

	return false;
}

/**
 * Flush the entire file cache or a specific group.
 *
 * @since 1.0.0
 *
 * @param string $group Optional. Specific group to flush. Empty flushes all.
 * @return void
 */
function mc_cache_flush( string $group = '' ): void {

	global $mc_runtime_cache;
	$mc_runtime_cache = array();

	if ( '' !== $group ) {
		$dir = MC_CACHE_DIR . mc_sanitize_filename( $group );
	} else {
		$dir = MC_CACHE_DIR;
	}

	if ( is_dir( $dir ) ) {
		mc_rmdir_recursive( $dir );
		mkdir( $dir, 0755, true );
	}
}

/**
 * Build the filesystem path for a cache file.
 *
 * @since 1.0.0
 *
 * @param string $key   Cache key.
 * @param string $group Cache group.
 * @return string Absolute file path.
 */
function mc_cache_file_path( string $key, string $group ): string {

	$safe_group = mc_sanitize_filename( $group );
	$hash       = md5( $key );

	return MC_CACHE_DIR . $safe_group . '/' . $hash . '.php';
}

/**
 * Recursively remove a directory and its contents.
 *
 * @since 1.0.0
 *
 * @param string $dir Directory path.
 * @return void
 */
function mc_rmdir_recursive( string $dir ): void {

	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
		\RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	rmdir( $dir );
}
