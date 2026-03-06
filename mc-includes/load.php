<?php

/**
 * MinimalCMS Load Helpers
 *
 * Utility functions for paths, constants, and environment setup.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Global configuration array populated by mc-load.php.
 *
 * @global array $mc_config
 */
global $mc_config;

/**
 * Read and decode the config.json file.
 *
 * @since 1.0.0
 *
 * @param string $path Absolute path to config.json.
 * @return array Configuration key-value pairs.
 */
function mc_load_config(string $path): array
{

	if (! is_readable($path)) {
		return array();
	}

	$raw = file_get_contents($path);
	if (false === $raw) {
		return array();
	}

	$data = json_decode($raw, true);
	if (! is_array($data)) {
		return array();
	}

	return $data;
}

/**
 * Define a constant if it has not been defined yet.
 *
 * @since 1.0.0
 *
 * @param string $name  Constant name.
 * @param mixed  $value Constant value.
 * @return void
 */
function mc_maybe_define(string $name, mixed $value): void
{

	if (! defined($name)) {
		define($name, $value);
	}
}

/**
 * Initialise core path constants from the config array.
 *
 * Called once during bootstrap in mc-load.php after config is read.
 *
 * @since 1.0.0
 *
 * @param array $config The parsed config.json data.
 * @return void
 */
function mc_initialise_constants(array $config): void
{

	mc_maybe_define('MC_INC', MC_ABSPATH . 'mc-includes/');

	$content_dir = $config['content_dir'] ?? 'mc-content';
	mc_maybe_define('MC_CONTENT_DIR', MC_ABSPATH . trailingslashit($content_dir));

	$data_dir = $config['data_dir'] ?? 'mc-data';
	mc_maybe_define('MC_DATA_DIR', MC_ABSPATH . trailingslashit($data_dir));

	mc_maybe_define('MC_PLUGIN_DIR', MC_CONTENT_DIR . 'plugins/');
	mc_maybe_define('MC_MU_PLUGIN_DIR', MC_CONTENT_DIR . 'mu-plugins/');
	mc_maybe_define('MC_THEME_DIR', MC_CONTENT_DIR . 'themes/');
	mc_maybe_define('MC_UPLOAD_DIR', MC_CONTENT_DIR . 'uploads/');
	mc_maybe_define('MC_CACHE_DIR', MC_CONTENT_DIR . 'cache/');
	mc_maybe_define('MC_SESSION_DIR', MC_DATA_DIR . 'sessions/');
	mc_maybe_define('MC_LOG_DIR', MC_DATA_DIR . 'logs/');

	mc_maybe_define('MC_SITE_URL', $config['site_url'] ?? '');
	mc_maybe_define('MC_SITE_NAME', $config['site_name'] ?? 'MinimalCMS Site');
	mc_maybe_define('MC_SITE_DESCRIPTION', $config['site_description'] ?? '');
	mc_maybe_define('MC_TIMEZONE', $config['timezone'] ?? 'UTC');
	mc_maybe_define('MC_DEBUG', (bool) ( $config['debug'] ?? false ));
	mc_maybe_define('MC_SECRET_KEY', $config['secret_key'] ?? '');
	mc_maybe_define('MC_ENCRYPTION_KEY', $config['encryption_key'] ?? '');
	mc_maybe_define('MC_FRONT_PAGE', $config['front_page'] ?? 'index');
	mc_maybe_define('MC_POSTS_PER_PAGE', (int) ( $config['posts_per_page'] ?? 10 ));
	mc_maybe_define('MC_PERMALINK_STRUCTURE', $config['permalink_structure'] ?? '/{type}/{slug}');
	mc_maybe_define('MC_ACTIVE_THEME', $config['active_theme'] ?? 'default');
}

/**
 * Append a trailing slash to a string if it does not already have one.
 *
 * @since 1.0.0
 *
 * @param string $value A file-system or URL path.
 * @return string The value with a trailing slash.
 */
