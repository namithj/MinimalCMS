<?php

/**
 * MC_Markdown — Parsedown wrapper.
 *
 * Replaces the procedural markdown.php. Wraps the bundled Parsedown library
 * and provides a filterable parse API.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Markdown parser.
 *
 * @since {version}
 */
class MC_Markdown
{
	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Parsedown instance (lazy-loaded).
	 *
	 * @since {version}
	 * @var \Parsedown|null
	 */
	private ?\Parsedown $parser = null;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks $hooks Hooks engine.
	 */
	public function __construct(MC_Hooks $hooks)
	{

		$this->hooks = $hooks;
	}

	/**
	 * Parse Markdown string to HTML.
	 *
	 * @since {version}
	 *
	 * @param string $markdown Raw Markdown text.
	 * @return string Rendered HTML.
	 */
	public function parse(string $markdown): string
	{

		/**
		 * Filter raw markdown before parsing.
		 *
		 * @since {version}
		 *
		 * @param string $markdown Raw Markdown text.
		 */
		$markdown = $this->hooks->apply_filters('mc_pre_parse_markdown', $markdown);

		if (null === $this->parser) {
			$this->parser = new \Parsedown();
			$this->parser->setSafeMode(false);
		}

		$html = $this->parser->text($markdown);

		/**
		 * Filter the HTML produced by the Markdown parser.
		 *
		 * @since {version}
		 *
		 * @param string $html     Rendered HTML.
		 * @param string $markdown Original Markdown.
		 */
		return $this->hooks->apply_filters('mc_parse_markdown', $html, $markdown);
	}
}
