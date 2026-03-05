<?php
/**
 * Single Content Template
 *
 * Used for individual content items of custom types.
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

mc_get_header();
?>

<article class="entry">
	<header class="entry-header">
		<h1 class="entry-title"><?php mc_the_title(); ?></h1>
		<?php
		$content = mc_get_the_content_item();
		if ( ! empty( $content['author'] ) || ! empty( $content['created'] ) ) :
		?>
		<p class="entry-meta">
			<?php if ( ! empty( $content['author'] ) ) : ?>
				By <?php echo mc_esc_html( $content['author'] ); ?>
			<?php endif; ?>
			<?php if ( ! empty( $content['created'] ) ) : ?>
				on <?php echo mc_esc_html( date( 'F j, Y', strtotime( $content['created'] ) ) ); ?>
			<?php endif; ?>
		</p>
		<?php endif; ?>
	</header>
	<div class="entry-content">
		<?php mc_the_content(); ?>
	</div>
</article>

<?php
mc_get_footer();
