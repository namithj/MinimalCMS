<?php

/**
 * MinimalCMS — Edit / Create Page (Admin)
 *
 * Markdown editor with live-preview and metadata sidebar.
 * Sidebar fields are rendered through the Fields API so that plugins
 * and themes can register additional fields per content type.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

require_once __DIR__ . '/admin.php';

$content_type = mc_sanitize_slug(mc_input('type', 'get') ?: 'page');
$edit_slug    = mc_sanitize_slug(mc_input('slug', 'get') ?? '');
$is_new       = empty($edit_slug);

if ($is_new && ! mc_current_user_can('create_content')) {
	mc_redirect(mc_admin_url());
	exit;
}

if (! $is_new && ! mc_current_user_can('edit_content')) {
	mc_redirect(mc_admin_url());
	exit;
}

$type_obj    = mc_get_content_type($content_type);
$type_single = $type_obj['singular'] ?? ucfirst($content_type);

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

if (! $is_new) {
	$existing = mc_get_content($content_type, $edit_slug);
	if ($existing && ! mc_is_error($existing)) {
		$item = array_merge($item, $existing);
		$body = $existing['body_raw'] ?? '';
	} else {
		$is_new = true;
	}
}

/*
 * ── Build sidebar field definitions via Fields API ─────────────────────────
 */
$publish_fields = array(
	'status' => array(
		'id'      => 'status',
		'type'    => 'select',
		'label'   => 'Status',
		'default' => 'draft',
		'options' => array(
			'choices' => array(
				'draft'   => 'Draft',
				'publish' => 'Published',
			),
		),
	),
);

$attribute_fields = array(
	'slug' => array(
		'id'          => 'slug',
		'type'        => 'text',
		'label'       => 'Slug',
		'description' => 'URL-friendly identifier.',
		'default'     => '',
		'attributes'  => array_merge(
			array( 'placeholder' => 'auto-generated' ),
			! $is_new ? array( 'data-manual' => '1' ) : array()
		),
	),
);

// Parent field — only for hierarchical types.
if ($type_obj['hierarchical'] ?? false) {
	$all_pages = mc_query_content(array(
		'type'     => $content_type,
		'order_by' => 'title',
		'order'    => 'asc',
		'status'   => '',
	));

	$parent_choices = array( '' => '(none)' );
	foreach ($all_pages as $p) {
		if ($p['slug'] !== $edit_slug) {
			$parent_choices[ $p['slug'] ] = $p['title'];
		}
	}

	$attribute_fields['parent'] = array(
		'id'      => 'parent',
		'type'    => 'select',
		'label'   => 'Parent',
		'default' => '',
		'options' => array( 'choices' => $parent_choices ),
	);
}

$page_templates = mc_get_page_templates();

// Build per-page section fields and a map of which templates define each one.
$section_fields       = array();
$section_template_map = array(); // [ '_section_foo' => [ 'tpl-a.php', 'tpl-b.php' ] ]

foreach ($page_templates as $tpl_file => $tpl_data) {
	foreach (( $tpl_data['sections'] ?? array() ) as $section_id => $section_label) {
		$key = '_section_' . $section_id;
		if (! isset($section_fields[ $key ])) {
			$section_fields[ $key ] = array(
				'id'      => $key,
				'type'    => 'markdown',
				'label'   => $section_label,
				'default' => '',
				'options' => array( 'rows' => 6 ),
			);
		}
		$section_template_map[ $key ][] = $tpl_file;
	}
}

if (! empty($page_templates)) {
	$template_choices = array( '' => 'Default' );
	foreach ($page_templates as $tpl_file => $tpl_data) {
		$template_choices[ $tpl_file ] = $tpl_data['name'];
	}

	$attribute_fields['template'] = array(
		'id'      => 'template',
		'type'    => 'select',
		'label'   => 'Template',
		'default' => '',
		'options' => array( 'choices' => $template_choices ),
	);
}

$attribute_fields['order'] = array(
	'id'      => 'order',
	'type'    => 'number',
	'label'   => 'Order',
	'default' => 0,
);

/**
 * Filter the sidebar field definitions for the content editor.
 *
 * Plugins can add custom meta fields here. Values for extra fields are
 * stored in the content item's 'meta' array.
 *
 * @since 1.1.0
 *
 * @param array  $attribute_fields Field definitions keyed by field ID.
 * @param string $content_type     Content type slug.
 * @param array  $item             Current content item data.
 * @param bool   $is_new           Whether this is a new item.
 */
$attribute_fields = mc_apply_filters('mc_edit_content_fields', $attribute_fields, $content_type, $item, $is_new);

// Build a map of current values for sidebar fields.
$sidebar_values = array();
$core_keys      = array( 'status', 'slug', 'parent', 'template', 'order' );

foreach ($publish_fields as $fid => $fdef) {
	$sidebar_values[ $fid ] = $item[ $fid ] ?? ( $fdef['default'] ?? '' );
}
foreach ($attribute_fields as $fid => $fdef) {
	if (in_array($fid, $core_keys, true)) {
		$sidebar_values[ $fid ] = $item[ $fid ] ?? ( $fdef['default'] ?? '' );
	} else {
		// Custom fields: pull from meta.
		$sidebar_values[ $fid ] = $item['meta'][ $fid ] ?? ( $fdef['default'] ?? '' );
	}
}

