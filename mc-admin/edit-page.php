<?php
/**
 * MinimalCMS — Edit / Create Page (Admin)
 *
 * Markdown editor with live-preview and metadata sidebar.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

$content_type = mc_sanitize_slug( mc_input( 'type', 'get' ) ?: 'page' );
$edit_slug    = mc_sanitize_slug( mc_input( 'slug', 'get' ) ?? '' );
$is_new       = empty( $edit_slug );

if ( $is_new && ! mc_current_user_can( 'create_content' ) ) {
	mc_redirect( mc_admin_url() );
	exit;
}

if ( ! $is_new && ! mc_current_user_can( 'edit_content' ) ) {
	mc_redirect( mc_admin_url() );
	exit;
}

$type_obj    = mc_get_content_type( $content_type );
$type_single = $type_obj['singular'] ?? ucfirst( $content_type );

/*
 * ── Load existing content ──────────────────────────────────────────────────
 */
$item = array(
	'title'    => '',
	'slug'     => '',
	'status'   => 'draft',
	'excerpt'  => '',
	'parent'   => '',
	'template' => '',
	'order'    => 0,
	'meta'     => array(),
);
$body = '';

if ( ! $is_new ) {
	$existing = mc_get_content( $content_type, $edit_slug );
	if ( $existing && ! mc_is_error( $existing ) ) {
		$item = array_merge( $item, $existing );
		$body = $existing['body_raw'] ?? '';
	} else {
		$is_new = true;
	}
}

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';

if ( mc_is_post_request() ) {

	if ( ! mc_verify_nonce( mc_input( '_mc_nonce', 'post' ), 'edit_content' ) ) {
		$notice      = 'Invalid security token. Please try again.';
		$notice_type = 'error';
	} else {
		$item['title']    = mc_sanitize_text( mc_input( 'title', 'post' ) ?? '' );
		$item['slug']     = mc_sanitize_slug( mc_input( 'slug', 'post' ) ?? '' );
		$item['status']   = mc_sanitize_slug( mc_input( 'status', 'post' ) ?? '' ) ?: 'draft';
		$item['excerpt']  = mc_sanitize_text( mc_input( 'excerpt', 'post' ) ?? '' );
		$item['parent']   = mc_sanitize_slug( mc_input( 'parent', 'post' ) ?? '' );
		$item['template'] = mc_sanitize_text( mc_input( 'template', 'post' ) ?? '' );
		$item['order']    = (int) mc_input( 'order', 'post' );
		$body             = mc_input( 'body', 'post' ); // Raw markdown.

		if ( empty( $item['title'] ) ) {
			$notice      = 'Title is required.';
			$notice_type = 'error';
		} elseif ( empty( $item['slug'] ) ) {
			$item['slug'] = mc_slugify( $item['title'] );
		}

		if ( 'error' !== $notice_type ) {
			$save_meta = $item;

			// If creating new, set author.
			if ( $is_new ) {
				$save_meta['author'] = mc_get_current_user_id();
			}

			$result = mc_save_content( $content_type, $item['slug'], $save_meta, $body ?? '' );

			// Handle slug rename: if editing and slug changed, delete old directory.
			if ( true === $result && ! $is_new && $edit_slug !== $item['slug'] ) {
				mc_delete_content( $content_type, $edit_slug );
			}

			if ( mc_is_error( $result ) ) {
				$notice      = $result->get_error_message();
				$notice_type = 'error';
			} else {
				$notice = $type_single . ' saved.';
				$is_new = false;

				// Redirect to edit URL with new slug.
				mc_redirect( mc_admin_url( 'edit-page.php?type=' . urlencode( $content_type ) . '&slug=' . urlencode( $item['slug'] ) . '&saved=1' ) );
				exit;
			}
		}
	}
}

if ( isset( $_GET['saved'] ) ) {
	$notice      = $type_single . ' saved successfully.';
	$notice_type = 'success';
}

/*
 * ── Gather additional data for the form ────────────────────────────────────
 */
