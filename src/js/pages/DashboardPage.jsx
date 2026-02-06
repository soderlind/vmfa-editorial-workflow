/**
 * Dashboard Page Component for Editorial Workflow.
 *
 * Displays current workflow status, pending items, and action buttons.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Dashboard Page component.
 *
 * @return {JSX.Element} The dashboard page content.
 */
export function DashboardPage() {
	const [ stats, setStats ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );
	const [ isRefreshing, setIsRefreshing ] = useState( false );

	/**
	 * Fetch workflow statistics.
	 */
	const fetchStats = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await apiFetch( {
				path: '/vmfa-editorial/v1/stats',
				method: 'GET',
			} );
			setStats( response );
		} catch ( err ) {
			// Ignore fetch errors.
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStats();
	}, [ fetchStats ] );

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
			// Also refresh local stats.
			fetchStats();
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
	}, [ fetchStats ] );

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

			{ /* Workflow Status Card */ }
			<Card className="vmfo-dashboard-card">
				<CardHeader>
					<h3>{ __( 'Workflow Status', 'vmfa-editorial-workflow' ) }</h3>
				</CardHeader>
				<CardBody>
					{ isLoading ? (
						<div className="vmfo-status-loading">
							<Spinner />
							<p>
								{ __(
									'Loading workflow status…',
									'vmfa-editorial-workflow'
								) }
							</p>
						</div>
					) : (
						<div className="vmfo-status-content">
							{ stats?.needsReview > 0 ? (
								<>
									<div className="vmfo-status-alert vmfo-status-alert--warning">
										<span className="vmfo-status-count">
											{ stats.needsReview }
										</span>
										<span className="vmfo-status-label">
											{ __(
												'media items awaiting review',
												'vmfa-editorial-workflow'
											) }
										</span>
									</div>
									{ reviewPageUrl && (
										<Button
											variant="primary"
											href={ reviewPageUrl }
										>
											{ __( 'Go to Review Page', 'vmfa-editorial-workflow' ) }
										</Button>
									) }
								</>
							) : (
								<div className="vmfo-status-alert vmfo-status-alert--success">
									<span className="vmfo-status-label">
										{ __(
											'No media items pending review. All caught up!',
											'vmfa-editorial-workflow'
										) }
									</span>
								</div>
							) }
						</div>
					) }
				</CardBody>
			</Card>

			{ /* Recent Activity Card */ }
			<Card className="vmfo-dashboard-card">
				<CardHeader>
					<h3>
						{ __( 'Workflow Summary', 'vmfa-editorial-workflow' ) }
					</h3>
				</CardHeader>
				<CardBody>
					{ isLoading ? (
						<Spinner />
					) : (
						<div className="vmfo-summary-grid">
							<div className="vmfo-summary-item">
								<span className="vmfo-summary-value">
									{ stats?.totalMedia ?? '—' }
								</span>
								<span className="vmfo-summary-label">
									{ __( 'Total Media', 'vmfa-editorial-workflow' ) }
								</span>
							</div>
							<div className="vmfo-summary-item">
								<span className="vmfo-summary-value vmfo-summary-value--warning">
									{ stats?.needsReview ?? '—' }
								</span>
								<span className="vmfo-summary-label">
									{ __( 'Needs Review', 'vmfa-editorial-workflow' ) }
								</span>
							</div>
							<div className="vmfo-summary-item">
								<span className="vmfo-summary-value vmfo-summary-value--success">
									{ stats?.approved ?? '—' }
								</span>
								<span className="vmfo-summary-label">
									{ __( 'Approved', 'vmfa-editorial-workflow' ) }
								</span>
							</div>
							<div className="vmfo-summary-item">
								<span className="vmfo-summary-value">
									{ stats?.rolesConfigured ?? '—' }
								</span>
								<span className="vmfo-summary-label">
									{ __( 'Roles Configured', 'vmfa-editorial-workflow' ) }
								</span>
							</div>
						</div>
					) }
				</CardBody>
			</Card>

			{ /* Review Actions Card */ }
			<Card className="vmfo-dashboard-card">
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
			<Card className="vmfo-dashboard-card">
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

			{ /* About Review Workflow Card */ }
			<Card className="vmfo-dashboard-card vmfo-info-card">
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
					<ul className="vmfo-quick-links">
						{ reviewPageUrl && (
							<li>
								<a href={ reviewPageUrl }>
									{ __( 'Review pending media', 'vmfa-editorial-workflow' ) }
								</a>
							</li>
						) }
						<li>
							<a href="upload.php">
								{ __( 'Media Library', 'vmfa-editorial-workflow' ) }
							</a>
						</li>
					</ul>
				</CardBody>
			</Card>
		</>
	);
}

export default DashboardPage;
