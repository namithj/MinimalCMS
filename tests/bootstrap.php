<?php

/**
 * PHPUnit Bootstrap
 *
 * Sets up the test environment: defines constants, loads autoloaders,
 * and includes MinimalCMS core files needed by test cases.
 *
 * @package MinimalCMS\Tests
 */

// Prevent web access.
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
	die('Tests must be run from the CLI.');
}

// MinimalCMS root directory.
define('MC_ABSPATH', dirname(__DIR__) . '/');

// Composer autoloader (loads PHPUnit, Parsedown, and PSR-4 test namespaces).
require_once MC_ABSPATH . 'mc-includes/vendor/autoload.php';

// PSR-0-style autoloader for MC_ classes in mc-includes/classes/.
require_once MC_ABSPATH . 'mc-includes/autoload.php';

// Procedural API — provides mc_maybe_define(), mc_rmdir_recursive(), and
// all thin wrappers needed by test helpers. The functions are only defined
// here; no MC_App boot occurs in unit tests.
require_once MC_ABSPATH . 'mc-includes/functions.php';

// ── Temporary test data directories ─────────────────────────────────────────

$test_tmp = sys_get_temp_dir() . '/mc_test_' . getmypid() . '/';

if (! is_dir($test_tmp)) {
	mkdir($test_tmp, 0755, true);
}

define('MC_TEST_TMP', $test_tmp);

// ── Define core constants with test-safe values ─────────────────────────────

mc_maybe_define('MC_INC', MC_ABSPATH . 'mc-includes/');
mc_maybe_define('MC_CONTENT_DIR', MC_TEST_TMP . 'mc-content/');
mc_maybe_define('MC_DATA_DIR', MC_TEST_TMP . 'mc-data/');
mc_maybe_define('MC_PLUGIN_DIR', MC_TEST_TMP . 'mc-content/plugins/');
mc_maybe_define('MC_MU_PLUGIN_DIR', MC_TEST_TMP . 'mc-content/mu-plugins/');
mc_maybe_define('MC_THEME_DIR', MC_TEST_TMP . 'mc-content/themes/');
mc_maybe_define('MC_UPLOAD_DIR', MC_TEST_TMP . 'mc-content/uploads/');
mc_maybe_define('MC_CACHE_DIR', MC_TEST_TMP . 'mc-content/cache/');
mc_maybe_define('MC_SESSION_DIR', MC_TEST_TMP . 'mc-data/sessions/');
mc_maybe_define('MC_LOG_DIR', MC_TEST_TMP . 'mc-data/logs/');

mc_maybe_define('MC_SITE_URL', 'http://localhost/minimal');
mc_maybe_define('MC_SITE_NAME', 'Test Site');
mc_maybe_define('MC_SITE_DESCRIPTION', 'A test site.');
mc_maybe_define('MC_TIMEZONE', 'UTC');
mc_maybe_define('MC_DEBUG', true);
mc_maybe_define('MC_SECRET_KEY', 'test-secret-key-for-phpunit-only');
mc_maybe_define('MC_ENCRYPTION_KEY', 'test-encryption-key-for-phpunit-only');
mc_maybe_define('MC_FRONT_PAGE', 'index');
mc_maybe_define('MC_POSTS_PER_PAGE', 10);
mc_maybe_define('MC_PERMALINK_STRUCTURE', '/{type}/{slug}');
mc_maybe_define('MC_ACTIVE_THEME', 'default');

// Create required directories.
$dirs = array(
	MC_CONTENT_DIR,
	MC_DATA_DIR,
	MC_PLUGIN_DIR,
	MC_MU_PLUGIN_DIR,
	MC_THEME_DIR,
	MC_UPLOAD_DIR,
	MC_CACHE_DIR,
	MC_SESSION_DIR,
	MC_LOG_DIR,
);

foreach ($dirs as $dir) {
	if (! is_dir($dir)) {
		mkdir($dir, 0755, true);
	}
}

// ── Cleanup hook ────────────────────────────────────────────────────────────

register_shutdown_function(
	function () {
		$tmp = MC_TEST_TMP;
		if (is_dir($tmp)) {
			mc_rmdir_recursive($tmp);
		}
	}
);
