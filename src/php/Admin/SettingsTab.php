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

defined( 'ABSPATH' ) || exit;

use VirtualMediaFolders\Addon\AbstractSettingsTab;

/**
 * Settings Tab class.
 *
 * Adds a settings tab to VMF settings page.
 */
class SettingsTab extends AbstractSettingsTab {

	/** @inheritDoc */
	protected function get_tab_slug(): string {
		return 'editorial-workflow';
	}

	/** @inheritDoc */
	protected function get_tab_label(): string {
		return __( 'Editorial Workflow', 'vmfa-editorial-workflow' );
	}

	/** @inheritDoc */
	protected function get_text_domain(): string {
		return 'vmfa-editorial-workflow';
	}

	/** @inheritDoc */
	protected function get_build_path(): string {
		return VMFA_EDITORIAL_WORKFLOW_PATH . 'build/';
	}

	/** @inheritDoc */
	protected function get_build_url(): string {
		return VMFA_EDITORIAL_WORKFLOW_URL . 'build/';
	}

	/** @inheritDoc */
	protected function get_languages_path(): string {
		return VMFA_EDITORIAL_WORKFLOW_PATH . 'languages';
	}

	/** @inheritDoc */
	protected function get_plugin_version(): string {
		return VMFA_EDITORIAL_WORKFLOW_VERSION;
	}

	/** @inheritDoc */
	protected function get_localized_name(): string {
		return 'vmfaSettings';
	}

	/** @inheritDoc */
	protected function get_asset_entry(): string {
		return 'settings';
	}

	/** @inheritDoc */
	protected function get_app_container_id(): string {
		return 'vmfa-settings-root';
	}

	/** @inheritDoc */
	protected function get_localized_data(): array {
		return [
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
				'delete'          => __( 'Delete', 'vmfa-editorial-workflow' ),
			],
		];
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
		<div id="<?php echo esc_attr( $this->get_app_container_id() ); ?>">
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
			if ( 'administrator' === $role_key ) {
				continue;
			}

			if ( ! isset( $role_data['capabilities']['upload_files'] ) || ! $role_data['capabilities']['upload_files'] ) {
				continue;
			}

			$roles[] = [
				'key'  => $role_key,
				'name' => translate_user_role( $role_data['name'] ),
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
				'key'   => 'delete',
				'label' => __( 'Delete', 'vmfa-editorial-workflow' ),
			],
		];
	}
}
