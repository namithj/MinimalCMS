<?php

/**
 * MinimalCMS Procedural API
 *
 * Thin wrappers around MC_App services for backward compatibility.
 * These functions provide the public procedural API used by themes,
 * plugins, and admin files throughout the application.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/*
 * =============================================================================
 *  Bootstrap utilities — available before MC_App boots
 * =============================================================================
 */

/**
 * Check whether a value is an MC_Error instance.
 *
 * @since {version}
 *
 * @param mixed $thing The value to check.
 * @return bool
 */
function mc_is_error(mixed $thing): bool
{
	return $thing instanceof MC_Error;
}

/**
 * Define a constant only if it has not already been defined.
 *
 * @since {version}
 *
 * @param string $name  Constant name.
 * @param mixed  $value Value.
 * @return void
 */
function mc_maybe_define(string $name, mixed $value): void
{
	if (!defined($name)) {
		define($name, $value);
	}
}

/**
 * Append a trailing slash to a string if not already present.
 *
 * @since {version}
 *
 * @param string $value Input string.
 * @return string
 */
function trailingslashit(string $value): string
{
	return rtrim($value, '/\\') . '/';
}

/**
 * Remove trailing slashes from a string.
 *
 * @since {version}
 *
 * @param string $value Input string.
 * @return string
 */
function untrailingslashit(string $value): string
{
	return rtrim($value, '/\\');
}

/**
 * Create a directory (and any parents) if it does not already exist.
 *
 * @since {version}
 *
 * @param string $path Directory path.
 * @param int    $mode Octal permission mode.
 * @return bool True on success (or already exists), false on failure.
 */
function mc_ensure_dir(string $path, int $mode = 0755): bool
{
	if (is_dir($path)) {
		return true;
	}

	return mkdir($path, $mode, true);
}

/**
 * Detect the site root (where mc-load.php lives).
 *
 * @since {version}
 *
 * @return string Absolute path with trailing slash.
 */
function mc_detect_base_path(): string
{
	return defined('MC_ABSPATH') ? MC_ABSPATH : dirname(__DIR__) . '/';
}

/*
 * =============================================================================
 *  App shortcut
 * =============================================================================
 */

/**
 * Get the MC_App singleton instance.
 *
 * @since {version}
 *
 * @return MC_App
 */
function mc_app(): MC_App
{
	return MC_App::instance();
}

/*
 * =============================================================================
 *  URL builders — depend on MC_ constants set during boot
 * =============================================================================
 */

/**
 * Build a fully qualified URL for the site root or a given path.
 *
 * @since {version}
 *
 * @param string $path Optional relative path to append.
 * @return string
 */
function mc_site_url(string $path = ''): string
{
	// Always auto-detect from the current request so the CMS is not
	// tied to a hardcoded URL. Fall back to the configured site_url
	// only in CLI / non-web contexts where HTTP_HOST is unavailable.
	$host = $_SERVER['HTTP_HOST'] ?? '';

	if ('' !== $host) {
		$scheme    = (isset($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
		$base_path = defined('MC_BASE_PATH') ? MC_BASE_PATH : '';
		$base      = $scheme . '://' . $host . $base_path;
	} else {
		$base = defined('MC_SITE_URL') ? MC_SITE_URL : '';
	}

	if ('' === $path) {
		return untrailingslashit($base) . '/';
	}

	return untrailingslashit($base) . '/' . ltrim($path, '/');
}

/**
 * Build a URL within mc-content/.
 *
 * @since {version}
 *
 * @param string $path Optional relative path inside mc-content.
 * @return string
 */
function mc_content_url(string $path = ''): string
{
	$abspath     = mc_detect_base_path();
	$content_dir = defined('MC_CONTENT_DIR') ? MC_CONTENT_DIR : $abspath . 'mc-content/';
	$rel         = ltrim(str_replace($abspath, '', $content_dir), '/\\');

	if ('' === $path) {
		return mc_site_url(trailingslashit($rel));
	}

	return mc_site_url(untrailingslashit($rel) . '/' . ltrim($path, '/'));
}

/**
 * Build a URL within the active theme directory.
 *
 * @since {version}
 *
 * @param string $path Optional path inside the theme directory.
 * @return string
 */
function mc_theme_url(string $path = ''): string
{
	$theme = defined('MC_ACTIVE_THEME') ? MC_ACTIVE_THEME : 'default';

	return mc_content_url('themes/' . $theme . ('' !== $path ? '/' . ltrim($path, '/') : ''));
}

/**
 * Build a URL for an mc-admin page.
 *
 * @since {version}
 *
 * @param string $page Optional admin page path.
 * @return string
 */
function mc_admin_url(string $page = ''): string
{
	return mc_site_url('mc-admin/' . ltrim($page, '/'));
}

/**
 * Get the favicon URL.
 *
 * @since {version}
 *
 * @return string
 */
function mc_favicon_url(): string
{
	$favicon = mc_theme_url('favicon.ico');

	/**
	 * Filter the favicon URL.
	 *
	 * @since {version}
	 *
	 * @param string $favicon URL.
	 */
	return mc_apply_filters('mc_favicon_url', $favicon);
}

/**
 * Get the absolute filesystem path to the active theme directory.
 *
 * @since {version}
 *
 * @return string Path with trailing slash.
 */
function mc_get_active_theme_dir(): string
{
	return mc_app()->themes()->get_active_dir();
}

/*
 * =============================================================================
 *  Hooks API
 * =============================================================================
 */

/**
 * Add a callback to a filter hook.
 *
 * @since {version}
 *
 * @param string   $tag           Filter tag.
 * @param callable $callback      Callback to add.
 * @param int      $priority      Priority (default 10).
 * @param int      $accepted_args Number of accepted args (default 1).
 * @return void
 */
function mc_add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	mc_app()->hooks()->add_filter($tag, $callback, $priority, $accepted_args);
}

/**
 * Apply filters to a value.
 *
 * @since {version}
 *
 * @param string $tag   Filter tag.
 * @param mixed  $value Value to filter.
 * @param mixed  ...$extra Additional arguments.
 * @return mixed Filtered value.
 */
function mc_apply_filters(string $tag, mixed $value, mixed ...$extra): mixed
{
	return mc_app()->hooks()->apply_filters($tag, $value, ...$extra);
}

/**
 * Remove a callback from a filter hook.
 *
 * @since {version}
 *
 * @param string   $tag      Filter tag.
 * @param callable $callback Callback to remove.
 * @param int      $priority Priority.
 * @return void
 */
function mc_remove_filter(string $tag, callable $callback, int $priority = 10): void
{
	mc_app()->hooks()->remove_filter($tag, $callback, $priority);
}

/**
 * Check whether a filter has callbacks for a tag.
 *
 * @since {version}
 *
 * @param string        $tag      Filter tag.
 * @param callable|null $callback Optional specific callback to check.
 * @return bool
 */
function mc_has_filter(string $tag, ?callable $callback = null): bool
{
	return mc_app()->hooks()->has_filter($tag, $callback);
}

/**
 * Add a callback to an action hook.
 *
 * @since {version}
 *
 * @param string   $tag           Action tag.
 * @param callable $callback      Callback to add.
 * @param int      $priority      Priority (default 10).
 * @param int      $accepted_args Number of accepted args (default 1).
 * @return void
 */
function mc_add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
	mc_app()->hooks()->add_action($tag, $callback, $priority, $accepted_args);
}

