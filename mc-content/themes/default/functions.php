<?php
/**
 * Default Theme Functions
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * Enqueue the default theme stylesheet.
 *
 * @since 1.0.0
 *
 * @return void
 */
function default_theme_enqueue_assets(): void {

	mc_enqueue_style( 'default-style', mc_theme_url( 'style.css' ) );
}
mc_add_action( 'mc_init', 'default_theme_enqueue_assets' );

/**
 * Add "Theme Sections" to the admin sidebar when the active theme has templates
 * that declare global sections.
 *
 * @since {version}
 *
 * @return void
 */
function default_theme_admin_menu(): void {

	$templates = mc_get_page_templates();
	$has_sections = false;
	foreach ( $templates as $tpl ) {
		if ( ! empty( $tpl['global_sections'] ) ) {
			$has_sections = true;
			break;
		}
	}

	if ( ! $has_sections ) {
		return;
	}

	global $mc_admin_menu;
	$mc_admin_menu[] = array(
		'slug'       => 'themes-sections',
		'title'      => 'Theme Sections',
		'url'        => mc_admin_url( 'template-sections.php' ),
		'capability' => 'manage_themes',
		'parent'     => 'themes',
	);
}
mc_add_action( 'mc_admin_menu', 'default_theme_admin_menu' );

/**
 * Get published pages for navigation.
 *
 * @since 1.0.0
 *
 * @return array List of page content arrays.
 */
function default_theme_get_nav_pages(): array {

	return mc_query_content( array(
		'type'     => 'page',
		'status'   => 'publish',
		'order_by' => 'order',
		'order'    => 'ASC',
		'limit'    => 20,
	) );
}
