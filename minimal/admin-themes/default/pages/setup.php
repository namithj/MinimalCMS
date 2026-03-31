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

require_once defined('MC_INC') ? MC_INC . 'admin/bootstrap.php' : dirname(__DIR__, 3) . '/mc-includes/admin/bootstrap.php';

mc_start_session();

$step = (int) ($_GET['step'] ?? 1);

$has_pending_backup = !empty($_SESSION['mc_setup_backup']) && is_array($_SESSION['mc_setup_backup']);

/*
 * ── Guard: redirect away if already set up ─────────────────────────────────
 */
if (!mc_app()->setup()->needs_setup() && !(3 === $step && $has_pending_backup)) {
	mc_redirect(mc_admin_url('login.php'));
	exit;
}

$notice      = '';
$notice_type = 'error';
$config      = mc_app()->config()->all();

/*
 * ── Step 3: Handle backup generation and completion gate ───────────────────
 */
if (3 === $step && mc_is_post_request()) {
	$action = mc_sanitize_slug(mc_input('setup_action', 'post') ?? '');

	if ('download_backup' === $action) {
		if (!mc_verify_nonce((string) mc_input('_mc_nonce', 'post'), 'setup_backup_download')) {
			$notice = 'Invalid backup download request.';
		} elseif (!$has_pending_backup) {
			$notice = 'No pending setup backup data was found.';
		} else {
			$passphrase = (string) (mc_input('backup_passphrase', 'post') ?? '');
			$confirm    = (string) (mc_input('backup_passphrase_confirm', 'post') ?? '');

			if ('' === trim($passphrase)) {
				$notice = 'Backup passphrase is required.';
			} elseif (strlen($passphrase) < 12) {
				$notice = 'Backup passphrase must be at least 12 characters.';
			} elseif ($passphrase !== $confirm) {
				$notice = 'Backup passphrases do not match.';
			} else {
				$master_key_hex = (string) ($_SESSION['mc_setup_backup']['master_key_hex'] ?? '');
				$bundle         = mc_app()->setup()->generate_backup_bundle($master_key_hex, $passphrase);

				if (mc_is_error($bundle)) {
					$notice = $bundle->get_error_message();
				} else {
					$_SESSION['mc_setup_backup']['downloaded'] = true;
					$filename = basename((string) $bundle['filename']);

					header('Content-Type: application/json; charset=UTF-8');
					header('Content-Disposition: attachment; filename="' . $filename . '"');
					header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
					header('Pragma: no-cache');
					header('Expires: 0');

					echo $bundle['content'];
					exit;
				}
			}
		}
	} elseif ('finish_setup' === $action) {
		if (!mc_verify_nonce((string) mc_input('_mc_nonce', 'post'), 'setup_finish')) {
			$notice = 'Invalid setup completion request.';
		} elseif (!$has_pending_backup) {
			$notice = 'No pending setup backup data was found.';
		} else {
			$downloaded = !empty($_SESSION['mc_setup_backup']['downloaded']);
			$confirmed  = '1' === (string) (mc_input('backup_confirmed', 'post') ?? '');

			if (!$downloaded) {
				$notice = 'You must generate and download a backup before continuing.';
			} elseif (!$confirmed) {
				$notice = 'Please confirm that you saved the backup bundle.';
			} else {
				unset($_SESSION['mc_setup_backup']);
				mc_redirect(mc_admin_url());
				exit;
			}
		}
	}
}

/*
 * ── Step 2: Process form submission ────────────────────────────────────────
 */