/**
 * Fire an action hook.
 *
 * @since {version}
 *
 * @param string $tag   Action tag.
 * @param mixed  ...$args Arguments to pass.
 * @return void
 */
function mc_do_action(string $tag, mixed ...$args): void
{
	mc_app()->hooks()->do_action($tag, ...$args);
}

/**
 * Remove a callback from an action hook.
 *
 * @since {version}
 *
 * @param string   $tag      Action tag.
 * @param callable $callback Callback to remove.
 * @param int      $priority Priority.
 * @return void
 */
function mc_remove_action(string $tag, callable $callback, int $priority = 10): void
{
	mc_app()->hooks()->remove_action($tag, $callback, $priority);
}

/**
 * Check whether an action has callbacks for a tag.
 *
 * @since {version}
 *
 * @param string        $tag      Action tag.
 * @param callable|null $callback Optional specific callback.
 * @return bool
 */
function mc_has_action(string $tag, ?callable $callback = null): bool
{
	return mc_app()->hooks()->has_action($tag, $callback);
}

/**
 * Get the number of times an action has fired.
 *
 * @since {version}
 *
 * @param string $tag Action tag.
 * @return int
 */
function mc_did_action(string $tag): int
{
	return mc_app()->hooks()->did_action($tag);
}

/**
 * Check whether a filter has been applied.
 *
 * @since {version}
 *
 * @param string $tag Filter tag.
 * @return int Number of times fired.
 */
function mc_did_filter(string $tag): int
{
	return mc_app()->hooks()->did_filter($tag);
}

/**
 * Check whether an action is currently executing.
 *
 * @since {version}
 *
 * @param string|null $tag Action tag or null to check any.
 * @return bool
 */
function mc_doing_action(?string $tag = null): bool
{
	return mc_app()->hooks()->doing_action($tag);
}

/**
 * Check whether a filter is currently executing.
 *
 * @since {version}
 *
 * @param string|null $tag Filter tag or null to check any.
 * @return bool
 */
function mc_doing_filter(?string $tag = null): bool
{
	return mc_app()->hooks()->doing_filter($tag);
}

/**
 * Get the name of the currently executing filter.
 *
 * @since {version}
 *
 * @return string
 */
function mc_current_filter(): string
{
	return mc_app()->hooks()->current_filter();
}

/*
 * =============================================================================
 *  Formatting — escaping and sanitisation
 * =============================================================================
 */

/**
 * Escape a string for safe HTML output.
 *
 * @since {version}
 *
 * @param string $value Raw value.
 * @return string
 */
function mc_esc_html(string $value): string
{
	return mc_app()->formatter()->esc_html($value);
}

/**
 * Escape a string for use in an HTML attribute.
 *
 * @since {version}
 *
 * @param string $value Raw value.
 * @return string
 */
function mc_esc_attr(string $value): string
{
	return mc_app()->formatter()->esc_attr($value);
}

/**
 * Escape a URL for safe HTML output.
 *
 * @since {version}
 *
 * @param string $value Raw URL.
 * @return string
 */
function mc_esc_url(string $value): string
{
	return mc_app()->formatter()->esc_url($value);
}

/**
 * Escape a value for safe use inside a JavaScript string.
 *
 * @since {version}
 *
 * @param string $value Raw value.
 * @return string
 */
function mc_esc_js(string $value): string
{
	return mc_app()->formatter()->esc_js($value);
}

/**
 * Escape a string for use inside a <textarea>.
 *
 * @since {version}
 *
 * @param string $value Raw value.
 * @return string
 */
function mc_esc_textarea(string $value): string
{
	return mc_app()->formatter()->esc_textarea($value);
}

/**
 * Sanitise plain text (strip all tags).
 *
 * @since {version}
 *
 * @param string $value Raw input.
 * @return string
 */
function mc_sanitize_text(string $value): string
{
	return mc_app()->formatter()->sanitize_text($value);
}

/**
 * Sanitise a URL slug.
 *
 * @since {version}
 *
 * @param string $value Raw slug.
 * @return string
 */
function mc_sanitize_slug(string $value): string
{
	return mc_app()->formatter()->sanitize_slug($value);
}

/**
 * Sanitise a filename.
 *
 * @since {version}
 *
 * @param string $value Raw filename.
 * @return string
 */
function mc_sanitize_filename(string $value): string
{
	return mc_app()->formatter()->sanitize_filename($value);
}

/**
 * Sanitise an email address.
 *
 * @since {version}
 *
 * @param string $value Raw email.
 * @return string
 */
function mc_sanitize_email(string $value): string
{
	return mc_app()->formatter()->sanitize_email($value);
}

/**
 * Sanitise HTML content (allow basic tags).
 *
 * @since {version}
 *
 * @param string $value Raw HTML.
 * @return string
 */
function mc_sanitize_html(string $value): string
{
	return mc_app()->formatter()->sanitize_html($value);
}

/**
 * Convert a string to a URL-safe slug.
 *
 * @since {version}
 *
 * @param string $value Input string.
 * @return string
 */
function mc_slugify(string $value): string
{
	return mc_app()->formatter()->slugify($value);
}

/**
 * Truncate a string to a maximum length.
 *
 * @since {version}
 *
 * @param string $value  Input string.
 * @param int    $length Maximum character length.
 * @param string $more   Append string when truncated.
 * @return string
 */
function mc_truncate(string $value, int $length = 55, string $more = '…'): string
{
	return mc_app()->formatter()->truncate($value, $length, $more);
}

/*
 * =============================================================================
 *  HTTP — nonces, redirects, JSON, request helpers
 * =============================================================================
 */

/**
 * Read a value from a request superglobal, with optional sanitisation.
 *
 * @since {version}
 *
 * @param string        $key      Request parameter name.
 * @param string        $method   'GET', 'POST', 'REQUEST', 'COOKIE', or 'SERVER'. Default 'REQUEST'.
 * @param callable|null $sanitize Optional sanitisation callback applied to the raw value.
 * @return mixed Raw or sanitised value, or null if the key is not present.
 */
function mc_input(string $key, string $method = 'REQUEST', ?callable $sanitize = null): mixed
{
	return mc_app()->http()->input($key, $method, $sanitize);
}

/**
 * Create a nonce string for the given action.
 *
 * @since {version}
 *
 * @param string $action Nonce action name.
 * @return string
 */
function mc_create_nonce(string $action): string
{
	return mc_app()->http()->create_nonce($action);
}

/**
 * Verify a nonce value.
 *
 * @since {version}
 *
 * @param string $nonce  Nonce to verify.
 * @param string $action Expected action name.
 * @return bool
 */
function mc_verify_nonce(string $nonce, string $action): bool
{
	return mc_app()->http()->verify_nonce($nonce, $action);
}

/**
 * Get the current nonce tick.
 *
 * @since {version}
 *
 * @return int
 */
function mc_nonce_tick(): int
{
	return mc_app()->http()->nonce_tick();
}

/**
 * Output a hidden nonce field.
 *
 * @since {version}
 *
 * @param string $action  Nonce action name.
 * @param string $name    Input field name.
 * @param bool   $display Whether to echo (true) or return (false).
 * @return string
 */
function mc_nonce_field(string $action, string $name = '_mc_nonce', bool $display = true): string
{
	$nonce = mc_create_nonce($action);
	$field = '<input type="hidden" name="' . mc_esc_attr($name) . '" value="' . mc_esc_attr($nonce) . '" />' . "\n";
	if ($display) {
		echo $field;
	}
	return $field;
}

