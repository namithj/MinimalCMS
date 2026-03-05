<?php
/**
 * MinimalCMS Template Tags
 *
 * Helper functions available in theme templates.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 *  Head / Footer hooks
 * -------------------------------------------------------------------------
 */

/**
 * Fire the mc_head action (themes call this inside <head>).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_head(): void {

	mc_do_action( 'mc_head' );
}

/**
 * Fire the mc_footer action (themes call this before </body>).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_footer(): void {

	mc_do_action( 'mc_footer' );
}

/*
 * -------------------------------------------------------------------------
 *  Asset enqueuing
 * -------------------------------------------------------------------------
 */

/**
 * Enqueued styles and scripts.
 *
 * @global array $mc_enqueued_styles
 * @global array $mc_enqueued_scripts
 */
global $mc_enqueued_styles, $mc_enqueued_scripts;
$mc_enqueued_styles  = array();
$mc_enqueued_scripts = array();

/**
 * Enqueue a CSS stylesheet.
 *
 * @since 1.0.0
 *
 * @param string $handle Unique handle name.
 * @param string $src    URL to the CSS file.
 * @param string $media  Media attribute. Default 'all'.
 * @return void
 */
function mc_enqueue_style( string $handle, string $src, string $media = 'all' ): void {

	global $mc_enqueued_styles;

	$mc_enqueued_styles[ $handle ] = array(
		'src'   => $src,
		'media' => $media,
	);
}

/**
 * Enqueue a JavaScript file.
 *
 * @since 1.0.0
 *
 * @param string $handle    Unique handle name.
 * @param string $src       URL to the JS file.
 * @param bool   $in_footer Whether to output in footer. Default true.
 * @return void
 */
function mc_enqueue_script( string $handle, string $src, bool $in_footer = true ): void {

	global $mc_enqueued_scripts;

	$mc_enqueued_scripts[ $handle ] = array(
		'src'       => $src,
		'in_footer' => $in_footer,
	);
}

/**
 * Output all enqueued stylesheets (called on mc_head).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_print_styles(): void {

	global $mc_enqueued_styles;

	foreach ( $mc_enqueued_styles as $handle => $style ) {
		printf(
			'<link rel="stylesheet" id="%s-css" href="%s" media="%s" />' . "\n",
			mc_esc_attr( $handle ),
			mc_esc_url( $style['src'] ),
			mc_esc_attr( $style['media'] )
		);
	}
}

/**
 * Output header scripts (those not deferred to footer).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_print_head_scripts(): void {

	global $mc_enqueued_scripts;

	foreach ( $mc_enqueued_scripts as $handle => $script ) {
		if ( ! $script['in_footer'] ) {
			printf(
				'<script id="%s-js" src="%s"></script>' . "\n",
				mc_esc_attr( $handle ),
				mc_esc_url( $script['src'] )
			);
		}
	}
}

/**
 * Output footer scripts.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_print_footer_scripts(): void {

	global $mc_enqueued_scripts;

	foreach ( $mc_enqueued_scripts as $handle => $script ) {
		if ( $script['in_footer'] ) {
			printf(
				'<script id="%s-js" src="%s"></script>' . "\n",
				mc_esc_attr( $handle ),
				mc_esc_url( $script['src'] )
			);
		}
	}
}

/*
 * -------------------------------------------------------------------------
 *  Content output
 * -------------------------------------------------------------------------
 */

/**
 * Get the current content item.
 *
 * @since 1.0.0
 *
 * @return array|null
 */
function mc_get_the_content_item(): ?array {

	global $mc_query;
	return $mc_query['content'] ?? null;
}

/**
 * Output the page/content title.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_the_title(): void {

	$content = mc_get_the_content_item();
	$title   = $content['title'] ?? '';

	echo mc_esc_html( mc_apply_filters( 'mc_the_title', $title ) );
}

/**
 * Get the page/content title without echoing.
 *
 * @since 1.0.0
 *
 * @return string
 */
function mc_get_the_title(): string {

	$content = mc_get_the_content_item();
	$title   = $content['title'] ?? '';

	return mc_apply_filters( 'mc_the_title', $title );
}

/**
 * Output the rendered content body (Markdown → HTML).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_the_content(): void {

	$content = mc_get_the_content_item();
	$raw     = $content['body_raw'] ?? '';
	$html    = mc_parse_markdown( $raw );

	/**
	 * Filter the content HTML before output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html    Rendered HTML.
	 * @param array  $content Full content array.
	 */
	echo mc_apply_filters( 'mc_the_content', $html, $content );
}

/**
 * Get the content HTML without echoing.
 *
 * @since 1.0.0
 *
 * @return string
 */
function mc_get_the_content(): string {

	$content = mc_get_the_content_item();
	$raw     = $content['body_raw'] ?? '';
	$html    = mc_parse_markdown( $raw );

	return mc_apply_filters( 'mc_the_content', $html, $content );
}

/**
 * Output the content excerpt.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_the_excerpt(): void {

	$content = mc_get_the_content_item();
	$excerpt = $content['excerpt'] ?? '';

	if ( '' === $excerpt ) {
		$excerpt = mc_truncate( strip_tags( mc_get_the_content() ) );
	}

	echo mc_esc_html( mc_apply_filters( 'mc_the_excerpt', $excerpt ) );
}

/**
 * Output the document title (for <title> tag).
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_document_title(): void {

	$parts = array();

	if ( mc_is_404() ) {
		$parts[] = 'Page Not Found';
	} elseif ( mc_is_single() || mc_is_front_page() ) {
		$parts[] = mc_get_the_title();
	} elseif ( mc_is_archive() ) {
		global $mc_query;
		$type    = mc_get_content_type( $mc_query['type'] ?? '' );
		$parts[] = $type['label'] ?? 'Archive';
	}

	$parts[] = MC_SITE_NAME;

	$title = implode( ' — ', array_filter( $parts ) );

	/**
	 * Filter the document <title>.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The full title string.
	 */
	echo mc_esc_html( mc_apply_filters( 'mc_document_title', $title ) );
}

