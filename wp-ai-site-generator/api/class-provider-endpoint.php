<?php
/**
 * Provider management REST API endpoints.
 *
 * Handles all provider-related REST API endpoints including testing and usage.
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

/**
 * Provider Endpoint class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 */
class Provider_Endpoint extends WP_REST_Controller {

	/**
	 * The namespace.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $namespace    The namespace.
	 */
	protected $namespace;

	/**
	 * Database handler.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler instance.
	 */
	private $db_handler;

	/**
	 * Provider manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Provider_Manager    $provider_manager    Provider manager instance.
	 */
	private $provider_manager;

	/**
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->namespace = 'waisg/v1';
		$this->db_handler = new DB_Handler();
		$this->provider_manager = new Provider_Manager();
	}

	/**
	 * Register the routes for the provider endpoints.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		// List available providers
		register_rest_route(
			$this->namespace,
			'/providers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_providers' ),
					'permission_callback' => array( $this, 'view_providers_permissions_check' ),
					'args'                => array(
						'include_status' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'include_usage' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		// Test provider connection
		register_rest_route(
			$this->namespace,
			'/providers/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'test_provider' ),
					'permission_callback' => array( $this, 'test_provider_permissions_check' ),
					'args'                => $this->get_test_provider_args(),
				),
			)
		);

		// Get usage statistics
		register_rest_route(
			$this->namespace,
			'/providers/usage',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_usage_statistics' ),
					'permission_callback' => array( $this, 'view_usage_permissions_check' ),
					'args'                => $this->get_usage_stats_args(),
				),
			)
		);

		// Get provider details
		register_rest_route(
			$this->namespace,
			'/providers/(?P<provider>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_provider_details' ),
					'permission_callback' => array( $this, 'view_providers_permissions_check' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'validate_callback' => function( $param ) {
								return ! empty( $param );
							},
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Update provider configuration
		register_rest_route(
			$this->namespace,
			'/providers/(?P<provider>[\w-]+)/config',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_provider_config' ),
					'permission_callback' => array( $this, 'configure_provider_permissions_check' ),
					'args'                => $this->get_provider_config_args(),
				),
			)
		);

		// Get provider models
		register_rest_route(
			$this->namespace,
			'/providers/(?P<provider>[\w-]+)/models',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_provider_models' ),
					'permission_callback' => array( $this, 'view_providers_permissions_check' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Get provider limits
		register_rest_route(
			$this->namespace,
			'/providers/(?P<provider>[\w-]+)/limits',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_provider_limits' ),
					'permission_callback' => array( $this, 'view_providers_permissions_check' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Reset provider usage
		register_rest_route(
			$this->namespace,
			'/providers/(?P<provider>[\w-]+)/reset-usage',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_provider_usage' ),
					'permission_callback' => array( $this, 'manage_provider_permissions_check' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
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
			$include_status = $request->get_param( 'include_status' );
			$include_usage = $request->get_param( 'include_usage' );

			$providers = $this->provider_manager->get_available_providers();
			$response_data = array();

			foreach ( $providers as $provider_name => $provider_info ) {
				$provider_data = array(
					'id'          => $provider_name,
					'name'        => $provider_info['name'],
					'description' => $provider_info['description'],
					'supported'   => $provider_info['supported_features'] ?? array(),
					'configured'  => $this->provider_manager->is_provider_configured( $provider_name ),
				);

				if ( $include_status ) {
					$provider_data['status'] = $this->get_provider_status( $provider_name );
				}

				if ( $include_usage && current_user_can( 'manage_options' ) ) {
					$provider_data['usage'] = $this->get_provider_usage_summary( $provider_name );
				}

				// Add capabilities
				$provider_data['capabilities'] = array(
					'chat'       => $provider_info['supports_chat'] ?? true,
					'completion' => $provider_info['supports_completion'] ?? true,
					'embedding'  => $provider_info['supports_embedding'] ?? false,
					'image'      => $provider_info['supports_image'] ?? false,
					'streaming'  => $provider_info['supports_streaming'] ?? false,
				);

				// Add pricing info if available
				if ( isset( $provider_info['pricing'] ) ) {
					$provider_data['pricing'] = $provider_info['pricing'];
				}

				$response_data[] = $provider_data;
			}

			return new WP_REST_Response( $response_data );

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
			// Verify nonce
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'invalid_nonce',
					__( 'Invalid security token', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			$provider = $request->get_param( 'provider' );
			$api_key = $request->get_param( 'api_key' );
			$config = $request->get_param( 'config' );

			// Temporarily set the API key if provided
			$temp_config = null;
			if ( $api_key ) {
				$temp_config = array( 'api_key' => $api_key );
				if ( $config ) {
					$temp_config = array_merge( $config, $temp_config );
				}
			}

			// Test the provider
			$result = $this->provider_manager->test_provider( $provider, $temp_config );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( ! $result['success'] ) {
				return new WP_Error(
					'test_failed',
					$result['error'] ?? __( 'Provider test failed', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			// Log the test
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'provider_tested',
				'object_id'   => 0,
				'object_type' => 'provider',
				'details'     => wp_json_encode( array(
					'provider' => $provider,
					'success'  => true,
				) ),
			) );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Provider connection successful', 'wp-ai-site-generator' ),
					'details' => array(
						'provider'      => $provider,
						'models'        => $result['models'] ?? array(),
						'response_time' => $result['response_time'] ?? null,
						'capabilities'  => $result['capabilities'] ?? array(),
					),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'test_failed',
				$e->getMessage(),
				array( 'status' => 500 )
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
	public function get_usage_statistics( $request ) {
		try {
			$provider = $request->get_param( 'provider' );
			$period = $request->get_param( 'period' );
			$start_date = $request->get_param( 'start_date' );
			$end_date = $request->get_param( 'end_date' );
			$user_id = $request->get_param( 'user_id' );
			$group_by = $request->get_param( 'group_by' );

			// Build query args
			$args = array(
				'period' => $period,
			);

			if ( $provider ) {
				$args['provider'] = sanitize_text_field( $provider );
			}

			if ( $start_date ) {
				$args['start_date'] = sanitize_text_field( $start_date );
			}

			if ( $end_date ) {
				$args['end_date'] = sanitize_text_field( $end_date );
			}

			// Only admins can view other users' usage
			if ( current_user_can( 'manage_options' ) && $user_id ) {
				$args['user_id'] = absint( $user_id );
			} else {
				$args['user_id'] = get_current_user_id();
			}

			// Get usage data
			$usage_data = $this->db_handler->get_usage_statistics( $args );

			// Process and group data
			$processed_data = $this->process_usage_data( $usage_data, $group_by );

			// Calculate totals
			$totals = $this->calculate_usage_totals( $usage_data );

			// Get limits
			$limits = $this->get_usage_limits( $provider );

			// Calculate remaining
			$remaining = $this->calculate_remaining_usage( $totals, $limits );

			return new WP_REST_Response(
				array(
					'period'    => $period,
					'data'      => $processed_data,
					'totals'    => $totals,
					'limits'    => $limits,
					'remaining' => $remaining,
					'metadata'  => array(
						'start_date' => $args['start_date'] ?? null,
						'end_date'   => $args['end_date'] ?? null,
						'provider'   => $provider,
						'user_id'    => $args['user_id'],
					),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get provider details.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_provider_details( $request ) {
		try {
			$provider_name = $request->get_param( 'provider' );

			$provider = $this->provider_manager->get_provider( $provider_name );
			if ( ! $provider ) {
				return new WP_Error(
					'not_found',
					__( 'Provider not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			$info = $this->provider_manager->get_provider_info( $provider_name );

			// Get configuration (hide sensitive data)
			$config = get_option( 'waisg_provider_settings', array() );
			$provider_config = $config['providers'][ $provider_name ] ?? array();

			if ( isset( $provider_config['api_key'] ) ) {
				$provider_config['api_key'] = ! empty( $provider_config['api_key'] ) ? '********' : '';
			}

			// Get models if configured
			$models = array();
			if ( $this->provider_manager->is_provider_configured( $provider_name ) ) {
				$models = $provider->get_available_models();
			}

			// Get usage for current user
			$usage = $this->get_provider_usage_summary( $provider_name );

			return new WP_REST_Response(
				array(
					'id'           => $provider_name,
					'info'         => $info,
					'configured'   => $this->provider_manager->is_provider_configured( $provider_name ),
					'config'       => $provider_config,
					'models'       => $models,
					'usage'        => $usage,
					'status'       => $this->get_provider_status( $provider_name ),
					'capabilities' => $provider->get_capabilities(),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Update provider configuration.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_provider_config( $request ) {
		try {
			// Verify nonce
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'invalid_nonce',
					__( 'Invalid security token', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			$provider = $request->get_param( 'provider' );
			$api_key = $request->get_param( 'api_key' );
			$config = $request->get_param( 'config' );
			$enabled = $request->get_param( 'enabled' );

			// Get current settings
			$settings = get_option( 'waisg_provider_settings', array() );
			if ( ! isset( $settings['providers'] ) ) {
				$settings['providers'] = array();
			}

			// Update provider config
			if ( ! isset( $settings['providers'][ $provider ] ) ) {
				$settings['providers'][ $provider ] = array();
			}

			// Update API key if provided (and not masked)
			if ( $api_key && $api_key !== '********' ) {
				$settings['providers'][ $provider ]['api_key'] = sanitize_text_field( $api_key );
			}

			// Update other config
			if ( $config ) {
				foreach ( $config as $key => $value ) {
					if ( $key !== 'api_key' ) {
						$settings['providers'][ $provider ][ $key ] = $this->sanitize_config_value( $value );
					}
				}
			}

			// Update enabled status
			if ( $enabled !== null ) {
				$settings['providers'][ $provider ]['enabled'] = (bool) $enabled;
			}

			// Save settings
			$updated = update_option( 'waisg_provider_settings', $settings );

			if ( ! $updated ) {
				return new WP_Error(
					'update_failed',
					__( 'Failed to update provider configuration', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Test the new configuration if API key was provided
			if ( $api_key && $api_key !== '********' ) {
				$test_result = $this->provider_manager->test_provider( $provider );
				if ( ! $test_result['success'] ) {
					// Revert changes if test failed
					unset( $settings['providers'][ $provider ] );
					update_option( 'waisg_provider_settings', $settings );

					return new WP_Error(
						'invalid_config',
						__( 'Provider configuration is invalid', 'wp-ai-site-generator' ),
						array( 'status' => 400 )
					);
				}
			}

			// Log the update
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'provider_configured',
				'object_id'   => 0,
				'object_type' => 'provider',
				'details'     => wp_json_encode( array(
					'provider' => $provider,
					'enabled'  => $settings['providers'][ $provider ]['enabled'] ?? true,
				) ),
			) );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Provider configuration updated successfully', 'wp-ai-site-generator' ),
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
	 * Get provider models.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_provider_models( $request ) {
		try {
			$provider_name = $request->get_param( 'provider' );

			$provider = $this->provider_manager->get_provider( $provider_name );
			if ( ! $provider ) {
				return new WP_Error(
					'not_found',
					__( 'Provider not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			if ( ! $this->provider_manager->is_provider_configured( $provider_name ) ) {
				return new WP_Error(
					'not_configured',
					__( 'Provider is not configured', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			$models = $provider->get_available_models();

			// Enhance model data
			$enhanced_models = array();
			foreach ( $models as $model ) {
				$enhanced_models[] = array(
					'id'           => $model['id'],
					'name'         => $model['name'],
					'description'  => $model['description'] ?? '',
					'context_size' => $model['context_size'] ?? null,
					'capabilities' => $model['capabilities'] ?? array(),
					'pricing'      => $model['pricing'] ?? null,
					'recommended'  => $model['recommended'] ?? false,
				);
			}

			return new WP_REST_Response( $enhanced_models );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get provider limits.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_provider_limits( $request ) {
		try {
			$provider = $request->get_param( 'provider' );

			$limits = get_option( 'waisg_provider_limits', array() );
			$provider_limits = $limits[ $provider ] ?? array();

			// Get default limits if not set
			if ( empty( $provider_limits ) ) {
				$provider_limits = $this->get_default_provider_limits( $provider );
			}

			// Get current usage
			$usage = $this->get_provider_usage_summary( $provider );

			// Calculate remaining
			$remaining = array();
			foreach ( $provider_limits as $key => $limit ) {
				if ( is_numeric( $limit ) && isset( $usage[ $key ] ) ) {
					$remaining[ $key ] = max( 0, $limit - $usage[ $key ] );
				}
			}

			return new WP_REST_Response(
				array(
					'limits'    => $provider_limits,
					'usage'     => $usage,
					'remaining' => $remaining,
					'period'    => $provider_limits['period'] ?? 'month',
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Reset provider usage.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function reset_provider_usage( $request ) {
		try {
			// Verify nonce
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'invalid_nonce',
					__( 'Invalid security token', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			$provider = $request->get_param( 'provider' );

			// Reset usage for the provider
			$result = $this->db_handler->reset_usage( array(
				'provider' => $provider,
				'user_id'  => current_user_can( 'manage_options' ) ? null : get_current_user_id(),
			) );

			if ( ! $result ) {
				return new WP_Error(
					'reset_failed',
					__( 'Failed to reset usage statistics', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Log the reset
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'usage_reset',
				'object_id'   => 0,
				'object_type' => 'provider',
				'details'     => wp_json_encode( array(
					'provider' => $provider,
				) ),
			) );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Usage statistics reset successfully', 'wp-ai-site-generator' ),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'reset_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get provider status.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @return   array
	 */
	private function get_provider_status( $provider ) {
		$status = array(
			'available'  => $this->provider_manager->is_provider_available( $provider ),
			'configured' => $this->provider_manager->is_provider_configured( $provider ),
			'active'     => false,
			'health'     => 'unknown',
		);

		if ( $status['configured'] ) {
			// Check if actively working
			$last_used = get_transient( 'waisg_provider_last_used_' . $provider );
			$status['active'] = $last_used && ( time() - $last_used ) < DAY_IN_SECONDS;

			// Check health
			$errors = get_transient( 'waisg_provider_errors_' . $provider );
			if ( ! $errors ) {
				$status['health'] = 'healthy';
			} elseif ( $errors < 3 ) {
				$status['health'] = 'degraded';
			} else {
				$status['health'] = 'unhealthy';
			}
		}

		return $status;
	}

