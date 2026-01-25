<?php
/**
 * Workflow State Manager.
 *
 * Manages workflow state system folders (Needs Review, Approved).
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow;

use VmfaEditorialWorkflow\Services\AccessChecker;
use WP_Error;
use WP_Term;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Workflow State class.
 *
 * Creates and manages protected system folders for workflow states.
 */
class WorkflowState {

	/**
	 * Parent workflow folder slug.
	 *
	 * @var string
	 */
	public const FOLDER_WORKFLOW = 'vmfa-workflow';

	/**
	 * Needs Review folder slug.
	 *
	 * @var string
	 */
	public const FOLDER_NEEDS_REVIEW = 'vmfa-needs-review';

	/**
	 * Approved folder slug.
	 *
	 * @var string
	 */
	public const FOLDER_APPROVED = 'vmfa-approved';

	/**
	 * Term meta key for system folder flag.
	 *
	 * @var string
	 */
	public const META_SYSTEM_FOLDER = 'vmfa_system_folder';

	/**
	 * Option key for workflow enabled state.
	 *
	 * @var string
	 */
	public const OPTION_WORKFLOW_ENABLED = 'vmfa_workflow_enabled';

	/**
	 * Option key for editor review access.
	 *
	 * @var string
	 */
	public const OPTION_EDITORS_CAN_REVIEW = 'vmfa_editors_can_review';

	/**
	 * Option key for custom approved folder.
	 *
	 * @var string
	 */
	public const OPTION_APPROVED_FOLDER = 'vmfa_approved_folder';

	/**
	 * Access checker instance.
	 *
	 * @var AccessChecker
	 */
	private AccessChecker $access_checker;

	/**
	 * VMF taxonomy name.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param AccessChecker $access_checker Access checker instance.
	 */
	public function __construct( AccessChecker $access_checker ) {
		$this->access_checker = $access_checker;
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
		// Protect system folders from deletion.
		add_filter( 'vmfo_can_delete_folder', [ $this, 'protect_system_folders' ], 10, 3 );

		// Protect system folders from renaming.
		add_filter( 'pre_update_term', [ $this, 'protect_system_folder_rename' ], 10, 2 );
	}

	/**
	 * Run on plugin activation.
	 *
	 * Creates workflow system folders.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Create instance to access methods.
		$instance = new self( new AccessChecker() );
		$instance->create_system_folders();

		// Enable workflow by default.
		update_option( self::OPTION_WORKFLOW_ENABLED, true );
	}

	/**
	 * Create system workflow folders.
	 *
	 * @return bool True if all folders were created successfully.
	 */
	public function create_system_folders(): bool {
		// Create parent Workflow folder.
		$workflow_folder = $this->ensure_folder(
			self::FOLDER_WORKFLOW,
			__( 'Workflow', 'vmfa-editorial-workflow' ),
			0
		);

		if ( ! $workflow_folder ) {
			return false;
		}

		// Create Needs Review folder.
		$needs_review = $this->ensure_folder(
			self::FOLDER_NEEDS_REVIEW,
			__( 'Needs Review', 'vmfa-editorial-workflow' ),
			$workflow_folder
		);

		// Create Approved folder.
		$approved = $this->ensure_folder(
			self::FOLDER_APPROVED,
			__( 'Approved', 'vmfa-editorial-workflow' ),
			$workflow_folder
		);

		return ( false !== $needs_review && false !== $approved );
	}

	/**
	 * Ensure a folder exists, creating if needed.
	 *
	 * @param string $slug   Folder slug.
	 * @param string $name   Folder display name.
	 * @param int    $parent Parent term ID.
	 * @return int|false Term ID or false on failure.
	 */
	private function ensure_folder( string $slug, string $name, int $parent ) {
		// Check if folder already exists.
		$existing = get_term_by( 'slug', $slug, $this->taxonomy );

		if ( $existing instanceof WP_Term ) {
			// Mark as system folder if not already.
			update_term_meta( $existing->term_id, self::META_SYSTEM_FOLDER, true );
			return $existing->term_id;
		}

		// Create the folder.
		$result = wp_insert_term(
			$name,
			$this->taxonomy,
			[
				'slug'   => $slug,
				'parent' => $parent,
			]
		);

		if ( is_wp_error( $result ) ) {
			return false;
		}

		$term_id = $result['term_id'];

		// Mark as system folder.
		update_term_meta( $term_id, self::META_SYSTEM_FOLDER, true );

		return $term_id;
	}

