<?php

/**
 * MinimalCMS Routing / Rewrite System
 *
 * Parses the incoming URL and resolves it to a content item or route handler.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Custom routes registered by plugins.
 *
 * Structure: $mc_routes[] = array(
 *     'pattern'  => string (regex),
 *     'callback' => callable,
 *     'priority' => int,
 * )
 *
 * @global array $mc_routes
 */
global $mc_routes;
$mc_routes = array();

/**
 * The resolved query data for the current request.
 *
 * @global array $mc_query
 */
global $mc_query;
$mc_query = array();

/*
 * -------------------------------------------------------------------------
 *  Route registration
 * -------------------------------------------------------------------------
 */

/**
 * Register a custom route.
 *
 * @since 1.0.0
 *
 * @param string   $pattern  Regex pattern (without delimiters) to match against the request path.
 * @param callable $callback Handler function. Receives regex matches as first argument.
 * @param int      $priority Lower = matched first. Default 10.
 * @return void
 */
function mc_add_route(string $pattern, callable $callback, int $priority = 10): void
{

	global $mc_routes;

	$mc_routes[] = array(
		'pattern'  => $pattern,
		'callback' => $callback,
		'priority' => $priority,
	);
}

/*
 * -------------------------------------------------------------------------
 *  Request parsing
 * -------------------------------------------------------------------------
 */

/**
 * Extract the request path relative to the CMS installation.
 *
 * @since 1.0.0
 *
 * @return string Cleaned path, e.g. "about" or "docs/getting-started".
 */
function mc_get_request_path(): string
{

	$uri = $_SERVER['REQUEST_URI'] ?? '/';

	// Strip query string.
	$path = strtok($uri, '?');

	// Remove the base path (subdirectory install support).
	$base = mc_detect_base_path();
	if ('' !== $base && str_starts_with($path, $base)) {
		$path = substr($path, strlen($base));
	}

	$path = trim($path, '/');

	return $path;
}

/**
 * Parse the incoming request and populate the global $mc_query.
 *
 * Resolution order:
 *  1. Admin routes (/mc-admin/*)
 *  2. Custom routes registered by plugins (sorted by priority)
 *  3. Content type archives ({type}/)
 *  4. Content items ({type}/{slug} or {page-slug} for pages)
 *  5. Front page (empty path maps to configured front_page)
 *  6. 404
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_parse_request(): void
{

	global $mc_query, $mc_routes;

	$path = mc_get_request_path();

	/**
	 * Filter the request path before routing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Cleaned request path.
	 */
	$path = mc_apply_filters('mc_request_path', $path);

	$mc_query = array(
		'path'          => $path,
		'type'          => '',
		'slug'          => '',
		'content'       => null,
		'is_front_page' => false,
		'is_archive'    => false,
		'is_single'     => false,
		'is_404'        => false,
		'is_admin'      => false,
		'page_num'      => 1,
		'archive_items' => array(),
	);

	// 1. Admin route — handled separately.
	if (str_starts_with($path, 'mc-admin')) {
		$mc_query['is_admin'] = true;
		return;
	}

	// 2. Custom routes (plugin-registered).
	if (! empty($mc_routes)) {
		// Sort by priority.
		usort($mc_routes, fn($a, $b) => $a['priority'] <=> $b['priority']);

		foreach ($mc_routes as $route) {
			if (preg_match('#^' . $route['pattern'] . '$#', $path, $matches)) {
				call_user_func($route['callback'], $matches);
				return;
			}
		}
	}

	// 3–5. Content resolution.
	mc_resolve_content_route($path);
}

/**
 * Resolve a URL path to a content item or archive.
 *
 * @since 1.0.0
 *
 * @param string $path The request path.
 * @return void
 */
