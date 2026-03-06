<?php

/**
 * MinimalCMS Settings API
 *
 * Provides settings storage (file-backed JSON), settings page / section /
 * field registration, and a generic form processing engine that renders
 * and validates admin settings forms through the Fields API.
 *
 * @package MinimalCMS
 * @since   1.1.0
 */

defined('MC_ABSPATH') || exit;

/*
 * =========================================================================
 *  PART 1 — Settings storage
 * =========================================================================
 */

/**
 * In-memory cache of loaded settings files (namespace => data).
 *
 * @global array $mc_settings_cache
 */
global $mc_settings_cache;
$mc_settings_cache = array();

/**
 * Get the filesystem path for a settings namespace.
 *
 * Settings are stored in MC_DATA_DIR . 'settings/<namespace>.json'.
 *
 * @since 1.1.0
 *
 * @param string $namespace Dot-separated namespace, e.g. 'core.general'.
 * @return string Absolute path.
 */
function mc_settings_path(string $namespace): string
{

	// Sanitise the namespace into a safe filename.
	$safe = preg_replace('/[^a-z0-9._-]/', '-', strtolower($namespace));

	return MC_DATA_DIR . 'settings/' . $safe . '.json';
}

/**
 * Ensure the settings directory exists.
 *
 * @since 1.1.0
 *
 * @return void
 */
function mc_ensure_settings_dir(): void
{

	$dir = MC_DATA_DIR . 'settings/';
	if (! is_dir($dir)) {
		mkdir($dir, 0755, true);
	}
}

/**
 * Load all settings for a namespace.
 *
 * @since 1.1.0
 *
 * @param string $namespace Settings namespace.
 * @return array Key-value pairs.
 */
function mc_get_settings(string $namespace): array
{

	global $mc_settings_cache;

	if (isset($mc_settings_cache[ $namespace ])) {
		return $mc_settings_cache[ $namespace ];
	}

	$path = mc_settings_path($namespace);

	if (! is_file($path)) {
		$mc_settings_cache[ $namespace ] = array();
		return array();
	}

	$raw  = file_get_contents($path);
	$data = json_decode($raw, true);

	if (! is_array($data)) {
		$data = array();
	}

	/**
	 * Filter settings after loading from disk.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $data      Settings data.
	 * @param string $namespace Namespace.
	 */
	$data = mc_apply_filters('mc_get_settings', $data, $namespace);

	$mc_settings_cache[ $namespace ] = $data;

	return $data;
}

/**
 * Get a single setting value.
 *
 * @since 1.1.0
 *
 * @param string $namespace Settings namespace.
 * @param string $key       Setting key.
 * @param mixed  $default   Default value if not set.
 * @return mixed
 */
function mc_get_setting(string $namespace, string $key, mixed $default = null): mixed
{

	$data = mc_get_settings($namespace);
	return $data[ $key ] ?? $default;
}

/**
 * Update (merge) settings for a namespace and persist to disk.
 *
 * @since 1.1.0
 *
 * @param string $namespace Settings namespace.
 * @param array  $values    Key-value pairs to merge.
 * @return true|MC_Error True on success.
 */
function mc_update_settings(string $namespace, array $values): true|MC_Error
{

	global $mc_settings_cache;

	mc_ensure_settings_dir();

	$existing = mc_get_settings($namespace);

	/**
	 * Filter settings values before saving.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $values    New values being saved.
	 * @param array  $existing  Current stored values.
	 * @param string $namespace Namespace.
	 */
	$values = mc_apply_filters('mc_pre_update_settings', $values, $existing, $namespace);

	$merged = array_merge($existing, $values);
	$path   = mc_settings_path($namespace);
	$json   = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

	if (false === file_put_contents($path, $json . "\n", LOCK_EX)) {
		return new MC_Error('settings_write_failed', "Failed to write settings for namespace '{$namespace}'.");
	}

	$mc_settings_cache[ $namespace ] = $merged;

	/**
	 * Fires after settings are persisted.
	 *
	 * @since 1.1.0
	 *
	 * @param string $namespace Namespace.
	 * @param array  $merged    Final merged settings.
	 */
	mc_do_action('mc_settings_updated', $namespace, $merged);

	return true;
}

/**
 * Delete a specific setting key within a namespace.
 *
 * @since 1.1.0
 *
 * @param string $namespace Settings namespace.
 * @param string $key       Key to remove.
 * @return true|MC_Error True on success.
 */
