<?php
/**
 * Plugin Name: Posts
 * Description: Registers a "Posts" content type for blog-style articles with archive support.
 * Version:     1.0.0
 * Author:      MinimalCMS
 */

defined( 'MC_ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 *  Register the "post" content type
 * -------------------------------------------------------------------------
 */

/**
 * Register the post content type on init.
 *
 * Posts live at /post/{slug} and have an archive listing at /post/.
 *
 * @since 1.0.0
 * @return void
 */
function posts_register_content_type(): void {

	mc_register_content_type(
		'post',
		array(
			'label'        => 'Posts',
			'singular'     => 'Post',
			'public'       => true,
			'hierarchical' => false,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'post' ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		)
	);
}
mc_add_action( 'mc_init', 'posts_register_content_type' );

/*
 * -------------------------------------------------------------------------
 *  Admin menu entry
 * -------------------------------------------------------------------------
 */

/**
 * Add a "Posts" link to the admin sidebar.
 *
 * @since 1.0.0
 * @return void
 */
function posts_admin_menu(): void {

	global $mc_admin_menu;

	// Insert after "Pages" (index 1) so it appears right below.
	$entry = array(
		'title'      => 'Posts',
		'url'        => mc_admin_url( 'pages.php?type=post' ),
		'capability' => 'edit_content',
		'icon'       => '&#x1F4DD;',
	);

	// Find the Pages item position and insert after it.
	$insert_at = 2; // default: after Pages.
	foreach ( $mc_admin_menu as $i => $item ) {
		if ( str_contains( $item['url'] ?? '', 'pages.php' ) && ! str_contains( $item['url'] ?? '', 'type=' ) ) {
			$insert_at = $i + 1;
			break;
		}
	}

	array_splice( $mc_admin_menu, $insert_at, 0, array( $entry ) );
}
mc_add_action( 'mc_admin_menu', 'posts_admin_menu' );

/*
 * -------------------------------------------------------------------------
 *  Seed a sample "Hello World" post on activation
 * -------------------------------------------------------------------------
 */

/**
 * Create an initial "Hello World" post if none exist yet.
 *
 * Called once when the plugin is first activated.
 *
 * @since 1.0.0
 * @return void
 */
function posts_maybe_seed_sample(): void {

	// Only seed if there are zero posts.
	$existing = mc_query_content( array( 'type' => 'post' ) );
	if ( ! empty( $existing ) ) {
		return;
	}

	$meta = array(
		'title'   => 'Hello World',
		'slug'    => 'hello-world',
		'status'  => 'publish',
		'author'  => mc_get_current_user()['username'] ?? 'admin',
		'excerpt' => 'Welcome to MinimalCMS. This is your first post — edit or delete it, then start writing!',
	);

	$body = <<<'MD'
Welcome to **MinimalCMS**! This is your very first post. You can edit or delete it from the admin panel, then start creating your own content.

## Getting Started

- Head to the **Admin → Posts** page to manage your posts.
- Click **+ Add New** to write a new article.
- Posts are stored as Markdown files — no database required.

Happy writing! 🎉
MD;

	mc_save_content( 'post', 'hello-world', $meta, $body );
}
mc_add_action( 'mc_init', 'posts_maybe_seed_sample', 20 );
