<?php

/**
 * MinimalCMS — Admin Dashboard
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once defined('MC_INC') ? MC_INC . 'admin/bootstrap.php' : dirname(__DIR__, 3) . '/mc-includes/admin/bootstrap.php';

$admin_page_title = 'Dashboard';
require mc_get_admin_theme_template('templates/admin-header.php');

?>

<?php require __DIR__ . '/widgets/widget-site-info.php'; ?>

<div class="dashboard-grid-2col">
<?php require __DIR__ . '/widgets/widget-recent-pages.php'; ?>
<?php require __DIR__ . '/widgets/widget-quick-links.php'; ?>
</div>

<?php
mc_do_action('mc_admin_dashboard');
require mc_get_admin_theme_template('templates/admin-footer.php');
