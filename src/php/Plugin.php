<?php
/**
 * Main plugin class.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow;

defined( 'ABSPATH' ) || exit;

use VirtualMediaFolders\Addon\AbstractPlugin;

/**
 * Plugin bootstrap class.
 *
 * Orchestrates all plugin components.
 */
final class Plugin extends AbstractPlugin {

	private Services\AccessChecker $access_checker;
	private AccessEnforcer $access_enforcer;
	private Services\InboxService $inbox_service;
	private WorkflowState $workflow_state;
	private ?Admin\SettingsTab $settings_tab = null;

	/** @inheritDoc */
	protected function get_text_domain(): string {
		return 'vmfa-editorial-workflow';
	}

	/** @inheritDoc */
	protected function get_plugin_file(): string {
		return VMFA_EDITORIAL_WORKFLOW_FILE;
	}

	/** @inheritDoc */
	protected function init_services(): void {
		$this->access_checker  = new Services\AccessChecker();
		$this->workflow_state  = new WorkflowState( $this->access_checker );
		$this->inbox_service   = new Services\InboxService( $this->access_checker, $this->workflow_state );
		$this->access_enforcer = new AccessEnforcer( $this->access_checker );
		$this->settings_tab    = new Admin\SettingsTab();
	}

	/** @inheritDoc */
	protected function init_hooks(): void {
		// REST routes.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Admin components.
		if ( is_admin() ) {
			new Admin\ReviewPage( $this->access_checker, $this->workflow_state );

			if ( $this->supports_parent_tabs() ) {
				add_filter( 'vmfo_settings_tabs', [ $this->settings_tab, 'register_tab' ] );
				add_action( 'vmfo_settings_enqueue_scripts', [ $this->settings_tab, 'enqueue_tab_scripts' ], 10, 2 );
			} else {
				add_action( 'admin_menu', [ $this->settings_tab, 'register_admin_menu' ] );
				add_action( 'admin_enqueue_scripts', [ $this->settings_tab, 'enqueue_admin_assets' ] );
			}

			$media_library_enforcer = new Admin\MediaLibraryEnforcer();
			$media_library_enforcer->init();
		}

		// Access enforcement & inbox routing.
		$this->access_enforcer->init();
		$this->inbox_service->init();
		$this->workflow_state->init();
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$settings_controller = new REST\SettingsController( $this->access_checker );
		$settings_controller->register_routes();
	}

	public function get_access_checker(): Services\AccessChecker {
		return $this->access_checker;
	}

	public function get_inbox_service(): Services\InboxService {
		return $this->inbox_service;
	}

	public function get_workflow_state(): WorkflowState {
		return $this->workflow_state;
	}
}