	/**
	 * Get provider usage summary.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @return   array
	 */
	private function get_provider_usage_summary( $provider ) {
		$usage = $this->db_handler->get_usage_statistics( array(
			'provider' => $provider,
			'user_id'  => get_current_user_id(),
			'period'   => 'month',
		) );

		$summary = array(
			'requests'     => 0,
			'tokens_used'  => 0,
			'cost'         => 0,
			'generations'  => 0,
			'last_used'    => null,
		);

		foreach ( $usage as $record ) {
			$summary['requests']++;
			$summary['tokens_used'] += $record->tokens_used;
			$summary['cost'] += $record->cost;
			if ( $record->action === 'generation' ) {
				$summary['generations']++;
			}
			if ( ! $summary['last_used'] || $record->created_at > $summary['last_used'] ) {
				$summary['last_used'] = $record->created_at;
			}
		}

		return $summary;
	}

	/**
	 * Process usage data for grouping.
	 *
	 * @since    1.0.0
	 * @param    array     $data       Raw usage data.
	 * @param    string    $group_by   Grouping parameter.
	 * @return   array
	 */
	private function process_usage_data( $data, $group_by ) {
		if ( ! $group_by ) {
			return $data;
		}

		$grouped = array();

		foreach ( $data as $record ) {
			$key = '';

			switch ( $group_by ) {
				case 'day':
					$key = date( 'Y-m-d', strtotime( $record->created_at ) );
					break;
				case 'provider':
					$key = $record->provider;
					break;
				case 'action':
					$key = $record->action;
					break;
				case 'user':
					$key = $record->user_id;
					break;
			}

			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = array(
					'key'         => $key,
					'requests'    => 0,
					'tokens_used' => 0,
					'cost'        => 0,
				);
			}

			$grouped[ $key ]['requests']++;
			$grouped[ $key ]['tokens_used'] += $record->tokens_used;
			$grouped[ $key ]['cost'] += $record->cost;
		}

