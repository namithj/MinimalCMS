<?php

/**
 * MinimalCMS Default Filters
 *
 * Registers the core hooks that wire internal features together.
 * Loaded during bootstrap after all includes but before plugins/themes.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

// Print enqueued styles in <head>.
mc_add_action('mc_head', 'mc_print_styles', 8);

// Print header scripts in <head>.
mc_add_action('mc_head', 'mc_print_head_scripts', 9);

// Print footer scripts before </body>.
mc_add_action('mc_footer', 'mc_print_footer_scripts', 20);

// Process shortcodes in content output.
mc_add_filter('mc_the_content', 'mc_do_shortcode', 11);

// Convert line breaks in content.
mc_add_filter(
	'mc_the_content',
	function (string $html): string {
		// Parsedown already handles this, but ensure consistent line breaks.
		return $html;
	},
	12
);
