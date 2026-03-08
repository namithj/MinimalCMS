<?php

/**
 * MinimalCMS — Admin Dashboard
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

$admin_page_title = 'Dashboard';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php require __DIR__ . '/widgets/widget-site-info.php'; ?>

<div class="dashboard-grid-2col">
<?php require __DIR__ . '/widgets/widget-recent-pages.php'; ?>
<?php require __DIR__ . '/widgets/widget-quick-links.php'; ?>
</div>

<?php
mc_do_action( 'mc_admin_dashboard' );
require MC_ABSPATH . 'mc-admin/admin-footer.php';
