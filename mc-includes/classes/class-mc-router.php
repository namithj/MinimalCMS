<?php

/**
 * MC_Router — URL routing and query state.
 *
 * Replaces rewrite.php. Parses the incoming URL and resolves it to a content
 * item, archive listing, custom route, or 404.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * URL router.
 *
 * @since {version}
 */
class MC_Router
{
	/**
	 * Custom routes registered by plugins.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $routes = array();

	/**
	 * The resolved query state for the current request.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $query = array();

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Content_Manager
	 */
	private MC_Content_Manager $content;

	/**
	 * @since {version}
	 * @var MC_Content_Type_Registry
	 */
	private MC_Content_Type_Registry $types;

	/**
	 * @since {version}
	 * @var MC_Http
	 */
	private MC_Http $http;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks                 $hooks   Hooks engine.
	 * @param MC_Content_Manager       $content Content manager.
	 * @param MC_Content_Type_Registry $types   Content type registry.
	 * @param MC_Http                  $http    HTTP / nonce helper.
	 */
	public function __construct(MC_Hooks $hooks, MC_Content_Manager $content, MC_Content_Type_Registry $types, MC_Http $http)
	{

		$this->hooks   = $hooks;
		$this->content = $content;
		$this->types   = $types;
		$this->http    = $http;

		$this->query = $this->default_query();
	}

