<?php

/**
 * MinimalCMS Content System
 *
 * Provides content type registration and file-based content querying.
 * Each content type maps to a directory of Markdown + JSON sidecar files.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Registered content types keyed by slug.
 *
 * @global array $mc_content_types
 */
global $mc_content_types;
$mc_content_types = array();

/*
 * -------------------------------------------------------------------------
 *  Content type registration
 * -------------------------------------------------------------------------
 */

/**
 * Register a content type.
 *
 * @since 1.0.0
 *
 * @param string $slug Type identifier (e.g. 'page', 'doc').
 * @param array  $args {
 *     Configuration arguments.
 *
 *     @type string $label        Human-readable plural label.
 *     @type string $singular     Singular label.
 *     @type bool   $public       Whether publicly queryable. Default true.
 *     @type bool   $hierarchical Whether items can have parents. Default false.
 *     @type bool   $has_archive  Whether the type has an archive listing page. Default false.
 *     @type array  $rewrite      Rewrite settings. 'slug' key overrides URL prefix.
 *     @type array  $supports     Features: 'title', 'editor', 'excerpt', 'thumbnail'. Default all.
 * }
 * @return void
 */
function mc_register_content_type(string $slug, array $args = array()): void
{

	global $mc_content_types;

	$defaults = array(
		'label'        => ucfirst($slug) . 's',
		'singular'     => ucfirst($slug),
		'public'       => true,
		'hierarchical' => false,
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => $slug ),
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
	);

	$mc_content_types[ $slug ] = array_merge($defaults, $args);

	// Derive storage folder from the plural label if not explicitly supplied.
	// e.g. label "Blog Posts" → folder "blog-posts", "Pages" → "pages".
	if (! isset($args['folder'])) {
		$mc_content_types[ $slug ]['folder'] = mc_sanitize_slug(
			strtolower($mc_content_types[ $slug ]['label'])
		);
	}

	// Ensure the content directory exists.
	$dir = MC_CONTENT_DIR . $mc_content_types[ $slug ]['folder'] . '/';
	if (! is_dir($dir)) {
		mkdir($dir, 0755, true);
	}

	/**
	 * Fires after a content type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Type slug.
	 * @param array  $args Type configuration.
	 */
	mc_do_action('mc_registered_content_type', $slug, $mc_content_types[ $slug ]);
}

/**
 * Get a registered content type definition.
 *
 * @since 1.0.0
 *
 * @param string $slug Type slug.
 * @return array|null Type definition or null.
 */
function mc_get_content_type(string $slug): ?array
{

	global $mc_content_types;
	return $mc_content_types[ $slug ] ?? null;
}

/**
 * Get all registered content types.
 *
 * @since 1.0.0
 *
 * @return array Associative array of slug => definition.
 */
function mc_get_content_types(): array
{

	global $mc_content_types;
	return $mc_content_types;
}

/**
 * Get the storage folder name for a content type.
 *
 * Returns the value of the 'folder' key set during registration, which
 * defaults to the plural form of the type slug (e.g. 'page' → 'pages').
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @return string Folder name (no slashes).
 */
function mc_content_type_folder(string $type): string
{

	global $mc_content_types;
	if (isset($mc_content_types[ $type ]['folder'])) {
		return $mc_content_types[ $type ]['folder'];
	}
	// Fallback for unregistered types: use the type slug directly.
	return $type;
}

/*
 * -------------------------------------------------------------------------
 *  Content file I/O
 * -------------------------------------------------------------------------
 */

/**
 * Get the directory path for a content item.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string Directory path with trailing slash.
 */
function mc_content_item_dir(string $type, string $slug): string
{

	return MC_CONTENT_DIR . mc_content_type_folder($type) . '/' . $slug . '/';
}

/**
 * Get the Markdown file path for a content item.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string Absolute path to the .md file.
 */
function mc_content_md_path(string $type, string $slug): string
{

	return mc_content_item_dir($type, $slug) . $slug . '.md';
}

