const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve( __dirname, 'src/js/settings/index.jsx' ),
		review: path.resolve( __dirname, 'src/js/review/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