/**
 * Append a nonce query parameter to a URL.
 *
 * @since {version}
 *
 * @param string $url    Base URL.
 * @param string $action Nonce action name.
 * @param string $name   Parameter name.
 * @return string
 */
function mc_nonce_url(string $url, string $action, string $name = '_mc_nonce'): string
{
	return mc_app()->http()->nonce_url($url, $action, $name);
}

/**
 * Redirect to a URL.
 *
 * @since {version}
 *
 * @param string $url    Destination URL.
 * @param int    $status HTTP status code.
 * @return void
 */
function mc_redirect(string $url, int $status = 302): void
{
	mc_app()->http()->redirect($url, $status);
}

/**
 * Redirect only to local URLs (prevents open-redirect).
 *
 * @since {version}
 *
 * @param string $url     Destination URL.
 * @param string $default Fallback URL if $url is external.
 * @param int    $status  HTTP status code.
 * @return void
 */
function mc_safe_redirect(string $url, string $default = '', int $status = 302): void
{
	mc_app()->http()->safe_redirect($url, $default, $status);
}

/**
 * Get the current HTTP request method.
 *
 * @since {version}
 *
 * @return string Uppercase method name (e.g. 'GET', 'POST').
 */
function mc_request_method(): string
{
	return mc_app()->http()->request_method();
}

/**
 * Check whether the current request is a POST.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_post_request(): bool
{
	return mc_app()->http()->is_post_request();
}

/**
 * Check whether the current request is an AJAX request.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_ajax_request(): bool
{
	return mc_app()->http()->is_ajax_request();
}

/**
 * Send a JSON response.
 *
 * @since {version}
 *
 * @param mixed $data    Data to encode.
 * @param int   $status  HTTP status code.
 * @return void
 */
function mc_send_json(mixed $data, int $status = 200): void
{
	mc_app()->http()->send_json($data, $status);
}

/**
 * Send a JSON success response.
 *
 * @since {version}
 *
 * @param mixed $data   Optional data payload.
 * @param int   $status HTTP status code.
 * @return void
 */
function mc_send_json_success(mixed $data = null, int $status = 200): void
{
	mc_app()->http()->send_json_success($data, $status);
}

/**
 * Send a JSON error response.
 *
 * @since {version}
 *
 * @param mixed $data   Optional data payload.
 * @param int   $status HTTP status code.
 * @return void
 */
function mc_send_json_error(mixed $data = null, int $status = 400): void
{
	mc_app()->http()->send_json_error($data, $status);
}

/**
 * Send a 404 Not Found response and stop execution.
 *
 * @since {version}
 *
 * @return void
 */
function mc_send_404(): void
{
	mc_app()->http()->send_404();
}

/**
 * Send no-cache HTTP headers.
 *
 * @since {version}
 *
 * @return void
 */
function mc_no_cache_headers(): void
{
	mc_app()->http()->no_cache_headers();
}

/*
 * =============================================================================
 *  Cache
 * =============================================================================
 */

/**
 * Retrieve an item from the object cache.
 *
 * @since {version}
 *
 * @param string $key   Cache key.
 * @param string $group Cache group.
 * @return mixed|false The cached value or false if not found.
 */
function mc_cache_get(string $key, string $group = 'default'): mixed
{
	return mc_app()->cache()->get($key, $group);
}

/**
 * Store an item in the object cache.
 *
 * @since {version}
 *
 * @param string $key        Cache key.
 * @param mixed  $value      Value to cache.
 * @param string $group      Cache group.
 * @param int    $expiration Seconds until expiry (0 = unlimited).
 * @return bool
 */
function mc_cache_set(string $key, mixed $value, string $group = 'default', int $expiration = 0): bool
{
	return mc_app()->cache()->set($key, $value, $group, $expiration);
}

/**
 * Delete an item from the object cache.
 *
 * @since {version}
 *
 * @param string $key   Cache key.
 * @param string $group Cache group.
 * @return bool
 */
function mc_cache_delete(string $key, string $group = 'default'): bool
{
	return mc_app()->cache()->delete($key, $group);
}

/**
 * Flush the entire object cache.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_cache_flush(): bool
{
	return mc_app()->cache()->flush();
}

/**
 * Recursively remove a directory and all its contents.
 *
 * @since {version}
 *
 * @param string $dir Path to the directory.
 * @return bool
 */
function mc_rmdir_recursive(string $dir): bool
{
	if (!is_dir($dir)) {
		return false;
	}

	$items = scandir($dir);
	if (false === $items) {
		return false;
	}

	foreach ($items as $item) {
		if ('.' === $item || '..' === $item) {
			continue;
		}

		$path = $dir . DIRECTORY_SEPARATOR . $item;

		if (is_dir($path)) {
			mc_rmdir_recursive($path);
		} else {
			unlink($path);
		}
	}

	return rmdir($dir);
}

/*
 * =============================================================================
 *  Capabilities
 * =============================================================================
 */

/**
 * Add a new role.
 *
 * @since {version}
 *
 * @param string $role         Role slug.
 * @param string $display_name Human-readable name.
 * @param array  $caps         Initial capabilities array.
 * @return void
 */
function mc_add_role(string $role, string $display_name, array $caps = array()): void
{
	mc_app()->capabilities()->add_role($role, $display_name, $caps);
}

/**
 * Remove a role.
 *
 * @since {version}
 *
 * @param string $role Role slug.
 * @return void
 */
function mc_remove_role(string $role): void
{
	mc_app()->capabilities()->remove_role($role);
}

/**
 * Get a role definition.
 *
 * @since {version}
 *
 * @param string $role Role slug.
 * @return array|null
 */
function mc_get_role(string $role): ?array
{
	return mc_app()->capabilities()->get_role($role);
}

/**
 * Get all registered roles.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_roles(): array
{
	return mc_app()->capabilities()->get_roles();
}

/**
 * Add a capability to a role.
 *
 * @since {version}
 *
 * @param string $role Role slug.
 * @param string $cap  Capability name.
 * @return void
 */
function mc_add_cap(string $role, string $cap): void
{
	mc_app()->capabilities()->add_cap($role, $cap);
}

/**
 * Remove a capability from a role.
 *
 * @since {version}
 *
 * @param string $role Role slug.
 * @param string $cap  Capability name.
 * @return void
 */
function mc_remove_cap(string $role, string $cap): void
{
	mc_app()->capabilities()->remove_cap($role, $cap);
}

/**
 * Check whether a role has a given capability.
 *
 * @since {version}
 *
 * @param string $role Role slug.
 * @param string $cap  Capability name.
 * @return bool
 */
function mc_role_has_cap(string $role, string $cap): bool
{
	return mc_app()->capabilities()->role_has_cap($role, $cap);
}

/**
 * Check whether a specific user has a capability.
 *
 * @since {version}
 *
 * @param string $username Username.
 * @param string $cap      Capability name.
 * @return bool
 */
function mc_user_can(string $username, string $cap): bool
{
	return mc_app()->capabilities()->user_can($username, $cap);
}

/**
 * Check whether the currently logged-in user has a capability.
 *
 * @since {version}
 *
 * @param string $cap Capability name.
 * @return bool
 */
function mc_current_user_can(string $cap): bool
{
	return mc_app()->users()->current_user_can($cap);
}

