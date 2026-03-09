<?php

/**
 * MinimalCMS — Pages List (Admin)
 *
 * Lists content items with edit / delete actions.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

mc_admin_require_capability('edit_content');

$content_type = mc_sanitize_slug(mc_input('type', 'get') ?: 'page');
$type_obj     = mc_get_content_type($content_type);

if (! $type_obj) {
	$content_type = 'page';
	$type_obj     = mc_get_content_type('page');
}

$type_label  = $type_obj['label'] ?? ucfirst($content_type);
$type_single = $type_obj['singular'] ?? ucfirst($content_type);

/*
 * ── Handle delete action ───────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

$_delete_result = mc_handle_admin_delete_action(
	'slug',
	'delete_',
	'delete_content',
	fn($slug) => mc_delete_content($content_type, $slug),
	$type_single . ' deleted.'
);
if (null !== $_delete_result) {
	$notice      = $_delete_result['notice'];
	$notice_type = $_delete_result['notice_type'];
}

/*
 * ── Query content ──────────────────────────────────────────────────────────
 */
$status_filter = mc_sanitize_slug(mc_input('status', 'get') ?? '');
$query_args    = array(
	'type'     => $content_type,
	'order_by' => 'modified',
	'order'    => 'desc',
	'status'   => '',
);
if ($status_filter) {
	$query_args['status'] = $status_filter;
}

$items = mc_query_content($query_args);

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = $type_label;
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php mc_render_admin_notice($notice, $notice_type); ?>

<?php
mc_render_page_header_bar(
	$type_label,
	count($items),
	mc_current_user_can('create_content') ? mc_admin_url('edit-page.php?type=' . urlencode($content_type)) : ''
);
?>

<!-- Filters -->
<div class="filter-bar">
	<a href="<?php echo mc_esc_url(mc_admin_url('pages.php?type=' . urlencode($content_type))); ?>"
		class="filter-link <?php echo ! $status_filter ? 'filter-link-active' : ''; ?>">All</a>
	<a href="<?php echo mc_esc_url(mc_admin_url('pages.php?type=' . urlencode($content_type) . '&status=publish')); ?>"
		class="filter-link <?php echo 'publish' === $status_filter ? 'filter-link-active' : ''; ?>">Published</a>
	<a href="<?php echo mc_esc_url(mc_admin_url('pages.php?type=' . urlencode($content_type) . '&status=draft')); ?>"
		class="filter-link <?php echo 'draft' === $status_filter ? 'filter-link-active' : ''; ?>">Drafts</a>
</div>

<?php if ($items) : ?>
	<table class="mc-table">
		<thead>
			<tr>
				<th>Title</th>
				<th>Slug</th>
				<th>Status</th>
				<th>Updated</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($items as $item) : ?>
				<tr>
					<td>
						<strong>
							<a href="<?php echo mc_esc_url(mc_admin_url('edit-page.php?type=' . urlencode($content_type) . '&slug=' . urlencode($item['slug']))); ?>">
								<?php echo mc_esc_html($item['title']); ?>
							</a>
						</strong>
					</td>
					<td><code><?php echo mc_esc_html($item['slug']); ?></code></td>
					<td>
						<span class="badge badge-<?php echo mc_esc_attr($item['status']); ?>">
							<?php echo mc_esc_html(ucfirst($item['status'])); ?>
						</span>
					</td>
				<td class="text-muted-sm">
					<?php echo mc_esc_html(date('M j, Y', strtotime($item['modified'] ?? $item['created'] ?? ''))); ?>
				</td>
				<td class="row-actions text-right">
						<a href="<?php echo mc_esc_url(mc_admin_url('edit-page.php?type=' . urlencode($content_type) . '&slug=' . urlencode($item['slug']))); ?>">Edit</a>
						<?php if ('form' === $content_type) : ?>
							<a href="<?php echo mc_esc_url(mc_admin_url('form-submissions.php?form=' . urlencode($item['slug']))); ?>">Submissions</a>
						<?php elseif ('draft' === ($item['status'] ?? '')) : ?>
							<a href="<?php echo mc_esc_url(mc_get_preview_url($content_type, $item['slug'])); ?>" target="_blank">Preview</a>
						<?php else : ?>
							<a href="<?php echo mc_esc_url(mc_get_content_permalink($content_type, $item['slug'])); ?>" target="_blank">View</a>
						<?php endif; ?>
						<?php if (mc_current_user_can('delete_content')) : ?>
							<a href="<?php echo mc_esc_url(mc_admin_url('pages.php?type=' . urlencode($content_type) . '&action=delete&slug=' . urlencode($item['slug']) . '&_nonce=' . mc_create_nonce('delete_' . $item['slug']))); ?>"
								class="delete confirm-delete">Delete</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<?php
	mc_render_empty_state(
		'&#x1F4C4;',
		'No ' . strtolower($type_label) . ' found.',
		mc_current_user_can('create_content') ? mc_admin_url('edit-page.php?type=' . urlencode($content_type)) : '',
		'Create your first ' . strtolower($type_single)
	);
	?>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
