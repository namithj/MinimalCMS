<?php

/**
 * MinimalCMS Shortcodes
 *
 * A simple shortcode system allowing [shortcode attr="val"]content[/shortcode] syntax.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Registered shortcode handlers.
 *
 * @global array<string, callable> $mc_shortcodes
 */
global $mc_shortcodes;
$mc_shortcodes = array();

/**
 * Register a shortcode handler.
 *
 * @since 1.0.0
 *
 * @param string   $tag      Shortcode tag (e.g. 'gallery').
 * @param callable $callback Handler receives ($attrs, $content, $tag).
 * @return void
 */
function mc_add_shortcode(string $tag, callable $callback): void
{

	global $mc_shortcodes;
	$mc_shortcodes[ $tag ] = $callback;
}

/**
 * Remove a registered shortcode.
 *
 * @since 1.0.0
 *
 * @param string $tag Shortcode tag.
 * @return void
 */
function mc_remove_shortcode(string $tag): void
{

	global $mc_shortcodes;
	unset($mc_shortcodes[ $tag ]);
}

/**
 * Check whether a shortcode is registered.
 *
 * @since 1.0.0
 *
 * @param string $tag Shortcode tag.
 * @return bool
 */
function mc_shortcode_exists(string $tag): bool
{

	global $mc_shortcodes;
	return isset($mc_shortcodes[ $tag ]);
}

/**
 * Process shortcodes in a string.
 *
 * Supports self-closing [tag attr="val"] and enclosing [tag]content[/tag].
 *
 * @since 1.0.0
 *
 * @param string $content The content to process.
 * @return string Content with shortcodes replaced.
 */
function mc_do_shortcode(string $content): string
{

	global $mc_shortcodes;

	if (empty($mc_shortcodes)) {
		return $content;
	}

	$tags    = array_keys($mc_shortcodes);
	$pattern = mc_get_shortcode_regex($tags);

	return preg_replace_callback('/' . $pattern . '/s', 'mc_do_shortcode_tag', $content);
}

/**
 * Build a regex that matches registered shortcode tags.
 *
 * @since 1.0.0
 *
 * @param string[] $tags List of shortcode tag names.
 * @return string Regex pattern (without delimiters).
 */
function mc_get_shortcode_regex(array $tags): string
{

	$tag_list = implode('|', array_map('preg_quote', $tags));

	// Matches [tag attrs]content[/tag] or self-closing [tag attrs /] or [tag attrs].
	return '\\['
		. '(' . $tag_list . ')'             // 1: Tag name.
		. '(\\b[^\\]\\/]*(?:\\/(?!\\]).)*)' // 2: Attributes.
		. '(?:'
		. '(\\/)'                         // 3: Self-closing slash.
		. '\\]'
		. '|'
		. '\\]'
		. '(?:'
		. '([^\\[]*(?:\\[(?!\\/\\1\\])[^\\[]*)*)'  // 4: Enclosed content.
		. '\\[\\/\\1\\]'
		. ')?'
		. ')';
}

/**
 * Callback for preg_replace_callback to invoke a shortcode handler.
 *
 * @since 1.0.0
 *
 * @param array $match Regex match groups.
 * @return string Replacement string.
 */
function mc_do_shortcode_tag(array $match): string
{

	global $mc_shortcodes;

	$tag     = $match[1];
	$attrs   = mc_parse_shortcode_attrs($match[2]);
	$content = $match[4] ?? '';

	if (! isset($mc_shortcodes[ $tag ])) {
		return $match[0];
	}

	return (string) call_user_func($mc_shortcodes[ $tag ], $attrs, $content, $tag);
}

/**
 * Parse shortcode attribute string into an associative array.
 *
 * @since 1.0.0
 *
 * @param string $text Attribute string from the shortcode tag.
 * @return array Key-value pairs.
 */
function mc_parse_shortcode_attrs(string $text): array
{

	$attrs   = array();
	$text    = trim($text);
	$pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*(\S+)(?:\s|$)/';

	if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $m) {
			if (! empty($m[1])) {
				$attrs[ $m[1] ] = $m[2];
			} elseif (! empty($m[3])) {
				$attrs[ $m[3] ] = $m[4];
			} elseif (! empty($m[5])) {
				$attrs[ $m[5] ] = $m[6];
			}
		}
	}

	return $attrs;
}