/*
 * =============================================================================
 *  Users & Sessions
 * =============================================================================
 */

/**
 * Get a user by username.
 *
 * @since {version}
 *
 * @param string $username Username.
 * @return array|null User data or null if not found.
 */
function mc_get_user(string $username): ?array
{
	return mc_app()->users()->get_user($username);
}

/**
 * Get a user by email address.
 *
 * @since {version}
 *
 * @param string $email Email address.
 * @return array|null
 */
function mc_get_user_by_email(string $email): ?array
{
	return mc_app()->users()->get_user_by_email($email);
}

/**
 * Create a new user account.
 *
 * @since {version}
 *
 * @param array $data User data (username, password, email, role, …).
 * @return true|MC_Error
 */
function mc_create_user(array $data): true|MC_Error
{
	return mc_app()->users()->create_user($data);
}

/**
 * Update an existing user account.
 *
 * @since {version}
 *
 * @param string $username Username of the user to update.
 * @param array  $data     Fields to update.
 * @return true|MC_Error
 */
function mc_update_user(string $username, array $data): true|MC_Error
{
	return mc_app()->users()->update_user($username, $data);
}

/**
 * Delete a user account.
 *
 * @since {version}
 *
 * @param string $username Username.
 * @return true|MC_Error
 */
function mc_delete_user(string $username): true|MC_Error
{
	return mc_app()->users()->delete_user($username);
}

/**
 * Get all users.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_users(): array
{
	return mc_app()->users()->get_users();
}

/**
 * Authenticate a user by username and password.
 *
 * @since {version}
 *
 * @param string $username Username.
 * @param string $password Plain-text password.
 * @return array|MC_Error User data array on success, MC_Error on failure.
 */
function mc_authenticate(string $username, string $password): array|MC_Error
{
	return mc_app()->users()->authenticate($username, $password);
}

/**
 * Check whether a user is currently logged in.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_logged_in(): bool
{
	return mc_app()->users()->is_logged_in();
}

/**
 * Get the currently logged-in user data.
 *
 * @since {version}
 *
 * @return array|null
 */
function mc_get_current_user(): ?array
{
	return mc_app()->users()->get_current_user();
}

/**
 * Get the currently logged-in user's username.
 *
 * @since {version}
 *
 * @return string Empty string if not logged in.
 */
function mc_get_current_user_id(): string
{
	return mc_app()->users()->get_current_user_id();
}

/**
 * Read the raw users file.
 *
 * @since {version}
 *
 * @return array
 */
function mc_read_users(): array
{
	return mc_app()->users()->read_users();
}

/**
 * Write a full users array to storage.
 *
 * @since {version}
 *
 * @param array $users Users array.
 * @return true|MC_Error
 */
function mc_write_users(array $users): true|MC_Error
{
	return mc_app()->users()->write_users($users);
}

/**
 * Get the login page URL.
 *
 * @since {version}
 *
 * @param string $redirect Optional redirect URL after login.
 * @return string
 */
function mc_login_url(string $redirect = ''): string
{
	$url = mc_admin_url('login.php');

	if ('' !== $redirect) {
		$url .= '?redirect_to=' . rawurlencode($redirect);
	}

	return $url;
}

/**
 * Get the logout URL.
 *
 * @since {version}
 *
 * @return string
 */
function mc_logout_url(): string
{
	return mc_app()->users()->logout_url();
}

/**
 * Start the PHP session.
 *
 * @since {version}
 *
 * @return void
 */
function mc_start_session(): void
{
	mc_app()->session()->start();
}

/**
 * Set authentication credentials in the session.
 *
 * @since {version}
 *
 * @param string $username Username.
 * @return void
 */
function mc_set_auth_session(string $username): void
{
	mc_app()->session()->set_auth($username);
}

/**
 * Destroy the current session.
 *
 * @since {version}
 *
 * @return void
 */
function mc_destroy_session(): void
{
	mc_app()->session()->destroy();
}

/*
 * =============================================================================
 *  Field Registry
 * =============================================================================
 */

/**
 * Register a custom field type.
 *
 * @since {version}
 *
 * @param string $type_slug Unique field type slug.
 * @param array  $args      Type definition (render, sanitize, validate callbacks).
 * @return void
 */
function mc_register_field_type(string $type_slug, array $args): void
{
	mc_app()->fields()->register_type($type_slug, $args);
}

/**
 * Get a registered field type definition.
 *
 * @since {version}
 *
 * @param string $type_slug Field type slug.
 * @return array|null
 */
function mc_get_field_type(string $type_slug): ?array
{
	return mc_app()->fields()->get_type($type_slug);
}

/**
 * Get all registered field types.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_field_types(): array
{
	return mc_app()->fields()->get_types();
}

/**
 * Render a single field using its registered renderer.
 *
 * @since {version}
 *
 * @param array  $field Field definition.
 * @param mixed  $value Current value.
 * @param string|null $error Validation error message.
 * @return void
 */
function mc_render_field(array $field, mixed $value = '', ?string $error = null): void
{
	mc_app()->fields()->render($field, $value, $error);
}

/**
 * Sanitise a single field value.
 *
 * @since {version}
 *
 * @param array $field Field definition.
 * @param mixed $value Raw value.
 * @return mixed
 */
function mc_sanitize_field(array $field, mixed $value): mixed
{
	return mc_app()->fields()->sanitize($field['type'] ?? 'text', $value, $field);
}

/**
 * Validate a single field value.
 *
 * @since {version}
 *
 * @param array $field Field definition.
 * @param mixed $value Value to validate.
 * @return string Empty string on pass, error message on failure.
 */
function mc_validate_field(array $field, mixed $value): string
{
	$result = mc_app()->fields()->validate($field['type'] ?? 'text', $value, $field);
	return true === $result ? '' : $result;
}

/**
 * Process (sanitise + validate) a set of fields against raw input.
 *
 * @since {version}
 *
 * @param array $fields field_id => field definition map.
 * @param array $raw    field_id => raw value map.
 * @return array{values: array, errors: array}
 */
function mc_process_fields(array $fields, array $raw): array
{
	return mc_app()->fields()->process($fields, $raw);
}

/**
 * Default field sanitiser (strips tags).
 *
 * @since {version}
 *
 * @param array $field Field definition.
 * @param mixed $value Raw value.
 * @return mixed
 */
function mc_field_default_sanitize(array $field, mixed $value): mixed
{
	return mc_app()->fields()->default_sanitize($field, $value);
}

/**
 * Default field validator (checks required).
 *
 * @since {version}
 *
 * @param array $field Field definition.
 * @param mixed $value Sanitised value.
 * @return string
 */
function mc_field_default_validate(array $field, mixed $value): string
{
	return mc_app()->fields()->default_validate($field, $value);
}

/**
 * Register core field types (text, textarea, url, email, number, checkbox, select, hidden).
 *
 * @since {version}
 *
 * @return void
 */
function mc_register_core_field_types(): void
{
	mc_app()->fields()->register_core_types();
}

/**
 * Build an HTML attribute string from an associative array.
 *
 * @since {version}
 *
 * @param array $attrs Attribute key => value pairs.
 * @return string
 */
function mc_build_field_attributes(array $attrs): string
{
	return mc_app()->fields()->build_attributes($attrs);
}

/*
 * =============================================================================
 *  Settings storage
 * =============================================================================
 */

