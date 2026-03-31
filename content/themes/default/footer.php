<?php
/**
 * Footer Template
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;
?>
		</div><!-- .container -->
	</main><!-- .site-main -->

	<footer class="site-footer">
		<div class="container">
			<p>&copy; <?php echo date( 'Y' ); ?> <?php echo mc_esc_html( MC_SITE_NAME ); ?>. Powered by <a href="https://github.com/minimalcms">MinimalCMS</a>.</p>
		</div>
	</footer>
</div><!-- .site -->

<?php mc_footer(); ?>
</body>
</html>
