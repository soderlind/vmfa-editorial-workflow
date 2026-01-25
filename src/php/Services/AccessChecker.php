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

		// Administrators always have full access (cannot be overridden).
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

		// Grant view access to user's inbox folder (where their uploads go).
		if ( self::ACTION_VIEW === $action ) {
			$inbox_folder = $this->get_user_inbox_folder( $user_id );
			if ( $inbox_folder && $inbox_folder === $folder_id ) {
				return true;
			}
		}

		// Check each role the user has.
		foreach ( $user->roles as $role ) {
			// Check if this role has explicit permissions configured.
			$has_explicit_permissions = $this->role_has_configured_permissions( $folder_id, $role );

			if ( $has_explicit_permissions ) {
				// Use explicit permissions.
				$allowed_actions = $this->get_folder_permissions_for_role( $folder_id, $role );
				if ( in_array( $action, $allowed_actions, true ) ) {
					return true;
				}
			} else {
				// No explicit permissions - use defaults.
				// Editors get full access by default.
				if ( 'editor' === $role ) {
					return true;
				}
			}
		}

		// If no permissions are configured for this folder at all, only Editors have default access.
		// (Admins are handled at the top of can_perform_action())
		// Authors/Contributors must have explicit permissions granted.
		return false;
	}

	/**
	 * Get the inbox folder for a user based on their role.
	 *
	 * This duplicates logic from InboxService to avoid circular dependencies.
	 *
	 * @param int $user_id User ID.
	 * @return int|null Folder term ID or null.
	 */
	private function get_user_inbox_folder( int $user_id ): ?int {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		// Administrators don't have inbox folders.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return null;
		}

		// Check inbox map.
		$inbox_map = \get_option( InboxService::OPTION_INBOX_MAP, [] );
		if ( is_array( $inbox_map ) ) {
			foreach ( $user->roles as $role ) {
				if ( isset( $inbox_map[ $role ] ) && is_numeric( $inbox_map[ $role ] ) ) {
					$folder_id = (int) $inbox_map[ $role ];
					$term      = \get_term( $folder_id, $this->taxonomy );
					if ( $term && ! \is_wp_error( $term ) ) {
						return $folder_id;
					}
				}
			}
		}

		// Fall back to "Needs Review" workflow folder.
		$needs_review_id = \get_option( 'vmfa_needs_review_folder', 0 );
		if ( $needs_review_id ) {
			$term = \get_term( (int) $needs_review_id, $this->taxonomy );
			if ( $term && ! \is_wp_error( $term ) ) {
				return (int) $needs_review_id;
			}
		}

		return null;
	}

	/**
	 * Check if a specific role has permissions configured for a folder.
	 *
	 * @param int    $folder_id Folder term ID.
	 * @param string $role      Role name.
	 * @return bool
	 */
	public function role_has_configured_permissions( int $folder_id, string $role ): bool {
		$meta_key    = self::META_PREFIX . sanitize_key( $role );
		$permissions = get_term_meta( $folder_id, $meta_key, true );

		// If the meta exists (even as empty array), permissions are explicitly configured.
		return is_array( $permissions );
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
	 * Returns true if ANY role has permissions explicitly set on this folder,
	 * even if those permissions are an empty array (meaning "deny all").
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

			// If the meta exists (even as empty array), permissions are configured.
			// An empty array means "explicitly deny all" vs. no meta means "use default".
			if ( is_array( $permissions ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a user has any configured permissions on any folder.
	 *
	 * Used to determine if a user should use default access or restricted access.
	 * Returns true if any folder has explicit permissions for any of the user's roles.
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user has any configured permissions.
	 */
	public function user_has_any_configured_permissions( int $user_id ): bool {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		$user_roles = $user->roles;

		if ( empty( $user_roles ) ) {
			return false;
		}

		// Get all folders.
		$folders = get_terms(
			[
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		if ( is_wp_error( $folders ) || empty( $folders ) ) {
			return false;
		}

		// Check if any folder has configured permissions for any of the user's roles.
		foreach ( $folders as $folder_id ) {
			foreach ( $user_roles as $role ) {
				if ( $this->role_has_configured_permissions( (int) $folder_id, $role ) ) {
					return true;
				}
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
	 * Returns permissions for roles that have explicit configuration.
	 * Includes roles with empty arrays (meaning "no access") to distinguish
	 * from roles with no configuration (default behavior).
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
			$meta_key   = self::META_PREFIX . sanitize_key( $role );
			$meta_value = get_term_meta( $folder_id, $meta_key, true );

			// Only include if the meta exists (is an array).
			// Empty array means "explicitly deny all" and should be included.
			if ( is_array( $meta_value ) ) {
				$permissions[ $role ] = array_filter(
					$meta_value,
					fn( $action ) => in_array( $action, self::ACTIONS, true )
				);
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
