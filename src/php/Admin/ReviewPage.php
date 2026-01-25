<?php
/**
 * Review Page Admin.
 *
 * Admin page for reviewing media items in the workflow.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Admin;

use VmfaEditorialWorkflow\Services\AccessChecker;
use VmfaEditorialWorkflow\WorkflowState;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Review Page class.
 *
 * Provides an admin submenu for reviewing media needing attention.
 */
class ReviewPage {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'vmfa-review';

	/**
	 * Access checker instance.
	 *
	 * @var AccessChecker
	 */
	private AccessChecker $access_checker;

	/**
	 * Workflow state instance.
	 *
	 * @var WorkflowState
	 */
	private WorkflowState $workflow_state;

	/**
	 * Constructor.
	 *
	 * @param AccessChecker $access_checker Access checker instance.
	 * @param WorkflowState $workflow_state Workflow state instance.
	 */
	public function __construct( AccessChecker $access_checker, WorkflowState $workflow_state ) {
		$this->access_checker = $access_checker;
		$this->workflow_state = $workflow_state;

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_vmfa_bulk_approve', [ $this, 'handle_bulk_approve' ] );
		add_action( 'wp_ajax_vmfa_bulk_assign', [ $this, 'handle_bulk_assign' ] );

		// Update menu badge when folder assignments change.
		add_action( 'vmfo_folder_assigned', [ $this, 'maybe_invalidate_cache' ], 10, 2 );
		add_action( 'vmfa_marked_needs_review', [ $this, 'invalidate_cache' ] );
		add_action( 'vmfa_approved', [ $this, 'invalidate_cache' ] );
	}