/*
 * -------------------------------------------------------------------------
 *  Template partials
 * -------------------------------------------------------------------------
 */

/**
 * Include the header template partial.
 *
 * @since 1.0.0
 *
 * @param string $name Optional. Specialised header name (header-{name}.php).
 * @return void
 */
function mc_get_header( string $name = '' ): void {

	mc_do_action( 'mc_get_header', $name );

	$templates = array();
	if ( '' !== $name ) {
		$templates[] = 'header-' . $name . '.php';
	}
	$templates[] = 'header.php';

	$file = mc_locate_template( $templates );

	if ( '' !== $file ) {
		include $file;
	}
}

/**
 * Include the footer template partial.
 *
 * @since 1.0.0
 *
 * @param string $name Optional. Specialised footer name.
 * @return void
 */
function mc_get_footer( string $name = '' ): void {

	mc_do_action( 'mc_get_footer', $name );

	$templates = array();
	if ( '' !== $name ) {
		$templates[] = 'footer-' . $name . '.php';
	}
	$templates[] = 'footer.php';

	$file = mc_locate_template( $templates );

	if ( '' !== $file ) {
		include $file;
	}
}

/**
 * Include the sidebar template partial.
 *
 * @since 1.0.0
 *
 * @param string $name Optional. Specialised sidebar name.
 * @return void
 */
function mc_get_sidebar( string $name = '' ): void {

	mc_do_action( 'mc_get_sidebar', $name );

	$templates = array();
	if ( '' !== $name ) {
		$templates[] = 'sidebar-' . $name . '.php';
	}
	$templates[] = 'sidebar.php';

	$file = mc_locate_template( $templates );

	if ( '' !== $file ) {
		include $file;
	}
}

/**
 * Include a generic template part.
 *
 * @since 1.0.0
 *
 * @param string $slug The template slug (e.g. 'content').
 * @param string $name Optional specialisation (e.g. 'page' → content-page.php).
 * @return void
 */
function mc_get_template_part( string $slug, string $name = '' ): void {

	$templates = array();
	if ( '' !== $name ) {
		$templates[] = $slug . '-' . $name . '.php';
	}
	$templates[] = $slug . '.php';

	$file = mc_locate_template( $templates );

	if ( '' !== $file ) {
		include $file;
	}
}

/*
 * -------------------------------------------------------------------------
 *  Body class
 * -------------------------------------------------------------------------
 */

/**
 * Output contextual CSS classes for the <body> tag.
 *
 * @since 1.0.0
 *
 * @param string $extra Optional extra classes to append.
 * @return void
 */
function mc_body_class( string $extra = '' ): void {

	$classes = array();

	if ( mc_is_front_page() ) {
		$classes[] = 'home';
		$classes[] = 'front-page';
	}

	if ( mc_is_single() ) {
		global $mc_query;
		$classes[] = 'single';
		$classes[] = 'single-' . ( $mc_query['type'] ?? 'page' );
		if ( ! empty( $mc_query['slug'] ) ) {
			$classes[] = 'slug-' . $mc_query['slug'];
		}
	}

	if ( mc_is_archive() ) {
		global $mc_query;
		$classes[] = 'archive';
		$classes[] = 'archive-' . ( $mc_query['type'] ?? '' );
	}

	if ( mc_is_404() ) {
		$classes[] = 'error404';
	}

	if ( mc_is_logged_in() ) {
		$classes[] = 'logged-in';
	}

	if ( '' !== $extra ) {
		$classes[] = $extra;
	}

	/**
	 * Filter body CSS classes.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $classes CSS class names.
	 */
	$classes = mc_apply_filters( 'mc_body_class', $classes );

	echo 'class="' . mc_esc_attr( implode( ' ', $classes ) ) . '"';
}

/*
 * -------------------------------------------------------------------------
 *  Miscellaneous helpers
 * -------------------------------------------------------------------------
 */

/**
 * Get the URL for a content item.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string Full URL.
 */
function mc_get_content_permalink( string $type, string $slug ): string {

	$type_def     = mc_get_content_type( $type );
	$rewrite_slug = $type_def['rewrite']['slug'] ?? $type;

	if ( '' === $rewrite_slug ) {
		// Pages sit at root.
		return mc_site_url( $slug );
	}

	return mc_site_url( $rewrite_slug . '/' . $slug );
}

/**
 * Output the permalink for the current content item.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_the_permalink(): void {

	global $mc_query;
	$type = $mc_query['type'] ?? 'page';
	$slug = $mc_query['slug'] ?? '';

	echo mc_esc_url( mc_get_content_permalink( $type, $slug ) );
}

/**
 * Get the featured image URL for the current content item.
 *
 * @since 1.0.0
 *
 * @return string URL or empty string.
 */
function mc_get_featured_image(): string {

	$content = mc_get_the_content_item();
	$image   = $content['featured_image'] ?? '';

	if ( '' === $image ) {
		return '';
	}

	// If it's a relative path, prepend the uploads URL.
	if ( ! str_starts_with( $image, 'http' ) ) {
		return mc_content_url( 'uploads/' . ltrim( $image, '/' ) );
	}

	return $image;
}
