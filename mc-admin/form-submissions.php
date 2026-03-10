<?php

/**
 * MinimalCMS — Form Submissions (Admin)
 *
 * Lists and displays form submissions stored on disk.
 * Supports per-form filtering, detail view, and deletion.
 *
 * @package MinimalCMS\Forms
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

mc_admin_require_capability('edit_content');

/*
 * ── Helpers ───────────────────────────────────────────────────
 */

/**
 * Return the base submissions directory (with trailing slash).
 *
 * @return string
 */
function fsub_get_submissions_dir(): string
{
	return defined('MC_FORMS_SUBMISSIONS_DIR') ? MC_FORMS_SUBMISSIONS_DIR : MC_CONTENT_DIR . 'forms-submissions/';
}

/**
 * Return an array of all form slugs that have a submissions directory.
 *
 * @return string[]
 */
function fsub_get_form_slugs(): array
{
	$base = fsub_get_submissions_dir();
	if (! is_dir($base)) {
		return array();
	}
	$entries = scandir($base);
	$slugs   = array();
	foreach ($entries as $entry) {
		if ('.' === $entry || '..' === $entry) {
			continue;
		}
		if (is_dir($base . $entry)) {
			$slugs[] = $entry;
		}
	}
	return $slugs;
}

/**
 * Return an array of submission data arrays for a given form slug,
 * sorted newest-first.
 *
 * @param string $form_slug Form slug.
 * @return array[]
 */
function fsub_get_submissions(string $form_slug): array
{
	$base = fsub_get_submissions_dir();
	$dir  = $base . $form_slug . '/';

	if (! is_dir($dir)) {
		return array();
	}

	$files = glob($dir . '*.php');
	if (! $files) {
		return array();
	}

	// Sort newest first (filenames are date-prefixed).
	rsort($files);

	$submissions = array();
	foreach ($files as $file) {
		$raw = MC_File_Guard::read($file);
		if (false === $raw || '' === $raw) {
			continue;
		}
		$plain = forms_decrypt_submission(trim($raw));
		if (false === $plain) {
			continue;
		}
		$data = json_decode($plain, true);
		if (is_array($data)) {
			$submissions[] = $data;
		}
	}

	return $submissions;
}

/**
 * Load a single submission by form slug and submission ID.
 *
 * @param string $form_slug     Form slug.
 * @param string $submission_id Submission ID (filename without .php).
 * @return array|null
 */
function fsub_get_submission(string $form_slug, string $submission_id): ?array
{
	// Validate ID format to prevent path traversal.
	if (! preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{8}$/', $submission_id)) {
		return null;
	}

	$base = fsub_get_submissions_dir();
	$path = $base . $form_slug . '/' . $submission_id . '.php';

	if (! file_exists($path)) {
		return null;
	}

	$raw   = MC_File_Guard::read($path);
	if (false === $raw || '' === $raw) {
		return null;
	}
	$plain = forms_decrypt_submission(trim($raw));

	if (false === $plain) {
		return null;
	}

	$data = json_decode($plain, true);

	return is_array($data) ? $data : null;
}

/**
 * Delete a single submission file.
 *
 * @param string $form_slug     Form slug.
 * @param string $submission_id Submission ID.
 * @return bool
 */
function fsub_delete_submission(string $form_slug, string $submission_id): bool
{
	// Validate ID format to prevent path traversal.
	if (! preg_match('/^[0-9]{8}-[0-9]{6}-[0-9a-f]{8}$/', $submission_id)) {
		return false;
	}

	$base = fsub_get_submissions_dir();
	$path = $base . $form_slug . '/' . $submission_id . '.php';

	if (! file_exists($path)) {
		return false;
	}

	return unlink($path);
}

/**
 * Resolve field labels from the saved form definition.
 *
 * Returns an array of field_name => label. Falls back to the field name
 * when the form definition is unavailable.
 *
 * @param string $form_slug Form slug.
 * @return array<string, string>
 */
function fsub_get_field_labels(string $form_slug): array
{
	$form = mc_get_content('form', $form_slug);
	if (! $form || mc_is_error($form)) {
		return array();
	}

	$meta   = function_exists('forms_normalize_meta') ? forms_normalize_meta($form['meta'] ?? array()) : array();
	$fields = $meta['fields'] ?? array();
	$labels = array();

	foreach ($fields as $field) {
		$name = $field['name'] ?? '';
		if ('' !== $name) {
			$labels[ $name ] = '' !== ( $field['label'] ?? '' ) ? $field['label'] : $name;
		}
	}

	return $labels;
}

