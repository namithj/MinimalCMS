<?php
/**
 * MinimalCMS Bootstrap Sequence
 *
 * Loads all core libraries, initialises subsystems, loads plugins and the
 * active theme, and fires the init hooks. Called from mc-load.php.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/*
 * -------------------------------------------------------------------------
 *  Phase 1 — Core libraries (zero external deps)
 * -------------------------------------------------------------------------
 */

require_once MC_INC . 'class-mc-error.php';
require_once MC_INC . 'hooks.php';
require_once MC_INC . 'formatting.php';
require_once MC_INC . 'http.php';
require_once MC_INC . 'cache.php';

/*
 * -------------------------------------------------------------------------
 *  Phase 2 — Subsystems
 * -------------------------------------------------------------------------
 */

require_once MC_INC . 'capabilities.php';
require_once MC_INC . 'user.php';
require_once MC_INC . 'fields.php';
require_once MC_INC . 'settings.php';
require_once MC_INC . 'content.php';
require_once MC_INC . 'markdown.php';
require_once MC_INC . 'rewrite.php';
require_once MC_INC . 'plugin.php';
require_once MC_INC . 'theme.php';
require_once MC_INC . 'template-loader.php';
require_once MC_INC . 'template-tags.php';
require_once MC_INC . 'shortcodes.php';

/*
 * -------------------------------------------------------------------------
 *  Phase 3 — Default hooks and built-in content types
 * -------------------------------------------------------------------------
 */

require_once MC_INC . 'default-filters.php';
require_once MC_INC . 'content-types.php';

// Initialise roles and default content types.
mc_initialise_roles();
mc_create_initial_content_types();

// Register core field types and settings pages.
mc_register_core_field_types();
mc_register_core_settings_pages();
mc_maybe_seed_core_settings();

/*
 * -------------------------------------------------------------------------
 *  Phase 4 — Must-use plugins
 * -------------------------------------------------------------------------
 */

mc_load_mu_plugins();

/**
 * Fires after must-use plugins have been loaded.
 *
 * @since 1.0.0
 */
mc_do_action( 'mc_muplugins_loaded' );

/*
 * -------------------------------------------------------------------------
 *  Phase 5 — Regular plugins
 * -------------------------------------------------------------------------
 */

mc_load_plugins();

/**
 * Fires after all active plugins have been loaded.
 *
 * @since 1.0.0
 */
mc_do_action( 'mc_plugins_loaded' );

/*
 * -------------------------------------------------------------------------
 *  Phase 6 — Theme
 * -------------------------------------------------------------------------
 */

mc_load_theme();

/*
 * -------------------------------------------------------------------------
 *  Phase 7 — Init
 * -------------------------------------------------------------------------
 */

/**
 * Fires when MinimalCMS is fully initialised.
 *
 * Plugins and themes should register custom content types, routes,
 * shortcodes, and other extensions on this hook.
 *
 * @since 1.0.0
 */
mc_do_action( 'mc_init' );

/**
 * Fires after MinimalCMS is completely loaded and ready.
 *
 * @since 1.0.0
 */
mc_do_action( 'mc_loaded' );