		return array_values( $grouped );
	}

	/**
	 * Calculate usage totals.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Usage data.
	 * @return   array
	 */
	private function calculate_usage_totals( $data ) {
		$totals = array(
			'requests'    => 0,
			'tokens_used' => 0,
			'cost'        => 0,
			'providers'   => array(),
			'actions'     => array(),
		);

		foreach ( $data as $record ) {
			$totals['requests']++;
			$totals['tokens_used'] += $record->tokens_used;
			$totals['cost'] += $record->cost;

			if ( ! isset( $totals['providers'][ $record->provider ] ) ) {
				$totals['providers'][ $record->provider ] = 0;
			}
			$totals['providers'][ $record->provider ]++;

			if ( ! isset( $totals['actions'][ $record->action ] ) ) {
				$totals['actions'][ $record->action ] = 0;
			}
			$totals['actions'][ $record->action ]++;
		}

		return $totals;
	}

	/**
	 * Get usage limits.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @return   array
	 */
	private function get_usage_limits( $provider = null ) {
		$limits = get_option( 'waisg_usage_limits', array() );

		$default_limits = array(
			'max_requests_per_day'   => 1000,
			'max_tokens_per_day'     => 100000,
			'max_cost_per_month'     => 100,
			'max_generations_per_day' => 50,
		);

		if ( $provider ) {
			$provider_limits = $limits['providers'][ $provider ] ?? array();
			return array_merge( $default_limits, $provider_limits );
		}

		return array_merge( $default_limits, $limits );
	}

	/**
	 * Calculate remaining usage.
	 *
	 * @since    1.0.0
	 * @param    array    $totals    Usage totals.
	 * @param    array    $limits    Usage limits.
	 * @return   array
	 */
	private function calculate_remaining_usage( $totals, $limits ) {
		$remaining = array();

		foreach ( $limits as $key => $limit ) {
			$usage_key = str_replace( array( 'max_', '_per_day', '_per_month' ), '', $key );
			$usage = $totals[ $usage_key ] ?? 0;
			$remaining[ $key ] = max( 0, $limit - $usage );
		}

		return $remaining;
	}

	/**
	 * Get default provider limits.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @return   array
	 */
	private function get_default_provider_limits( $provider ) {
		$defaults = array(
			'openai' => array(
				'max_requests_per_minute' => 60,
				'max_tokens_per_minute'   => 90000,
				'max_requests_per_day'    => 10000,
			),
			'claude' => array(
				'max_requests_per_minute' => 50,
				'max_tokens_per_minute'   => 100000,
				'max_requests_per_day'    => 10000,
			),
			'groq' => array(
				'max_requests_per_minute' => 30,
				'max_tokens_per_minute'   => 10000,
				'max_requests_per_day'    => 14400,
			),
			'cohere' => array(
				'max_requests_per_minute' => 100,
				'max_tokens_per_minute'   => 100000,
				'max_requests_per_day'    => 10000,
			),
		);

		return $defaults[ $provider ] ?? array(
			'max_requests_per_minute' => 60,
			'max_tokens_per_minute'   => 10000,
			'max_requests_per_day'    => 1000,
		);
	}

	/**
	 * Sanitize configuration value.
	 *
	 * @since    1.0.0
	 * @param    mixed    $value    Value to sanitize.
	 * @return   mixed
	 */
	private function sanitize_config_value( $value ) {
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		} elseif ( is_array( $value ) ) {
			return array_map( array( $this, 'sanitize_config_value' ), $value );
		} elseif ( is_bool( $value ) ) {
			return (bool) $value;
		} elseif ( is_numeric( $value ) ) {
			return is_float( $value ) ? (float) $value : (int) $value;
		}
		return null;
	}

	/**
	 * Get test provider arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_test_provider_args() {
		return array(
			'provider' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return ! empty( $param );
				},
			),
			'api_key' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'config' => array(
				'type'    => 'object',
				'default' => array(),
			),
		);
	}

	/**
	 * Get usage statistics arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_usage_stats_args() {
		return array(
			'provider' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'period' => array(
				'type'              => 'string',
				'default'           => 'month',
				'enum'              => array( 'day', 'week', 'month', 'year', 'all' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'start_date' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return empty( $param ) || strtotime( $param ) !== false;
				},
			),
			'end_date' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return empty( $param ) || strtotime( $param ) !== false;
				},
			),
			'user_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'group_by' => array(
				'type'              => 'string',
				'enum'              => array( 'day', 'provider', 'action', 'user' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get provider configuration arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_provider_config_args() {
		return array(
			'provider' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'api_key' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'enabled' => array(
				'type' => 'boolean',
			),
			'config' => array(
				'type'    => 'object',
				'default' => array(),
			),
		);
	}

	/**
	 * Check if current user can view providers.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function view_providers_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can test providers.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function test_provider_permissions_check() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can view usage.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function view_usage_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can configure providers.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function configure_provider_permissions_check() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can manage providers.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function manage_provider_permissions_check() {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}
}