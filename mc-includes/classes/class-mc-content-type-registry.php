<?php
/**
 * MC_Content_Type_Registry — Content type definitions.
 *
 * Replaces content-types.php and the registration portion of content.php.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Content type registry.
 *
 * @since {version}
 */
class MC_Content_Type_Registry {

	/**
	 * Registered types keyed by slug.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $types = array();

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Content storage directory.
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
	 * @param MC_Hooks $hooks       Hooks engine.
	 * @param string   $content_dir Base content directory.
	 */
	public function __construct(MC_Hooks $hooks, string $content_dir) {

		$this->hooks       = $hooks;
		$this->content_dir = rtrim($content_dir, '/') . '/';
	}

	/**
	 * Register a content type with its configuration.
	 *
	 * @since {version}
	 *
	 * @param string $slug Content type slug.
	 * @param array  $args Type configuration.
	 * @return void
	 */
	public function register(string $slug, array $args = array()): void {

		$defaults = array(
			'label'        => ucfirst($slug) . 's',
			'singular'     => ucfirst($slug),
			'public'       => true,
			'hierarchical' => false,
			'has_archive'  => false,
			'rewrite'      => array('slug' => $slug),
			'supports'     => array('title', 'editor', 'excerpt', 'thumbnail'),
			'show_in_menu' => true,
			'menu_icon'    => '&#x1F4C2;',
		);

		/**
		 * Filter content type args before registration.
		 *
		 * @since {version}
		 *
		 * @param array  $args Merged type arguments.
		 * @param string $slug Type slug.
		 */
		$merged = $this->hooks->apply_filters(
			'mc_register_content_type_args',
			array_merge($defaults, $args),
			$slug
		);

		// Derive folder from label if not set.
		if (!isset($args['folder'])) {
			$merged['folder'] = strtolower(
				preg_replace('/[^a-z0-9]+/i', '-', trim($merged['label']))
			);
			$merged['folder'] = trim($merged['folder'], '-');
		}

		$this->types[$slug] = $merged;

		// Ensure the content directory exists.
		$dir = $this->content_dir . $merged['folder'] . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		$this->hooks->do_action('mc_registered_content_type', $slug, $merged);
	}

	/**
	 * Get a registered content type definition.
	 *
	 * @since {version}
	 *
	 * @param string $slug Type slug.
	 * @return array|null Type definition or null.
	 */
	public function get(string $slug): ?array {

		return $this->types[$slug] ?? null;
	}

	/**
	 * Get all registered content types.
	 *
	 * @since {version}
	 *
	 * @return array Associative array of slug => definition.
	 */
	public function all(): array {

		/**
		 * Filter all content types when retrieved.
		 *
		 * @since {version}
		 *
		 * @param array $types All registered types.
		 */
		return $this->hooks->apply_filters('mc_content_types', $this->types);
	}

	/**
	 * Get directory path for a content type's storage folder.
	 *
	 * @since {version}
	 *
	 * @param string $type Content type slug.
	 * @return string Folder name (no slashes).
	 */
	public function type_folder(string $type): string {

		if (isset($this->types[$type]['folder'])) {
			return $this->types[$type]['folder'];
		}

		return $type;
	}

	/**
	 * Register built-in types.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function register_defaults(): void {

		$this->register('page', array(
			'label'        => 'Pages',
			'singular'     => 'Page',
			'public'       => true,
			'hierarchical' => true,
			'has_archive'  => false,
			'rewrite'      => array('slug' => ''),
			'supports'     => array('title', 'editor', 'excerpt', 'thumbnail'),
			'menu_icon'    => '&#x1F4C4;',
		));
	}
}
