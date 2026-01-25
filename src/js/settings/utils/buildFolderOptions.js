/**
 * Build hierarchical folder options for select dropdowns.
 *
 * @package VmfaEditorialWorkflow
 */

/**
 * Build folder options with hierarchy.
 *
 * @param {Array}    folders       Array of folder objects with id, name, parent, isSystem.
 * @param {Object}   options       Configuration options.
 * @param {string}   options.emptyLabel  Label for empty/default option.
 * @param {boolean}  options.excludeSystem  Whether to exclude system folders.
 * @param {Function} options.filter  Optional filter function for folders.
 * @return {Array} Array of { value, label } options for SelectControl.
 */
export function buildFolderOptions( folders, {
	emptyLabel = '',
	excludeSystem = false,
	filter = null,
} = {} ) {
	const options = [];

	if ( emptyLabel ) {
		options.push( { value: '', label: emptyLabel } );
	}

	const addOptions = ( parentId, prefix = '' ) => {
		folders
			.filter( ( folder ) => {
				if ( folder.parent !== parentId ) {
					return false;
				}
				if ( excludeSystem && folder.isSystem ) {
					return false;
				}
				if ( filter && ! filter( folder ) ) {
					return false;
				}
				return true;
			} )
			.forEach( ( folder ) => {
				options.push( {
					value: String( folder.id ),
					label: prefix + folder.name,
				} );
				addOptions( folder.id, prefix + '— ' );
			} );
	};

	addOptions( 0 );

	return options;
}
