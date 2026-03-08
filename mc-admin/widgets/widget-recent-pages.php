<?php

/**
 * Dashboard Widget: Recent Pages
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

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
<div class="card">
	<div class="card-header">Recent Pages</div>
	<?php if ($recent) : ?>
		<table class="mc-table widget-table">
			<?php foreach ($recent as $item) : ?>
				<tr>
					<td>
						<a href="<?php echo mc_esc_url(mc_admin_url('edit-page.php?type=page&slug=' . urlencode($item['slug']))); ?>">
							<?php echo mc_esc_html($item['title']); ?>
						</a>
					</td>
					<td class="text-right">
						<span class="badge badge-<?php echo mc_esc_attr($item['status']); ?>"><?php echo mc_esc_html($item['status']); ?></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php else : ?>
		<p class="text-muted-with-padding">No pages yet.</p>
	<?php endif; ?>
</div>
