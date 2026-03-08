<?php

/**
 * MC_Theme_Manager — Theme discovery, loading, and switching.
 *
 * Replaces theme.php. Handles theme.json parsing, child theme support,
 * page template discovery, and theme switching.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Theme lifecycle manager.
 *
 * @since {version}
 */
class MC_Theme_Manager
{
	/**
	 * Discovered themes keyed by slug.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $themes = array();

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Config
	 */
	private MC_Config $config;

	/**
	 * Themes directory with trailing slash.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $themes_dir;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks  $hooks      Hooks engine.
	 * @param MC_Config $config     Configuration.
	 * @param string    $themes_dir Themes directory path.
	 */
	public function __construct(MC_Hooks $hooks, MC_Config $config, string $themes_dir)
	{

		$this->hooks      = $hooks;
		$this->config     = $config;
		$this->themes_dir = rtrim($themes_dir, '/') . '/';
	}

	/**
	 * Parse theme.json metadata for a theme directory.
	 *
	 * @since {version}
	 *
	 * @param string $dir Absolute path to the theme directory (with trailing slash).
	 * @return array Theme metadata.
	 */
	public function get_theme_data(string $dir): array
	{

		$defaults = array(
			'name'        => basename(rtrim($dir, '/')),
			'version'     => '1.0.0',
			'author'      => '',
			'description' => '',
			'requires_mc' => '',
			'license'     => '',
			'template'    => '',
			'text_domain' => '',
		);

		$manifest = $dir . 'theme.json';

		if (!is_file($manifest)) {
			return $defaults;
		}

		$raw  = file_get_contents($manifest);
		$data = json_decode($raw, true);

		if (!is_array($data)) {
			return $defaults;
		}

		return array_merge($defaults, $data);
	}

	/**
	 * Discover all installed themes.
	 *
	 * @since {version}
	 *
	 * @return array slug => metadata.
	 */
	public function discover(): array
	{

		if (!is_dir($this->themes_dir)) {
			return array();
		}

		$themes  = array();
		$entries = array_diff(scandir($this->themes_dir), array('.', '..'));

		foreach ($entries as $entry) {
			$theme_dir = $this->themes_dir . $entry . '/';
			if (!is_dir($theme_dir)) {
				continue;
			}

			if (!is_file($theme_dir . 'theme.json') && !is_file($theme_dir . 'style.css')) {
				continue;
			}

			$themes[$entry] = $this->get_theme_data($theme_dir);
		}

		/**
		 * Filter discovered themes list.
		 *
		 * @since {version}
		 *
		 * @param array $themes Discovered themes.
		 */
		return $this->hooks->apply_filters('mc_discover_themes', $themes);
	}