/**
 * Get all settings values for a namespace.
 *
 * @since {version}
 *
 * @param string $namespace Settings namespace (e.g. 'core.general').
 * @return array
 */
function mc_get_settings(string $namespace): array
{
	return mc_app()->settings()->get_all($namespace);
}

/**
 * Get a single setting value.
 *
 * @since {version}
 *
 * @param string $namespace Settings namespace.
 * @param string $key       Setting key.
 * @param mixed  $default   Default if key is not set.
 * @return mixed
 */
function mc_get_setting(string $namespace, string $key, mixed $default = ''): mixed
{
	return mc_app()->settings()->get($namespace, $key, $default);
}

/**
 * Update settings in a namespace (merges with existing values).
 *
 * @since {version}
 *
 * @param string $namespace Settings namespace.
 * @param array  $values    Key => value pairs to persist.
 * @return true|MC_Error
 */
function mc_update_settings(string $namespace, array $values): true|MC_Error
{
	return mc_app()->settings()->update($namespace, $values);
}

/**
 * Delete a single key from a settings namespace.
 *
 * @since {version}
 *
 * @param string $namespace Settings namespace.
 * @param string $key       Key to delete.
 * @return true|MC_Error
 */
function mc_delete_setting(string $namespace, string $key): true|MC_Error
{
	return mc_app()->settings()->delete_key($namespace, $key);
}

/**
 * Delete an entire settings namespace file.
 *
 * @since {version}
 *
 * @param string $namespace Settings namespace.
 * @return true|MC_Error
 */
function mc_delete_settings(string $namespace): true|MC_Error
{
	return mc_app()->settings()->delete($namespace);
}

/**
 * Get the absolute path to a settings file.
 *
 * @since {version}
 *
 * @param string $namespace Settings namespace.
 * @return string
 */
function mc_settings_path(string $namespace): string
{
	return mc_app()->settings()->path($namespace);
}

/*
 * =============================================================================
 *  Settings Registry — pages, sections, fields
 * =============================================================================
 */

/**
 * Register a settings page.
 *
 * @since {version}
 *
 * @param string $page_slug Page slug.
 * @param array  $args      Page definition.
 * @return void
 */
function mc_register_settings_page(string $page_slug, array $args = array()): void
{
	mc_app()->settings_registry()->register_page($page_slug, $args);
}

/**
 * Get a registered settings page.
 *
 * @since {version}
 *
 * @param string $page_slug Page slug.
 * @return array|null
 */
function mc_get_settings_page(string $page_slug): ?array
{
	return mc_app()->settings_registry()->get_page($page_slug);
}

/**
 * Get all registered settings pages.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_settings_pages(): array
{
	return mc_app()->settings_registry()->get_pages();
}

/**
 * Register a section within a settings page.
 *
 * @since {version}
 *
 * @param string $page_slug  Page slug.
 * @param string $section_id Section identifier.
 * @param array  $args       Section definition.
 * @return void
 */
function mc_register_settings_section(string $page_slug, string $section_id, array $args = array()): void
{
	mc_app()->settings_registry()->register_section($page_slug, $section_id, $args);
}

/**
 * Register a field within a settings page section.
 *
 * @since {version}
 *
 * @param string $page_slug  Page slug.
 * @param string $section_id Section identifier.
 * @param string $field_id   Field identifier.
 * @param array  $args       Field definition.
 * @return void
 */
function mc_register_setting_field(string $page_slug, string $section_id, string $field_id, array $args = array()): void
{
	mc_app()->settings_registry()->register_field($page_slug, $section_id, $field_id, $args);
}

/**
 * Get all sections for a settings page.
 *
 * @since {version}
 *
 * @param string $page_slug Page slug.
 * @return array
 */
function mc_get_settings_page_sections(string $page_slug): array
{
	return mc_app()->settings_registry()->get_sections($page_slug);
}

/**
 * Get all fields for a given page and section.
 *
 * @since {version}
 *
 * @param string $page_slug  Page slug.
 * @param string $section_id Section identifier.
 * @return array
 */
function mc_get_settings_section_fields(string $page_slug, string $section_id): array
{
	return mc_app()->settings_registry()->get_section_fields($page_slug, $section_id);
}

/**
 * Get all fields for a settings page (across all sections).
 *
 * @since {version}
 *
 * @param string $page_slug Page slug.
 * @return array
 */
function mc_get_settings_page_fields(string $page_slug): array
{
	return mc_app()->settings_registry()->get_page_fields($page_slug);
}

/**
 * Get current values for a settings page.
 *
 * @since {version}
 *
 * @param string $page_slug Page slug.
 * @return array
 */
function mc_get_settings_page_values(string $page_slug): array
{
	return mc_app()->settings_registry()->get_page_values($page_slug);
}

/**
 * Render a settings page form.
 *
 * @since {version}
 *
 * @param string $page_slug   Page slug.
 * @param array  $values      Current values.
 * @param array  $errors      Validation errors.
 * @param string $notice      Optional notice message.
 * @param string $notice_type Notice type ('success', 'error').
 * @return void
 */
function mc_render_settings_page(
	string $page_slug,
	array $values = array(),
	array $errors = array(),
	string $notice = '',
	string $notice_type = 'success'
): void {
	mc_app()->settings_registry()->render_page($page_slug, $values, $errors, $notice, $notice_type);
}

/**
 * Handle a settings page POST submission.
 *
 * @since {version}
 *
 * @param string $page_slug Page slug.
 * @param array  $post_data Raw POST data (usually $_POST).
 * @return array{saved: bool, values: array, errors: array, notice: string, notice_type: string}
 */
function mc_handle_settings_post(string $page_slug, array $post_data): array
{
	return mc_app()->settings_registry()->handle_post($page_slug, $post_data);
}

/**
 * Register the core (built-in) settings pages.
 *
 * @since {version}
 *
 * @return void
 */
function mc_register_core_settings_pages(): void
{
	mc_app()->settings_registry()->register_core_pages();
}

/**
 * Populate the front_page select field with live page choices.
 *
 * @since {version}
 *
 * @return void
 */
function mc_populate_front_page_choices(): void
{
	mc_app()->settings_registry()->populate_front_page_choices(mc_app()->content());
}

/**
 * Sync core settings back to config.json after save.
 *
 * @since {version}
 *
 * @param string $page_slug Saved page slug.
 * @param array  $values    Saved values.
 * @return void
 */
function mc_sync_core_settings_to_config(string $page_slug, array $values): void
{
	if ('general' !== $page_slug) {
		return;
	}

	$config = mc_app()->config();

	$config_keys = array(
		'site_name', 'site_description', 'site_url', 'timezone',
		'front_page', 'posts_per_page', 'permalink_structure', 'debug',
	);

	foreach ($config_keys as $key) {
		if (array_key_exists($key, $values)) {
			$config->set($key, $values[$key]);
		}
	}

	$config->save();
}

/**
 * Seed core settings from config.json on first run.
 *
 * @since {version}
 *
 * @return void
 */
function mc_maybe_seed_core_settings(): void
{
	$path = mc_settings_path('core.general');

	if (is_file($path)) {
		return;
	}

	$config = mc_app()->config();

	$seed_keys = array(
		'site_name', 'site_description', 'site_url', 'timezone',
		'front_page', 'posts_per_page', 'permalink_structure', 'debug',
	);

	$seed = array();
	foreach ($seed_keys as $key) {
		$val = $config->get($key);
		if (null !== $val) {
			$seed[$key] = $val;
		}
	}

	if (!empty($seed)) {
		mc_update_settings('core.general', $seed);
	}
}

