<?php
/**
 * Front Page Template
 *
 * Special template for the site's home page.
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
