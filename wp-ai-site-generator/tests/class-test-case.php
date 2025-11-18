<?php
/**
 * Base test case class for WordPress AI Site Generator plugin
 *
 * @package WP_AI_Site_Generator\Tests
 */

namespace WP_AI_Site_Generator\Tests;

use WP_UnitTestCase;

/**
 * Base test case class
 *
 * All test classes should extend this class to inherit common functionality
 * and helper methods for testing the plugin.
 */
abstract class Test_Case extends WP_UnitTestCase {

	/**
	 * Plugin instance
	 *
	 * @var \WP_AI_Site_Generator
	 */
	protected $plugin;

	/**
	 * Set up test fixtures
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset any plugin options
		delete_option( 'wp_ai_site_generator_settings' );
		delete_option( 'wp_ai_site_generator_version' );

		// Clear any transients
		$this->clear_plugin_transients();

		// Initialize plugin instance
		$this->plugin = \WP_AI_Site_Generator::get_instance();
	}

	/**
	 * Tear down test fixtures
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up test data
		$this->cleanup_test_data();

		// Reset globals
		$this->reset_globals();
	}

	/**
	 * Clear all plugin transients
	 *
	 * @return void
	 */
	protected function clear_plugin_transients() {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_wp_ai_site_%',
				'_transient_timeout_wp_ai_site_%'
			)
		);
	}

	/**
	 * Clean up test data
	 *
	 * @return void
	 */
	protected function cleanup_test_data() {
		global $wpdb;

		// Clean up custom tables
		$tables = array(
			'wp_ai_site_generator_sessions',
			'wp_ai_site_generator_messages',
			'wp_ai_site_generator_designs',
			'wp_ai_site_generator_history',
		);

		foreach ( $tables as $table ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}{$table}'" ) ) {
				$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$table}" );
			}
		}
	}

	/**
	 * Reset global variables
	 *
	 * @return void
	 */
	protected function reset_globals() {
		$_GET    = array();
		$_POST   = array();
		$_REQUEST = array();
	}

	/**
	 * Create a test user with specific role
	 *
	 * @param string $role User role.
	 * @return int User ID
	 */
	protected function create_test_user( $role = 'administrator' ) {
		return $this->factory()->user->create(
			array(
				'role' => $role,
			)
		);
	}

	/**
	 * Create test API settings
	 *
	 * @param array $providers Provider settings.
	 * @return void
	 */
	protected function setup_api_settings( $providers = array() ) {
		$default_providers = array(
			'openai' => array(
				'api_key' => 'test-api-key',
				'model'   => 'gpt-3.5-turbo',
			),
		);

		$settings = wp_parse_args( $providers, $default_providers );
		update_option( 'wp_ai_site_generator_settings', $settings );
	}

	/**
	 * Assert that a hook has been registered
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return void
	 */
	protected function assertHookRegistered( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter;

		$this->assertArrayHasKey( $hook, $wp_filter );
		$this->assertArrayHasKey( $priority, $wp_filter[ $hook ]->callbacks );

		$found = false;
		foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $registered_callback ) {
			if ( $registered_callback['function'] === $callback ) {
				$found = true;
				$this->assertEquals( $accepted_args, $registered_callback['accepted_args'] );
				break;
			}
		}

		$this->assertTrue( $found, "Hook callback not found for {$hook}" );
	}

	/**
	 * Mock an AJAX request
	 *
	 * @param string $action AJAX action.
	 * @param array  $data   Request data.
	 * @return void
	 */
	protected function mock_ajax_request( $action, $data = array() ) {
		$_REQUEST['action'] = $action;
		$_REQUEST           = array_merge( $_REQUEST, $data );
		$_POST              = $_REQUEST;

		// Set AJAX constant
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}
	}

	/**
	 * Get private or protected property value
	 *
	 * @param object $object   Object instance.
	 * @param string $property Property name.
	 * @return mixed Property value
	 */
	protected function get_private_property( $object, $property ) {
		$reflection = new \ReflectionClass( $object );
		$property   = $reflection->getProperty( $property );
		$property->setAccessible( true );
		return $property->getValue( $object );
	}

	/**
	 * Call private or protected method
	 *
	 * @param object $object Object instance.
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed Method return value
	 */
	protected function call_private_method( $object, $method, $args = array() ) {
		$reflection = new \ReflectionClass( $object );
		$method     = $reflection->getMethod( $method );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}
}