<?php
/**
 * Access Enforcer.
 *
 * Enforces folder access restrictions across all surfaces:
 * REST API, AJAX, and admin UI.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow;

use VmfaEditorialWorkflow\Services\AccessChecker;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Access Enforcer class.
 *
 * Hooks into REST, AJAX, and admin to enforce folder permissions.
 */
class AccessEnforcer {

	/**
	 * Access checker instance.
	 *
	 * @var AccessChecker
	 */
	private AccessChecker $access_checker;

	/**
	 * VMF REST namespace.
	 *
	 * @var string
	 */
	private const VMF_NAMESPACE = 'vmfo/v1';

	/**
	 * Constructor.
	 *
	 * @param AccessChecker $access_checker Access checker instance.
	 */
	public function __construct( AccessChecker $access_checker ) {
		$this->access_checker = $access_checker;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// REST API enforcement.
		add_filter( 'rest_pre_dispatch', array( $this, 'filter_rest_pre_dispatch' ), 10, 3 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'enforce_rest_permissions' ), 10, 3 );

		// AJAX media query enforcement.
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_media_query_args' ), 20 );

		// REST API media query enforcement (for /wp/v2/media endpoint).
		add_filter( 'rest_attachment_query', array( $this, 'filter_rest_attachment_query' ), 20, 2 );

		// Admin folder list filtering.
		add_filter( 'get_terms', array( $this, 'filter_folder_terms' ), 10, 4 );

		// AJAX move enforcement - intercept VMF drag-drop and bulk move actions.
		add_action( 'wp_ajax_vmfo_move_to_folder', array( $this, 'enforce_ajax_move_permission' ), 1 );
		add_action( 'wp_ajax_vmfo_bulk_move_to_folder', array( $this, 'enforce_ajax_move_permission' ), 1 );

