/**
 * Inbox Card component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useMemo } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { buildFolderOptions } from './utils/buildFolderOptions';

/**
 * Inbox Card component.
 *
 * Maps roles to inbox folders in a card layout.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.roles     Available roles.
 * @param {Array}    props.folders   Available folders.
 * @param {Object}   props.inboxMap  Current inbox mapping.
 * @param {Function} props.onChange  Callback when mapping changes.
 * @return {JSX.Element} Inbox card.
 */
export default function InboxCard( {
	roles = [],
	folders = [],
	inboxMap = {},
	onChange,
} ) {
	/**
	 * Build folder options with hierarchy.
	 */
	const folderOptions = useMemo(
		() =>
			buildFolderOptions( folders, {
				emptyLabel: __(
					'Use workflow inbox',
					'vmfa-editorial-workflow'
				),
			} ),
		[ folders ]
	);

	/**
	 * Handle inbox change for a role.
	 */
	const handleChange = ( role, folderId ) => {
		const newMap = { ...inboxMap };

		if ( folderId ) {
			newMap[ role ] = parseInt( folderId, 10 );
		} else {
			delete newMap[ role ];
		}

		onChange( newMap );
	};

	if ( ! roles.length ) {
		return null;
	}

	return (
		<div className="vmfa-card">
			<div className="vmfa-card-header">
				<h3>{ __( 'Role Inbox Folders', 'vmfa-editorial-workflow' ) }</h3>
			</div>
			<div className="vmfa-card-body">
				<p className="vmfa-card-description">
					{ __(
						'Override the default workflow inbox for specific roles. If not set, uploads will go to the "Needs Review" folder.',
						'vmfa-editorial-workflow'
					) }
				</p>

				<div className="vmfa-inbox-grid">
					{ roles.map( ( role ) => (
						<div key={ role.key } className="vmfa-inbox-item">
							<SelectControl
								label={ role.name }
								value={ String( inboxMap[ role.key ] || '' ) }
								options={ folderOptions }
								onChange={ ( value ) => handleChange( role.key, value ) }
							/>
						</div>
					) ) }
				</div>
			</div>
		</div>
	);
}