/*
 * ── Input ──────────────────────────────────────────────────────────────────
 */

$current_form = mc_sanitize_slug(mc_input('form', 'get') ?? '');
$view_id      = mc_input('id', 'get') ?? '';
// Sanitise view_id: alphanumeric + hyphens only, max 40 chars.
$view_id      = preg_replace('/[^0-9a-f\-]/', '', mb_substr($view_id, 0, 40));
$notice       = '';
$notice_type  = 'success';

/*
 * ── Delete action ──────────────────────────────────────────────────────────
 */
if (
	isset($_GET['action'], $_GET['id'], $_GET['form'], $_GET['_nonce']) &&
	'delete' === $_GET['action'] &&
	mc_current_user_can('delete_content')
) {
	$del_form = mc_sanitize_slug($_GET['form']);
	$del_id   = preg_replace('/[^0-9a-f\-]/', '', mb_substr($_GET['id'], 0, 40));

	if (mc_verify_nonce($_GET['_nonce'], 'delete_submission_' . $del_id)) {
		if (fsub_delete_submission($del_form, $del_id)) {
			$notice = 'Submission deleted.';
			// If we were viewing that submission, go back to the list.
			if ($view_id === $del_id) {
				$view_id = '';
			}
		} else {
			$notice      = 'Could not delete submission.';
			$notice_type = 'error';
		}
	} else {
		$notice      = 'Invalid nonce.';
		$notice_type = 'error';
	}
}

/*
 * ── Render mode ────────────────────────────────────────────────────────────
 */

$all_form_slugs = fsub_get_form_slugs();

// Detail view.
if ('' !== $current_form && '' !== $view_id) {
	$submission = fsub_get_submission($current_form, $view_id);
	if (! $submission) {
		$notice      = 'Submission not found.';
		$notice_type = 'error';
		$view_id     = '';
	}
}

// Per-form list view.
if ('' !== $current_form && '' === $view_id) {
	$submissions  = fsub_get_submissions($current_form);
	$field_labels = fsub_get_field_labels($current_form);
	$form_obj     = mc_get_content('form', $current_form);
	$form_title   = $form_obj['title'] ?? ucfirst(str_replace('-', ' ', $current_form));
}

$admin_page_title = 'Form Submissions';
require MC_ABSPATH . 'mc-admin/admin-header.php';
?>

<?php mc_render_admin_notice($notice, $notice_type); ?>

<?php
/* ── Detail view ─────────────────────────────────────────────────────────── */
if ('' !== $current_form && '' !== $view_id && isset($submission) && $submission) :
	$field_labels = fsub_get_field_labels($current_form);
	$form_obj     = mc_get_content('form', $current_form);
	$form_title   = $form_obj['title'] ?? ucfirst(str_replace('-', ' ', $current_form));
	$delete_url   = mc_esc_url(
		mc_admin_url(
			'form-submissions.php?form=' . urlencode($current_form) .
			'&action=delete&id=' . urlencode($submission['id']) .
			'&_nonce=' . mc_create_nonce('delete_submission_' . $submission['id'])
		)
	);
	$back_url     = mc_esc_url(mc_admin_url('form-submissions.php?form=' . urlencode($current_form)));
?>

<div class="page-header-bar">
	<h2><?php echo mc_esc_html($form_title); ?> — Submission</h2>
	<a href="<?php echo $back_url; ?>" class="btn">&larr; Back to list</a>
</div>

