<?php
/**
 * MinimalCMS — Admin Footer
 *
 * Closes the content area and outputs closing HTML.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;
?>
			</div><!-- .admin-content -->

			<footer class="admin-footer-bar">
				<p>MinimalCMS <?php echo mc_esc_html( MC_VERSION ); ?></p>
			</footer>
		</main>
	</div><!-- .admin-layout -->

	<script src="<?php echo mc_esc_url( mc_admin_url( 'assets/js/admin.js' ) ); ?>"></script>
	<?php mc_do_action( 'mc_admin_footer' ); ?>
</body>
</html>
