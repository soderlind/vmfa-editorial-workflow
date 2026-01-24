<?php
/**
 * InboxService tests.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Tests;

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use VmfaEditorialWorkflow\Services\AccessChecker;
use VmfaEditorialWorkflow\Services\InboxService;

/**
 * Test case for InboxService.
 */
class InboxServiceTest extends \VMFA_TestCase {

	/**
	 * Test getting inbox map returns empty array by default.
	 *
	 * @return void
	 */
	public function test_get_inbox_map_returns_empty_by_default(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$access_checker = $this->createMock( AccessChecker::class );
		$service        = new InboxService( $access_checker );

		$this->assertEquals( [], $service->get_inbox_map() );
	}

	/**
	 * Test setting inbox map.
	 *
	 * @return void
	 */
	public function test_set_inbox_map(): void {
		$saved = null;
		Functions\when( 'update_option' )->alias( function ( $option, $value ) use ( &$saved ) {
			$saved = $value;
			return true;
		} );

		$access_checker = $this->createMock( AccessChecker::class );
		$service        = new InboxService( $access_checker );

		$result = $service->set_inbox_map( [
			'editor' => 123,
			'author' => 456,
		] );

		$this->assertTrue( $result );
		$this->assertEquals( [ 'editor' => 123, 'author' => 456 ], $saved );
	}

	/**
	 * Test getting user inbox folder.
	 *
	 * @return void
	 */
	public function test_get_user_inbox_folder(): void {
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'roles' => [ 'editor' ] ] );
		Functions\when( 'get_option' )->justReturn( [ 'editor' => 123 ] );
		Functions\when( 'get_term' )->justReturn( new \WP_Term( (object) [ 'term_id' => 123, 'name' => 'Editor Inbox' ] ) );

		$access_checker = $this->createMock( AccessChecker::class );
		$service        = new InboxService( $access_checker );

		$this->assertEquals( 123, $service->get_user_inbox_folder( 2 ) );
	}

	/**
	 * Test inbox routing on upload.
	 *
	 * @return void
	 */
	public function test_route_to_inbox_assigns_folder(): void {
		Functions\when( 'wp_get_object_terms' )->justReturn( [] );
		Functions\when( 'get_current_user_id' )->justReturn( 2 );
		Functions\when( 'get_userdata' )->justReturn( (object) [ 'roles' => [ 'contributor' ] ] );
		Functions\when( 'get_option' )->justReturn( [ 'contributor' => 789 ] );
		Functions\when( 'get_term' )->justReturn( new \WP_Term( (object) [ 'term_id' => 789 ] ) );
		Functions\when( 'wp_set_object_terms' )->justReturn( [ 789 ] );

		Actions\expectDone( 'vmfa_inbox_assigned' )
			->once()
			->with( 100, 789, 2 );

		$access_checker = $this->createMock( AccessChecker::class );
		$access_checker->method( 'can_upload_to_folder' )->willReturn( true );

		$service  = new InboxService( $access_checker );
		$metadata = $service->route_to_inbox( [ 'file' => 'test.jpg' ], 100, 'create' );

		$this->assertIsArray( $metadata );
	}

	/**
	 * Test inbox routing skips non-create contexts.
	 *
	 * @return void
	 */
	public function test_route_to_inbox_skips_non_create_context(): void {
		$access_checker = $this->createMock( AccessChecker::class );
		$service        = new InboxService( $access_checker );

		$metadata = [ 'file' => 'test.jpg' ];
		$result   = $service->route_to_inbox( $metadata, 100, 'edit' );

		$this->assertEquals( $metadata, $result );
	}

	/**
	 * Test inbox routing skips if already assigned.
	 *
	 * @return void
	 */
	public function test_route_to_inbox_skips_if_already_assigned(): void {
		Functions\when( 'wp_get_object_terms' )->justReturn( [ 456 ] ); // Already has a folder.

		$access_checker = $this->createMock( AccessChecker::class );
		$service        = new InboxService( $access_checker );

		$metadata = [ 'file' => 'test.jpg' ];
		$result   = $service->route_to_inbox( $metadata, 100, 'create' );

		$this->assertEquals( $metadata, $result );
	}

	/**
	 * Test is_inbox_folder check.
	 *
	 * @return void
	 */
	public function test_is_inbox_folder(): void {
		Functions\when( 'get_option' )->justReturn( [ 'editor' => 123, 'author' => 123 ] );

		$access_checker = $this->createMock( AccessChecker::class );
		$service        = new InboxService( $access_checker );

		$this->assertTrue( $service->is_inbox_folder( 123 ) );
		$this->assertFalse( $service->is_inbox_folder( 999 ) );
	}
}
