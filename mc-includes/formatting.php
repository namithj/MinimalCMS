<?php
/**
 * MinimalCMS Formatting Functions
 *
 * Escaping, sanitisation, and string formatting utilities.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 *  Escaping (output context–specific)
 * -------------------------------------------------------------------------
 */

/**
 * Escape a string for safe output in an HTML context.
 *
 * @since 1.0.0
 *
 * @param string $text Raw text.
 * @return string Escaped text.
 */
function mc_esc_html( string $text ): string {

	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Escape a string for safe use inside an HTML attribute value.
 *
 * @since 1.0.0
 *
 * @param string $text Raw text.
 * @return string Escaped text.
 */
function mc_esc_attr( string $text ): string {

	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Escape and validate a URL for safe output in href / src attributes.
 *
 * @since 1.0.0
 *
 * @param string $url Raw URL.
 * @return string Sanitised URL or empty string if invalid.
 */
function mc_esc_url( string $url ): string {

	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	// Only allow http, https, mailto, and tel schemes.
	if ( preg_match( '/^(?:https?|mailto|tel):/i', $url ) ) {
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	// Relative URLs are fine.
	if ( str_starts_with( $url, '/' ) || str_starts_with( $url, '#' ) || str_starts_with( $url, '?' ) ) {
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	return '';
}

/**
 * Escape a string for safe inline JavaScript output.
 *
 * @since 1.0.0
 *
 * @param string $text Raw text.
 * @return string JSON-encoded string (without surrounding quotes).
 */
function mc_esc_js( string $text ): string {

	$encoded = json_encode( $text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

	// Remove surrounding double quotes added by json_encode.
	return trim( $encoded, '"' );
}

/**
 * Escape output for use inside a <textarea>.
 *
 * @since 1.0.0
 *
 * @param string $text Raw text.
 * @return string Escaped text.
 */
function mc_esc_textarea( string $text ): string {

	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/*
 * -------------------------------------------------------------------------
 *  Sanitisation (input cleaning)
 * -------------------------------------------------------------------------
 */

/**
 * Sanitise a plain-text string (strip tags, normalise whitespace).
 *
 * @since 1.0.0
 *
 * @param string $text Input text.
 * @return string Cleaned text.
 */
function mc_sanitize_text( string $text ): string {

	$text = strip_tags( $text );
	$text = preg_replace( '/\s+/', ' ', $text );

	return trim( $text );
}

/**
 * Sanitise a value into a valid URL slug.
 *
 * @since 1.0.0
 *
 * @param string $slug Raw slug text.
 * @return string Cleaned slug (lowercase, hyphens, alphanumeric).
 */
function mc_sanitize_slug( string $slug ): string {

	$slug = mb_strtolower( $slug, 'UTF-8' );
	$slug = preg_replace( '/[^a-z0-9\-]/', '-', $slug );
	$slug = preg_replace( '/-+/', '-', $slug );

	return trim( $slug, '-' );
}

/**
 * Sanitise a filename by removing unsafe characters.
 *
 * @since 1.0.0
 *
 * @param string $filename Raw filename.
 * @return string Safe filename.
 */
function mc_sanitize_filename( string $filename ): string {

	// Remove path traversal.
	$filename = str_replace( array( '../', './' ), '', $filename );
	$filename = preg_replace( '/[^a-zA-Z0-9._\-]/', '-', $filename );

	return trim( $filename, '.-' );
}

/**
 * Sanitise and validate an email address.
 *
 * @since 1.0.0
 *
 * @param string $email Raw email.
 * @return string Validated email or empty string.
 */
function mc_sanitize_email( string $email ): string {

	$email = trim( $email );
	$clean = filter_var( $email, FILTER_SANITIZE_EMAIL );

	if ( false === $clean || ! filter_var( $clean, FILTER_VALIDATE_EMAIL ) ) {
		return '';
	}

	return $clean;
}

/**
 * Sanitise a string that may contain HTML, keeping only allowed tags.
 *
 * @since 1.0.0
 *
 * @param string $html           Raw HTML.
 * @param string $allowed_tags   Allowed tag list for strip_tags, e.g. '<p><a><strong>'.
 * @return string Cleaned HTML.
 */
function mc_sanitize_html( string $html, string $allowed_tags = '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><h5><h6><blockquote><code><pre>' ): string {

	return strip_tags( $html, $allowed_tags );
}

/*
 * -------------------------------------------------------------------------
 *  Input retrieval
 * -------------------------------------------------------------------------
 */

/**
 * Retrieve and optionally sanitise a value from a superglobal.
 *
 * @since 1.0.0
 *
 * @param string        $key      Parameter key.
 * @param string        $method   'GET', 'POST', 'REQUEST', 'COOKIE', or 'SERVER'.
 * @param callable|null $sanitize Optional sanitisation callback.
 * @return mixed Raw or sanitised value, or null if not present.
 */
function mc_input( string $key, string $method = 'REQUEST', ?callable $sanitize = null ): mixed {

	$source = match ( strtoupper( $method ) ) {
		'GET'     => $_GET,
		'POST'    => $_POST,
		'COOKIE'  => $_COOKIE,
		'SERVER'  => $_SERVER,
		default   => $_REQUEST,
	};

	if ( ! isset( $source[ $key ] ) ) {
		return null;
	}

	$value = $source[ $key ];

	if ( null !== $sanitize ) {
		$value = call_user_func( $sanitize, $value );
	}

	return $value;
}

/*
 * -------------------------------------------------------------------------
 *  Miscellaneous helpers
 * -------------------------------------------------------------------------
 */

/**
 * Convert a title string into a URL-safe slug.
 *
 * @since 1.0.0
 *
 * @param string $title The title to convert.
 * @return string The slug.
 */
function mc_slugify( string $title ): string {

	return mc_sanitize_slug( $title );
}

/**
 * Truncate a string to a given number of characters, preserving whole words.
 *
 * @since 1.0.0
 *
 * @param string $text   The text to truncate.
 * @param int    $length Maximum character length. Default 150.
 * @param string $suffix Suffix to append. Default '&hellip;'.
 * @return string Truncated text.
 */
function mc_truncate( string $text, int $length = 150, string $suffix = '&hellip;' ): string {

	$text = mc_sanitize_text( $text );

	if ( mb_strlen( $text, 'UTF-8' ) <= $length ) {
		return $text;
	}

	$truncated  = mb_substr( $text, 0, $length, 'UTF-8' );
	$last_space = mb_strrpos( $truncated, ' ', 0, 'UTF-8' );

	if ( false !== $last_space ) {
		$truncated = mb_substr( $truncated, 0, $last_space, 'UTF-8' );
	}

	return $truncated . $suffix;
}
