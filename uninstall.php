<?php
/**
 * Uninstall handler for Virtual Media Folders – Editorial Workflow.
 *
 * Removes all plugin-specific options and term meta from the database.
 *
 * @package VmfaEditorialWorkflow
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'vmfa_workflow_enabled' );
delete_option( 'vmfa_editors_can_review' );
delete_option( 'vmfa_approved_folder' );
delete_option( 'vmfa_inbox_map' );
delete_option( 'vmfa_needs_review_folder' );

// Remove per-folder term meta (role permissions and system folder flags).
global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'vmfa_access_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
		'vmfa_system_folder'
	)
);
