<?php
/**
 * MinimalCMS — Themes Manager (Admin)
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if ( ! mc_current_user_can( 'manage_themes' ) ) {
	mc_redirect( mc_admin_url() );
	exit;
}

/*
 * ── Handle switch theme ────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if ( isset( $_GET['action'], $_GET['theme'], $_GET['_nonce'] ) && 'activate' === $_GET['action'] ) {
	$theme_slug = $_GET['theme'];

	if ( ! mc_verify_nonce( $_GET['_nonce'], 'activate_theme_' . $theme_slug ) ) {
		$notice      = 'Invalid security token.';
		$notice_type = 'error';
	} else {
		$result = mc_switch_theme( $theme_slug );
		if ( mc_is_error( $result ) ) {
			$notice      = $result->get_error_message();
			$notice_type = 'error';
		} else {
			$notice = 'Theme activated.';
		}
	}
}

$themes       = mc_discover_themes();
$active_theme = $GLOBALS['mc_config']['active_theme'] ?? 'default';

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = 'Themes';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ( $notice ) : ?>
	<div class="notice notice-<?php echo mc_esc_attr( $notice_type ); ?>" data-dismiss>
		<p><?php echo mc_esc_html( $notice ); ?></p>
	</div>
<?php endif; ?>

<div class="page-header-bar">
	<h2>Themes (<?php echo count( $themes ); ?>)</h2>
</div>

<?php if ( $themes ) : ?>
	<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
		<?php
		foreach ( $themes as $slug => $data ) :
			$is_active = $slug === $active_theme;
			?>
			<div class="card" style="<?php echo $is_active ? 'border-color:#2271b1;border-width:2px;' : ''; ?>">
				<div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
					<span><?php echo mc_esc_html( $data['name'] ?? $slug ); ?></span>
					<?php if ( $is_active ) : ?>
						<span class="badge badge-active">Active</span>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $data['description'] ) ) : ?>
					<p style="font-size:.9rem;color:#646970;margin-bottom:12px;"><?php echo mc_esc_html( $data['description'] ); ?></p>
				<?php endif; ?>

				<p style="font-size:.85rem;color:#8c8f94;margin-bottom:12px;">
					<?php if ( ! empty( $data['author'] ) ) : ?>
						By <?php echo mc_esc_html( $data['author'] ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $data['version'] ) ) : ?>
						&middot; v<?php echo mc_esc_html( $data['version'] ); ?>
					<?php endif; ?>
				</p>

				<?php if ( ! $is_active ) : ?>
					<a href="<?php echo mc_esc_url( mc_admin_url( 'themes.php?action=activate&theme=' . urlencode( $slug ) . '&_nonce=' . mc_create_nonce( 'activate_theme_' . $slug ) ) ); ?>"
						class="btn btn-primary btn-sm">Activate</a>
				<?php else : ?>
					<span class="btn btn-secondary btn-sm" style="pointer-events:none;opacity:.6;">Active Theme</span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
<?php else : ?>
	<div class="empty-state">
		<div class="icon">&#x1F3A8;</div>
		<p>No themes installed. Create a theme folder in <code>mc-content/themes/</code>.</p>
	</div>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
