<?php
/**
 * Access Checker service.
 *
 * Determines folder permissions based on user roles and term meta.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Services;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Access Checker class.
 *
 * Checks whether users can perform actions on specific folders.
 * Permissions are stored as term meta on vmfo_folder terms.
 */
class AccessChecker {

	/**
	 * Action constants.
	 */
	public const ACTION_VIEW   = 'view';
	public const ACTION_MOVE   = 'move';
	public const ACTION_UPLOAD = 'upload';
	public const ACTION_REMOVE = 'remove';

	/**
	 * All available actions.
	 *
	 * @var array<string>
	 */
	public const ACTIONS = [
		self::ACTION_VIEW,
		self::ACTION_MOVE,
		self::ACTION_UPLOAD,
		self::ACTION_REMOVE,
	];

	/**
	 * Term meta key prefix for role permissions.
	 *
	 * Full key format: vmfa_access_{role}
	 * Value: array of allowed actions.
	 *
	 * @var string
	 */
	public const META_PREFIX = 'vmfa_access_';

	/**
	 * Cache for permission lookups within a request.
	 *
	 * @var array<string, bool>
	 */
	private array $cache = [];

	/**
	 * VMF taxonomy name.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';
	}

	/**
	 * Check if a user can view a folder.
	 *
	 * @param int      $folder_id Folder term ID.
	 * @param int|null $user_id   User ID. Defaults to current user.
	 * @return bool
	 */
	public function can_view_folder( int $folder_id, ?int $user_id = null ): bool {
		return $this->can_perform_action( $folder_id, self::ACTION_VIEW, $user_id );
	}

	/**
	 * Check if a user can move media to a folder.
	 *
	 * @param int      $folder_id Folder term ID.
	 * @param int|null $user_id   User ID. Defaults to current user.
	 * @return bool
	 */
	public function can_move_to_folder( int $folder_id, ?int $user_id = null ): bool {
		return $this->can_perform_action( $folder_id, self::ACTION_MOVE, $user_id );
	}

	/**
	 * Check if a user can upload to a folder.
	 *
	 * @param int      $folder_id Folder term ID.
	 * @param int|null $user_id   User ID. Defaults to current user.
	 * @return bool
	 */
	public function can_upload_to_folder( int $folder_id, ?int $user_id = null ): bool {
		return $this->can_perform_action( $folder_id, self::ACTION_UPLOAD, $user_id );
	}

	/**
	 * Check if a user can remove media from a folder.
	 *
	 * @param int      $folder_id Folder term ID.
	 * @param int|null $user_id   User ID. Defaults to current user.
	 * @return bool
	 */
	public function can_remove_from_folder( int $folder_id, ?int $user_id = null ): bool {
		return $this->can_perform_action( $folder_id, self::ACTION_REMOVE, $user_id );
	}

	/**
	 * Check if a user can perform an action on a folder.
	 *
	 * @param int      $folder_id Folder term ID.
	 * @param string   $action    Action to check (view, move, upload, remove).
	 * @param int|null $user_id   User ID. Defaults to current user.
	 * @return bool
	 */
	public function can_perform_action( int $folder_id, string $action, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();

		// Administrators always have full access.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Check cache.
		$cache_key = "{$folder_id}:{$action}:{$user_id}";
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$result = $this->check_role_permission( $folder_id, $action, $user_id );

		// Cache result.
		$this->cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Check role-based permission for a folder action.
	 *
	 * @param int    $folder_id Folder term ID.
	 * @param string $action    Action to check.
	 * @param int    $user_id   User ID.
	 * @return bool
	 */
	private function check_role_permission( int $folder_id, string $action, int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Check each role the user has.
		foreach ( $user->roles as $role ) {
			$allowed_actions = $this->get_folder_permissions_for_role( $folder_id, $role );

			if ( in_array( $action, $allowed_actions, true ) ) {
				return true;
			}
		}

		// If no permissions are configured for this folder, check default behavior.
		// Default: allow if user has upload_files capability.
		if ( ! $this->has_configured_permissions( $folder_id ) ) {
			return user_can( $user_id, 'upload_files' );
		}

		return false;
	}

	/**
	 * Get allowed actions for a role on a folder.
	 *
	 * @param int    $folder_id Folder term ID.
	 * @param string $role      Role name.
	 * @return array<string> Array of allowed actions.
	 */
	public function get_folder_permissions_for_role( int $folder_id, string $role ): array {
		$meta_key    = self::META_PREFIX . sanitize_key( $role );
		$permissions = get_term_meta( $folder_id, $meta_key, true );

		if ( ! is_array( $permissions ) ) {
			return [];
		}

		return array_filter(
			$permissions,
			fn( $action ) => in_array( $action, self::ACTIONS, true )
		);
	}

	/**
	 * Check if a folder has any configured permissions.
	 *
	 * @param int $folder_id Folder term ID.
	 * @return bool
	 */
	public function has_configured_permissions( int $folder_id ): bool {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		foreach ( array_keys( $wp_roles->roles ) as $role ) {
			$meta_key    = self::META_PREFIX . sanitize_key( $role );
			$permissions = get_term_meta( $folder_id, $meta_key, true );

			if ( is_array( $permissions ) && ! empty( $permissions ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set permissions for a role on a folder.
	 *
	 * @param int           $folder_id Folder term ID.
	 * @param string        $role      Role name.
	 * @param array<string> $actions   Array of allowed actions.
	 * @return bool True on success.
	 */
	public function set_folder_permissions( int $folder_id, string $role, array $actions ): bool {
		// Validate actions.
		$valid_actions = array_filter(
			$actions,
			fn( $action ) => in_array( $action, self::ACTIONS, true )
		);

		$meta_key = self::META_PREFIX . sanitize_key( $role );
		$result   = update_term_meta( $folder_id, $meta_key, $valid_actions );

		// Clear cache.
		$this->clear_cache();

		return false !== $result;
	}

	/**
	 * Remove all permissions for a role on a folder.
	 *
	 * @param int    $folder_id Folder term ID.
	 * @param string $role      Role name.
	 * @return bool True on success.
	 */
	public function remove_folder_permissions( int $folder_id, string $role ): bool {
		$meta_key = self::META_PREFIX . sanitize_key( $role );
		$result   = delete_term_meta( $folder_id, $meta_key );

		// Clear cache.
		$this->clear_cache();

		return $result;
	}

	/**
	 * Get all folders a user can access for a specific action.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  Action to check (view, move, upload, remove).
	 * @return array<int> Array of folder term IDs.
	 */
	public function get_allowed_folders( int $user_id, string $action = self::ACTION_VIEW ): array {
		$folders = get_terms(
			[
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_wp_error( $folders ) ) {
			return [];
		}

		return array_filter(
			$folders,
			fn( $folder_id ) => $this->can_perform_action( (int) $folder_id, $action, $user_id )
		);
	}

	/**
	 * Get all permissions for a folder (all roles).
	 *
	 * @param int $folder_id Folder term ID.
	 * @return array<string, array<string>> Role => actions map.
	 */
	public function get_all_folder_permissions( int $folder_id ): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		$permissions = [];

		foreach ( array_keys( $wp_roles->roles ) as $role ) {
			$actions = $this->get_folder_permissions_for_role( $folder_id, $role );
			if ( ! empty( $actions ) ) {
				$permissions[ $role ] = $actions;
			}
		}

		return $permissions;
	}

	/**
	 * Clear the permission cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = [];
	}
}