	/**
	 * Register a custom route pattern.
	 *
	 * @since {version}
	 *
	 * @param string   $pattern  Regex pattern (without delimiters).
	 * @param callable $callback Handler. Receives regex matches as first argument.
	 * @param int      $priority Lower = matched first. Default 10.
	 * @return void
	 */
	public function add_route(string $pattern, callable $callback, int $priority = 10): void
	{

		$this->routes[] = array(
			'pattern'  => $pattern,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Extract the clean request path from the current URI.
	 *
	 * @since {version}
	 *
	 * @return string Cleaned path, e.g. "about" or "docs/getting-started".
	 */
	public function get_request_path(): string
	{

		$uri = $_SERVER['REQUEST_URI'] ?? '/';
		$path = strtok($uri, '?');

		$base = defined('MC_BASE_PATH') ? MC_BASE_PATH : '';
		if ('' !== $base && str_starts_with($path, $base)) {
			$path = substr($path, strlen($base));
		}

		return trim($path, '/');
	}

	/**
	 * Parse the current request and populate the query state.
	 *
	 * Resolution order:
	 *   1. Admin routes (/mc-admin/*)
	 *   2. Custom routes (sorted by priority)
	 *   3. Content type archives/items
	 *   4. Pages (root-level URLs)
	 *   5. 404
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function parse_request(): void
	{

		$path = $this->get_request_path();

		/**
		 * Filter the request path before routing.
		 *
		 * @since {version}
		 *
		 * @param string $path Cleaned request path.
		 */
		$path = $this->hooks->apply_filters('mc_request_path', $path);

		$this->query = $this->default_query();
		$this->query['path'] = $path;

		// 1. Admin route.
		if (str_starts_with($path, 'mc-admin')) {
			$this->query['is_admin'] = true;
			return;
		}

		// 2. Custom routes.
		if (!empty($this->routes)) {
			/**
			 * Filter custom routes before matching.
			 *
			 * @since {version}
			 *
			 * @param array $routes Registered routes.
			 */
			$routes = $this->hooks->apply_filters('mc_custom_routes', $this->routes);

			usort($routes, fn($a, $b) => $a['priority'] <=> $b['priority']);

			foreach ($routes as $route) {
				if (preg_match('#^' . $route['pattern'] . '$#', $path, $matches)) {
					call_user_func($route['callback'], $matches);
					$this->hooks->do_action('mc_route_matched', $route, $matches);
					return;
				}
			}
		}

		// 3-5. Content resolution.
		$this->resolve_content_route($path);

		/**
		 * Filter query state after routing.
		 *
		 * @since {version}
		 *
		 * @param array $query Query state.
		 */
		$this->query = $this->hooks->apply_filters('mc_parse_request', $this->query);
	}

	/**
	 * Resolve a URL path to a content item or archive.
	 *
	 * @since {version}
	 *
	 * @param string $path The request path.
	 * @return void
	 */
	public function resolve_content_route(string $path): void
	{

		$content_types = $this->types->all();

		// Handle pagination suffix.
		if (preg_match('#^(.+)/page/(\d+)$#', $path, $page_match)) {
			$path                    = $page_match[1];
			$this->query['page_num'] = max(1, (int) $page_match[2]);
		} elseif (preg_match('#^page/(\d+)$#', $path, $page_match)) {
			$path                    = '';
			$this->query['page_num'] = max(1, (int) $page_match[1]);
		}

		// Front page.
		if ('' === $path) {
			$front_slug = defined('MC_FRONT_PAGE') ? MC_FRONT_PAGE : 'home';
			$item       = $this->content->get('page', $front_slug);

			if (null !== $item && $this->is_content_viewable($item, 'page', $front_slug)) {
				$this->query['type']          = 'page';
				$this->query['slug']          = $front_slug;
				$this->query['content']       = $item;
				$this->query['is_front_page'] = true;
				$this->query['is_single']     = true;
				$this->query['is_preview']    = 'publish' !== ($item['status'] ?? '');
				return;
			}

			$this->query['is_404'] = true;
			return;
		}

		// Custom content types: {rewrite-slug}/{item-slug} or {rewrite-slug}/ (archive).
		foreach ($content_types as $type_slug => $type_def) {
			if ('page' === $type_slug) {
				continue;
			}

			if (empty($type_def['public'])) {
				continue;
			}

			$rewrite_slug = $type_def['rewrite']['slug'] ?? $type_slug;

			// Archive.
			if ($path === $rewrite_slug && !empty($type_def['has_archive'])) {
				$this->query['type']       = $type_slug;
				$this->query['is_archive'] = true;

				$per_page = defined('MC_POSTS_PER_PAGE') ? MC_POSTS_PER_PAGE : 10;
				$offset   = ($this->query['page_num'] - 1) * $per_page;

				$this->query['archive_items'] = $this->content->query(array(
					'type'   => $type_slug,
					'status' => 'publish',
					'offset' => $offset,
				));
				return;
			}

			// Single item.
			if (str_starts_with($path, $rewrite_slug . '/')) {
				$item_slug = substr($path, strlen($rewrite_slug) + 1);
				$item      = $this->content->get($type_slug, $item_slug);

				if (null !== $item && $this->is_content_viewable($item, $type_slug, $item_slug)) {
					$this->query['type']       = $type_slug;
					$this->query['slug']       = $item_slug;
					$this->query['content']    = $item;
					$this->query['is_single']  = true;
					$this->query['is_preview'] = 'publish' !== ($item['status'] ?? '');
					return;
				}
			}
		}

		// Pages: root-level URL.
		$item = $this->content->get('page', $path);

		if (null !== $item && $this->is_content_viewable($item, 'page', $path)) {
			$front_slug = defined('MC_FRONT_PAGE') ? MC_FRONT_PAGE : 'home';
			if ($path === $front_slug) {
				$site_url = defined('MC_SITE_URL') ? MC_SITE_URL : '/';
				header('Location: ' . $site_url, true, 301);
				exit;
			}

			$this->query['type']       = 'page';
			$this->query['slug']       = $path;
			$this->query['content']    = $item;
			$this->query['is_single']  = true;
			$this->query['is_preview'] = 'publish' !== ($item['status'] ?? '');
			return;
		}

		// 404.
		$this->query['is_404'] = true;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Query conditionals
	 * -------------------------------------------------------------------------
	 */

	/**
	 * @since {version}
	 * @return bool
	 */
	public function is_front_page(): bool
	{

		return !empty($this->query['is_front_page']);
	}

	/**
	 * @since {version}
	 * @return bool
	 */
	public function is_single(): bool
	{

		return !empty($this->query['is_single']);
	}

	/**
	 * @since {version}
	 * @return bool
	 */
	public function is_archive(): bool
	{

		return !empty($this->query['is_archive']);
	}

	/**
	 * @since {version}
	 * @return bool
	 */
	public function is_404(): bool
	{

		return !empty($this->query['is_404']);
	}

	/**
	 * @since {version}
	 * @return bool
	 */
	public function is_page(): bool
	{

		return 'page' === ($this->query['type'] ?? '') && $this->is_single();
	}

	/**
	 * @since {version}
	 * @return bool
	 */
	public function is_admin(): bool
	{

		return !empty($this->query['is_admin']);
	}

	/**
	 * Get the full query state array.
	 *
	 * @since {version}
	 *
	 * @return array
	 */
	public function get_query(): array
	{

		return $this->query;
	}

	/**
	 * Get the current page number.
	 *
	 * @since {version}
	 *
	 * @return int
	 */
	public function get_page_num(): int
	{

		return $this->query['page_num'] ?? 1;
	}

	/**
	 * Check whether the current request is a valid draft preview.
	 *
	 * Verifies the ?preview=true&key={nonce} parameters. The nonce is tied
	 * to the current logged-in user, so it cannot be reused by another user.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return bool
	 */
	private function is_valid_preview(string $type, string $slug): bool
	{

		$preview = $this->http->input('preview', 'GET');
		$key     = $this->http->input('key', 'GET');

		if ('true' !== $preview || empty($key) || !is_string($key)) {
			return false;
		}

		return $this->http->verify_nonce($key, 'preview_' . $type . '_' . $slug);
	}

	/**
	 * Check whether a content item is viewable on the front end.
	 *
	 * An item is viewable if it is published or if the current request
	 * carries a valid preview nonce for the item.
	 *
	 * @since {version}
	 *
	 * @param array  $item Content item data.
	 * @param string $type Content type slug.
	 * @param string $slug Content item slug.
	 * @return bool
	 */
	private function is_content_viewable(array $item, string $type, string $slug): bool
	{

		if ('publish' === ($item['status'] ?? '')) {
			return true;
		}

		return $this->is_valid_preview($type, $slug);
	}

	/**
	 * Default empty query state.
	 *
	 * @since {version}
	 *
	 * @return array
	 */
	private function default_query(): array
	{

		return array(
			'path'          => '',
			'type'          => '',
			'slug'          => '',
			'content'       => null,
			'is_front_page' => false,
			'is_archive'    => false,
			'is_single'     => false,
			'is_404'        => false,
			'is_admin'      => false,
			'is_preview'    => false,
			'page_num'      => 1,
			'archive_items' => array(),
		);
	}
}