		// Folder deletion enforcement.
		add_filter( 'vmfo_can_delete_folder', array( $this, 'enforce_folder_delete_permission' ), 20, 3 );
	}

	/**
	 * Filter REST dispatch to modify folder list responses.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed
	 */
	public function filter_rest_pre_dispatch( $result, $server, $request ) {
		// Only process if not already handled.
		if ( null !== $result ) {
			return $result;
		}

		$route = $request->get_route();

		// Filter GET /vmfo/v1/folders to only show accessible folders.
		if ( '/vmfo/v1/folders' === $route && 'GET' === $request->get_method() ) {
			add_filter( 'rest_post_dispatch', array( $this, 'filter_folders_response' ), 10, 3 );
		}

		// Filter GET /vmfo/v1/folders/counts to only count accessible folders.
		if ( '/vmfo/v1/folders/counts' === $route && 'GET' === $request->get_method() ) {
			add_filter( 'rest_post_dispatch', array( $this, 'filter_folder_counts_response' ), 10, 3 );
		}

		return $result;
	}

	/**
	 * Filter folders response to hide inaccessible folders.
	 *
	 * @param WP_REST_Response $response Result to send to the client.
	 * @param WP_REST_Server   $server   Server instance.
	 * @param WP_REST_Request  $request  Request used to generate the response.
	 * @return WP_REST_Response
	 */
	public function filter_folders_response( WP_REST_Response $response, $server, WP_REST_Request $request ): WP_REST_Response {
		// Remove this filter to prevent duplicate processing.
		remove_filter( 'rest_post_dispatch', array( $this, 'filter_folders_response' ), 10 );

		if ( '/vmfo/v1/folders' !== $request->get_route() ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) ) {
			return $response;
		}

		$user_id = get_current_user_id();

		// Skip filtering for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return $response;
		}

		// Skip filtering for editors with default full access.
		if ( current_user_can( 'edit_others_posts' ) && ! $this->access_checker->user_has_any_configured_permissions( $user_id ) ) {
			return $response;
		}

		// Get allowed folders for this user (includes inbox folder).
		$allowed_folders = $this->access_checker->get_allowed_folders( $user_id, AccessChecker::ACTION_VIEW );

		// Filter folders based on view permission.
		$filtered = array_filter(
			$data,
			function ( $folder ) use ( $allowed_folders ) {
				$folder_id = (int) $folder[ 'id' ];
				return in_array( $folder_id, $allowed_folders, true );
			}
		);

		// Re-index array.
		$response->set_data( array_values( $filtered ) );

		return $response;
	}

	/**
	 * Filter folder counts response.
	 *
	 * @param WP_REST_Response $response Result to send to the client.
	 * @param WP_REST_Server   $server   Server instance.
	 * @param WP_REST_Request  $request  Request used to generate the response.
	 * @return WP_REST_Response
	 */
	public function filter_folder_counts_response( WP_REST_Response $response, $server, WP_REST_Request $request ): WP_REST_Response {
		// Remove this filter to prevent duplicate processing.
		remove_filter( 'rest_post_dispatch', array( $this, 'filter_folder_counts_response' ), 10 );

		if ( '/vmfo/v1/folders/counts' !== $request->get_route() ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! is_array( $data ) ) {
			return $response;
		}

		$user_id = get_current_user_id();

		// Skip filtering for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return $response;
		}

		// Skip filtering for editors with default full access.
		if ( current_user_can( 'edit_others_posts' ) && ! $this->access_checker->user_has_any_configured_permissions( $user_id ) ) {
			return $response;
		}

		// Get allowed folders for this user.
		$allowed_folders = $this->access_checker->get_allowed_folders( $user_id, AccessChecker::ACTION_VIEW );

		// Filter counts to only include accessible folders.
		$filtered = array();
		foreach ( $data as $folder_id => $count ) {
			$folder_int = (int) $folder_id;

			// Handle "Uncategorized" (folder_id = 0) specially.
			if ( 0 === $folder_int ) {
				// For users with no folder access, show only their own uncategorized media.
				if ( empty( $allowed_folders ) ) {
					$own_uncategorized_count = $this->count_user_uncategorized_media( $user_id );
					if ( $own_uncategorized_count > 0 ) {
						$filtered[ $folder_id ] = $own_uncategorized_count;
					}
				}
				// Users with folder access don't see Uncategorized at all (their items are in folders).
				continue;
			}

			// Only include folders the user can view.
			if ( in_array( $folder_int, $allowed_folders, true ) ) {
				$filtered[ $folder_id ] = $count;
			}
		}

		$response->set_data( $filtered );

		return $response;
	}

	/**
	 * Count uncategorized media for a specific user.
	 *
	 * @param int $user_id User ID.
	 * @return int Count of uncategorized attachments.
	 */
	private function count_user_uncategorized_media( int $user_id ): int {
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array(
				array(
					'taxonomy' => $taxonomy,
					'operator' => 'NOT EXISTS',
				),
			),
		);

		$query = new \WP_Query( $args );

		return $query->found_posts;
	}

	/**
	 * Enforce permissions before REST callbacks execute.
	 *
	 * @param WP_REST_Response|WP_Error|mixed $response Response.
	 * @param array                           $handler  Route handler.
	 * @param WP_REST_Request                 $request  Request.
	 * @return WP_REST_Response|WP_Error|mixed
	 */
	public function enforce_rest_permissions( $response, array $handler, WP_REST_Request $request ) {
		// Don't process if already an error.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$route  = $request->get_route();
		$method = $request->get_method();

		// Match /vmfo/v1/folders/{id}/media routes.
		if ( preg_match( '#^/vmfo/v1/folders/(\d+)/media$#', $route, $matches ) ) {
			$folder_id = (int) $matches[ 1 ];

			// POST = adding media to folder.
			if ( 'POST' === $method ) {
				if ( ! $this->access_checker->can_move_to_folder( $folder_id ) ) {
					return new WP_Error(
						'vmfa_permission_denied',
						__( 'You do not have permission to add media to this folder.', 'vmfa-editorial-workflow' ),
						array( 'status' => 403 )
					);
				}
			}
		}

		// Match /vmfo/v1/folders/{id} routes for single folder operations.
		if ( preg_match( '#^/vmfo/v1/folders/(\d+)$#', $route, $matches ) ) {
			$folder_id = (int) $matches[ 1 ];

			// GET = viewing folder.
			if ( 'GET' === $method ) {
				if ( ! $this->access_checker->can_view_folder( $folder_id ) ) {
					return new WP_Error(
						'vmfa_permission_denied',
						__( 'You do not have permission to view this folder.', 'vmfa-editorial-workflow' ),
						array( 'status' => 403 )
					);
				}
			}
		}

		return $response;
	}

	/**
	 * Enforce move permissions for VMF AJAX actions.
	 *
	 * Intercepts vmfo_move_to_folder and vmfo_bulk_move_to_folder AJAX actions
	 * to check folder-level permissions before VMF processes the request.
	 *
	 * Permission model:
	 * - Moving TO a folder: Requires "Move To" permission on destination
	 * - Moving TO Uncategorized: Allowed for all users (no special permission needed)
	 *
	 * @return void Sends JSON error and exits if permission denied.
	 */
	public function enforce_ajax_move_permission(): void {
		// Skip for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Skip for editors with default full access (no explicit permissions configured).
		if ( current_user_can( 'edit_others_posts' ) && ! $this->access_checker->user_has_any_configured_permissions( $user_id ) ) {
			return;
		}

		// Get the target folder ID from the request.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by VMF.
		$target_folder = isset( $_POST[ 'folder_id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'folder_id' ] ) ) : '';

		// Allow removing to Uncategorized for all users.
		$is_remove_to_uncategorized = ( '' === $target_folder || 'uncategorized' === $target_folder || 'root' === $target_folder );
		if ( $is_remove_to_uncategorized ) {
			return;
		}

		// Moving to a folder requires "Move To" permission on destination.
		$target_folder_id = absint( $target_folder );

		if ( ! $this->access_checker->can_move_to_folder( $target_folder_id, $user_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to move media to this folder.', 'vmfa-editorial-workflow' ),
				),
				403
			);
		}
	}

	/**
	 * Get folder IDs for a media attachment.
	 *
	 * @param int $media_id Attachment ID.
	 * @return array<int> Array of folder term IDs.
	 */
	private function get_media_folders( int $media_id ): array {
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		$terms = wp_get_object_terms( $media_id, $taxonomy, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map( 'intval', $terms );
	}

	/**
	 * Filter AJAX attachment query to only show media in accessible folders.
	 *
	 * @param array $query Query arguments.
	 * @return array Modified query arguments.
	 */
	public function filter_media_query_args( array $query ): array {
		$user_id = get_current_user_id();

		// Skip for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return $query;
		}

		// Skip for editors with default full access (no explicit permissions configured).
		if ( current_user_can( 'edit_others_posts' ) && ! $this->access_checker->user_has_any_configured_permissions( $user_id ) ) {
			return $query;
		}

		// Get allowed folder IDs.
		$allowed_folders = $this->access_checker->get_allowed_folders( $user_id, AccessChecker::ACTION_VIEW );

		// Get the taxonomy name.
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		// If user has no allowed folders, restrict to only their own uploads.
		if ( empty( $allowed_folders ) ) {
			$query[ 'author' ] = $user_id;
			return $query;
		}

		// Check if query already has a folder filter.
		if ( isset( $query[ 'tax_query' ] ) && is_array( $query[ 'tax_query' ] ) ) {
			// Find and modify vmfo_folder tax query.
			foreach ( $query[ 'tax_query' ] as $key => $tax_query ) {
				if ( isset( $tax_query[ 'taxonomy' ] ) && $taxonomy === $tax_query[ 'taxonomy' ] ) {
					// Intersect requested folders with allowed folders.
					if ( isset( $tax_query[ 'terms' ] ) && is_array( $tax_query[ 'terms' ] ) ) {
						$query[ 'tax_query' ][ $key ][ 'terms' ] = array_intersect(
							$tax_query[ 'terms' ],
							$allowed_folders
						);
					}
				}
			}
		} else {
			// No folder filter set - restrict to allowed folders only.
			$query[ 'tax_query' ] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $allowed_folders,
					'operator' => 'IN',
				),
			);
		}

		return $query;
	}

	/**
	 * Filter REST API attachment query to only show media user can access.
	 *
	 * This filters the /wp/v2/media endpoint to ensure users only see
	 * media they have permission to view.
	 *
	 * @param array           $args    Query arguments.
	 * @param WP_REST_Request $request REST request.
	 * @return array Modified query arguments.
	 */
	public function filter_rest_attachment_query( array $args, $request ): array {
		// Use the same logic as AJAX query filtering.
		return $this->filter_media_query_args( $args );
	}

	/**
	 * Filter folder terms in admin to hide inaccessible folders.
	 *
	 * @param array         $terms      Array of found terms.
	 * @param array|null    $taxonomies Array of taxonomies.
	 * @param array         $args       Term query arguments.
	 * @param WP_Term_Query $term_query The term query object.
	 * @return array Filtered terms.
	 */
	public function filter_folder_terms( array $terms, ?array $taxonomies, array $args, $term_query ): array {
		// Only filter vmfo_folder taxonomy.
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		if ( ! is_array( $taxonomies ) || ! in_array( $taxonomy, $taxonomies, true ) ) {
			return $terms;
		}

		// Skip for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return $terms;
		}

		// Only filter in admin context.
		if ( ! is_admin() ) {
			return $terms;
		}

		$user_id = get_current_user_id();

		// Filter to only accessible folders.
		return array_filter(
			$terms,
			function ( $term ) use ( $user_id ) {
				if ( is_object( $term ) && isset( $term->term_id ) ) {
					return $this->access_checker->can_view_folder( (int) $term->term_id, $user_id );
				}
				if ( is_numeric( $term ) ) {
					return $this->access_checker->can_view_folder( (int) $term, $user_id );
				}
				return true;
			}
		);
	}

	/**
	 * Enforce folder delete permission.
	 *
	 * Hooks into vmfo_can_delete_folder filter to check if user has
	 * "Delete" permission on the folder before allowing deletion.
	 *
	 * @param bool|\WP_Error $can_delete Whether folder can be deleted.
	 * @param int            $folder_id  Folder term ID.
	 * @param \WP_Term       $term       Folder term object.
	 * @return bool|\WP_Error
	 */
	public function enforce_folder_delete_permission( $can_delete, int $folder_id, \WP_Term $term ) {
		// If already denied (e.g., system folder), respect that.
		if ( is_wp_error( $can_delete ) || false === $can_delete ) {
			return $can_delete;
		}

		// Skip for administrators.
		if ( current_user_can( 'manage_options' ) ) {
			return $can_delete;
		}

		$user_id = get_current_user_id();

		// Skip for editors with default full access (no explicit permissions configured).
		if ( current_user_can( 'edit_others_posts' ) && ! $this->access_checker->user_has_any_configured_permissions( $user_id ) ) {
			return $can_delete;
		}

		// Check delete permission.
		if ( ! $this->access_checker->can_delete_folder( $folder_id, $user_id ) ) {
			return new \WP_Error(
				'vmfa_permission_denied',
				__( 'You do not have permission to delete this folder.', 'vmfa-editorial-workflow' )
			);
		}

		return $can_delete;
	}
}