if (2 === $step && mc_is_post_request()) {
	if (!mc_verify_nonce((string) mc_input('_mc_nonce', 'post'), 'setup_install')) {
		$notice = 'Invalid setup request.';
	}

	$site_name = mc_sanitize_text(mc_input('site_name', 'post') ?? '');
	$username  = mc_sanitize_slug(mc_input('username', 'post') ?? '');
	$email     = mc_sanitize_email(mc_input('email', 'post') ?? '');
	$password  = mc_input('password', 'post');
	$password2 = mc_input('password_confirm', 'post');

	if (!$notice && empty($site_name)) {
		$notice = 'Site name is required.';
	} elseif (!$notice && empty($username)) {
		$notice = 'Username is required.';
	} elseif (!$notice && empty($email)) {
		$notice = 'Email is required.';
	} elseif (!$notice && empty($password)) {
		$notice = 'Password is required.';
	} elseif (!$notice && $password !== $password2) {
		$notice = 'Passwords do not match.';
	}

	if (! $notice) {
		/*
		 * 1. Seed config.php from the sample file if it does not exist yet.
		 */
		$config_path = MC_ABSPATH . 'config.php';
		$sample_path = MC_ABSPATH . 'config.sample.php';

		if (! is_file($config_path) && is_file($sample_path)) {
			copy($sample_path, $config_path);
		}

		$config = mc_app()->config()->all();

		/*
		 * 2. Provision keystore (master key + encrypted app keys).
		 */
		$data_dir   = MC_DATA_DIR;
		try {
			$master_key = MC_Keystore::resolve_master_key($data_dir, MC_ABSPATH);
		} catch (\RuntimeException $e) {
			$master_key = '';
		}

		if ('' === $master_key) {
			$master_key_hex = MC_Keystore::generate_master_key($data_dir);
			if ('' !== $master_key_hex) {
				$master_key = hex2bin($master_key_hex);
			}
		}

		if ('' === $master_key) {
			$notice = 'Failed to generate master key. Check file permissions on content/data/.';
		} else {
			$app_keys = array(
				'secret_key'     => bin2hex(random_bytes(32)),
				'encryption_key' => bin2hex(random_bytes(32)),
			);

			if (!MC_Keystore::save_keys($data_dir, $master_key, $app_keys)) {
				$notice = 'Failed to write keystore. Check file permissions on content/data/.';
			}
		}

		if (! $notice) {
			$config['site_name']  = $site_name;
			$config['front_page'] = 'home';

			/*
			 * 2b. Auto-detect site_url when it is blank (fresh install).
			 */
			if (empty($config['site_url'])) {
				$scheme = (!empty($_SERVER['HTTPS']) && 'off' !== $_SERVER['HTTPS']) ? 'https' : 'http';
				$host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
				$host   = strtolower(trim(preg_replace('/:\\d+$/', '', $host)));
				if ('' === $host || !preg_match('/^[a-z0-9.-]+$/', $host)) {
					$host = 'localhost';
				}
				$base   = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/setup.php'));
				$base   = ('/' === $base || '\\' === $base) ? '' : $base;
				$config['site_url'] = $scheme . '://' . $host . $base;
			}

			/*
			 * 3. Save config (keys are in keystore, not config).
			 */
			$saved = mc_save_config($config);

			if (! $saved) {
				$notice = 'Could not save configuration. Check file permissions.';
			} else {
				/*
				 * 4. Update the user manager's encryption key from keystore.
				 */
				mc_app()->users()->set_encryption_key($app_keys['encryption_key']);

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

					$_SESSION['mc_setup_backup'] = array(
						'master_key_hex' => bin2hex($master_key),
						'downloaded'     => false,
						'created_at'     => time(),
					);

					$step = 3;
				}
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
	<link rel="stylesheet" href="<?php echo mc_esc_url(mc_admin_theme_url('assets/css/auth.css')); ?>">
	<link rel="icon" href="<?php echo mc_esc_url(mc_admin_theme_url('assets/favicon.svg')); ?>" type="image/svg+xml">
</head>
<body>
	<div class="auth-wrap auth-wrap--wide">
		<div class="auth-logo">Minimal<span>CMS</span></div>
		<div class="auth-box">

		<?php if (3 === $step) : // ── Success ──────────────────────────── ?>
			<div style="text-align:center;font-size:3rem;margin-bottom:16px;">&#x2705;</div>
			<h1 style="text-align:center;">One Last Step: Backup Your Recovery Bundle</h1>
			<p class="lead" style="text-align:center;">Your admin account is ready. Before entering the dashboard, download and securely store your encrypted key backup.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-error"><?php echo mc_esc_html($notice); ?></div>
			<?php endif; ?>

			<div class="notice notice-error" style="margin-top:16px;">
				If this backup is lost and the server key files are lost, encrypted users and submissions cannot be recovered.
			</div>

			<form method="post" action="?step=3" style="margin-top:20px;">
				<div class="form-group">
					<label for="backup_passphrase">Backup Passphrase</label>
					<input type="password" id="backup_passphrase" name="backup_passphrase" autocomplete="new-password" required>
				</div>
				<div class="form-group">
					<label for="backup_passphrase_confirm">Confirm Backup Passphrase</label>
					<input type="password" id="backup_passphrase_confirm" name="backup_passphrase_confirm" autocomplete="new-password" required>
				</div>

				<?php mc_nonce_field('setup_backup_download'); ?>
				<input type="hidden" name="setup_action" value="download_backup">
				<button type="submit" class="btn btn-full-width">Generate and Download Recovery Backup</button>
			</form>

			<?php if (!empty($_SESSION['mc_setup_backup']['downloaded'])) : ?>
				<div class="notice notice-success" style="margin-top:16px;">Backup download completed for this setup session.</div>
			<?php endif; ?>

			<form method="post" action="?step=3" style="margin-top:16px;">
				<div class="form-group">
					<label>
						<input type="checkbox" name="backup_confirmed" value="1" required>
						I have saved the recovery backup in a secure location.
					</label>
				</div>

				<?php mc_nonce_field('setup_finish'); ?>
				<input type="hidden" name="setup_action" value="finish_setup">
				<button type="submit" class="btn btn-full-width" <?php echo empty($_SESSION['mc_setup_backup']['downloaded']) ? 'disabled' : ''; ?>>Go to Dashboard</button>
			</form>

			<div style="text-align:center;margin-top:10px;font-size:0.9rem;color:#6b7280;">
				Dashboard access is blocked until backup download and confirmation are complete.
			</div>

		<?php else : // ── Setup Form ──────────────────────────────────────── ?>
			<h1>Welcome to MinimalCMS</h1>
			<p class="lead">Let's set up your site. This will only take a moment.</p>

			<?php if ($notice) : ?>
				<div class="notice notice-error"><?php echo mc_esc_html($notice); ?></div>
			<?php endif; ?>

			<form method="post" action="?step=2">
				<?php mc_nonce_field('setup_install'); ?>
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