function mc_delete_setting(string $namespace, string $key): true|MC_Error
{

	global $mc_settings_cache;

	$data = mc_get_settings($namespace);
	unset($data[ $key ]);

	mc_ensure_settings_dir();

	$path = mc_settings_path($namespace);
	$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

	if (false === file_put_contents($path, $json . "\n", LOCK_EX)) {
		return new MC_Error('settings_write_failed', "Failed to write settings for namespace '{$namespace}'.");
	}

	$mc_settings_cache[ $namespace ] = $data;

	return true;
}

/**
 * Delete all settings for a namespace (removes the JSON file).
 *
 * @since 1.1.0
 *
 * @param string $namespace Settings namespace.
 * @return true|MC_Error
 */
function mc_delete_settings(string $namespace): true|MC_Error
{

	global $mc_settings_cache;

	$path = mc_settings_path($namespace);

	if (is_file($path) && ! unlink($path)) {
		return new MC_Error('settings_delete_failed', "Failed to delete settings for namespace '{$namespace}'.");
	}

	unset($mc_settings_cache[ $namespace ]);

	return true;
}

/*
 * =========================================================================
 *  PART 2 — Settings page / section / field registries
 * =========================================================================
 */

/**
 * Registered settings pages keyed by page slug.
 *
 * @global array $mc_settings_pages
 */
global $mc_settings_pages;
$mc_settings_pages = array();

/**
 * Registered settings sections keyed by "page_slug:section_id".
 *
 * @global array $mc_settings_sections
 */
global $mc_settings_sections;
$mc_settings_sections = array();

/**
 * Registered settings fields keyed by "page_slug:section_id:field_id".
 *
 * @global array $mc_settings_fields
 */
global $mc_settings_fields;
$mc_settings_fields = array();

/**
 * Register a settings page.
 *
 * @since 1.1.0
 *
 * @param string $page_slug Unique page identifier.
 * @param array  $args {
 *     Page configuration.
 *
 *     @type string $title       Page title.
 *     @type string $capability  Required capability. Default 'manage_settings'.
 *     @type string $namespace   Storage namespace. Default 'core.<page_slug>'.
 *     @type string $nonce_action Nonce action name. Default 'save_<page_slug>'.
 *     @type string $menu_title  Optional menu label (defaults to $title).
 *     @type string $menu_icon   Optional emoji/icon for menu.
 *     @type int    $menu_position Optional position weight for ordering.
 * }
 * @return void
 */
function mc_register_settings_page(string $page_slug, array $args = array()): void
{

	global $mc_settings_pages;

	$mc_settings_pages[ $page_slug ] = array_merge(
		array(
			'title'         => ucfirst(str_replace('-', ' ', $page_slug)),
			'capability'    => 'manage_settings',
			'namespace'     => 'core.' . $page_slug,
			'nonce_action'  => 'save_' . $page_slug,
			'menu_title'    => '',
			'menu_icon'     => '',
			'menu_position' => 100,
			'sections'      => array(),
		),
		$args
	);

	if ('' === $mc_settings_pages[ $page_slug ]['menu_title']) {
		$mc_settings_pages[ $page_slug ]['menu_title'] = $mc_settings_pages[ $page_slug ]['title'];
	}
}

/**
 * Get a registered settings page definition.
 *
 * @since 1.1.0
 *
 * @param string $page_slug Page slug.
 * @return array|null
 */
function mc_get_settings_page(string $page_slug): ?array
{

	global $mc_settings_pages;
	return $mc_settings_pages[ $page_slug ] ?? null;
}

/**
 * Get all registered settings pages.
 *
 * @since 1.1.0
 *
 * @return array
 */
function mc_get_settings_pages(): array
{

	global $mc_settings_pages;
	return $mc_settings_pages;
}

/**
 * Register a section within a settings page.
 *
 * @since 1.1.0
 *
 * @param string $page_slug  Page this section belongs to.
 * @param string $section_id Section identifier.
 * @param array  $args {
 *     @type string $title       Section heading.
 *     @type string $description Optional description below the heading.
 *     @type int    $priority    Order weight. Default 10.
 * }
 * @return void
 */
