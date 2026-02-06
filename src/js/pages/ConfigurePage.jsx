/**
 * Configure Page Component for Editorial Workflow.
 *
 * Contains all settings - workflow options, permissions, inbox mapping.
 * No action buttons here - just configuration.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import WorkflowCard from '../settings/WorkflowCard';
import PermissionsCard from '../settings/PermissionsCard';
import InboxCard from '../settings/InboxCard';

/**
 * Configure Page component.
 *
 * @return {JSX.Element} The configure page content.
 */
export function ConfigurePage() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ isDismissing, setIsDismissing ] = useState( false );

	const [ settings, setSettings ] = useState( {
		permissions: {},
		inbox: {},
		workflow: {
			editorsCanReview: true,
			approvedFolder: '',
		},
	} );

	const { roles, folders, actions } = window.vmfaSettings || {};

	/**
	 * Fetch settings on mount.
	 */
	useEffect( () => {
		fetchSettings();
	}, [] );

	/**
	 * Auto-dismiss success notices after 3 seconds.
	 */
	useEffect( () => {
		if ( notice?.status === 'success' && ! isDismissing ) {
			const timer = setTimeout( () => {
				setIsDismissing( true );
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
				message:
					error.message ||
					__( 'Error loading settings.', 'vmfa-editorial-workflow' ),
			} );
		} finally {
			setIsLoading( false );
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
				message: __( 'Settings saved.', 'vmfa-editorial-workflow' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Error saving settings.', 'vmfa-editorial-workflow' ),
			} );
		} finally {
			setIsSaving( false );
		}
	}, [ settings ] );

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
		<div className="vmfa-configure-page">
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

export default ConfigurePage;
