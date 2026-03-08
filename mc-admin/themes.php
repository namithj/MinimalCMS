<?php

/**
 * MinimalCMS — Themes Manager (Admin)
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

mc_admin_require_capability('manage_themes');

/*
 * ── Handle switch theme ────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if (isset($_GET['action'], $_GET['theme'], $_GET['_nonce']) && 'activate' === $_GET['action']) {
	$theme_slug = mc_sanitize_slug(mc_input('theme', 'get') ?? '');

	if (! mc_verify_nonce($_GET['_nonce'], 'activate_theme_' . $theme_slug)) {
		$notice      = 'Invalid security token.';
		$notice_type = 'error';
	} else {
		$result = mc_switch_theme($theme_slug);
		if (mc_is_error($result)) {
			$notice      = $result->get_error_message();
			$notice_type = 'error';
		} else {
			$notice = 'Theme activated.';
		}
	}
}

$themes       = mc_discover_themes();
$active_theme = mc_app()->config()->get('active_theme', 'default');

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = 'Themes';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php mc_render_admin_notice($notice, $notice_type); ?>

<?php mc_render_page_header_bar('Themes', count($themes)); ?>

<?php if ($themes) : ?>
	<div class="card-grid-responsive">
		<?php
		foreach ($themes as $slug => $data) :
			$is_active = $slug === $active_theme;
			?>
			<div class="card<?php echo $is_active ? ' theme-card--active' : ''; ?>">
				<div class="card-header card-header--flex">
					<span><?php echo mc_esc_html($data['name'] ?? $slug); ?></span>
					<?php if ($is_active) : ?>
						<span class="badge badge-active">Active</span>
					<?php endif; ?>
				</div>

				<?php if (! empty($data['description'])) : ?>
					<p class="text-meta-lg"><?php echo mc_esc_html($data['description']); ?></p>
				<?php endif; ?>

				<p class="text-meta">
					<?php if (! empty($data['author'])) : ?>
						By <?php echo mc_esc_html($data['author']); ?>
					<?php endif; ?>
					<?php if (! empty($data['version'])) : ?>
						&middot; v<?php echo mc_esc_html($data['version']); ?>
					<?php endif; ?>
				</p>

				<?php if (! $is_active) : ?>
					<a href="<?php echo mc_esc_url(mc_admin_url('themes.php?action=activate&theme=' . urlencode($slug) . '&_nonce=' . mc_create_nonce('activate_theme_' . $slug))); ?>"
						class="btn btn-primary btn-sm">Activate</a>
				<?php else : ?>
					<span class="btn btn-secondary btn-sm btn-disabled-visual">Active Theme</span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<?php mc_render_empty_state('&#x1F3A8;', 'No themes installed. Create a theme folder in mc-content/themes/.'); ?>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