/**
 * Persist the current config to config.json.
 *
 * @since {version}
 *
 * @param array $data Optional data to merge into config before saving.
 * @return bool
 */
function mc_save_config(array $data = array()): bool
{
	$config = mc_app()->config();

	foreach ($data as $key => $value) {
		$config->set($key, $value);
	}

	return $config->save();
}

/*
 * =============================================================================
 *  Content types & content CRUD
 * =============================================================================
 */

/**
 * Register a content type.
 *
 * @since {version}
 *
 * @param string $slug Content type slug.
 * @param array  $args Content type definition.
 * @return void
 */
function mc_register_content_type(string $slug, array $args = array()): void
{
	mc_app()->content_types()->register($slug, $args);
}

/**
 * Get a registered content type definition.
 *
 * @since {version}
 *
 * @param string $slug Content type slug.
 * @return array|null
 */
function mc_get_content_type(string $slug): ?array
{
	return mc_app()->content_types()->get($slug);
}

/**
 * Get all registered content types.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_content_types(): array
{
	return mc_app()->content_types()->all();
}

/**
 * Get the filesystem folder for a content type.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @return string
 */
function mc_content_type_folder(string $type): string
{
	return mc_app()->content_types()->type_folder($type);
}

/**
 * Get the absolute directory path for a content item.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string Path with trailing slash.
 */
function mc_content_item_dir(string $type, string $slug): string
{
	return mc_content_type_folder($type) . mc_sanitize_slug($slug) . '/';
}

/**
 * Get the absolute path to a content item's Markdown body file.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string
 */
function mc_content_md_path(string $type, string $slug): string
{
	return mc_content_item_dir($type, $slug) . 'content.md';
}

/**
 * Get the absolute path to a content item's JSON meta file.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string
 */
function mc_content_json_path(string $type, string $slug): string
{
	return mc_content_item_dir($type, $slug) . 'content.json';
}

/**
 * Get a single content item.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return array|null
 */
function mc_get_content(string $type, string $slug): ?array
{
	return mc_app()->content()->get($type, $slug);
}

/**
 * Query content items.
 *
 * @since {version}
 *
 * @param array $args Query arguments (type, status, limit, offset, order_by, order, search).
 * @return array
 */
function mc_query_content(array $args = array()): array
{
	return mc_app()->content()->query($args);
}

/**
 * Count content items.
 *
 * @since {version}
 *
 * @param string $type   Content type slug.
 * @param string $status Optional status filter.
 * @return int
 */
function mc_count_content(string $type, string $status = ''): int
{
	return mc_app()->content()->count($type, $status);
}

/**
 * Save (create or update) a content item.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @param array  $meta Meta fields.
 * @param string $body Markdown body content.
 * @return true|MC_Error
 */
function mc_save_content(string $type, string $slug, array $meta, string $body = ''): true|MC_Error
{
	return mc_app()->content()->save($type, $slug, $meta, $body);
}

/**
 * Delete a content item.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return true|MC_Error
 */
function mc_delete_content(string $type, string $slug): true|MC_Error
{
	return mc_app()->content()->delete($type, $slug);
}

/**
 * Check whether a content item exists.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return bool
 */
function mc_content_exists(string $type, string $slug): bool
{
	return mc_app()->content()->exists($type, $slug);
}

/**
 * Register built-in content types (page, post).
 *
 * @since {version}
 *
 * @return void
 */
function mc_create_initial_content_types(): void
{
	mc_app()->content_types()->register_defaults();
}

/**
 * Add content-type entries to the admin sidebar menu.
 *
 * Hooked into mc_admin_menu. Uses the OOP registry instead of the
 * old $mc_content_types global.
 *
 * @since {version}
 *
 * @return void
 */
function mc_add_content_type_menu_items(): void
{
	$types = mc_app()->content_types()->all();

	foreach ($types as $type_slug => $type) {
		if (empty($type['show_in_menu'])) {
			continue;
		}

		$label = $type['labels']['menu_name'] ?? ucfirst($type_slug);

		/**
		 * Fires to add a content type to the admin sidebar.
		 *
		 * @since {version}
		 *
		 * @param string $type_slug Content type slug.
		 * @param string $label     Menu label.
		 * @param array  $type      Full type definition.
		 */
		mc_do_action('mc_admin_menu_item', $type_slug, $label, $type);
	}
}

/*
 * =============================================================================
 *  Markdown
 * =============================================================================
 */

/**
 * Parse a Markdown string into HTML.
 *
 * @since {version}
 *
 * @param string $text Raw Markdown.
 * @return string HTML.
 */
function mc_parse_markdown(string $text): string
{
	return mc_app()->markdown()->parse($text);
}

/*
 * =============================================================================
 *  Shortcodes
 * =============================================================================
 */

/**
 * Register a shortcode handler.
 *
 * @since {version}
 *
 * @param string   $tag      Shortcode tag (e.g. 'gallery').
 * @param callable $callback Handler receiving ($attrs, $content, $tag).
 * @return void
 */
function mc_add_shortcode(string $tag, callable $callback): void
{
	mc_app()->shortcodes()->add($tag, $callback);
}

/**
 * Remove a registered shortcode.
 *
 * @since {version}
 *
 * @param string $tag Shortcode tag.
 * @return void
 */
function mc_remove_shortcode(string $tag): void
{
	mc_app()->shortcodes()->remove($tag);
}

/**
 * Check whether a shortcode tag is registered.
 *
 * @since {version}
 *
 * @param string $tag Shortcode tag.
 * @return bool
 */
function mc_shortcode_exists(string $tag): bool
{
	return mc_app()->shortcodes()->exists($tag);
}

/**
 * Process all registered shortcodes in a string.
 *
 * @since {version}
 *
 * @param string $content Content to process.
 * @return string
 */
function mc_do_shortcode(string $content): string
{
	return mc_app()->shortcodes()->do_shortcode($content);
}

/*
 * =============================================================================
 *  Router
 * =============================================================================
 */

/**
 * Add a custom route.
 *
 * @since {version}
 *
 * @param string   $pattern  URL pattern (supports {param} placeholders).
 * @param callable $callback Route handler.
 * @param int      $priority Priority (lower = higher precedence).
 * @return void
 */
function mc_add_route(string $pattern, callable $callback, int $priority = 10): void
{
	mc_app()->router()->add_route($pattern, $callback, $priority);
}

/**
 * Get the current request path.
 *
 * @since {version}
 *
 * @return string
 */
function mc_get_request_path(): string
{
	return mc_app()->router()->get_request_path();
}

/**
 * Parse the current HTTP request and populate query variables.
 *
 * @since {version}
 *
 * @return void
 */
function mc_parse_request(): void
{
	mc_app()->router()->parse_request();
}

/**
 * Check whether the current request is for the front page.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_front_page(): bool
{
	return mc_app()->router()->is_front_page();
}

/**
 * Check whether the current request is for a single content item.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_single(): bool
{
	return mc_app()->router()->is_single();
}

/**
 * Check whether the current request is for an archive/listing.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_archive(): bool
{
	return mc_app()->router()->is_archive();
}

/**
 * Check whether the current request resulted in a 404.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_404(): bool
{
	return mc_app()->router()->is_404();
}

/**
 * Check whether the current request is for a page type.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_page(): bool
{
	return mc_app()->router()->is_page();
}

/**
 * Check whether the current request targets the admin area.
 *
 * @since {version}
 *
 * @return bool
 */
