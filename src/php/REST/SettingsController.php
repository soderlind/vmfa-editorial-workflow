<?php
/**
 * Settings REST Controller.
 *
 * REST API endpoints for Editorial Workflow settings.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\REST;

use VmfaEditorialWorkflow\Services\AccessChecker;
use VmfaEditorialWorkflow\Services\InboxService;
use VmfaEditorialWorkflow\WorkflowState;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Settings Controller class.
 *
 * Handles REST API endpoints for managing Editorial Workflow settings.
 */
class SettingsController extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'vmfa-editorial/v1';

	/**
	 * Access checker instance.
	 *
	 * @var AccessChecker
	 */
	private AccessChecker $access_checker;

	/**
	 * Constructor.
	 *
	 * @param AccessChecker $access_checker Access checker instance.
	 */
	public function __construct( AccessChecker $access_checker ) {
		$this->access_checker = $access_checker;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET/POST /settings - Get or update all settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => $this->get_settings_args(),
				],
			]
		);

		// GET/POST /permissions - Folder permissions.
		register_rest_route(
			$this->namespace,
			'/permissions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_permissions' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_permissions' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// GET/POST /inbox - Inbox mappings.
		register_rest_route(
			$this->namespace,
			'/inbox',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_inbox_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_inbox_map' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// GET /stats - Workflow statistics.
		register_rest_route(
			$this->namespace,
			'/stats',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_stats' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		// GET/POST /workflow - Workflow settings.
		register_rest_route(
			$this->namespace,
			'/workflow',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_workflow_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_workflow_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage these settings.', 'vmfa-editorial-workflow' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Get settings endpoint args.
	 *
	 * @return array
	 */
	private function get_settings_args(): array {
		return [
			'permissions' => [
				'type'        => 'object',
				'description' => __( 'Folder permissions by folder ID and role.', 'vmfa-editorial-workflow' ),
			],
			'inbox'       => [
				'type'        => 'object',
				'description' => __( 'Inbox folder mapping by role.', 'vmfa-editorial-workflow' ),
			],
			'workflow'    => [
				'type'        => 'object',
				'description' => __( 'Workflow configuration.', 'vmfa-editorial-workflow' ),
			],
		];
	}

	/**
	 * Get all settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$inbox_service  = new InboxService( $this->access_checker );
		$workflow_state = new WorkflowState( $this->access_checker );

		return rest_ensure_response( [
			'permissions' => $this->get_all_permissions(),
			'inbox'       => $inbox_service->get_inbox_map(),
			'workflow'    => [
				'enabled'           => $workflow_state->is_workflow_enabled(),
				'needsReviewFolder' => $workflow_state->get_needs_review_folder(),
				'approvedFolder'    => $workflow_state->get_approved_folder(),
			],
		] );
	}

	/**
	 * Update all settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$errors = [];

		// Update permissions if provided.
		if ( $request->has_param( 'permissions' ) ) {
			$result = $this->save_permissions( $request->get_param( 'permissions' ) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// Update inbox if provided.
		if ( $request->has_param( 'inbox' ) ) {
			$inbox_service = new InboxService( $this->access_checker );
			$inbox_data    = $request->get_param( 'inbox' );

			if ( is_array( $inbox_data ) ) {
				$inbox_service->set_inbox_map( $inbox_data );
			}
		}

		// Update workflow if provided.
		if ( $request->has_param( 'workflow' ) ) {
			$workflow_state = new WorkflowState( $this->access_checker );
			$workflow_data  = $request->get_param( 'workflow' );

			if ( isset( $workflow_data['enabled'] ) ) {
				$workflow_state->set_workflow_enabled( (bool) $workflow_data['enabled'] );
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'settings_update_error',
				implode( ' ', $errors ),
				[ 'status' => 400 ]
			);
		}

		return $this->get_settings( $request );
	}

	/**
	 * Get permissions endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_permissions( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->get_all_permissions() );
	}

	/**
	 * Update permissions endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_permissions( WP_REST_Request $request ) {
		$permissions = $request->get_json_params();

		$result = $this->save_permissions( $permissions );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->get_all_permissions() );
	}

	/**
	 * Get inbox map endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_inbox_map( WP_REST_Request $request ): WP_REST_Response {
		$inbox_service = new InboxService( $this->access_checker );

		return rest_ensure_response( $inbox_service->get_inbox_map() );
	}

	/**
	 * Update inbox map endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_inbox_map( WP_REST_Request $request ) {
		$inbox_service = new InboxService( $this->access_checker );
		$inbox_data    = $request->get_json_params();

		if ( ! is_array( $inbox_data ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Invalid inbox data.', 'vmfa-editorial-workflow' ),
				[ 'status' => 400 ]
			);
		}

		$inbox_service->set_inbox_map( $inbox_data );

		return rest_ensure_response( $inbox_service->get_inbox_map() );
	}

	/**
	 * Get workflow settings endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_workflow_settings( WP_REST_Request $request ): WP_REST_Response {
		$workflow_state = new WorkflowState( $this->access_checker );

		return rest_ensure_response( [
			'enabled'           => $workflow_state->is_workflow_enabled(),
			'needsReviewFolder' => $workflow_state->get_needs_review_folder(),
			'approvedFolder'    => $workflow_state->get_approved_folder(),
		] );
	}

	/**
	 * Update workflow settings endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_workflow_settings( WP_REST_Request $request ) {
		$workflow_state = new WorkflowState( $this->access_checker );
		$data           = $request->get_json_params();

		if ( isset( $data['enabled'] ) ) {
			$workflow_state->set_workflow_enabled( (bool) $data['enabled'] );
		}

		return $this->get_workflow_settings( $request );
	}

	/**
	 * Get all folder permissions.
	 *
	 * @return array Folder ID => role => actions map.
	 */
	private function get_all_permissions(): array {
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		$folders = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		] );

		if ( is_wp_error( $folders ) ) {
			return [];
		}

		$permissions = [];

		foreach ( $folders as $folder_id ) {
			$folder_permissions = $this->access_checker->get_all_folder_permissions( (int) $folder_id );

			if ( ! empty( $folder_permissions ) ) {
				$permissions[ $folder_id ] = $folder_permissions;
			}
		}

		return $permissions;
	}

	/**
	 * Save folder permissions.
	 *
	 * @param array $permissions Folder ID => role => actions map.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function save_permissions( $permissions ) {
		if ( ! is_array( $permissions ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Invalid permissions data.', 'vmfa-editorial-workflow' ),
				[ 'status' => 400 ]
			);
		}

		foreach ( $permissions as $folder_id => $role_permissions ) {
			$folder_id = absint( $folder_id );

			if ( ! is_array( $role_permissions ) ) {
				continue;
			}

			foreach ( $role_permissions as $role => $actions ) {
				if ( ! is_array( $actions ) ) {
					continue;
				}

				$this->access_checker->set_folder_permissions( $folder_id, $role, $actions );
			}
		}

		return true;
	}

	/**
	 * Get workflow statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$workflow_state = new WorkflowState( $this->access_checker );

		// Get taxonomy.
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		// Total media count.
		$total_media = (int) wp_count_posts( 'attachment' )->inherit;

		// Needs review folder count.
		$needs_review_folder = $workflow_state->get_needs_review_folder();
		$needs_review_count  = 0;
		if ( $needs_review_folder ) {
			$query = new \WP_Query( [
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $needs_review_folder,
					],
				],
			] );
			$needs_review_count = $query->found_posts;
		}

		// Approved folder count.
		$approved_folder = $workflow_state->get_approved_folder();
		$approved_count  = 0;
		if ( $approved_folder ) {
			$query = new \WP_Query( [
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $approved_folder,
					],
				],
			] );
			$approved_count = $query->found_posts;
		}

		// Roles with permissions configured.
		$permissions = $this->get_all_permissions();
		$roles_configured = [];
		foreach ( $permissions as $folder_perms ) {
			foreach ( array_keys( $folder_perms ) as $role ) {
				$roles_configured[ $role ] = true;
			}
		}

		return rest_ensure_response( [
			'totalMedia'      => $total_media,
			'needsReview'     => $needs_review_count,
			'approved'        => $approved_count,
			'rolesConfigured' => count( $roles_configured ),
		] );
	}
}
