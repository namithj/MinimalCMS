<?php

/**
 * MinimalCMS Plugin System
 *
 * Handles plugin discovery, loading, and lifecycle hooks.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Loaded plugin metadata keyed by plugin file path.
 *
 * @global array $mc_plugins
 */
global $mc_plugins;
$mc_plugins = array();

/**
 * Activation hooks keyed by plugin file.
 *
 * @global array $mc_activation_hooks
 */
global $mc_activation_hooks;
$mc_activation_hooks = array();

/**
 * Deactivation hooks keyed by plugin file.
 *
 * @global array $mc_deactivation_hooks
 */
global $mc_deactivation_hooks;
$mc_deactivation_hooks = array();

/*
 * -------------------------------------------------------------------------
 *  Plugin header parsing
 * -------------------------------------------------------------------------
 */

/**
 * Parse plugin metadata from the comment header of a PHP file.
 *
 * Recognised headers: Plugin Name, Description, Version, Author, Requires MC.
 *
 * @since 1.0.0
 *
 * @param string $file Absolute path to the plugin's main PHP file.
 * @return array Parsed header data.
 */
function mc_get_plugin_data(string $file): array
{

	$defaults = array(
		'name'        => '',
		'description' => '',
		'version'     => '',
		'author'      => '',
		'requires_mc' => '',
	);

	if (! is_file($file)) {
		return $defaults;
	}

	// Read only the first 8 kB.
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
			$data[ $key ] = trim($m[1]);
		} else {
			$data[ $key ] = $defaults[ $key ];
		}
	}

	return $data;
}

/*
 * -------------------------------------------------------------------------
 *  Plugin discovery
 * -------------------------------------------------------------------------
 */

/**
 * Discover all plugins in the plugins directory.
 *
 * A valid plugin is either:
 *  - A subfolder with a PHP file of the same name (e.g. my-plugin/my-plugin.php)
 *  - A single PHP file directly in the plugins directory
 *
 * @since 1.0.0
 *
 * @return array Associative array of relative_path => header data.
 */
function mc_discover_plugins(): array
{

	$dir     = MC_PLUGIN_DIR;
	$plugins = array();

	if (! is_dir($dir)) {
		return $plugins;
	}

	$entries = array_diff(scandir($dir), array( '.', '..' ));

	foreach ($entries as $entry) {
		$full = $dir . $entry;

		if (is_dir($full)) {
			// Directory plugin: look for a PHP file with the same name.
			$main_file = $full . '/' . $entry . '.php';
			if (is_file($main_file)) {
				$relative             = $entry . '/' . $entry . '.php';
				$plugins[ $relative ] = mc_get_plugin_data($main_file);
			}
		} elseif (str_ends_with($entry, '.php') && 'index.php' !== $entry) {
			// Single-file plugin.
			$plugins[ $entry ] = mc_get_plugin_data($full);
		}
	}

	return $plugins;
}

/**
 * Get the list of active plugin relative paths from config.
 *
 * @since 1.0.0
 *
 * @return string[] Array of relative plugin paths.
 */
function mc_get_active_plugins(): array
{

	global $mc_config;
	return $mc_config['active_plugins'] ?? array();
}

/**
 * Check whether a plugin (by relative path) is active.
 *
 * @since 1.0.0
 *
 * @param string $plugin Relative path (e.g. 'my-plugin/my-plugin.php').
 * @return bool
 */
function mc_is_plugin_active(string $plugin): bool
{

	return in_array($plugin, mc_get_active_plugins(), true);
}

/*
 * -------------------------------------------------------------------------
 *  Plugin loading
 * -------------------------------------------------------------------------
 */

