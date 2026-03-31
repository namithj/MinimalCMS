<?php

/**
 * MinimalCMS Formatter
 *
 * Escaping, sanitisation, and string formatting utilities.
 * Replaces the procedural formatting.php with a hookable class.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_Formatter
 *
 * All escaping and sanitisation methods. Fires filters through MC_Hooks
 * where appropriate so plugins can extend behaviour.
 *
 * @since {version}
 */
class MC_Formatter
{
	/**
	 * The hooks engine.
	 *
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks $hooks The hooks engine.
	 */
	public function __construct(MC_Hooks $hooks)
	{

		$this->hooks = $hooks;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Internal helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Shared htmlspecialchars call used by all output-escaping methods.
	 *
	 * @since {version}
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	private function htmlspecialchars(string $text): string
	{

		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Escaping (output context–specific)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Escape a string for safe output in an HTML context.
	 *
	 * @since {version}
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function esc_html(string $text): string
	{

		return $this->htmlspecialchars($text);
	}

	/**
	 * Escape a string for safe use inside an HTML attribute value.
	 *
	 * @since {version}
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function esc_attr(string $text): string
	{

		return $this->htmlspecialchars($text);
	}

	/**
	 * Escape and validate a URL for safe output in href / src attributes.
	 *
	 * @since {version}
	 *
	 * @param string $url Raw URL.
	 * @return string Sanitised URL or empty string if invalid.
	 */
	public function esc_url(string $url): string
	{

		$url = trim($url);

		if ('' === $url) {
			return '';
		}

		/**
		 * Filter the allowed URL protocols for esc_url.
		 *
		 * @since {version}
		 *
		 * @param string[] $protocols Allowed protocol schemes.
		 */
		$protocols = $this->hooks->apply_filters(
			'mc_esc_url_protocols',
			array('https', 'http', 'mailto', 'tel')
		);

		$pattern = '/^(?:' . implode('|', array_map('preg_quote', $protocols)) . '):/i';

		if (preg_match($pattern, $url)) {
			return $this->htmlspecialchars($url);
		}

		// Relative URLs are fine.
		if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
			return $this->htmlspecialchars($url);
		}

		return '';
	}

	/**
	 * Escape a string for safe inline JavaScript output.
	 *
	 * @since {version}
	 *
	 * @param string $text Raw text.
	 * @return string JSON-encoded string (without surrounding quotes).
	 */
	public function esc_js(string $text): string
	{

		$encoded = json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_AMP | JSON_HEX_QUOT);

		return trim((string) $encoded, '"');
	}

	/**
	 * Escape output for use inside a textarea element.
	 *
	 * @since {version}
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function esc_textarea(string $text): string
	{

		return $this->htmlspecialchars($text);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Sanitisation (input cleaning)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Sanitise a plain-text string (strip tags, normalise whitespace).
	 *
	 * @since {version}
	 *
	 * @param string $text Input text.
	 * @return string Cleaned text.
	 */
	public function sanitize_text(string $text): string
	{

		$text = strip_tags($text);
		$text = preg_replace('/\s+/', ' ', $text);

		return trim($text);
	}

	/**
	 * Sanitise a value into a valid URL slug.
	 *
	 * @since {version}
	 *
	 * @param string $slug Raw slug text.
	 * @return string Cleaned slug (lowercase, hyphens, alphanumeric).
	 */
	public function sanitize_slug(string $slug): string
	{

		$slug = mb_strtolower($slug, 'UTF-8');
		$slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
		$slug = preg_replace('/-+/', '-', $slug);

		return trim($slug, '-');
	}

	/**
	 * Sanitise a filename by removing unsafe characters.
	 *
	 * @since {version}
	 *
	 * @param string $filename Raw filename.
	 * @return string Safe filename.
	 */
	public function sanitize_filename(string $filename): string
	{

		$filename = str_replace(array('../', './'), '', $filename);
		$filename = preg_replace('/[^a-zA-Z0-9._\-]/', '-', $filename);

		return trim($filename, '.-');
	}