function mc_register_settings_section(string $page_slug, string $section_id, array $args = array()): void
{

	global $mc_settings_sections, $mc_settings_pages;

	$key = $page_slug . ':' . $section_id;

	$mc_settings_sections[ $key ] = array_merge(
		array(
			'title'       => '',
			'description' => '',
			'priority'    => 10,
			'page'        => $page_slug,
			'id'          => $section_id,
			'fields'      => array(),
		),
		$args
	);

	// Track section order on the page.
	if (isset($mc_settings_pages[ $page_slug ])) {
		$mc_settings_pages[ $page_slug ]['sections'][ $section_id ] = $mc_settings_sections[ $key ]['priority'];
	}
}

/**
 * Register a field within a settings page section.
 *
 * @since 1.1.0
 *
 * @param string $page_slug  Parent page slug.
 * @param string $section_id Parent section ID.
 * @param string $field_id   Unique field identifier (becomes the storage key).
 * @param array  $args       Field definition — same shape as mc_render_field() plus
 *                            'type', 'label', 'description', 'default', 'options', etc.
 * @return void
 */
function mc_register_setting_field(string $page_slug, string $section_id, string $field_id, array $args = array()): void
{

	global $mc_settings_fields, $mc_settings_sections;

	$full_key    = $page_slug . ':' . $section_id . ':' . $field_id;
	$section_key = $page_slug . ':' . $section_id;

	$args = array_merge(
		array(
			'id'          => $field_id,
			'type'        => 'text',
			'label'       => '',
			'description' => '',
			'default'     => '',
			'options'     => array(),
			'attributes'  => array(),
			'required'    => false,
		),
		$args
	);

	$mc_settings_fields[ $full_key ] = $args;

	// Track field on its section.
	if (isset($mc_settings_sections[ $section_key ])) {
		$mc_settings_sections[ $section_key ]['fields'][] = $field_id;
	}
}

/**
 * Get all sections for a settings page, sorted by priority.
 *
 * @since 1.1.0
 *
 * @param string $page_slug Page slug.
 * @return array Array of section definitions.
 */
function mc_get_settings_page_sections(string $page_slug): array
{

	global $mc_settings_sections;

	$sections = array();
	foreach ($mc_settings_sections as $key => $section) {
		if ($section['page'] === $page_slug) {
			$sections[ $key ] = $section;
		}
	}

	uasort($sections, fn($a, $b) => $a['priority'] <=> $b['priority']);

	return $sections;
}

/**
 * Get all fields for a given page and section.
 *
 * @since 1.1.0
 *
 * @param string $page_slug  Page slug.
 * @param string $section_id Section ID.
 * @return array Associative array of field_id => field definition.
 */
function mc_get_settings_section_fields(string $page_slug, string $section_id): array
{

	global $mc_settings_fields;

	$prefix = $page_slug . ':' . $section_id . ':';
	$fields = array();

	foreach ($mc_settings_fields as $key => $field) {
		if (str_starts_with($key, $prefix)) {
			$field_id           = substr($key, strlen($prefix));
			$fields[ $field_id ] = $field;
		}
	}

	return $fields;
}

/**
 * Get all fields for a settings page (across all sections), keyed by field ID.
 *
 * @since 1.1.0
 *
 * @param string $page_slug Page slug.
 * @return array field_id => field definition.
 */
function mc_get_settings_page_fields(string $page_slug): array
{

	$sections = mc_get_settings_page_sections($page_slug);
	$all      = array();

	foreach ($sections as $section) {
		$fields = mc_get_settings_section_fields($page_slug, $section['id']);
		$all    = array_merge($all, $fields);
	}

	return $all;
}

/*
 * =========================================================================
 *  PART 3 — Settings form engine
 * =========================================================================
 */

/**
 * Render a complete settings page form.
 *
 * Outputs <form>, sections with card wrappers, fields within each section,
 * nonce, and a submit button.
 *
 * @since 1.1.0
 *
 * @param string $page_slug Page slug.
 * @param array  $values    Current values keyed by field ID.
 * @param array  $errors    Validation errors keyed by field ID.
 * @param string $notice    Optional notice message to display.
 * @param string $notice_type 'success', 'error', etc.
 * @return void
 */
