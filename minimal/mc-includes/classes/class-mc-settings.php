<?php

/**
 * MC_Settings — File-backed settings storage.
 *
 * Replaces Part 1 of the procedural settings.php. Each settings "namespace"
 * (e.g. 'core.general') maps to a PHP-guarded file under the data directory.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Settings storage engine.
 *
 * @since {version}
 */
class MC_Settings
{
	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Settings directory path (with trailing slash).
	 *
	 * @since {version}
	 * @var string
	 */
	private string $settings_dir;

	/**
	 * In-memory cache of loaded namespaces.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks $hooks        Hooks engine.
	 * @param string   $settings_dir Directory for settings files.
	 */
	public function __construct(MC_Hooks $hooks, string $settings_dir)
	{

		$this->hooks        = $hooks;
		$this->settings_dir = rtrim($settings_dir, '/') . '/';
	}

	/**
	 * Get the filesystem path for a namespace.
	 *
	 * @since {version}
	 *
	 * @param string $namespace Dot-separated namespace (e.g. 'core.general').
	 * @return string Absolute path to the PHP-guarded settings file.
	 */
	public function path(string $namespace): string
	{

		$safe = preg_replace('/[^a-z0-9._-]/', '-', strtolower($namespace));
		return $this->settings_dir . $safe . '.php';
	}

	/**
	 * Load all settings for a namespace.
	 *
	 * @since {version}
	 *
	 * @param string $namespace Settings namespace.
	 * @return array Key-value pairs.
	 */
	public function get_all(string $namespace): array
	{

		if (isset($this->cache[$namespace])) {
			return $this->cache[$namespace];
		}

		$path = $this->path($namespace);
		if (!is_file($path)) {
			$this->cache[$namespace] = array();
			return array();
		}

		$data = MC_File_Guard::read_json($path);
		if (!is_array($data)) {
			$data = array();
		}

		/**
		 * Filter settings after loading from disk.
		 *
		 * @since {version}
		 *
		 * @param array  $data      Settings data.
		 * @param string $namespace Namespace.
		 */
		$data = $this->hooks->apply_filters('mc_get_settings', $data, $namespace);

		$this->cache[$namespace] = $data;

		return $data;
	}

	/**
	 * Get a single setting value.
	 *
	 * @since {version}
	 *
	 * @param string $namespace Settings namespace.
	 * @param string $key       Setting key.
	 * @param mixed  $default   Default value.
	 * @return mixed
	 */
	public function get(string $namespace, string $key, mixed $default = null): mixed
	{

		$data = $this->get_all($namespace);
		return $data[$key] ?? $default;
	}

	/**
	 * Merge-update settings for a namespace and persist to disk.
	 *
	 * @since {version}
	 *
	 * @param string $namespace Settings namespace.
	 * @param array  $values    Key-value pairs to merge.
	 * @return true|MC_Error
	 */
	public function update(string $namespace, array $values): true|MC_Error
	{

		$this->ensure_dir();

		$existing = $this->get_all($namespace);

		/**
		 * Filter settings values before persisting.
		 *
		 * @since {version}
		 *
		 * @param array  $values    New values.
		 * @param array  $existing  Current values.
		 * @param string $namespace Namespace.
		 */
		$values = $this->hooks->apply_filters('mc_pre_update_settings', $values, $existing, $namespace);

		$merged = array_merge($existing, $values);
		$path   = $this->path($namespace);

		if (!MC_File_Guard::write_json($path, $merged)) {
			return new MC_Error('settings_write_failed', "Failed to write settings for namespace '{$namespace}'.");
		}

		$this->cache[$namespace] = $merged;

		/**
		 * Fires after settings are persisted.
		 *
		 * @since {version}
		 *
		 * @param string $namespace Namespace.
		 * @param array  $merged    Final merged values.
		 */
		$this->hooks->do_action('mc_settings_updated', $namespace, $merged);

		return true;
	}

	/**
	 * Delete a specific key within a namespace.
	 *
	 * @since {version}
	 *
	 * @param string $namespace Settings namespace.
	 * @param string $key       Key to remove.
	 * @return true|MC_Error
	 */
	public function delete_key(string $namespace, string $key): true|MC_Error
	{

		$data = $this->get_all($namespace);
		unset($data[$key]);

		$this->ensure_dir();

		$path = $this->path($namespace);
		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		if (!MC_File_Guard::write_json($path, $data)) {
			return new MC_Error('settings_write_failed', "Failed to write settings for namespace '{$namespace}'.");
		}

		$this->cache[$namespace] = $data;

		/**
		 * Fires after a setting key is deleted.
		 *
		 * @since {version}
		 *
		 * @param string $namespace Namespace.
		 * @param string $key       Deleted key.
		 */
		$this->hooks->do_action('mc_setting_deleted', $namespace, $key);

		return true;
	}

	/**
	 * Delete all settings for a namespace (removes the JSON file).
	 *
	 * @since {version}
	 *
	 * @param string $namespace Settings namespace.
	 * @return true|MC_Error
	 */
	public function delete(string $namespace): true|MC_Error
	{

		$path = $this->path($namespace);

		if (is_file($path) && !unlink($path)) {
			return new MC_Error('settings_delete_failed', "Failed to delete settings for namespace '{$namespace}'.");
		}

		unset($this->cache[$namespace]);

		/**
		 * Fires after all settings for a namespace are deleted.
		 *
		 * @since {version}
		 *
		 * @param string $namespace Namespace.
		 */
		$this->hooks->do_action('mc_settings_deleted', $namespace);

		return true;
	}

	/**
	 * Ensure the settings directory exists.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	private function ensure_dir(): void
	{

		if (!is_dir($this->settings_dir)) {
			mkdir($this->settings_dir, 0755, true);
		}
	}
}
