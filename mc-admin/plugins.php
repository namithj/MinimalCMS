<?php

/**
 * MinimalCMS — Plugins Manager (Admin)
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

mc_admin_require_capability('manage_plugins');

/*
 * ── Handle activate / deactivate ───────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if (isset($_GET['action'], $_GET['plugin'], $_GET['_nonce'])) {
	$plugin_file = $_GET['plugin'];
	$action      = $_GET['action'];

	if (! mc_verify_nonce($_GET['_nonce'], $action . '_plugin_' . $plugin_file)) {
		$notice      = 'Invalid security token.';
		$notice_type = 'error';
	} else {
		if ('activate' === $action) {
			$result = mc_activate_plugin($plugin_file);
		} elseif ('deactivate' === $action) {
			$result = mc_deactivate_plugin($plugin_file);
		} else {
			$result = new MC_Error('invalid_action', 'Unknown action.');
		}

		if (mc_is_error($result)) {
			$notice      = $result->get_error_message();
			$notice_type = 'error';
		} else {
			$notice = 'Plugin ' . ( 'activate' === $action ? 'activated' : 'deactivated' ) . '.';
		}
	}
}

$plugins        = mc_discover_plugins();
$active_plugins = mc_get_active_plugins();

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = 'Plugins';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php mc_render_admin_notice($notice, $notice_type); ?>

<?php mc_render_page_header_bar('Plugins', count($plugins)); ?>

<?php if ($plugins) : ?>
	<table class="mc-table">
		<thead>
			<tr>
				<th>Plugin</th>
				<th>Version</th>
				<th>Author</th>
				<th>Status</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($plugins as $file => $data) :
				$is_active = in_array($file, $active_plugins, true);
				?>
				<tr>
					<td>
						<strong><?php echo mc_esc_html($data['Name'] ?? basename($file)); ?></strong>
						<?php if (! empty($data['Description'])) : ?>
						<br><span class="text-muted-sm"><?php echo mc_esc_html($data['Description']); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo mc_esc_html($data['Version'] ?? '—'); ?></td>
					<td><?php echo mc_esc_html($data['Author'] ?? '—'); ?></td>
					<td>
						<span class="badge badge-<?php echo $is_active ? 'active' : 'inactive'; ?>">
							<?php echo $is_active ? 'Active' : 'Inactive'; ?>
						</span>
					</td>
					<td class="row-actions text-right">
						<?php if ($is_active) : ?>
							<a href="<?php echo mc_esc_url(mc_admin_url('plugins.php?action=deactivate&plugin=' . urlencode($file) . '&_nonce=' . mc_create_nonce('deactivate_plugin_' . $file))); ?>">Deactivate</a>
						<?php else : ?>
							<a href="<?php echo mc_esc_url(mc_admin_url('plugins.php?action=activate&plugin=' . urlencode($file) . '&_nonce=' . mc_create_nonce('activate_plugin_' . $file))); ?>">Activate</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<?php mc_render_empty_state('&#x1F50C;', 'No plugins installed. Drop a plugin folder into mc-content/plugins/.'); ?>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
