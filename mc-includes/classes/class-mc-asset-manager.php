<?php

/**
 * MC_Asset_Manager — Style and script enqueue system.
 *
 * Extracted from template-tags.php. Manages CSS and JS enqueuing and output.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Asset queue manager.
 *
 * @since {version}
 */
class MC_Asset_Manager
{
	/**
	 * Enqueued stylesheets keyed by handle.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $styles = array();

	/**
	 * Enqueued scripts keyed by handle.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $scripts = array();

	/**
	 * Localised script data keyed by handle.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $localisations = array();

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Formatter
	 */
	private MC_Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks     $hooks     Hooks engine.
	 * @param MC_Formatter $formatter Formatter for escaping.
	 */
	public function __construct(MC_Hooks $hooks, MC_Formatter $formatter)
	{

		$this->hooks     = $hooks;
		$this->formatter = $formatter;
	}

	/**
	 * Queue a CSS stylesheet.
	 *
	 * @since {version}
	 *
	 * @param string $handle Unique handle name.
	 * @param string $src    URL to the CSS file.
	 * @param string $media  Media attribute. Default 'all'.
	 * @return void
	 */
	public function enqueue_style(string $handle, string $src, string $media = 'all'): void
	{

		$this->styles[$handle] = array(
			'src'   => $src,
			'media' => $media,
		);
	}

	/**
	 * Queue a JavaScript file.
	 *
	 * @since {version}
	 *
	 * @param string $handle    Unique handle name.
	 * @param string $src       URL to the JS file.
	 * @param bool   $in_footer Whether to output in footer. Default true.
	 * @return void
	 */
	public function enqueue_script(string $handle, string $src, bool $in_footer = true): void
	{

		$this->scripts[$handle] = array(
			'src'       => $src,
			'in_footer' => $in_footer,
		);
	}

	/**
	 * Remove a previously enqueued stylesheet.
	 *
	 * @since {version}
	 *
	 * @param string $handle Handle to dequeue.
	 * @return void
	 */
	public function dequeue_style(string $handle): void
	{

		unset($this->styles[$handle]);
	}

	/**
	 * Remove a previously enqueued script.
	 *
	 * @since {version}
	 *
	 * @param string $handle Handle to dequeue.
	 * @return void
	 */
	public function dequeue_script(string $handle): void
	{

		unset($this->scripts[$handle]);
	}

	/**
	 * Output all enqueued stylesheets as <link> tags.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function print_styles(): void
	{

		/**
		 * Filter the styles array before output.
		 *
		 * @since {version}
		 *
		 * @param array $styles Enqueued styles.
		 */
		$styles = $this->hooks->apply_filters('mc_print_styles', $this->styles);

		foreach ($styles as $handle => $style) {
			printf(
				'<link rel="stylesheet" id="%s-css" href="%s" media="%s" />' . "\n",
				$this->formatter->esc_attr($handle),
				$this->formatter->esc_url($style['src']),
				$this->formatter->esc_attr($style['media'])
			);
		}
	}

	/**
	 * Output header scripts (non-footer scripts).
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function print_head_scripts(): void
	{

		/**
		 * Filter the scripts array before output.
		 *
		 * @since {version}
		 *
		 * @param array $scripts Enqueued scripts.
		 */
		$scripts = $this->hooks->apply_filters('mc_print_scripts', $this->scripts);

		foreach ($scripts as $handle => $script) {
			if (!$script['in_footer']) {
				$this->output_script($handle, $script);
			}
		}
	}

	/**
	 * Output footer scripts.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function print_footer_scripts(): void
	{

		$scripts = $this->hooks->apply_filters('mc_print_scripts', $this->scripts);

		foreach ($scripts as $handle => $script) {
			if ($script['in_footer']) {
				$this->output_script($handle, $script);
			}
		}
	}

	/**
	 * Localise a script handle with data accessible to JS.
	 *
	 * @since {version}
	 *
	 * @param string $handle      Script handle.
	 * @param string $object_name JS variable name.
	 * @param array  $data        Key-value data.
	 * @return void
	 */
	public function localize_script(string $handle, string $object_name, array $data): void
	{

		$this->localisations[$handle] = array(
			'object_name' => $object_name,
			'data'        => $data,
		);
	}

	/**
	 * Output a single script tag with optional localisation.
	 *
	 * @since {version}
	 *
	 * @param string $handle Script handle.
	 * @param array  $script Script definition.
	 * @return void
	 */
	private function output_script(string $handle, array $script): void
	{

		if (isset($this->localisations[$handle])) {
			$l10n = $this->localisations[$handle];
			$json = json_encode($l10n['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			printf(
				'<script id="%s-js-extra">var %s = %s;</script>' . "\n",
				$this->formatter->esc_attr($handle),
				$this->formatter->esc_js($l10n['object_name']),
				$json
			);
		}

		printf(
			'<script id="%s-js" src="%s"></script>' . "\n",
			$this->formatter->esc_attr($handle),
			$this->formatter->esc_url($script['src'])
		);
	}
}
