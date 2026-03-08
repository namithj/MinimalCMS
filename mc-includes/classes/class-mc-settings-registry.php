<?php

/**
 * MC_Settings_Registry — Settings page, section and field registrations.
 *
 * Replaces Parts 2-3 of the procedural settings.php. Works with MC_Settings
 * for storage and MC_Field_Registry for rendering and validation.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Settings form registry and renderer.
 *
 * @since {version}
 */
class MC_Settings_Registry
{
	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_Settings
	 */
	private MC_Settings $settings;

	/**
	 * @since {version}
	 * @var MC_Field_Registry
	 */
	private MC_Field_Registry $fields;

	/**
	 * @since {version}
	 * @var MC_Http
	 */
	private MC_Http $http;

	/**
	 * Registered pages keyed by page slug.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $pages = array();

	/**
	 * Registered sections keyed by "page_slug:section_id".
	 *
	 * @since {version}
	 * @var array
	 */
	private array $sections = array();

	/**
	 * Registered fields keyed by "page_slug:section_id:field_id".
	 *
	 * @since {version}
	 * @var array
	 */
	private array $field_defs = array();

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks          $hooks    Hooks engine.
	 * @param MC_Settings       $settings Settings storage.
	 * @param MC_Field_Registry $fields   Field registry.
	 * @param MC_Http           $http     HTTP utilities.
	 */
	public function __construct(
		MC_Hooks $hooks,
		MC_Settings $settings,
		MC_Field_Registry $fields,
		MC_Http $http
	) {

		$this->hooks    = $hooks;
		$this->settings = $settings;
		$this->fields   = $fields;
		$this->http     = $http;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register a settings page.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug Unique page identifier.
	 * @param array  $args      Page configuration.
	 * @return void
	 */
	public function register_page(string $page_slug, array $args = array()): void
	{

		$this->pages[$page_slug] = array_merge(
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

		if ('' === $this->pages[$page_slug]['menu_title']) {
			$this->pages[$page_slug]['menu_title'] = $this->pages[$page_slug]['title'];
		}
	}

	/**
	 * Get a registered settings page.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug Page slug.
	 * @return array|null
	 */
	public function get_page(string $page_slug): ?array
	{

		return $this->pages[$page_slug] ?? null;
	}

	/**
	 * Get all registered settings pages.
	 *
	 * @since {version}
	 *
	 * @return array
	 */
	public function get_pages(): array
	{

		return $this->pages;
	}

	/**
	 * Register a section within a settings page.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug  Page slug.
	 * @param string $section_id Section identifier.
	 * @param array  $args       Section configuration.
	 * @return void
	 */
	public function register_section(string $page_slug, string $section_id, array $args = array()): void
	{

		$key = $page_slug . ':' . $section_id;

		$this->sections[$key] = array_merge(
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

		if (isset($this->pages[$page_slug])) {
			$this->pages[$page_slug]['sections'][$section_id] = $this->sections[$key]['priority'];
		}
	}

	/**
	 * Register a field within a settings page section.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug  Parent page slug.
	 * @param string $section_id Parent section ID.
	 * @param string $field_id   Unique field identifier (becomes storage key).
	 * @param array  $args       Field definition.
	 * @return void
	 */
	public function register_field(string $page_slug, string $section_id, string $field_id, array $args = array()): void
	{

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

		$this->field_defs[$full_key] = $args;

		if (isset($this->sections[$section_key])) {
			$this->sections[$section_key]['fields'][] = $field_id;
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Retrieval helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Get all sections for a settings page, sorted by priority.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug Page slug.
	 * @return array Array of section definitions.
	 */
	public function get_sections(string $page_slug): array
	{

		$result = array();
		foreach ($this->sections as $key => $section) {
			if ($section['page'] === $page_slug) {
				$result[$key] = $section;
			}
		}

		uasort($result, fn($a, $b) => $a['priority'] <=> $b['priority']);

		return $result;
	}

	/**
	 * Get fields for a given page and section.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug  Page slug.
	 * @param string $section_id Section ID.
	 * @return array field_id => field definition.
	 */
	public function get_section_fields(string $page_slug, string $section_id): array
	{

		$prefix = $page_slug . ':' . $section_id . ':';
		$result = array();

		foreach ($this->field_defs as $key => $field) {
			if (str_starts_with($key, $prefix)) {
				$field_id          = substr($key, strlen($prefix));
				$result[$field_id] = $field;
			}
		}

		return $result;
	}

	/**
	 * Get all fields for a settings page (across all sections).
	 *
	 * @since {version}
	 *
	 * @param string $page_slug Page slug.
	 * @return array field_id => field definition.
	 */
	public function get_page_fields(string $page_slug): array
	{

		$sections = $this->get_sections($page_slug);
		$all      = array();

		foreach ($sections as $section) {
			$fields = $this->get_section_fields($page_slug, $section['id']);
			$all    = array_merge($all, $fields);
		}

		return $all;
	}

	/**
	 * Get current values for a settings page (from storage with defaults).
	 *
	 * @since {version}
	 *
	 * @param string $page_slug Page slug.
	 * @return array Values keyed by field ID.
	 */
	public function get_page_values(string $page_slug): array
	{

		$page = $this->get_page($page_slug);
		if (null === $page) {
			return array();
		}

		$stored = $this->settings->get_all($page['namespace']);
		$fields = $this->get_page_fields($page_slug);
		$values = array();

		foreach ($fields as $field_id => $field) {
			$values[$field_id] = $stored[$field_id] ?? ($field['default'] ?? '');
		}

		return $values;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Rendering
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Render a complete settings page form.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug  Page slug.
	 * @param array  $values     Current values keyed by field ID.
	 * @param array  $errors     Validation errors keyed by field ID.
	 * @param string $notice     Optional notice message.
	 * @param string $notice_type 'success', 'error', etc.
	 * @return void
	 */
	public function render_page(
		string $page_slug,
		array $values = array(),
		array $errors = array(),
		string $notice = '',
		string $notice_type = 'success'
	): void {

		$page = $this->get_page($page_slug);
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

		$sections = $this->get_sections($page_slug);

		foreach ($sections as $section) {
			echo '<div class="card">' . "\n";

			if ('' !== $section['title']) {
				echo '<div class="card-header">' . mc_esc_html($section['title']) . '</div>' . "\n";
			}

			if ('' !== $section['description']) {
				echo '<p class="section-description">' . mc_esc_html($section['description']) . '</p>' . "\n";
			}

			$fields = $this->get_section_fields($page_slug, $section['id']);

			foreach ($fields as $field_id => $field) {
				$field['id'] = $field_id;
				$value       = $values[$field_id] ?? ($field['default'] ?? '');
				$error       = $errors[$field_id] ?? null;

				$this->fields->render($field, $value, $error);
			}

			echo '</div>' . "\n";
		}

		echo '<div class="form-actions">' . "\n";
		echo '<button type="submit" class="btn btn-primary">Save Settings</button>' . "\n";
		echo '</div>' . "\n";

		echo '</form>' . "\n";
		echo '</div>' . "\n";
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Form processing
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Process a settings page POST submission.
	 *
	 * @since {version}
	 *
	 * @param string $page_slug Page slug.
	 * @param array  $post_data POST data (defaults to $_POST when empty).
	 * @return array{saved: bool, values: array, errors: array, notice: string, notice_type: string}
	 */
	public function handle_post(string $page_slug, array $post_data = array()): array
	{

		$page   = $this->get_page($page_slug);
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

		if (empty($post_data)) {
			$post_data = $_POST;
		}

		// Nonce check.
		$nonce = $post_data['_mc_nonce'] ?? '';
		if (!$this->http->verify_nonce($nonce, $page['nonce_action'])) {
			$result['notice']      = 'Invalid security token.';
			$result['notice_type'] = 'error';
			return $result;
		}

		// Gather registered fields.
		$fields = $this->get_page_fields($page_slug);

		// Build raw input.
		$raw = array();
		foreach ($fields as $field_id => $field) {
			$raw[$field_id] = $post_data[$field_id] ?? '';
		}

		// Sanitise and validate via the Field Registry.
		$processed = $this->fields->process($fields, $raw);

		$result['values'] = $processed['values'];
		$result['errors'] = $processed['errors'];

		if (!empty($processed['errors'])) {
			$result['notice']      = 'Please correct the errors below.';
			$result['notice_type'] = 'error';
			return $result;
		}

		// Persist.
		$saved = $this->settings->update($page['namespace'], $processed['values']);

		if (is_a($saved, 'MC_Error')) {
			$result['notice']      = $saved->get_error_message();
			$result['notice_type'] = 'error';
			return $result;
		}

		$result['saved']  = true;
		$result['notice'] = 'Settings saved.';

		/**
		 * Fires after a settings page is successfully saved.
		 *
		 * @since {version}
		 *
		 * @param string $page_slug Page slug.
		 * @param array  $values    Saved values.
		 */
		$this->hooks->do_action('mc_settings_page_saved', $page_slug, $processed['values']);

		return $result;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Field mutation helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Update the choices for a registered select field.
	 *
	 * Used by dynamic population hooks (e.g. front_page choices from pages).
	 *
	 * @since {version}
	 *
	 * @param string $page_slug  Page slug.
	 * @param string $section_id Section ID.
	 * @param string $field_id   Field ID.
	 * @param array  $choices    Key => label map.
	 * @return void
	 */
	public function set_field_choices(string $page_slug, string $section_id, string $field_id, array $choices): void
	{

		$key = $page_slug . ':' . $section_id . ':' . $field_id;

		if (isset($this->field_defs[$key])) {
			$this->field_defs[$key]['options']['choices'] = $choices;
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Core settings registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register the core settings pages shipped with MinimalCMS.
	 *
	 * Replaces the old mc_register_core_settings_pages() procedural function.
	 * Fires the mc_register_settings action at the end so plugins can add
	 * their own settings pages.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function register_core_pages(): void
	{

		/*
		 * ── Core "Site Settings" page ──────────────────────────────────────
		 * Storage namespace: core.general → mc-data/settings/core.general.json
		 */
		$this->register_page(
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
		$this->register_section(
			'general',
			'general',
			array(
				'title'    => 'General',
				'priority' => 10,
			)
		);

		$this->register_field(
			'general',
			'general',
			'site_name',
			array(
				'type'    => 'text',
				'label'   => 'Site Name',
				'default' => '',
			)
		);

		$this->register_field(
			'general',
			'general',
			'site_description',
			array(
				'type'    => 'text',
				'label'   => 'Site Description',
				'default' => '',
			)
		);

		$this->register_field(
			'general',
			'general',
			'site_url',
			array(
				'type'        => 'url',
				'label'       => 'Site URL',
				'description' => 'Leave blank for auto-detection.',
				'default'     => '',
			)
		);

		$this->register_field(
			'general',
			'general',
			'timezone',
			array(
				'type'        => 'text',
				'label'       => 'Timezone',
				'description' => 'PHP timezone string, e.g. America/New_York',
				'default'     => 'UTC',
				'attributes'  => array( 'placeholder' => 'UTC' ),
			)
		);

		/*
		 * Section: Viewing
		 */
		$this->register_section(
			'general',
			'viewing',
			array(
				'title'    => 'Viewing',
				'priority' => 20,
			)
		);

		// Choices are populated dynamically at render time via set_field_choices().
		$this->register_field(
			'general',
			'viewing',
			'front_page',
			array(
				'type'        => 'select',
				'label'       => 'Home Page',
				'description' => 'The page displayed when visitors access your site root.',
				'default'     => 'index',
				'options'     => array( 'choices' => array() ),
				'attributes'  => array( 'style' => 'max-width:320px' ),
			)
		);

		$this->register_field(
			'general',
			'viewing',
			'posts_per_page',
			array(
				'type'       => 'number',
				'label'      => 'Items Per Page',
				'default'    => 10,
				'options'    => array( 'min' => 1 ),
				'attributes' => array( 'style' => 'width:120px' ),
			)
		);

		$this->register_field(
			'general',
			'viewing',
			'permalink_structure',
			array(
				'type'        => 'text',
				'label'       => 'Permalink Structure',
				'description' => 'Tokens: {type}, {slug}',
				'default'     => '/{type}/{slug}/',
			)
		);

		/*
		 * Section: Advanced
		 */
		$this->register_section(
			'general',
			'advanced',
			array(
				'title'    => 'Advanced',
				'priority' => 30,
			)
		);

		$this->register_field(
			'general',
			'advanced',
			'debug',
			array(
				'type'        => 'checkbox',
				'label'       => 'Enable Debug Mode',
				'description' => 'Shows PHP errors and extra logging.',
				'default'     => false,
			)
		);

		/**
		 * Fires after core settings pages are registered.
		 *
		 * Plugins can hook here to add their own settings pages.
		 *
		 * @since {version}
		 */
		$this->hooks->do_action('mc_register_settings');
	}

	/**
	 * Populate the front_page select field choices from registered pages.
	 *
	 * Call this method before rendering the settings page so the select
	 * reflects current content.
	 *
	 * @since {version}
	 *
	 * @param MC_Content_Manager $content Content manager.
	 * @return void
	 */
	public function populate_front_page_choices(MC_Content_Manager $content): void
	{

		$all_pages = $content->query(
			array(
				'type'     => 'page',
				'status'   => '',
				'limit'    => 200,
				'order_by' => 'title',
				'order'    => 'ASC',
			)
		);

		$choices = array();
		foreach ($all_pages as $fp_item) {
			$choices[ $fp_item['slug'] ] = $fp_item['title'] . ' (/' . $fp_item['slug'] . ')';
		}

		$this->set_field_choices('general', 'viewing', 'front_page', $choices);
	}
}