<div class="content-max-width-lg">
	<table class="mc-table mb-lg">
		<tbody>
			<tr>
				<th class="th-fixed-sm">ID</th>
				<td><code><?php echo mc_esc_html($submission['id']); ?></code></td>
			</tr>
			<tr>
				<th>Submitted</th>
				<td><?php echo mc_esc_html(date('M j, Y \a\t H:i:s', strtotime($submission['submitted'] ?? ''))); ?></td>
			</tr>
			<tr>
				<th>IP Address</th>
				<td><?php echo mc_esc_html($submission['ip'] ?? '—'); ?></td>
			</tr>
			<tr>
				<th>User Agent</th>
				<td class="text-tiny-breakable"><?php echo mc_esc_html($submission['user_agent'] ?? '—'); ?></td>
			</tr>
		</tbody>
	</table>

	<h3 class="mb-sm">Field Values</h3>
	<table class="mc-table mb-lg">
		<thead>
			<tr>
				<th class="th-fixed-md">Field</th>
				<th>Value</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($submission['values'] ?? array() as $field_key => $field_value) : ?>
				<tr>
					<td><strong><?php echo mc_esc_html($field_labels[ $field_key ] ?? $field_key); ?></strong></td>
					<td>
						<?php
						if (is_array($field_value)) {
							echo mc_esc_html(implode(', ', $field_value));
						} else {
							echo nl2br(mc_esc_html((string) $field_value));
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if (mc_current_user_can('delete_content')) : ?>
		<a href="<?php echo $delete_url; ?>"
			class="btn btn-danger confirm-delete">
			Delete This Submission
		</a>
	<?php endif; ?>
</div>

<?php
/* ── Per-form list view ───────────────────────────────────────────────────── */
elseif ('' !== $current_form && isset($submissions, $form_title)) :
?>

<div class="page-header-bar">
	<h2><?php echo mc_esc_html($form_title); ?> — <?php echo count($submissions); ?> Submission<?php echo 1 === count($submissions) ? '' : 's'; ?></h2>
	<a href="<?php echo mc_esc_url(mc_admin_url('form-submissions.php')); ?>" class="btn">&larr; All Forms</a>
</div>

<?php if ($submissions) : ?>
	<table class="mc-table">
		<thead>
			<tr>
				<th>Date</th>
				<th>ID</th>
				<th>IP</th>
				<th>Preview</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($submissions as $sub) : ?>
				<?php
				$sub_date    = date('M j, Y H:i', strtotime($sub['submitted'] ?? ''));
				$sub_preview = '';
				$values      = $sub['values'] ?? array();
				if ($values) {
					$first_val   = reset($values);
					$sub_preview = is_array($first_val) ? implode(', ', $first_val) : (string) $first_val;
					$sub_preview = mb_substr($sub_preview, 0, 60);
				}
				$view_url   = mc_esc_url(mc_admin_url('form-submissions.php?form=' . urlencode($current_form) . '&id=' . urlencode($sub['id'])));
				$delete_url = mc_esc_url(
					mc_admin_url(
						'form-submissions.php?form=' . urlencode($current_form) .
						'&action=delete&id=' . urlencode($sub['id']) .
						'&_nonce=' . mc_create_nonce('delete_submission_' . $sub['id'])
					)
				);
				?>
				<tr>
					<td class="text-nowrap-sm"><?php echo mc_esc_html($sub_date); ?></td>
					<td><code class="text-code-tiny"><?php echo mc_esc_html($sub['id']); ?></code></td>
					<td class="text-muted-sm"><?php echo mc_esc_html($sub['ip'] ?? '—'); ?></td>
					<td class="text-muted-sm"><?php echo mc_esc_html($sub_preview); ?></td>
					<td class="row-actions text-right-nowrap">
						<a href="<?php echo $view_url; ?>">View</a>
						<?php if (mc_current_user_can('delete_content')) : ?>
							<a href="<?php echo $delete_url; ?>" class="delete confirm-delete">Delete</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<?php mc_render_empty_state('&#x1F4EC;', 'No submissions yet for this form.'); ?>
<?php endif; ?>

<?php
/* ── Overview: all forms ──────────────────────────────────────────────────── */
else :
?>

<div class="page-header-bar">
	<h2>Form Submissions</h2>
</div>

<?php if ($all_form_slugs) : ?>
	<table class="mc-table">
		<thead>
			<tr>
				<th>Form</th>
				<th>Submissions</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($all_form_slugs as $slug) : ?>
				<?php
				$form_obj   = mc_get_content('form', $slug);
				$title      = $form_obj['title'] ?? ucfirst(str_replace('-', ' ', $slug));
				$subs       = fsub_get_submissions($slug);
				$count      = count($subs);
				$latest     = $count > 0 ? date('M j, Y H:i', strtotime($subs[0]['submitted'] ?? '')) : '—';
				$list_url   = mc_esc_url(mc_admin_url('form-submissions.php?form=' . urlencode($slug)));
				?>
				<tr>
					<td>
						<strong><a href="<?php echo $list_url; ?>"><?php echo mc_esc_html($title); ?></a></strong>
						<div class="text-tiny-breakable text-muted-sm"><code><?php echo mc_esc_html($slug); ?></code></div>
					</td>
					<td><?php echo (int) $count; ?></td>
					<td class="row-actions text-right">
						<a href="<?php echo $list_url; ?>">View</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<?php mc_render_empty_state('&#x1F4EC;', 'No form submissions yet.'); ?>
<?php endif; ?>

<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
