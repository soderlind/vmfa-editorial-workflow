/**
 * Tests for InboxCard component.
 *
 * @package VmfaEditorialWorkflow
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import InboxCard from './InboxCard';

// Mock WordPress packages
vi.mock( '@wordpress/element', () => ( {
	useMemo: ( fn ) => fn(),
} ) );

vi.mock( '@wordpress/components', () => ( {
	SelectControl: ( { label, value, options, onChange } ) => (
		<div data-testid={ `select-${ label }` }>
			<label>{ label }</label>
			<select
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
				data-testid={ `select-input-${ label }` }
			>
				{ options.map( ( opt ) => (
					<option key={ opt.value } value={ opt.value }>
						{ opt.label }
					</option>
				) ) }
			</select>
		</div>
	),
} ) );

vi.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

describe( 'InboxCard', () => {
	const mockRoles = [
		{ key: 'editor', name: 'Editor' },
		{ key: 'author', name: 'Author' },
		{ key: 'contributor', name: 'Contributor' },
	];

	const mockFolders = [
		{ id: 1, name: 'Needs Review', parent: 0, isSystem: true },
		{ id: 2, name: 'Approved', parent: 0, isSystem: true },
		{ id: 3, name: 'Custom Folder', parent: 0, isSystem: false },
	];

	it( 'should return null when no roles provided', () => {
		const { container } = render(
			<InboxCard
				roles={ [] }
				folders={ mockFolders }
				inboxMap={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( container.firstChild ).toBeNull();
	} );

	it( 'should render card with header', () => {
		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Role Inbox Folders' ) ).toBeInTheDocument();
	} );

	it( 'should render a select for each role', () => {
		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect( screen.getByText( 'Editor' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Author' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Contributor' ) ).toBeInTheDocument();
	} );

	it( 'should display current inbox mapping value', () => {
		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ { editor: 3 } }
				onChange={ vi.fn() }
			/>
		);

		const editorSelect = screen.getByTestId( 'select-input-Editor' );
		expect( editorSelect.value ).toBe( '3' );
	} );

	it( 'should call onChange with new mapping when folder selected', () => {
		const onChange = vi.fn();

		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ {} }
				onChange={ onChange }
			/>
		);

		const editorSelect = screen.getByTestId( 'select-input-Editor' );
		fireEvent.change( editorSelect, { target: { value: '2' } } );

		expect( onChange ).toHaveBeenCalledWith( { editor: 2 } );
	} );

	it( 'should remove role from mapping when empty value selected', () => {
		const onChange = vi.fn();

		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ { editor: 3, author: 2 } }
				onChange={ onChange }
			/>
		);

		const editorSelect = screen.getByTestId( 'select-input-Editor' );
		fireEvent.change( editorSelect, { target: { value: '' } } );

		expect( onChange ).toHaveBeenCalledWith( { author: 2 } );
	} );

	it( 'should preserve other mappings when one is changed', () => {
		const onChange = vi.fn();

		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ { editor: 1 } }
				onChange={ onChange }
			/>
		);

		const authorSelect = screen.getByTestId( 'select-input-Author' );
		fireEvent.change( authorSelect, { target: { value: '3' } } );

		expect( onChange ).toHaveBeenCalledWith( { editor: 1, author: 3 } );
	} );

	it( 'should render description text', () => {
		render(
			<InboxCard
				roles={ mockRoles }
				folders={ mockFolders }
				inboxMap={ {} }
				onChange={ vi.fn() }
			/>
		);

		expect(
			screen.getByText( /Override the default workflow inbox/ )
		).toBeInTheDocument();
	} );
} );
