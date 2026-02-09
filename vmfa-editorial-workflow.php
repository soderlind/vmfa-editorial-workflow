<?php
/**
 * Plugin Name: Virtual Media Folders - Editorial Workflow
 * Plugin URI: https://github.com/soderlind/vmfa-editorial-workflow
 * Description: Role-based folder access, move restrictions, and Inbox workflow for Virtual Media Folders.
 * Version: 1.6.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Tested up to: 6.9
 * Requires Plugins: virtual-media-folders
 * Author: Per Soderlind
 * Author URI: https://soderlind.no
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vmfa-editorial-workflow
 * Domain Path: /languages
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'VMFA_EDITORIAL_WORKFLOW_VERSION', '1.6.0' );
define( 'VMFA_EDITORIAL_WORKFLOW_FILE', __FILE__ );
define( 'VMFA_EDITORIAL_WORKFLOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'VMFA_EDITORIAL_WORKFLOW_URL', plugin_dir_url( __FILE__ ) );

// Autoload Composer dependencies.
if ( file_exists( VMFA_EDITORIAL_WORKFLOW_PATH . 'vendor/autoload.php' ) ) {
	require_once VMFA_EDITORIAL_WORKFLOW_PATH . 'vendor/autoload.php';
}

// Update checker via GitHub releases.
if ( ! class_exists( \Soderlind\WordPress\GitHubUpdater::class ) ) {
	require_once __DIR__ . '/class-github-updater.php';
}
\Soderlind\WordPress\GitHubUpdater::init(
	github_url:  'https://github.com/soderlind/vmfa-editorial-workflow',
	plugin_file: VMFA_EDITORIAL_WORKFLOW_FILE,
	plugin_slug: 'vmfa-editorial-workflow',
	name_regex:  '/vmfa-editorial-workflow\.zip/',
	branch:      'main',
);

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void {
	// Verify parent plugin is active (fallback for WP < 6.5).
	if ( ! defined( 'VMFO_VERSION' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\missing_parent_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain(
		'vmfa-editorial-workflow',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Require autoloader or manual includes.
	require_once VMFA_EDITORIAL_WORKFLOW_PATH . 'src/php/Plugin.php';

	// Boot the plugin.
	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 15 );

/**
 * Display admin notice when parent plugin is missing.
 *
 * @return void
 */
function missing_parent_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			esc_html_e(
				'Virtual Media Folders – Editorial Workflow requires the Virtual Media Folders plugin to be installed and activated.',
				'vmfa-editorial-workflow'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Run on plugin activation.
 *
 * @return void
 */
function activate(): void {
	// Ensure parent plugin check passes before activation tasks.
	if ( ! defined( 'VMFO_VERSION' ) ) {
		return;
	}

	// Load required classes for activation.
	require_once VMFA_EDITORIAL_WORKFLOW_PATH . 'src/php/Services/AccessChecker.php';
	require_once VMFA_EDITORIAL_WORKFLOW_PATH . 'src/php/WorkflowState.php';
	WorkflowState::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Run on plugin deactivation.
 *
 * @return void
 */
function deactivate(): void {
	// Cleanup transients if any.
	delete_transient( 'vmfa_editorial_workflow_review_count' );
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
