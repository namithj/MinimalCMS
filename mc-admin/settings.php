<?php
/**
 * MinimalCMS — Site Settings (Admin)
 *
 * Edit config.json values through the admin interface.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if ( ! mc_current_user_can( 'manage_settings' ) ) {
	mc_redirect( mc_admin_url() );
	exit;
}

$config      = $GLOBALS['mc_config'];
$notice      = '';
$notice_type = 'success';

/*
 * ── Helper for URL sanitisation (basic) ────────────────────────────────────
 */
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
if ( mc_is_post_request() ) {

	if ( ! mc_verify_nonce( mc_input( '_mc_nonce', 'post' ), 'save_settings' ) ) {
		$notice      = 'Invalid security token.';
		$notice_type = 'error';
	} else {

		$config['site_name']           = mc_sanitize_text( mc_input( 'site_name', 'post' ) ?? '' );
		$config['site_description']    = mc_sanitize_text( mc_input( 'site_description', 'post' ) ?? '' );
		$config['site_url']            = esc_url_raw( mc_input( 'site_url', 'post' ) ?? '' );
		$config['timezone']            = mc_sanitize_text( mc_input( 'timezone', 'post' ) ?? '' );
		$config['permalink_structure'] = mc_sanitize_text( mc_input( 'permalink_structure', 'post' ) ?? '' );
		$config['front_page']          = mc_sanitize_slug( mc_input( 'front_page', 'post' ) ?? 'index' );
		$config['posts_per_page']      = max( 1, (int) mc_input( 'posts_per_page', 'post' ) );
		$config['debug']               = (bool) mc_input( 'debug', 'post' );

		$saved = mc_save_config( $config );

		if ( mc_is_error( $saved ) ) {
			$notice      = $saved->get_error_message();
			$notice_type = 'error';
		} else {
			$notice = 'Settings saved.';
			// Reload.
			$config               = json_decode( file_get_contents( MC_ABSPATH . 'config.json' ), true );
			$GLOBALS['mc_config'] = $config;
		}
	}
}

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = 'Settings';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ( $notice ) : ?>
	<div class="notice notice-<?php echo mc_esc_attr( $notice_type ); ?>" data-dismiss>
		<p><?php echo mc_esc_html( $notice ); ?></p>
	</div>
<?php endif; ?>

<div style="max-width:720px;">
	<form method="post" action="">
		<?php mc_nonce_field( 'save_settings' ); ?>

		<div class="card">
			<div class="card-header">General</div>

			<div class="form-group">
				<label for="field-site-name">Site Name</label>
				<input type="text" id="field-site-name" name="site_name" class="form-control"
						value="<?php echo mc_esc_attr( $config['site_name'] ?? '' ); ?>">
			</div>

			<div class="form-group">
				<label for="field-site-description">Site Description</label>
				<input type="text" id="field-site-description" name="site_description" class="form-control"
						value="<?php echo mc_esc_attr( $config['site_description'] ?? '' ); ?>">
			</div>

			<div class="form-group">
				<label for="field-site-url">Site URL</label>
				<input type="url" id="field-site-url" name="site_url" class="form-control"
						value="<?php echo mc_esc_attr( $config['site_url'] ?? '' ); ?>">
				<p class="description">Leave blank for auto-detection.</p>
			</div>

			<div class="form-group">
				<label for="field-timezone">Timezone</label>
				<input type="text" id="field-timezone" name="timezone" class="form-control"
						value="<?php echo mc_esc_attr( $config['timezone'] ?? 'UTC' ); ?>"
						placeholder="UTC">
				<p class="description">PHP timezone string, e.g. <code>America/New_York</code></p>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Reading</div>

			<div class="form-group">
				<label for="field-front-page">Home Page</label>
				<?php
				$all_pages    = mc_query_content( array( 'type' => 'page', 'status' => '', 'limit' => 200, 'order_by' => 'title', 'order' => 'ASC' ) );
				$current_fp   = $config['front_page'] ?? 'index';
				?>
				<select id="field-front-page" name="front_page" class="form-control" style="max-width:320px;">
					<?php foreach ( $all_pages as $fp_item ) : ?>
						<option value="<?php echo mc_esc_attr( $fp_item['slug'] ); ?>"
							<?php echo $fp_item['slug'] === $current_fp ? 'selected' : ''; ?>>
							<?php echo mc_esc_html( $fp_item['title'] ); ?> (<?php echo mc_esc_html( '/' . $fp_item['slug'] ); ?>)
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">The page displayed when visitors access your site root.</p>
			</div>

			<div class="form-group">
				<label for="field-posts-per-page">Items Per Page</label>
				<input type="number" id="field-posts-per-page" name="posts_per_page" class="form-control" style="width:120px;"
						value="<?php echo (int) ( $config['posts_per_page'] ?? 10 ); ?>" min="1">
			</div>

			<div class="form-group">
				<label for="field-permalink">Permalink Structure</label>
				<input type="text" id="field-permalink" name="permalink_structure" class="form-control"
						value="<?php echo mc_esc_attr( $config['permalink_structure'] ?? '/{type}/{slug}/' ); ?>">
				<p class="description">Tokens: <code>{type}</code>, <code>{slug}</code>, <code>{year}</code>, <code>{month}</code></p>
			</div>
		</div>

		<div class="card">
			<div class="card-header">Advanced</div>

			<div class="form-group">
				<label>
					<input type="checkbox" name="debug" value="1"
						<?php echo ! empty( $config['debug'] ) ? 'checked' : ''; ?>>
					Enable Debug Mode
				</label>
				<p class="description">Shows PHP errors and extra logging.</p>
			</div>
		</div>

		<div class="form-actions">
			<button type="submit" class="btn btn-primary">Save Settings</button>
		</div>
	</form>
</div>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