function mc_render_settings_page(string $page_slug, array $values = array(), array $errors = array(), string $notice = '', string $notice_type = 'success'): void
{

	$page = mc_get_settings_page($page_slug);
	if (null === $page) {
		return;
	}

	if ('' !== $notice) {
		echo '<div class="notice notice-' . mc_esc_attr($notice_type) . '" data-dismiss>' . "\n";
		echo '<p>' . mc_esc_html($notice) . '</p>' . "\n";
		echo '</div>' . "\n";
	}

	echo '<div style="max-width:720px;">' . "\n";
	echo '<form method="post" action="">' . "\n";

	mc_nonce_field($page['nonce_action']);

	$sections = mc_get_settings_page_sections($page_slug);

	foreach ($sections as $section) {
		echo '<div class="card">' . "\n";

		if ('' !== $section['title']) {
			echo '<div class="card-header">' . mc_esc_html($section['title']) . '</div>' . "\n";
		}

		if ('' !== $section['description']) {
			echo '<p class="section-description">' . mc_esc_html($section['description']) . '</p>' . "\n";
		}

		$fields = mc_get_settings_section_fields($page_slug, $section['id']);

		foreach ($fields as $field_id => $field) {
			$field['id'] = $field_id;
			$value       = $values[ $field_id ] ?? ( $field['default'] ?? '' );
			$error       = $errors[ $field_id ] ?? null;

			mc_render_field($field, $value, $error);
		}

		echo '</div>' . "\n";
	}

	echo '<div class="form-actions">' . "\n";
	echo '<button type="submit" class="btn btn-primary">Save Settings</button>' . "\n";
	echo '</div>' . "\n";

	echo '</form>' . "\n";
	echo '</div>' . "\n";
}

/**
 * Process a settings page POST submission.
 *
 * Handles nonce verification, field sanitisation/validation, persistence,
 * and returns the result for the controller to use.
 *
 * @since 1.1.0
 *
 * @param string $page_slug Page slug.
 * @return array{
 *     saved: bool,
 *     values: array,
 *     errors: array,
 *     notice: string,
 *     notice_type: string,
 * }
 */
function mc_handle_settings_post(string $page_slug): array
{

	$page   = mc_get_settings_page($page_slug);
	$result = array(
		'saved'       => false,
		'values'      => array(),
		'errors'      => array(),
		'notice'      => '',
		'notice_type' => 'success',
	);

	if (null === $page) {
		$result['notice']      = 'Settings page not found.';
		$result['notice_type'] = 'error';
		return $result;
	}

	// Nonce check.
	if (! mc_verify_nonce(mc_input('_mc_nonce', 'post'), $page['nonce_action'])) {
		$result['notice']      = 'Invalid security token.';
		$result['notice_type'] = 'error';
		return $result;
	}

	// Gather registered fields.
	$fields = mc_get_settings_page_fields($page_slug);

	// Build raw input from POST.
	$raw = array();
	foreach ($fields as $field_id => $field) {
		$raw[ $field_id ] = mc_input($field_id, 'post');
	}

	// Sanitise and validate.
	$processed = mc_process_fields($fields, $raw);

	$result['values'] = $processed['values'];
	$result['errors'] = $processed['errors'];

	if (! empty($processed['errors'])) {
		$result['notice']      = 'Please correct the errors below.';
		$result['notice_type'] = 'error';
		return $result;
	}

	// Persist.
	$saved = mc_update_settings($page['namespace'], $processed['values']);

	if (mc_is_error($saved)) {
		$result['notice']      = $saved->get_error_message();
		$result['notice_type'] = 'error';
		return $result;
	}

	$result['saved']  = true;
	$result['notice'] = 'Settings saved.';

	/**
	 * Fires after a settings page is successfully saved.
	 *
	 * @since 1.1.0
	 *
	 * @param string $page_slug Page slug.
	 * @param array  $values    Saved values.
	 */
	mc_do_action('mc_settings_page_saved', $page_slug, $processed['values']);

	return $result;
}

/**
 * Load current values for a settings page (from storage + defaults).
 *
 * @since 1.1.0
 *
 * @param string $page_slug Page slug.
 * @return array Values keyed by field ID, with defaults applied.
 */
function mc_get_settings_page_values(string $page_slug): array
{

	$page = mc_get_settings_page($page_slug);
	if (null === $page) {
		return array();
	}

	$stored = mc_get_settings($page['namespace']);
	$fields = mc_get_settings_page_fields($page_slug);
	$values = array();

	foreach ($fields as $field_id => $field) {
		$values[ $field_id ] = $stored[ $field_id ] ?? ( $field['default'] ?? '' );
	}

	return $values;
}

/*
 * =========================================================================
 *  PART 4 — Admin page registration helpers
 * =========================================================================
 */

/**
 * Registered admin pages (beyond the hardcoded menu).
 *
 * @global array $mc_registered_admin_pages
 */
