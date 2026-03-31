<?php
/**
 * 404 Template
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

mc_send_404();
mc_get_header();
?>

<div class="error-404">
	<h1>404</h1>
	<p>Sorry, the page you are looking for could not be found.</p>
	<p><a href="<?php echo mc_esc_url( mc_site_url() ); ?>">Return to the home page</a></p>
</div>

<?php
mc_get_footer();
