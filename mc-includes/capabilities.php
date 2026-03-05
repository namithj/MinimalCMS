<?php
/**
 * MinimalCMS Roles and Capabilities
 *
 * Defines the role → capability mapping and provides the permission check API.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * All registered roles.
 *
 * Structure: $mc_roles[ $role_slug ] = array(
 *     'label'        => string,
 *     'capabilities' => array<string, bool>,
 * )
 *
 * @global array $mc_roles
 */
global $mc_roles;
$mc_roles = array();

/**
 * Register built-in roles and their capabilities.
 *
 * Called during bootstrap (from mc-settings.php) and filterable via mc_user_roles.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_initialise_roles(): void {

	global $mc_roles;

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
	 * @since 1.0.0
	 *
	 * @param array $roles Default roles.
	 */
	$mc_roles = mc_apply_filters( 'mc_user_roles', $roles );
}

/**
 * Register a new role.
 *
 * @since 1.0.0
 *
 * @param string $slug         Role identifier.
 * @param string $label        Human-readable label.
 * @param array  $capabilities Associative array of capability => bool.
 * @return void
 */
function mc_add_role( string $slug, string $label, array $capabilities = array() ): void {

	global $mc_roles;

	$mc_roles[ $slug ] = array(
		'label'        => $label,
		'capabilities' => $capabilities,
	);
}

/**
 * Remove a registered role.
 *
 * @since 1.0.0
 *
 * @param string $slug Role identifier.
 * @return void
 */
function mc_remove_role( string $slug ): void {

	global $mc_roles;
	unset( $mc_roles[ $slug ] );
}

/**
 * Get the definition of a role.
 *
 * @since 1.0.0
 *
 * @param string $slug Role identifier.
 * @return array|null Role array or null if not found.
 */
function mc_get_role( string $slug ): ?array {

	global $mc_roles;
	return $mc_roles[ $slug ] ?? null;
}

/**
 * Get all registered roles.
 *
 * @since 1.0.0
 *
 * @return array All roles.
 */
function mc_get_roles(): array {

	global $mc_roles;
	return $mc_roles;
}

/**
 * Add a capability to an existing role.
 *
 * @since 1.0.0
 *
 * @param string $role       Role identifier.
 * @param string $capability Capability name.
 * @param bool   $grant      Whether to grant. Default true.
 * @return void
 */
function mc_add_cap( string $role, string $capability, bool $grant = true ): void {

	global $mc_roles;

	if ( isset( $mc_roles[ $role ] ) ) {
		$mc_roles[ $role ]['capabilities'][ $capability ] = $grant;
	}
}

/**
 * Remove a capability from a role.
 *
 * @since 1.0.0
 *
 * @param string $role       Role identifier.
 * @param string $capability Capability name.
 * @return void
 */
function mc_remove_cap( string $role, string $capability ): void {

	global $mc_roles;

	unset( $mc_roles[ $role ]['capabilities'][ $capability ] );
}

/**
 * Check whether a user role has a given capability.
 *
 * @since 1.0.0
 *
 * @param string $role       Role identifier.
 * @param string $capability Capability to check.
 * @return bool True if the role grants the capability.
 */
function mc_role_has_cap( string $role, string $capability ): bool {

	global $mc_roles;

	if ( ! isset( $mc_roles[ $role ] ) ) {
		return false;
	}

	return ! empty( $mc_roles[ $role ]['capabilities'][ $capability ] );
}

/**
 * Check whether a specific user has a capability.
 *
 * @since 1.0.0
 *
 * @param array  $user       User data array (must contain 'role' key).
 * @param string $capability Capability name.
 * @return bool
 */
function mc_user_can( array $user, string $capability ): bool {

	$role = $user['role'] ?? '';

	/**
	 * Short-circuit capability check.
	 *
	 * @since 1.0.0
	 *
	 * @param bool|null $result     Null to proceed with default check.
	 * @param array     $user       User data.
	 * @param string    $capability Capability name.
	 */
	$override = mc_apply_filters( 'mc_user_can', null, $user, $capability );

	if ( null !== $override ) {
		return (bool) $override;
	}

	return mc_role_has_cap( $role, $capability );
}

/**
 * Check whether the currently logged-in user has a capability.
 *
 * This is the primary permission gate used throughout the CMS.
 *
 * @since 1.0.0
 *
 * @param string $capability Capability name.
 * @return bool
 */
function mc_current_user_can( string $capability ): bool {

	$user = mc_get_current_user();

	if ( empty( $user ) ) {
		return false;
	}

	return mc_user_can( $user, $capability );
}