$all_pages = array();
if ( $type_obj['hierarchical'] ?? false ) {
	$all_pages = mc_query_content(
		array(
			'type'     => $content_type,
			'order_by' => 'title',
			'order'    => 'asc',
			'status'   => '',
		)
	);
}

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = $is_new ? 'New ' . $type_single : 'Edit ' . $type_single;
define( 'MC_LOAD_EDITOR', true );
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ( $notice ) : ?>
	<div class="notice notice-<?php echo mc_esc_attr( $notice_type ); ?>" data-dismiss>
		<p><?php echo mc_esc_html( $notice ); ?></p>
	</div>
<?php endif; ?>

<form method="post" action="" data-slug="<?php echo mc_esc_attr( $edit_slug ); ?>">
	<?php mc_nonce_field( 'edit_content' ); ?>

	<div class="editor-layout">
		<!-- Main editor column -->
		<div class="editor-main">
			<div class="form-group">
				<label for="field-title">Title</label>
				<input type="text" id="field-title" name="title" class="form-control" value="<?php echo mc_esc_attr( $item['title'] ); ?>" placeholder="Enter title…" autofocus>
			</div>

			<div class="form-group">
				<label>Content (Markdown)</label>
				<textarea id="editor-markdown" name="body" placeholder="Write your content in Markdown…"><?php echo mc_esc_textarea( $body ); ?></textarea>
			</div>

			<div class="form-group">
				<label for="field-excerpt">Excerpt</label>
				<textarea id="field-excerpt" name="excerpt" class="form-control" rows="3" placeholder="Short summary…"><?php echo mc_esc_textarea( $item['excerpt'] ); ?></textarea>
			</div>
		</div>

		<!-- Sidebar column -->
		<div class="editor-sidebar">
			<div class="card">
				<div class="card-header">Publish</div>
				<div class="form-group">
					<label for="field-status">Status</label>
					<select id="field-status" name="status" class="form-control">
						<option value="draft" <?php echo 'draft' === $item['status'] ? 'selected' : ''; ?>>Draft</option>
						<option value="publish" <?php echo 'publish' === $item['status'] ? 'selected' : ''; ?>>Published</option>
					</select>
				</div>
				<div class="form-actions">
					<button type="submit" class="btn btn-primary">Save <?php echo mc_esc_html( $type_single ); ?></button>
					<?php if ( ! $is_new ) : ?>
						<a href="<?php echo mc_esc_url( mc_get_content_permalink( $content_type, $item['slug'] ) ); ?>" class="btn btn-secondary" target="_blank">View</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="card">
				<div class="card-header">Attributes</div>
				<div class="form-group">
					<label for="field-slug">Slug</label>
					<input type="text" id="field-slug" name="slug" class="form-control" value="<?php echo mc_esc_attr( $item['slug'] ); ?>" placeholder="auto-generated"
						<?php echo ! $is_new ? 'data-manual="1"' : ''; ?>>
					<p class="description">URL-friendly identifier.</p>
				</div>

				<?php if ( $type_obj['hierarchical'] ?? false ) : ?>
					<div class="form-group">
						<label for="field-parent">Parent</label>
						<select id="field-parent" name="parent" class="form-control">
							<option value="">(none)</option>
							<?php
							foreach ( $all_pages as $p ) :
								if ( $p['slug'] === $edit_slug ) {
									continue;
								}
								?>
								<option value="<?php echo mc_esc_attr( $p['slug'] ); ?>"
									<?php echo $item['parent'] === $p['slug'] ? 'selected' : ''; ?>>
									<?php echo mc_esc_html( $p['title'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<div class="form-group">
					<label for="field-template">Template</label>
					<input type="text" id="field-template" name="template" class="form-control" value="<?php echo mc_esc_attr( $item['template'] ); ?>" placeholder="default">
					<p class="description">Custom template file name.</p>
				</div>

				<div class="form-group">
					<label for="field-order">Order</label>
					<input type="number" id="field-order" name="order" class="form-control" value="<?php echo (int) $item['order']; ?>">
				</div>
			</div>
		</div>
	</div>
</form>

<script src="<?php echo mc_esc_url( mc_admin_url( 'assets/js/editor.js' ) ); ?>"></script>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