/**
 * Load all must-use plugins.
 *
 * MU plugins are single PHP files inside mc-content/mu-plugins/.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_load_mu_plugins(): void
{

	$dir = MC_MU_PLUGIN_DIR;

	if (! is_dir($dir)) {
		return;
	}

	$files = glob($dir . '*.php');

	if (false === $files) {
		return;
	}

	sort($files);

	foreach ($files as $file) {
		include_once $file;
		mc_do_action('mc_mu_plugin_loaded', $file);
	}
}

/**
 * Load all active regular plugins.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_load_plugins(): void
{

	global $mc_plugins;

	$active = mc_get_active_plugins();

	foreach ($active as $relative) {
		$file = MC_PLUGIN_DIR . $relative;

		if (! is_file($file)) {
			continue;
		}

		include_once $file;

		$mc_plugins[ $relative ] = mc_get_plugin_data($file);

		/**
		 * Fires after an active plugin is loaded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $relative Relative plugin path.
		 */
		mc_do_action('mc_plugin_loaded', $relative);
	}
}

/*
 * -------------------------------------------------------------------------
 *  Activation / deactivation
 * -------------------------------------------------------------------------
 */

/**
 * Register a callback to run when a plugin is activated.
 *
 * @since 1.0.0
 *
 * @param string   $file     The plugin main file (__FILE__ from the plugin).
 * @param callable $callback Activation callback.
 * @return void
 */
function mc_register_activation_hook(string $file, callable $callback): void
{

	global $mc_activation_hooks;
	$mc_activation_hooks[ $file ] = $callback;
}

/**
 * Register a callback to run when a plugin is deactivated.
 *
 * @since 1.0.0
 *
 * @param string   $file     The plugin main file.
 * @param callable $callback Deactivation callback.
 * @return void
 */
function mc_register_deactivation_hook(string $file, callable $callback): void
{

	global $mc_deactivation_hooks;
	$mc_deactivation_hooks[ $file ] = $callback;
}

/**
 * Activate a plugin by adding it to the active list and saving config.
 *
 * @since 1.0.0
 *
 * @param string $relative Relative plugin path.
 * @return true|MC_Error True on success.
 */
function mc_activate_plugin(string $relative): true|MC_Error
{

	global $mc_config, $mc_activation_hooks;

	$file = MC_PLUGIN_DIR . $relative;

	if (! is_file($file)) {
		return new MC_Error('not_found', 'Plugin file not found.');
	}

	$active = mc_get_active_plugins();

	if (in_array($relative, $active, true)) {
		return new MC_Error('already_active', 'Plugin is already active.');
	}

	// Load the plugin so its activation hook can register.
	include_once $file;

	// Run activation hook if registered.
	if (isset($mc_activation_hooks[ $file ])) {
		call_user_func($mc_activation_hooks[ $file ]);
	}

	$active[]                    = $relative;
	$mc_config['active_plugins'] = $active;
	mc_save_config($mc_config);

	mc_do_action('mc_plugin_activated', $relative);

	return true;
}

/**
 * Deactivate a plugin by removing it from the active list.
 *
 * @since 1.0.0
 *
 * @param string $relative Relative plugin path.
 * @return true|MC_Error True on success.
 */
function mc_deactivate_plugin(string $relative): true|MC_Error
{

	global $mc_config, $mc_deactivation_hooks;

	$active = mc_get_active_plugins();

	if (! in_array($relative, $active, true)) {
		return new MC_Error('not_active', 'Plugin is not active.');
	}

	$file = MC_PLUGIN_DIR . $relative;

	if (isset($mc_deactivation_hooks[ $file ])) {
		call_user_func($mc_deactivation_hooks[ $file ]);
	}

	$active                      = array_values(array_diff($active, array( $relative )));
	$mc_config['active_plugins'] = $active;
	mc_save_config($mc_config);

	mc_do_action('mc_plugin_deactivated', $relative);

	return true;
}

/**
 * Save the global config array back to config.json.
 *
 * @since 1.0.0
 *
 * @param array $config Configuration data.
 * @return bool True on success.
 */
function mc_save_config(array $config): bool
{

	$path = MC_ABSPATH . 'config.json';
	$json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

	return false !== file_put_contents($path, $json . "\n", LOCK_EX);
}
