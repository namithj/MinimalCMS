<?php
/**
 * MinimalCMS — Users List (Admin)
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if ( ! mc_current_user_can( 'manage_users' ) ) {
	mc_redirect( mc_admin_url() );
	exit;
}

/*
 * ── Handle delete action ───────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if ( isset( $_GET['action'], $_GET['id'], $_GET['_nonce'] ) && 'delete' === $_GET['action'] ) {
	$del_id = $_GET['id'];

	if ( $del_id === mc_get_current_user_id() ) {
		$notice      = 'You cannot delete your own account.';
		$notice_type = 'error';
	} elseif ( ! mc_verify_nonce( $_GET['_nonce'], 'delete_user_' . $del_id ) ) {
		$notice      = 'Invalid security token.';
		$notice_type = 'error';
	} else {
		$result = mc_delete_user( $del_id );
		if ( mc_is_error( $result ) ) {
			$notice      = $result->get_error_message();
			$notice_type = 'error';
		} else {
			$notice = 'User deleted.';
		}
	}
}

if ( isset( $_GET['saved'] ) ) {
	$notice      = 'User saved successfully.';
	$notice_type = 'success';
}

$users = mc_get_users();
$users = is_array( $users ) ? $users : array();

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = 'Users';
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ( $notice ) : ?>
	<div class="notice notice-<?php echo mc_esc_attr( $notice_type ); ?>" data-dismiss>
		<p><?php echo mc_esc_html( $notice ); ?></p>
	</div>
<?php endif; ?>

<div class="page-header-bar">
	<h2>Users (<?php echo count( $users ); ?>)</h2>
	<a href="<?php echo mc_esc_url( mc_admin_url( 'user-edit.php' ) ); ?>" class="btn btn-primary">+ Add New</a>
</div>

<?php if ( $users ) : ?>
	<table class="mc-table">
		<thead>
			<tr>
				<th>Username</th>
				<th>Display Name</th>
				<th>Email</th>
				<th>Role</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $users as $user ) : ?>
				<tr>
					<td>
						<strong>
						<a href="<?php echo mc_esc_url( mc_admin_url( 'user-edit.php?id=' . urlencode( $user['username'] ) ) ); ?>">
								<?php echo mc_esc_html( $user['username'] ); ?>
							</a>
						</strong>
					</td>
					<td><?php echo mc_esc_html( $user['display_name'] ?? '' ); ?></td>
					<td><?php echo mc_esc_html( $user['email'] ?? '' ); ?></td>
					<td>
						<span class="badge badge-active"><?php echo mc_esc_html( ucfirst( $user['role'] ?? 'contributor' ) ); ?></span>
					</td>
					<td class="row-actions" style="text-align:right;">
						<a href="<?php echo mc_esc_url( mc_admin_url( 'user-edit.php?id=' . urlencode( $user['username'] ) ) ); ?>">Edit</a>
						<?php if ( $user['username'] !== mc_get_current_user_id() ) : ?>
							<a href="<?php echo mc_esc_url( mc_admin_url( 'users.php?action=delete&id=' . urlencode( $user['username'] ) . '&_nonce=' . mc_create_nonce( 'delete_user_' . $user['username'] ) ) ); ?>"
								class="delete confirm-delete">Delete</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="empty-state">
		<div class="icon">&#x1F465;</div>
		<p>No users found.</p>
	</div>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
