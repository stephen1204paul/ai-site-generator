<?php
/**
 * REST API Controller for the plugin.
 *
 * Handles all REST API endpoints for the plugin.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 * @since      1.0.0
 */

namespace WPAISiteGenerator\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAISiteGenerator\Database\DB_Handler;
use WPAISiteGenerator\Providers\Provider_Manager;
use WPAISiteGenerator\Generators\Block_Generator;

/**
 * REST API Controller class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 */
class REST_Controller extends WP_REST_Controller {

	/**
	 * The plugin name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The plugin name.
	 */
	private $plugin_name;

	/**
	 * The plugin version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The plugin version.
	 */
	private $version;

	/**
	 * The namespace.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $namespace    The namespace.
	 */
	protected $namespace;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name    The plugin name.
	 * @param    string    $version        The plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->namespace   = 'waisg/v1';
	}

	/**
	 * Register specialized endpoint handlers.
	 *
	 * @since    1.0.0
	 */
	private function register_specialized_endpoints() {
		// Include endpoint classes
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-chat-endpoint.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-generation-endpoint.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-provider-endpoint.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-template-endpoint.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-quality-endpoint.php';

		// Instantiate and register each endpoint handler
		$chat_endpoint = new Chat_Endpoint();
		$chat_endpoint->register_routes();

		$generation_endpoint = new Generation_Endpoint();
		$generation_endpoint->register_routes();

		$provider_endpoint = new Provider_Endpoint();
		$provider_endpoint->register_routes();

		$template_endpoint = new Template_Endpoint();
		$template_endpoint->register_routes();

		$quality_endpoint = new Quality_Endpoint();
		$quality_endpoint->register_routes();
	}

	/**
	 * Register the routes for the REST API.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		// Register specialized endpoint handlers
		$this->register_specialized_endpoints();

		// Legacy Generation endpoints (kept for backward compatibility)
		register_rest_route(
			$this->namespace,
			'/generate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_site' ),
					'permission_callback' => array( $this, 'generate_permissions_check' ),
					'args'                => $this->get_generate_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/generations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_generations' ),
					'permission_callback' => array( $this, 'get_generations_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/generations/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_generation' ),
					'permission_callback' => array( $this, 'get_generation_permissions_check' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_generation' ),
					'permission_callback' => array( $this, 'update_generation_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_generation' ),
					'permission_callback' => array( $this, 'delete_generation_permissions_check' ),
				),
			)
		);

		// Template endpoints - MOVED TO class-template-endpoint.php
		// Provider endpoints - MOVED TO class-provider-endpoint.php
		// These endpoints are now handled by specialized endpoint classes

		// Settings endpoints
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_settings_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_settings_permissions_check' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);

		// Usage statistics endpoints
		register_rest_route(
			$this->namespace,
			'/usage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_usage_stats' ),
					'permission_callback' => array( $this, 'get_usage_permissions_check' ),
					'args'                => array(
						'period' => array(
							'default' => 'month',
							'enum'    => array( 'day', 'week', 'month', 'year' ),
						),
					),
				),
			)
		);

		// Block generation endpoints
		register_rest_route(
			$this->namespace,
			'/blocks/generate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_block' ),
					'permission_callback' => array( $this, 'generate_block_permissions_check' ),
					'args'                => $this->get_block_generate_args(),
				),
			)
		);
	}

	/**
	 * Generate a site.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function generate_site( $request ) {
		try {
			$prompt   = $request->get_param( 'prompt' );
			$provider = $request->get_param( 'provider' );
			$options  = $request->get_param( 'options' );

			$db_handler = new DB_Handler();
			$provider_manager = new Provider_Manager();

			// Validate provider
			if ( ! $provider_manager->is_provider_available( $provider ) ) {
				return new WP_Error(
					'invalid_provider',
					__( 'The selected provider is not available', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			// Create generation record
			$generation_id = $db_handler->create_generation( array(
				'user_id'  => get_current_user_id(),
				'prompt'   => $prompt,
				'provider' => $provider,
				'status'   => 'pending',
				'metadata' => wp_json_encode( $options ),
			) );

			// Schedule generation
			wp_schedule_single_event(
				time(),
				'waisg_process_generation',
				array( $generation_id, $prompt, $provider, $options )
			);

			return new WP_REST_Response(
				array(
					'generation_id' => $generation_id,
					'status'        => 'pending',
					'message'       => __( 'Generation started successfully', 'wp-ai-site-generator' ),
				),
				201
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'generation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get all generations.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_generations( $request ) {
		try {
			$db_handler = new DB_Handler();

			$args = array(
				'user_id' => get_current_user_id(),
				'limit'   => $request->get_param( 'per_page' ),
				'offset'  => ( $request->get_param( 'page' ) - 1 ) * $request->get_param( 'per_page' ),
				'orderby' => $request->get_param( 'orderby' ),
				'order'   => $request->get_param( 'order' ),
			);

			$generations = $db_handler->get_generations( $args );
			$total = $db_handler->get_generations_count( $args );

			$response = new WP_REST_Response( $generations );
			$response->header( 'X-WP-Total', $total );
			$response->header( 'X-WP-TotalPages', ceil( $total / $args['limit'] ) );

			return $response;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get a single generation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_generation( $request ) {
		try {
			$db_handler = new DB_Handler();
			$generation = $db_handler->get_generation( $request->get_param( 'id' ) );

			if ( ! $generation ) {
				return new WP_Error(
					'not_found',
					__( 'Generation not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
			);
			}

			return new WP_REST_Response( $generation );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Update a generation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_generation( $request ) {
		try {
			$db_handler = new DB_Handler();
			$id = $request->get_param( 'id' );

			$data = array();
			if ( $request->has_param( 'status' ) ) {
				$data['status'] = $request->get_param( 'status' );
			}
			if ( $request->has_param( 'metadata' ) ) {
				$data['metadata'] = wp_json_encode( $request->get_param( 'metadata' ) );
			}

			$updated = $db_handler->update_generation( $id, $data );

			if ( ! $updated ) {
				return new WP_Error(
					'update_failed',
					__( 'Failed to update generation', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Generation updated successfully', 'wp-ai-site-generator' ),
				)
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'update_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete a generation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function delete_generation( $request ) {
		try {
			$db_handler = new DB_Handler();
			$deleted = $db_handler->delete_generation( $request->get_param( 'id' ) );

			if ( ! $deleted ) {
				return new WP_Error(
					'delete_failed',
					__( 'Failed to delete generation', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Generation deleted successfully', 'wp-ai-site-generator' ),
				)
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'delete_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get all templates.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_templates( $request ) {
		try {
			$db_handler = new DB_Handler();
			$templates = $db_handler->get_templates();

			return new WP_REST_Response( $templates );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Create a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function create_template( $request ) {
		try {
			$db_handler = new DB_Handler();

			$template_data = array(
				'name'            => $request->get_param( 'name' ),
				'slug'            => sanitize_title( $request->get_param( 'name' ) ),
				'description'     => $request->get_param( 'description' ),
				'prompt_template' => $request->get_param( 'prompt_template' ),
				'configuration'   => wp_json_encode( $request->get_param( 'configuration' ) ),
				'category'        => $request->get_param( 'category' ),
				'created_by'      => get_current_user_id(),
			);

			$template_id = $db_handler->create_template( $template_data );

			return new WP_REST_Response(
				array(
					'id'      => $template_id,
					'message' => __( 'Template created successfully', 'wp-ai-site-generator' ),
				),
				201
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'create_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get available providers.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_providers( $request ) {
		try {
			$provider_manager = new Provider_Manager();
			$providers = $provider_manager->get_available_providers();

			return new WP_REST_Response( $providers );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Test a provider connection.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function test_provider( $request ) {
		try {
			$provider_name = $request->get_param( 'provider' );
			$provider_manager = new Provider_Manager();

			$result = $provider_manager->test_provider( $provider_name );

			if ( $result['success'] ) {
				return new WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Provider connection successful', 'wp-ai-site-generator' ),
						'details' => $result['details'],
					)
				);
			} else {
				return new WP_Error(
					'test_failed',
					$result['error'],
					array( 'status' => 400 )
				);
			}
		} catch ( \Exception $e ) {
			return new WP_Error(
				'test_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get plugin settings.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_settings( $request ) {
		$settings = array(
			'general'    => get_option( 'waisg_settings', array() ),
			'providers'  => get_option( 'waisg_provider_settings', array() ),
			'generation' => get_option( 'waisg_generation_defaults', array() ),
			'features'   => get_option( 'waisg_features', array() ),
			'limits'     => get_option( 'waisg_usage_limits', array() ),
		);

		// Remove sensitive data (API keys)
		if ( isset( $settings['providers']['providers'] ) ) {
			foreach ( $settings['providers']['providers'] as $provider => &$config ) {
				if ( isset( $config['api_key'] ) ) {
					$config['api_key'] = ! empty( $config['api_key'] ) ? '********' : '';
				}
			}
		}

		return new WP_REST_Response( $settings );
	}

	/**
	 * Update plugin settings.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$section = $request->get_param( 'section' );
		$settings = $request->get_param( 'settings' );

		$option_map = array(
			'general'    => 'waisg_settings',
			'providers'  => 'waisg_provider_settings',
			'generation' => 'waisg_generation_defaults',
			'features'   => 'waisg_features',
			'limits'     => 'waisg_usage_limits',
		);

		if ( ! isset( $option_map[ $section ] ) ) {
			return new WP_Error(
				'invalid_section',
				__( 'Invalid settings section', 'wp-ai-site-generator' ),
				array( 'status' => 400 )
			);
		}

		// For provider settings, preserve API keys if not provided
		if ( $section === 'providers' && isset( $settings['providers'] ) ) {
			$current = get_option( $option_map[ $section ], array() );
			foreach ( $settings['providers'] as $provider => &$config ) {
				if ( isset( $config['api_key'] ) && $config['api_key'] === '********' ) {
					$config['api_key'] = $current['providers'][ $provider ]['api_key'] ?? '';
				}
			}
		}

		$updated = update_option( $option_map[ $section ], $settings );

		if ( $updated ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Settings updated successfully', 'wp-ai-site-generator' ),
				)
			);
		} else {
			return new WP_Error(
				'update_failed',
				__( 'No changes were made', 'wp-ai-site-generator' ),
				array( 'status' => 400 )
			);
		}
	}

	/**
	 * Get usage statistics.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_usage_stats( $request ) {
		try {
			$period = $request->get_param( 'period' );
			$db_handler = new DB_Handler();

			$stats = $db_handler->get_usage_statistics( $period );

			return new WP_REST_Response( $stats );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Generate a block.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function generate_block( $request ) {
		try {
			$prompt = $request->get_param( 'prompt' );
			$type = $request->get_param( 'type' );
			$provider = $request->get_param( 'provider' );

			$provider_manager = new Provider_Manager();
			$block_generator = new Block_Generator( $provider_manager );

			$block = $block_generator->generate( $prompt, $type, $provider );

			return new WP_REST_Response(
				array(
					'block'   => $block,
					'message' => __( 'Block generated successfully', 'wp-ai-site-generator' ),
				)
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'generation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get arguments for generation endpoint.
	 *
	 * @since    1.0.0
	 * @return   array    Arguments.
	 */
	private function get_generate_args() {
		return array(
			'prompt' => array(
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => function( $param ) {
					return ! empty( $param );
				},
			),
			'provider' => array(
				'required' => false,
				'type'     => 'string',
				'default'  => 'openai',
			),
			'options' => array(
				'required' => false,
				'type'     => 'object',
				'default'  => array(),
			),
		);
	}

	/**
	 * Get arguments for template endpoints.
	 *
	 * @since    1.0.0
	 * @return   array    Arguments.
	 */
	private function get_template_args() {
		return array(
			'name' => array(
				'required' => true,
				'type'     => 'string',
			),
			'description' => array(
				'type' => 'string',
			),
			'prompt_template' => array(
				'required' => true,
				'type'     => 'string',
			),
			'configuration' => array(
				'type' => 'object',
			),
			'category' => array(
				'type'    => 'string',
				'default' => 'general',
			),
		);
	}

	/**
	 * Get arguments for settings endpoint.
	 *
	 * @since    1.0.0
	 * @return   array    Arguments.
	 */
	private function get_settings_args() {
		return array(
			'section' => array(
				'required' => true,
				'type'     => 'string',
				'enum'     => array( 'general', 'providers', 'generation', 'features', 'limits' ),
			),
			'settings' => array(
				'required' => true,
				'type'     => 'object',
			),
		);
	}

	/**
	 * Get arguments for block generation.
	 *
	 * @since    1.0.0
	 * @return   array    Arguments.
	 */
	private function get_block_generate_args() {
		return array(
			'prompt' => array(
				'required' => true,
				'type'     => 'string',
			),
			'type' => array(
				'type'    => 'string',
				'default' => 'paragraph',
				'enum'    => array( 'paragraph', 'heading', 'list', 'image', 'button', 'columns', 'custom' ),
			),
			'provider' => array(
				'type'    => 'string',
				'default' => 'openai',
			),
		);
	}

	/**
	 * Check if current user can generate sites.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function generate_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can view generations.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_generations_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can view a generation.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_generation_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can update a generation.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function update_generation_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can delete a generation.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function delete_generation_permissions_check() {
		return current_user_can( 'delete_posts' );
	}

	/**
	 * Check if current user can view templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_templates_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can view a template.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_template_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can create templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function create_template_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can update templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function update_template_permissions_check() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can delete templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function delete_template_permissions_check() {
		return current_user_can( 'delete_posts' );
	}

	/**
	 * Check if current user can view providers.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_providers_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can test providers.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function test_provider_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can view settings.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_settings_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can update settings.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function update_settings_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can view usage stats.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_usage_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can generate blocks.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function generate_block_permissions_check() {
		return current_user_can( 'edit_posts' );
	}
}