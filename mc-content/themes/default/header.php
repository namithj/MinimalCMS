<?php
/**
 * Header Template
 *
 * @package MinimalCMS\Themes\Default
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php mc_document_title(); ?></title>
	<meta name="description" content="<?php mc_the_excerpt(); ?>" />
	<?php mc_head(); ?>
</head>
<body <?php mc_body_class(); ?>>

<?php if ( mc_is_logged_in() ) : ?>
<div class="mc-admin-bar">
	<span>MinimalCMS</span>
	<nav>
		<a href="<?php echo mc_esc_url( mc_admin_url() ); ?>">Dashboard</a>
		<a href="<?php echo mc_esc_url( mc_admin_url( 'pages.php' ) ); ?>">Pages</a>
		<?php if ( mc_is_single() ) : ?>
			<?php
			global $mc_query;
			$edit_url = mc_admin_url( 'edit-page.php?type=' . rawurlencode( $mc_query['type'] ?? 'page' ) . '&slug=' . rawurlencode( $mc_query['slug'] ?? '' ) );
			?>
			<a href="<?php echo mc_esc_url( $edit_url ); ?>">Edit Page</a>
		<?php endif; ?>
		<a href="<?php echo mc_esc_url( mc_admin_url( 'login.php?action=logout' ) ); ?>">Log Out</a>
	</nav>
</div>
<?php endif; ?>

<div class="site">
	<header class="site-header">
		<div class="container">
			<div class="site-branding">
				<a href="<?php echo mc_esc_url( mc_site_url() ); ?>">
					<?php echo mc_esc_html( MC_SITE_NAME ); ?>
				</a>
				<?php if ( MC_SITE_DESCRIPTION ) : ?>
					<span class="site-description"> — <?php echo mc_esc_html( MC_SITE_DESCRIPTION ); ?></span>
				<?php endif; ?>
			</div>
			<nav class="main-navigation">
				<ul>
				<?php
				$nav_pages = default_theme_get_nav_pages();
				foreach ( $nav_pages as $nav_item ) :
					$is_active = ( ( $mc_query['slug'] ?? '' ) === $nav_item['slug'] ) ? ' active' : '';
					$label     = $nav_item['title'] ?: $nav_item['slug'];
					$href      = mc_get_content_permalink( 'page', $nav_item['slug'] );
				?>
					<li><a href="<?php echo mc_esc_url( $href ); ?>"<?php echo $is_active ? ' class="active"' : ''; ?>><?php echo mc_esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
				</ul>
			</nav>
		</div>
	</header>

	<main class="site-main">
		<div class="container">