global $mc_registered_admin_pages;
$mc_registered_admin_pages = array();

/**
 * Register an admin page and optionally add it to the admin menu.
 *
 * This is a higher-level alternative to manually pushing to $mc_admin_menu.
 * The page renders through the settings form engine when a registered
 * settings page with the same slug exists, or falls back to a custom
 * render callback.
 *
 * @since 1.1.0
 *
 * @param string $slug Page slug (used as ?page=<slug> or filename).
 * @param array  $args {
 *     @type string   $title       Page title.
 *     @type string   $capability  Required capability. Default 'manage_settings'.
 *     @type string   $menu_title  Menu label. Default same as $title.
 *     @type string   $menu_icon   Emoji/icon for the sidebar menu.
 *     @type int      $menu_position Sort order. Default 100.
 *     @type callable $render      Optional custom render callback.
 *     @type bool     $add_menu    Whether to add to admin menu. Default true.
 * }
 * @return void
 */
function mc_register_admin_page(string $slug, array $args = array()): void
{

	global $mc_registered_admin_pages, $mc_admin_menu;

	$args = array_merge(
		array(
			'title'         => ucfirst(str_replace('-', ' ', $slug)),
			'capability'    => 'manage_settings',
			'menu_title'    => '',
			'menu_icon'     => '&#x1F4CB;',
			'menu_position' => 100,
			'render'        => null,
			'add_menu'      => true,
		),
		$args
	);

	if ('' === $args['menu_title']) {
		$args['menu_title'] = $args['title'];
	}

	$mc_registered_admin_pages[ $slug ] = $args;

	// Add to admin menu if requested.
	if ($args['add_menu'] && isset($mc_admin_menu)) {
		$mc_admin_menu[] = array(
			'title'      => $args['menu_title'],
			'url'        => mc_admin_url('settings.php?page=' . urlencode($slug)),
			'capability' => $args['capability'],
			'icon'       => $args['menu_icon'],
		);
	}
}

/**
 * Get a registered admin page definition.
 *
 * @since 1.1.0
 *
 * @param string $slug Page slug.
 * @return array|null
 */
function mc_get_registered_admin_page(string $slug): ?array
{

	global $mc_registered_admin_pages;
	return $mc_registered_admin_pages[ $slug ] ?? null;
}

/*
 * =========================================================================
 *  PART 5 — Core settings page registrations
 * =========================================================================
 */

/**
 * Register the core Settings pages and fields using the Settings API.
 *
 * This is the "dogfooding" entry point: the built-in Site Settings page
 * that was previously hardcoded in mc-admin/settings.php is now defined
 * through the same API that plugins and themes use.
 *
 * @since 1.1.0
 *
 * @return void
 */
function mc_register_core_settings_pages(): void
{

	/*
	 * ── Core "Site Settings" page ──────────────────────────────────────────
	 * Storage namespace: core.general  →  mc-data/settings/core.general.json
	 * but we also sync back to config.json for backward compatibility.
	 */
	mc_register_settings_page(
		'general',
		array(
			'title'        => 'Settings',
			'capability'   => 'manage_settings',
			'namespace'    => 'core.general',
			'nonce_action' => 'save_settings',
		)
	);

	/*
	 * Section: General
	 */
	mc_register_settings_section('general', 'general', array(
		'title'    => 'General',
		'priority' => 10,
	));

	mc_register_setting_field('general', 'general', 'site_name', array(
		'type'    => 'text',
		'label'   => 'Site Name',
		'default' => '',
	));

	mc_register_setting_field('general', 'general', 'site_description', array(
		'type'    => 'text',
		'label'   => 'Site Description',
		'default' => '',
	));

	mc_register_setting_field('general', 'general', 'site_url', array(
		'type'        => 'url',
		'label'       => 'Site URL',
		'description' => 'Leave blank for auto-detection.',
		'default'     => '',
	));

	mc_register_setting_field('general', 'general', 'timezone', array(
		'type'        => 'text',
		'label'       => 'Timezone',
		'description' => 'PHP timezone string, e.g. America/New_York',
		'default'     => 'UTC',
		'attributes'  => array( 'placeholder' => 'UTC' ),
	));

	/*
	 * Section: Reading
	 */
	mc_register_settings_section('general', 'reading', array(
		'title'    => 'Reading',
		'priority' => 20,
	));

	// The "Home Page" select gets its choices dynamically at render time
	// via a filter, so we register it as a normal select with empty choices.
	mc_register_setting_field('general', 'reading', 'front_page', array(
		'type'        => 'select',
		'label'       => 'Home Page',
		'description' => 'The page displayed when visitors access your site root.',
		'default'     => 'index',
		'options'     => array(
			'choices' => array(), // Populated dynamically — see mc_populate_front_page_choices().
		),
		'attributes'  => array( 'style' => 'max-width:320px' ),
	));

	mc_register_setting_field('general', 'reading', 'posts_per_page', array(
		'type'    => 'number',
		'label'   => 'Items Per Page',
		'default' => 10,
		'options' => array( 'min' => 1 ),
		'attributes' => array( 'style' => 'width:120px' ),
	));

	mc_register_setting_field('general', 'reading', 'permalink_structure', array(
		'type'        => 'text',
		'label'       => 'Permalink Structure',
		'description' => 'Tokens: {type}, {slug}, {year}, {month}',
		'default'     => '/{type}/{slug}/',
	));

	/*
	 * Section: Advanced
	 */
	mc_register_settings_section('general', 'advanced', array(
		'title'    => 'Advanced',
		'priority' => 30,
	));

	mc_register_setting_field('general', 'advanced', 'debug', array(
		'type'        => 'checkbox',
		'label'       => 'Enable Debug Mode',
		'description' => 'Shows PHP errors and extra logging.',
		'default'     => false,
	));

	/**
	 * Fires after core settings pages are registered.
	 *
	 * Plugins can hook here to add their own settings pages.
	 *
	 * @since 1.1.0
	 */
	mc_do_action('mc_register_settings');
}