	/**
	 * Sanitise and validate an email address.
	 *
	 * @since {version}
	 *
	 * @param string $email Raw email.
	 * @return string Validated email or empty string.
	 */
	public function sanitize_email(string $email): string
	{

		$email = trim($email);
		$clean = filter_var($email, FILTER_SANITIZE_EMAIL);

		if (false === $clean || !filter_var($clean, FILTER_VALIDATE_EMAIL)) {
			return '';
		}

		return $clean;
	}

	/**
	 * Sanitise a string that may contain HTML, keeping only allowed tags.
	 *
	 * Retained tags are scrubbed of on* event-handler attributes and
	 * javascript:/vbscript: URI schemes to prevent XSS.
	 *
	 * @since {version}
	 *
	 * @param string $html         Raw HTML.
	 * @param string $allowed_tags Allowed tag list for strip_tags, e.g. '<p><a><strong>'.
	 * @return string Cleaned HTML.
	 */
	public function sanitize_html(string $html, string $allowed_tags = ''): string
	{

		if ('' === $allowed_tags) {
			/**
			 * Filter the default allowed HTML tags for sanitize_html.
			 *
			 * @since {version}
			 *
			 * @param string $allowed_tags Default allowed tag string.
			 */
			$allowed_tags = $this->hooks->apply_filters(
				'mc_sanitize_html_allowed_tags',
				'<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><h5><h6><blockquote><code><pre><img><table><thead><tbody><tr><th><td><figure><figcaption><span><div><hr><sub><sup><abbr>'
			);
		}

		$html = strip_tags($html, $allowed_tags);

		if ('' === trim($html) || !str_contains($html, '<')) {
			return $html;
		}

		return (string) preg_replace_callback(
			'/<([a-zA-Z][a-zA-Z0-9]*)([^>]*)>/i',
			static function (array $m): string {
				$tag   = $m[1];
				$attrs = $m[2];

				// Remove on* event-handler attributes.
				$attrs = (string) preg_replace(
					'/\s+on[a-zA-Z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i',
					'',
					$attrs
				);

				// Remove href/src/action/formaction with javascript: or vbscript: scheme.
				$attrs = (string) preg_replace(
					'/\s+(href|src|action|formaction)\s*=\s*"[^"]*(?:javascript|vbscript)\s*:[^"]*"/i',
					'',
					$attrs
				);
				$attrs = (string) preg_replace(
					"/\\s+(href|src|action|formaction)\\s*=\\s*'[^']*(?:javascript|vbscript)\\s*:[^']*'/i",
					'',
					$attrs
				);

				return '<' . $tag . $attrs . '>';
			},
			$html
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Miscellaneous helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Convert a title string into a URL-safe slug.
	 *
	 * @since {version}
	 *
	 * @param string $text The title to convert.
	 * @return string The slug.
	 */
	public function slugify(string $text): string
	{

		return $this->sanitize_slug($text);
	}

	/**
	 * Truncate a string to a given number of characters, preserving whole words.
	 *
	 * @since {version}
	 *
	 * @param string $text   The text to truncate.
	 * @param int    $length Maximum character length. Default 150.
	 * @param string $suffix Suffix to append. Default '&hellip;'.
	 * @return string Truncated text.
	 */
	public function truncate(string $text, int $length = 150, string $suffix = '&hellip;'): string
	{

		$text = $this->sanitize_text($text);

		if (mb_strlen($text, 'UTF-8') <= $length) {
			return $text;
		}

		$truncated  = mb_substr($text, 0, $length, 'UTF-8');
		$last_space = mb_strrpos($truncated, ' ', 0, 'UTF-8');

		if (false !== $last_space) {
			$truncated = mb_substr($truncated, 0, $last_space, 'UTF-8');
		}

		return $truncated . $suffix;
	}
}
