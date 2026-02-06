/**
 * Overview Page Component for Editorial Workflow.
 *
 * Displays add-on description, KPI stats, and quick info.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { StatsCard } from '@vmfo/shared';

/**
 * Overview Page component.
 *
 * @return {JSX.Element} The overview page content.
 */
export function OverviewPage() {
	const [ stats, setStats ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	/**
	 * Fetch workflow statistics.
	 */
	const fetchStats = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa-editorial/v1/stats',
				method: 'GET',
			} );
			setStats( response );
		} catch ( err ) {
			// Ignore fetch errors; stats may still show loading.
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStats();
	}, [ fetchStats ] );

	const kpiStats = stats
		? [
				{
					label: __( 'Total Media', 'vmfa-editorial-workflow' ),
					value: stats.totalMedia?.toLocaleString() ?? '—',
				},
				{
					label: __( 'Needs Review', 'vmfa-editorial-workflow' ),
					value: stats.needsReview?.toLocaleString() ?? '—',
				},
				{
					label: __( 'Approved', 'vmfa-editorial-workflow' ),
					value: stats.approved?.toLocaleString() ?? '—',
				},
				{
					label: __( 'Roles Configured', 'vmfa-editorial-workflow' ),
					value: stats.rolesConfigured?.toLocaleString() ?? '—',
				},
		  ]
		: [
				{
					label: __( 'Total Media', 'vmfa-editorial-workflow' ),
					isLoading: loading,
				},
				{
					label: __( 'Needs Review', 'vmfa-editorial-workflow' ),
					isLoading: loading,
				},
				{
					label: __( 'Approved', 'vmfa-editorial-workflow' ),
					isLoading: loading,
				},
				{
					label: __( 'Roles Configured', 'vmfa-editorial-workflow' ),
					isLoading: loading,
				},
		  ];

	return (
		<>
			<StatsCard stats={ kpiStats } />

			<Card className="vmfo-overview-card">
				<CardHeader>
					<h3>
						{ __( 'About Editorial Workflow', 'vmfa-editorial-workflow' ) }
					</h3>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Editorial Workflow provides a structured media approval process for your WordPress site. Media uploaded by contributors and authors is automatically placed in a review queue for editors to approve before publication.',
							'vmfa-editorial-workflow'
						) }
					</p>
					<h4>{ __( 'Features', 'vmfa-editorial-workflow' ) }</h4>
					<ul>
						<li>
							{ __(
								'Automatic "Needs Review" folder for new uploads from non-admin roles',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Granular folder permissions by role',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Custom inbox folders per role',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Dedicated review page for approving media',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Configurable approved destination folder',
								'vmfa-editorial-workflow'
							) }
						</li>
					</ul>
					<h4>{ __( 'How It Works', 'vmfa-editorial-workflow' ) }</h4>
					<ol>
						<li>
							{ __(
								'Contributors upload media files',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Files are automatically placed in the "Needs Review" folder',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Editors review and approve media from the Review page',
								'vmfa-editorial-workflow'
							) }
						</li>
						<li>
							{ __(
								'Approved media moves to the designated folder',
								'vmfa-editorial-workflow'
							) }
						</li>
					</ol>
				</CardBody>
			</Card>
		</>
	);
}

export default OverviewPage;
