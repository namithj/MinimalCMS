<?php
/**
 * MinimalCMS — Pages List (Admin)
 *
 * Lists content items with edit / delete actions.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if ( ! mc_current_user_can( 'edit_content' ) ) {
	mc_redirect( mc_admin_url() );
	exit;
}

$content_type = mc_sanitize_slug( mc_input( 'type', 'get' ) ?: 'page' );
$type_obj     = mc_get_content_type( $content_type );

if ( ! $type_obj ) {
	$content_type = 'page';
	$type_obj     = mc_get_content_type( 'page' );
}

$type_label  = $type_obj['label'] ?? ucfirst( $content_type );
$type_single = $type_obj['singular'] ?? ucfirst( $content_type );

/*
 * ── Handle delete action ───────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if ( isset( $_GET['action'], $_GET['slug'], $_GET['_nonce'] ) && 'delete' === $_GET['action'] ) {
	if ( mc_current_user_can( 'delete_content' ) && mc_verify_nonce( $_GET['_nonce'], 'delete_' . $_GET['slug'] ) ) {
		$deleted = mc_delete_content( $content_type, $_GET['slug'] );
		if ( mc_is_error( $deleted ) ) {
			$notice      = $deleted->get_error_message();
			$notice_type = 'error';
		} else {
			$notice = $type_single . ' deleted.';
		}
	} else {
		$notice      = 'Permission denied or invalid nonce.';
		$notice_type = 'error';
	}
}

/*
 * ── Query content ──────────────────────────────────────────────────────────
 */
$status_filter = mc_sanitize_slug( mc_input( 'status', 'get' ) ?? '' );
$query_args    = array(
	'type'     => $content_type,
	'order_by' => 'modified',
	'order'    => 'desc',
	'status'   => '',
);
if ( $status_filter ) {
	$query_args['status'] = $status_filter;
}

$items = mc_query_content( $query_args );

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = $type_label;
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ( $notice ) : ?>
	<div class="notice notice-<?php echo mc_esc_attr( $notice_type ); ?>" data-dismiss>
		<p><?php echo mc_esc_html( $notice ); ?></p>
	</div>
<?php endif; ?>

<div class="page-header-bar">
	<h2><?php echo mc_esc_html( $type_label ); ?> (<?php echo count( $items ); ?>)</h2>
	<?php if ( mc_current_user_can( 'create_content' ) ) : ?>
		<a href="<?php echo mc_esc_url( mc_admin_url( 'edit-page.php?type=' . urlencode( $content_type ) ) ); ?>" class="btn btn-primary">+ Add New</a>
	<?php endif; ?>
</div>

<!-- Filters -->
<div style="margin-bottom:12px;font-size:.85rem;">
	<a href="<?php echo mc_esc_url( mc_admin_url( 'pages.php?type=' . urlencode( $content_type ) ) ); ?>"
		style="<?php echo ! $status_filter ? 'font-weight:700;' : ''; ?>">All</a> |
	<a href="<?php echo mc_esc_url( mc_admin_url( 'pages.php?type=' . urlencode( $content_type ) . '&status=publish' ) ); ?>"
		style="<?php echo 'publish' === $status_filter ? 'font-weight:700;' : ''; ?>">Published</a> |
	<a href="<?php echo mc_esc_url( mc_admin_url( 'pages.php?type=' . urlencode( $content_type ) . '&status=draft' ) ); ?>"
		style="<?php echo 'draft' === $status_filter ? 'font-weight:700;' : ''; ?>">Drafts</a>
</div>

<?php if ( $items ) : ?>
	<table class="mc-table">
		<thead>
			<tr>
				<th>Title</th>
				<th>Slug</th>
				<th>Status</th>
				<th>Updated</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td>
						<strong>
							<a href="<?php echo mc_esc_url( mc_admin_url( 'edit-page.php?type=' . urlencode( $content_type ) . '&slug=' . urlencode( $item['slug'] ) ) ); ?>">
								<?php echo mc_esc_html( $item['title'] ); ?>
							</a>
						</strong>
					</td>
					<td><code><?php echo mc_esc_html( $item['slug'] ); ?></code></td>
					<td>
						<span class="badge badge-<?php echo mc_esc_attr( $item['status'] ); ?>">
							<?php echo mc_esc_html( ucfirst( $item['status'] ) ); ?>
						</span>
					</td>
					<td style="font-size:.85rem;color:#646970;">
						<?php echo mc_esc_html( date( 'M j, Y', strtotime( $item['modified'] ?? $item['created'] ?? '' ) ) ); ?>
					</td>
					<td class="row-actions" style="text-align:right;">
						<a href="<?php echo mc_esc_url( mc_admin_url( 'edit-page.php?type=' . urlencode( $content_type ) . '&slug=' . urlencode( $item['slug'] ) ) ); ?>">Edit</a>
						<a href="<?php echo mc_esc_url( mc_get_content_permalink( $content_type, $item['slug'] ) ); ?>" target="_blank">View</a>
						<?php if ( mc_current_user_can( 'delete_content' ) ) : ?>
							<a href="<?php echo mc_esc_url( mc_admin_url( 'pages.php?type=' . urlencode( $content_type ) . '&action=delete&slug=' . urlencode( $item['slug'] ) . '&_nonce=' . mc_create_nonce( 'delete_' . $item['slug'] ) ) ); ?>"
								class="delete confirm-delete">Delete</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<div class="empty-state">
		<div class="icon">&#x1F4C4;</div>
		<p>No <?php echo mc_esc_html( strtolower( $type_label ) ); ?> found.</p>
		<?php if ( mc_current_user_can( 'create_content' ) ) : ?>
			<a href="<?php echo mc_esc_url( mc_admin_url( 'edit-page.php?type=' . urlencode( $content_type ) ) ); ?>" class="btn btn-primary">Create your first <?php echo mc_esc_html( strtolower( $type_single ) ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
