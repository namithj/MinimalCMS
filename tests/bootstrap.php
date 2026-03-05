<?php
/**
 * PHPUnit Bootstrap
 *
 * Sets up the test environment: defines constants, loads Composer autoloader,
 * and includes MinimalCMS core files needed by test cases.
 *
 * @package MinimalCMS\Tests
 */

// Prevent web access.
if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
	die( 'Tests must be run from the CLI.' );
}

// MinimalCMS root directory.
define( 'MC_ABSPATH', dirname( __DIR__ ) . '/' );

// Composer autoloader (loads PHPUnit, Parsedown, MC_Error class, and PSR-4 test namespaces).
require_once MC_ABSPATH . 'mc-includes/vendor/autoload.php';

// ── Load MinimalCMS core files ──────────────────────────────────────────────
// These files are guarded by `defined( 'MC_ABSPATH' ) || exit` so MC_ABSPATH
// must be defined before they can be loaded.
$mc_core_files = [
	'mc-includes/load.php',
	'mc-includes/hooks.php',
	'mc-includes/formatting.php',
	'mc-includes/http.php',
	'mc-includes/cache.php',
	'mc-includes/capabilities.php',
	'mc-includes/user.php',
	'mc-includes/content.php',
	'mc-includes/markdown.php',
	'mc-includes/rewrite.php',
	'mc-includes/plugin.php',
	'mc-includes/theme.php',
	'mc-includes/template-loader.php',
	'mc-includes/template-tags.php',
	'mc-includes/shortcodes.php',
];

foreach ( $mc_core_files as $file ) {
	require_once MC_ABSPATH . $file;
}

// ── Temporary test data directories ─────────────────────────────────────────

$test_tmp = sys_get_temp_dir() . '/mc_test_' . getmypid() . '/';

if ( ! is_dir( $test_tmp ) ) {
	mkdir( $test_tmp, 0755, true );
}

define( 'MC_TEST_TMP', $test_tmp );

// ── Define core constants with test-safe values ─────────────────────────────

// Define constants needed by the CMS.
mc_maybe_define( 'MC_INC', MC_ABSPATH . 'mc-includes/' );
mc_maybe_define( 'MC_CONTENT_DIR', MC_TEST_TMP . 'mc-content/' );
mc_maybe_define( 'MC_DATA_DIR', MC_TEST_TMP . 'mc-data/' );
mc_maybe_define( 'MC_PLUGIN_DIR', MC_TEST_TMP . 'mc-content/plugins/' );
mc_maybe_define( 'MC_MU_PLUGIN_DIR', MC_TEST_TMP . 'mc-content/mu-plugins/' );
mc_maybe_define( 'MC_THEME_DIR', MC_TEST_TMP . 'mc-content/themes/' );
mc_maybe_define( 'MC_UPLOAD_DIR', MC_TEST_TMP . 'mc-content/uploads/' );
mc_maybe_define( 'MC_CACHE_DIR', MC_TEST_TMP . 'mc-content/cache/' );
mc_maybe_define( 'MC_SESSION_DIR', MC_TEST_TMP . 'mc-data/sessions/' );
mc_maybe_define( 'MC_LOG_DIR', MC_TEST_TMP . 'mc-data/logs/' );

mc_maybe_define( 'MC_SITE_URL', 'http://localhost/minimal' );
mc_maybe_define( 'MC_SITE_NAME', 'Test Site' );
mc_maybe_define( 'MC_SITE_DESCRIPTION', 'A test site.' );
mc_maybe_define( 'MC_TIMEZONE', 'UTC' );
mc_maybe_define( 'MC_DEBUG', true );
mc_maybe_define( 'MC_SECRET_KEY', 'test-secret-key-for-phpunit-only' );
mc_maybe_define( 'MC_ENCRYPTION_KEY', 'test-encryption-key-for-phpunit-only' );
mc_maybe_define( 'MC_FRONT_PAGE', 'index' );
mc_maybe_define( 'MC_POSTS_PER_PAGE', 10 );
mc_maybe_define( 'MC_PERMALINK_STRUCTURE', '/{type}/{slug}' );
mc_maybe_define( 'MC_ACTIVE_THEME', 'default' );

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

foreach ( $dirs as $dir ) {
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0755, true );
	}
}

// ── Load remaining core files not covered by Composer "files" autoload ──────

// content-types.php and default-filters.php are not in the Composer autoload
// because they run registration logic. We load them for integration tests.
// For unit tests, individual test classes handle setup as needed.

// Initialise the roles system so capability tests work.
mc_initialise_roles();

// ── Cleanup hook ────────────────────────────────────────────────────────────

register_shutdown_function(
	function () {
		$tmp = MC_TEST_TMP;
		if ( is_dir( $tmp ) ) {
				mc_rmdir_recursive( $tmp );
		}
	}
);
