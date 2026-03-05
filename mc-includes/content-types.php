<?php
/**
 * MinimalCMS Default Content Types
 *
 * Registers the built-in 'page' content type.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * Register core content types.
 *
 * Called during bootstrap before plugins have loaded so that the default
 * 'page' type is always available.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_create_initial_content_types(): void {

	mc_register_content_type(
		'page',
		array(
			'label'        => 'Pages',
			'singular'     => 'Page',
			'public'       => true,
			'hierarchical' => true,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => '' ), // Pages sit at the root URL.
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		)
	);
}
