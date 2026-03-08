<?php
/**
 * MC_Template_Loader — Template hierarchy resolution and loading.
 *
 * Replaces template-loader.php. Determines the correct template file
 * from the active theme based on the current query context.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Template hierarchy loader.
 *
 * @since {version}
 */
class MC_Template_Loader {

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Router
	 */
	private MC_Router $router;

	/**
	 * @since {version}
	 * @var MC_Theme_Manager
	 */
	private MC_Theme_Manager $themes;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks         $hooks  Hooks engine.
	 * @param MC_Router        $router Router.
	 * @param MC_Theme_Manager $themes Theme manager.
	 */
	public function __construct(MC_Hooks $hooks, MC_Router $router, MC_Theme_Manager $themes) {

		$this->hooks  = $hooks;
		$this->router = $router;
		$this->themes = $themes;
	}

	/**
	 * Determine and include the appropriate template.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function load(): void {

		/**
		 * Fires before the template is determined.
		 *
		 * @since {version}
		 */
		$this->hooks->do_action('mc_template_redirect');

		$templates = $this->get_hierarchy();

		/**
		 * Filter the template hierarchy candidates.
		 *
		 * @since {version}
		 *
		 * @param string[] $templates Candidate template filenames.
		 */
		$templates = $this->hooks->apply_filters('mc_template_hierarchy', $templates);

		$template = $this->locate($templates);

		/**
		 * Filter the resolved template file path.
		 *
		 * @since {version}
		 *
		 * @param string   $template  Absolute path or empty.
		 * @param string[] $templates Candidates that were searched.
		 */
		$template = $this->hooks->apply_filters('mc_template_include', $template, $templates);

		if ('' !== $template && is_file($template)) {
			include $template;
		} else {
			if (!headers_sent()) {
				http_response_code(404);
			}
			echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body><h1>404 — Page Not Found</h1></body></html>';
		}
	}

	/**
	 * Build the template candidate list based on query state.
	 *
	 * @since {version}
	 *
	 * @return string[] Ordered list of template filenames.
	 */
	public function get_hierarchy(): array {

		$query     = $this->router->get_query();
		$templates = array();
		$type      = $query['type'] ?? '';
		$slug      = $query['slug'] ?? '';
		$content   = $query['content'] ?? null;

		// Custom template set in content metadata.
		if (null !== $content && !empty($content['template'])) {
			$templates[] = $content['template'];
		}

		if (!empty($query['is_404'])) {
			$templates[] = '404.php';
		} elseif (!empty($query['is_front_page'])) {
			$templates[] = 'front-page.php';
			if ('' !== $slug) {
				$templates[] = 'page-' . $slug . '.php';
			}
			$templates[] = 'page.php';
		} elseif (!empty($query['is_archive'])) {
			if ('' !== $type) {
				$templates[] = 'archive-' . $type . '.php';
			}
			$templates[] = 'archive.php';
		} elseif (!empty($query['is_single'])) {
			if ('page' === $type) {
				if ('' !== $slug) {
					$templates[] = 'page-' . $slug . '.php';
				}
				$templates[] = 'page.php';
			} else {
				if ('' !== $slug) {
					$templates[] = 'single-' . $type . '-' . $slug . '.php';
				}
				$templates[] = 'single-' . $type . '.php';
			}
			$templates[] = 'single.php';
		}

		$templates[] = 'index.php';

		return $templates;
	}

	/**
	 * Find the first matching template file in theme directories.
	 *
	 * @since {version}
	 *
	 * @param string[] $templates Candidate filenames.
	 * @return string Absolute path to the first match, or empty string.
	 */
	public function locate(array $templates): string {

		$theme_dir  = $this->themes->get_active_dir();
		$parent_dir = $this->themes->get_parent_dir();

		foreach ($templates as $tpl) {
			if (is_file($theme_dir . $tpl)) {
				return $theme_dir . $tpl;
			}

			if ('' !== $parent_dir && is_file($parent_dir . $tpl)) {
				return $parent_dir . $tpl;
			}
		}

		return '';
	}
}
