<?php

/**
 * MinimalCMS Application Container
 *
 * Singleton that wires all subsystems together and replaces global variables.
 * The only permitted global in the entire application.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_App
 *
 * Application container. Holds every subsystem as a registered service.
 * Provides typed accessors for each service and a boot() method that
 * replaces the old mc-settings.php bootstrap sequence.
 *
 * @since {version}
 */
class MC_App
{
	/**
	 * Singleton instance.
	 *
	 * @since {version}
	 * @var MC_App|null
	 */
	private static ?MC_App $instance = null;

	/**
	 * Registered services keyed by name.
	 *
	 * @since {version}
	 * @var array<string, object>
	 */
	private array $services = array();

	/**
	 * Whether the application has been booted.
	 *
	 * @since {version}
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * MinimalCMS version string.
	 *
	 * @since {version}
	 * @var string
	 */
	public const VERSION = '0.0.4';

	/**
	 * Minimum required PHP version.
	 *
	 * @since {version}
	 * @var string
	 */
	public const REQUIRED_PHP = '8.2.0';

	/**
	 * Private constructor — use instance() instead.
	 *
	 * @since {version}
	 */
	private function __construct()
	{
	}

	/**
	 * Prevent cloning.
	 *
	 * @since {version}
	 */
	private function __clone()
	{
	}