function mc_is_admin_request(): bool
{
	return mc_app()->router()->is_admin();
}

/**
 * Get the current query variables.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_query(): array
{
	return mc_app()->router()->get_query();
}

/**
 * Get the current page number for archive pagination.
 *
 * @since {version}
 *
 * @return int
 */
function mc_get_page_num(): int
{
	return mc_app()->router()->get_page_num();
}

/*
 * =============================================================================
 *  Template loading
 * =============================================================================
 */

/**
 * Load and execute the most appropriate template file.
 *
 * @since {version}
 *
 * @return void
 */
function mc_load_template(): void
{
	mc_app()->template_loader()->load();
}

/**
 * Get the template hierarchy for the current request.
 *
 * @since {version}
 *
 * @return array Ordered list of template filenames.
 */
function mc_get_template_hierarchy(): array
{
	return mc_app()->template_loader()->get_hierarchy();
}

/**
 * Locate the first template file that exists on disk.
 *
 * @since {version}
 *
 * @param array $templates Ordered list of template filenames.
 * @return string|null Absolute path or null if none found.
 */
function mc_locate_template(array $templates): ?string
{
	return mc_app()->template_loader()->locate($templates);
}

/*
 * =============================================================================
 *  Template tags
 * =============================================================================
 */

/**
 * Fire the mc_head action (output styles, head scripts, etc.).
 *
 * @since {version}
 *
 * @return void
 */
function mc_head(): void
{
	mc_app()->template_tags()->head();
}

/**
 * Fire the mc_body_open action (renders admin bar, etc.).
 *
 * @since {version}
 *
 * @return void
 */
function mc_body_open(): void
{
	mc_app()->template_tags()->body_open();
}

/**
 * Fire the mc_footer action (output footer scripts, etc.).
 *
 * @since {version}
 *
 * @return void
 */
function mc_footer(): void
{
	mc_app()->template_tags()->footer();
}

/**
 * Enqueue a stylesheet.
 *
 * @since {version}
 *
 * @param string $handle Handle/identifier.
 * @param string $src    URL to the stylesheet.
 * @param string $media  CSS media query (default 'all').
 * @return void
 */
function mc_enqueue_style(string $handle, string $src, string $media = 'all'): void
{
	mc_app()->assets()->enqueue_style($handle, $src, $media);
}

/**
 * Dequeue a previously enqueued stylesheet.
 *
 * @since {version}
 *
 * @param string $handle Handle.
 * @return void
 */
function mc_dequeue_style(string $handle): void
{
	mc_app()->assets()->dequeue_style($handle);
}

/**
 * Enqueue a JavaScript file.
 *
 * @since {version}
 *
 * @param string $handle    Handle/identifier.
 * @param string $src       URL to the script.
 * @param bool   $in_footer Whether to load in the footer (default true).
 * @return void
 */
function mc_enqueue_script(string $handle, string $src, bool $in_footer = true): void
{
	mc_app()->assets()->enqueue_script($handle, $src, $in_footer);
}

/**
 * Dequeue a previously enqueued script.
 *
 * @since {version}
 *
 * @param string $handle Handle.
 * @return void
 */
function mc_dequeue_script(string $handle): void
{
	mc_app()->assets()->dequeue_script($handle);
}

/**
 * Localise a script — pass PHP data into JavaScript.
 *
 * @since {version}
 *
 * @param string $handle      Script handle.
 * @param string $object_name JavaScript variable name.
 * @param array  $data        Data array.
 * @return void
 */
function mc_localize_script(string $handle, string $object_name, array $data): void
{
	mc_app()->assets()->localize_script($handle, $object_name, $data);
}

/**
 * Output enqueued stylesheets.
 *
 * @since {version}
 *
 * @return void
 */
function mc_print_styles(): void
{
	mc_app()->assets()->print_styles();
}

/**
 * Output enqueued head scripts.
 *
 * @since {version}
 *
 * @return void
 */
function mc_print_head_scripts(): void
{
	mc_app()->assets()->print_head_scripts();
}

/**
 * Output enqueued footer scripts.
 *
 * @since {version}
 *
 * @return void
 */
function mc_print_footer_scripts(): void
{
	mc_app()->assets()->print_footer_scripts();
}

/**
 * Get the current content item as an array.
 *
 * @since {version}
 *
 * @return array|null
 */
function mc_get_the_content_item(): ?array
{
	return mc_app()->template_tags()->get_the_content_item();
}

/**
 * Output the title of the current content item.
 *
 * @since {version}
 *
 * @return void
 */
function mc_the_title(): void
{
	mc_app()->template_tags()->the_title();
}

/**
 * Get the title of the current content item.
 *
 * @since {version}
 *
 * @return string
 */
function mc_get_the_title(): string
{
	return mc_app()->template_tags()->get_the_title();
}

/**
 * Output the rendered HTML body of the current content item.
 *
 * @since {version}
 *
 * @return void
 */
function mc_the_content(): void
{
	mc_app()->template_tags()->the_content();
}

/**
 * Get the rendered HTML body of the current content item.
 *
 * @since {version}
 *
 * @return string
 */
function mc_get_the_content(): string
{
	return mc_app()->template_tags()->get_the_content();
}

/**
 * Output a short excerpt of the current content item.
 *
 * @since {version}
 *
 * @return void
 */
function mc_the_excerpt(): void
{
	mc_app()->template_tags()->the_excerpt();
}

/**
 * Output the document <title>.
 *
 * @since {version}
 *
 * @return void
 */
function mc_document_title(): void
{
	mc_app()->template_tags()->document_title();
}

/**
 * Load and output the theme's header file.
 *
 * @since {version}
 *
 * @param string $name Optional variant name (e.g. 'minimal' → header-minimal.php).
 * @return void
 */
function mc_get_header(string $name = ''): void
{
	mc_app()->template_tags()->get_header($name);
}

/**
 * Load and output the theme's footer file.
 *
 * @since {version}
 *
 * @param string $name Optional variant name.
 * @return void
 */
function mc_get_footer(string $name = ''): void
{
	mc_app()->template_tags()->get_footer($name);
}

/**
 * Load and output a sidebar file.
 *
 * @since {version}
 *
 * @param string $name Optional variant name.
 * @return void
 */
function mc_get_sidebar(string $name = ''): void
{
	mc_app()->template_tags()->get_sidebar($name);
}

/**
 * Load a template part file.
 *
 * @since {version}
 *
 * @param string $slug Part slug (e.g. 'loop').
 * @param string $name Optional variant (e.g. 'post' → loop-post.php).
 * @return void
 */
function mc_get_template_part(string $slug, string $name = ''): void
{
	mc_app()->template_tags()->get_template_part($slug, $name);
}

/**
 * Output a space-separated list of CSS body classes.
 *
 * @since {version}
 *
 * @param string $extra Additional classes to append.
 * @return void
 */
function mc_body_class(string $extra = ''): void
{
	mc_app()->template_tags()->body_class($extra);
}

/**
 * Build the canonical URL for a content item.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string
 */
