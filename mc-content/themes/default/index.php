<?php
/**
 * Index Template (Ultimate Fallback)
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

mc_get_header();

if ( mc_is_single() || mc_is_front_page() ) :
?>
	<article class="entry">
		<header class="entry-header">
			<h1 class="entry-title"><?php mc_the_title(); ?></h1>
		</header>
		<div class="entry-content">
			<?php mc_the_content(); ?>
		</div>
	</article>
<?php else : ?>
	<p>Welcome to <?php echo mc_esc_html( MC_SITE_NAME ); ?>.</p>
<?php endif;

mc_get_footer();
