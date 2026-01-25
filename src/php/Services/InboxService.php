<?php
/**
 * Inbox Service.
 *
 * Routes new uploads to role-based inbox folders.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Services;

use VmfaEditorialWorkflow\WorkflowState;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Inbox Service class.
 *
 * Handles automatic routing of uploads to inbox folders based on user role.
 */
class InboxService {

	/**
	 * Option name for inbox mapping.
	 *
	 * Format: [ 'role_name' => folder_id, ... ]
	 *
	 * @var string
	 */
	public const OPTION_INBOX_MAP = 'vmfa_inbox_map';

	/**
	 * Access checker instance.
	 *
	 * @var AccessChecker
	 */
	private AccessChecker $access_checker;

	/**
	 * Workflow state instance.
	 *
	 * @var WorkflowState|null
	 */
	private ?WorkflowState $workflow_state;

	/**
	 * VMF taxonomy name.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param AccessChecker      $access_checker Access checker instance.
	 * @param WorkflowState|null $workflow_state Workflow state instance (optional).
	 */
	public function __construct( AccessChecker $access_checker, ?WorkflowState $workflow_state = null ) {
		$this->access_checker = $access_checker;
		$this->workflow_state = $workflow_state;
		$this->taxonomy       = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into attachment metadata generation (runs after upload).
		// Priority 15 = before Rules Engine (20) but after VMF core.
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'route_to_inbox' ], 15, 3 );
	}

	/**
	 * Route newly uploaded media to the user's inbox folder.
	 *
	 * @param array  $metadata      Attachment metadata.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context       Context: 'create' for new uploads.
	 * @return array Unmodified metadata.
	 */
	public function route_to_inbox( array $metadata, int $attachment_id, string $context ): array {
		// Only process new uploads.
		if ( 'create' !== $context ) {
			return $metadata;
		}

		// Check if attachment already has a folder assignment.
		$existing_folders = wp_get_object_terms( $attachment_id, $this->taxonomy, [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $existing_folders ) && ! empty( $existing_folders ) ) {
			return $metadata;
		}

		// Get current user's inbox folder (includes fallback to Needs Review).
		$inbox_folder_id = $this->get_user_inbox_folder( get_current_user_id() );

		if ( ! $inbox_folder_id ) {
			return $metadata;
		}

		// Check if this is a workflow system folder (always allow for non-admins with upload_files cap).
		$is_workflow_folder = $this->is_workflow_folder( $inbox_folder_id );

		// Verify user can upload to this folder (skip check for workflow folders).
		if ( ! $is_workflow_folder && ! $this->access_checker->can_upload_to_folder( $inbox_folder_id ) ) {
			return $metadata;
		}

		// Assign to inbox folder.
		$result = wp_set_object_terms( $attachment_id, $inbox_folder_id, $this->taxonomy );

		if ( ! is_wp_error( $result ) ) {
			/**
			 * Fires after media is assigned to an inbox folder.
			 *
			 * @param int $attachment_id  The attachment ID.
			 * @param int $inbox_folder_id The inbox folder term ID.
			 * @param int $user_id        The user who uploaded the media.
			 */
			do_action( 'vmfa_inbox_assigned', $attachment_id, $inbox_folder_id, get_current_user_id() );
		}

		return $metadata;
	}

	/**
	 * Get inbox folder ID for a user based on their role.
	 *
	 * Falls back to the "Needs Review" workflow folder if no role-specific inbox is configured.
	 *
	 * @param int $user_id User ID.
	 * @return int|null Folder term ID or null if no inbox configured.
	 */
	public function get_user_inbox_folder( int $user_id ): ?int {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		// Administrators don't need inbox routing.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return null;
		}

		$inbox_map = $this->get_inbox_map();

		// Check each role (in order) for an inbox assignment.
		foreach ( $user->roles as $role ) {
			if ( isset( $inbox_map[ $role ] ) && is_numeric( $inbox_map[ $role ] ) ) {
				$folder_id = (int) $inbox_map[ $role ];

				// Verify folder exists.
				$term = get_term( $folder_id, $this->taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					return $folder_id;
				}
			}
		}

		// Fall back to "Needs Review" workflow folder if available.
		if ( $this->workflow_state && $this->workflow_state->is_workflow_enabled() ) {
			$needs_review_folder = $this->workflow_state->get_needs_review_folder();
			if ( $needs_review_folder ) {
				return $needs_review_folder;
			}
		}

		return null;
	}

	/**
	 * Get the inbox mapping array.
	 *
	 * @return array<string, int> Role => folder_id map.
	 */
	public function get_inbox_map(): array {
		$map = get_option( self::OPTION_INBOX_MAP, [] );

		if ( ! is_array( $map ) ) {
			return [];
		}

		return $map;
	}

	/**
	 * Set the inbox mapping.
	 *
	 * @param array<string, int> $map Role => folder_id map.
	 * @return bool True on success.
	 */
	public function set_inbox_map( array $map ): bool {
		// Sanitize the map.
		$sanitized = [];
		foreach ( $map as $role => $folder_id ) {
			$sanitized[ sanitize_key( $role ) ] = absint( $folder_id );
		}

		return update_option( self::OPTION_INBOX_MAP, $sanitized );
	}

	/**
	 * Set inbox folder for a specific role.
	 *
	 * @param string $role      Role name.
	 * @param int    $folder_id Folder term ID.
	 * @return bool True on success.
	 */
	public function set_role_inbox( string $role, int $folder_id ): bool {
		$map                          = $this->get_inbox_map();
		$map[ sanitize_key( $role ) ] = $folder_id;

		return $this->set_inbox_map( $map );
	}

	/**
	 * Remove inbox mapping for a role.
	 *
	 * @param string $role Role name.
	 * @return bool True on success.
	 */
	public function remove_role_inbox( string $role ): bool {
		$map = $this->get_inbox_map();

		$role_key = sanitize_key( $role );
		if ( isset( $map[ $role_key ] ) ) {
			unset( $map[ $role_key ] );
			return $this->set_inbox_map( $map );
		}

		return true;
	}

	/**
	 * Get all configured inbox folders.
	 *
	 * @return array<int> Array of folder term IDs.
	 */
	public function get_all_inbox_folder_ids(): array {
		$map = $this->get_inbox_map();

		return array_unique( array_filter( array_map( 'absint', array_values( $map ) ) ) );
	}

	/**
	 * Check if a folder is configured as an inbox.
	 *
	 * @param int $folder_id Folder term ID.
	 * @return bool True if folder is an inbox.
	 */
	public function is_inbox_folder( int $folder_id ): bool {
		return in_array( $folder_id, $this->get_all_inbox_folder_ids(), true );
	}

	/**
	 * Get roles that have a specific folder as their inbox.
	 *
	 * @param int $folder_id Folder term ID.
	 * @return array<string> Role names.
	 */
	public function get_roles_for_inbox( int $folder_id ): array {
		$map   = $this->get_inbox_map();
		$roles = [];

		foreach ( $map as $role => $mapped_folder_id ) {
			if ( (int) $mapped_folder_id === $folder_id ) {
				$roles[] = $role;
			}
		}

		return $roles;
	}

	/**
	 * Check if a folder is a workflow system folder (Needs Review or Approved).
	 *
	 * @param int $folder_id Folder term ID.
	 * @return bool True if folder is a workflow folder.
	 */
	private function is_workflow_folder( int $folder_id ): bool {
		if ( ! $this->workflow_state ) {
			return false;
		}

		$needs_review = $this->workflow_state->get_needs_review_folder();
		$approved     = $this->workflow_state->get_approved_folder();

		return $folder_id === $needs_review || $folder_id === $approved;
	}
}
