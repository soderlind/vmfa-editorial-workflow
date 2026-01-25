/**
 * Permissions Card component.
 *
 * @package VmfaEditorialWorkflow
 */

import { useState, useCallback, useMemo } from '@wordpress/element';
import { CheckboxControl, Button, DropdownMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { settings } from '@wordpress/icons';

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
	 * Roles that cannot be configured (always full access).
	 */
	const unconfigurableRoles = [ 'administrator' ];

	/**
	 * Roles with default full access (but can be overridden).
	 */
	const defaultFullAccessRoles = [ 'editor' ];

	/**
	 * Check if a role cannot be configured.
	 */
	const isUnconfigurable = useCallback(
		( roleKey ) => unconfigurableRoles.includes( roleKey ),
		[]
	);

	/**
	 * Check if a role has default full access (can be overridden).
	 */
	const hasDefaultFullAccess = useCallback(
		( roleKey ) => defaultFullAccessRoles.includes( roleKey ),
		[]
	);

	/**
	 * Check if a role has any explicit permissions configured.
	 * An empty array counts as explicit configuration (means "no access").
	 */
	const hasExplicitPermissions = useCallback(
		( roleKey ) => {
			return Object.values( permissions ).some(
				( folderPerms ) => folderPerms[ roleKey ] !== undefined
			);
		},
		[ permissions ]
	);

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
	 * When a role with default full access first gets explicit configuration,
	 * we initialize ALL folders with full access so the transition is smooth.
	 */
	const togglePermission = useCallback(
		( folderId, role, action, checked ) => {
			const newPermissions = { ...permissions };

			// If this role has default full access and doesn't have explicit permissions yet,
			// we need to initialize ALL folders with full access before modifying this one.
			// This ensures a smooth transition from "default full access" to "explicit permissions".
			if ( hasDefaultFullAccess( role ) && ! hasExplicitPermissions( role ) ) {
				folders.forEach( ( folder ) => {
					if ( ! newPermissions[ folder.id ] ) {
						newPermissions[ folder.id ] = {};
					}
					// Give all actions to this role for all folders.
					newPermissions[ folder.id ][ role ] = actions.map( ( a ) => a.key );
				} );
			}

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

			// Keep empty arrays - they mean "explicitly no access" and need to be saved.
			// Don't delete empty entries as PHP needs them to know to save "no permissions".

			onChange( newPermissions );
		},
		[ permissions, folders, actions, hasDefaultFullAccess, hasExplicitPermissions, onChange ]
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
	 * We explicitly set empty arrays for all folders so PHP knows to save
	 * "no permissions" rather than keeping old permissions in the database.
	 */
	const revokeAllForRole = useCallback(
		( roleKey ) => {
			const newPermissions = { ...permissions };

			// Set empty permissions for all folders to explicitly revoke access.
			// This ensures PHP saves "no permissions" rather than keeping old values.
			folders.forEach( ( folder ) => {
				if ( ! newPermissions[ folder.id ] ) {
					newPermissions[ folder.id ] = {};
				}
				newPermissions[ folder.id ][ roleKey ] = [];
			} );

			onChange( newPermissions );
		},
		[ permissions, folders, onChange ]
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

			// For roles with default full access, first initialize all folders with
			// all actions except the one being revoked.
			if ( hasDefaultFullAccess( roleKey ) ) {
				const otherActions = actions.filter( ( a ) => a.key !== actionKey ).map( ( a ) => a.key );
				folders.forEach( ( folder ) => {
					if ( ! newPermissions[ folder.id ] ) {
						newPermissions[ folder.id ] = {};
					}
					if ( newPermissions[ folder.id ][ roleKey ] === undefined ) {
						// Not yet configured - set to all actions except the one being revoked.
						newPermissions[ folder.id ][ roleKey ] = otherActions;
					} else {
						// Already configured - just remove the action.
						newPermissions[ folder.id ][ roleKey ] = newPermissions[ folder.id ][ roleKey ].filter(
							( a ) => a !== actionKey
						);
					}
				} );
			} else {
				// For other roles, remove the action from existing permissions.
				// Keep empty arrays - they mean "explicitly no access".
				Object.keys( newPermissions ).forEach( ( folderId ) => {
					if ( newPermissions[ folderId ][ roleKey ] ) {
						newPermissions[ folderId ][ roleKey ] = newPermissions[ folderId ][ roleKey ].filter(
							( a ) => a !== actionKey
						);
						// Keep empty arrays - don't delete them.
					}
				} );
			}

			onChange( newPermissions );
		},
		[ permissions, folders, actions, hasDefaultFullAccess, onChange ]
	);

	/**
	 * Check if role has all permissions for an action.
	 * For roles with default full access and no explicit config, returns true.
	 */
	const hasAllActionPermissions = useCallback(
		( roleKey, actionKey ) => {
			// If role has default full access and no explicit permissions, it has all.
			if ( hasDefaultFullAccess( roleKey ) && ! hasExplicitPermissions( roleKey ) ) {
				return true;
			}
			return folders.every( ( folder ) =>
				permissions[ folder.id ]?.[ roleKey ]?.includes( actionKey )
			);
		},
		[ permissions, folders, hasDefaultFullAccess, hasExplicitPermissions ]
	);

	/**
	 * Check if role has some permissions for an action.
	 * For roles with default full access and no explicit config, returns true.
	 */
	const hasSomeActionPermissions = useCallback(
		( roleKey, actionKey ) => {
			// If role has default full access and no explicit permissions, it has all.
			if ( hasDefaultFullAccess( roleKey ) && ! hasExplicitPermissions( roleKey ) ) {
				return true;
			}
			return folders.some( ( folder ) =>
				permissions[ folder.id ]?.[ roleKey ]?.includes( actionKey )
			);
		},
		[ permissions, folders, hasDefaultFullAccess, hasExplicitPermissions ]
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
						'Configure which roles can access each folder. Administrators and Editors have full access by default. Click column headers to toggle all.',
						'vmfa-editorial-workflow'
					) }
				</p>

				<div className="vmfa-permissions-list">
					{ roles.map( ( role ) => {
						const isLocked = isUnconfigurable( role.key );
						const hasDefaultAccess = hasDefaultFullAccess( role.key );
						const hasExplicit = hasExplicitPermissions( role.key );
						// Show as full access if locked, or if has default access and no explicit permissions set
						const showAsFullAccess = isLocked || ( hasDefaultAccess && ! hasExplicit );

						return (
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
									{ showAsFullAccess ? (
										<span className="vmfa-role-full-access">
											{ __( 'Full access', 'vmfa-editorial-workflow' ) }
											{ hasDefaultAccess && ! isLocked && (
												<span className="vmfa-default-badge">{ __( 'default', 'vmfa-editorial-workflow' ) }</span>
											) }
										</span>
									) : (
										<span className="vmfa-role-count">
											{ countRolePermissions( role.key ) }{ ' ' }
											{ __( 'permissions', 'vmfa-editorial-workflow' ) }
										</span>
									) }
								</button>
								{ ! isLocked && (
								<DropdownMenu
									icon={ settings }
									label={ __( 'Bulk actions', 'vmfa-editorial-workflow' ) }
									controls={ getBulkMenuControls( role.key ) }
									className="vmfa-bulk-menu"
								/>
								) }
							</div>

							{ expandedRoles[ role.key ] && (
								<div className="vmfa-role-folders">
									{ isLocked && (
										<p className="vmfa-full-access-notice">
											<span className="dashicons dashicons-yes-alt"></span>
											{ __(
												'This role has full access to all folders. Permissions cannot be restricted.',
												'vmfa-editorial-workflow'
											) }
										</p>
									) }
									{ hasDefaultAccess && ! isLocked && ! hasExplicit && (
										<p className="vmfa-default-access-notice">
											<span className="dashicons dashicons-info"></span>
											{ __(
												'This role has full access by default. Configure permissions below to override.',
												'vmfa-editorial-workflow'
											) }
										</p>
									) }
									<table className="vmfa-folders-table">
										<thead>
											<tr>
												<th>{ __( 'Folder', 'vmfa-editorial-workflow' ) }</th>
												{ actions.map( ( action ) => {
													const allChecked = showAsFullAccess || hasAllActionPermissions( role.key, action.key );
													const someChecked = hasSomeActionPermissions( role.key, action.key );
													return (
														<th
															key={ action.key }
															className="vmfa-action-header-cell"
														>
															{ isLocked ? (
																<span className="vmfa-column-label">
																	<span className="vmfa-column-indicator all"></span>
																	{ action.label }
																</span>
															) : (
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
															) }
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
																checked={ showAsFullAccess || hasPermission( folder.id, role.key, action.key ) }
																onChange={ ( checked ) =>
																	togglePermission( folder.id, role.key, action.key, checked )
																}
																disabled={ isLocked }
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
					); } ) }
				</div>
			</div>
		</div>
	);
}
