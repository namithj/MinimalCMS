<?php
/**
 * MinimalCMS Bootstrap Loader
 *
 * Loads config.json, defines core constants, and hands off to mc-settings.php.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

// Define the absolute path to the MinimalCMS root directory.
if ( ! defined( 'MC_ABSPATH' ) ) {
	define( 'MC_ABSPATH', dirname( __FILE__ ) . '/' );
}

// Load Composer autoloader.
$autoloader = MC_ABSPATH . 'mc-includes/vendor/autoload.php';
if ( is_file( $autoloader ) ) {
	require_once $autoloader;
}

// Load the foundational helpers (path utilities, constant helpers).
require_once MC_ABSPATH . 'mc-includes/load.php';

// Load version constants.
require_once MC_ABSPATH . 'mc-includes/version.php';

// Read configuration. Fall back to the sample on a fresh install.
$config_path   = MC_ABSPATH . 'config.json';
$config_exists = is_file( $config_path );
if ( ! $config_exists ) {
	$config_path = MC_ABSPATH . 'config.sample.json';
}
$mc_config = mc_load_config( $config_path );

// On a fresh install (no config.json yet), redirect all front-end requests to /home.
if ( ! $config_exists ) {
	$base         = mc_detect_base_path();
	$home_url     = rtrim( $base, '/' ) . '/home';
	$request_uri  = $_SERVER['REQUEST_URI'] ?? '/';
	$request_path = strtok( $request_uri, '?' );

	if ( ! str_starts_with( $request_path, $home_url )
		&& false === strpos( $request_path, 'mc-admin' )
	) {
		header( 'Location: ' . $home_url, true, 302 );
		exit;
	}
}

// Auto-detect site URL if not set.
if ( empty( $mc_config['site_url'] ) ) {
	$scheme   = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
	$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$base     = mc_detect_base_path();
	$mc_config['site_url'] = $scheme . '://' . $host . $base;
}

// Initialise all core constants from config.
mc_initialise_constants( $mc_config );

// Configure environment.
mc_set_timezone();
mc_set_error_reporting();

// Load the full bootstrap sequence.
require_once MC_ABSPATH . 'mc-settings.php';
