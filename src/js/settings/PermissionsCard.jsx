/**
 * Permissions Card component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { CheckboxControl, Button, DropdownMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Permissions Card component.
 *
 * Card-based layout for folder permission configuration.
 *
 * @param {Object}   props             Component props.
 * @param {Array}    props.roles       Available roles.
 * @param {Array}    props.folders     Available folders.
 * @param {Array}    props.actions     Available actions.
 * @param {Object}   props.permissions Current permissions state.
 * @param {Function} props.onChange    Callback when permissions change.
 * @return {JSX.Element} Permissions card.
 */
export default function PermissionsCard( {
	roles = [],
	folders = [],
	actions = [],
	permissions = {},
	onChange,
} ) {
	const [ expandedRoles, setExpandedRoles ] = useState( {} );

	/**
	 * Build folder tree with indentation.
	 */
	const folderTree = useMemo( () => {
		const tree = [];

		const addWithDepth = ( folder, depth = 0 ) => {
			tree.push( { ...folder, depth } );
			folders
				.filter( ( f ) => f.parent === folder.id )
				.forEach( ( child ) => addWithDepth( child, depth + 1 ) );
		};

		folders
			.filter( ( f ) => f.parent === 0 )
			.forEach( ( root ) => addWithDepth( root, 0 ) );

		return tree;
	}, [ folders ] );

	/**
	 * Check if a role has a specific action on a folder.
	 */
	const hasPermission = useCallback(
		( folderId, role, action ) => {
			return permissions[ folderId ]?.[ role ]?.includes( action ) ?? false;
		},
		[ permissions ]
	);

	/**
	 * Toggle a permission.
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

			if ( checked && ! currentActions.includes( action ) ) {
				currentActions.push( action );
			} else if ( ! checked ) {
				const index = currentActions.indexOf( action );
				if ( index > -1 ) currentActions.splice( index, 1 );
			}

			newPermissions[ folderId ][ role ] = currentActions;

			// Cleanup empty entries.
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

	/**
	 * Bulk grant all permissions for a role.
	 */
	const grantAllForRole = useCallback(
		( roleKey ) => {
			const newPermissions = { ...permissions };

			folders.forEach( ( folder ) => {
				if ( ! newPermissions[ folder.id ] ) {
					newPermissions[ folder.id ] = {};
				}
				newPermissions[ folder.id ][ roleKey ] = actions.map( ( a ) => a.key );
			} );

			onChange( newPermissions );
		},
		[ permissions, folders, actions, onChange ]
	);

	/**
	 * Bulk revoke all permissions for a role.
	 */
	const revokeAllForRole = useCallback(
		( roleKey ) => {
			const newPermissions = { ...permissions };

			Object.keys( newPermissions ).forEach( ( folderId ) => {
				if ( newPermissions[ folderId ][ roleKey ] ) {
					delete newPermissions[ folderId ][ roleKey ];
				}
				if ( Object.keys( newPermissions[ folderId ] ).length === 0 ) {
					delete newPermissions[ folderId ];
				}
			} );

			onChange( newPermissions );
		},
		[ permissions, onChange ]
	);

	/**
	 * Bulk grant a specific action for all folders for a role.
	 */
	const grantActionForRole = useCallback(
		( roleKey, actionKey ) => {
			const newPermissions = { ...permissions };

			folders.forEach( ( folder ) => {
				if ( ! newPermissions[ folder.id ] ) {
					newPermissions[ folder.id ] = {};
				}
				if ( ! newPermissions[ folder.id ][ roleKey ] ) {
					newPermissions[ folder.id ][ roleKey ] = [];
				}
				if ( ! newPermissions[ folder.id ][ roleKey ].includes( actionKey ) ) {
					newPermissions[ folder.id ][ roleKey ] = [
						...newPermissions[ folder.id ][ roleKey ],
						actionKey,
					];
				}
			} );

			onChange( newPermissions );
		},
		[ permissions, folders, onChange ]
	);

	/**
	 * Bulk revoke a specific action for all folders for a role.
	 */
	const revokeActionForRole = useCallback(
		( roleKey, actionKey ) => {
			const newPermissions = { ...permissions };

			Object.keys( newPermissions ).forEach( ( folderId ) => {
				if ( newPermissions[ folderId ][ roleKey ] ) {
					newPermissions[ folderId ][ roleKey ] = newPermissions[ folderId ][ roleKey ].filter(
						( a ) => a !== actionKey
					);
					if ( newPermissions[ folderId ][ roleKey ].length === 0 ) {
						delete newPermissions[ folderId ][ roleKey ];
					}
				}
				if ( Object.keys( newPermissions[ folderId ] ).length === 0 ) {
					delete newPermissions[ folderId ];
				}
			} );

			onChange( newPermissions );
		},
		[ permissions, onChange ]
	);

	/**
	 * Check if role has all permissions for an action.
	 */
	const hasAllActionPermissions = useCallback(
		( roleKey, actionKey ) => {
			return folders.every( ( folder ) =>
				permissions[ folder.id ]?.[ roleKey ]?.includes( actionKey )
			);
		},
		[ permissions, folders ]
	);

	/**
	 * Check if role has some permissions for an action.
	 */
	const hasSomeActionPermissions = useCallback(
		( roleKey, actionKey ) => {
			return folders.some( ( folder ) =>
				permissions[ folder.id ]?.[ roleKey ]?.includes( actionKey )
			);
		},
		[ permissions, folders ]
	);

	/**
	 * Toggle all permissions for an action column.
	 */
	const toggleActionColumn = useCallback(
		( roleKey, actionKey ) => {
			if ( hasAllActionPermissions( roleKey, actionKey ) ) {
				revokeActionForRole( roleKey, actionKey );
			} else {
				grantActionForRole( roleKey, actionKey );
			}
		},
		[ hasAllActionPermissions, revokeActionForRole, grantActionForRole ]
	);

	/**
	 * Toggle role expansion.
	 */
	const toggleRole = ( roleKey ) => {
		setExpandedRoles( ( prev ) => ( {
			...prev,
			[ roleKey ]: ! prev[ roleKey ],
		} ) );
	};

	/**
	 * Count permissions for a role.
	 */
	const countRolePermissions = useCallback(
		( roleKey ) => {
			let count = 0;
			Object.values( permissions ).forEach( ( folderPerms ) => {
				if ( folderPerms[ roleKey ] ) {
					count += folderPerms[ roleKey ].length;
				}
			} );
			return count;
		},
		[ permissions ]
	);

	/**
	 * Get bulk action menu items for a role.
	 */
	const getBulkMenuControls = useCallback(
		( roleKey ) => {
			const grantActions = actions.map( ( action ) => ( {
				title: `${ __( 'Grant', 'vmfa-editorial-workflow' ) } "${ action.label }" ${ __( 'to all folders', 'vmfa-editorial-workflow' ) }`,
				onClick: () => grantActionForRole( roleKey, action.key ),
			} ) );

			const revokeActions = actions.map( ( action ) => ( {
				title: `${ __( 'Revoke', 'vmfa-editorial-workflow' ) } "${ action.label }" ${ __( 'from all folders', 'vmfa-editorial-workflow' ) }`,
				onClick: () => revokeActionForRole( roleKey, action.key ),
			} ) );

			return [
				{
					title: __( 'Grant all permissions', 'vmfa-editorial-workflow' ),
					onClick: () => grantAllForRole( roleKey ),
				},
				{
					title: __( 'Revoke all permissions', 'vmfa-editorial-workflow' ),
					onClick: () => revokeAllForRole( roleKey ),
				},
				...grantActions,
				...revokeActions,
			];
		},
		[ actions, grantAllForRole, revokeAllForRole, grantActionForRole, revokeActionForRole ]
	);

	if ( ! roles.length || ! folders.length ) {
		return (
			<div className="vmfa-card">
				<div className="vmfa-card-header">
					<h3>{ __( 'Folder Permissions', 'vmfa-editorial-workflow' ) }</h3>
				</div>
				<div className="vmfa-card-body">
					<p className="vmfa-empty-state">
						{ __( 'No roles or folders available.', 'vmfa-editorial-workflow' ) }
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="vmfa-card">
			<div className="vmfa-card-header">
				<h3>{ __( 'Folder Permissions', 'vmfa-editorial-workflow' ) }</h3>
			</div>
			<div className="vmfa-card-body">
				<p className="vmfa-card-description">
					{ __(
						'Configure which roles can access each folder. Administrators always have full access. Click column headers to toggle all.',
						'vmfa-editorial-workflow'
					) }
				</p>

				<div className="vmfa-permissions-list">
					{ roles.map( ( role ) => (
						<div key={ role.key } className="vmfa-permission-role">
							<div className="vmfa-role-header-row">
								<button
									type="button"
									className="vmfa-role-header"
									onClick={ () => toggleRole( role.key ) }
									aria-expanded={ expandedRoles[ role.key ] }
								>
									<span className="vmfa-role-toggle">
										<span
											className={ `dashicons dashicons-arrow-${ expandedRoles[ role.key ] ? 'down' : 'right' }-alt2` }
										></span>
									</span>
									<span className="vmfa-role-name">{ role.name }</span>
									<span className="vmfa-role-count">
										{ countRolePermissions( role.key ) }{ ' ' }
										{ __( 'permissions', 'vmfa-editorial-workflow' ) }
									</span>
								</button>
								<DropdownMenu
									icon="admin-generic"
									label={ __( 'Bulk actions', 'vmfa-editorial-workflow' ) }
									controls={ getBulkMenuControls( role.key ) }
									className="vmfa-bulk-menu"
								/>
							</div>

							{ expandedRoles[ role.key ] && (
								<div className="vmfa-role-folders">
									<table className="vmfa-folders-table">
										<thead>
											<tr>
												<th>{ __( 'Folder', 'vmfa-editorial-workflow' ) }</th>
												{ actions.map( ( action ) => {
													const allChecked = hasAllActionPermissions( role.key, action.key );
													const someChecked = hasSomeActionPermissions( role.key, action.key );
													return (
														<th
															key={ action.key }
															className="vmfa-action-header-cell"
														>
															<button
																type="button"
																className="vmfa-column-toggle"
																onClick={ () => toggleActionColumn( role.key, action.key ) }
																title={ allChecked
																	? __( 'Revoke all', 'vmfa-editorial-workflow' )
																	: __( 'Grant all', 'vmfa-editorial-workflow' )
																}
															>
																<span className={ `vmfa-column-indicator ${ allChecked ? 'all' : someChecked ? 'some' : '' }` }></span>
																{ action.label }
															</button>
														</th>
													);
												} ) }
											</tr>
										</thead>
										<tbody>
											{ folderTree.map( ( folder ) => (
												<tr
													key={ folder.id }
													className={ folder.isSystem ? 'vmfa-system-folder' : '' }
												>
													<td style={ { paddingLeft: `${ folder.depth * 16 + 8 }px` } }>
														{ folder.isSystem && (
															<span
																className="dashicons dashicons-lock"
																title={ __( 'System folder', 'vmfa-editorial-workflow' ) }
															></span>
														) }
														{ folder.name }
													</td>
													{ actions.map( ( action ) => (
														<td key={ action.key } className="vmfa-checkbox-cell">
															<CheckboxControl
																checked={ hasPermission( folder.id, role.key, action.key ) }
																onChange={ ( checked ) =>
																	togglePermission( folder.id, role.key, action.key, checked )
																}
															/>
														</td>
													) ) }
												</tr>
											) ) }
										</tbody>
									</table>
								</div>
							) }
						</div>
					) ) }
				</div>
			</div>
		</div>
	);
}
