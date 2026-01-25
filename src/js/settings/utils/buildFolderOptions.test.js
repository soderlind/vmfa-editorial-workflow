/**
 * Tests for buildFolderOptions utility.
 *
 * @package VmfaEditorialWorkflow
 */

import { describe, it, expect } from 'vitest';
import { buildFolderOptions } from './buildFolderOptions';

describe( 'buildFolderOptions', () => {
	const mockFolders = [
		{ id: 1, name: 'Needs Review', parent: 0, isSystem: true },
		{ id: 2, name: 'Approved', parent: 0, isSystem: true },
		{ id: 3, name: 'Department A', parent: 0, isSystem: false },
		{ id: 4, name: 'Sub Department', parent: 3, isSystem: false },
		{ id: 5, name: 'Deep Nested', parent: 4, isSystem: false },
	];

	it( 'should return empty array for empty folders', () => {
		const result = buildFolderOptions( [] );
		expect( result ).toEqual( [] );
	} );

	it( 'should add empty label option when provided', () => {
		const result = buildFolderOptions( mockFolders, {
			emptyLabel: 'Select a folder',
		} );

		expect( result[ 0 ] ).toEqual( { value: '', label: 'Select a folder' } );
	} );

	it( 'should build flat list from root folders', () => {
		const simpleFolders = [
			{ id: 1, name: 'Folder A', parent: 0, isSystem: false },
			{ id: 2, name: 'Folder B', parent: 0, isSystem: false },
		];

		const result = buildFolderOptions( simpleFolders );

		expect( result ).toEqual( [
			{ value: '1', label: 'Folder A' },
			{ value: '2', label: 'Folder B' },
		] );
	} );

	it( 'should build hierarchical options with prefix', () => {
		const result = buildFolderOptions( mockFolders );

		expect( result ).toContainEqual( { value: '3', label: 'Department A' } );
		expect( result ).toContainEqual( { value: '4', label: '— Sub Department' } );
		expect( result ).toContainEqual( { value: '5', label: '— — Deep Nested' } );
	} );

	it( 'should exclude system folders when excludeSystem is true', () => {
		const result = buildFolderOptions( mockFolders, {
			excludeSystem: true,
		} );

		const values = result.map( ( opt ) => opt.value );

		expect( values ).not.toContain( '1' );
		expect( values ).not.toContain( '2' );
		expect( values ).toContain( '3' );
	} );

	it( 'should include system folders by default', () => {
		const result = buildFolderOptions( mockFolders );

		const values = result.map( ( opt ) => opt.value );

		expect( values ).toContain( '1' );
		expect( values ).toContain( '2' );
	} );

	it( 'should apply custom filter function', () => {
		const result = buildFolderOptions( mockFolders, {
			filter: ( folder ) => folder.name.includes( 'Department' ),
		} );

		const labels = result.map( ( opt ) => opt.label );

		expect( labels ).toContain( 'Department A' );
		expect( labels ).toContain( '— Sub Department' );
		expect( labels ).not.toContain( 'Needs Review' );
	} );

	it( 'should convert folder id to string value', () => {
		const result = buildFolderOptions( mockFolders );

		result.forEach( ( opt ) => {
			expect( typeof opt.value ).toBe( 'string' );
		} );
	} );

	it( 'should combine emptyLabel with excludeSystem', () => {
		const result = buildFolderOptions( mockFolders, {
			emptyLabel: 'Choose folder',
			excludeSystem: true,
		} );

		expect( result[ 0 ] ).toEqual( { value: '', label: 'Choose folder' } );
		expect( result.length ).toBe( 4 ); // empty + 3 non-system folders
	} );
} );
