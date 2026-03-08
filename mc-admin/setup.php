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
if (!mc_app()->setup()->needs_setup()) {
	mc_redirect(mc_admin_url('login.php'));
	exit;
}

$step        = (int) ($_GET['step'] ?? 1);
$notice      = '';
$notice_type = 'error';

/*
 * ── Step 2: Process form submission ────────────────────────────────────────
 */
if (2 === $step && mc_is_post_request()) {
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

		$config['site_name']  = $site_name;
		$config['front_page'] = 'home';

		/*
		 * 2b. Auto-detect site_url when it is blank (fresh install).
		 */
		if (empty($config['site_url'])) {
			$scheme = (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
			$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
			$base   = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/mc-admin/setup.php'));
			$base   = ('/' === $base || '\\' === $base) ? '' : $base;
			$config['site_url'] = $scheme . '://' . $host . $base;
		}

		/*
		 * 3. Save config first (encryption_key needed for user file).
		 */
		$saved = mc_save_config($config);

		if (! $saved) {
			$notice = 'Could not save configuration. Check file permissions.';
		} else {
			/*
			 * 4. Update the user manager's encryption key to the newly
			 *    generated one so the user file is encrypted correctly.
			 */
			mc_app()->users()->set_encryption_key($config['encryption_key']);

			/*
			 * 5. Create admin user.
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
				/*
				 * 6. Seed general settings from the setup form values.
				 */
				mc_update_settings('core.general', array(
					'site_name'  => $site_name,
					'site_url'   => $config['site_url'],
					'front_page' => 'home',
				));

				// Log in and advance to success step.
				mc_start_session();
				mc_set_auth_session($username);

				$step = 3;
			}
		}
	}

	if ($notice) {
		$step = 1;
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

		<?php if (3 === $step) : // ── Success ──────────────────────────── ?>
			<div style="text-align:center;font-size:3rem;margin-bottom:16px;">&#x2705;</div>
			<h1 style="text-align:center;">All Set!</h1>
			<p class="lead" style="text-align:center;">Your site is ready. You've been logged in as the admin.</p>
			<div style="text-align:center;margin-top:20px;">
				<a href="<?php echo mc_esc_url(rtrim($config['site_url'] ?? mc_site_url(), '/') . '/mc-admin/'); ?>" class="btn btn-full-width">Go to Dashboard</a>
			</div>

		<?php else : // ── Setup Form ──────────────────────────────────────── ?>
			<h1>Welcome to MinimalCMS</h1>
			<p class="lead">Let's set up your site. This will only take a moment.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-error"><?php echo mc_esc_html($notice); ?></div>
			<?php endif; ?>

			<form method="post" action="?step=2">
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

		<?php endif; ?>

		</div>
	</div>
</body>
</html>
