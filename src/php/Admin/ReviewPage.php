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
			'upload_files',
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
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'vmfa-review',
			'vmfaReview',
			[
				'nonce'           => wp_create_nonce( 'vmfa_review_actions' ),
				'needsReviewId'   => $this->workflow_state->get_needs_review_folder(),
				'approvedId'      => $this->workflow_state->get_approved_folder(),
				'allowedFolders'  => $this->get_allowed_destination_folders(),
				'i18n'            => [
					'approve'       => __( 'Approve', 'vmfa-editorial-workflow' ),
					'assignTo'      => __( 'Assign to…', 'vmfa-editorial-workflow' ),
					'selectItems'   => __( 'Select items to perform bulk actions.', 'vmfa-editorial-workflow' ),
					'confirmApprove' => __( 'Approve selected items?', 'vmfa-editorial-workflow' ),
					'success'       => __( 'Action completed successfully.', 'vmfa-editorial-workflow' ),
					'error'         => __( 'An error occurred.', 'vmfa-editorial-workflow' ),
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
			'paged'          => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
		] );

		$total_items = $this->workflow_state->get_review_count( true );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Media Review', 'vmfa-editorial-workflow' ); ?>
			</h1>

			<?php if ( $total_items > 0 ) : ?>
				<span class="title-count theme-count"><?php echo esc_html( (string) $total_items ); ?></span>
			<?php endif; ?>

			<hr class="wp-header-end">

			<?php if ( empty( $items ) ) : ?>
				<div class="vmfa-review-empty">
					<p><?php esc_html_e( 'No media items need review.', 'vmfa-editorial-workflow' ); ?></p>
				</div>
			<?php else : ?>
				<?php $this->render_bulk_actions(); ?>
				<?php $this->render_media_grid( $items ); ?>
				<?php $this->render_pagination( $total_items ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render bulk actions bar.
	 *
	 * @return void
	 */
	private function render_bulk_actions(): void {
		$allowed_folders = $this->get_allowed_destination_folders();
		?>
		<div class="vmfa-review-actions">
			<label>
				<input type="checkbox" id="vmfa-select-all" />
				<?php esc_html_e( 'Select All', 'vmfa-editorial-workflow' ); ?>
			</label>

			<div class="vmfa-bulk-buttons">
				<button type="button" class="button" id="vmfa-bulk-approve" disabled>
					<?php esc_html_e( 'Approve Selected', 'vmfa-editorial-workflow' ); ?>
				</button>

				<?php if ( ! empty( $allowed_folders ) ) : ?>
					<select id="vmfa-assign-folder" disabled>
						<option value=""><?php esc_html_e( 'Assign to folder…', 'vmfa-editorial-workflow' ); ?></option>
						<?php foreach ( $allowed_folders as $folder ) : ?>
							<option value="<?php echo esc_attr( (string) $folder['id'] ); ?>">
								<?php echo esc_html( $folder['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="vmfa-bulk-assign" disabled>
						<?php esc_html_e( 'Assign Selected', 'vmfa-editorial-workflow' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<span class="vmfa-selection-count"></span>
		</div>
		<?php
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
	 * Render single media item.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private function render_media_item( int $attachment_id ): void {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return;
		}

		$thumbnail = wp_get_attachment_image( $attachment_id, 'thumbnail', true );
		$title     = get_the_title( $attachment_id );
		$edit_link = get_edit_post_link( $attachment_id );
		$author    = get_the_author_meta( 'display_name', $attachment->post_author );
		$date      = get_the_date( '', $attachment );

		?>
		<li class="vmfa-review-item" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
			<div class="vmfa-item-checkbox">
				<input type="checkbox" name="vmfa-items[]" value="<?php echo esc_attr( (string) $attachment_id ); ?>" />
			</div>
			<div class="vmfa-item-thumbnail">
				<?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="vmfa-item-details">
				<strong class="vmfa-item-title">
					<?php if ( $edit_link ) : ?>
						<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</strong>
				<span class="vmfa-item-meta">
					<?php
					printf(
						/* translators: 1: author name, 2: date */
						esc_html__( 'Uploaded by %1$s on %2$s', 'vmfa-editorial-workflow' ),
						esc_html( $author ),
						esc_html( $date )
					);
					?>
				</span>
			</div>
			<div class="vmfa-item-actions">
				<button type="button" class="button button-primary vmfa-approve-single" data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
					<?php esc_html_e( 'Approve', 'vmfa-editorial-workflow' ); ?>
				</button>
			</div>
		</li>
		<?php
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
		$current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

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
			echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
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
	 * @return array Array of folder data [ 'id' => int, 'name' => string ].
	 */
	private function get_allowed_destination_folders(): array {
		$user_id = get_current_user_id();
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
					'id'   => $term->term_id,
					'name' => $term->name,
				];
			}
		}

		return $folders;
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

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];

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

		$ids       = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : [];
		$folder_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;

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
