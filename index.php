<?php
/**
 * MinimalCMS Front Controller
 *
 * All web requests are routed here by the web server (Apache .htaccess or
 * nginx try_files). When the server routes a real static file through PHP
 * (common with nginx), we detect it and serve the file directly.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

// Serve static assets directly when the web server routes them through PHP.
// Apache's mod_rewrite handles this via .htaccess (!-f / !-d), but nginx
// may funnel every request through index.php regardless.
if ( PHP_SAPI !== 'cli' ) {
	$_mc_request_path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( false !== $_mc_request_path && '/' !== $_mc_request_path ) {
		$_mc_file = __DIR__ . '/' . ltrim( $_mc_request_path, '/' );
		$_mc_file = realpath( $_mc_file );
		// Only serve if the resolved path is a real file inside the doc root.
		if (
			false !== $_mc_file
			&& is_file( $_mc_file )
			&& str_starts_with( $_mc_file, __DIR__ . DIRECTORY_SEPARATOR )
			&& ! str_ends_with( $_mc_file, '.php' )
		) {
			$_mc_mime_types = array(
				'css'  => 'text/css',
				'js'   => 'application/javascript',
				'json' => 'application/json',
				'svg'  => 'image/svg+xml',
				'png'  => 'image/png',
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'gif'  => 'image/gif',
				'webp' => 'image/webp',
				'ico'  => 'image/x-icon',
				'woff' => 'font/woff',
				'woff2' => 'font/woff2',
				'ttf'  => 'font/ttf',
				'otf'  => 'font/otf',
				'map'  => 'application/json',
			);
			$_mc_ext  = strtolower( pathinfo( $_mc_file, PATHINFO_EXTENSION ) );
			$_mc_mime = $_mc_mime_types[ $_mc_ext ] ?? mime_content_type( $_mc_file );
			header( 'Content-Type: ' . $_mc_mime );
			header( 'Content-Length: ' . filesize( $_mc_file ) );
			readfile( $_mc_file );
			exit;
		}
	}
	unset( $_mc_request_path, $_mc_file, $_mc_mime_types, $_mc_ext, $_mc_mime );
}

define( 'MC_USE_THEMES', true );

require __DIR__ . '/mc-blog-header.php';
