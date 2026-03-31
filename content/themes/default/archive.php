<?php
/**
 * Archive Template
 *
 * Displays a listing of content items for a content type.
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

mc_get_header();

global $mc_query;
$type_def = mc_get_content_type( $mc_query['type'] ?? '' );
$items    = $mc_query['archive_items'] ?? array();
?>

<header class="entry-header">
	<h1 class="entry-title"><?php echo mc_esc_html( $type_def['label'] ?? 'Archive' ); ?></h1>
</header>

<?php if ( ! empty( $items ) ) : ?>
	<ul class="archive-list">
	<?php foreach ( $items as $item ) : ?>
		<li>
			<a href="<?php echo mc_esc_url( mc_get_content_permalink( $mc_query['type'], $item['slug'] ) ); ?>">
				<?php echo mc_esc_html( $item['title'] ); ?>
			</a>
			<?php if ( ! empty( $item['excerpt'] ) ) : ?>
				<p class="excerpt"><?php echo mc_esc_html( $item['excerpt'] ); ?></p>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
	</ul>
<?php else : ?>
	<p>No items found.</p>
<?php endif; ?>

<?php
mc_get_footer();