	/**
	 * Get the singleton instance.
	 *
	 * @since {version}
	 *
	 * @return MC_App
	 */
	public static function instance(): MC_App
	{

		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot the application.
	 *
	 * Creates all foundation services and wires dependencies.
	 * Called once from mc-load.php.
	 *
	 * @since {version}
	 *
	 * @param string $config_path Absolute path to config.json.
	 * @return void
	 */
	public function boot(string $config_path): void
	{

		if ($this->booted) {
			return;
		}

		$abspath     = defined('MC_ABSPATH') ? MC_ABSPATH : dirname($config_path) . '/';
		$sample_path = dirname($config_path) . '/config.sample.json';

		// 1. Config.
		$config = new MC_Config($config_path, $sample_path);
		$config->load();
		$this->set('config', $config);

		// 2. Hooks (no deps).
		$hooks = new MC_Hooks();
		$this->set('hooks', $hooks);

		/**
		 * Filter the config data after loading, before constants are defined.
		 *
		 * @since {version}
		 *
		 * @param array $data The parsed config data.
		 */
		$filtered_data = $hooks->apply_filters('mc_config_loaded', $config->all());

		// Re-apply filtered data if hooks modified it.
		foreach ($filtered_data as $key => $value) {
			$config->set($key, $value);
		}

		// 3. Formatter (needs Hooks).
		$formatter = new MC_Formatter($hooks);
		$this->set('formatter', $formatter);

		// 4. Http (needs Hooks + secret key).
		$secret_key = $config->get('secret_key', '');
		if (defined('MC_SECRET_KEY') && '' === $secret_key) {
			$secret_key = MC_SECRET_KEY;
		}
		$http = new MC_Http($hooks, $secret_key);
		$this->set('http', $http);

		// 5. Cache (needs cache dir).
		$content_rel = rtrim($config->get('content_dir', 'mc-content'), '/');
		$cache_dir   = defined('MC_CACHE_DIR')
			? MC_CACHE_DIR
			: $abspath . $content_rel . '/cache/';
		$cache = new MC_Cache($cache_dir);
		$this->set('cache', $cache);

		/*
		 * -----------------------------------------------------------------
		 *  Phase 2 – Authentication & Capabilities
		 * -----------------------------------------------------------------
		 */

		// 6. Capabilities.
		$capabilities = new MC_Capabilities($hooks);
		$this->set('capabilities', $capabilities);

		// 7. Session.
		$session_dir = $abspath . rtrim($config->get('data_dir', 'mc-data'), '/') . '/sessions/';
		$session     = new MC_Session($hooks, $session_dir);
		$this->set('session', $session);

		// 8. User Manager.
		$users_file     = $abspath . rtrim($config->get('data_dir', 'mc-data'), '/') . '/users.php';
		$encryption_key = $config->get('encryption_key', '');
		$user_manager   = new MC_User_Manager($hooks, $formatter, $capabilities, $session, $users_file, $encryption_key);
		$this->set('user_manager', $user_manager);

		/*
		 * -----------------------------------------------------------------
		 *  Phase 3 – Content & Extensibility
		 * -----------------------------------------------------------------
		 */

		// 9. Content Type Registry.
		$content_dir    = $abspath . $content_rel . '/';
		$content_types  = new MC_Content_Type_Registry($hooks, $content_dir);
		$this->set('content_types', $content_types);

		// 10. Content Manager.
		$content_manager = new MC_Content_Manager($content_types, $hooks, $cache, $formatter, $content_dir);
		$this->set('content', $content_manager);

		// 11. Markdown.
		$markdown = new MC_Markdown($hooks);
		$this->set('markdown', $markdown);

		// 12. Shortcodes.
		$shortcodes = new MC_Shortcodes();
		$this->set('shortcodes', $shortcodes);

		// 13. Field Registry.
		$fields = new MC_Field_Registry($hooks, $formatter);
		$this->set('fields', $fields);

		// 14. Settings.
		$settings_dir = $abspath . rtrim($config->get('data_dir', 'mc-data'), '/') . '/settings/';
		$settings     = new MC_Settings($hooks, $settings_dir);
		$this->set('settings', $settings);

		// 15. Settings Registry.
		$settings_registry = new MC_Settings_Registry($hooks, $settings, $fields, $http);
		$this->set('settings_registry', $settings_registry);

		/*
		 * -----------------------------------------------------------------
		 *  Phase 4 – Presentation & Extensibility
		 * -----------------------------------------------------------------
		 */

		// 16. Router.
		$router = new MC_Router($hooks, $content_manager, $content_types);
		$this->set('router', $router);

		// 17. Asset Manager.
		$assets = new MC_Asset_Manager($hooks, $formatter);
		$this->set('assets', $assets);

		// 18. Theme Manager.
		$themes_dir    = $content_dir . 'themes/';
		$theme_manager = new MC_Theme_Manager($hooks, $config, $themes_dir);
		$this->set('themes', $theme_manager);

		// 19. Plugin Manager.
		$plugins_dir    = $content_dir . 'plugins/';
		$mu_plugins_dir = $content_dir . 'mu-plugins/';
		$plugin_manager = new MC_Plugin_Manager($hooks, $config, $plugins_dir, $mu_plugins_dir);
		$this->set('plugins', $plugin_manager);

		// 20. Template Loader.
		$template_loader = new MC_Template_Loader($hooks, $router, $theme_manager);
		$this->set('template_loader', $template_loader);

		// 21. Template Tags.
		$template_tags = new MC_Template_Tags(
			$hooks,
			$router,
			$markdown,
			$shortcodes,
			$formatter,
			$theme_manager,
			$assets,
			$user_manager,
			$template_loader
		);
		$this->set('template_tags', $template_tags);

		// 22. Admin Bar.
		$admin_bar = new MC_Admin_Bar($hooks, $user_manager, $router, $formatter);
		$this->set('admin_bar', $admin_bar);

		// 23. Setup.
		$setup = new MC_Setup($config, $user_manager, $hooks);
		$this->set('setup', $setup);

		// Mark booted before running lifecycle so re-entrant calls from
		// plugins or hooks short-circuit cleanly.
		$this->booted = true;

		/*
		 * -----------------------------------------------------------------
		 *  Lifecycle: constants, environment, core registrations
		 * -----------------------------------------------------------------
		 */

		$this->define_constants($config, $abspath, $content_rel);
		$this->configure_environment($config);

		// Initialise built-in roles (administrator, editor, author, contributor).
		$capabilities->initialise_roles();

		// Register built-in content types (page, post).
		$content_types->register_defaults();

		// Register built-in field types (text, textarea, select, checkbox, …).
		$fields->register_core_types();

		/*
		 * -----------------------------------------------------------------
		 *  Default filters — replaces mc-includes/default-filters.php
		 * -----------------------------------------------------------------
		 */

		// Print styles in <head>.
		$hooks->add_action('mc_head', array($assets, 'print_styles'), 8);
		// Print head scripts in <head>.
		$hooks->add_action('mc_head', array($assets, 'print_head_scripts'), 9);
		// Render admin bar immediately after <body> opens.
		$hooks->add_action('mc_body_open', array($admin_bar, 'render'), 1);
		// Print footer scripts before </body>.
		$hooks->add_action('mc_footer', array($assets, 'print_footer_scripts'), 20);
		// Process shortcodes when content is output.
		$hooks->add_filter('mc_the_content', array($shortcodes, 'do_shortcode'), 11);

		/*
		 * Sync "general" settings page back to config.json so that
		 * constants and legacy consumers continue to work after save.
		 */
		$hooks->add_action(
			'mc_settings_page_saved',
			static function (string $page_slug, array $values) use ($config): void {
				if ('general' !== $page_slug) {
					return;
				}

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
			},
			10,
			2
		);

		/*
		 * -----------------------------------------------------------------
		 *  Settings registration
		 * -----------------------------------------------------------------
		 */

		$settings_registry->register_core_pages();

		// Seed settings from config.json values if the settings file has
		// not been created yet (first-run or migration scenario).
		$settings_file = $settings_dir . 'core.general.json';
		if (!is_file($settings_file)) {
			$seed_keys = array(
				'site_name', 'site_description', 'site_url', 'timezone',
				'front_page', 'posts_per_page', 'permalink_structure', 'debug',
			);

			$seed = array();
			foreach ($seed_keys as $seed_key) {
				$val = $config->get($seed_key);
				if (null !== $val) {
					$seed[$seed_key] = $val;
				}
			}

			if (!empty($seed)) {
				$settings->update('core.general', $seed);
			}
		}

		/*
		 * -----------------------------------------------------------------
		 *  Plugin loading
		 * -----------------------------------------------------------------
		 */

		$plugin_manager->load_mu_plugins();

		/**
		 * Fires after must-use plugins have loaded.
		 *
		 * @since {version}
		 */
		$hooks->do_action('mc_muplugins_loaded');

		$plugin_manager->load_plugins();

		/**
		 * Fires after all active plugins have loaded.
		 *
		 * @since {version}
		 */
		$hooks->do_action('mc_plugins_loaded');

		/*
		 * -----------------------------------------------------------------
		 *  Theme loading
		 * -----------------------------------------------------------------
		 */

		$theme_manager->load();

		/*
		 * -----------------------------------------------------------------
		 *  Application ready hooks
		 * -----------------------------------------------------------------
		 */

		/**
		 * Fires when the application has fully initialised.
		 *
		 * All services, plugins, and themes are loaded by this point.
		 *
		 * @since {version}
		 */
		$hooks->do_action('mc_init');

		/**
		 * Fires after mc_init — final opportunity for late setup.
		 *
		 * @since {version}
		 */
		$hooks->do_action('mc_loaded');
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Private boot helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Define a constant only if it has not already been defined.
	 *
	 * @since {version}
	 *
	 * @param string $name  Constant name.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private function maybe_define(string $name, mixed $value): void
	{

		if (!defined($name)) {
			define($name, $value);
		}
	}

	/**
	 * Define all MinimalCMS path and settings constants.
	 *
	 * @since {version}
	 *
	 * @param MC_Config $config      Configuration object.
	 * @param string    $abspath     Absolute path to the site root.
	 * @param string    $content_rel Relative content directory name.
	 * @return void
	 */
	private function define_constants(MC_Config $config, string $abspath, string $content_rel): void
	{

		$content_dir = $abspath . $content_rel . '/';
		$data_dir    = $abspath . rtrim($config->get('data_dir', 'mc-data'), '/') . '/';

		$this->maybe_define('MC_INC', $abspath . 'mc-includes/');
		$this->maybe_define('MC_CONTENT_DIR', $content_dir);
		$this->maybe_define('MC_DATA_DIR', $data_dir);
		$this->maybe_define('MC_PLUGIN_DIR', $content_dir . 'plugins/');
		$this->maybe_define('MC_MU_PLUGIN_DIR', $content_dir . 'mu-plugins/');
		$this->maybe_define('MC_THEME_DIR', $content_dir . 'themes/');
		$this->maybe_define('MC_UPLOAD_DIR', $content_dir . 'uploads/');
		$this->maybe_define('MC_CACHE_DIR', $content_dir . 'cache/');
		$this->maybe_define('MC_SESSION_DIR', $data_dir . 'sessions/');
		$this->maybe_define('MC_LOG_DIR', $data_dir . 'logs/');

		// Runtime values from config.
		$this->maybe_define('MC_SITE_URL', rtrim((string) $config->get('site_url', ''), '/'));
		$this->maybe_define('MC_SITE_NAME', (string) $config->get('site_name', ''));
		$this->maybe_define('MC_SITE_DESCRIPTION', (string) $config->get('site_description', ''));
		$this->maybe_define('MC_TIMEZONE', (string) $config->get('timezone', 'UTC'));
		$this->maybe_define('MC_DEBUG', (bool)   $config->get('debug', false));
		$this->maybe_define('MC_SECRET_KEY', (string) $config->get('secret_key', ''));
		$this->maybe_define('MC_ENCRYPTION_KEY', (string) $config->get('encryption_key', ''));
		$this->maybe_define('MC_FRONT_PAGE', (string) $config->get('front_page', 'index'));
		$this->maybe_define('MC_POSTS_PER_PAGE', (int)    $config->get('posts_per_page', 10));
		$this->maybe_define('MC_PERMALINK_STRUCTURE', (string) $config->get('permalink_structure', '/{type}/{slug}/'));
		$this->maybe_define('MC_ACTIVE_THEME', (string) $config->get('active_theme', 'default'));
		$this->maybe_define('MC_VERSION', self::VERSION);
	}

	/**
	 * Configure the PHP runtime environment.
	 *
	 * @since {version}
	 *
	 * @param MC_Config $config Configuration object.
	 * @return void
	 */
	private function configure_environment(MC_Config $config): void
	{

		$timezone = $config->get('timezone', 'UTC');
		if (is_string($timezone) && '' !== $timezone) {
			date_default_timezone_set($timezone);
		}

		$debug = (bool) $config->get('debug', false);

		if ($debug) {
			ini_set('display_errors', '1');
			error_reporting(E_ALL);
		} else {
			ini_set('display_errors', '0');
			error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
		}
	}

	/**
	 * Register a service by key.
	 *
	 * @since {version}
	 *
	 * @param string $key     Service identifier.
	 * @param object $service Service instance.
	 * @return void
	 */
	public function set(string $key, object $service): void
	{

		$this->services[$key] = $service;
	}

	/**
	 * Retrieve a service by key.
	 *
	 * @since {version}
	 *
	 * @param string $key Service identifier.
	 * @return object
	 *
	 * @throws \RuntimeException If service not registered.
	 */
	public function get(string $key): object
	{

		if (!isset($this->services[$key])) {
			throw new \RuntimeException(
				sprintf('Service "%s" is not registered in MC_App.', $key)
			);
		}

		return $this->services[$key];
	}

	/**
	 * Check if a service is registered.
	 *
	 * @since {version}
	 *
	 * @param string $key Service identifier.
	 * @return bool
	 */
	public function has(string $key): bool
	{

		return isset($this->services[$key]);
	}

	/**
	 * Check if the app has booted.
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function is_booted(): bool
	{

		return $this->booted;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Typed accessors — Foundation (Phase 1)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the configuration service.
	 *
	 * @since {version}
	 *
	 * @return MC_Config
	 */
	public function config(): MC_Config
	{

		return $this->services['config'];
	}

	/**
	 * Get the hooks engine.
	 *
	 * @since {version}
	 *
	 * @return MC_Hooks
	 */
	public function hooks(): MC_Hooks
	{

		return $this->services['hooks'];
	}

	/**
	 * Get the formatter/sanitiser.
	 *
	 * @since {version}
	 *
	 * @return MC_Formatter
	 */
	public function formatter(): MC_Formatter
	{

		return $this->services['formatter'];
	}

	/**
	 * Get the HTTP helper.
	 *
	 * @since {version}
	 *
	 * @return MC_Http
	 */
	public function http(): MC_Http
	{

		return $this->services['http'];
	}

	/**
	 * Get the cache service.
	 *
	 * @since {version}
	 *
	 * @return MC_Cache
	 */
	public function cache(): MC_Cache
	{

		return $this->services['cache'];
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Typed accessors — Infrastructure (Phase 2 stubs)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the capabilities service.
	 *
	 * @since {version}
	 *
	 * @return MC_Capabilities
	 */
	public function capabilities(): MC_Capabilities
	{

		return $this->services['capabilities'];
	}

	/**
	 * Get the session service.
	 *
	 * @since {version}
	 *
	 * @return MC_Session
	 */
	public function session(): MC_Session
	{

		return $this->services['session'];
	}

	/**
	 * Get the user manager.
	 *
	 * @since {version}
	 *
	 * @return MC_User_Manager
	 */
	public function users(): MC_User_Manager
	{

		return $this->services['user_manager'];
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Typed accessors — Content (Phase 3 stubs)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the field registry.
	 *
	 * @since {version}
	 *
	 * @return MC_Field_Registry
	 */
	public function fields(): MC_Field_Registry
	{

		return $this->services['fields'];
	}

	/**
	 * Get the settings storage.
	 *
	 * @since {version}
	 *
	 * @return MC_Settings
	 */
	public function settings(): MC_Settings
	{

		return $this->services['settings'];
	}

	/**
	 * Get the settings page registry.
	 *
	 * @since {version}
	 *
	 * @return MC_Settings_Registry
	 */
	public function settings_registry(): MC_Settings_Registry
	{

		return $this->services['settings_registry'];
	}

	/**
	 * Get the content type registry.
	 *
	 * @since {version}
	 *
	 * @return MC_Content_Type_Registry
	 */
	public function content_types(): MC_Content_Type_Registry
	{

		return $this->services['content_types'];
	}

	/**
	 * Get the content manager.
	 *
	 * @since {version}
	 *
	 * @return MC_Content_Manager
	 */
	public function content(): MC_Content_Manager
	{

		return $this->services['content'];
	}

	/**
	 * Get the Markdown parser.
	 *
	 * @since {version}
	 *
	 * @return MC_Markdown
	 */
	public function markdown(): MC_Markdown
	{

		return $this->services['markdown'];
	}

	/**
	 * Get the shortcodes engine.
	 *
	 * @since {version}
	 *
	 * @return MC_Shortcodes
	 */
	public function shortcodes(): MC_Shortcodes
	{

		return $this->services['shortcodes'];
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Typed accessors — Presentation (Phase 4 stubs)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the router.
	 *
	 * @since {version}
	 *
	 * @return MC_Router
	 */
	public function router(): MC_Router
	{

		return $this->services['router'];
	}

	/**
	 * Get the template loader.
	 *
	 * @since {version}
	 *
	 * @return MC_Template_Loader
	 */
	public function template_loader(): MC_Template_Loader
	{

		return $this->services['template_loader'];
	}

	/**
	 * Get the template tags helper.
	 *
	 * @since {version}
	 *
	 * @return MC_Template_Tags
	 */
	public function template_tags(): MC_Template_Tags
	{

		return $this->services['template_tags'];
	}

	/**
	 * Get the asset manager.
	 *
	 * @since {version}
	 *
	 * @return MC_Asset_Manager
	 */
	public function assets(): MC_Asset_Manager
	{

		return $this->services['assets'];
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Typed accessors — Extensibility (Phase 4 stubs)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get the theme manager.
	 *
	 * @since {version}
	 *
	 * @return MC_Theme_Manager
	 */
	public function themes(): MC_Theme_Manager
	{

		return $this->services['themes'];
	}

	/**
	 * Get the plugin manager.
	 *
	 * @since {version}
	 *
	 * @return MC_Plugin_Manager
	 */
	public function plugins(): MC_Plugin_Manager
	{

		return $this->services['plugins'];
	}

	/**
	 * Get the admin bar.
	 *
	 * @since {version}
	 *
	 * @return MC_Admin_Bar
	 */
	public function admin_bar(): MC_Admin_Bar
	{

		return $this->services['admin_bar'];
	}

	/**
	 * Get the setup service.
	 *
	 * @since {version}
	 *
	 * @return MC_Setup
	 */
	public function setup(): MC_Setup
	{

		return $this->services['setup'];
	}

	/**
	 * Reset the singleton for testing purposes.
	 *
	 * @since {version}
	 * @internal
	 *
	 * @return void
	 */
	public static function reset(): void
	{

		self::$instance = null;
	}
}
