<?php
/**
 * WorkflowState tests.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

namespace VmfaEditorialWorkflow\Tests;

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use VmfaEditorialWorkflow\Services\AccessChecker;
use VmfaEditorialWorkflow\WorkflowState;

/**
 * Test case for WorkflowState.
 */
class WorkflowStateTest extends \VMFA_TestCase {

	/**
	 * Test is_system_folder check.
	 *
	 * @return void
	 */
	public function test_is_system_folder(): void {
		Functions\when( 'get_term_meta' )->alias( function ( $term_id, $key, $single ) {
			if ( 123 === $term_id && 'vmfa_system_folder' === $key ) {
				return true;
			}
			return false;
		} );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertTrue( $workflow->is_system_folder( 123 ) );
	}

	/**
	 * Test protect_system_folders returns error for system folders.
	 *
	 * @return void
	 */
	public function test_protect_system_folders_blocks_deletion(): void {
		Functions\when( 'get_term_meta' )->alias( function ( $term_id, $key, $single ) {
			if ( 123 === $term_id && 'vmfa_system_folder' === $key ) {
				return true;
			}
			return false;
		} );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$term   = new \WP_Term( (object) [ 'term_id' => 123, 'name' => 'Needs Review' ] );
		$result = $workflow->protect_system_folders( true, 123, $term );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'vmfa_system_folder', $result->get_error_code() );
	}

	/**
	 * Test protect_system_folders allows deletion of non-system folders.
	 *
	 * @return void
	 */
	public function test_protect_system_folders_allows_regular_folders(): void {
		Functions\when( 'get_term_meta' )->justReturn( false );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$term   = new \WP_Term( (object) [ 'term_id' => 456, 'name' => 'My Folder' ] );
		$result = $workflow->protect_system_folders( true, 456, $term );

		$this->assertTrue( $result );
	}

	/**
	 * Test getting needs review folder.
	 *
	 * @return void
	 */
	public function test_get_needs_review_folder(): void {
		Functions\when( 'get_term_by' )->justReturn( new \WP_Term( (object) [ 'term_id' => 100 ] ) );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertEquals( 100, $workflow->get_needs_review_folder() );
	}

	/**
	 * Test mark_needs_review assigns to correct folder.
	 *
	 * @return void
	 */
	public function test_mark_needs_review(): void {
		Functions\when( 'get_term_by' )->justReturn( new \WP_Term( (object) [ 'term_id' => 100 ] ) );
		Functions\when( 'wp_set_object_terms' )->justReturn( [ 100 ] );

		Actions\expectDone( 'vmfa_marked_needs_review' )
			->once()
			->with( 50, 100 );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertTrue( $workflow->mark_needs_review( 50 ) );
	}

	/**
	 * Test mark_approved assigns to correct folder.
	 *
	 * @return void
	 */
	public function test_mark_approved(): void {
		Functions\when( 'get_term_by' )->alias( function ( $field, $value, $taxonomy ) {
			if ( 'vmfa-approved' === $value ) {
				return new \WP_Term( (object) [ 'term_id' => 101 ] );
			}
			return false;
		} );
		Functions\when( 'wp_set_object_terms' )->justReturn( [ 101 ] );

		Actions\expectDone( 'vmfa_approved' )
			->once()
			->with( 50, 101 );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertTrue( $workflow->mark_approved( 50 ) );
	}

	/**
	 * Test is_workflow_enabled returns option value.
	 *
	 * @return void
	 */
	public function test_is_workflow_enabled(): void {
		Functions\when( 'get_option' )->justReturn( true );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertTrue( $workflow->is_workflow_enabled() );
	}

	/**
	 * Test set_workflow_enabled updates option.
	 *
	 * @return void
	 */
	public function test_set_workflow_enabled(): void {
		$updated = false;
		Functions\when( 'update_option' )->alias( function ( $option, $value ) use ( &$updated ) {
			$updated = true;
			$this->assertEquals( 'vmfa_workflow_enabled', $option );
			$this->assertFalse( $value );
			return true;
		} );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertTrue( $workflow->set_workflow_enabled( false ) );
		$this->assertTrue( $updated );
	}

	/**
	 * Test review count caching.
	 *
	 * @return void
	 */
	public function test_get_review_count_uses_transient(): void {
		Functions\when( 'get_transient' )->justReturn( 5 );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$this->assertEquals( 5, $workflow->get_review_count() );
	}

	/**
	 * Test invalidate_review_count_cache.
	 *
	 * @return void
	 */
	public function test_invalidate_review_count_cache(): void {
		$deleted = false;
		Functions\when( 'delete_transient' )->alias( function ( $transient ) use ( &$deleted ) {
			$deleted = true;
			$this->assertEquals( 'vmfa_editorial_workflow_review_count', $transient );
			return true;
		} );

		$access_checker = $this->createMock( AccessChecker::class );
		$workflow       = new WorkflowState( $access_checker );

		$workflow->invalidate_review_count_cache();

		$this->assertTrue( $deleted );
	}
}