	/**
	 * Load the active theme (and parent if child theme).
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function load(): void
	{

		$active_slug = $this->config->get('active_theme', 'default');
		$theme_dir   = $this->themes_dir . $active_slug . '/';

		if (!is_dir($theme_dir)) {
			return;
		}

		$theme_data = $this->get_theme_data($theme_dir);
		$this->themes[$active_slug] = $theme_data;

		// If child theme, load parent first.
		$parent_slug = $theme_data['template'] ?? '';

		if ('' !== $parent_slug && $parent_slug !== $active_slug) {
			$parent_dir = $this->themes_dir . $parent_slug . '/';

			if (is_dir($parent_dir)) {
				$this->themes[$parent_slug] = $this->get_theme_data($parent_dir);

				$parent_functions = $parent_dir . 'functions.php';
				if (is_file($parent_functions)) {
					include_once $parent_functions;
				}
			}
		}

		$functions = $theme_dir . 'functions.php';
		if (is_file($functions)) {
			include_once $functions;
		}

		/**
		 * Fires after the active theme has been loaded.
		 *
		 * @since {version}
		 *
		 * @param string $active_slug Active theme slug.
		 */
		$this->hooks->do_action('mc_after_setup_theme', $active_slug);
	}

	/**
	 * Get the active theme's metadata.
	 *
	 * @since {version}
	 *
	 * @return array Theme metadata.
	 */
	public function get_active(): array
	{

		$slug = $this->config->get('active_theme', 'default');
		return $this->themes[$slug] ?? array();
	}

	/**
	 * Get the active theme directory path.
	 *
	 * @since {version}
	 *
	 * @return string
	 */
	public function get_active_dir(): string
	{

		$slug = $this->config->get('active_theme', 'default');
		return $this->themes_dir . $slug . '/';
	}

	/**
	 * Get the parent theme directory path (for child themes).
	 *
	 * @since {version}
	 *
	 * @return string Directory path or empty string.
	 */
	public function get_parent_dir(): string
	{

		$active      = $this->get_active();
		$parent_slug = $active['template'] ?? '';

		if ('' === $parent_slug || $parent_slug === $this->config->get('active_theme', 'default')) {
			return '';
		}

		return $this->themes_dir . $parent_slug . '/';
	}

	/**
	 * Switch to a different theme.
	 *
	 * @since {version}
	 *
	 * @param string $slug Theme directory name.
	 * @return true|MC_Error
	 */
	public function switch_theme(string $slug): true|MC_Error
	{

		$theme_dir = $this->themes_dir . $slug . '/';

		if (!is_dir($theme_dir)) {
			return new MC_Error('not_found', 'Theme not found.');
		}

		/**
		 * Filter before theme switch.
		 *
		 * @since {version}
		 *
		 * @param string $slug Theme slug.
		 */
		$slug = $this->hooks->apply_filters('mc_switch_theme', $slug);

		$this->config->set('active_theme', $slug);
		$this->config->save();

		$this->hooks->do_action('mc_theme_switched', $slug);

		return true;
	}

	/**
	 * Get available page templates from the active theme (and parent).
	 *
	 * @since {version}
	 *
	 * @return array filename => {name, sections, global_sections}.
	 */
	public function get_page_templates(): array
	{

		$dirs = array($this->get_active_dir());

		$parent_dir = $this->get_parent_dir();
		if ('' !== $parent_dir) {
			$dirs[] = $parent_dir;
		}

		$templates = array();

		foreach ($dirs as $dir) {
			if (!is_dir($dir)) {
				continue;
			}

			$files = glob($dir . '*.php');
			if (!is_array($files)) {
				continue;
			}

			foreach ($files as $file) {
				$header = file_get_contents($file, false, null, 0, 8192);
				if (false === $header) {
					continue;
				}

				if (!preg_match('/^\s*(?:\*|#|\/\/)?\s*Template Name:\s*(.+)$/m', $header, $m)) {
					continue;
				}

				$filename = basename($file);
				if (isset($templates[$filename])) {
					continue;
				}

				$sections        = array();
				$global_sections = array();

				if (preg_match('/^\s*(?:\*|#|\/\/)?\s*Template Sections:\s*(.+)$/m', $header, $sm)) {
					$sections = $this->parse_section_header(trim($sm[1]));
				}

				if (preg_match('/^\s*(?:\*|#|\/\/)?\s*Global Sections:\s*(.+)$/m', $header, $gm)) {
					$global_sections = $this->parse_section_header(trim($gm[1]));
				}

				$templates[$filename] = array(
					'name'            => trim($m[1]),
					'sections'        => $sections,
					'global_sections' => $global_sections,
				);
			}
		}

		uasort($templates, static fn($a, $b) => strcmp($a['name'], $b['name']));

		/**
		 * Filter available page templates list.
		 *
		 * @since {version}
		 *
		 * @param array $templates Page templates.
		 */
		return $this->hooks->apply_filters('mc_page_templates', $templates);
	}

	/**
	 * Parse a section header string ("id:Label, id2:Label2").
	 *
	 * @since {version}
	 *
	 * @param string $header_value Raw header value.
	 * @return array<string,string> section_id => label.
	 */
	public function parse_section_header(string $header_value): array
	{

		$sections = array();

		foreach (explode(',', $header_value) as $part) {
			$part  = trim($part);
			$colon = strpos($part, ':');

			if (false === $colon || '' === $part) {
				continue;
			}

			$id    = trim(substr($part, 0, $colon));
			$label = trim(substr($part, $colon + 1));

			if ('' !== $id && '' !== $label) {
				$sections[$id] = $label;
			}
		}

		return $sections;
	}
}