	/**
	 * Add menu page under Media.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		// Only show if workflow is enabled.
		if ( ! $this->workflow_state->is_workflow_enabled() ) {
			return;
		}

		// Determine capability required for review access.
		// Administrators always have access. Editors only if setting allows.
		$capability = 'manage_options'; // Admin only by default.

		if ( $this->workflow_state->editors_can_review() ) {
			$capability = 'edit_others_posts'; // Editors and Admins.
		}

		$count      = $this->workflow_state->get_review_count();
		$menu_title = __( 'Review', 'vmfa-editorial-workflow' );

		// Add badge if items need review.
		if ( $count > 0 ) {
			$menu_title .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
				$count
			);
		}

		add_media_page(
			__( 'Media Review', 'vmfa-editorial-workflow' ),
			$menu_title,
			$capability,
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue assets for review page.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'media_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();

		$asset_file = VMFA_EDITORIAL_WORKFLOW_PATH . 'build/review.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => VMFA_EDITORIAL_WORKFLOW_VERSION,
		];

		wp_enqueue_script(
			'vmfa-review',
			VMFA_EDITORIAL_WORKFLOW_URL . 'build/review.js',
			$asset[ 'dependencies' ],
			$asset[ 'version' ],
			true
		);

		wp_enqueue_style(
			'vmfa-review',
			VMFA_EDITORIAL_WORKFLOW_URL . 'build/review.css',
			[],
			$asset[ 'version' ]
		);

		// Enqueue dashicons for icons.
		wp_enqueue_style( 'dashicons' );

		wp_localize_script(
			'vmfa-review',
			'vmfaReview',
			[
				'nonce'          => wp_create_nonce( 'vmfa_review_actions' ),
				'needsReviewId'  => $this->workflow_state->get_needs_review_folder(),
				'approvedId'     => $this->workflow_state->get_approved_folder(),
				'allowedFolders' => $this->get_allowed_destination_folders(),
				'i18n'           => [
					'approve'        => __( 'Approve', 'vmfa-editorial-workflow' ),
					'assignTo'       => __( 'Assign to…', 'vmfa-editorial-workflow' ),
					'selectItems'    => __( 'Select items to perform bulk actions.', 'vmfa-editorial-workflow' ),
					'confirmApprove' => __( 'Approve selected items?', 'vmfa-editorial-workflow' ),
					'confirmMove'    => __( 'Move selected items to this folder?', 'vmfa-editorial-workflow' ),
					'success'        => __( 'Action completed successfully.', 'vmfa-editorial-workflow' ),
					'error'          => __( 'An error occurred.', 'vmfa-editorial-workflow' ),
					'saving'         => __( 'Processing…', 'vmfa-editorial-workflow' ),
				],
			]
		);
	}

	/**
	 * Render the review page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$needs_review_folder = $this->workflow_state->get_needs_review_folder();

		if ( ! $needs_review_folder ) {
			$this->render_setup_notice();
			return;
		}

		$items = $this->workflow_state->get_items_needing_review( [
			'posts_per_page' => 50,
			'paged'          => isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : 1,
		] );

		$total_items = $this->workflow_state->get_review_count( true );

		?>
		<div class="wrap">
			<div class="vmfa-review-header">
				<h1>
					<?php esc_html_e( 'Media Review', 'vmfa-editorial-workflow' ); ?>
					<?php if ( $total_items > 0 ) : ?>
						<span class="vmfa-review-count-badge"><?php echo esc_html( (string) $total_items ); ?></span>
					<?php endif; ?>
				</h1>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<?php $this->render_empty_state(); ?>
			<?php else : ?>
				<?php $this->render_toolbar(); ?>
				<?php $this->render_media_grid( $items ); ?>
				<?php $this->render_pagination( $total_items ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the toolbar with bulk actions.
	 *
	 * @return void
	 */
	private function render_toolbar(): void {
		$allowed_folders      = $this->get_allowed_destination_folders();
		$hierarchical_folders = $this->build_folder_hierarchy( $allowed_folders );
		$approved_folder      = $this->workflow_state->get_approved_folder();
		$approved_term        = $approved_folder ? get_term( $approved_folder ) : null;
		$approved_name        = $approved_term instanceof \WP_Term ? $approved_term->name : __( 'Approved', 'vmfa-editorial-workflow' );
		?>
		<div class="vmfa-review-toolbar">
			<label class="vmfa-toolbar-select-all">
				<input type="checkbox" id="vmfa-select-all" />
				<?php esc_html_e( 'Select All', 'vmfa-editorial-workflow' ); ?>
			</label>

			<div class="vmfa-toolbar-actions">
				<select id="vmfa-destination-folder" disabled>
					<option value=""><?php esc_html_e( 'Select destination…', 'vmfa-editorial-workflow' ); ?></option>
					<option value="approve">✓
						<?php echo esc_html( sprintf( __( 'Approve → %s', 'vmfa-editorial-workflow' ), $approved_name ) ); ?>
					</option>
					<?php if ( ! empty( $hierarchical_folders ) ) : ?>
						<optgroup label="<?php esc_attr_e( 'Move to folder', 'vmfa-editorial-workflow' ); ?>">
							<?php foreach ( $hierarchical_folders as $folder ) : ?>
								<option value="<?php echo esc_attr( (string) $folder[ 'id' ] ); ?>">
									<?php echo esc_html( $folder[ 'name' ] ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
				</select>
				<button type="button" class="button button-primary" id="vmfa-bulk-action" disabled>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Apply', 'vmfa-editorial-workflow' ); ?>
				</button>
			</div>

			<div class="vmfa-toolbar-info">
				<span class="vmfa-selection-count"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render bulk actions bar.
	 *
	 * @deprecated Use render_toolbar() instead.
	 * @return void
	 */
	private function render_bulk_actions(): void {
		$this->render_toolbar();
	}

	/**
	 * Render media grid.
	 *
	 * @param array $items Array of attachment IDs.
	 * @return void
	 */
	private function render_media_grid( array $items ): void {
		?>
		<ul class="vmfa-review-grid">
			<?php foreach ( $items as $attachment_id ) : ?>
				<?php $this->render_media_item( (int) $attachment_id ); ?>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render single media item as a card.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_media_item( int $attachment_id ): void {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return;
		}

		$title      = get_the_title( $attachment_id );
		$edit_link  = get_edit_post_link( $attachment_id );
		$author     = get_the_author_meta( 'display_name', $attachment->post_author );
		$date       = get_the_date( '', $attachment );
		$mime_type  = get_post_mime_type( $attachment_id );
		$is_image   = strpos( $mime_type, 'image/' ) === 0;
		$full_url   = wp_get_attachment_url( $attachment_id );
		$medium_src = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$thumbnail  = $medium_src ? $medium_src[ 0 ] : '';

		// Get file size.
		$file_path = get_attached_file( $attachment_id );
		$file_size = $file_path && file_exists( $file_path ) ? size_format( filesize( $file_path ), 1 ) : '';

		?>
		<li class="vmfa-media-card" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
			<div class="vmfa-card-checkbox">
				<input type="checkbox" name="vmfa-items[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
			</div>

			<div class="vmfa-card-thumbnail" data-full="<?php echo esc_url( $full_url ); ?>"
				data-title="<?php echo esc_attr( $title ); ?>">
				<?php if ( $is_image && $thumbnail ) : ?>
					<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
					<div class="vmfa-card-preview-overlay">
						<span class="dashicons dashicons-visibility"></span>
					</div>
				<?php else : ?>
					<div class="vmfa-file-icon">
						<span class="dashicons <?php echo esc_attr( $this->get_mime_icon( $mime_type ) ); ?>"></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="vmfa-card-body">
				<h3 class="vmfa-card-title">
					<?php if ( $edit_link ) : ?>
						<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</h3>
				<div class="vmfa-card-meta">
					<span class="vmfa-card-meta-item">
						<span class="dashicons dashicons-admin-users"></span>
						<?php echo esc_html( $author ); ?>
					</span>
					<span class="vmfa-card-meta-item">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php echo esc_html( $date ); ?>
					</span>
					<?php if ( $file_size ) : ?>
						<span class="vmfa-card-meta-item">
							<span class="dashicons dashicons-media-default"></span>
							<?php echo esc_html( $file_size ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<div class="vmfa-card-actions">
				<button type="button" class="button button-primary vmfa-approve-single"
					data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Approve', 'vmfa-editorial-workflow' ); ?>
				</button>
				<?php if ( $edit_link ) : ?>
					<a href="<?php echo esc_url( $edit_link ); ?>" class="button">
						<span class="dashicons dashicons-edit"></span>
						<?php esc_html_e( 'Edit', 'vmfa-editorial-workflow' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/**
	 * Get appropriate dashicon class for MIME type.
	 *
	 * @param string $mime_type MIME type.
	 * @return string Dashicon class.
	 */
	private function get_mime_icon( string $mime_type ): string {
		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			return 'dashicons-format-image';
		}
		if ( strpos( $mime_type, 'video/' ) === 0 ) {
			return 'dashicons-format-video';
		}
		if ( strpos( $mime_type, 'audio/' ) === 0 ) {
			return 'dashicons-format-audio';
		}
		if ( strpos( $mime_type, 'application/pdf' ) === 0 ) {
			return 'dashicons-pdf';
		}
		if ( strpos( $mime_type, 'application/' ) === 0 ) {
			return 'dashicons-media-document';
		}
		return 'dashicons-media-default';
	}

	/**
	 * Render pagination.
	 *
	 * @param int $total_items Total number of items.
	 * @return void
	 */
	private function render_pagination( int $total_items ): void {
		$per_page    = 50;
		$total_pages = (int) ceil( $total_items / $per_page );
		$current     = isset( $_GET[ 'paged' ] ) ? absint( $_GET[ 'paged' ] ) : 1;

		if ( $total_pages <= 1 ) {
			return;
		}

		$page_links = paginate_links( [
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'total'     => $total_pages,
			'current'   => $current,
		] );

		if ( $page_links ) {
			echo '<div class="vmfa-review-pagination">' . $page_links . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render empty state when no items need review.
	 *
	 * @return void
	 */
	private function render_empty_state(): void {
		?>
		<div class="vmfa-review-empty">
			<span class="dashicons dashicons-yes-alt"></span>
			<h2><?php esc_html_e( 'All caught up!', 'vmfa-editorial-workflow' ); ?></h2>
			<p><?php esc_html_e( 'No media items are waiting for review. New uploads will appear here.', 'vmfa-editorial-workflow' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render setup notice when workflow folders don't exist.
	 *
	 * @return void
	 */
	private function render_setup_notice(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Media Review', 'vmfa-editorial-workflow' ); ?></h1>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'Workflow folders have not been set up. Please deactivate and reactivate the plugin.', 'vmfa-editorial-workflow' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get folders the current user can assign media to.
	 *
	 * @return array Array of folder data [ 'id' => int, 'name' => string, 'parent' => int ].
	 */
	private function get_allowed_destination_folders(): array {
		$user_id    = get_current_user_id();
		$folder_ids = $this->access_checker->get_allowed_folders( $user_id, AccessChecker::ACTION_MOVE );

		// Exclude workflow system folders from destinations.
		$system_folders = [
			$this->workflow_state->get_workflow_folder(),
			$this->workflow_state->get_needs_review_folder(),
			$this->workflow_state->get_approved_folder(),
		];

		$folder_ids = array_diff( $folder_ids, array_filter( $system_folders ) );

		$folders = [];
		foreach ( $folder_ids as $folder_id ) {
			$term = get_term( $folder_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$folders[] = [
					'id'     => $term->term_id,
					'name'   => $term->name,
					'parent' => $term->parent,
				];
			}
		}

		return $folders;
	}

	/**
	 * Build hierarchical folder options for select dropdown.
	 *
	 * @param array $folders    All folders.
	 * @param int   $parent_id  Parent ID to start from.
	 * @param int   $depth      Current depth level.
	 * @return array Flattened array with indented names.
	 */
	private function build_folder_hierarchy( array $folders, int $parent_id = 0, int $depth = 0 ): array {
		$result = [];
		$prefix = str_repeat( '— ', $depth );

		foreach ( $folders as $folder ) {
			if ( $folder[ 'parent' ] === $parent_id ) {
				$result[] = [
					'id'   => $folder[ 'id' ],
					'name' => $prefix . $folder[ 'name' ],
				];
				$result   = array_merge( $result, $this->build_folder_hierarchy( $folders, $folder[ 'id' ], $depth + 1 ) );
			}
		}

		return $result;
	}

	/**
	 * Handle bulk approve AJAX action.
	 *
	 * @return void
	 */
	public function handle_bulk_approve(): void {
		check_ajax_referer( 'vmfa_review_actions', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vmfa-editorial-workflow' ) ], 403 );
		}

		$ids = isset( $_POST[ 'ids' ] ) ? array_map( 'absint', (array) $_POST[ 'ids' ] ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No items selected.', 'vmfa-editorial-workflow' ) ], 400 );
		}

		$approved_folder = $this->workflow_state->get_approved_folder();
		if ( ! $approved_folder ) {
			wp_send_json_error( [ 'message' => __( 'Approved folder not found.', 'vmfa-editorial-workflow' ) ], 500 );
		}

		// Check permission to move to approved folder.
		if ( ! $this->access_checker->can_move_to_folder( $approved_folder ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vmfa-editorial-workflow' ) ], 403 );
		}

		$success = 0;
		$failed  = 0;

		foreach ( $ids as $attachment_id ) {
			if ( $this->workflow_state->mark_approved( $attachment_id ) ) {
				++$success;
			} else {
				++$failed;
			}
		}

		$this->invalidate_cache();

		wp_send_json_success( [
			'success' => $success,
			'failed'  => $failed,
			'message' => sprintf(
				/* translators: %d: number of items approved */
				__( '%d items approved.', 'vmfa-editorial-workflow' ),
				$success
			),
		] );
	}

	/**
	 * Handle bulk assign AJAX action.
	 *
	 * @return void
	 */
	public function handle_bulk_assign(): void {
		check_ajax_referer( 'vmfa_review_actions', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vmfa-editorial-workflow' ) ], 403 );
		}

		$ids       = isset( $_POST[ 'ids' ] ) ? array_map( 'absint', (array) $_POST[ 'ids' ] ) : [];
		$folder_id = isset( $_POST[ 'folder_id' ] ) ? absint( $_POST[ 'folder_id' ] ) : 0;

		if ( empty( $ids ) ) {
			wp_send_json_error( [ 'message' => __( 'No items selected.', 'vmfa-editorial-workflow' ) ], 400 );
		}

		if ( ! $folder_id ) {
			wp_send_json_error( [ 'message' => __( 'No folder selected.', 'vmfa-editorial-workflow' ) ], 400 );
		}

		// Check permission.
		if ( ! $this->access_checker->can_move_to_folder( $folder_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied for this folder.', 'vmfa-editorial-workflow' ) ], 403 );
		}

		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		$success = 0;
		$failed  = 0;

		foreach ( $ids as $attachment_id ) {
			$result = wp_set_object_terms( $attachment_id, $folder_id, $taxonomy );
			if ( ! is_wp_error( $result ) ) {
				++$success;
			} else {
				++$failed;
			}
		}

		$this->invalidate_cache();

		wp_send_json_success( [
			'success' => $success,
			'failed'  => $failed,
			'message' => sprintf(
				/* translators: %d: number of items assigned */
				__( '%d items assigned.', 'vmfa-editorial-workflow' ),
				$success
			),
		] );
	}

	/**
	 * Maybe invalidate cache based on folder assignment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $folder_id     Folder ID.
	 * @return void
	 */
	public function maybe_invalidate_cache( int $attachment_id, int $folder_id ): void {
		$review_folder = $this->workflow_state->get_needs_review_folder();
		if ( $folder_id === $review_folder ) {
			$this->invalidate_cache();
		}
	}

	/**
	 * Invalidate review count cache.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		$this->workflow_state->invalidate_review_count_cache();
	}
}
