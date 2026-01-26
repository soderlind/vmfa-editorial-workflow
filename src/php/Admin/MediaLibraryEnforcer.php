<?php
/**
 * Media Library Enforcer.
 *
 * Forces non-admin users to use the folder sidebar view in Media Library.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Media Library Enforcer class.
 *
 * Enforces folder view mode for non-admin users.
 */
class MediaLibraryEnforcer {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Only run in admin.
		if ( ! is_admin() ) {
			return;
		}

		// Enqueue scripts and styles for media library.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Check if current user should be forced to folder view.
	 *
	 * @return bool True if user should be forced to folder view.
	 */
	private function should_enforce_folder_view(): bool {
		// Administrators bypass this restriction.
		return ! current_user_can( 'manage_options' );
	}

	/**
	 * Enqueue enforcer assets on media library pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only on media library pages.
		if ( 'upload.php' !== $hook_suffix ) {
			return;
		}

		// Only for non-admins.
		if ( ! $this->should_enforce_folder_view() ) {
			return;
		}

		$asset_file = VMFA_EDITORIAL_WORKFLOW_PATH . 'build/media-library-enforcer.asset.php';

		// Use asset file if it exists (built), otherwise use defaults.
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
		} else {
			$asset = array(
				'dependencies' => array(),
				'version'      => VMFA_EDITORIAL_WORKFLOW_VERSION,
			);
		}

		// Enqueue JavaScript.
		wp_enqueue_script(
			'vmfa-media-library-enforcer',
			VMFA_EDITORIAL_WORKFLOW_URL . 'build/media-library-enforcer.js',
			array_merge( $asset['dependencies'], array( 'media-views' ) ),
			$asset['version'],
			true
		);

		// Enqueue CSS.
		wp_enqueue_style(
			'vmfa-media-library-enforcer',
			VMFA_EDITORIAL_WORKFLOW_URL . 'build/media-library-enforcer.css',
			array(),
			$asset['version']
		);
	}
}
