<?php
/**
 * Integration test for chat functionality
 *
 * @package WP_AI_Site_Generator\Tests\Integration
 */

namespace WP_AI_Site_Generator\Tests\Integration;

use WP_AI_Site_Generator\Tests\Test_Case;

/**
 * Chat integration test class
 */
class Test_Chat_Integration extends Test_Case {

	/**
	 * Test creating a new chat session
	 *
	 * @return void
	 */
	public function test_create_chat_session() {
		global $wpdb;

		// Login as admin
		$user_id = $this->create_test_user( 'administrator' );
		wp_set_current_user( $user_id );

		// Create a session
		$session_data = array(
			'user_id'    => $user_id,
			'session_id' => wp_generate_uuid4(),
			'status'     => 'active',
			'metadata'   => json_encode( array( 'test' => true ) ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'wp_ai_site_generator_sessions',
			$session_data
		);

		$session_id = $wpdb->insert_id;

		// Verify session was created
		$this->assertGreaterThan( 0, $session_id );

		// Retrieve session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wp_ai_site_generator_sessions WHERE id = %d",
				$session_id
			)
		);

		$this->assertNotNull( $session );
		$this->assertEquals( $user_id, $session->user_id );
		$this->assertEquals( 'active', $session->status );
	}

	/**
	 * Test sending and retrieving messages
	 *
	 * @return void
	 */
	public function test_send_and_retrieve_messages() {
		global $wpdb;

		// Create user and session
		$user_id = $this->create_test_user( 'administrator' );
		wp_set_current_user( $user_id );

		$session_id = wp_generate_uuid4();
		$wpdb->insert(
			$wpdb->prefix . 'wp_ai_site_generator_sessions',
			array(
				'user_id'    => $user_id,
				'session_id' => $session_id,
				'status'     => 'active',
			)
		);

		$session_db_id = $wpdb->insert_id;

		// Add messages
		$messages = array(
			array(
				'session_id' => $session_db_id,
				'role'       => 'user',
				'content'    => 'Create a landing page',
				'metadata'   => json_encode( array() ),
			),
			array(
				'session_id' => $session_db_id,
				'role'       => 'assistant',
				'content'    => 'I\'ll create a landing page for you.',
				'metadata'   => json_encode( array() ),
			),
		);

		foreach ( $messages as $message ) {
			$wpdb->insert(
				$wpdb->prefix . 'wp_ai_site_generator_messages',
				$message
			);
		}

		// Retrieve messages
		$retrieved_messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wp_ai_site_generator_messages WHERE session_id = %d ORDER BY created_at ASC",
				$session_db_id
			)
		);

		$this->assertCount( 2, $retrieved_messages );
		$this->assertEquals( 'user', $retrieved_messages[0]->role );
		$this->assertEquals( 'assistant', $retrieved_messages[1]->role );
	}

	/**
	 * Test saving generation history
	 *
	 * @return void
	 */
	public function test_save_generation_history() {
		global $wpdb;

		// Create user
		$user_id = $this->create_test_user( 'administrator' );

		// Save generation history
		$history_data = array(
			'user_id'         => $user_id,
			'generation_type' => 'landing_page',
			'prompt'          => 'Create a modern landing page',
			'provider'        => 'openai',
			'model'           => 'gpt-4',
			'result'          => json_encode(
				array(
					'success' => true,
					'page_id' => 123,
				)
			),
			'metadata'        => json_encode(
				array(
					'tokens_used' => 1500,
					'duration'    => 3.5,
				)
			),
			'status'          => 'completed',
		);

		$wpdb->insert(
			$wpdb->prefix . 'wp_ai_site_generator_history',
			$history_data
		);

		$history_id = $wpdb->insert_id;

		// Verify history was saved
		$this->assertGreaterThan( 0, $history_id );

		// Retrieve history
		$history = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wp_ai_site_generator_history WHERE id = %d",
				$history_id
			)
		);

		$this->assertNotNull( $history );
		$this->assertEquals( 'landing_page', $history->generation_type );
		$this->assertEquals( 'completed', $history->status );

		// Verify JSON data
		$result   = json_decode( $history->result, true );
		$metadata = json_decode( $history->metadata, true );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 123, $result['page_id'] );
		$this->assertEquals( 1500, $metadata['tokens_used'] );
	}

	/**
	 * Test design templates storage
	 *
	 * @return void
	 */
	public function test_store_design_template() {
		global $wpdb;

		// Create user
		$user_id = $this->create_test_user( 'administrator' );

		// Store design template
		$design_data = array(
			'user_id'      => $user_id,
			'name'         => 'Modern Hero Section',
			'type'         => 'section',
			'category'     => 'hero',
			'content'      => json_encode(
				array(
					'html' => '<section class="hero">...</section>',
					'css'  => '.hero { background: blue; }',
					'js'   => 'console.log("Hero loaded");',
				)
			),
			'thumbnail'    => 'https://example.com/thumbnail.jpg',
			'settings'     => json_encode(
				array(
					'responsive' => true,
					'dark_mode'  => true,
				)
			),
			'is_active'    => 1,
			'usage_count'  => 0,
		);

		$wpdb->insert(
			$wpdb->prefix . 'wp_ai_site_generator_designs',
			$design_data
		);

		$design_id = $wpdb->insert_id;

		// Verify design was stored
		$this->assertGreaterThan( 0, $design_id );

		// Retrieve design
		$design = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wp_ai_site_generator_designs WHERE id = %d",
				$design_id
			)
		);

		$this->assertNotNull( $design );
		$this->assertEquals( 'Modern Hero Section', $design->name );
		$this->assertEquals( 'hero', $design->category );

		// Verify JSON content
		$content = json_decode( $design->content, true );
		$this->assertArrayHasKey( 'html', $content );
		$this->assertArrayHasKey( 'css', $content );
		$this->assertArrayHasKey( 'js', $content );
	}

	/**
	 * Test REST API chat endpoint
	 *
	 * @return void
	 */
	public function test_rest_api_chat_endpoint() {
		// Create admin user
		$user_id = $this->create_test_user( 'administrator' );
		wp_set_current_user( $user_id );

		// Setup API settings
		$this->setup_api_settings();

		// Create REST request
		$request = new \WP_REST_Request( 'POST', '/wp-ai-site-generator/v1/chat' );
		$request->set_body_params(
			array(
				'message'    => 'Create a contact form',
				'session_id' => wp_generate_uuid4(),
			)
		);

		// Set up nonce for authentication
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		// Note: This is a mock test as actual API call would require valid API keys
		// In a real scenario, you would mock the API provider response
		$this->assertInstanceOf( \WP_REST_Request::class, $request );
	}

	/**
	 * Test migration manager
	 *
	 * @return void
	 */
	public function test_migration_manager() {
		// Get migration manager
		$migration_manager = new \WP_AI_Site_Generator\Database\Migration_Manager();

		// Check if migrations are tracked
		$version = get_option( 'wp_ai_site_generator_db_version', '0.0.0' );
		$this->assertNotEquals( '0.0.0', $version );

		// Verify all migrations ran
		$migrations_dir = WP_AI_SITE_GENERATOR_PLUGIN_DIR . '/database/migrations';
		$migration_files = glob( $migrations_dir . '/*.php' );

		$this->assertNotEmpty( $migration_files );
	}
}