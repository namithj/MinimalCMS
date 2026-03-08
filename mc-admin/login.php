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
	// CSRF guard — verify nonce before processing credentials.
	if (! mc_verify_nonce(mc_input('_mc_nonce', 'post'), 'login')) {
		$error = 'Invalid security token. Please try again.';
	} else {
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
	} // end nonce check
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
	<link rel="stylesheet" href="<?php echo mc_esc_url(mc_admin_url('assets/css/auth.css')); ?>">
	<link rel="icon" href="<?php echo mc_esc_url(mc_admin_url('assets/favicon.svg')); ?>" type="image/svg+xml">
</head>
<body>
	<div class="auth-wrap">
		<div class="auth-logo">Minimal<span>CMS</span></div>
		<div class="auth-box">
			<h1 class="centered">Log In</h1>

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
				<?php endif; ?>			<?php mc_nonce_field('login'); ?>				<button type="submit" class="btn btn-full-width">Log In</button>
			</form>
		</div>
		<div class="back-link">
			<a href="<?php echo mc_esc_url(mc_site_url()); ?>">&larr; Back to <?php echo mc_esc_html(MC_SITE_NAME); ?></a>
		</div>
	</div>
</body>
</html>
