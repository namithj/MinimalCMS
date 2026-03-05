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
if ( is_array( $existing_users ) && count( $existing_users ) > 0 ) {
	mc_redirect( mc_admin_url( 'login.php' ) );
	exit;
}

$step        = (int) ( $_GET['step'] ?? 1 );
$notice      = '';
$notice_type = 'error';

/*
 * ── Step 2: Process form ───────────────────────────────────────────────────
 */
if ( 2 === $step && mc_is_post_request() ) {

	$site_name = mc_sanitize_text( mc_input( 'site_name', 'post' ) ?? '' );
	$username  = mc_sanitize_slug( mc_input( 'username', 'post' ) ?? '' );
	$email     = mc_sanitize_email( mc_input( 'email', 'post' ) ?? '' );
	$password  = mc_input( 'password', 'post' );
	$password2 = mc_input( 'password_confirm', 'post' );

	if ( empty( $site_name ) ) {
		$notice = 'Site name is required.';
	} elseif ( empty( $username ) ) {
		$notice = 'Username is required.';
	} elseif ( empty( $email ) ) {
		$notice = 'Email is required.';
	} elseif ( empty( $password ) ) {
		$notice = 'Password is required.';
	} elseif ( $password !== $password2 ) {
		$notice = 'Passwords do not match.';
	}

	if ( ! $notice ) {

		/*
		 * 1. Seed config.json from the sample file if it does not exist yet.
		 */
		$config_path = MC_ABSPATH . 'config.json';
		$sample_path = MC_ABSPATH . 'config.sample.json';

		if ( ! is_file( $config_path ) && is_file( $sample_path ) ) {
			copy( $sample_path, $config_path );
		}

		$config = $GLOBALS['mc_config'];

		/*
		 * 2. Generate cryptographic keys if they are still placeholder/empty.
		 */
		if ( empty( $config['encryption_key'] ) || 'CHANGE_ME_RANDOM_HEX_64' === $config['encryption_key'] ) {
			$config['encryption_key'] = bin2hex( random_bytes( 32 ) );
		}

		if ( empty( $config['secret_key'] ) || 'CHANGE_ME_RANDOM_STRING' === $config['secret_key'] ) {
			$config['secret_key'] = bin2hex( random_bytes( 32 ) );
		}

		$config['site_name'] = $site_name;

		/*
		 * 3. Save config first (encryption_key needed for user file).
		 */
		$saved = mc_save_config( $config );

		if ( mc_is_error( $saved ) ) {
			$notice = 'Could not save config: ' . $saved->get_error_message();
		} else {
			// Reload globals so mc_derive_encryption_key() picks up new key.
			$GLOBALS['mc_config'] = $config;

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

			if ( mc_is_error( $user ) ) {
				$notice = 'Could not create user: ' . $user->get_error_message();
			} else {
				// Log in immediately.
				mc_start_session();
				mc_set_auth_session( $username );

				$step = 3; // Success.
			}
		}
	}

	if ( $notice ) {
		$step = 1; // Back to form on error.
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Setup &mdash; MinimalCMS</title>
	<style>
		*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1d2327}
		.setup-wrap{width:100%;max-width:520px;padding:20px}
		.setup-logo{text-align:center;margin-bottom:24px;font-size:1.5rem;font-weight:700;letter-spacing:-.5px}
		.setup-box{background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
		.setup-box h1{font-size:1.2rem;margin-bottom:8px}
		.setup-box p.lead{font-size:.95rem;color:#646970;margin-bottom:20px}
		.form-group{margin-bottom:16px}
		.form-group label{display:block;font-size:.875rem;font-weight:600;margin-bottom:6px}
		.form-group input{width:100%;padding:8px 12px;font-size:.95rem;border:1px solid #8c8f94;border-radius:4px;outline:none;transition:border-color .15s}
		.form-group input:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
		.btn{display:inline-block;padding:10px 20px;font-size:.95rem;font-weight:600;color:#fff;background:#2271b1;border:none;border-radius:4px;cursor:pointer;transition:background .15s;text-decoration:none}
		.btn:hover{background:#135e96}
		.notice{padding:10px 14px;border-radius:4px;font-size:.875rem;margin-bottom:16px}
		.notice-error{background:#fcf0f1;border-left:4px solid #d63638;color:#d63638}
		.notice-success{background:#edfaef;border-left:4px solid #00a32a;color:#00a32a}
		.desc{font-size:.8rem;color:#646970;margin-top:4px}
		.success-icon{text-align:center;font-size:3rem;margin-bottom:16px}
	</style>
</head>
<body>
	<div class="setup-wrap">
		<div class="setup-logo">MinimalCMS</div>
		<div class="setup-box">

		<?php if ( 3 === $step ) : // ── Success ─────────────────────────── ?>

			<div class="success-icon">&#x2705;</div>
			<h1 style="text-align:center;">All Set!</h1>
			<p class="lead" style="text-align:center;">Your site is ready. You've been logged in as the admin.</p>
			<div style="text-align:center;margin-top:20px;">
				<a href="<?php echo mc_esc_url( mc_admin_url() ); ?>" class="btn">Go to Dashboard</a>
			</div>

		<?php else : // ── Setup Form ────────────────────────────────────── ?>

			<h1>Welcome to MinimalCMS</h1>
			<p class="lead">Let's set up your site. This will only take a moment.</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-error"><?php echo mc_esc_html( $notice ); ?></div>
			<?php endif; ?>

			<form method="post" action="?page=setup.php&step=2">
				<div class="form-group">
					<label for="site_name">Site Name</label>
					<input type="text" id="site_name" name="site_name"
							value="<?php echo mc_esc_attr( $site_name ?? ( $GLOBALS['mc_config']['site_name'] ?? 'My Site' ) ); ?>" autofocus>
				</div>

				<hr style="border:none;border-top:1px solid #dcdcde;margin:20px 0;">
				<h2 style="font-size:1rem;margin-bottom:12px;">Admin Account</h2>

				<div class="form-group">
					<label for="username">Username</label>
					<input type="text" id="username" name="username"
							value="<?php echo mc_esc_attr( $username ?? '' ); ?>" autocomplete="username">
				</div>

				<div class="form-group">
					<label for="email">Email</label>
					<input type="email" id="email" name="email"
							value="<?php echo mc_esc_attr( $email ?? '' ); ?>">
				</div>

				<div class="form-group">
					<label for="password">Password</label>
					<input type="password" id="password" name="password" autocomplete="new-password">
				</div>

				<div class="form-group">
					<label for="password_confirm">Confirm Password</label>
					<input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
				</div>

				<button type="submit" class="btn" style="width:100%;margin-top:8px;">Install MinimalCMS</button>
			</form>

		<?php endif; ?>

		</div>
	</div>
</body>
</html>
