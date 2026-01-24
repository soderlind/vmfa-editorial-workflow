<?php
/**
 * PHPUnit bootstrap file.
 *
 * Uses Brain Monkey for mocking WordPress functions.
 *
 * @package VmfaEditorialWorkflow
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Brain Monkey setup.
use Brain\Monkey;

// Define WordPress constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'VMFA_EDITORIAL_WORKFLOW_VERSION' ) ) {
	define( 'VMFA_EDITORIAL_WORKFLOW_VERSION', '1.0.0' );
}

if ( ! defined( 'VMFA_EDITORIAL_WORKFLOW_PATH' ) ) {
	define( 'VMFA_EDITORIAL_WORKFLOW_PATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'VMFA_EDITORIAL_WORKFLOW_URL' ) ) {
	define( 'VMFA_EDITORIAL_WORKFLOW_URL', 'https://example.com/wp-content/plugins/vmfa-editorial-workflow/' );
}

// Mock VMF constants.
if ( ! defined( 'VMFO_VERSION' ) ) {
	define( 'VMFO_VERSION', '1.0.0' );
}

/**
 * Base test case class with Brain Monkey integration.
 */
abstract class VMFA_TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Only stub truly constant functions that won't need mocking.
		// Translation and escaping functions.
		Monkey\Functions\stubs( [
			'__'                  => fn( $text, $domain = 'default' ) => $text,
			'esc_html__'          => fn( $text, $domain = 'default' ) => $text,
			'esc_attr__'          => fn( $text, $domain = 'default' ) => $text,
			'esc_html'            => fn( $text ) => htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ),
			'esc_attr'            => fn( $text ) => htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ),
			'esc_url'             => fn( $url ) => filter_var( $url, FILTER_SANITIZE_URL ),
			'wp_kses_post'        => fn( $text ) => $text,
			'sanitize_key'        => fn( $key ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ),
			'sanitize_text_field' => fn( $text ) => trim( strip_tags( $text ) ),
			'absint'              => fn( $num ) => abs( (int) $num ),
			'wp_parse_args'       => fn( $args, $defaults ) => array_merge( $defaults, $args ),
			'is_admin'            => fn() => true,
			'is_wp_error'         => fn( $thing ) => $thing instanceof \WP_Error,
			'plugin_dir_path'     => fn( $file ) => dirname( $file ) . '/',
			'plugin_dir_url'      => fn( $file ) => 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/',
		] );
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Stub common WordPress functions with default behaviors.
	 *
	 * Call this in tests that don't need specific mocking for these functions.
	 *
	 * @return void
	 */
	protected function stubWordPressFunctions(): void {
		Monkey\Functions\stubs( [
			'current_user_can'    => fn( $cap ) => false,
			'get_current_user_id' => fn() => 1,
			'user_can'            => fn( $user, $cap ) => false,
			'get_userdata'        => fn( $id ) => (object) [ 'roles' => [] ],
			'get_option'          => fn( $option, $default = false ) => $default,
			'update_option'       => fn( $option, $value ) => true,
			'delete_option'       => fn( $option ) => true,
			'get_term_meta'       => fn( $term_id, $key, $single = false ) => $single ? '' : [],
			'update_term_meta'    => fn( $term_id, $key, $value ) => true,
			'delete_term_meta'    => fn( $term_id, $key ) => true,
			'get_transient'       => fn( $transient ) => false,
			'set_transient'       => fn( $transient, $value, $expiration = 0 ) => true,
			'delete_transient'    => fn( $transient ) => true,
			'get_term_by'         => fn( $field, $value, $taxonomy ) => false,
			'wp_insert_term'      => fn( $name, $taxonomy, $args = [] ) => [ 'term_id' => 1, 'term_taxonomy_id' => 1 ],
			'get_terms'           => fn( $args ) => [],
		] );
	}
}

/**
 * Mock WP_Error class for tests.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private array $errors   = [];
		private array $error_data = [];

		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		public function get_error_code(): string {
			$codes = $this->get_error_codes();
			return $codes[0] ?? '';
		}

		public function get_error_messages( string $code = '' ): array {
			if ( empty( $code ) ) {
				$all_messages = [];
				foreach ( $this->errors as $messages ) {
					$all_messages = array_merge( $all_messages, $messages );
				}
				return $all_messages;
			}
			return $this->errors[ $code ] ?? [];
		}

		public function get_error_message( string $code = '' ): string {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			$messages = $this->get_error_messages( $code );
			return $messages[0] ?? '';
		}

		public function get_error_data( string $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}

		public function add( string $code, string $message, $data = '' ): void {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function has_errors(): bool {
			return ! empty( $this->errors );
		}
	}
}

/**
 * Mock WP_Term class for tests.
 */
if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public int $term_id;
		public string $name;
		public string $slug;
		public string $term_group;
		public int $term_taxonomy_id;
		public string $taxonomy;
		public string $description;
		public int $parent;
		public int $count;

		public function __construct( object $term = null ) {
			if ( $term ) {
				foreach ( get_object_vars( $term ) as $key => $value ) {
					$this->$key = $value;
				}
			}
		}
	}
}

/**
 * Mock WP_Roles class for tests.
 */
if ( ! class_exists( 'WP_Roles' ) ) {
	class WP_Roles {
		public array $roles = [
			'administrator' => [
				'name'         => 'Administrator',
				'capabilities' => [ 'manage_options' => true, 'upload_files' => true ],
			],
			'editor'        => [
				'name'         => 'Editor',
				'capabilities' => [ 'upload_files' => true ],
			],
			'author'        => [
				'name'         => 'Author',
				'capabilities' => [ 'upload_files' => true ],
			],
			'contributor'   => [
				'name'         => 'Contributor',
				'capabilities' => [ 'upload_files' => true ],
			],
			'subscriber'    => [
				'name'         => 'Subscriber',
				'capabilities' => [],
			],
		];
	}
}
