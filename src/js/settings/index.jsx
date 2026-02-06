/**
 * Settings panel entry point.
 *
 * Uses AddonShell for unified add-on UI structure.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import { AddonShell } from '@vmfo/shared';
import { OverviewPage, DashboardPage, ConfigurePage } from '../pages';

import '../../css/settings.css';

/**
 * Main Editorial Workflow App using AddonShell.
 *
 * @return {JSX.Element} The app component.
 */
function EditorialWorkflowApp() {
	const [ stats, setStats ] = useState( null );
	const [ enabled ] = useState( true );

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
			// Ignore fetch errors.
		}
	}, [] );

	useEffect( () => {
		fetchStats();
	}, [ fetchStats ] );

	// Listen for stats refresh events.
	useEffect( () => {
		const handleRefresh = ( e ) => {
			if ( e.detail?.addonKey === 'editorial-workflow' ) {
				fetchStats();
			}
		};
		window.addEventListener( 'vmfa-stats-refresh', handleRefresh );
		return () => {
			window.removeEventListener( 'vmfa-stats-refresh', handleRefresh );
		};
	}, [ fetchStats ] );

	// Build KPI stats for AddonShell.
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
		: [];

	return (
		<AddonShell
			addonKey="editorial-workflow"
			addonLabel={ __( 'Editorial Workflow', 'vmfa-editorial-workflow' ) }
			enabled={ enabled }
			stats={ kpiStats }
			overviewContent={ <OverviewPage /> }
			dashboardContent={ <DashboardPage /> }
			configureContent={ <ConfigurePage /> }
		/>
	);
}

/**
 * Initialize the Editorial Workflow panel.
 */
function initEditorialWorkflow() {
	const container = document.getElementById( 'vmfa-settings-root' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <EditorialWorkflowApp /> );
	}
}

// Initialize when DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initEditorialWorkflow );
} else {
	initEditorialWorkflow();
}
