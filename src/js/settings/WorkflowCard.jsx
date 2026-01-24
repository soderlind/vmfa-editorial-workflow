/**
 * Workflow Folders Card component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useMemo } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Workflow Folders Card component.
 *
 * Displays and toggles the workflow feature. System folders are created automatically.
 *
 * @param {Object}   props                   Component props.
 * @param {Array}    props.folders           Available folders.
 * @param {Object}   props.workflow          Current workflow settings.
 * @param {Function} props.onChange          Callback when workflow changes.
 * @return {JSX.Element} Workflow folders card.
 */
export default function WorkflowCard( {
	folders = [],
	workflow = {},
	onChange,
} ) {
	const {
		enabled = true,
		needsReviewFolder = '',
		approvedFolder = '',
	} = workflow;

	/**
	 * Get folder name by ID.
	 */
	const getFolderName = useMemo( () => {
		const folderMap = {};
		folders.forEach( ( f ) => {
			folderMap[ f.id ] = f.name;
		} );
		return ( id ) => folderMap[ id ] || __( 'Not configured', 'vmfa-editorial-workflow' );
	}, [ folders ] );

	/**
	 * Handle enabled toggle.
	 */
	const handleEnabledChange = ( value ) => {
		onChange( {
			...workflow,
			enabled: value,
		} );
	};

	return (
		<div className="vmfa-card">
			<div className="vmfa-card-header">
				<h3>{ __( 'Workflow Folders', 'vmfa-editorial-workflow' ) }</h3>
				<ToggleControl
					checked={ enabled }
					onChange={ handleEnabledChange }
				/>
			</div>

			{ enabled && (
				<div className="vmfa-card-body">
					<p className="vmfa-card-description">
						{ __(
							'Media uploaded by non-admin roles will be placed in the "Needs Review" folder for editorial approval. Approved media can be moved to the appropriate destination folder.',
							'vmfa-editorial-workflow'
						) }
					</p>

					<div className="vmfa-workflow-info">
						<div className="vmfa-workflow-folder">
							<span className="vmfa-folder-icon dashicons dashicons-category"></span>
							<div className="vmfa-folder-details">
								<strong>{ __( 'Needs Review', 'vmfa-editorial-workflow' ) }</strong>
								<span className="vmfa-folder-path">
									{ needsReviewFolder
										? `Workflow / ${ getFolderName( needsReviewFolder ) }`
										: __( 'Will be created on first use', 'vmfa-editorial-workflow' )
									}
								</span>
							</div>
						</div>
						<div className="vmfa-workflow-folder">
							<span className="vmfa-folder-icon dashicons dashicons-yes-alt"></span>
							<div className="vmfa-folder-details">
								<strong>{ __( 'Approved', 'vmfa-editorial-workflow' ) }</strong>
								<span className="vmfa-folder-path">
									{ approvedFolder
										? `Workflow / ${ getFolderName( approvedFolder ) }`
										: __( 'Will be created on first use', 'vmfa-editorial-workflow' )
									}
								</span>
							</div>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
