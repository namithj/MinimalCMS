<?php

/**
 * MinimalCMS — Login / Logout
 *
 * Handles authentication form and session lifecycle.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

/*
 * ── Logout action ──────────────────────────────────────────────────────────
 */
if (isset($_GET['action']) && 'logout' === $_GET['action']) {
	mc_destroy_session();
	mc_redirect(mc_admin_url('login.php') . '?logged_out=1');
	exit;
}

/*
 * ── Redirect if already authenticated ──────────────────────────────────────
 */
if (mc_is_logged_in()) {
	mc_redirect(mc_admin_url());
	exit;
}

$error = '';

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
if (mc_is_post_request()) {
	$username = mc_sanitize_text(mc_input('username', 'post') ?? '');
	$password = mc_input('password', 'post');

	if (empty($username) || empty($password)) {
		$error = 'Please enter both username and password.';
	} else {
		$user = mc_authenticate($username, $password);

		if (mc_is_error($user)) {
			$error = $user->get_error_message();
		} else {
			mc_set_auth_session($user['username']);
			$redirect = mc_input('redirect_to', 'post');
			$redirect = $redirect ? $redirect : mc_admin_url();
			mc_redirect($redirect);
			exit;
		}
	}
}

$logged_out = isset($_GET['logged_out']);
$redirect   = mc_esc_attr(mc_input('redirect_to', 'get') ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Log In &mdash; <?php echo mc_esc_html(MC_SITE_NAME); ?></title>
	<style>
		*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
		body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1d2327}
		.login-wrap{width:100%;max-width:360px;padding:20px}
		.login-logo{text-align:center;margin-bottom:24px;font-size:1.5rem;font-weight:700;letter-spacing:-.5px}
		.login-box{background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
		.login-box h1{font-size:1.1rem;margin-bottom:20px;text-align:center}
		.form-group{margin-bottom:16px}
		.form-group label{display:block;font-size:.875rem;font-weight:600;margin-bottom:6px}
		.form-group input{width:100%;padding:8px 12px;font-size:.95rem;border:1px solid #8c8f94;border-radius:4px;outline:none;transition:border-color .15s}
		.form-group input:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
		.btn{display:block;width:100%;padding:10px;font-size:.95rem;font-weight:600;color:#fff;background:#2271b1;border:none;border-radius:4px;cursor:pointer;transition:background .15s}
		.btn:hover{background:#135e96}
		.notice{padding:10px 14px;border-radius:4px;font-size:.875rem;margin-bottom:16px}
		.notice-error{background:#fcf0f1;border-left:4px solid #d63638;color:#d63638}
		.notice-success{background:#edfaef;border-left:4px solid #00a32a;color:#00a32a}
		.back-link{text-align:center;margin-top:16px;font-size:.85rem}
		.back-link a{color:#2271b1;text-decoration:none}
		.back-link a:hover{text-decoration:underline}
	</style>
</head>
<body>
	<div class="login-wrap">
		<div class="login-logo">MinimalCMS</div>
		<div class="login-box">
			<h1>Log In</h1>

			<?php if ($logged_out) : ?>
				<div class="notice notice-success">You have been logged out.</div>
			<?php endif; ?>

			<?php if ($error) : ?>
				<div class="notice notice-error"><?php echo mc_esc_html($error); ?></div>
			<?php endif; ?>

			<form method="post" action="">
				<div class="form-group">
					<label for="username">Username</label>
					<input type="text" id="username" name="username" value="<?php echo mc_esc_attr($username ?? ''); ?>" autocomplete="username" autofocus>
				</div>
				<div class="form-group">
					<label for="password">Password</label>
					<input type="password" id="password" name="password" autocomplete="current-password">
				</div>
				<?php if ($redirect) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo $redirect; ?>">
				<?php endif; ?>
				<button type="submit" class="btn">Log In</button>
			</form>
		</div>
		<div class="back-link">
			<a href="<?php echo mc_esc_url(mc_site_url()); ?>">&larr; Back to <?php echo mc_esc_html(MC_SITE_NAME); ?></a>
		</div>
	</div>
</body>
</html>
