<?php

/**
 * MC_Template_Tags — Template helper functions.
 *
 * Replaces the template helper portion of template-tags.php (minus the
 * asset enqueue system which is now in MC_Asset_Manager).
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Template output helpers.
 *
 * @since {version}
 */
class MC_Template_Tags
{
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
	 * @var MC_Markdown
	 */
	private MC_Markdown $markdown;

	/**
	 * @since {version}
	 * @var MC_Shortcodes
	 */
	private MC_Shortcodes $shortcodes;

	/**
	 * @since {version}
	 * @var MC_Formatter
	 */
	private MC_Formatter $formatter;

	/**
	 * @since {version}
	 * @var MC_Theme_Manager
	 */
	private MC_Theme_Manager $themes;

	/**
	 * @since {version}
	 * @var MC_Asset_Manager
	 */
	private MC_Asset_Manager $assets;

	/**
	 * @since {version}
	 * @var MC_User_Manager
	 */
	private MC_User_Manager $users;

	/**
	 * @since {version}
	 * @var MC_Template_Loader
	 */
	private MC_Template_Loader $template_loader;

	/**
	 * @since {version}
	 * @var MC_Settings
	 */
	private MC_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks           $hooks           Hooks engine.
	 * @param MC_Router          $router          Router.
	 * @param MC_Markdown        $markdown        Markdown parser.
	 * @param MC_Shortcodes      $shortcodes      Shortcode engine.
	 * @param MC_Formatter       $formatter       Formatter.
	 * @param MC_Theme_Manager   $themes          Theme manager.
	 * @param MC_Asset_Manager   $assets          Asset manager.
	 * @param MC_User_Manager    $users           User manager.
	 * @param MC_Template_Loader $template_loader Template loader.
	 * @param MC_Settings        $settings        Settings storage.
	 */
	public function __construct(
		MC_Hooks $hooks,
		MC_Router $router,
		MC_Markdown $markdown,
		MC_Shortcodes $shortcodes,
		MC_Formatter $formatter,
		MC_Theme_Manager $themes,
		MC_Asset_Manager $assets,
		MC_User_Manager $users,
		MC_Template_Loader $template_loader,
		MC_Settings $settings
	) {

		$this->hooks           = $hooks;
		$this->router          = $router;
		$this->markdown        = $markdown;
		$this->shortcodes      = $shortcodes;
		$this->formatter       = $formatter;
		$this->themes          = $themes;
		$this->assets          = $assets;
		$this->users           = $users;
		$this->template_loader = $template_loader;
		$this->settings        = $settings;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Head / Footer hooks
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Fire the mc_head action.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function head(): void
	{

		$this->hooks->do_action('mc_head');
	}

	/**
	 * Fire the mc_body_open action.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function body_open(): void
	{

		$this->hooks->do_action('mc_body_open');
	}

	/**
	 * Fire the mc_footer action.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function footer(): void
	{

		$this->hooks->do_action('mc_footer');
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Content output
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the current content item from the query.
	 *
	 * @since {version}
	 *
	 * @return array|null
	 */
	public function get_the_content_item(): ?array
	{

		$query = $this->router->get_query();
		return $query['content'] ?? null;
	}

	/**
	 * Output the content title (escaped).
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function the_title(): void
	{

		echo $this->formatter->esc_html($this->get_the_title());
	}

	/**
	 * Get the content title without echoing.
	 *
	 * @since {version}
	 *
	 * @return string
	 */
	public function get_the_title(): string
	{

		$content = $this->get_the_content_item();
		$title   = $content['title'] ?? '';

		/**
		 * Filter the page title for display.
		 *
		 * @since {version}
		 *
		 * @param string $title Page title.
		 */
		return $this->hooks->apply_filters('mc_the_title', $title);
	}

	/**
	 * Output the rendered content body (Markdown → HTML, shortcodes processed).
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function the_content(): void
	{

		echo $this->get_the_content();
	}

	/**
	 * Get the rendered content HTML without echoing.
	 *
	 * @since {version}
	 *
	 * @return string
	 */
	public function get_the_content(): string
	{

		$content     = $this->get_the_content_item();
		$raw         = $content['body_raw'] ?? '';
		$editor_mode = $this->settings->get('core.general', 'editor_mode', 'markdown');

		if ('text' === $editor_mode) {
			$html = $raw;
		} else {
			$html = $this->markdown->parse($raw);
		}

		$html = $this->shortcodes->do_shortcode($html);

		/**
		 * Filter the content HTML before output.
		 *
		 * @since {version}
		 *
		 * @param string     $html    Rendered HTML.
		 * @param array|null $content Full content array.
		 */
		return $this->hooks->apply_filters('mc_the_content', $html, $content);
	}

	/**
	 * Output the content excerpt.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function the_excerpt(): void
	{

		$content = $this->get_the_content_item();
		$excerpt = $content['excerpt'] ?? '';

		if ('' === $excerpt) {
			$full    = $this->get_the_content();
			$excerpt = mb_substr(strip_tags($full), 0, 160);
		}

		/**
		 * Filter the excerpt before output.
		 *
		 * @since {version}
		 *
		 * @param string $excerpt Excerpt text.
		 */
		echo $this->formatter->esc_html(
			$this->hooks->apply_filters('mc_the_excerpt', $excerpt)
		);
	}

