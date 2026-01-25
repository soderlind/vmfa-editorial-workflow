/**
 * Tests for PermissionMatrix component.
 *
 * @package VmfaEditorialWorkflow
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import PermissionMatrix from './PermissionMatrix';

// Mock WordPress packages
vi.mock( '@wordpress/element', () => ( {
	useCallback: ( fn ) => fn,
	useMemo: ( fn ) => fn(),
} ) );

vi.mock( '@wordpress/components', () => ( {
	CheckboxControl: ( { checked, onChange } ) => (
		<input
			type="checkbox"
			checked={ checked }
			onChange={ ( e ) => onChange( e.target.checked ) }
			data-testid="checkbox"
		/>
	),
} ) );

vi.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

describe( 'PermissionMatrix', () => {
	const mockRoles = [
		{ key: 'editor', name: 'Editor' },
		{ key: 'author', name: 'Author' },
	];

	const mockFolders = [
		{ id: 1, name: 'Needs Review', parent: 0, isSystem: true },
		{ id: 2, name: 'Approved', parent: 0, isSystem: false },
		{ id: 3, name: 'Sub Folder', parent: 2, isSystem: false },
	];

	const mockActions = [
		{ key: 'view', label: 'View' },
		{ key: 'move', label: 'Move' },
	];

	it( 'should render empty state when no roles provided', () => {
		render(
			<PermissionMatrix
				roles={ [] }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'No roles or folders available.' ) ).toBeInTheDocument();
	} );

	it( 'should render empty state when no folders provided', () => {
		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ [] }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'No roles or folders available.' ) ).toBeInTheDocument();
	} );

	it( 'should render table with folder column header', () => {
		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Folder' ) ).toBeInTheDocument();
	} );

	it( 'should render role headers', () => {
		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Editor' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Author' ) ).toBeInTheDocument();
	} );

	it( 'should render action headers for each role', () => {
		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		// Each action appears once per role
		const viewHeaders = screen.getAllByText( 'View' );
		const moveHeaders = screen.getAllByText( 'Move' );

		expect( viewHeaders ).toHaveLength( 2 );
		expect( moveHeaders ).toHaveLength( 2 );
	} );

	it( 'should render folder names', () => {
		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Needs Review' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Approved' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Sub Folder' ) ).toBeInTheDocument();
	} );

	it( 'should render checkboxes for each folder/role/action combination', () => {
		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ vi.fn() }
			/>
		);

		// 3 folders × 2 roles × 2 actions = 12 checkboxes
		const checkboxes = screen.getAllByTestId( 'checkbox' );
		expect( checkboxes ).toHaveLength( 12 );
	} );

	it( 'should check checkbox when permission exists', () => {
		const permissions = {
			1: {
				editor: [ 'view' ],
			},
		};

		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ permissions }
				onChange={ vi.fn() }
			/>
		);

		const checkboxes = screen.getAllByTestId( 'checkbox' );
		const checkedBoxes = checkboxes.filter( ( cb ) => cb.checked );

		expect( checkedBoxes ).toHaveLength( 1 );
	} );

	it( 'should call onChange when checkbox is toggled on', () => {
		const onChange = vi.fn();

		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ onChange }
			/>
		);

		const checkboxes = screen.getAllByTestId( 'checkbox' );
		fireEvent.click( checkboxes[ 0 ] );

		expect( onChange ).toHaveBeenCalled();
	} );

	it( 'should add action to permissions when toggled on', () => {
		const onChange = vi.fn();

		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ {} }
				onChange={ onChange }
			/>
		);

		const checkboxes = screen.getAllByTestId( 'checkbox' );
		fireEvent.click( checkboxes[ 0 ] );

		const newPermissions = onChange.mock.calls[ 0 ][ 0 ];
		expect( newPermissions ).toHaveProperty( '1' );
		expect( newPermissions[ 1 ] ).toHaveProperty( 'editor' );
		expect( newPermissions[ 1 ].editor ).toContain( 'view' );
	} );

	it( 'should remove action from permissions when toggled off', () => {
		const onChange = vi.fn();
		const permissions = {
			1: {
				editor: [ 'view', 'move' ],
			},
		};

		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ permissions }
				onChange={ onChange }
			/>
		);

		// First checkbox should be checked (folder 1, editor, view)
		const checkboxes = screen.getAllByTestId( 'checkbox' );
		fireEvent.click( checkboxes[ 0 ] ); // Toggle off

		const newPermissions = onChange.mock.calls[ 0 ][ 0 ];
		expect( newPermissions[ 1 ].editor ).not.toContain( 'view' );
		expect( newPermissions[ 1 ].editor ).toContain( 'move' );
	} );

	it( 'should clean up empty role when last action removed', () => {
		const onChange = vi.fn();
		const permissions = {
			1: {
				editor: [ 'view' ],
				author: [ 'view' ], // Keep another role so folder isn't cleaned up
			},
		};

		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ permissions }
				onChange={ onChange }
			/>
		);

		const checkboxes = screen.getAllByTestId( 'checkbox' );
		fireEvent.click( checkboxes[ 0 ] ); // Toggle off the only action for editor

		const newPermissions = onChange.mock.calls[ 0 ][ 0 ];
		expect( newPermissions[ 1 ] ).not.toHaveProperty( 'editor' );
		expect( newPermissions[ 1 ] ).toHaveProperty( 'author' ); // Author still exists
	} );

	it( 'should clean up empty folder when last role removed', () => {
		const onChange = vi.fn();
		const permissions = {
			1: {
				editor: [ 'view' ],
			},
		};

		render(
			<PermissionMatrix
				roles={ mockRoles }
				folders={ mockFolders }
				actions={ mockActions }
				permissions={ permissions }
				onChange={ onChange }
			/>
		);

		const checkboxes = screen.getAllByTestId( 'checkbox' );
		fireEvent.click( checkboxes[ 0 ] ); // Toggle off

		const newPermissions = onChange.mock.calls[ 0 ][ 0 ];
		expect( newPermissions ).not.toHaveProperty( '1' );
	} );
} );
