<?php

/**
 * MinimalCMS — Edit / Create User (Admin)
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if (! mc_current_user_can('manage_users')) {
	mc_redirect(mc_admin_url());
	exit;
}

$edit_id = mc_input('id', 'get');
$is_new  = empty($edit_id);

/*
 * ── Load existing user ─────────────────────────────────────────────────────
 */
$user = array(
	'username'     => '',
	'email'        => '',
	'display_name' => '',
	'role'         => 'contributor',
);

if (! $is_new) {
	$existing = mc_get_user($edit_id);
	if ($existing) {
		$user = array_merge($user, $existing);
	} else {
		mc_redirect(mc_admin_url('users.php'));
		exit;
	}
}

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if (mc_is_post_request()) {
	if (! mc_verify_nonce(mc_input('_mc_nonce', 'post'), 'edit_user')) {
		$notice      = 'Invalid security token.';
		$notice_type = 'error';
	} else {
		$user['username']     = mc_sanitize_slug(mc_input('username', 'post') ?? '');
		$user['email']        = mc_sanitize_email(mc_input('email', 'post') ?? '');
		$user['display_name'] = mc_sanitize_text(mc_input('display_name', 'post') ?? '');
		$user['role']         = mc_sanitize_slug(mc_input('role', 'post') ?? '') ?: 'contributor';
		$password             = mc_input('password', 'post');
		$password_confirm     = mc_input('password_confirm', 'post');

		if (empty($user['username'])) {
			$notice      = 'Username is required.';
			$notice_type = 'error';
		} elseif (empty($user['email'])) {
			$notice      = 'Email is required.';
			$notice_type = 'error';
		} elseif ($is_new && empty($password)) {
			$notice      = 'Password is required for new users.';
			$notice_type = 'error';
		} elseif (! empty($password) && $password !== $password_confirm) {
			$notice      = 'Passwords do not match.';
			$notice_type = 'error';
		}

		if ('error' !== $notice_type) {
			if ($is_new) {
				$result = mc_create_user(
					array(
						'username'     => $user['username'],
						'password'     => $password,
						'email'        => $user['email'],
						'role'         => $user['role'],
						'display_name' => $user['display_name'],
					)
				);
			} else {
				$update_data = array(
					'email'        => $user['email'],
					'display_name' => $user['display_name'],
					'role'         => $user['role'],
				);
				if (! empty($password)) {
					$update_data['password'] = $password;
				}
				$result = mc_update_user($edit_id, $update_data);
			}

			if (mc_is_error($result)) {
				$notice      = $result->get_error_message();
				$notice_type = 'error';
			} else {
				$new_id = $is_new ? $result : $edit_id;
				mc_redirect(mc_admin_url('users.php?saved=1'));
				exit;
			}
		}
	}
}

$roles = mc_get_roles();

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = $is_new ? 'Add New User' : 'Edit User';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ($notice) : ?>
	<div class="notice notice-<?php echo mc_esc_attr($notice_type); ?>">
		<p><?php echo mc_esc_html($notice); ?></p>
	</div>
<?php endif; ?>

<div style="max-width:640px;">
	<form method="post" action="">
		<?php mc_nonce_field('edit_user'); ?>

		<div class="card">
			<div class="card-header"><?php echo $is_new ? 'New User' : 'Edit User'; ?></div>

			<div class="form-group">
				<label for="field-username">Username</label>
				<input type="text" id="field-username" name="username" class="form-control"
						value="<?php echo mc_esc_attr($user['username']); ?>"
						<?php echo $is_new ? '' : 'readonly'; ?>>
				<?php if (! $is_new) : ?>
					<p class="description">Username cannot be changed.</p>
				<?php endif; ?>
			</div>

			<div class="form-group">
				<label for="field-email">Email</label>
				<input type="email" id="field-email" name="email" class="form-control" value="<?php echo mc_esc_attr($user['email']); ?>">
			</div>

			<div class="form-group">
				<label for="field-display-name">Display Name</label>
				<input type="text" id="field-display-name" name="display_name" class="form-control" value="<?php echo mc_esc_attr($user['display_name']); ?>">
			</div>

			<div class="form-group">
				<label for="field-role">Role</label>
				<select id="field-role" name="role" class="form-control">
					<?php foreach ($roles as $role_slug => $role_data) : ?>
						<option value="<?php echo mc_esc_attr($role_slug); ?>"
							<?php echo $user['role'] === $role_slug ? 'selected' : ''; ?>>
							<?php echo mc_esc_html(ucfirst($role_slug)); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="form-group">
				<label for="field-password"><?php echo $is_new ? 'Password' : 'New Password'; ?></label>
				<input type="password" id="field-password" name="password" class="form-control" autocomplete="new-password">
				<?php if (! $is_new) : ?>
					<p class="description">Leave blank to keep current password.</p>
				<?php endif; ?>
			</div>

			<div class="form-group">
				<label for="field-password-confirm">Confirm Password</label>
				<input type="password" id="field-password-confirm" name="password_confirm" class="form-control" autocomplete="new-password">
			</div>

			<div class="form-actions">
				<button type="submit" class="btn btn-primary"><?php echo $is_new ? 'Create User' : 'Update User'; ?></button>
				<a href="<?php echo mc_esc_url(mc_admin_url('users.php')); ?>" class="btn btn-secondary">Cancel</a>
			</div>
		</div>
	</form>
</div>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