// Load current values for template section fields from item meta.
$section_values = array();
foreach ($section_fields as $fid => $fdef) {
	$section_values[ $fid ] = $item['meta'][ $fid ] ?? '';
}

/*
 * ── Handle POST ────────────────────────────────────────────────────────────
 */
$notice      = '';
$notice_type = 'success';
$errors      = array();

if (mc_is_post_request()) {
	if (! mc_verify_nonce(mc_input('_mc_nonce', 'post'), 'edit_content')) {
		$notice      = 'Invalid security token. Please try again.';
		$notice_type = 'error';
	} else {
		// Process main content fields manually (title, body, excerpt).
		$item['title']   = mc_sanitize_text(mc_input('title', 'post') ?? '');
		$item['excerpt'] = mc_sanitize_text(mc_input('excerpt', 'post') ?? '');
		$body            = mc_input('body', 'post') ?? '';

		// Process sidebar fields through the Fields API.
		$raw_sidebar = array();
		foreach (array_merge($publish_fields, $attribute_fields) as $fid => $fdef) {
			$raw_sidebar[ $fid ] = mc_input($fid, 'post');
		}

		$all_sidebar  = array_merge($publish_fields, $attribute_fields);
		$processed    = mc_process_fields($all_sidebar, $raw_sidebar);
		$errors       = $processed['errors'];

		// Map processed values back to the item.
		$item['status']   = $processed['values']['status'] ?? 'draft';
		$item['slug']     = mc_sanitize_slug((string) ( $processed['values']['slug'] ?? '' ));
		$item['parent']   = $processed['values']['parent'] ?? '';
		$item['template'] = $processed['values']['template'] ?? '';
		$item['order']    = (int) ( $processed['values']['order'] ?? 0 );

		// Store any custom (non-core) fields into meta.
		foreach ($processed['values'] as $fid => $fval) {
			if (! in_array($fid, $core_keys, true)) {
				$item['meta'][ $fid ] = $fval;
			}
		}

		// Process template section fields and store in meta.
		if (! empty($section_fields)) {
			$raw_sections = array();
			foreach ($section_fields as $fid => $fdef) {
				$raw_sections[ $fid ] = mc_input($fid, 'post');
			}
			$processed_sections = mc_process_fields($section_fields, $raw_sections);
			foreach ($processed_sections['values'] as $fid => $val) {
				$item['meta'][ $fid ] = $val;
			}
			$section_values = $processed_sections['values'];
		}

		// Update sidebar_values for re-render.
		foreach ($publish_fields as $fid => $fdef) {
			$sidebar_values[ $fid ] = $processed['values'][ $fid ] ?? $sidebar_values[ $fid ];
		}
		foreach ($attribute_fields as $fid => $fdef) {
			$sidebar_values[ $fid ] = $processed['values'][ $fid ] ?? $sidebar_values[ $fid ];
		}

		// Title is required.
		if (empty($item['title'])) {
			$notice      = 'Title is required.';
			$notice_type = 'error';
		} elseif (empty($item['slug'])) {
			$item['slug'] = mc_slugify($item['title']);
		}

		if ('error' !== $notice_type && empty($errors)) {
			$save_meta = $item;

			if ($is_new) {
				$save_meta['author'] = mc_get_current_user_id();
			}

			$result = mc_save_content($content_type, $item['slug'], $save_meta, $body ?? '');

			// Handle slug rename.
			if (true === $result && ! $is_new && $edit_slug !== $item['slug']) {
				mc_delete_content($content_type, $edit_slug);
			}

			if (mc_is_error($result)) {
				$notice      = $result->get_error_message();
				$notice_type = 'error';
			} else {
				mc_redirect(mc_admin_url('edit-page.php?type=' . urlencode($content_type) . '&slug=' . urlencode($item['slug']) . '&saved=1'));
				exit;
			}
		} elseif (! empty($errors)) {
			$notice      = 'Please correct the errors below.';
			$notice_type = 'error';
		}
	}
}

if (isset($_GET['saved'])) {
	$notice      = $type_single . ' saved successfully.';
	$notice_type = 'success';
}

/*
 * ── Render ─────────────────────────────────────────────────────────────────
 */
$admin_page_title    = $is_new ? 'New ' . $type_single : 'Edit ' . $type_single;
$type_supports       = $type_obj['supports'] ?? array( 'title', 'editor', 'excerpt' );
$supports_editor     = in_array('editor', $type_supports, true);
$supports_excerpt    = in_array('excerpt', $type_supports, true);

if ($supports_editor || ! empty($section_fields)) {
	define('MC_LOAD_EDITOR', true);
}

// Body field definition — rendered via Fields API so the markdown type is used.
$body_field = array(
	'id'          => 'body',
	'type'        => 'markdown',
	'label'       => 'Content (Markdown)',
	'default'     => '',
	'attributes'  => array(
		'placeholder'  => 'Write your content in Markdown…',
		'data-autosave' => '1',
	),
);

