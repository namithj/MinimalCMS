<?php

/**
 * MinimalCMS Configuration
 *
 * Loads, reads, and persists config.json. Replaces the global $mc_config
 * and config-related functions from load.php.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_Config
 *
 * Configuration loader / writer. Supports dot-notation access and
 * fires hooks on load and save.
 *
 * @since {version}
 */
class MC_Config
{
	/**
	 * Parsed configuration data.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $data = array();

	/**
	 * Absolute path to config.json.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $config_path;

	/**
	 * Absolute path to config.sample.json.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $sample_path;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param string $config_path Absolute path to config.json.
	 * @param string $sample_path Absolute path to config.sample.json.
	 */
	public function __construct(string $config_path, string $sample_path)
	{

		$this->config_path = $config_path;
		$this->sample_path = $sample_path;
	}

	/**
	 * Load and parse config.json.
	 *
	 * Falls back to the sample file if config.json does not exist.
	 *
	 * @since {version}
	 *
	 * @return array The parsed configuration.
	 */
	public function load(): array
	{

		$path = is_file($this->config_path) ? $this->config_path : $this->sample_path;

		if (!is_readable($path)) {
			return $this->data;
		}

		$raw = file_get_contents($path);

		if (false === $raw) {
			return $this->data;
		}

		$data = json_decode($raw, true);

		if (!is_array($data)) {
			return $this->data;
		}

		$this->data = $data;
		return $this->data;
	}

	/**
	 * Get a config value by dot-notation key.
	 *
	 * Example: $config->get('site_url') or $config->get('nested.key.path').
	 *
	 * @since {version}
	 *
	 * @param string $key     Dot-notation key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{

		if (isset($this->data[$key])) {
			return $this->data[$key];
		}

		// Dot-notation traversal.
		$segments = explode('.', $key);
		$value    = $this->data;

		foreach ($segments as $segment) {
			if (!is_array($value) || !array_key_exists($segment, $value)) {
				return $default;
			}
			$value = $value[$segment];
		}

		return $value;
	}

	/**
	 * Set a config value (in memory only).
	 *
	 * @since {version}
	 *
	 * @param string $key   Config key.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	public function set(string $key, mixed $value): void
	{

		$this->data[$key] = $value;
	}

	/**
	 * Persist current config to disk.
	 *
	 * @since {version}
	 *
	 * @return bool True on success, false on failure.
	 */
	public function save(): bool
	{

		$json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $json) {
			return false;
		}

		return false !== file_put_contents($this->config_path, $json, LOCK_EX);
	}

	/**
	 * Check if this is a fresh install (no config.json).
	 *
	 * @since {version}
	 *
	 * @return bool True if config.json does not exist.
	 */
	public function is_fresh_install(): bool
	{

		return !is_file($this->config_path);
	}

	/**
	 * Get the entire config array.
	 *
	 * @since {version}
	 *
	 * @return array
	 */
	public function all(): array
	{

		return $this->data;
	}

	/**
	 * Initialise core constants from config values.
	 *
	 * @since {version}
	 *
	 * @param string $abspath The MC_ABSPATH value for building paths.
	 * @return void
	 */
	public function define_constants(string $abspath): void
	{

		$this->maybe_define('MC_INC', $abspath . 'mc-includes/');

		$content_dir = $this->data['content_dir'] ?? 'mc-content';
		$this->maybe_define('MC_CONTENT_DIR', $abspath . rtrim($content_dir, '/\\') . '/');

		$data_dir = $this->data['data_dir'] ?? 'mc-data';
		$this->maybe_define('MC_DATA_DIR', $abspath . rtrim($data_dir, '/\\') . '/');

		$this->maybe_define('MC_PLUGIN_DIR', MC_CONTENT_DIR . 'plugins/');
		$this->maybe_define('MC_MU_PLUGIN_DIR', MC_CONTENT_DIR . 'mu-plugins/');
		$this->maybe_define('MC_THEME_DIR', MC_CONTENT_DIR . 'themes/');
		$this->maybe_define('MC_UPLOAD_DIR', MC_CONTENT_DIR . 'uploads/');
		$this->maybe_define('MC_CACHE_DIR', MC_CONTENT_DIR . 'cache/');
		$this->maybe_define('MC_SESSION_DIR', MC_DATA_DIR . 'sessions/');
		$this->maybe_define('MC_LOG_DIR', MC_DATA_DIR . 'logs/');

		$this->maybe_define('MC_SITE_URL', $this->data['site_url'] ?? '');
		$this->maybe_define('MC_SITE_NAME', $this->data['site_name'] ?? 'MinimalCMS Site');
		$this->maybe_define('MC_SITE_DESCRIPTION', $this->data['site_description'] ?? '');
		$this->maybe_define('MC_TIMEZONE', $this->data['timezone'] ?? 'UTC');
		$this->maybe_define('MC_DEBUG', (bool) ($this->data['debug'] ?? false));
		$this->maybe_define('MC_SECRET_KEY', $this->data['secret_key'] ?? '');
		$this->maybe_define('MC_ENCRYPTION_KEY', $this->data['encryption_key'] ?? '');
		$this->maybe_define('MC_FRONT_PAGE', $this->data['front_page'] ?? 'index');
		$this->maybe_define('MC_POSTS_PER_PAGE', (int) ($this->data['posts_per_page'] ?? 10));
		$this->maybe_define('MC_PERMALINK_STRUCTURE', $this->data['permalink_structure'] ?? '/{type}/{slug}');
		$this->maybe_define('MC_ACTIVE_THEME', $this->data['active_theme'] ?? 'default');
	}

	/**
	 * Define a constant if it has not been defined yet.
	 *
	 * @since {version}
	 *
	 * @param string $name  Constant name.
	 * @param mixed  $value Constant value.
	 * @return void
	 */
	private function maybe_define(string $name, mixed $value): void
	{

		if (!defined($name)) {
			define($name, $value);
		}
	}
}
