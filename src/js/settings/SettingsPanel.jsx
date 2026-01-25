/**
 * Settings Panel component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import StatsCard from './StatsCard';
import WorkflowCard from './WorkflowCard';
import PermissionsCard from './PermissionsCard';
import InboxCard from './InboxCard';

/**
 * Main settings panel component.
 *
 * @return {JSX.Element} Settings panel.
 */
export default function SettingsPanel() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ isDismissing, setIsDismissing ] = useState( false );
	const [ stats, setStats ] = useState( {} );

	const [ settings, setSettings ] = useState( {
		permissions: {},
		inbox: {},
		workflow: {
			enabled: true,
			needsReviewFolder: '',
			approvedFolder: '',
		},
	} );

	const { roles, folders, actions, i18n } = window.vmfaSettings || {};

	/**
	 * Fetch settings on mount.
	 */
	useEffect( () => {
		fetchSettings();
		fetchStats();
	}, [] );

	/**
	 * Auto-dismiss success notices after 3 seconds.
	 */
	useEffect( () => {
		if ( notice?.status === 'success' && ! isDismissing ) {
			const timer = setTimeout( () => {
				setIsDismissing( true );
				// Wait for animation to complete before removing.
				setTimeout( () => {
					setNotice( null );
					setIsDismissing( false );
				}, 300 );
			}, 3000 );
			return () => clearTimeout( timer );
		}
	}, [ notice, isDismissing ] );

	/**
	 * Fetch settings from REST API.
	 */
	const fetchSettings = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await apiFetch( {
				path: '/vmfa-editorial/v1/settings',
			} );
			setSettings( response );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: error.message || i18n?.error || 'Error loading settings.',
			} );
		} finally {
			setIsLoading( false );
		}
	}, [ i18n ] );

	/**
	 * Fetch statistics.
	 */
	const fetchStats = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa-editorial/v1/stats',
			} );
			setStats( response );
		} catch ( error ) {
			// Stats are optional, don't show error.
			console.warn( 'Could not load stats:', error );
		}
	}, [] );

	/**
	 * Save settings to REST API.
	 */
	const saveSettings = useCallback( async () => {
		setIsSaving( true );
		setNotice( null );

		try {
			const response = await apiFetch( {
				path: '/vmfa-editorial/v1/settings',
				method: 'POST',
				data: settings,
			} );
			setSettings( response );
			setNotice( {
				status: 'success',
				message: i18n?.saved || 'Settings saved.',
			} );
			// Refresh stats after save.
			fetchStats();
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: error.message || i18n?.error || 'Error saving settings.',
			} );
		} finally {
			setIsSaving( false );
		}
	}, [ settings, i18n, fetchStats ] );

	/**
	 * Update permissions state.
	 */
	const updatePermissions = useCallback( ( newPermissions ) => {
		setSettings( ( prev ) => ( {
			...prev,
			permissions: newPermissions,
		} ) );
	}, [] );

	/**
	 * Update inbox state.
	 */
	const updateInbox = useCallback( ( newInbox ) => {
		setSettings( ( prev ) => ( {
			...prev,
			inbox: newInbox,
		} ) );
	}, [] );

	/**
	 * Update workflow state.
	 */
	const updateWorkflow = useCallback( ( newWorkflow ) => {
		setSettings( ( prev ) => ( {
			...prev,
			workflow: newWorkflow,
		} ) );
	}, [] );

	if ( isLoading ) {
		return (
			<div className="vmfa-settings-loading">
				<Spinner />
				<p>{ __( 'Loading settings…', 'vmfa-editorial-workflow' ) }</p>
			</div>
		);
	}

	return (
		<div className="vmfa-settings-panel">
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onRemove={ () => {
						setIsDismissing( true );
						setTimeout( () => {
							setNotice( null );
							setIsDismissing( false );
						}, 300 );
					} }
					className={ `vmfa-notice${ isDismissing ? ' is-dismissing' : '' }` }
				>
					{ notice.message }
				</Notice>
			) }

			<StatsCard stats={ stats } />

			<WorkflowCard
				folders={ folders }
				workflow={ settings.workflow }
				onChange={ updateWorkflow }
			/>

			<PermissionsCard
				roles={ roles }
				folders={ folders }
				actions={ actions }
				permissions={ settings.permissions }
				onChange={ updatePermissions }
			/>

			<InboxCard
				roles={ roles }
				folders={ folders }
				inboxMap={ settings.inbox }
				onChange={ updateInbox }
			/>

			<div className="vmfa-settings-actions">
				<Button
					variant="primary"
					onClick={ saveSettings }
					isBusy={ isSaving }
					disabled={ isSaving }
				>
					{ isSaving
						? __( 'Saving…', 'vmfa-editorial-workflow' )
						: __( 'Save Changes', 'vmfa-editorial-workflow' ) }
				</Button>
			</div>
		</div>
	);
}
