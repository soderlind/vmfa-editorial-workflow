/**
 * Inbox Mapping component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useCallback, useMemo } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { buildFolderOptions } from './utils/buildFolderOptions';

/**
 * Inbox Mapping component.
 *
 * Maps roles to inbox folders.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.roles     Available roles.
 * @param {Array}    props.folders   Available folders.
 * @param {Object}   props.inboxMap  Current inbox mapping.
 * @param {Function} props.onChange  Callback when mapping changes.
 * @return {JSX.Element} Inbox mapping UI.
 */
export default function InboxMapping( {
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
				emptyLabel: __( 'No inbox (use default)', 'vmfa-editorial-workflow' ),
			} ),
		[ folders ]
	);

	/**
	 * Handle inbox change for a role.
	 *
	 * @param {string} role     Role key.
	 * @param {string} folderId Folder ID (as string from select).
	 */
	const handleChange = useCallback(
		( role, folderId ) => {
			const newMap = { ...inboxMap };

			if ( folderId ) {
				newMap[ role ] = parseInt( folderId, 10 );
			} else {
				delete newMap[ role ];
			}

			onChange( newMap );
		},
		[ inboxMap, onChange ]
	);

	if ( ! roles.length ) {
		return (
			<p className="vmfa-empty-state">
				{ __( 'No roles available.', 'vmfa-editorial-workflow' ) }
			</p>
		);
	}

	return (
		<div className="vmfa-inbox-mapping">
			<table className="form-table">
				<tbody>
					{ roles.map( ( role ) => (
						<tr key={ role.key }>
							<th scope="row">{ role.name }</th>
							<td>
								<SelectControl
									value={ String( inboxMap[ role.key ] || '' ) }
									options={ folderOptions }
									onChange={ ( value ) =>
										handleChange( role.key, value )
									}
								/>
								<p className="description">
									{ __(
										'New uploads from this role will be placed in the selected folder.',
										'vmfa-editorial-workflow'
									) }
								</p>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
