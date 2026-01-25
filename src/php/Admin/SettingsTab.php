<?php
/**
 * Settings Tab Admin.
 *
 * Registers settings tab in VMF settings for Editorial Workflow configuration.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Admin;

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Settings Tab class.
 *
 * Adds a settings tab to VMF settings page.
 */
class SettingsTab {

	/**
	 * Tab ID.
	 *
	 * @var string
	 */
	public const TAB_ID = 'editorial-workflow';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Register tab with VMF settings.
		add_filter( 'vmfo_settings_tabs', [ $this, 'register_tab' ] );

		// Enqueue settings assets.
		add_action( 'vmfo_settings_enqueue_scripts', [ $this, 'enqueue_assets' ], 10, 2 );
	}

	/**
	 * Register settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function register_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = [
			'title'    => __( 'Editorial Workflow', 'vmfa-editorial-workflow' ),
			'callback' => [ $this, 'render_tab' ],
		];

		return $tabs;
	}

	/**
	 * Enqueue assets for settings tab.
	 *
	 * @param string $active_tab    Currently active tab.
	 * @param string $active_subtab Currently active subtab.
	 * @return void
	 */
	public function enqueue_assets( string $active_tab, string $active_subtab ): void {
		if ( self::TAB_ID !== $active_tab ) {
			return;
		}

		$asset_file = VMFA_EDITORIAL_WORKFLOW_PATH . 'build/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ],
			'version'      => VMFA_EDITORIAL_WORKFLOW_VERSION,
		];

		wp_enqueue_style(
			'vmfa-settings',
			VMFA_EDITORIAL_WORKFLOW_URL . 'build/settings.css',
			[ 'wp-components' ],
			$asset[ 'version' ]
		);

		wp_enqueue_script(
			'vmfa-settings',
			VMFA_EDITORIAL_WORKFLOW_URL . 'build/settings.js',
			$asset[ 'dependencies' ],
			$asset[ 'version' ],
			true
		);

		wp_set_script_translations(
			'vmfa-settings',
			'vmfa-editorial-workflow',
			VMFA_EDITORIAL_WORKFLOW_PATH . 'languages'
		);

		wp_localize_script(
			'vmfa-settings',
			'vmfaSettings',
			[
				'restUrl' => rest_url( 'vmfa-editorial/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'roles'   => $this->get_editable_roles(),
				'folders' => $this->get_folders(),
				'actions' => $this->get_actions(),
				'i18n'    => [
					'title'           => __( 'Editorial Workflow Settings', 'vmfa-editorial-workflow' ),
					'permissions'     => __( 'Folder Permissions', 'vmfa-editorial-workflow' ),
					'permissionsDesc' => __( 'Configure which roles can access each folder.', 'vmfa-editorial-workflow' ),
					'inbox'           => __( 'Inbox Mapping', 'vmfa-editorial-workflow' ),
					'inboxDesc'       => __( 'Set the default upload folder for each role.', 'vmfa-editorial-workflow' ),
					'workflow'        => __( 'Workflow Settings', 'vmfa-editorial-workflow' ),
					'workflowDesc'    => __( 'Configure workflow states and behavior.', 'vmfa-editorial-workflow' ),
					'enableWorkflow'  => __( 'Enable workflow folders', 'vmfa-editorial-workflow' ),
					'save'            => __( 'Save Changes', 'vmfa-editorial-workflow' ),
					'saving'          => __( 'Saving…', 'vmfa-editorial-workflow' ),
					'saved'           => __( 'Settings saved.', 'vmfa-editorial-workflow' ),
					'error'           => __( 'Error saving settings.', 'vmfa-editorial-workflow' ),
					'selectFolder'    => __( 'Select folder…', 'vmfa-editorial-workflow' ),
					'noInbox'         => __( 'No inbox (use default)', 'vmfa-editorial-workflow' ),
					'view'            => __( 'View', 'vmfa-editorial-workflow' ),
					'move'            => __( 'Move to', 'vmfa-editorial-workflow' ),
					'upload'          => __( 'Upload to', 'vmfa-editorial-workflow' ),
					'remove'          => __( 'Remove from', 'vmfa-editorial-workflow' ),
				],
			]
		);
	}

	/**
	 * Render settings tab content.
	 *
	 * @param string $active_tab    Currently active tab.
	 * @param string $active_subtab Currently active subtab.
	 * @return void
	 */
	public function render_tab( string $active_tab, string $active_subtab ): void {
		?>
		<div id="vmfa-settings-root">
			<p class="description">
				<?php esc_html_e( 'Loading settings…', 'vmfa-editorial-workflow' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get editable roles for settings.
	 *
	 * @return array Role data.
	 */
	private function get_editable_roles(): array {
		$roles    = [];
		$wp_roles = wp_roles();

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			// Skip administrator - they always have full access.
			if ( 'administrator' === $role_key ) {
				continue;
			}

			// Only include roles that can upload files.
			if ( ! isset( $role_data[ 'capabilities' ][ 'upload_files' ] ) || ! $role_data[ 'capabilities' ][ 'upload_files' ] ) {
				continue;
			}

			$roles[] = [
				'key'  => $role_key,
				'name' => translate_user_role( $role_data[ 'name' ] ),
			];
		}

		return $roles;
	}

	/**
	 * Get folders for settings.
	 *
	 * @return array Folder data.
	 */
	private function get_folders(): array {
		$taxonomy = defined( 'VirtualMediaFolders\Taxonomy::TAXONOMY' )
			? \VirtualMediaFolders\Taxonomy::TAXONOMY
			: 'vmfo_folder';

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$folders = [];
		foreach ( $terms as $term ) {
			$is_system = (bool) get_term_meta( $term->term_id, 'vmfa_system_folder', true );

			$folders[] = [
				'id'       => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => $term->parent,
				'isSystem' => $is_system,
			];
		}

		return $folders;
	}

	/**
	 * Get available actions.
	 *
	 * @return array Action definitions.
	 */
	private function get_actions(): array {
		return [
			[
				'key'   => 'view',
				'label' => __( 'View', 'vmfa-editorial-workflow' ),
			],
			[
				'key'   => 'move',
				'label' => __( 'Move to', 'vmfa-editorial-workflow' ),
			],
			[
				'key'   => 'upload',
				'label' => __( 'Upload to', 'vmfa-editorial-workflow' ),
			],
			[
				'key'   => 'remove',
				'label' => __( 'Remove from', 'vmfa-editorial-workflow' ),
			],
		];
	}
}
