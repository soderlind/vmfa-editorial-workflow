import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
	plugins: [react()],
	build: {
		outDir: 'build',
		emptyOutDir: true,
		manifest: true,
		cssCodeSplit: false,
		rollupOptions: {
			input: {
				settings: resolve(__dirname, 'src/js/settings/index.jsx'),
				review: resolve(__dirname, 'src/js/review/index.js'),
			},
			output: {
				format: 'iife',
				entryFileNames: '[name].js',
				chunkFileNames: '[name]-[hash].js',
				assetFileNames: '[name].[ext]',
				globals: {
					'@wordpress/element': 'wp.element',
					'@wordpress/components': 'wp.components',
					'@wordpress/api-fetch': 'wp.apiFetch',
					'@wordpress/i18n': 'wp.i18n',
					'@wordpress/data': 'wp.data',
					'jquery': 'jQuery',
				},
			},
			external: [
				'@wordpress/element',
				'@wordpress/components',
				'@wordpress/api-fetch',
				'@wordpress/i18n',
				'@wordpress/data',
				'jquery',
			],
		},
	},
	define: {
		'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development'),
	},
});
