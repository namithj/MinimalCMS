<?php
/**
 * MinimalCMS Bootstrap Loader
 *
 * Defines MC_ABSPATH, loads autoloaders and the procedural API, then boots
 * the application container. This is the single entry point for all
 * bootstrap logic — mc-settings.php is no longer used.
 *
 * @package MinimalCMS
 * @since   {version}
 */

if ( ! defined( 'MC_ABSPATH' ) ) {
	define( 'MC_ABSPATH', dirname( __FILE__ ) . '/' );
}

// Composer autoloader (third-party dependencies).
$mc_composer_autoload = MC_ABSPATH . 'mc-includes/vendor/autoload.php';
if ( is_file( $mc_composer_autoload ) ) {
	require_once $mc_composer_autoload;
}
unset( $mc_composer_autoload );

// PSR-0-style autoloader for MC_ classes in mc-includes/classes/.
require_once MC_ABSPATH . 'mc-includes/autoload.php';

// Procedural API — thin wrappers around MC_App services.
require_once MC_ABSPATH . 'mc-includes/functions.php';

// Boot the application container. All constants, environment config,
// plugins, and theme are initialised inside boot().
$GLOBALS['mc_app'] = MC_App::instance();
$GLOBALS['mc_app']->boot( MC_ABSPATH . 'config.json' );
