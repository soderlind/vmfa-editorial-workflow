/**
 * Permission Matrix component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useCallback, useMemo } from '@wordpress/element';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Permission Matrix component.
 *
 * Displays a grid of folders × roles with action checkboxes.
 *
 * @param {Object}   props             Component props.
 * @param {Array}    props.roles       Available roles.
 * @param {Array}    props.folders     Available folders.
 * @param {Array}    props.actions     Available actions.
 * @param {Object}   props.permissions Current permissions state.
 * @param {Function} props.onChange    Callback when permissions change.
 * @return {JSX.Element} Permission matrix.
 */
export default function PermissionMatrix( {
	roles = [],
	folders = [],
	actions = [],
	permissions = {},
	onChange,
} ) {
	/**
	 * Build folder tree with indentation.
	 */
	const folderTree = useMemo( () => {
		const tree = [];
		const folderMap = {};

		// Create a map for quick lookup.
		folders.forEach( ( folder ) => {
			folderMap[ folder.id ] = folder;
		} );

		// Build tree with depth.
		const addWithDepth = ( folder, depth = 0 ) => {
			tree.push( { ...folder, depth } );
			folders
				.filter( ( f ) => f.parent === folder.id )
				.forEach( ( child ) => addWithDepth( child, depth + 1 ) );
		};

		// Start with root folders.
		folders
			.filter( ( f ) => f.parent === 0 )
			.forEach( ( root ) => addWithDepth( root, 0 ) );

		return tree;
	}, [ folders ] );

	/**
	 * Check if a role has a specific action on a folder.
	 *
	 * @param {number} folderId Folder ID.
	 * @param {string} role     Role key.
	 * @param {string} action   Action key.
	 * @return {boolean} Whether action is allowed.
	 */
	const hasPermission = useCallback(
		( folderId, role, action ) => {
			const folderPerms = permissions[ folderId ];
			if ( ! folderPerms ) {
				return false;
			}
			const rolePerms = folderPerms[ role ];
			if ( ! rolePerms ) {
				return false;
			}
			return rolePerms.includes( action );
		},
		[ permissions ]
	);

	/**
	 * Toggle a permission.
	 *
	 * @param {number}  folderId Folder ID.
	 * @param {string}  role     Role key.
	 * @param {string}  action   Action key.
	 * @param {boolean} checked  New checked state.
	 */
	const togglePermission = useCallback(
		( folderId, role, action, checked ) => {
			const newPermissions = { ...permissions };

			if ( ! newPermissions[ folderId ] ) {
				newPermissions[ folderId ] = {};
			}

			if ( ! newPermissions[ folderId ][ role ] ) {
				newPermissions[ folderId ][ role ] = [];
			}

			const currentActions = [ ...newPermissions[ folderId ][ role ] ];

			if ( checked ) {
				if ( ! currentActions.includes( action ) ) {
					currentActions.push( action );
				}
			} else {
				const index = currentActions.indexOf( action );
				if ( index > -1 ) {
					currentActions.splice( index, 1 );
				}
			}

			newPermissions[ folderId ][ role ] = currentActions;

			// Clean up empty arrays.
			if ( currentActions.length === 0 ) {
				delete newPermissions[ folderId ][ role ];
			}
			if ( Object.keys( newPermissions[ folderId ] ).length === 0 ) {
				delete newPermissions[ folderId ];
			}

			onChange( newPermissions );
		},
		[ permissions, onChange ]
	);

	if ( ! roles.length || ! folders.length ) {
		return (
			<p className="vmfa-empty-state">
				{ __( 'No roles or folders available.', 'vmfa-editorial-workflow' ) }
			</p>
		);
	}

	return (
		<div className="vmfa-permission-matrix">
			<table className="widefat">
				<thead>
					<tr>
						<th>{ __( 'Folder', 'vmfa-editorial-workflow' ) }</th>
						{ roles.map( ( role ) => (
							<th key={ role.key } colSpan={ actions.length }>
								{ role.name }
							</th>
						) ) }
					</tr>
					<tr>
						<th></th>
						{ roles.map( ( role ) =>
							actions.map( ( action ) => (
								<th
									key={ `${ role.key }-${ action.key }` }
									className="vmfa-action-header"
								>
									{ action.label }
								</th>
							) )
						) }
					</tr>
				</thead>
				<tbody>
					{ folderTree.map( ( folder ) => (
						<tr
							key={ folder.id }
							className={ folder.isSystem ? 'vmfa-system-folder' : '' }
						>
							<td
								style={ { paddingLeft: `${ folder.depth * 20 + 8 }px` } }
							>
								{ folder.isSystem && (
									<span className="dashicons dashicons-lock" title={ __( 'System folder', 'vmfa-editorial-workflow' ) }></span>
								) }
								{ folder.name }
							</td>
							{ roles.map( ( role ) =>
								actions.map( ( action ) => (
									<td
										key={ `${ folder.id }-${ role.key }-${ action.key }` }
										className="vmfa-checkbox-cell"
									>
										<CheckboxControl
											checked={ hasPermission(
												folder.id,
												role.key,
												action.key
											) }
											onChange={ ( checked ) =>
												togglePermission(
													folder.id,
													role.key,
													action.key,
													checked
												)
											}
										/>
									</td>
								) )
							) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
