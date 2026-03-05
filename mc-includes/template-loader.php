<?php
/**
 * MinimalCMS Template Loader
 *
 * Resolves the correct template file from the active theme based on the
 * current query context (template hierarchy).
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * Determine and include the appropriate template for the current request.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_load_template(): void {

	global $mc_query;

	/**
	 * Fires before the template is determined.
	 *
	 * Plugins can use this to send redirects or override rendering.
	 *
	 * @since 1.0.0
	 */
	mc_do_action( 'mc_template_redirect' );

	// Build the template hierarchy (most specific → fallback).
	$templates = mc_get_template_hierarchy();

	/**
	 * Filter the template hierarchy candidates.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $templates Candidate template filenames.
	 */
	$templates = mc_apply_filters( 'mc_template_hierarchy', $templates );

	// Locate the first matching template file.
	$template = mc_locate_template( $templates );

	/**
	 * Filter the resolved template file path.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $template  Absolute path to the template, or empty.
	 * @param string[] $templates Candidate list that was searched.
	 */
	$template = mc_apply_filters( 'mc_template_include', $template, $templates );

	if ( '' !== $template && is_file( $template ) ) {
		include $template;
	} else {
		// Last resort: output a basic error.
		mc_send_404();
		echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Page Not Found</h1></body></html>';
	}
}

/**
 * Build the template hierarchy for the current query.
 *
 * @since 1.0.0
 *
 * @return string[] Ordered list of template filenames to search for.
 */
function mc_get_template_hierarchy(): array {

	global $mc_query;

	$templates = array();
	$type      = $mc_query['type'] ?? '';
	$slug      = $mc_query['slug'] ?? '';
	$content   = $mc_query['content'] ?? null;

	// Check for a custom template set in the content's metadata.
	if ( null !== $content && ! empty( $content['template'] ) ) {
		$templates[] = $content['template'];
	}

	if ( ! empty( $mc_query['is_404'] ) ) {
		$templates[] = '404.php';

	} elseif ( ! empty( $mc_query['is_front_page'] ) ) {
		$templates[] = 'front-page.php';
		if ( '' !== $slug ) {
			$templates[] = 'page-' . $slug . '.php';
		}
		$templates[] = 'page.php';

	} elseif ( ! empty( $mc_query['is_archive'] ) ) {
		if ( '' !== $type ) {
			$templates[] = 'archive-' . $type . '.php';
		}
		$templates[] = 'archive.php';

	} elseif ( ! empty( $mc_query['is_single'] ) ) {
		if ( 'page' === $type ) {
			if ( '' !== $slug ) {
				$templates[] = 'page-' . $slug . '.php';
			}
			$templates[] = 'page.php';
		} else {
			if ( '' !== $slug ) {
				$templates[] = 'single-' . $type . '-' . $slug . '.php';
			}
			$templates[] = 'single-' . $type . '.php';
		}
		$templates[] = 'single.php';
	}

	// Universal fallback.
	$templates[] = 'index.php';

	return $templates;
}

/**
 * Locate a template file from a list of candidates.
 *
 * Searches in order:
 *  1. Child theme directory (active theme)
 *  2. Parent theme directory (if child theme)
 *
 * @since 1.0.0
 *
 * @param string[] $templates Candidate filenames.
 * @return string Absolute path to the first matching file, or empty string.
 */
function mc_locate_template( array $templates ): string {

	$theme_dir  = mc_get_active_theme_dir();
	$parent_dir = mc_get_parent_theme_dir();

	foreach ( $templates as $tpl ) {
		// Child / active theme.
		if ( is_file( $theme_dir . $tpl ) ) {
			return $theme_dir . $tpl;
		}

		// Parent theme.
		if ( '' !== $parent_dir && is_file( $parent_dir . $tpl ) ) {
			return $parent_dir . $tpl;
		}
	}

	return '';
}
