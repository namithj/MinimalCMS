<?php
/**
 * MinimalCMS Theme System
 *
 * Handles theme discovery, loading, and child theme support.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * All discovered themes keyed by directory name.
 *
 * @global array $mc_themes
 */
global $mc_themes;
$mc_themes = array();

/*
 * -------------------------------------------------------------------------
 *  Theme discovery
 * -------------------------------------------------------------------------
 */

/**
 * Parse theme metadata from theme.json.
 *
 * @since 1.0.0
 *
 * @param string $dir Absolute path to the theme directory (with trailing slash).
 * @return array Theme metadata.
 */
function mc_get_theme_data( string $dir ): array {

	$defaults = array(
		'name'        => basename( rtrim( $dir, '/' ) ),
		'version'     => '1.0.0',
		'author'      => '',
		'description' => '',
		'requires_mc' => '',
		'license'     => '',
		'template'    => '', // Parent theme slug (for child themes).
		'text_domain' => '',
	);

	$manifest = $dir . 'theme.json';

	if ( ! is_file( $manifest ) ) {
		return $defaults;
	}

	$raw  = file_get_contents( $manifest );
	$data = json_decode( $raw, true );

	if ( ! is_array( $data ) ) {
		return $defaults;
	}

	return array_merge( $defaults, $data );
}

/**
 * Discover all themes in the themes directory.
 *
 * A valid theme is a subfolder containing a theme.json file.
 *
 * @since 1.0.0
 *
 * @return array Associative array of slug => metadata.
 */
function mc_discover_themes(): array {

	$dir    = MC_THEME_DIR;
	$themes = array();

	if ( ! is_dir( $dir ) ) {
		return $themes;
	}

	$entries = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $entries as $entry ) {
		$theme_dir = $dir . $entry . '/';

		if ( ! is_dir( $theme_dir ) ) {
			continue;
		}

		if ( ! is_file( $theme_dir . 'theme.json' ) && ! is_file( $theme_dir . 'style.css' ) ) {
			continue;
		}

		$themes[ $entry ] = mc_get_theme_data( $theme_dir );
	}

	return $themes;
}

/*
 * -------------------------------------------------------------------------
 *  Theme loading
 * -------------------------------------------------------------------------
 */

/**
 * Load the active theme's functions.php and parent theme if applicable.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_load_theme(): void {

	global $mc_themes;

	$active_slug = MC_ACTIVE_THEME;
	$theme_dir   = MC_THEME_DIR . $active_slug . '/';

	if ( ! is_dir( $theme_dir ) ) {
		return;
	}

	$theme_data                = mc_get_theme_data( $theme_dir );
	$mc_themes[ $active_slug ] = $theme_data;

	// If child theme, load parent first.
	$parent_slug = $theme_data['template'] ?? '';

	if ( '' !== $parent_slug && $parent_slug !== $active_slug ) {
		$parent_dir = MC_THEME_DIR . $parent_slug . '/';

		if ( is_dir( $parent_dir ) ) {
			$mc_themes[ $parent_slug ] = mc_get_theme_data( $parent_dir );

			$parent_functions = $parent_dir . 'functions.php';
			if ( is_file( $parent_functions ) ) {
				include_once $parent_functions;
			}
		}
	}

	// Load child (or standalone) theme functions.
	$functions = $theme_dir . 'functions.php';
	if ( is_file( $functions ) ) {
		include_once $functions;
	}

	/**
	 * Fires after the active theme has been loaded.
	 *
	 * @since 1.0.0
	 *
	 * @param string $active_slug Active theme slug.
	 */
	mc_do_action( 'mc_after_setup_theme', $active_slug );
}

/**
 * Get the active theme's metadata.
 *
 * @since 1.0.0
 *
 * @return array Theme metadata.
 */
function mc_get_active_theme(): array {

	global $mc_themes;
	return $mc_themes[ MC_ACTIVE_THEME ] ?? array();
}

/**
 * Get the parent theme directory path (for child themes).
 *
 * @since 1.0.0
 *
 * @return string Directory path or empty string if no parent.
 */
function mc_get_parent_theme_dir(): string {

	$theme_data  = mc_get_active_theme();
	$parent_slug = $theme_data['template'] ?? '';

	if ( '' === $parent_slug || $parent_slug === MC_ACTIVE_THEME ) {
		return '';
	}

	return MC_THEME_DIR . $parent_slug . '/';
}

/**
 * Switch the active theme.
 *
 * @since 1.0.0
 *
 * @param string $slug Theme directory name to switch to.
 * @return true|MC_Error True on success.
 */
function mc_switch_theme( string $slug ): true|MC_Error {

	global $mc_config;

	$theme_dir = MC_THEME_DIR . $slug . '/';

	if ( ! is_dir( $theme_dir ) ) {
		return new MC_Error( 'not_found', 'Theme not found.' );
	}

	$mc_config['active_theme'] = $slug;
	mc_save_config( $mc_config );

	mc_do_action( 'mc_switch_theme', $slug );

	return true;
}