	/**
	 * Output the document title (for the <title> tag).
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function document_title(): void
	{

		$parts = array();

		if ($this->router->is_404()) {
			$parts[] = 'Page Not Found';
		} elseif ($this->router->is_single() || $this->router->is_front_page()) {
			$parts[] = $this->get_the_title();
		} elseif ($this->router->is_archive()) {
			$query = $this->router->get_query();
			$parts[] = ucfirst($query['type'] ?? 'Archive');
		}

		$site_name = defined('MC_SITE_NAME') ? MC_SITE_NAME : '';
		if ('' !== $site_name) {
			$parts[] = $site_name;
		}

		$title = implode(' — ', array_filter($parts));

		/**
		 * Filter the document <title>.
		 *
		 * @since {version}
		 *
		 * @param string $title Full title string.
		 */
		echo $this->formatter->esc_html(
			$this->hooks->apply_filters('mc_document_title', $title)
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Template partials
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Include the header template partial.
	 *
	 * @since {version}
	 *
	 * @param string $name Optional specialised header name.
	 * @return void
	 */
	public function get_header(string $name = ''): void
	{

		$this->hooks->do_action('mc_get_header', $name);

		$templates = array();
		if ('' !== $name) {
			$templates[] = 'header-' . $name . '.php';
		}
		$templates[] = 'header.php';

		$file = $this->template_loader->locate($templates);
		if ('' !== $file) {
			include $file;
		}
	}

	/**
	 * Include the footer template partial.
	 *
	 * @since {version}
	 *
	 * @param string $name Optional specialised footer name.
	 * @return void
	 */
	public function get_footer(string $name = ''): void
	{

		$this->hooks->do_action('mc_get_footer', $name);

		$templates = array();
		if ('' !== $name) {
			$templates[] = 'footer-' . $name . '.php';
		}
		$templates[] = 'footer.php';

		$file = $this->template_loader->locate($templates);
		if ('' !== $file) {
			include $file;
		}
	}

	/**
	 * Include the sidebar template partial.
	 *
	 * @since {version}
	 *
	 * @param string $name Optional specialised sidebar name.
	 * @return void
	 */
	public function get_sidebar(string $name = ''): void
	{

		$this->hooks->do_action('mc_get_sidebar', $name);

		$templates = array();
		if ('' !== $name) {
			$templates[] = 'sidebar-' . $name . '.php';
		}
		$templates[] = 'sidebar.php';

		$file = $this->template_loader->locate($templates);
		if ('' !== $file) {
			include $file;
		}
	}

	/**
	 * Include a generic template part.
	 *
	 * @since {version}
	 *
	 * @param string $slug Template slug.
	 * @param string $name Optional specialisation.
	 * @return void
	 */
	public function get_template_part(string $slug, string $name = ''): void
	{

		$templates = array();
		if ('' !== $name) {
			$templates[] = $slug . '-' . $name . '.php';
		}
		$templates[] = $slug . '.php';

		$file = $this->template_loader->locate($templates);
		if ('' !== $file) {
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
	 * @since {version}
	 *
	 * @param string $extra Optional extra classes.
	 * @return void
	 */
	public function body_class(string $extra = ''): void
	{

		$classes = array();
		$query   = $this->router->get_query();

		if ($this->router->is_front_page()) {
			$classes[] = 'home';
			$classes[] = 'front-page';
		}

		if ($this->router->is_single()) {
			$classes[] = 'single';
			$classes[] = 'single-' . ($query['type'] ?? 'page');
			if (!empty($query['slug'])) {
				$classes[] = 'slug-' . $query['slug'];
			}
		}

		if ($this->router->is_archive()) {
			$classes[] = 'archive';
			$classes[] = 'archive-' . ($query['type'] ?? '');
		}

		if ($this->router->is_404()) {
			$classes[] = 'error404';
		}

		if ($this->users->is_logged_in()) {
			$classes[] = 'logged-in';
		}

		if ('' !== $extra) {
			$classes[] = $extra;
		}

		/**
		 * Filter body CSS classes.
		 *
		 * @since {version}
		 *
		 * @param string[] $classes CSS class names.
		 */
		$classes = $this->hooks->apply_filters('mc_body_class', $classes);

		echo 'class="' . $this->formatter->esc_attr(implode(' ', $classes)) . '"';
	}
}
