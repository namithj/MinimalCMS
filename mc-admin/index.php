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

// Gather stats.
$content_types = mc_get_content_types();
$users         = mc_get_users();
$user_count    = is_array($users) ? count($users) : 0;
$plugins       = mc_discover_plugins();
$themes        = mc_discover_themes();

?>

<div class="dashboard-grid">
	<?php
	foreach ($content_types as $slug => $type) :
		$count = mc_count_content($slug);
		?>
		<div class="stat-card">
			<div class="stat-number"><?php echo (int) $count; ?></div>
			<div class="stat-label"><?php echo mc_esc_html($type['label'] ?? ucfirst($slug)); ?></div>
		</div>
	<?php endforeach; ?>

	<div class="stat-card">
		<div class="stat-number"><?php echo $user_count; ?></div>
		<div class="stat-label">Users</div>
	</div>

	<div class="stat-card">
		<div class="stat-number"><?php echo count($plugins); ?></div>
		<div class="stat-label">Plugins</div>
	</div>

	<div class="stat-card">
		<div class="stat-number"><?php echo count($themes); ?></div>
		<div class="stat-label">Themes</div>
	</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

	<!-- Recent Pages -->
	<div class="card">
		<div class="card-header">Recent Pages</div>
		<?php
		$recent = mc_query_content(
			array(
				'type'     => 'page',
				'order_by' => 'modified',
				'order'    => 'desc',
				'limit'    => 5,
				'status'   => '',
			)
		);
		?>
		<?php if ($recent) : ?>
			<table class="mc-table" style="border:none;margin:0;">
				<?php foreach ($recent as $item) : ?>
					<tr>
						<td>
							<a href="<?php echo mc_esc_url(mc_admin_url('edit-page.php?type=page&slug=' . urlencode($item['slug']))); ?>">
								<?php echo mc_esc_html($item['title']); ?>
							</a>
						</td>
						<td style="text-align:right;">
							<span class="badge badge-<?php echo mc_esc_attr($item['status']); ?>"><?php echo mc_esc_html($item['status']); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php else : ?>
			<p style="color:#646970;padding:12px 0;">No pages yet.</p>
		<?php endif; ?>
	</div>

	<!-- Quick Links -->
	<div class="card">
		<div class="card-header">Quick Links</div>
		<ul style="list-style:none;padding:0;">
			<?php if (mc_current_user_can('create_content')) : ?>
				<li style="padding:8px 0;border-bottom:1px solid #dcdcde;">
					<a href="<?php echo mc_esc_url(mc_admin_url('edit-page.php?type=page')); ?>">+ Create New Page</a>
				</li>
			<?php endif; ?>
			<?php if (mc_current_user_can('manage_users')) : ?>
				<li style="padding:8px 0;border-bottom:1px solid #dcdcde;">
					<a href="<?php echo mc_esc_url(mc_admin_url('user-edit.php')); ?>">+ Add New User</a>
				</li>
			<?php endif; ?>
			<li style="padding:8px 0;border-bottom:1px solid #dcdcde;">
				<a href="<?php echo mc_esc_url(mc_site_url()); ?>" target="_blank">View Site &rarr;</a>
			</li>
			<?php if (mc_current_user_can('manage_settings')) : ?>
				<li style="padding:8px 0;">
					<a href="<?php echo mc_esc_url(mc_admin_url('settings.php')); ?>">Site Settings</a>
				</li>
			<?php endif; ?>
		</ul>
	</div>

</div>

<?php
mc_do_action('mc_admin_dashboard');
require MC_ABSPATH . 'mc-admin/admin-footer.php';