function trailingslashit(string $value): string
{

	return rtrim($value, '/\\') . '/';
}

/**
 * Remove a trailing slash from a string.
 *
 * @since 1.0.0
 *
 * @param string $value A file-system or URL path.
 * @return string The value without a trailing slash.
 */
function untrailingslashit(string $value): string
{

	return rtrim($value, '/\\');
}

/**
 * Detect and return the base URL path for the CMS installation.
 *
 * Handles subdirectory installs by comparing SCRIPT_NAME to DOCUMENT_ROOT.
 *
 * @since 1.0.0
 *
 * @return string The base path, e.g. "/minimal" or "".
 */
function mc_detect_base_path(): string
{

	// If MC_ABSPATH and DOCUMENT_ROOT are available, compute the base path
	// from the filesystem relationship. This avoids issues when scripts in
	// subdirectories (e.g. mc-admin/) are accessed directly.
	if (defined('MC_ABSPATH') && ! empty($_SERVER['DOCUMENT_ROOT'])) {
		$doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
		$cms_root = realpath(MC_ABSPATH);

		if (false !== $doc_root && false !== $cms_root && str_starts_with($cms_root, $doc_root)) {
			$relative = substr($cms_root, strlen($doc_root));
			$relative = str_replace('\\', '/', $relative);
			return rtrim($relative, '/');
		}
	}

	$script = $_SERVER['SCRIPT_NAME'] ?? '';
	$dir    = dirname($script);

	if ('/' === $dir || '\\' === $dir) {
		return '';
	}

	return rtrim($dir, '/\\');
}

/**
 * Build the full site URL for a given path.
 *
 * @since 1.0.0
 *
 * @param string $path Optional relative path to append.
 * @return string Full URL.
 */
function mc_site_url(string $path = ''): string
{

	$base = untrailingslashit(MC_SITE_URL);
	if ('' === $path) {
		return $base . '/';
	}

	return $base . '/' . ltrim($path, '/');
}

/**
 * Build the URL to the mc-content directory.
 *
 * @since 1.0.0
 *
 * @param string $path Optional relative path to append.
 * @return string Full URL.
 */
function mc_content_url(string $path = ''): string
{

	return mc_site_url('mc-content/' . ltrim($path, '/'));
}

/**
 * Build the URL to the active theme directory.
 *
 * @since 1.0.0
 *
 * @param string $path Optional relative path within the theme.
 * @return string Full URL.
 */
function mc_theme_url(string $path = ''): string
{

	return mc_content_url('themes/' . MC_ACTIVE_THEME . '/' . ltrim($path, '/'));
}

/**
 * Build the URL to the admin area.
 *
 * @since 1.0.0
 *
 * @param string $path Optional relative path within mc-admin.
 * @return string Full URL.
 */
function mc_admin_url(string $path = ''): string
{

	return mc_site_url('mc-admin/' . ltrim($path, '/'));
}

/**
 * Get the absolute filesystem path to the active theme directory.
 *
 * @since 1.0.0
 *
 * @return string Directory path with trailing slash.
 */
function mc_get_active_theme_dir(): string
{

	return MC_THEME_DIR . MC_ACTIVE_THEME . '/';
}

/**
 * Check whether the current request targets the admin area.
 *
 * @since 1.0.0
 *
 * @return bool True if this is an admin request.
 */
function mc_is_admin_request(): bool
{

	$uri = $_SERVER['REQUEST_URI'] ?? '';
	return (bool) preg_match('#/mc-admin(/|$)#', $uri);
}

/**
 * Set the PHP timezone from configuration.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_set_timezone(): void
{

	date_default_timezone_set(MC_TIMEZONE);
}

/**
 * Configure error reporting based on MC_DEBUG constant.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_set_error_reporting(): void
{

	if (MC_DEBUG) {
		error_reporting(E_ALL);
		ini_set('display_errors', '1');
	} else {
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
		ini_set('display_errors', '0');
	}
}
