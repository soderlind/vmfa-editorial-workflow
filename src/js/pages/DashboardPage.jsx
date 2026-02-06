/**
 * Dashboard Page Component for Editorial Workflow.
 *
 * Displays current workflow status and pending items.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Card, CardBody, CardHeader, Spinner, Button } from '@wordpress/components';
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

	return (
		<>
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

			{ /* Quick Links Card */ }
			<Card className="vmfo-dashboard-card vmfo-info-card">
				<CardHeader>
					<h3>
						{ __( 'Workflow Quick Actions', 'vmfa-editorial-workflow' ) }
					</h3>
				</CardHeader>
				<CardBody>
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
