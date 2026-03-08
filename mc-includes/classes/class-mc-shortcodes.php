<?php
/**
 * MC_Shortcodes — Shortcode registration and processing.
 *
 * Replaces the procedural shortcodes.php. Provides [tag attr="val"]content[/tag]
 * syntax for content templating.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Shortcode engine.
 *
 * @since {version}
 */
class MC_Shortcodes {

	/**
	 * Registered shortcode handlers keyed by tag.
	 *
	 * @since {version}
	 * @var array<string, callable>
	 */
	private array $shortcodes = array();

	/**
	 * Register a shortcode handler.
	 *
	 * @since {version}
	 *
	 * @param string   $tag      Shortcode tag.
	 * @param callable $callback Handler receives ($attrs, $content, $tag).
	 * @return void
	 */
	public function add(string $tag, callable $callback): void {

		$this->shortcodes[$tag] = $callback;
	}

	/**
	 * Remove a registered shortcode.
	 *
	 * @since {version}
	 *
	 * @param string $tag Shortcode tag.
	 * @return void
	 */
	public function remove(string $tag): void {

		unset($this->shortcodes[$tag]);
	}

	/**
	 * Check whether a shortcode is registered.
	 *
	 * @since {version}
	 *
	 * @param string $tag Shortcode tag.
	 * @return bool
	 */
	public function exists(string $tag): bool {

		return isset($this->shortcodes[$tag]);
	}

	/**
	 * Process shortcodes in a string.
	 *
	 * @since {version}
	 *
	 * @param string $content The content to process.
	 * @return string Content with shortcodes replaced.
	 */
	public function do_shortcode(string $content): string {

		if (empty($this->shortcodes)) {
			return $content;
		}

		$tags    = array_keys($this->shortcodes);
		$pattern = $this->get_regex($tags);

		return preg_replace_callback(
			'/' . $pattern . '/s',
			array($this, 'do_shortcode_tag'),
			$content
		);
	}

	/**
	 * Build a regex that matches registered shortcode tags.
	 *
	 * @since {version}
	 *
	 * @param string[] $tags List of shortcode tag names.
	 * @return string Regex pattern (without delimiters).
	 */
	public function get_regex(array $tags): string {

		$tag_list = implode('|', array_map('preg_quote', $tags));

		return '\\['
			. '(' . $tag_list . ')'
			. '(\\b[^\\]\\/]*(?:\\/(?!\\]).)*)' // Attributes.
			. '(?:'
			. '(\\/)'                         // Self-closing.
			. '\\]'
			. '|'
			. '\\]'
			. '(?:'
			. '([^\\[]*(?:\\[(?!\\/\\1\\])[^\\[]*)*)'  // Enclosed content.
			. '\\[\\/\\1\\]'
			. ')?'
			. ')';
	}

	/**
	 * Callback for preg_replace_callback to invoke a shortcode handler.
	 *
	 * @since {version}
	 *
	 * @param array $match Regex match groups.
	 * @return string Replacement string.
	 */
	public function do_shortcode_tag(array $match): string {

		$tag     = $match[1];
		$attrs   = $this->parse_attrs($match[2]);
		$content = $match[4] ?? '';

		if (!isset($this->shortcodes[$tag])) {
			return $match[0];
		}

		return (string) call_user_func($this->shortcodes[$tag], $attrs, $content, $tag);
	}

	/**
	 * Parse shortcode attribute string into an associative array.
	 *
	 * @since {version}
	 *
	 * @param string $text Attribute string.
	 * @return array Key-value pairs.
	 */
	public function parse_attrs(string $text): array {

		$attrs   = array();
		$text    = trim($text);
		$pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*(\S+)(?:\s|$)/';

		if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $m) {
				if (!empty($m[1])) {
					$attrs[$m[1]] = $m[2];
				} elseif (!empty($m[3])) {
					$attrs[$m[3]] = $m[4];
				} elseif (!empty($m[5])) {
					$attrs[$m[5]] = $m[6];
				}
			}
		}

		return $attrs;
	}
}