function mc_get_content_permalink(string $type, string $slug): string
{
	// Front-page slug always resolves to the site root.
	if ('page' === $type && defined('MC_FRONT_PAGE') && $slug === MC_FRONT_PAGE) {
		return mc_site_url();
	}

	$type_def     = mc_get_content_type($type);
	$rewrite_slug = ($type_def['rewrite']['slug'] ?? null) ?? $type;

	if ('' === $rewrite_slug) {
		return mc_site_url($slug);
	}

	return mc_site_url($rewrite_slug . '/' . $slug);
}

/**
 * Output the permalink for the current content item.
 *
 * @since {version}
 *
 * @return void
 */
function mc_the_permalink(): void
{
	$query = mc_get_query();
	$type  = $query['type'] ?? 'page';
	$slug  = $query['slug'] ?? '';

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo mc_esc_url(mc_get_content_permalink($type, $slug));
}

/**
 * Build a preview URL for a draft content item.
 *
 * The URL includes a nonce tied to the current user so only they can view it.
 *
 * @since {version}
 *
 * @param string $type Content type slug.
 * @param string $slug Content item slug.
 * @return string Full preview URL with query parameters.
 */
function mc_get_preview_url(string $type, string $slug): string
{
	$permalink = mc_get_content_permalink($type, $slug);
	$nonce     = mc_create_nonce('preview_' . $type . '_' . $slug);

	$separator = str_contains($permalink, '?') ? '&' : '?';

	return $permalink . $separator . 'preview=true&key=' . urlencode($nonce);
}

/**
 * Get the featured image URL for the current content item.
 *
 * @since {version}
 *
 * @return string URL or empty string if no image.
 */
function mc_get_featured_image(): string
{
	$content = mc_get_the_content_item();
	$image   = $content['featured_image'] ?? '';

	if ('' === $image) {
		return '';
	}

	if (!str_starts_with($image, 'http')) {
		return mc_content_url('uploads/' . ltrim($image, '/'));
	}

	return $image;
}

/**
 * Get the HTML content of a named template section.
 *
 * Checks the current item's local meta first (key `_section_{$id}`), then
 * falls back to global value under the `theme.sections` namespace.
 * Both values are Markdown and are rendered to HTML on output.
 *
 * @since {version}
 *
 * @param string $id Section identifier.
 * @return string HTML or empty string.
 */
function mc_get_the_section(string $id): string
{
	$content = mc_get_the_content_item();
	$local   = $content['meta']['_section_' . $id] ?? '';

	if ('' !== trim((string) $local)) {
		return mc_parse_markdown((string) $local);
	}

	$global = mc_get_setting('theme.sections', '_global_' . $id, '');

	if ('' !== trim((string) $global)) {
		return mc_parse_markdown((string) $global);
	}

	return '';
}

/**
 * Output the HTML content of a named template section.
 *
 * @since {version}
 *
 * @param string $id Section identifier.
 * @return void
 */
function mc_the_section(string $id): void
{
	echo mc_get_the_section($id);
}

/*
 * =============================================================================
 *  Theme Manager
 * =============================================================================
 */

/**
 * Get theme metadata from a theme directory.
 *
 * @since {version}
 *
 * @param string $dir Absolute path to theme directory.
 * @return array
 */
function mc_get_theme_data(string $dir): array
{
	return mc_app()->themes()->get_theme_data($dir);
}

/**
 * Discover all installed themes.
 *
 * @since {version}
 *
 * @return array slug => theme metadata.
 */
function mc_discover_themes(): array
{
	return mc_app()->themes()->discover();
}

/**
 * Load and activate the active theme.
 *
 * @since {version}
 *
 * @return void
 */
function mc_load_theme(): void
{
	mc_app()->themes()->load();
}

/**
 * Get the active theme metadata.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_active_theme(): array
{
	return mc_app()->themes()->get_active();
}

/**
 * Get the absolute filesystem path to the parent theme directory.
 *
 * @since {version}
 *
 * @return string Path with trailing slash.
 */
function mc_get_parent_theme_dir(): string
{
	return mc_app()->themes()->get_parent_dir();
}

/**
 * Switch the active theme.
 *
 * @since {version}
 *
 * @param string $slug Theme slug.
 * @return true|MC_Error
 */
function mc_switch_theme(string $slug): true|MC_Error
{
	return mc_app()->themes()->switch_theme($slug);
}

/**
 * Get all page templates registered by the active theme.
 *
 * @since {version}
 *
 * @return array slug => label.
 */
function mc_get_page_templates(): array
{
	return mc_app()->themes()->get_page_templates();
}

/**
 * Parse a theme section header string into an associative array.
 *
 * @since {version}
 *
 * @param string $header_value Raw header value from theme.json or template comment.
 * @return array
 */
function mc_parse_section_header(string $header_value): array
{
	return mc_app()->themes()->parse_section_header($header_value);
}

/*
 * =============================================================================
 *  Plugin Manager
 * =============================================================================
 */

/**
 * Get plugin header data from a plugin file.
 *
 * @since {version}
 *
 * @param string $file Absolute path to the plugin file.
 * @return array
 */
function mc_get_plugin_data(string $file): array
{
	return mc_app()->plugins()->get_plugin_data($file);
}

/**
 * Discover all installed plugins.
 *
 * @since {version}
 *
 * @return array relative_path => plugin metadata.
 */
function mc_discover_plugins(): array
{
	return mc_app()->plugins()->discover();
}

/**
 * Get the list of active plugin relative paths.
 *
 * @since {version}
 *
 * @return array
 */
function mc_get_active_plugins(): array
{
	return mc_app()->plugins()->get_active();
}

/**
 * Check whether a plugin is active.
 *
 * @since {version}
 *
 * @param string $plugin Relative plugin path (e.g. 'forms/forms.php').
 * @return bool
 */
function mc_is_plugin_active(string $plugin): bool
{
	return mc_app()->plugins()->is_active($plugin);
}

/**
 * Load all must-use plugins.
 *
 * @since {version}
 *
 * @return void
 */
function mc_load_mu_plugins(): void
{
	mc_app()->plugins()->load_mu_plugins();
}

/**
 * Load all active plugins.
 *
 * @since {version}
 *
 * @return void
 */
function mc_load_plugins(): void
{
	mc_app()->plugins()->load_plugins();
}

/**
 * Register a callback to run when a plugin is activated.
 *
 * @since {version}
 *
 * @param string   $file     Absolute path to the plugin's main file.
 * @param callable $callback Activation callback.
 * @return void
 */
function mc_register_activation_hook(string $file, callable $callback): void
{
	mc_app()->plugins()->register_activation_hook($file, $callback);
}

/**
 * Register a callback to run when a plugin is deactivated.
 *
 * @since {version}
 *
 * @param string   $file     Absolute path to the plugin's main file.
 * @param callable $callback Deactivation callback.
 * @return void
 */
function mc_register_deactivation_hook(string $file, callable $callback): void
{
	mc_app()->plugins()->register_deactivation_hook($file, $callback);
}

/**
 * Activate a plugin by its relative path.
 *
 * @since {version}
 *
 * @param string $plugin Relative path (e.g. 'forms/forms.php').
 * @return true|MC_Error
 */
function mc_activate_plugin(string $plugin): true|MC_Error
{
	return mc_app()->plugins()->activate($plugin);
}

/**
 * Deactivate a plugin by its relative path.
 *
 * @since {version}
 *
 * @param string $plugin Relative path.
 * @return true|MC_Error
 */
function mc_deactivate_plugin(string $plugin): true|MC_Error
{
	return mc_app()->plugins()->deactivate($plugin);
}