	/**
	 * Protect system folders from deletion.
	 *
	 * @param bool|WP_Error $can_delete Whether folder can be deleted.
	 * @param int           $folder_id  Folder term ID.
	 * @param WP_Term       $term       Term object.
	 * @return bool|WP_Error
	 */
	public function protect_system_folders( $can_delete, int $folder_id, WP_Term $term ) {
		if ( $this->is_system_folder( $folder_id ) ) {
			return new WP_Error(
				'vmfa_system_folder',
				__( 'System workflow folders cannot be deleted.', 'vmfa-editorial-workflow' ),
				[ 'status' => 403 ]
			);
		}

		return $can_delete;
	}

	/**
	 * Protect system folders from renaming.
	 *
	 * @param mixed $value   The new term data.
	 * @param int   $term_id Term ID.
	 * @return mixed
	 */
	public function protect_system_folder_rename( $value, int $term_id ) {
		if ( $this->is_system_folder( $term_id ) ) {
			return new WP_Error(
				'vmfa_system_folder',
				__( 'System workflow folders cannot be renamed.', 'vmfa-editorial-workflow' )
			);
		}

		return $value;
	}

	/**
	 * Check if a folder is a system folder.
	 *
	 * @param int $folder_id Folder term ID.
	 * @return bool
	 */
	public function is_system_folder( int $folder_id ): bool {
		return (bool) get_term_meta( $folder_id, self::META_SYSTEM_FOLDER, true );
	}

	/**
	 * Get the Needs Review folder ID.
	 *
	 * @return int|null Folder term ID or null if not found.
	 */
	public function get_needs_review_folder(): ?int {
		$term = get_term_by( 'slug', self::FOLDER_NEEDS_REVIEW, $this->taxonomy );

		return ( $term instanceof WP_Term ) ? $term->term_id : null;
	}

	/**
	 * Get the Approved folder ID.
	 *
	 * Returns custom folder if set, otherwise falls back to system folder.
	 *
	 * @return int|null Folder term ID or null if not found.
	 */
	public function get_approved_folder(): ?int {
		// Check for custom approved folder first.
		$custom_folder = (int) get_option( self::OPTION_APPROVED_FOLDER, 0 );
		if ( $custom_folder > 0 ) {
			// Verify the folder still exists.
			$term = get_term( $custom_folder, $this->taxonomy );
			if ( $term instanceof WP_Term ) {
				return $term->term_id;
			}
		}

		// Fall back to system folder.
		$term = get_term_by( 'slug', self::FOLDER_APPROVED, $this->taxonomy );

		return ( $term instanceof WP_Term ) ? $term->term_id : null;
	}

	/**
	 * Set the custom approved folder.
	 *
	 * @param int $folder_id Folder term ID, or 0 to use default.
	 * @return bool True on success.
	 */
	public function set_approved_folder( int $folder_id ): bool {
		if ( $folder_id <= 0 ) {
			return delete_option( self::OPTION_APPROVED_FOLDER );
		}

		return (bool) update_option( self::OPTION_APPROVED_FOLDER, $folder_id );
	}

	/**
	 * Get the custom approved folder ID (not falling back to system).
	 *
	 * @return int Custom folder ID or 0 if using default.
	 */
	public function get_custom_approved_folder(): int {
		return (int) get_option( self::OPTION_APPROVED_FOLDER, 0 );
	}

