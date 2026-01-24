<?php
/**
 * AccessChecker tests.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Tests;

use Brain\Monkey\Functions;
use VmfaEditorialWorkflow\Services\AccessChecker;

/**
 * Test case for AccessChecker.
 */
class AccessCheckerTest extends \VMFA_TestCase {

	/**
	 * Test that administrators always have access.
	 *
	 * @return void
	 */
	public function test_administrators_have_full_access(): void {
		Functions\when( 'user_can' )->justReturn( true );

		$checker = new AccessChecker();

		$this->assertTrue( $checker->can_view_folder( 123, 1 ) );
	}

	/**
	 * Test that users without permissions are denied.
	 *
	 * @return void
	 */
	public function test_users_without_permissions_are_denied(): void {
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'roles' => [ 'editor' ] ] );
		Functions\when( 'get_term_meta' )->justReturn( [] );

		$checker = new AccessChecker();

		// No permissions configured and user doesn't have upload_files.
		$this->assertFalse( $checker->can_view_folder( 123, 2 ) );
	}

	/**
	 * Test role-based permission check.
	 *
	 * @return void
	 */
	public function test_role_based_permission_grants_access(): void {
		Functions\when( 'user_can' )->alias( function ( $user, $cap ) {
			return $cap !== 'manage_options'; // Not admin but has other caps.
		} );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'roles' => [ 'editor' ] ] );
		Functions\when( 'get_term_meta' )->justReturn( [ 'view', 'move' ] );

		$checker = new AccessChecker();

		$this->assertTrue( $checker->can_view_folder( 123, 2 ) );
	}

	/**
	 * Test that move permission is checked correctly.
	 *
	 * @return void
	 */
	public function test_can_move_to_folder_checks_move_action(): void {
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'roles' => [ 'author' ] ] );
		Functions\when( 'get_term_meta' )->justReturn( [ 'view' ] ); // Only view, not move.

		$checker = new AccessChecker();

		$this->assertFalse( $checker->can_move_to_folder( 456, 2 ) );
	}

	/**
	 * Test setting folder permissions.
	 *
	 * @return void
	 */
	public function test_set_folder_permissions(): void {
		$updated = false;
		Functions\when( 'update_term_meta' )->alias( function ( $term_id, $key, $value ) use ( &$updated ) {
			$updated = true;
			$this->assertEquals( 123, $term_id );
			$this->assertEquals( 'vmfa_access_editor', $key );
			$this->assertEquals( [ 'view', 'move' ], $value );
			return true;
		} );

		$checker = new AccessChecker();
		$result  = $checker->set_folder_permissions( 123, 'editor', [ 'view', 'move', 'invalid' ] );

		$this->assertTrue( $result );
		$this->assertTrue( $updated );
	}

	/**
	 * Test getting all folder permissions.
	 *
	 * @return void
	 */
	public function test_get_all_folder_permissions(): void {
		global $wp_roles;
		$wp_roles = new \WP_Roles();

		Functions\when( 'get_term_meta' )->alias( function ( $term_id, $key, $single ) {
			if ( 'vmfa_access_editor' === $key ) {
				return [ 'view', 'move' ];
			}
			return [];
		} );

		$checker     = new AccessChecker();
		$permissions = $checker->get_all_folder_permissions( 123 );

		$this->assertArrayHasKey( 'editor', $permissions );
		$this->assertEquals( [ 'view', 'move' ], $permissions['editor'] );
	}

	/**
	 * Test caching within request for non-admin users.
	 *
	 * @return void
	 */
	public function test_permission_results_are_cached(): void {
		$userDataCallCount = 0;
		Functions\when( 'user_can' )->justReturn( false ); // Not admin.
		Functions\when( 'get_userdata' )->alias( function () use ( &$userDataCallCount ) {
			$userDataCallCount++;
			return (object) [ 'roles' => [ 'editor' ] ];
		} );
		Functions\when( 'get_term_meta' )->justReturn( [ 'view', 'move' ] );

		$checker = new AccessChecker();

		// First call.
		$this->assertTrue( $checker->can_view_folder( 123, 1 ) );

		// Second call - should use cache (get_userdata not called again).
		$this->assertTrue( $checker->can_view_folder( 123, 1 ) );

		// get_userdata should only be called once due to caching.
		$this->assertEquals( 1, $userDataCallCount );
	}

	/**
	 * Test cache clearing.
	 *
	 * @return void
	 */
	public function test_clear_cache(): void {
		$callCount = 0;
		Functions\when( 'user_can' )->alias( function () use ( &$callCount ) {
			$callCount++;
			return true;
		} );

		$checker = new AccessChecker();

		$checker->can_view_folder( 123, 1 );
		$checker->clear_cache();
		$checker->can_view_folder( 123, 1 );

		// user_can should be called twice because cache was cleared.
		$this->assertEquals( 2, $callCount );
	}
}