/**
 * Dynamically populate the front_page select choices.
 *
 * Hooked late so content queries work. Called from the settings page
 * controller before rendering.
 *
 * @since 1.1.0
 *
 * @return void
 */
function mc_populate_front_page_choices(): void
{

	global $mc_settings_fields;

	$key = 'general:reading:front_page';

	if (! isset($mc_settings_fields[ $key ])) {
		return;
	}

	$all_pages = mc_query_content(array(
		'type'     => 'page',
		'status'   => '',
		'limit'    => 200,
		'order_by' => 'title',
		'order'    => 'ASC',
	));

	$choices = array();
	foreach ($all_pages as $fp_item) {
		$choices[ $fp_item['slug'] ] = $fp_item['title'] . ' (/' . $fp_item['slug'] . ')';
	}

	$mc_settings_fields[ $key ]['options']['choices'] = $choices;
}

/**
 * Sync core settings back to config.json for backward compatibility.
 *
 * When the "general" settings page is saved, we mirror the values into
 * the global $mc_config and persist config.json so that constants and
 * other legacy consumers continue to work.
 *
 * @since 1.1.0
 *
 * @param string $page_slug The saved page slug.
 * @param array  $values    Saved values.
 * @return void
 */
function mc_sync_core_settings_to_config(string $page_slug, array $values): void
{

	if ('general' !== $page_slug) {
		return;
	}

	global $mc_config;

	$config_keys = array(
		'site_name', 'site_description', 'site_url', 'timezone',
		'front_page', 'posts_per_page', 'permalink_structure', 'debug',
	);

	foreach ($config_keys as $key) {
		if (array_key_exists($key, $values)) {
			$mc_config[ $key ] = $values[ $key ];
		}
	}

	mc_save_config($mc_config);
}

// Wire the config sync hook.
mc_add_action('mc_settings_page_saved', 'mc_sync_core_settings_to_config', 10, 2);

/**
 * Seed core settings from config.json if the settings file doesn't exist yet.
 *
 * This ensures a smooth migration: existing config.json values appear in
 * the new settings UI on first load without data loss.
 *
 * @since 1.1.0
 *
 * @return void
 */
function mc_maybe_seed_core_settings(): void
{

	$path = mc_settings_path('core.general');

	if (is_file($path)) {
		return;
	}

	global $mc_config;

	$seed_keys = array(
		'site_name', 'site_description', 'site_url', 'timezone',
		'front_page', 'posts_per_page', 'permalink_structure', 'debug',
	);

	$seed = array();
	foreach ($seed_keys as $key) {
		if (isset($mc_config[ $key ])) {
			$seed[ $key ] = $mc_config[ $key ];
		}
	}

	if (! empty($seed)) {
		mc_update_settings('core.general', $seed);
	}
}
