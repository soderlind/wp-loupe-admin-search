import { build, context } from 'esbuild';

const watch = process.argv.includes( '--watch' );

const options = {
	entryPoints: [ 'src/js/admin-search.js' ],
	bundle: false,
	format: 'iife',
	platform: 'browser',
	target: [ 'es2019' ],
	charset: 'utf8',
	legalComments: 'none',
	outfile: 'lib/js/admin-search.js',
	banner: {
		js: '/* Generated from src/js/admin-search.js. Do not edit lib/js/admin-search.js directly. */',
	},
	minify: ! watch,
	sourcemap: watch ? 'inline' : false,
};

if ( watch ) {
	const builder = await context( options );
	await builder.watch();
	console.log( 'Watching src/js/admin-search.js -> lib/js/admin-search.js' );
} else {
	await build( options );
	console.log( 'Built lib/js/admin-search.js' );
}