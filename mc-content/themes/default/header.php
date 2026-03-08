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
	<link rel="icon" href="<?php echo mc_esc_url( mc_favicon_url() ); ?>" type="image/svg+xml" />
	<?php mc_head(); ?>
</head>
<body <?php mc_body_class(); ?>>
<?php mc_body_open(); ?>

<div class="site">
	<header class="site-header">
		<div class="container">
			<div class="site-branding">
				<a href="<?php echo mc_esc_url( mc_site_url() ); ?>" class="brand">Minimal<span>CMS</span></a>
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
