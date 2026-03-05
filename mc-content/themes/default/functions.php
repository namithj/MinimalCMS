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
