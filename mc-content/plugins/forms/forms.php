<?php

/**
 * Plugin Name: Forms
 * Description: Create and manage forms with field builder, notification options, and confirmation settings.
 * Version:     1.0.0
 * Author:      MinimalCMS
 *
 * @package MinimalCMS\Forms
 */

defined('MC_ABSPATH') || exit;

/**
 * Base path for this plugin.
 */
define('MC_FORMS_DIR', __DIR__ . '/');

/**
 * Directory where form submissions are stored.
 */
define('MC_FORMS_SUBMISSIONS_DIR', MC_CONTENT_DIR . 'forms-submissions/');

/*
 * -------------------------------------------------------------------------
 *  Includes
 * -------------------------------------------------------------------------
 */

require_once MC_FORMS_DIR . 'includes/form-crypto.php';
require_once MC_FORMS_DIR . 'includes/form-schema.php';
require_once MC_FORMS_DIR . 'includes/form-field-types.php';
require_once MC_FORMS_DIR . 'includes/form-settings.php';
require_once MC_FORMS_DIR . 'includes/form-admin.php';
require_once MC_FORMS_DIR . 'includes/form-render.php';
require_once MC_FORMS_DIR . 'includes/form-submit.php';
require_once MC_FORMS_DIR . 'includes/form-notify.php';
require_once MC_FORMS_DIR . 'includes/form-confirmation.php';

/*
 * -------------------------------------------------------------------------
 *  Content type registration
 * -------------------------------------------------------------------------
 */

/**
 * Register the "form" content type as a non-public internal type.
 *
 * Forms are managed exclusively through the admin UI and rendered
 * via shortcodes or the plugin's dedicated endpoint.
 *
 * @since 1.0.0
 * @return void
 */
function forms_register_content_type(): void
{

	mc_register_content_type(
		'form',
		array(
			'label'        => 'Forms',
			'singular'     => 'Form',
			'public'       => false,
			'hierarchical' => false,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'form' ),
			'supports'     => array( 'title' ),
		)
	);
}
mc_add_action('mc_init', 'forms_register_content_type');

/*
 * -------------------------------------------------------------------------
 *  Admin menu
 * -------------------------------------------------------------------------
 */

/**
 * Add "Forms" entries to the admin sidebar.
 *
 * @since 1.0.0
 * @return void
 */
function forms_admin_menu(): void
{

	global $mc_admin_menu;

	// Main "Forms" list link.
	$entry = array(
		'title'      => 'Forms',
		'url'        => mc_admin_url('pages.php?type=form'),
		'capability' => 'edit_content',
		'icon'       => '&#x1F4CB;',
	);

	// "Submissions" link (child entry).
	$submissions_entry = array(
		'title'      => 'Submissions',
		'url'        => mc_admin_url('form-submissions.php'),
		'capability' => 'edit_content',
		'icon'       => '&#x1F4EC;',
	);

	// Insert after Posts if present, otherwise after Pages.
	$insert_at = 2;
	foreach ($mc_admin_menu as $i => $item) {
		if (str_contains($item['url'] ?? '', 'type=post')) {
			$insert_at = $i + 1;
			break;
		}
	}

	array_splice($mc_admin_menu, $insert_at, 0, array( $entry, $submissions_entry ));
}
mc_add_action('mc_admin_menu', 'forms_admin_menu');

/*
 * -------------------------------------------------------------------------
 *  Settings page (via Settings API)
 * -------------------------------------------------------------------------
 */

/**
 * Register the Forms global settings page through the Settings API.
 *
 * @since 1.0.0
 * @return void
 */
function forms_register_settings(): void
{

	forms_register_settings_page();
	forms_register_field_types();
}
mc_add_action('mc_register_settings', 'forms_register_settings');

/*
 * -------------------------------------------------------------------------
 *  Frontend assets
 * -------------------------------------------------------------------------
 */

/**
 * Enqueue the frontend form stylesheet on public pages.
 *
 * @since 1.0.0
 * @return void
 */
function forms_enqueue_frontend_styles(): void
{

	if (mc_is_admin_request()) {
		return;
	}

	$plugin_url = mc_site_url('mc-content/plugins/forms/');
	mc_enqueue_style('mc-forms', $plugin_url . 'assets/css/form-frontend.css');
}
mc_add_action('mc_init', 'forms_enqueue_frontend_styles');

/*
 * -------------------------------------------------------------------------
 *  Shortcode
 * -------------------------------------------------------------------------
 */

mc_add_shortcode('form', 'forms_shortcode_handler');

/*
 * -------------------------------------------------------------------------
 *  Frontend route: /form/{slug}
 * -------------------------------------------------------------------------
 */

/**
 * Register a dedicated public endpoint for forms.
 *
 * @since 1.0.0
 * @return void
 */
function forms_register_routes(): void
{

	mc_add_route('form/([a-z0-9-]+)', 'forms_endpoint_handler', 5);
}
mc_add_action('mc_init', 'forms_register_routes');

/*
 * -------------------------------------------------------------------------
 *  Submission processing
 * -------------------------------------------------------------------------
 */

/**
 * Listen for form submissions early in the request lifecycle.
 *
 * @since 1.0.0
 * @return void
 */
function forms_listen_for_submissions(): void
{

	if (! mc_is_post_request()) {
		return;
	}

	$form_slug = mc_sanitize_slug(mc_input('_mc_form_slug', 'post') ?? '');

	if ('' === $form_slug) {
		return;
	}

	forms_process_submission($form_slug);
}
mc_add_action('mc_init', 'forms_listen_for_submissions', 20);

/*
 * -------------------------------------------------------------------------
 *  Activation hook
 * -------------------------------------------------------------------------
 */

/**
 * Ensure storage directories exist on plugin activation.
 *
 * @since 1.0.0
 * @return void
 */
function forms_activate(): void
{

	if (! is_dir(MC_FORMS_SUBMISSIONS_DIR)) {
		mkdir(MC_FORMS_SUBMISSIONS_DIR, 0755, true);
	}

	// Protect submission files from direct access.
	$htaccess = MC_FORMS_SUBMISSIONS_DIR . '.htaccess';
	if (! is_file($htaccess)) {
		file_put_contents($htaccess, "Deny from all\n", LOCK_EX);
	}
	$index = MC_FORMS_SUBMISSIONS_DIR . 'index.php';
	if (! is_file($index)) {
		file_put_contents($index, "<?php\n// Silence is golden.\n", LOCK_EX);
	}
}
mc_register_activation_hook(__FILE__, 'forms_activate');
