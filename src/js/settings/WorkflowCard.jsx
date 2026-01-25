/**
 * Workflow Settings Card component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useMemo } from '@wordpress/element';
import { CheckboxControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { buildFolderOptions } from './utils/buildFolderOptions';

/**
 * Workflow Settings Card component.
 *
 * Displays workflow settings. Workflow is always enabled when plugin is active.
 *
 * @param {Object}   props                   Component props.
 * @param {Array}    props.folders           Available folders.
 * @param {Object}   props.workflow          Current workflow settings.
 * @param {Function} props.onChange          Callback when workflow changes.
 * @return {JSX.Element} Workflow settings card.
 */
export default function WorkflowCard( {
	folders = [],
	workflow = {},
	onChange,
} ) {
	const {
		editorsCanReview = true,
		approvedFolder = '',
	} = workflow;

	/**
	 * Build folder options with hierarchy, excluding system folders.
	 */
	const folderOptions = useMemo(
		() =>
			buildFolderOptions( folders, {
				emptyLabel: __(
					'Use default "Approved" folder',
					'vmfa-editorial-workflow'
				),
				excludeSystem: true,
			} ),
		[ folders ]
	);

	/**
	 * Handle editors can review toggle.
	 */
	const handleEditorsCanReviewChange = ( value ) => {
		onChange( {
			...workflow,
			editorsCanReview: value,
		} );
	};

	/**
	 * Handle approved folder change.
	 */
	const handleApprovedFolderChange = ( value ) => {
		onChange( {
			...workflow,
			approvedFolder: value ? parseInt( value, 10 ) : '',
		} );
	};

	return (
		<div className="vmfa-card">
			<div className="vmfa-card-header">
				<h3>{ __( 'Workflow Settings', 'vmfa-editorial-workflow' ) }</h3>
			</div>

			<div className="vmfa-card-body">
				<p className="vmfa-card-description">
					{ __(
						'Media uploaded by non-admin roles will be placed in the "Needs Review" folder for editorial approval.',
						'vmfa-editorial-workflow'
					) }
				</p>

				<div className="vmfa-workflow-settings">
					<SelectControl
						label={ __( 'Approved folder', 'vmfa-editorial-workflow' ) }
						help={ __( 'Where approved media will be moved. Leave empty to use the default "Approved" system folder.', 'vmfa-editorial-workflow' ) }
						value={ String( approvedFolder || '' ) }
						options={ folderOptions }
						onChange={ handleApprovedFolderChange }
					/>

					<CheckboxControl
						label={ __( 'Allow Editors to review media', 'vmfa-editorial-workflow' ) }
						help={ __( 'When enabled, Editors can access the Review page. When disabled, only Administrators can review.', 'vmfa-editorial-workflow' ) }
						checked={ editorsCanReview }
						onChange={ handleEditorsCanReviewChange }
					/>
				</div>
			</div>
		</div>
	);
}
