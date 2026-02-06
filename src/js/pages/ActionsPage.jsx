/**
 * Actions Page Component for Editorial Workflow.
 *
 * Contains action buttons - batch approve, refresh stats.
 * No settings here - just actions.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useCallback } from '@wordpress/element';
import { Button, Card, CardBody, CardHeader, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Actions Page component.
 *
 * @return {JSX.Element} The actions page content.
 */
export function ActionsPage() {
	const [ notice, setNotice ] = useState( null );
	const [ isRefreshing, setIsRefreshing ] = useState( false );

	const reviewPageUrl = window.vmfaSettings?.reviewPageUrl || '';

	/**
	 * Handle refresh stats.
	 */
	const handleRefreshStats = useCallback( async () => {
		setIsRefreshing( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/vmfa-editorial/v1/stats',
				method: 'GET',
			} );
			setNotice( {
				type: 'success',
				message: __( 'Statistics refreshed.', 'vmfa-editorial-workflow' ),
			} );
			// Dispatch custom event for other components to refresh.
			window.dispatchEvent(
				new CustomEvent( 'vmfa-stats-refresh', {
					detail: { addonKey: 'editorial-workflow' },
				} )
			);
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message:
					err.message ||
					__( 'Failed to refresh statistics.', 'vmfa-editorial-workflow' ),
			} );
		} finally {
			setIsRefreshing( false );
		}
	}, [] );

	return (
		<>
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
					className="vmfa-notice"
				>
					{ notice.message }
				</Notice>
			) }

			{ /* Primary Actions Card */ }
			<Card className="vmfo-actions-card">
				<CardHeader>
					<h3>{ __( 'Review Actions', 'vmfa-editorial-workflow' ) }</h3>
				</CardHeader>
				<CardBody>
					<p className="vmfo-description">
						{ __(
							'Review and approve media from the dedicated Review page. Individual approval or batch operations are available there.',
							'vmfa-editorial-workflow'
						) }
					</p>
					<div className="vmfo-actions-buttons">
						{ reviewPageUrl ? (
							<Button variant="primary" href={ reviewPageUrl }>
								{ __( 'Go to Review Page', 'vmfa-editorial-workflow' ) }
							</Button>
						) : (
							<Button
								variant="primary"
								href="upload.php?page=vmfa-review"
							>
								{ __( 'Go to Review Page', 'vmfa-editorial-workflow' ) }
							</Button>
						) }
					</div>
				</CardBody>
			</Card>

			{ /* Maintenance Actions Card */ }
			<Card className="vmfo-actions-card">
				<CardHeader>
					<h3>
						{ __( 'Maintenance Actions', 'vmfa-editorial-workflow' ) }
					</h3>
				</CardHeader>
				<CardBody>
					<p className="vmfo-description">
						{ __(
							'Refresh workflow statistics to ensure counts are accurate.',
							'vmfa-editorial-workflow'
						) }
					</p>
					<div className="vmfo-actions-buttons">
						<Button
							variant="secondary"
							onClick={ handleRefreshStats }
							isBusy={ isRefreshing }
							disabled={ isRefreshing }
						>
							{ isRefreshing
								? __( 'Refreshing…', 'vmfa-editorial-workflow' )
								: __( 'Refresh Statistics', 'vmfa-editorial-workflow' ) }
						</Button>
					</div>
				</CardBody>
			</Card>

			{ /* Info Card */ }
			<Card className="vmfo-actions-card vmfo-info-card">
				<CardHeader>
					<h3>
						{ __( 'About Review Workflow', 'vmfa-editorial-workflow' ) }
					</h3>
				</CardHeader>
				<CardBody>
					<ol>
						<li>
							{ __(
								'Non-admin users upload media, which lands in "Needs Review"',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Editors/Admins visit the Review page to see pending items',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Approve individual items or use batch approve',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Approved media moves to the configured destination folder',
								'vmfa-editorial-workflow'
							) }
						</li>
					</ol>
				</CardBody>
			</Card>
		</>
	);
}

export default ActionsPage;
