<?php
/**
 * Page with Sidebar Template
 *
 * Template Name: Page with Sidebar
 * Global Sections: sidebar_content:Sidebar Content
 *
 * Two-column layout: main content on the left, sidebar on the right.
 * The sidebar area is populated by the "Sidebar Content" template section.
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

mc_get_header();
?>

<article class="entry has-sidebar">
	<div class="content-area">
		<div class="entry-content">
			<?php mc_the_content(); ?>
		</div>
	</div>

	<aside class="sidebar">
		<?php mc_the_section( 'sidebar_content' ); ?>
		<?php mc_do_action( 'mc_sidebar' ); ?>
	</aside>
</article>

<?php
mc_get_footer();
