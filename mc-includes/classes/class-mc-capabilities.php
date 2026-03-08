<?php
/**
 * MC_Capabilities — Role and permission management.
 *
 * Replaces the procedural capabilities.php. Provides role CRUD and
 * capability checks using constructor-injected MC_Hooks.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Roles and permission management.
 *
 * @since {version}
 */
class MC_Capabilities {

	/**
	 * Registered roles keyed by slug.
	 *
	 * @since {version}
	 * @var array
	 */
	private array $roles = array();

	/**
	 * Hooks instance.
	 *
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks $hooks Hooks engine.
	 */
	public function __construct(MC_Hooks $hooks) {

		$this->hooks = $hooks;
	}

	/**
	 * Register built-in roles and their capabilities.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function initialise_roles(): void {

		$roles = array(
			'administrator' => array(
				'label'        => 'Administrator',
				'capabilities' => array(
					'create_content'        => true,
					'edit_content'          => true,
					'edit_others_content'   => true,
					'publish_content'       => true,
					'delete_content'        => true,
					'delete_others_content' => true,
					'manage_content_types'  => true,
					'upload_files'          => true,
					'manage_users'          => true,
					'manage_plugins'        => true,
					'manage_themes'         => true,
					'manage_settings'       => true,
					'view_admin'            => true,
				),
			),
			'editor'        => array(
				'label'        => 'Editor',
				'capabilities' => array(
					'create_content'        => true,
					'edit_content'          => true,
					'edit_others_content'   => true,
					'publish_content'       => true,
					'delete_content'        => true,
					'delete_others_content' => true,
					'manage_content_types'  => true,
					'upload_files'          => true,
					'view_admin'            => true,
				),
			),
			'author'        => array(
				'label'        => 'Author',
				'capabilities' => array(
					'create_content'  => true,
					'edit_content'    => true,
					'publish_content' => true,
					'delete_content'  => true,
					'upload_files'    => true,
					'view_admin'      => true,
				),
			),
			'contributor'   => array(
				'label'        => 'Contributor',
				'capabilities' => array(
					'create_content' => true,
					'edit_content'   => true,
					'delete_content' => true,
					'view_admin'     => true,
				),
			),
		);

		/**
		 * Filter the default role definitions.
		 *
		 * @since {version}
		 *
		 * @param array $roles Default roles.
		 */
		$this->roles = $this->hooks->apply_filters('mc_user_roles', $roles);
	}

	/**
	 * Register a new role.
	 *
	 * @since {version}
	 *
	 * @param string $slug         Role identifier.
	 * @param string $label        Human-readable label.
	 * @param array  $capabilities Associative array of capability => bool.
	 * @return void
	 */
	public function add_role(string $slug, string $label, array $capabilities = array()): void {

		$this->roles[$slug] = array(
			'label'        => $label,
			'capabilities' => $capabilities,
		);

		$this->hooks->do_action('mc_role_added', $slug, $label, $capabilities);
	}

	/**
	 * Remove a registered role.
	 *
	 * @since {version}
	 *
	 * @param string $slug Role identifier.
	 * @return void
	 */
	public function remove_role(string $slug): void {

		unset($this->roles[$slug]);
		$this->hooks->do_action('mc_role_removed', $slug);
	}

	/**
	 * Get the definition of a role.
	 *
	 * @since {version}
	 *
	 * @param string $slug Role identifier.
	 * @return array|null Role array or null if not found.
	 */
	public function get_role(string $slug): ?array {

		return $this->roles[$slug] ?? null;
	}

	/**
	 * Get all registered roles.
	 *
	 * @since {version}
	 *
	 * @return array All roles.
	 */
	public function get_roles(): array {

		return $this->roles;
	}

	/**
	 * Add a capability to an existing role.
	 *
	 * @since {version}
	 *
	 * @param string $role       Role identifier.
	 * @param string $capability Capability name.
	 * @param bool   $grant      Whether to grant. Default true.
	 * @return void
	 */
	public function add_cap(string $role, string $capability, bool $grant = true): void {

		if (isset($this->roles[$role])) {
			$this->roles[$role]['capabilities'][$capability] = $grant;
			$this->hooks->do_action('mc_capability_added', $role, $capability, $grant);
		}
	}

	/**
	 * Remove a capability from a role.
	 *
	 * @since {version}
	 *
	 * @param string $role       Role identifier.
	 * @param string $capability Capability name.
	 * @return void
	 */
	public function remove_cap(string $role, string $capability): void {

		unset($this->roles[$role]['capabilities'][$capability]);
		$this->hooks->do_action('mc_capability_removed', $role, $capability);
	}

	/**
	 * Check whether a user role has a given capability.
	 *
	 * @since {version}
	 *
	 * @param string $role       Role identifier.
	 * @param string $capability Capability to check.
	 * @return bool True if the role grants the capability.
	 */
	public function role_has_cap(string $role, string $capability): bool {

		if (!isset($this->roles[$role])) {
			return false;
		}

		return !empty($this->roles[$role]['capabilities'][$capability]);
	}

	/**
	 * Check whether a specific user has a capability.
	 *
	 * @since {version}
	 *
	 * @param array  $user       User data array (must contain 'role' key).
	 * @param string $capability Capability name.
	 * @return bool
	 */
	public function user_can(array $user, string $capability): bool {

		$role = $user['role'] ?? '';

		/**
		 * Short-circuit capability check.
		 *
		 * @since {version}
		 *
		 * @param bool|null $result     Null to proceed with default check.
		 * @param array     $user       User data.
		 * @param string    $capability Capability name.
		 */
		$override = $this->hooks->apply_filters('mc_user_can', null, $user, $capability);

		if (null !== $override) {
			return (bool) $override;
		}

		return $this->role_has_cap($role, $capability);
	}
}