/**
 * Get the JSON sidecar path for a content item.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string Absolute path to the .json file.
 */
function mc_content_json_path(string $type, string $slug): string
{

	return mc_content_item_dir($type, $slug) . $slug . '.json';
}

/**
 * Read a single content item.
 *
 * Returns an associative array combining JSON metadata and the raw/parsed Markdown body.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return array|null Content data or null if not found.
 */
function mc_get_content(string $type, string $slug): ?array
{

	$json_path = mc_content_json_path($type, $slug);
	$md_path   = mc_content_md_path($type, $slug);

	if (! is_file($json_path)) {
		return null;
	}

	$meta_raw = file_get_contents($json_path);
	$meta     = json_decode($meta_raw, true);

	if (! is_array($meta)) {
		$meta = array();
	}

	$body_raw = '';
	if (is_file($md_path)) {
		$body_raw = file_get_contents($md_path);
	}

	$content = array_merge(
		array(
			'type'           => $type,
			'slug'           => $slug,
			'title'          => '',
			'status'         => 'publish',
			'author'         => '',
			'created'        => '',
			'modified'       => '',
			'template'       => '',
			'parent'         => '',
			'order'          => 0,
			'excerpt'        => '',
			'featured_image' => '',
			'meta'           => array(),
		),
		$meta,
		array(
			'body_raw'  => $body_raw,
			'body_html' => '',
		)
	);

	/**
	 * Filter a content item after loading.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $content Loaded content data.
	 * @param string $type    Content type slug.
	 * @param string $slug    Item slug.
	 */
	return mc_apply_filters('mc_get_content', $content, $type, $slug);
}

/**
 * Query content items of a given type.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Query arguments.
 *
 *     @type string $type     Content type. Default 'page'.
 *     @type string $status   Filter by status. Default 'publish'.
 *     @type string $order_by Sort field. Default 'created'.
 *     @type string $order    'ASC' or 'DESC'. Default 'DESC'.
 *     @type int    $limit    Maximum items. Default MC_POSTS_PER_PAGE.
 *     @type int    $offset   Number of items to skip. Default 0.
 *     @type string $parent   Filter by parent slug. Default '' (all).
 * }
 * @return array List of content arrays.
 */
function mc_query_content(array $args = array()): array
{

	$defaults = array(
		'type'     => 'page',
		'status'   => 'publish',
		'order_by' => 'created',
		'order'    => 'DESC',
		'limit'    => MC_POSTS_PER_PAGE,
		'offset'   => 0,
		'parent'   => '',
	);

	$args = array_merge($defaults, $args);

	/**
	 * Filter query arguments before execution.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 */
	$args = mc_apply_filters('mc_pre_get_content', $args);

	$type = $args['type'];
	$dir  = MC_CONTENT_DIR . mc_content_type_folder($type) . '/';

	if (! is_dir($dir)) {
		return array();
	}

	$items = array();
	$slugs = array_diff(scandir($dir), array( '.', '..' ));

	foreach ($slugs as $slug) {
		if (! is_dir($dir . $slug)) {
			continue;
		}

		$item = mc_get_content($type, $slug);

		if (null === $item) {
			continue;
		}

		// Filter by status.
		if ('' !== $args['status'] && ( $item['status'] ?? '' ) !== $args['status']) {
			continue;
		}

		// Filter by parent.
		if ('' !== $args['parent'] && ( $item['parent'] ?? '' ) !== $args['parent']) {
			continue;
		}

		$items[] = $item;
	}

	// Sort.
	$order_by = $args['order_by'];
	$order    = strtoupper($args['order']);

	usort(
		$items,
		function ($a, $b) use ($order_by, $order) {
			$val_a = $a[ $order_by ] ?? '';
			$val_b = $b[ $order_by ] ?? '';

			if (is_numeric($val_a) && is_numeric($val_b)) {
				$cmp = $val_a <=> $val_b;
			} else {
				$cmp = strcmp((string) $val_a, (string) $val_b);
			}

			return 'ASC' === $order ? $cmp : -$cmp;
		}
	);

	// Pagination.
	$items = array_slice($items, $args['offset'], $args['limit']);

	return $items;
}

