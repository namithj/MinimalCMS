<?php

/**
 * MinimalCMS — Template Sections (Admin)
 *
 * Edit global section content shared across the entire site.
 * Global sections are declared in template files via the
 * "Global Sections:" header comment, e.g.:
 *
 *   Global Sections: promo_banner:Promo Banner, footer_cta:Footer CTA
 *
 * Values are stored in the `theme.sections` settings namespace and rendered
 * on the front-end via mc_the_section() / mc_get_the_section().
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

if (! mc_current_user_can('manage_themes')) {
	mc_redirect(mc_admin_url());
	exit;
}

/*
 * ── Gather all global sections from active theme templates ────────────────
 */
$page_templates  = mc_get_page_templates();
$global_sections = array(); // [ '_global_id' => [ 'label', 'template_name', 'template_file' ] ]

foreach ($page_templates as $tpl_file => $tpl_data) {
	foreach (( $tpl_data['global_sections'] ?? array() ) as $section_id => $section_label) {
		$key = '_global_' . $section_id;
		if (! isset($global_sections[ $key ])) {
			$global_sections[ $key ] = array(
				'label'         => $section_label,
				'template_name' => $tpl_data['name'],
				'template_file' => $tpl_file,
			);
		}
	}
}

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';
$values      = mc_get_settings('theme.sections');

if (mc_is_post_request()) {
	if (! mc_verify_nonce(mc_input('_mc_nonce', 'post'), 'save_global_sections')) {
		$notice      = 'Invalid security token. Please try again.';
		$notice_type = 'error';
	} else {
		$save = array();
		foreach ($global_sections as $key => $info) {
			$raw          = mc_input($key, 'post') ?? '';
			$sanitized    = mc_sanitize_field(array( 'type' => 'markdown' ), $raw);
			$save[ $key ] = $sanitized;
		}

		$result = mc_update_settings('theme.sections', $save);
		if (mc_is_error($result)) {
			$notice      = $result->get_error_message();
			$notice_type = 'error';
		} else {
			$values = mc_get_settings('theme.sections');
			$notice = 'Global sections saved successfully.';
		}
	}
}

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title = 'Template Sections';
define('MC_LOAD_EDITOR', true);
require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php if ($notice) : ?>
	<div class="notice notice-<?php echo mc_esc_attr($notice_type); ?>" data-dismiss>
		<p><?php echo mc_esc_html($notice); ?></p>
	</div>
<?php endif; ?>

<div class="page-header-bar">
	<h2>Global Template Sections</h2>
	<p style="margin-top:.4rem;font-size:.9rem;color:#646970;">These values are shared across the entire site. A page can override any section with its own local content via the page editor.</p>
</div>

<?php if (empty($global_sections)) : ?>
	<div class="empty-state">
		<div class="icon">&#x1F4DD;</div>
		<p>No global sections found. Declare them in a template file with a <code>Global Sections: id:Label</code> header comment.</p>
	</div>
<?php else : ?>
	<?php
	// Group sections by template name for display.
	$by_template = array();
	foreach ($global_sections as $key => $info) {
		$by_template[ $info['template_name'] ][ $key ] = $info;
	}
	?>

	<form method="post" action="">
		<?php mc_nonce_field('save_global_sections'); ?>

		<?php foreach ($by_template as $tpl_name => $sections) : ?>
			<div class="card" style="margin-bottom:1.5rem;">
				<div class="card-header"><?php echo mc_esc_html($tpl_name); ?></div>

				<?php foreach ($sections as $key => $info) : ?>
					<?php
					$gfield = array(
						'id'          => $key,
						'type'        => 'markdown',
						'label'       => $info['label'],
						'default'     => '',
						'description' => 'Supports Markdown. This value is used site-wide by pages that do not set their own local value for this section.',
						'options'     => array( 'rows' => 6 ),
					);
					mc_render_field($gfield, $values[ $key ] ?? '');
					?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<div class="form-actions">
			<button type="submit" class="btn btn-primary">Save Global Sections</button>
		</div>
	</form>

<?php endif; ?>

<script src="<?php echo mc_esc_url(mc_admin_url('assets/js/editor.js')); ?>"></script>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
