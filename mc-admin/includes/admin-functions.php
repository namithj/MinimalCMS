<?php
/**
 * MinimalCMS Admin Functions
 *
 * Shared helpers for admin pages.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * Admin menu items.
 *
 * @global array $mc_admin_menu
 */
global $mc_admin_menu;

$mc_admin_menu = array(
	array(
		'title'      => 'Dashboard',
		'url'        => mc_admin_url(),
		'capability' => 'view_admin',
		'icon'       => '&#x1F3E0;',
	),
	array(
		'title'      => 'Pages',
		'url'        => mc_admin_url( 'pages.php' ),
		'capability' => 'edit_content',
		'icon'       => '&#x1F4C4;',
	),
	array(
		'title'      => 'Users',
		'url'        => mc_admin_url( 'users.php' ),
		'capability' => 'manage_users',
		'icon'       => '&#x1F465;',
	),
	array(
		'title'      => 'Plugins',
		'url'        => mc_admin_url( 'plugins.php' ),
		'capability' => 'manage_plugins',
		'icon'       => '&#x1F50C;',
	),
	array(
		'title'      => 'Themes',
		'url'        => mc_admin_url( 'themes.php' ),
		'capability' => 'manage_themes',
		'icon'       => '&#x1F3A8;',
	),
	array(
		'title'      => 'Settings',
		'url'        => mc_admin_url( 'settings.php' ),
		'capability' => 'manage_settings',
		'icon'       => '&#x2699;',
	),
);

/**
 * Fires after default menu items are set.
 *
 * Plugins can add items: $mc_admin_menu[] = array( ... );
 *
 * @since 1.0.0
 */
mc_do_action( 'mc_admin_menu' );

/**
 * Render the admin menu in the sidebar.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_render_admin_menu(): void {

	global $mc_admin_menu;

	echo '<nav class="admin-menu">' . "\n";
	echo '<ul>' . "\n";

	foreach ( $mc_admin_menu as $item ) {
		if ( ! mc_current_user_can( $item['capability'] ) ) {
			continue;
		}

		$active = mc_is_current_admin_page( $item['url'] ) ? ' class="active"' : '';

		printf(
			'<li%s><a href="%s"><span class="icon">%s</span> %s</a></li>' . "\n",
			$active,
			mc_esc_url( $item['url'] ),
			$item['icon'],
			mc_esc_html( $item['title'] )
		);
	}

	echo '</ul>' . "\n";
	echo '</nav>' . "\n";
}

/**
 * Check if the given URL matches the current admin page.
 *
 * @since 1.0.0
 *
 * @param string $url Menu item URL.
 * @return bool
 */
function mc_is_current_admin_page( string $url ): bool {

	$current = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
	$target  = strtok( parse_url( $url, PHP_URL_PATH ) ?? '', '?' );

	return rtrim( $current, '/' ) === rtrim( $target, '/' );
}

/**
 * Display an admin notice.
 *
 * @since 1.0.0
 *
 * @param string $message Message text.
 * @param string $type    'success', 'error', 'warning', 'info'. Default 'info'.
 * @return void
 */
function mc_admin_notice( string $message, string $type = 'info' ): void {

	printf(
		'<div class="notice notice-%s"><p>%s</p></div>' . "\n",
		mc_esc_attr( $type ),
		mc_esc_html( $message )
	);
}
