<?php

/**
 * MinimalCMS Admin Functions
 *
 * Shared helpers for admin pages.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

/**
 * Admin menu items.
 *
 * @global array $mc_admin_menu
 */
global $mc_admin_menu;

$mc_admin_menu = array(
	array(
		'slug'       => 'dashboard',
		'title'      => 'Dashboard',
		'url'        => mc_admin_url(),
		'capability' => 'view_admin',
		'icon'       => '&#x1F3E0;',
	),
);

// Insert content types immediately after Dashboard, before Users.
foreach (mc_get_content_types() as $type_slug => $type_args) {
	$parent_slug = 'content-' . $type_slug;
	$label       = $type_args['label']    ?? ucfirst($type_slug);
	$singular    = $type_args['singular'] ?? ucfirst($type_slug);

	$mc_admin_menu[] = array(
		'slug'       => $parent_slug,
		'title'      => $label,
		'url'        => mc_admin_url('pages.php?type=' . rawurlencode($type_slug)),
		'capability' => 'edit_content',
		'icon'       => $type_args['menu_icon'] ?? '&#x1F4C4;',
	);

	$mc_admin_menu[] = array(
		'slug'       => $parent_slug . '-all',
		'title'      => 'All ' . $label,
		'url'        => mc_admin_url('pages.php?type=' . rawurlencode($type_slug)),
		'capability' => 'edit_content',
		'parent'     => $parent_slug,
	);

	$mc_admin_menu[] = array(
		'slug'       => $parent_slug . '-new',
		'title'      => 'New ' . $singular,
		'url'        => mc_admin_url('edit-page.php?type=' . rawurlencode($type_slug)),
		'capability' => 'create_content',
		'parent'     => $parent_slug,
	);
}

$mc_admin_menu = array_merge($mc_admin_menu, array(
	array(
		'slug'       => 'users',
		'title'      => 'Users',
		'url'        => mc_admin_url('users.php'),
		'capability' => 'manage_users',
		'icon'       => '&#x1F465;',
	),
	array(
		'slug'       => 'plugins',
		'title'      => 'Plugins',
		'url'        => mc_admin_url('plugins.php'),
		'capability' => 'manage_plugins',
		'icon'       => '&#x1F50C;',
	),
	array(
		'slug'       => 'themes',
		'title'      => 'Themes',
		'url'        => mc_admin_url('themes.php'),
		'capability' => 'manage_themes',
		'icon'       => '&#x1F3A8;',
	),
	array(
		'slug'       => 'settings',
		'title'      => 'Settings',
		'url'        => mc_admin_url('settings.php'),
		'capability' => 'manage_settings',
		'icon'       => '&#x2699;',
	),
));

mc_do_action('mc_admin_menu');

/**
 * Render the admin menu in the sidebar.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mc_render_admin_menu(): void
{

	global $mc_admin_menu;

	// Separate top-level items from submenu children.
	$top_level    = array();
	$children_map = array(); // keyed by parent slug.

	foreach ($mc_admin_menu as $item) {
		if (! empty($item['parent'])) {
			$children_map[ $item['parent'] ][] = $item;
		} else {
			$top_level[] = $item;
		}
	}

	echo '<nav class="admin-menu">' . "\n";
	echo '<ul>' . "\n";

	foreach ($top_level as $item) {
		if (! mc_current_user_can($item['capability'])) {
			continue;
		}

		$slug         = $item['slug'] ?? '';
		$raw_children = $children_map[ $slug ] ?? array();

		// Filter children the current user can access.
		$visible_children = array_filter(
			$raw_children,
			static fn($c) => mc_current_user_can($c['capability'])
		);

		$item_active  = mc_is_current_admin_page($item['url']);
		$child_active = false;
		foreach ($visible_children as $child) {
			if (mc_is_current_admin_page($child['url'])) {
				$child_active = true;
				break;
			}
		}

		$classes = array();
		if ($item_active || $child_active) {
			$classes[] = 'active';
		}
		if (! empty($visible_children)) {
			$classes[] = 'has-submenu';
		}
		$class_attr = $classes ? ' class="' . implode(' ', $classes) . '"' : '';

		printf(
			'<li%s><a href="%s"><span class="icon">%s</span> %s</a>' . "\n",
			$class_attr,
			mc_esc_url($item['url']),
			$item['icon'],
			mc_esc_html($item['title'])
		);

		if (! empty($visible_children)) {
			echo '<ul class="submenu">' . "\n";
			foreach ($visible_children as $child) {
				$child_class = mc_is_current_admin_page($child['url']) ? ' class="active"' : '';
				printf(
					'<li%s><a href="%s">%s</a></li>' . "\n",
					$child_class,
					mc_esc_url($child['url']),
					mc_esc_html($child['title'])
				);
			}
			echo '</ul>' . "\n";
		}

		echo '</li>' . "\n";
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
function mc_is_current_admin_page(string $url): bool
{

	$parsed       = parse_url($url);
	$current_path = rtrim(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), '/');
	$target_path  = rtrim($parsed['path'] ?? '', '/');

	if ($current_path !== $target_path) {
		return false;
	}

	// When the menu URL specifies a type, the current URL must match it.
	// Pages with no explicit type default to 'page'.
	if (! empty($parsed['query'])) {
		parse_str($parsed['query'], $menu_params);
		if (isset($menu_params['type'])) {
			$current_type = $_GET['type'] ?? 'page';
			if ($current_type !== $menu_params['type']) {
				return false;
			}
		}
	}

	return true;
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
function mc_admin_notice(string $message, string $type = 'info'): void
{

	printf(
		'<div class="notice notice-%s"><p>%s</p></div>' . "\n",
		mc_esc_attr($type),
		mc_esc_html($message)
	);
}

/**
 * Render a dismissible admin notice if $notice is non-empty.
 *
 * Consolidates the repeated notice markup used across every admin list
 * and edit page.
 *
 * @since 1.1.0
 *
 * @param string $notice      The notice message. Nothing is output when empty.
 * @param string $notice_type CSS modifier: 'success', 'error', 'warning', 'info'. Default 'success'.
 * @return void
 */
