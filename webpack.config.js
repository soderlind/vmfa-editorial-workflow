/**
 * Webpack configuration for VMFA Editorial Workflow.
 *
 * Extends default @wordpress/scripts webpack config to add
 * parent plugin's shared components as external dependency.
 *
 * @package VmfaEditorialWorkflow
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve( __dirname, 'src/js/settings/index.jsx' ),
		review: path.resolve( __dirname, 'src/js/review/index.js' ),
		'media-library-enforcer': path.resolve(
			__dirname,
			'src/js/media-library-enforcer.js'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	externals: {
		...defaultConfig.externals,
		// Map @vmfo/shared import to window.vmfo.shared global.
		'@vmfo/shared': 'vmfo.shared',
	},
};
