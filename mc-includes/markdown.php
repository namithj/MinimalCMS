<?php
/**
 * MinimalCMS Markdown Parser
 *
 * Wraps the bundled Parsedown library and provides a filterable API.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

// Parsedown is loaded via Composer autoloader.

/**
 * Parse a Markdown string into HTML.
 *
 * @since 1.0.0
 *
 * @param string $markdown Raw Markdown text.
 * @return string Rendered HTML.
 */
function mc_parse_markdown( string $markdown ): string {

	static $parser = null;

	if ( null === $parser ) {
		$parser = new Parsedown();
		$parser->setSafeMode( false );
	}

	$html = $parser->text( $markdown );

	/**
	 * Filter the HTML produced by the Markdown parser.
	 *
	 * Plugins can use this to post-process or replace the rendering engine.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $markdown Original Markdown.
	 */
	return mc_apply_filters( 'mc_parse_markdown', $html, $markdown );
}
