/**
 * Vendor Copy Script
 *
 * Copies third-party dist files from node_modules into mc-admin/assets/vendor/
 * so they can be served as local assets without CDN dependencies.
 *
 * Add new packages to the `packages` map below.
 *
 * Usage:  npm run vendor:copy
 */

const fs   = require( 'fs' );
const path = require( 'path' );

const ROOT       = path.resolve( __dirname, '..', '..' );
const VENDOR_DIR = path.join( ROOT, 'mc-admin', 'assets', 'vendor' );

/**
 * Map of package name → { src: glob-free list of files, dest: subfolder name }.
 *
 * To add a new library, add an entry here and run `npm run vendor:copy`.
 */
const packages = {
	easymde: {
		dest: 'easymde',
		files: [
			'node_modules/easymde/dist/easymde.min.css',
			'node_modules/easymde/dist/easymde.min.js',
		],
	},
	// Example: add more packages here
	// codemirror: {
	//     dest: 'codemirror',
	//     files: [
	//         'node_modules/codemirror/lib/codemirror.min.js',
	//         'node_modules/codemirror/lib/codemirror.min.css',
	//     ],
	// },
};

let copied = 0;

for ( const [ name, pkg ] of Object.entries( packages ) ) {
	const destDir = path.join( VENDOR_DIR, pkg.dest );

	// Ensure destination directory exists.
	fs.mkdirSync( destDir, { recursive: true } );

	for ( const relFile of pkg.files ) {
		const src  = path.join( ROOT, relFile );
		const dest = path.join( destDir, path.basename( relFile ) );

		if ( ! fs.existsSync( src ) ) {
			console.error( `  ✗ Missing: ${relFile} — run "npm install" first.` );
			process.exitCode = 1;
			continue;
		}

		fs.copyFileSync( src, dest );
		console.log( `  ✓ ${name}: ${path.relative( ROOT, dest )}` );
		copied++;
	}
}

console.log( `\nDone — ${copied} file(s) copied to mc-admin/assets/vendor/.\n` );
