<?php
/**
 * Page Template
 *
 * Template for displaying a single page content item.
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

mc_get_header();
?>

<article class="entry">
	<div class="entry-content">
		<?php mc_the_content(); ?>
	</div>
</article>

<?php
mc_get_footer();
