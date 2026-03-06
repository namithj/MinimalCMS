<?php

/**
 * MinimalCMS — Admin Header
 *
 * Outputs the opening HTML, sidebar navigation, and begins the content area.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined('MC_ABSPATH') || exit;

require_once MC_ABSPATH . 'mc-admin/includes/admin-functions.php';

$admin_page_title = $admin_page_title ?? 'Dashboard';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo mc_esc_html($admin_page_title); ?> &mdash; <?php echo mc_esc_html(MC_SITE_NAME); ?></title>
<link rel="stylesheet" href="<?php echo mc_esc_url(mc_admin_url('assets/css/admin.css')); ?>">
<?php mc_do_action('mc_admin_head'); ?>
<?php if (defined('MC_LOAD_EDITOR') && MC_LOAD_EDITOR) : ?>
<link rel="stylesheet" href="<?php echo mc_esc_url(mc_admin_url('assets/vendor/easymde/easymde.min.css')); ?>">
<script src="<?php echo mc_esc_url(mc_admin_url('assets/vendor/easymde/easymde.min.js')); ?>"></script>
<?php endif; ?>
</head>
<body class="mc-admin">
<div class="admin-layout">
<!-- Sidebar -->
<aside class="admin-sidebar">
<div class="sidebar-header">
				<a href="<?php echo mc_esc_url(mc_admin_url()); ?>" class="brand">Minimal<span>CMS</span></a>
</div>
<?php mc_render_admin_menu(); ?>
<div class="sidebar-footer">
				<span class="current-user"><?php echo mc_esc_html(mc_get_current_user()['display_name'] ?? ''); ?></span>
				<a href="<?php echo mc_esc_url(mc_admin_url('login.php?action=logout')); ?>" class="logout-link">Log out</a>
</div>
</aside>

<!-- Main content -->
<main class="admin-main">
<header class="admin-topbar">
				<button class="sidebar-toggle" aria-label="Toggle sidebar">&#9776;</button>
				<h1 class="page-title"><?php echo mc_esc_html($admin_page_title); ?></h1>
				<a href="<?php echo mc_esc_url(mc_site_url()); ?>" class="view-site" target="_blank">View Site &rarr;</a>
</header>

<div class="admin-content">
				<?php mc_do_action('mc_admin_notices'); ?>
