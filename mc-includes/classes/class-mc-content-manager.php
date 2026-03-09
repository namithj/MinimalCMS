<?php

/**
 * MC_Content_Manager — Flat-file content CRUD.
 *
 * Replaces the CRUD portion of content.php. Each content item is stored as
 * a JSON sidecar + Markdown body in mc-content/{type-folder}/{slug}/.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Content CRUD manager.
 *
 * @since {version}
 */
class MC_Content_Manager
{
	/**
	 * @since {version}
	 * @var MC_Content_Type_Registry
	 */
	private MC_Content_Type_Registry $types;

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Cache
	 */
	private MC_Cache $cache;

	/**
	 * @since {version}
	 * @var MC_Formatter
	 */
	private MC_Formatter $formatter;

	/**
	 * Base content directory.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $content_dir;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Content_Type_Registry $types       Content type registry.
	 * @param MC_Hooks                 $hooks       Hooks engine.
	 * @param MC_Cache                 $cache       Cache engine.
	 * @param MC_Formatter             $formatter   Formatter.
	 * @param string                   $content_dir Base content directory.
	 */
	public function __construct(
		MC_Content_Type_Registry $types,
		MC_Hooks $hooks,
		MC_Cache $cache,
		MC_Formatter $formatter,
		string $content_dir
	) {

		$this->types       = $types;
		$this->hooks       = $hooks;
		$this->cache       = $cache;
		$this->formatter   = $formatter;
		$this->content_dir = rtrim($content_dir, '/') . '/';
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Path helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the directory path for a content item.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return string Directory path with trailing slash.
	 */
	public function item_dir(string $type, string $slug): string
	{

		return $this->content_dir . $this->types->type_folder($type) . '/' . $slug . '/';
	}

	/**
	 * Get the Markdown file path for a content item.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return string Absolute path.
	 */
	public function md_path(string $type, string $slug): string
	{

		return $this->item_dir($type, $slug) . $slug . '.md';
	}

	/**
	 * Get the JSON sidecar path for a content item.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return string Absolute path.
	 */
	public function json_path(string $type, string $slug): string
	{

		return $this->item_dir($type, $slug) . $slug . '.json';
	}

	/*
	 * -------------------------------------------------------------------------
	 *  CRUD
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Read a single content item.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return array|null Content data or null if not found.
	 */
	public function get(string $type, string $slug): ?array
	{

		$json_path = $this->json_path($type, $slug);
		$md_path   = $this->md_path($type, $slug);

		if (!is_file($json_path)) {
			return null;
		}

		$meta_raw = file_get_contents($json_path);
		$meta     = json_decode($meta_raw, true);
		if (!is_array($meta)) {
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
		 * @since {version}
		 *
		 * @param array  $content Loaded content data.
		 * @param string $type    Content type slug.
		 * @param string $slug    Item slug.
		 */
		return $this->hooks->apply_filters('mc_get_content', $content, $type, $slug);
	}

	/**
	 * Save a content item (create or update).
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @param array  $meta Metadata fields.
	 * @param string $body Markdown body content.
	 * @return true|MC_Error True on success.
	 */
	public function save(string $type, string $slug, array $meta, string $body = ''): true|MC_Error
	{

		$slug = $this->formatter->sanitize_slug($slug);

		if ('' === $slug) {
			return new MC_Error('invalid_slug', 'Content slug cannot be empty.');
		}

		if (null === $this->types->get($type)) {
			return new MC_Error('invalid_type', 'Content type is not registered.');
		}

		/**
		 * Filter meta/body before save.
		 *
		 * @since {version}
		 *
		 * @param array  $meta Content metadata.
		 * @param string $body Markdown body.
		 * @param string $type Content type.
		 * @param string $slug Content slug.
		 */
		$meta = $this->hooks->apply_filters('mc_pre_save_content', $meta, $body, $type, $slug);

		$item_dir = $this->item_dir($type, $slug);
		if (!is_dir($item_dir)) {
			mkdir($item_dir, 0755, true);
		}

		$meta = array_merge(
			array(
				'title'          => $slug,
				'slug'           => $slug,
				'status'         => 'publish',
				'author'         => '',
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

		// Strip runtime-only keys — body belongs in the .md file, not the JSON sidecar.
		unset($meta['body_raw'], $meta['body_html']);

		$json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		if (false === file_put_contents($this->json_path($type, $slug), $json, LOCK_EX)) {
			return new MC_Error('write_failed', 'Failed to write content metadata.');
		}

		if (false === file_put_contents($this->md_path($type, $slug), $body, LOCK_EX)) {
			return new MC_Error('write_failed', 'Failed to write content body.');
		}

		$this->cache->delete($type . ':' . $slug, 'content');

		$this->hooks->do_action('mc_content_saved', $type, $slug, $meta);

		return true;
	}

	/**
	 * Delete a content item.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return true|MC_Error True on success.
	 */
	public function delete(string $type, string $slug): true|MC_Error
	{

		$item_dir = $this->item_dir($type, $slug);

		if (!is_dir($item_dir)) {
			return new MC_Error('not_found', 'Content item not found.');
		}

		$this->rmdir_recursive($item_dir);
		$this->cache->delete($type . ':' . $slug, 'content');

		$this->hooks->do_action('mc_content_deleted', $type, $slug);

		return true;
	}

	/**
	 * Check whether a content item exists.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return bool
	 */
	public function exists(string $type, string $slug): bool
	{

		return is_file($this->json_path($type, $slug));
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Queries
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Query content items of a given type.
	 *
	 * @since {version}
	 *
	 * @param array $args Query arguments.
	 * @return array List of content arrays.
	 */
	public function query(array $args): array
	{

		$defaults = array(
			'type'     => 'page',
			'status'   => 'publish',
			'order_by' => 'created',
			'order'    => 'DESC',
			'limit'    => 10,
			'offset'   => 0,
			'parent'   => '',
		);

		$args = array_merge($defaults, $args);

		/**
		 * Filter query arguments before execution.
		 *
		 * @since {version}
		 *
		 * @param array $args Query arguments.
		 */
		$args = $this->hooks->apply_filters('mc_pre_get_content', $args);

		$type = $args['type'];
		$dir  = $this->content_dir . $this->types->type_folder($type) . '/';

		if (!is_dir($dir)) {
			return array();
		}

		$items = array();
		$slugs = array_diff(scandir($dir), array('.', '..'));

		foreach ($slugs as $slug) {
			if (!is_dir($dir . $slug)) {
				continue;
			}

			$item = $this->get($type, $slug);
			if (null === $item) {
				continue;
			}

			if ('' !== $args['status'] && ($item['status'] ?? '') !== $args['status']) {
				continue;
			}

			if ('' !== $args['parent'] && ($item['parent'] ?? '') !== $args['parent']) {
				continue;
			}

			$items[] = $item;
		}

		// Sort.
		$order_by = $args['order_by'];
		$order    = strtoupper($args['order']);

		usort($items, function ($a, $b) use ($order_by, $order) {
			$val_a = $a[$order_by] ?? '';
			$val_b = $b[$order_by] ?? '';

			if (is_numeric($val_a) && is_numeric($val_b)) {
				$cmp = $val_a <=> $val_b;
			} else {
				$cmp = strcmp((string) $val_a, (string) $val_b);
			}

			return 'ASC' === $order ? $cmp : -$cmp;
		});

		$items = array_slice($items, $args['offset'], $args['limit']);

		/**
		 * Filter query results before return.
		 *
		 * @since {version}
		 *
		 * @param array $items Query results.
		 * @param array $args  Query arguments.
		 */
		return $this->hooks->apply_filters('mc_query_content_results', $items, $args);
	}

	/**
	 * Count content items matching criteria.
	 *
	 * @since {version}
	 *
	 * @param string $type   Content type.
	 * @param string $status Status filter. Default '' (all).
	 * @return int
	 */
	public function count(string $type, string $status = ''): int
	{

		$dir = $this->content_dir . $this->types->type_folder($type) . '/';

		if (!is_dir($dir)) {
			return 0;
		}

		$count = 0;
		$slugs = array_diff(scandir($dir), array('.', '..'));

		foreach ($slugs as $slug) {
			if (!is_dir($dir . $slug)) {
				continue;
			}

			if ('' === $status) {
				++$count;
				continue;
			}

			$json_path = $dir . $slug . '/' . $slug . '.json';
			if (!is_file($json_path)) {
				continue;
			}

			$meta = json_decode(file_get_contents($json_path), true);
			if (($meta['status'] ?? '') === $status) {
				++$count;
			}
		}

		/**
		 * Filter content count result.
		 *
		 * @since {version}
		 *
		 * @param int    $count  Count value.
		 * @param string $type   Content type.
		 * @param string $status Status filter.
		 */
		return $this->hooks->apply_filters('mc_count_content', $count, $type, $status);
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @since {version}
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function rmdir_recursive(string $dir): void
	{

		if (!is_dir($dir)) {
			return;
		}

		$entries = array_diff(scandir($dir), array('.', '..'));
		foreach ($entries as $entry) {
			$path = $dir . '/' . $entry;
			if (is_dir($path)) {
				$this->rmdir_recursive($path);
			} else {
				unlink($path);
			}
		}

		rmdir($dir);
	}
}