function mc_resolve_content_route(string $path): void
{

	global $mc_query;

	$content_types = mc_get_content_types();

	// Handle pagination suffix: strip /page/N from the end.
	if (preg_match('#^(.+)/page/(\d+)$#', $path, $page_match)) {
		$path                 = $page_match[1];
		$mc_query['page_num'] = max(1, (int) $page_match[2]);
	} elseif (preg_match('#^page/(\d+)$#', $path, $page_match)) {
		$path                 = '';
		$mc_query['page_num'] = max(1, (int) $page_match[1]);
	}

	// Empty path → front page.
	if ('' === $path) {
		$front_slug = MC_FRONT_PAGE;
		$item       = mc_get_content('page', $front_slug);

		if (null !== $item && 'publish' === ( $item['status'] ?? '' )) {
			$mc_query['type']          = 'page';
			$mc_query['slug']          = $front_slug;
			$mc_query['content']       = $item;
			$mc_query['is_front_page'] = true;
			$mc_query['is_single']     = true;
			return;
		}

		$mc_query['is_404'] = true;
		return;
	}

	// Check custom content types: {type-slug}/{item-slug} or {type-slug}/ (archive).
	foreach ($content_types as $type_slug => $type_def) {
		if ('page' === $type_slug) {
			continue; // Pages are handled below (root-level URLs).
		}

		// Skip non-public types — they are not directly accessible via URL.
		if (empty($type_def['public'])) {
			continue;
		}

		$rewrite_slug = $type_def['rewrite']['slug'] ?? $type_slug;

		// Archive: exact match on type slug.
		if ($path === $rewrite_slug && ! empty($type_def['has_archive'])) {
			$mc_query['type']       = $type_slug;
			$mc_query['is_archive'] = true;

			$offset = ( $mc_query['page_num'] - 1 ) * MC_POSTS_PER_PAGE;

			$mc_query['archive_items'] = mc_query_content(
				array(
					'type'   => $type_slug,
					'status' => 'publish',
					'offset' => $offset,
				)
			);
			return;
		}

		// Single item: {type-slug}/{item-slug}.
		if (str_starts_with($path, $rewrite_slug . '/')) {
			$item_slug = substr($path, strlen($rewrite_slug) + 1);
			$item      = mc_get_content($type_slug, $item_slug);

			if (null !== $item && 'publish' === ( $item['status'] ?? '' )) {
				$mc_query['type']      = $type_slug;
				$mc_query['slug']      = $item_slug;
				$mc_query['content']   = $item;
				$mc_query['is_single'] = true;
				return;
			}
		}
	}

	// Pages: root-level URL. Support hierarchical slugs (parent/child).
	$page_slug = $path;
	$item      = mc_get_content('page', $page_slug);

	if (null !== $item && 'publish' === ( $item['status'] ?? '' )) {
		$mc_query['type']      = 'page';
		$mc_query['slug']      = $page_slug;
		$mc_query['content']   = $item;
		$mc_query['is_single'] = true;
		return;
	}

	// Try slugified version of the path.
	$slugified = mc_sanitize_slug($page_slug);
	if ($slugified !== $page_slug) {
		$item = mc_get_content('page', $slugified);

		if (null !== $item && 'publish' === ( $item['status'] ?? '' )) {
			$mc_query['type']      = 'page';
			$mc_query['slug']      = $slugified;
			$mc_query['content']   = $item;
			$mc_query['is_single'] = true;
			return;
		}
	}

	// Nothing matched — 404.
	$mc_query['is_404'] = true;
}

/*
 * -------------------------------------------------------------------------
 *  Query conditionals
 * -------------------------------------------------------------------------
 */

/**
 * Is this the front page?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_front_page(): bool
{

	global $mc_query;
	return ! empty($mc_query['is_front_page']);
}

/**
 * Is this a single content item?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_single(): bool
{

	global $mc_query;
	return ! empty($mc_query['is_single']);
}

/**
 * Is this a content archive listing?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_archive(): bool
{

	global $mc_query;
	return ! empty($mc_query['is_archive']);
}

/**
 * Is this a 404 Not Found?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_404(): bool
{

	global $mc_query;
	return ! empty($mc_query['is_404']);
}

/**
 * Is this a page content type?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function mc_is_page(): bool
{

	global $mc_query;
	return 'page' === ( $mc_query['type'] ?? '' ) && mc_is_single();
}

/**
 * Get the current query data.
 *
 * @since 1.0.0
 *
 * @return array
 */
function mc_get_query(): array
{

	global $mc_query;
	return $mc_query;
}

/**
 * Get the current page number (for pagination).
 *
 * @since 1.0.0
 *
 * @return int
 */
function mc_get_page_num(): int
{

	global $mc_query;
	return $mc_query['page_num'] ?? 1;
}
