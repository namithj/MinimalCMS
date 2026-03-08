<?php

/**
 * MinimalCMS — Setup Wizard
 *
 * First-run setup: generates encryption keys, creates the initial admin user,
 * and writes config. Accessible only when the site has no users yet (fresh install).
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

/*
 * ── Guard: redirect away if already set up ─────────────────────────────────
 */
$existing_users = mc_get_users();
if (is_array($existing_users) && count($existing_users) > 0) {
	mc_redirect(mc_admin_url('login.php'));
	exit;
}

$notice      = '';
$notice_type = 'error';

/*
 * ── Process form submission ────────────────────────────────────────────────
 */
if (mc_is_post_request()) {
	$site_name = mc_sanitize_text(mc_input('site_name', 'post') ?? '');
	$username  = mc_sanitize_slug(mc_input('username', 'post') ?? '');
	$email     = mc_sanitize_email(mc_input('email', 'post') ?? '');
	$password  = mc_input('password', 'post');
	$password2 = mc_input('password_confirm', 'post');

	if (empty($site_name)) {
		$notice = 'Site name is required.';
	} elseif (empty($username)) {
		$notice = 'Username is required.';
	} elseif (empty($email)) {
		$notice = 'Email is required.';
	} elseif (empty($password)) {
		$notice = 'Password is required.';
	} elseif ($password !== $password2) {
		$notice = 'Passwords do not match.';
	}

	if (! $notice) {
		/*
		 * 1. Seed config.json from the sample file if it does not exist yet.
		 */
		$config_path = MC_ABSPATH . 'config.json';
		$sample_path = MC_ABSPATH . 'config.sample.json';

		if (! is_file($config_path) && is_file($sample_path)) {
			copy($sample_path, $config_path);
		}

		$config = mc_app()->config()->all();

		/*
		 * 2. Generate cryptographic keys if they are still placeholder/empty.
		 */
		if (empty($config['encryption_key']) || 'CHANGE_ME_RANDOM_HEX_64' === $config['encryption_key']) {
			$config['encryption_key'] = bin2hex(random_bytes(32));
		}

		if (empty($config['secret_key']) || 'CHANGE_ME_RANDOM_STRING' === $config['secret_key']) {
			$config['secret_key'] = bin2hex(random_bytes(32));
		}

		$config['site_name'] = $site_name;

		/*
		 * 3. Save config first (encryption_key needed for user file).
		 */
		$saved = mc_save_config($config);

		if (! $saved) {
			$notice = 'Could not save configuration. Check file permissions.';
		} else {
			/*
			 * 4. Create admin user.
			 */
			$user = mc_create_user(
				array(
					'username'     => $username,
					'password'     => $password,
					'email'        => $email,
					'role'         => 'administrator',
					'display_name' => $username,
				)
			);

			if (mc_is_error($user)) {
				$notice = 'Could not create user: ' . $user->get_error_message();
			} else {
				// Log in and redirect immediately to dashboard.
				mc_start_session();
				mc_set_auth_session($username);
				mc_redirect(mc_admin_url());
				exit;
			}
		}
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Setup &mdash; MinimalCMS</title>
	<link rel="stylesheet" href="<?php echo mc_esc_url(mc_admin_url('assets/css/auth.css')); ?>">
	<link rel="icon" href="<?php echo mc_esc_url(mc_admin_url('assets/favicon.svg')); ?>" type="image/svg+xml">
</head>
<body>
	<div class="auth-wrap auth-wrap--wide">
		<div class="auth-logo">Minimal<span>CMS</span></div>
		<div class="auth-box">

		<?php // ── Setup Form ─────────────────────────────────────────────── ?>
			<h1>Welcome to MinimalCMS</h1>
			<p class="lead">Let's set up your site. This will only take a moment.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-error"><?php echo mc_esc_html($notice); ?></div>
			<?php endif; ?>

			<form method="post" action="">
				<div class="form-group">
					<label for="site_name">Site Name</label>
					<input type="text" id="site_name" name="site_name"
							value="<?php echo mc_esc_attr($site_name ?? mc_app()->config()->get('site_name', 'My Site')); ?>" autofocus>
				</div>

				<hr class="hr-styled">
				<h2 class="section-heading">Admin Account</h2>

				<div class="form-group">
					<label for="username">Username</label>
					<input type="text" id="username" name="username"
							value="<?php echo mc_esc_attr($username ?? ''); ?>" autocomplete="username">
				</div>

				<div class="form-group">
					<label for="email">Email</label>
					<input type="email" id="email" name="email"
							value="<?php echo mc_esc_attr($email ?? ''); ?>">
				</div>

				<div class="form-group">
					<label for="password">Password</label>
					<input type="password" id="password" name="password" autocomplete="new-password">
				</div>

				<div class="form-group">
					<label for="password_confirm">Confirm Password</label>
					<input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
				</div>

				<button type="submit" class="btn btn-full-width">Install MinimalCMS</button>
			</form>

		</div>
	</div>
</body>
</html>