function mc_render_admin_notice(string $notice, string $notice_type = 'success'): void
{

	if ('' === $notice) {
		return;
	}

	printf(
		'<div class="notice notice-%s" data-dismiss><p>%s</p></div>' . "\n",
		mc_esc_attr($notice_type),
		mc_esc_html($notice)
	);
}

/**
 * Handle a GET-based delete action with nonce verification.
 *
 * Checks for action=delete in the query string, verifies the nonce, then
 * calls the supplied callback. Returns an array with 'notice' and
 * 'notice_type' keys on completion, or null when the action was not present.
 *
 * @since 1.1.0
 *
 * @param string   $id_param        GET parameter containing the entity identifier.
 * @param string   $nonce_prefix    Prefix for the nonce action (ID is appended).
 * @param string   $capability      Required capability, or empty to skip cap check.
 * @param callable $delete_callback Receives the raw ID string; must return true|MC_Error.
 * @param string   $success_message Notice text on success.
 * @return array{notice:string,notice_type:string}|null
 */
function mc_handle_admin_delete_action(
	string $id_param,
	string $nonce_prefix,
	string $capability,
	callable $delete_callback,
	string $success_message
): ?array {

	if (
		! isset($_GET['action'], $_GET[ $id_param ], $_GET['_nonce'])
		|| 'delete' !== $_GET['action']
	) {
		return null;
	}

	$id = $_GET[ $id_param ];

	if ($capability && ! mc_current_user_can($capability)) {
		return array( 'notice' => 'Permission denied.', 'notice_type' => 'error' );
	}

	if (! mc_verify_nonce($_GET['_nonce'], $nonce_prefix . $id)) {
		return array( 'notice' => 'Invalid security token.', 'notice_type' => 'error' );
	}

	$result = $delete_callback($id);

	if (mc_is_error($result)) {
		return array( 'notice' => $result->get_error_message(), 'notice_type' => 'error' );
	}

	return array( 'notice' => $success_message, 'notice_type' => 'success' );
}

/**
 * Render the standard list-page header bar (title + optional action button).
 *
 * @since 1.1.0
 *
 * @param string $title        Page heading text.
 * @param int    $count        Item count shown in parentheses. Pass -1 to omit.
 * @param string $button_url   URL for the primary action button. Empty to omit.
 * @param string $button_label Button label. Default '+ Add New'.
 * @return void
 */
function mc_render_page_header_bar(
	string $title,
	int $count = -1,
	string $button_url = '',
	string $button_label = '+ Add New'
): void {

	$count_str = $count >= 0 ? ' (' . $count . ')' : '';

	echo '<div class="page-header-bar">' . "\n";
	echo '<h2>' . mc_esc_html($title . $count_str) . '</h2>' . "\n";

	if ('' !== $button_url) {
		printf(
			'<a href="%s" class="btn btn-primary">%s</a>' . "\n",
			mc_esc_url($button_url),
			mc_esc_html($button_label)
		);
	}

	echo '</div>' . "\n";
}

/**
 * Render the standard empty-state block.
 *
 * @since 1.1.0
 *
 * @param string $icon         Emoji or HTML entity for the icon.
 * @param string $message      Descriptive paragraph text (HTML-escaped automatically).
 * @param string $action_url   URL for an optional call-to-action button. Empty to omit.
 * @param string $action_label CTA button label. Default 'Get Started'.
 * @return void
 */
function mc_render_empty_state(
	string $icon,
	string $message,
	string $action_url = '',
	string $action_label = 'Get Started'
): void {

	echo '<div class="empty-state">' . "\n";
	echo '<div class="icon">' . $icon . '</div>' . "\n"; // icon is safe (emoji / entity).
	echo '<p>' . mc_esc_html($message) . '</p>' . "\n";

	if ('' !== $action_url) {
		printf(
			'<a href="%s" class="btn btn-primary">%s</a>' . "\n",
			mc_esc_url($action_url),
			mc_esc_html($action_label)
		);
	}

	echo '</div>' . "\n";
}

/**
 * Require a capability or immediately redirect to the admin dashboard.
 *
 * Call this at the top of any admin page file that needs a specific capability
 * beyond the base view_admin gate enforced by admin.php.
 *
 * @since 1.1.0
 *
 * @param string $capability The required capability slug.
 * @return void Does not return when the current user lacks the capability.
 */
function mc_admin_require_capability(string $capability): void
{

	if (! mc_current_user_can($capability)) {
		mc_redirect(mc_admin_url());
		exit;
	}
}