require MC_ABSPATH . 'mc-admin/admin-header.php';

?>

<?php mc_render_admin_notice($notice, $notice_type); ?>

<form method="post" action="" data-slug="<?php echo mc_esc_attr($edit_slug); ?>">
	<?php mc_nonce_field('edit_content'); ?>

	<div class="editor-layout">
		<!-- Main editor column -->
		<div class="editor-main">
			<?php
			mc_render_field(
				array(
					'id'         => 'title',
					'type'       => 'text',
					'label'      => 'Title',
					'required'   => true,
					'attributes' => array(
						'placeholder' => 'Enter title…',
						'autofocus'   => 'autofocus',
					),
				),
				$item['title'],
				$errors['title'] ?? null
			);
			?>

			<?php if ($supports_editor) : ?>
			<?php mc_render_field($body_field, $body); ?>
			<?php endif; ?>

			<?php if ($supports_excerpt) : ?>
			<?php
			mc_render_field(
				array(
					'id'         => 'excerpt',
					'type'       => 'textarea',
					'label'      => 'Excerpt',
					'options'    => array( 'rows' => 3 ),
					'attributes' => array( 'placeholder' => 'Short summary…' ),
				),
				$item['excerpt']
			);
			?>
			<?php endif; ?>

			<?php
			/**
			 * Fires after the main editor fields (below excerpt).
			 *
			 * Plugins can output additional full-width fields here.
			 *
			 * @since 1.1.0
			 *
			 * @param string $content_type Content type slug.
			 * @param array  $item         Current item data.
			 */
			mc_do_action('mc_edit_content_after_editor', $content_type, $item);
			?>
		</div>

		<!-- Sidebar column -->
		<div class="editor-sidebar">
			<div class="card">
				<div class="card-header">Publish</div>
				<?php
				foreach ($publish_fields as $fid => $fdef) {
					$fdef['id'] = $fid;
					mc_render_field($fdef, $sidebar_values[ $fid ] ?? '', $errors[ $fid ] ?? null);
				}
				?>
				<div class="form-actions">
					<button type="submit" class="btn btn-primary">Save <?php echo mc_esc_html($type_single); ?></button>
					<?php if (! $is_new) : ?>
						<a href="<?php echo mc_esc_url(mc_get_content_permalink($content_type, $item['slug'])); ?>" class="btn btn-secondary" target="_blank">View</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="card">
				<div class="card-header">Attributes</div>
				<?php
				foreach ($attribute_fields as $fid => $fdef) {
					$fdef['id'] = $fid;
					mc_render_field($fdef, $sidebar_values[ $fid ] ?? '', $errors[ $fid ] ?? null);
				}
				?>
			</div>

			<?php
			/**
			 * Fires after the sidebar attribute cards.
			 *
			 * Plugins can output additional sidebar cards here.
			 *
			 * @since 1.1.0
			 *
			 * @param string $content_type Content type slug.
			 * @param array  $item         Current item data.
			 */
			mc_do_action('mc_edit_content_sidebar', $content_type, $item);
			?>

			<?php if (! empty($section_template_map)) : ?>
			<div class="card" id="template-sections-card">
				<div class="card-header">Template Sections</div>
				<?php foreach ($section_template_map as $fid => $tpl_files) : ?>
					<div class="template-section-wrapper" data-for-templates="<?php echo mc_esc_attr(implode(' ', $tpl_files)); ?>">
						<?php
						$sfdef         = $section_fields[ $fid ];
						$sfdef['id']   = $fid;
						mc_render_field($sfdef, $section_values[ $fid ] ?? '');
						?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</form>

<?php if (! empty($section_template_map)) : ?>
<script>
( function () {
	var select   = document.getElementById( 'field-template' );
	if ( ! select ) return;
	var card     = document.getElementById( 'template-sections-card' );
	var wrappers = card ? card.querySelectorAll( '[data-for-templates]' ) : [];

	function updateSections() {
		var val        = select.value;
		var anyVisible = false;
		wrappers.forEach( function ( el ) {
			var list    = el.dataset.forTemplates ? el.dataset.forTemplates.split( ' ' ) : [];
			var visible = val !== '' && list.indexOf( val ) !== -1;
			el.style.display = visible ? '' : 'none';
			if ( visible ) {
				anyVisible = true;
				// Refresh any EasyMDE instances inside this wrapper so CodeMirror
				// recomputes dimensions after being revealed from display:none.
				if ( window.mcEditors ) {
					el.querySelectorAll( 'textarea' ).forEach( function ( ta ) {
						if ( window.mcEditors[ ta.id ] ) {
							window.mcEditors[ ta.id ].codemirror.refresh();
						}
					} );
				}
			}
		} );
		if ( card ) card.style.display = anyVisible ? '' : 'none';
	}

	select.addEventListener( 'change', updateSections );
	updateSections();
}() );
</script>
<?php endif; ?>

<?php if ($supports_editor) : ?>
<script src="<?php echo mc_esc_url(mc_admin_url('assets/js/editor.js')); ?>"></script>
<?php endif; ?>

<?php require MC_ABSPATH . 'mc-admin/admin-footer.php'; ?>
