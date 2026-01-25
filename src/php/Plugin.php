<?php
/**
 * Main plugin class.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton class.
 *
 * Orchestrates all plugin components.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Access checker service.
	 *
	 * @var Services\AccessChecker
	 */
	private Services\AccessChecker $access_checker;

	/**
	 * Access enforcer.
	 *
	 * @var AccessEnforcer
	 */
	private AccessEnforcer $access_enforcer;

	/**
	 * Inbox service.
	 *
	 * @var Services\InboxService
	 */
	private Services\InboxService $inbox_service;

	/**
	 * Workflow state manager.
	 *
	 * @var WorkflowState
	 */
	private WorkflowState $workflow_state;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		$base = VMFA_EDITORIAL_WORKFLOW_PATH . 'src/php/';

		require_once $base . 'Services/AccessChecker.php';
		require_once $base . 'Services/InboxService.php';
		require_once $base . 'AccessEnforcer.php';
		require_once $base . 'WorkflowState.php';
		require_once $base . 'Admin/ReviewPage.php';
		require_once $base . 'Admin/SettingsTab.php';
		require_once $base . 'REST/SettingsController.php';
	}

	/**
	 * Initialize service classes.
	 *
	 * @return void
	 */
	private function init_services(): void {
		$this->access_checker  = new Services\AccessChecker();
		$this->workflow_state  = new WorkflowState( $this->access_checker );
		$this->inbox_service   = new Services\InboxService( $this->access_checker, $this->workflow_state );
		$this->access_enforcer = new AccessEnforcer( $this->access_checker );
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Register REST routes.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Initialize admin components.
		if ( is_admin() ) {
			new Admin\ReviewPage( $this->access_checker, $this->workflow_state );
			new Admin\SettingsTab();
		}

		// Initialize access enforcement.
		$this->access_enforcer->init();

		// Initialize inbox routing.
		$this->inbox_service->init();

		// Initialize workflow state protection.
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

	/**
	 * Get access checker instance.
	 *
	 * @return Services\AccessChecker
	 */
	public function get_access_checker(): Services\AccessChecker {
		return $this->access_checker;
	}

	/**
	 * Get inbox service instance.
	 *
	 * @return Services\InboxService
	 */
	public function get_inbox_service(): Services\InboxService {
		return $this->inbox_service;
	}

	/**
	 * Get workflow state instance.
	 *
	 * @return WorkflowState
	 */
	public function get_workflow_state(): WorkflowState {
		return $this->workflow_state;
	}

	/**
	 * Prevent cloning.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @return void
	 * @throws \Exception Always.
	 */
	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
