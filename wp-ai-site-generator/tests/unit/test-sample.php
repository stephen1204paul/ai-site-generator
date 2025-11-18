<?php
/**
 * Sample test case for WordPress AI Site Generator plugin
 *
 * @package WP_AI_Site_Generator\Tests\Unit
 */

namespace WP_AI_Site_Generator\Tests\Unit;

use WP_AI_Site_Generator\Tests\Test_Case;

/**
 * Sample test class
 *
 * This is a sample test to verify the test infrastructure is working correctly.
 */
class Test_Sample extends Test_Case {

	/**
	 * Test that the plugin main file is loaded
	 *
	 * @return void
	 */
	public function test_plugin_file_exists() {
		$plugin_file = WP_AI_SITE_GENERATOR_PLUGIN_DIR . '/wp-ai-site-generator.php';
		$this->assertFileExists( $plugin_file );
	}

	/**
	 * Test that the plugin is activated
	 *
	 * @return void
	 */
	public function test_plugin_is_activated() {
		$this->assertTrue( class_exists( 'WP_AI_Site_Generator' ) );
	}

	/**
	 * Test that plugin constants are defined
	 *
	 * @return void
	 */
	public function test_plugin_constants_defined() {
		$this->assertTrue( defined( 'WP_AI_SITE_GENERATOR_VERSION' ) );
		$this->assertTrue( defined( 'WP_AI_SITE_GENERATOR_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'WP_AI_SITE_GENERATOR_PLUGIN_URL' ) );
	}

	/**
	 * Test plugin instance is singleton
	 *
	 * @return void
	 */
	public function test_plugin_singleton_instance() {
		$instance1 = \WP_AI_Site_Generator::get_instance();
		$instance2 = \WP_AI_Site_Generator::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test plugin hooks are registered
	 *
	 * @return void
	 */
	public function test_plugin_hooks_registered() {
		// Test activation hook
		$this->assertTrue( has_action( 'plugins_loaded' ) );
		$this->assertTrue( has_action( 'init' ) );
	}

	/**
	 * Test plugin database tables exist
	 *
	 * @return void
	 */
	public function test_database_tables_exist() {
		global $wpdb;

		$tables = array(
			'sessions',
			'messages',
			'designs',
			'history',
		);

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . 'wp_ai_site_generator_' . $table;
			$result     = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
			$this->assertEquals( $table_name, $result, "Table {$table_name} does not exist" );
		}
	}

	/**
	 * Test admin menu is added
	 *
	 * @return void
	 */
	public function test_admin_menu_added() {
		// Login as admin
		$user_id = $this->create_test_user( 'administrator' );
		wp_set_current_user( $user_id );

		// Trigger admin menu
		do_action( 'admin_menu' );

		global $menu, $submenu;

		// Check if main menu exists
		$menu_exists = false;
		foreach ( $menu as $menu_item ) {
			if ( isset( $menu_item[2] ) && 'wp-ai-site-generator' === $menu_item[2] ) {
				$menu_exists = true;
				break;
			}
		}

		$this->assertTrue( $menu_exists, 'Admin menu not found' );
	}

	/**
	 * Test API providers are loaded
	 *
	 * @return void
	 */
	public function test_api_providers_loaded() {
		// Check if provider classes exist
		$this->assertTrue( class_exists( 'WP_AI_Site_Generator\Providers\OpenAI_Provider' ) );
		$this->assertTrue( class_exists( 'WP_AI_Site_Generator\Providers\Anthropic_Provider' ) );
		$this->assertTrue( class_exists( 'WP_AI_Site_Generator\Providers\Cohere_Provider' ) );
	}

	/**
	 * Test plugin settings are initialized
	 *
	 * @return void
	 */
	public function test_plugin_settings_initialized() {
		// Set test settings
		$this->setup_api_settings(
			array(
				'openai' => array(
					'api_key' => 'test-key',
					'model'   => 'gpt-4',
				),
			)
		);

		// Get settings
		$settings = get_option( 'wp_ai_site_generator_settings' );

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'openai', $settings );
		$this->assertEquals( 'test-key', $settings['openai']['api_key'] );
		$this->assertEquals( 'gpt-4', $settings['openai']['model'] );
	}

	/**
	 * Test REST API endpoints are registered
	 *
	 * @return void
	 */
	public function test_rest_api_endpoints_registered() {
		// Trigger REST API init
		do_action( 'rest_api_init' );

		// Get REST server
		$server = rest_get_server();
		$routes = $server->get_routes();

		// Check if our namespace exists
		$namespace_exists = false;
		foreach ( $routes as $route => $data ) {
			if ( strpos( $route, '/wp-ai-site-generator/v1' ) !== false ) {
				$namespace_exists = true;
				break;
			}
		}

		$this->assertTrue( $namespace_exists, 'REST API namespace not registered' );
	}
}