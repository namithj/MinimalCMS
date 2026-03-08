<?php
/**
 * MC_Plugin_Manager — Plugin discovery, loading, activation/deactivation.
 *
 * Replaces plugin.php. Handles plugin header parsing, dependency loading,
 * activation callbacks, and lifecycle management.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Plugin lifecycle manager.
 *
 * @since {version}
 */
class MC_Plugin_Manager {

	/**
	 * Loaded plugin metadata keyed by relative path.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $plugins = array();

	/**
	 * Activation hooks keyed by absolute file path.
	 *
	 * @since {version}
	 * @var array<string, callable>
	 */
	private array $activation_hooks = array();

	/**
	 * Deactivation hooks keyed by absolute file path.
	 *
	 * @since {version}
	 * @var array<string, callable>
	 */
	private array $deactivation_hooks = array();

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Config
	 */
	private MC_Config $config;

	/**
	 * Plugins directory with trailing slash.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $plugins_dir;

	/**
	 * Must-use plugins directory with trailing slash.
	 *
	 * @since {version}
	 * @var string
	 */
	private string $mu_plugins_dir;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks  $hooks          Hooks engine.
	 * @param MC_Config $config         Configuration.
	 * @param string    $plugins_dir    Plugins directory.
	 * @param string    $mu_plugins_dir MU plugins directory.
	 */
	public function __construct(MC_Hooks $hooks, MC_Config $config, string $plugins_dir, string $mu_plugins_dir) {

		$this->hooks          = $hooks;
		$this->config         = $config;
		$this->plugins_dir    = rtrim($plugins_dir, '/') . '/';
		$this->mu_plugins_dir = rtrim($mu_plugins_dir, '/') . '/';
	}

	/**
	 * Parse plugin header metadata from a PHP file.
	 *
	 * @since {version}
	 *
	 * @param string $file Absolute path to the plugin file.
	 * @return array Parsed header data.
	 */
	public function get_plugin_data(string $file): array {

		$defaults = array(
			'name'        => '',
			'description' => '',
			'version'     => '',
			'author'      => '',
			'requires_mc' => '',
		);

		if (!is_file($file)) {
			return $defaults;
		}

		$content = file_get_contents($file, false, null, 0, 8192);
		if (false === $content) {
			return $defaults;
		}

		$headers = array(
			'name'        => 'Plugin Name',
			'description' => 'Description',
			'version'     => 'Version',
			'author'      => 'Author',
			'requires_mc' => 'Requires MC',
		);

		$data = array();
		foreach ($headers as $key => $label) {
			if (preg_match('/^[\s\*]*' . preg_quote($label, '/') . ':\s*(.+)$/mi', $content, $m)) {
				$data[$key] = trim($m[1]);
			} else {
				$data[$key] = $defaults[$key];
			}
		}

		return $data;
	}

	/**
	 * Discover all plugins in the plugins directory.
	 *
	 * @since {version}
	 *
	 * @return array relative_path => header data.
	 */
	public function discover(): array {

		if (!is_dir($this->plugins_dir)) {
			return array();
		}

		$plugins = array();
		$entries = array_diff(scandir($this->plugins_dir), array('.', '..'));

		foreach ($entries as $entry) {
			$full = $this->plugins_dir . $entry;

			if (is_dir($full)) {
				$main_file = $full . '/' . $entry . '.php';
				if (is_file($main_file)) {
					$relative             = $entry . '/' . $entry . '.php';
					$plugins[$relative]   = $this->get_plugin_data($main_file);
				}
			} elseif (str_ends_with($entry, '.php') && 'index.php' !== $entry) {
				$plugins[$entry] = $this->get_plugin_data($full);
			}
		}

		/**
		 * Filter discovered plugins list.
		 *
		 * @since {version}
		 *
		 * @param array $plugins Discovered plugins.
		 */
		return $this->hooks->apply_filters('mc_discover_plugins', $plugins);
	}

	/**
	 * Get list of active plugins from config.
	 *
	 * @since {version}
	 *
	 * @return string[] Array of relative plugin paths.
	 */
	public function get_active(): array {

		$active = $this->config->get('active_plugins', array());

		/**
		 * Filter active plugins list before loading.
		 *
		 * @since {version}
		 *
		 * @param array $active Active plugin paths.
		 */
		return $this->hooks->apply_filters('mc_active_plugins', $active);
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @since {version}
	 *
	 * @param string $plugin Relative plugin path.
	 * @return bool
	 */
	public function is_active(string $plugin): bool {

		return in_array($plugin, $this->get_active(), true);
	}

	/**
	 * Load all must-use plugins.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function load_mu_plugins(): void {

		if (!is_dir($this->mu_plugins_dir)) {
			return;
		}

		$files = glob($this->mu_plugins_dir . '*.php');
		if (false === $files) {
			return;
		}

		sort($files);

		foreach ($files as $file) {
			include_once $file;
			$this->hooks->do_action('mc_mu_plugin_loaded', $file);
		}
	}

	/**
	 * Load all activated regular plugins.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function load_plugins(): void {

		$active = $this->get_active();

		foreach ($active as $relative) {
			$file = $this->plugins_dir . $relative;

			if (!is_file($file)) {
				continue;
			}

			include_once $file;

			$this->plugins[$relative] = $this->get_plugin_data($file);

			$this->hooks->do_action('mc_plugin_loaded', $relative);
		}
	}

	/**
	 * Register a plugin activation callback.
	 *
	 * @since {version}
	 *
	 * @param string   $file     Plugin main file (__FILE__).
	 * @param callable $callback Activation callback.
	 * @return void
	 */
	public function register_activation_hook(string $file, callable $callback): void {

		$this->activation_hooks[$file] = $callback;
	}

	/**
	 * Register a plugin deactivation callback.
	 *
	 * @since {version}
	 *
	 * @param string   $file     Plugin main file.
	 * @param callable $callback Deactivation callback.
	 * @return void
	 */
	public function register_deactivation_hook(string $file, callable $callback): void {

		$this->deactivation_hooks[$file] = $callback;
	}

	/**
	 * Activate a plugin.
	 *
	 * @since {version}
	 *
	 * @param string $relative Relative plugin path.
	 * @return true|MC_Error
	 */
	public function activate(string $relative): true|MC_Error {

		$file = $this->plugins_dir . $relative;

		if (!is_file($file)) {
			return new MC_Error('not_found', 'Plugin file not found.');
		}

		$active = $this->get_active();

		if (in_array($relative, $active, true)) {
			return new MC_Error('already_active', 'Plugin is already active.');
		}

		include_once $file;

		if (isset($this->activation_hooks[$file])) {
			call_user_func($this->activation_hooks[$file]);
		}

		$active[] = $relative;
		$this->config->set('active_plugins', $active);
		$this->config->save();

		$this->hooks->do_action('mc_plugin_activated', $relative);

		return true;
	}

	/**
	 * Deactivate a plugin.
	 *
	 * @since {version}
	 *
	 * @param string $relative Relative plugin path.
	 * @return true|MC_Error
	 */
	public function deactivate(string $relative): true|MC_Error {

		$active = $this->get_active();

		if (!in_array($relative, $active, true)) {
			return new MC_Error('not_active', 'Plugin is not active.');
		}

		$file = $this->plugins_dir . $relative;

		if (isset($this->deactivation_hooks[$file])) {
			call_user_func($this->deactivation_hooks[$file]);
		}

		$active = array_values(array_diff($active, array($relative)));
		$this->config->set('active_plugins', $active);
		$this->config->save();

		$this->hooks->do_action('mc_plugin_deactivated', $relative);

		return true;
	}
}