/**
 * Count content items matching criteria.
 *
 * @since 1.0.0
 *
 * @param string $type   Content type.
 * @param string $status Status filter. Default '' (all).
 * @return int Count.
 */
function mc_count_content(string $type, string $status = ''): int
{

	$dir = MC_CONTENT_DIR . mc_content_type_folder($type) . '/';

	if (! is_dir($dir)) {
		return 0;
	}

	$count = 0;
	$slugs = array_diff(scandir($dir), array( '.', '..' ));

	foreach ($slugs as $slug) {
		if (! is_dir($dir . $slug)) {
			continue;
		}

		if ('' === $status) {
			++$count;
			continue;
		}

		$json_path = $dir . $slug . '/' . $slug . '.json';
		if (! is_file($json_path)) {
			continue;
		}

		$meta = json_decode(file_get_contents($json_path), true);
		if (( $meta['status'] ?? '' ) === $status) {
			++$count;
		}
	}

	return $count;
}

/**
 * Save a content item (create or update).
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @param array  $meta Metadata fields.
 * @param string $body Markdown body content.
 * @return true|MC_Error True on success.
 */
function mc_save_content(string $type, string $slug, array $meta, string $body = ''): true|MC_Error
{

	$slug = mc_sanitize_slug($slug);

	if ('' === $slug) {
		return new MC_Error('invalid_slug', 'Content slug cannot be empty.');
	}

	if (null === mc_get_content_type($type)) {
		return new MC_Error('invalid_type', 'Content type is not registered.');
	}

	$item_dir = mc_content_item_dir($type, $slug);

	if (! is_dir($item_dir)) {
		mkdir($item_dir, 0755, true);
	}

	// Ensure required meta fields.
	$meta = array_merge(
		array(
			'title'          => $slug,
			'slug'           => $slug,
			'status'         => 'publish',
			'author'         => mc_get_current_user_id(),
			'created'        => gmdate('c'),
			'modified'       => gmdate('c'),
			'template'       => '',
			'parent'         => '',
			'order'          => 0,
			'excerpt'        => '',
			'featured_image' => '',
			'meta'           => array(),
		),
		$meta,
		array(
			'slug'     => $slug,
			'modified' => gmdate('c'),
		)
	);

	// Write JSON sidecar.
	$json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if (false === file_put_contents(mc_content_json_path($type, $slug), $json, LOCK_EX)) {
		return new MC_Error('write_failed', 'Failed to write content metadata.');
	}

	// Write Markdown body.
	if (false === file_put_contents(mc_content_md_path($type, $slug), $body, LOCK_EX)) {
		return new MC_Error('write_failed', 'Failed to write content body.');
	}

	mc_cache_delete($type . ':' . $slug, 'content');

	/**
	 * Fires after content is saved.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Content type.
	 * @param string $slug Content slug.
	 * @param array  $meta Saved metadata.
	 */
	mc_do_action('mc_content_saved', $type, $slug, $meta);

	return true;
}

/**
 * Delete a content item.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return true|MC_Error True on success.
 */
function mc_delete_content(string $type, string $slug): true|MC_Error
{

	$item_dir = mc_content_item_dir($type, $slug);

	if (! is_dir($item_dir)) {
		return new MC_Error('not_found', 'Content item not found.');
	}

	mc_rmdir_recursive($item_dir);
	mc_cache_delete($type . ':' . $slug, 'content');

	/**
	 * Fires after content is deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Content type.
	 * @param string $slug Deleted slug.
	 */
	mc_do_action('mc_content_deleted', $type, $slug);

	return true;
}

/**
 * Check whether a content item exists.
 *
 * @since 1.0.0
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return bool
 */
function mc_content_exists(string $type, string $slug): bool
{

	return is_file(mc_content_json_path($type, $slug));
}