	/**
	 * Get the parent Workflow folder ID.
	 *
	 * @return int|null Folder term ID or null if not found.
	 */
	public function get_workflow_folder(): ?int {
		$term = get_term_by( 'slug', self::FOLDER_WORKFLOW, $this->taxonomy );

		return ( $term instanceof WP_Term ) ? $term->term_id : null;
	}

	/**
	 * Mark an attachment as needing review.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success.
	 */
	public function mark_needs_review( int $attachment_id ): bool {
		$folder_id = $this->get_needs_review_folder();

		if ( ! $folder_id ) {
			return false;
		}

		$result = wp_set_object_terms( $attachment_id, $folder_id, $this->taxonomy );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		/**
		 * Fires after an attachment is marked for review.
		 *
		 * @param int $attachment_id Attachment ID.
		 * @param int $folder_id     Needs Review folder ID.
		 */
		do_action( 'vmfa_marked_needs_review', $attachment_id, $folder_id );

		return true;
	}

	/**
	 * Mark an attachment as approved.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success.
	 */
	public function mark_approved( int $attachment_id ): bool {
		$folder_id = $this->get_approved_folder();

		if ( ! $folder_id ) {
			return false;
		}

		$result = wp_set_object_terms( $attachment_id, $folder_id, $this->taxonomy );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		/**
		 * Fires after an attachment is approved.
		 *
		 * @param int $attachment_id Attachment ID.
		 * @param int $folder_id     Approved folder ID.
		 */
		do_action( 'vmfa_approved', $attachment_id, $folder_id );

		return true;
	}

	/**
	 * Get all attachments needing review.
	 *
	 * @param array $args Optional query arguments.
	 * @return array Array of attachment IDs.
	 */
	public function get_items_needing_review( array $args = [] ): array {
		$folder_id = $this->get_needs_review_folder();

		if ( ! $folder_id ) {
			return [];
		}

		$defaults = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => $this->taxonomy,
					'terms'    => $folder_id,
					'field'    => 'term_id',
				],
			],
		];

		$query_args = wp_parse_args( $args, $defaults );
		$query      = new \WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Get count of items needing review.
	 *
	 * Uses transient for performance.
	 *
	 * @param bool $force Force refresh of cached count.
	 * @return int Count of items.
	 */
	public function get_review_count( bool $force = false ): int {
		$transient_key = 'vmfa_editorial_workflow_review_count';

		if ( ! $force ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		$count = count( $this->get_items_needing_review() );

		set_transient( $transient_key, $count, HOUR_IN_SECONDS );

		return $count;
	}

	/**
	 * Invalidate review count cache.
	 *
	 * @return void
	 */
	public function invalidate_review_count_cache(): void {
		delete_transient( 'vmfa_editorial_workflow_review_count' );
	}

	/**
	 * Check if workflow feature is enabled.
	 *
	 * Workflow is always enabled when the plugin is active.
	 *
	 * @return bool Always true.
	 */
	public function is_workflow_enabled(): bool {
		return true;
	}

	/**
	 * Enable or disable workflow feature.
	 *
	 * @deprecated Workflow is now always enabled when plugin is active.
	 *
	 * @param bool $enabled Whether to enable workflow.
	 * @return bool True on success.
	 */
	public function set_workflow_enabled( bool $enabled ): bool {
		// No-op: workflow is always enabled.
		return true;
	}

	/**
	 * Check if editors can access the review page.
	 *
	 * @return bool True if editors can review (default true).
	 */
	public function editors_can_review(): bool {
		$value = get_option( self::OPTION_EDITORS_CAN_REVIEW, true );

		// Handle string '0' or '1' from database.
		if ( '0' === $value || '' === $value ) {
			return false;
		}

		return (bool) $value;
	}

	/**
	 * Set whether editors can access the review page.
	 *
	 * @param bool $can_review Whether editors can review.
	 * @return bool True on success.
	 */
	public function set_editors_can_review( bool $can_review ): bool {
		return update_option( self::OPTION_EDITORS_CAN_REVIEW, $can_review ? '1' : '0' );
	}
}
