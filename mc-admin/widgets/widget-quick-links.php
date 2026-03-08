<?php

/**
 * Dashboard Widget: Quick Links
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

?>
<div class="card">
	<div class="card-header">Quick Links</div>
	<ul class="link-list">
		<?php if ( mc_current_user_can( 'create_content' ) ) : ?>
			<li>
				<a href="<?php echo mc_esc_url( mc_admin_url( 'edit-page.php?type=page' ) ); ?>">+ Create New Page</a>
			</li>
		<?php endif; ?>
		<?php if ( mc_current_user_can( 'manage_users' ) ) : ?>
			<li>
				<a href="<?php echo mc_esc_url( mc_admin_url( 'user-edit.php' ) ); ?>">+ Add New User</a>
			</li>
		<?php endif; ?>
		<li>
			<a href="<?php echo mc_esc_url( mc_site_url() ); ?>" target="_blank">View Site &rarr;</a>
		</li>
		<?php if ( mc_current_user_can( 'manage_settings' ) ) : ?>
			<li>
				<a href="<?php echo mc_esc_url( mc_admin_url( 'settings.php' ) ); ?>">Site Settings</a>
			</li>
		<?php endif; ?>
	</ul>
</div>
