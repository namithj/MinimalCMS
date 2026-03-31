<?php

/**
 * Dashboard Widget: Site Info
 *
 * Displays system details, PHP configuration, memory usage, and storage stats.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Convert bytes to a human-readable string.
 */
function _mc_format_bytes(int $bytes): string
{
	if ($bytes >= 1073741824) {
		return number_format($bytes / 1073741824, 2) . ' GB';
	}
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 2) . ' MB';
	}
	if ($bytes >= 1024) {
		return number_format($bytes / 1024, 2) . ' KB';
	}
	return $bytes . ' B';
}

/**
 * Recursively sum the size of all files under a directory.
 */
function _mc_dir_size(string $dir): int
{
	$size = 0;
	if (! is_dir($dir)) {
		return 0;
	}
	foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
		if ($file->isFile()) {
			$size += $file->getSize();
		}
	}
	return $size;
}

// ── Data ───────────────────────────────────────────────────────────────────

$disk_total       = disk_total_space(MC_CONTENT_DIR) ?: 0;
$disk_free        = disk_free_space(MC_CONTENT_DIR) ?: 0;
$disk_used        = $disk_total - $disk_free;
$disk_used_pct    = $disk_total > 0 ? round(( $disk_used / $disk_total ) * 100, 1) : 0;
$content_dir_size = _mc_dir_size(MC_CONTENT_DIR);
$mem_usage        = memory_get_usage(true);
$mem_peak         = memory_get_peak_usage(true);
$mem_limit_raw    = ini_get('memory_limit');

$mem_limit_bytes = (int) $mem_limit_raw;
if (str_ends_with(strtolower($mem_limit_raw), 'g')) {
	$mem_limit_bytes = (int) $mem_limit_raw * 1073741824;
} elseif (str_ends_with(strtolower($mem_limit_raw), 'm')) {
	$mem_limit_bytes = (int) $mem_limit_raw * 1048576;
} elseif (str_ends_with(strtolower($mem_limit_raw), 'k')) {
	$mem_limit_bytes = (int) $mem_limit_raw * 1024;
}
$mem_pct = $mem_limit_bytes > 0 ? round(( $mem_usage / $mem_limit_bytes ) * 100, 1) : 0;

?>
<div class="card">
	<div class="card-header">Site Info</div>
	<div class="site-info-grid">

		<!-- System -->
		<div class="site-info-panel">
			<p class="site-info-panel__label">System</p>
			<dl class="site-info-list">
				<dt>MinimalCMS</dt>
				<dd>v<?php echo mc_esc_html(MC_VERSION); ?></dd>
				<dt>PHP</dt>
				<dd><?php echo mc_esc_html(PHP_VERSION); ?></dd>
				<dt>OS</dt>
				<dd><?php echo mc_esc_html(PHP_OS_FAMILY . ' (' . php_uname('r') . ')'); ?></dd>
				<dt>Web Server</dt>
				<dd><?php echo mc_esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></dd>
				<dt>HTTPS</dt>
				<dd><?php echo ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) ? '<span class="badge badge-publish">Yes</span>' : '<span class="badge badge-draft">No</span>'; ?></dd>
			</dl>
		</div>

		<!-- PHP Configuration -->
		<div class="site-info-panel">
			<p class="site-info-panel__label">PHP Configuration</p>
			<dl class="site-info-list">
				<dt>Memory Limit</dt>
				<dd><?php echo mc_esc_html($mem_limit_raw); ?></dd>
				<dt>Upload Max</dt>
				<dd><?php echo mc_esc_html(ini_get('upload_max_filesize')); ?></dd>
				<dt>Post Max Size</dt>
				<dd><?php echo mc_esc_html(ini_get('post_max_size')); ?></dd>
				<dt>Max Exec Time</dt>
				<dd><?php echo mc_esc_html(ini_get('max_execution_time')); ?>s</dd>
				<dt>Extensions</dt>
				<dd><?php
					$exts = array_filter(array( 'sodium', 'mbstring', 'json', 'fileinfo', 'openssl' ), 'extension_loaded');
					echo mc_esc_html(implode(', ', $exts));
				?></dd>
			</dl>
		</div>

		<!-- Memory Usage -->
		<div class="site-info-panel">
			<p class="site-info-panel__label">Memory Usage</p>
			<div class="site-info-meter">
				<div class="site-info-meter__row">
					<span class="site-info-meter__value"><?php echo mc_esc_html(_mc_format_bytes($mem_usage)); ?></span>
					<span class="site-info-meter__sub">of <?php echo mc_esc_html($mem_limit_raw); ?> limit</span>
				</div>
				<div class="site-info-bar" title="<?php echo mc_esc_attr($mem_pct . '% used'); ?>">
					<div class="site-info-bar__fill<?php echo $mem_pct > 80 ? ' site-info-bar__fill--warn' : ''; ?>" style="width:<?php echo min(100, $mem_pct); ?>%"></div>
				</div>
				<div class="site-info-meter__meta">
					<span><?php echo mc_esc_html($mem_pct . '% used'); ?></span>
					<span>Peak: <?php echo mc_esc_html(_mc_format_bytes($mem_peak)); ?></span>
				</div>
			</div>
		</div>

		<!-- Storage -->
		<div class="site-info-panel">
			<p class="site-info-panel__label">Storage</p>
			<div class="site-info-meter">
				<div class="site-info-meter__row">
					<span class="site-info-meter__value"><?php echo mc_esc_html(_mc_format_bytes((int) $disk_used)); ?></span>
					<span class="site-info-meter__sub">of <?php echo mc_esc_html(_mc_format_bytes((int) $disk_total)); ?></span>
				</div>
				<div class="site-info-bar" title="<?php echo mc_esc_attr($disk_used_pct . '% used'); ?>">
					<div class="site-info-bar__fill<?php echo $disk_used_pct > 80 ? ' site-info-bar__fill--warn' : ''; ?>" style="width:<?php echo min(100, $disk_used_pct); ?>%"></div>
				</div>
				<div class="site-info-meter__meta">
					<span><?php echo mc_esc_html($disk_used_pct . '% used'); ?></span>
					<span>Free: <?php echo mc_esc_html(_mc_format_bytes((int) $disk_free)); ?></span>
				</div>
			</div>
		</div>

	</div>
</div>
