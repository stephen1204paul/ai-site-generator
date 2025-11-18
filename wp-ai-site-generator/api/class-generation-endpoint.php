<?php
/**
 * Generation-specific REST API endpoints.
 *
 * Handles all generation-related REST API endpoints for sites, pages, and sections.
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
use WPAISiteGenerator\Generators\Site_Generator;
use WPAISiteGenerator\Generators\Page_Generator;
use WPAISiteGenerator\Generators\Block_Generator;

/**
 * Generation Endpoint class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 */
class Generation_Endpoint extends WP_REST_Controller {

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
	 * Register the routes for the generation endpoints.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		// Generate complete site
		register_rest_route(
			$this->namespace,
			'/generate/site',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_site' ),
					'permission_callback' => array( $this, 'generate_permissions_check' ),
					'args'                => $this->get_site_generation_args(),
				),
			)
		);

		// Generate single page
		register_rest_route(
			$this->namespace,
			'/generate/page',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_page' ),
					'permission_callback' => array( $this, 'generate_permissions_check' ),
					'args'                => $this->get_page_generation_args(),
				),
			)
		);

		// Generate/regenerate section
		register_rest_route(
			$this->namespace,
			'/generate/section',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_section' ),
					'permission_callback' => array( $this, 'generate_permissions_check' ),
					'args'                => $this->get_section_generation_args(),
				),
			)
		);

		// Check generation status
		register_rest_route(
			$this->namespace,
			'/generate/status/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_generation_status' ),
					'permission_callback' => array( $this, 'status_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Apply generated design
		register_rest_route(
			$this->namespace,
			'/generate/apply/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_generation' ),
					'permission_callback' => array( $this, 'apply_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						),
						'options' => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);
	}

	/**
	 * Generate a complete site.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function generate_site( $request ) {
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

			// Check rate limiting
			if ( $this->is_rate_limited( 'site' ) ) {
				return new WP_Error(
					'rate_limited',
					__( 'Too many generation requests. Please wait before trying again.', 'wp-ai-site-generator' ),
					array( 'status' => 429 )
				);
			}

			$prompt = $request->get_param( 'prompt' );
			$business_type = $request->get_param( 'business_type' );
			$provider = $request->get_param( 'provider' );
			$template = $request->get_param( 'template' );
			$pages = $request->get_param( 'pages' );
			$style = $request->get_param( 'style' );
			$features = $request->get_param( 'features' );

			// Validate provider
			if ( ! $this->provider_manager->is_provider_available( $provider ) ) {
				return new WP_Error(
					'invalid_provider',
					__( 'The selected AI provider is not available', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			// Create generation record
			$generation_data = array(
				'user_id'       => get_current_user_id(),
				'type'          => 'site',
				'prompt'        => sanitize_textarea_field( $prompt ),
				'provider'      => sanitize_text_field( $provider ),
				'status'        => 'pending',
				'metadata'      => wp_json_encode( array(
					'business_type' => $business_type,
					'template'      => $template,
					'pages'         => $pages,
					'style'         => $style,
					'features'      => $features,
				) ),
			);

			$generation_id = $this->db_handler->create_generation( $generation_data );

			if ( ! $generation_id ) {
				return new WP_Error(
					'creation_failed',
					__( 'Failed to create generation record', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Schedule background generation
			wp_schedule_single_event(
				time(),
				'waisg_process_site_generation',
				array( $generation_id, $generation_data )
			);

			// Update transient for real-time status
			set_transient(
				'waisg_generation_' . $generation_id,
				array(
					'status'  => 'queued',
					'message' => __( 'Site generation has been queued', 'wp-ai-site-generator' ),
				),
				HOUR_IN_SECONDS
			);

			return new WP_REST_Response(
				array(
					'generation_id' => $generation_id,
					'status'        => 'queued',
					'message'       => __( 'Site generation started successfully', 'wp-ai-site-generator' ),
					'estimated_time' => $this->estimate_generation_time( 'site', count( $pages ) ),
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
	 * Generate a single page.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function generate_page( $request ) {
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

			// Check rate limiting
			if ( $this->is_rate_limited( 'page' ) ) {
				return new WP_Error(
					'rate_limited',
					__( 'Too many generation requests. Please wait before trying again.', 'wp-ai-site-generator' ),
					array( 'status' => 429 )
				);
			}

			$title = $request->get_param( 'title' );
			$prompt = $request->get_param( 'prompt' );
			$type = $request->get_param( 'type' );
			$parent_id = $request->get_param( 'parent_id' );
			$template = $request->get_param( 'template' );
			$provider = $request->get_param( 'provider' );
			$sections = $request->get_param( 'sections' );

			// Validate provider
			$provider_instance = $this->provider_manager->get_provider( $provider );
			if ( ! $provider_instance ) {
				return new WP_Error(
					'invalid_provider',
					__( 'AI provider not available', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			// Generate page content
			$page_generator = new Page_Generator( $this->provider_manager );

			$generation_options = array(
				'template' => $template,
				'sections' => $sections,
				'style'    => $request->get_param( 'style' ),
			);

			$result = $page_generator->generate(
				$prompt,
				$type,
				$provider,
				$generation_options
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Create page in WordPress
			$page_data = array(
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => $result['content'],
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_parent'  => absint( $parent_id ),
				'meta_input'   => array(
					'_waisg_generated'     => true,
					'_waisg_provider'      => $provider,
					'_waisg_generation_id' => $result['generation_id'] ?? 0,
					'_waisg_template'      => $template,
				),
			);

			$page_id = wp_insert_post( $page_data );

			if ( is_wp_error( $page_id ) ) {
				return new WP_Error(
					'page_creation_failed',
					__( 'Failed to create page', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Track usage
			$this->db_handler->track_usage( array(
				'user_id'      => get_current_user_id(),
				'provider'     => $provider,
				'action'       => 'page_generation',
				'tokens_used'  => $result['usage']['total_tokens'] ?? 0,
				'reference_id' => $page_id,
			) );

			return new WP_REST_Response(
				array(
					'page_id'  => $page_id,
					'title'    => $title,
					'status'   => 'draft',
					'edit_url' => get_edit_post_link( $page_id, 'raw' ),
					'view_url' => get_permalink( $page_id ),
					'usage'    => $result['usage'] ?? array(),
					'message'  => __( 'Page generated successfully', 'wp-ai-site-generator' ),
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
	 * Generate or regenerate a section.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function generate_section( $request ) {
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

			// Check rate limiting
			if ( $this->is_rate_limited( 'section' ) ) {
				return new WP_Error(
					'rate_limited',
					__( 'Too many generation requests. Please wait before trying again.', 'wp-ai-site-generator' ),
					array( 'status' => 429 )
				);
			}

			$prompt = $request->get_param( 'prompt' );
			$type = $request->get_param( 'type' );
			$page_id = $request->get_param( 'page_id' );
			$block_id = $request->get_param( 'block_id' );
			$provider = $request->get_param( 'provider' );
			$replace = $request->get_param( 'replace' );

			// Validate page exists
			if ( $page_id && ! get_post( $page_id ) ) {
				return new WP_Error(
					'invalid_page',
					__( 'The specified page does not exist', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Generate section/block
			$block_generator = new Block_Generator( $this->provider_manager );

			$block_options = array(
				'style'      => $request->get_param( 'style' ),
				'columns'    => $request->get_param( 'columns' ),
				'with_image' => $request->get_param( 'with_image' ),
			);

			$result = $block_generator->generate(
				$prompt,
				$type,
				$provider,
				$block_options
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Update page if specified
			if ( $page_id ) {
				$page = get_post( $page_id );
				$content = $page->post_content;

				if ( $replace && $block_id ) {
					// Replace existing block
					$content = $this->replace_block_in_content(
						$content,
						$block_id,
						$result['html']
					);
				} else {
					// Append to content
					$content .= "\n\n" . $result['html'];
				}

				wp_update_post( array(
					'ID'           => $page_id,
					'post_content' => $content,
				) );

				// Add revision note
				wp_save_post_revision( $page_id );
				update_post_meta( $page_id, '_waisg_last_section_update', current_time( 'mysql' ) );
			}

			// Track usage
			$this->db_handler->track_usage( array(
				'user_id'      => get_current_user_id(),
				'provider'     => $provider,
				'action'       => 'section_generation',
				'tokens_used'  => $result['usage']['total_tokens'] ?? 0,
				'reference_id' => $page_id,
			) );

			return new WP_REST_Response(
				array(
					'block'    => $result['block'] ?? $result['html'],
					'html'     => $result['html'],
					'usage'    => $result['usage'] ?? array(),
					'message'  => __( 'Section generated successfully', 'wp-ai-site-generator' ),
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
	 * Get generation status.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_generation_status( $request ) {
		try {
			$generation_id = $request->get_param( 'id' );

			// Check transient first for real-time updates
			$transient_status = get_transient( 'waisg_generation_' . $generation_id );

			// Get generation from database
			$generation = $this->db_handler->get_generation( $generation_id );

			if ( ! $generation ) {
				return new WP_Error(
					'not_found',
					__( 'Generation not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Verify ownership
			if ( $generation->user_id != get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to view this generation', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Parse metadata
			$metadata = json_decode( $generation->metadata, true );

			// Prepare response
			$response_data = array(
				'id'         => $generation->id,
				'status'     => $generation->status,
				'type'       => $generation->type,
				'progress'   => $transient_status['progress'] ?? $this->calculate_progress( $generation ),
				'message'    => $transient_status['message'] ?? $this->get_status_message( $generation->status ),
				'created_at' => $generation->created_at,
				'updated_at' => $generation->updated_at,
			);

			// Add result if completed
			if ( $generation->status === 'completed' ) {
				$response_data['result'] = $generation->result ? json_decode( $generation->result, true ) : null;
				$response_data['apply_url'] = rest_url( $this->namespace . '/generate/apply/' . $generation_id );
			}

			// Add error if failed
			if ( $generation->status === 'failed' ) {
				$response_data['error'] = $generation->error_message;
			}

			// Add additional details
			if ( $transient_status ) {
				$response_data['details'] = $transient_status;
			}

			// Add estimated completion time if processing
			if ( $generation->status === 'processing' ) {
				$response_data['estimated_completion'] = $this->estimate_completion_time( $generation );
			}

			return new WP_REST_Response( $response_data );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'status_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Apply a generated design.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function apply_generation( $request ) {
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

			$generation_id = $request->get_param( 'id' );
			$options = $request->get_param( 'options' );

			// Get generation
			$generation = $this->db_handler->get_generation( $generation_id );

			if ( ! $generation ) {
				return new WP_Error(
					'not_found',
					__( 'Generation not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Verify ownership
			if ( $generation->user_id != get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to apply this generation', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Check if generation is completed
			if ( $generation->status !== 'completed' ) {
				return new WP_Error(
					'not_ready',
					__( 'Generation is not ready to be applied', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			// Check if already applied
			if ( $generation->applied_at ) {
				return new WP_Error(
					'already_applied',
					__( 'This generation has already been applied', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			$result = json_decode( $generation->result, true );
			$created_items = array();

			// Apply based on generation type
			switch ( $generation->type ) {
				case 'site':
					$created_items = $this->apply_site_generation( $result, $options );
					break;

				case 'page':
					$created_items = $this->apply_page_generation( $result, $options );
					break;

				case 'section':
					$created_items = $this->apply_section_generation( $result, $options );
					break;

				default:
					return new WP_Error(
						'invalid_type',
						__( 'Invalid generation type', 'wp-ai-site-generator' ),
						array( 'status' => 400 )
					);
			}

			// Mark as applied
			$this->db_handler->update_generation( $generation_id, array(
				'applied_at' => current_time( 'mysql' ),
				'applied_by' => get_current_user_id(),
			) );

			// Log application
			$this->db_handler->log_activity( array(
				'user_id'    => get_current_user_id(),
				'action'     => 'generation_applied',
				'object_id'  => $generation_id,
				'object_type' => 'generation',
				'details'    => wp_json_encode( $created_items ),
			) );

			return new WP_REST_Response(
				array(
					'success'       => true,
					'message'       => __( 'Generation applied successfully', 'wp-ai-site-generator' ),
					'created_items' => $created_items,
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'apply_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Apply site generation.
	 *
	 * @since    1.0.0
	 * @param    array    $result    Generation result.
	 * @param    array    $options   Apply options.
	 * @return   array    Created items.
	 */
	private function apply_site_generation( $result, $options ) {
		$created_items = array(
			'pages'    => array(),
			'menus'    => array(),
			'settings' => array(),
		);

		// Create pages
		if ( ! empty( $result['pages'] ) ) {
			foreach ( $result['pages'] as $page_data ) {
				$page_id = wp_insert_post( array(
					'post_title'   => $page_data['title'],
					'post_content' => $page_data['content'],
					'post_status'  => $options['publish'] ? 'publish' : 'draft',
					'post_type'    => 'page',
					'menu_order'   => $page_data['order'] ?? 0,
					'meta_input'   => array(
						'_waisg_generated' => true,
					),
				) );

				if ( ! is_wp_error( $page_id ) ) {
					$created_items['pages'][] = array(
						'id'    => $page_id,
						'title' => $page_data['title'],
						'url'   => get_permalink( $page_id ),
					);

					// Set as home page if specified
					if ( $page_data['is_home'] ?? false ) {
						update_option( 'show_on_front', 'page' );
						update_option( 'page_on_front', $page_id );
					}
				}
			}
		}

		// Create menus
		if ( ! empty( $result['menus'] ) && ! empty( $options['create_menus'] ) ) {
			foreach ( $result['menus'] as $menu_data ) {
				$menu_id = wp_create_nav_menu( $menu_data['name'] );

				if ( ! is_wp_error( $menu_id ) ) {
					$created_items['menus'][] = array(
						'id'   => $menu_id,
						'name' => $menu_data['name'],
					);

					// Add menu items
					foreach ( $menu_data['items'] as $item ) {
						wp_update_nav_menu_item( $menu_id, 0, array(
							'menu-item-title'  => $item['title'],
							'menu-item-url'    => $item['url'],
							'menu-item-status' => 'publish',
						) );
					}
				}
			}
		}

		// Apply settings
		if ( ! empty( $result['settings'] ) && ! empty( $options['apply_settings'] ) ) {
			foreach ( $result['settings'] as $key => $value ) {
				update_option( $key, $value );
				$created_items['settings'][] = $key;
			}
		}

		return $created_items;
	}

	/**
	 * Apply page generation.
	 *
	 * @since    1.0.0
	 * @param    array    $result    Generation result.
	 * @param    array    $options   Apply options.
	 * @return   array    Created items.
	 */
	private function apply_page_generation( $result, $options ) {
		$page_id = wp_insert_post( array(
			'post_title'   => $result['title'],
			'post_content' => $result['content'],
			'post_status'  => $options['publish'] ? 'publish' : 'draft',
			'post_type'    => 'page',
			'post_parent'  => $options['parent_id'] ?? 0,
			'meta_input'   => array(
				'_waisg_generated' => true,
			),
		) );

		if ( is_wp_error( $page_id ) ) {
			throw new \Exception( $page_id->get_error_message() );
		}

		return array(
			'pages' => array(
				array(
					'id'    => $page_id,
					'title' => $result['title'],
					'url'   => get_permalink( $page_id ),
				),
			),
		);
	}

	/**
	 * Apply section generation.
	 *
	 * @since    1.0.0
	 * @param    array    $result    Generation result.
	 * @param    array    $options   Apply options.
	 * @return   array    Created items.
	 */
	private function apply_section_generation( $result, $options ) {
		if ( empty( $options['page_id'] ) ) {
			throw new \Exception( __( 'Page ID is required for section application', 'wp-ai-site-generator' ) );
		}

		$page = get_post( $options['page_id'] );
		if ( ! $page ) {
			throw new \Exception( __( 'Page not found', 'wp-ai-site-generator' ) );
		}

		$content = $page->post_content;

		if ( ! empty( $options['replace_block'] ) ) {
			$content = $this->replace_block_in_content(
				$content,
				$options['block_id'],
				$result['content']
			);
		} else {
			$content .= "\n\n" . $result['content'];
		}

		wp_update_post( array(
			'ID'           => $options['page_id'],
			'post_content' => $content,
		) );

		return array(
			'sections' => array(
				array(
					'page_id' => $options['page_id'],
					'type'    => $result['type'] ?? 'block',
					'action'  => $options['replace_block'] ? 'replaced' : 'added',
				),
			),
		);
	}

	/**
	 * Replace block in content.
	 *
	 * @since    1.0.0
	 * @param    string    $content     Current content.
	 * @param    string    $block_id    Block ID to replace.
	 * @param    string    $new_block   New block content.
	 * @return   string    Updated content.
	 */
	private function replace_block_in_content( $content, $block_id, $new_block ) {
		// This is a simplified implementation
		// In practice, you'd need to parse Gutenberg blocks properly
		$pattern = '/<!-- wp:.*?"id":"' . preg_quote( $block_id, '/' ) . '".*?<!-- \/wp:.*?-->/s';
		return preg_replace( $pattern, $new_block, $content );
	}

	/**
	 * Check if user is rate limited.
	 *
	 * @since    1.0.0
	 * @param    string    $type    Generation type.
	 * @return   bool
	 */
	private function is_rate_limited( $type ) {
		$user_id = get_current_user_id();
		$transient_key = 'waisg_rate_limit_' . $type . '_' . $user_id;

		$limits = get_option( 'waisg_usage_limits', array() );
		$max_per_hour = $limits[ 'max_' . $type . '_per_hour' ] ?? 10;

		$current = get_transient( $transient_key );
		if ( $current >= $max_per_hour ) {
			return true;
		}

		set_transient( $transient_key, ( $current ?: 0 ) + 1, HOUR_IN_SECONDS );
		return false;
	}

	/**
	 * Estimate generation time.
	 *
	 * @since    1.0.0
	 * @param    string    $type         Generation type.
	 * @param    int       $complexity   Complexity factor.
	 * @return   int       Estimated seconds.
	 */
	private function estimate_generation_time( $type, $complexity = 1 ) {
		$base_times = array(
			'site'    => 120,
			'page'    => 30,
			'section' => 10,
		);

		return ( $base_times[ $type ] ?? 60 ) * $complexity;
	}

	/**
	 * Calculate generation progress.
	 *
	 * @since    1.0.0
	 * @param    object    $generation    Generation record.
	 * @return   int       Progress percentage.
	 */
	private function calculate_progress( $generation ) {
		switch ( $generation->status ) {
			case 'pending':
				return 0;
			case 'processing':
				// Calculate based on time elapsed
				$elapsed = time() - strtotime( $generation->updated_at );
				$estimated = $this->estimate_generation_time( $generation->type );
				return min( 90, ( $elapsed / $estimated ) * 100 );
			case 'completed':
				return 100;
			case 'failed':
				return 0;
			default:
				return 0;
		}
	}

	/**
	 * Get status message.
	 *
	 * @since    1.0.0
	 * @param    string    $status    Generation status.
	 * @return   string    Status message.
	 */
	private function get_status_message( $status ) {
		$messages = array(
			'pending'    => __( 'Generation is queued and will start soon', 'wp-ai-site-generator' ),
			'processing' => __( 'Generation is in progress', 'wp-ai-site-generator' ),
			'completed'  => __( 'Generation completed successfully', 'wp-ai-site-generator' ),
			'failed'     => __( 'Generation failed', 'wp-ai-site-generator' ),
		);

		return $messages[ $status ] ?? __( 'Unknown status', 'wp-ai-site-generator' );
	}

	/**
	 * Estimate completion time.
	 *
	 * @since    1.0.0
	 * @param    object    $generation    Generation record.
	 * @return   string    Estimated completion time.
	 */
	private function estimate_completion_time( $generation ) {
		$estimated_seconds = $this->estimate_generation_time( $generation->type );
		$elapsed = time() - strtotime( $generation->updated_at );
		$remaining = max( 0, $estimated_seconds - $elapsed );

		if ( $remaining < 60 ) {
			return sprintf( __( '%d seconds', 'wp-ai-site-generator' ), $remaining );
		} elseif ( $remaining < 3600 ) {
			return sprintf( __( '%d minutes', 'wp-ai-site-generator' ), ceil( $remaining / 60 ) );
		} else {
			return __( 'More than an hour', 'wp-ai-site-generator' );
		}
	}

	/**
	 * Get site generation arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_site_generation_args() {
		return array(
			'prompt' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => function( $param ) {
					return ! empty( trim( $param ) ) && strlen( $param ) >= 10;
				},
			),
			'business_type' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'provider' => array(
				'type'              => 'string',
				'default'           => 'openai',
				'enum'              => array( 'openai', 'claude', 'groq', 'cohere' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'template' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'pages' => array(
				'type'    => 'array',
				'default' => array( 'home', 'about', 'services', 'contact' ),
				'items'   => array(
					'type' => 'string',
				),
			),
			'style' => array(
				'type'    => 'object',
				'default' => array(),
			),
			'features' => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Get page generation arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_page_generation_args() {
		return array(
			'title' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return ! empty( trim( $param ) ) && strlen( $param ) <= 200;
				},
			),
			'prompt' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'type' => array(
				'type'              => 'string',
				'default'           => 'page',
				'enum'              => array( 'page', 'landing', 'blog', 'portfolio', 'contact', 'about' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'parent_id' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'template' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'provider' => array(
				'type'              => 'string',
				'default'           => 'openai',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'sections' => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array(
					'type' => 'string',
				),
			),
			'style' => array(
				'type'    => 'object',
				'default' => array(),
			),
		);
	}

	/**
	 * Get section generation arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_section_generation_args() {
		return array(
			'prompt' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'type' => array(
				'type'              => 'string',
				'default'           => 'content',
				'enum'              => array(
					'hero',
					'content',
					'features',
					'testimonials',
					'cta',
					'gallery',
					'team',
					'pricing',
					'faq',
					'contact',
				),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'block_id' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'provider' => array(
				'type'              => 'string',
				'default'           => 'openai',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'replace' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'style' => array(
				'type'    => 'object',
				'default' => array(),
			),
			'columns' => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'maximum'           => 4,
				'sanitize_callback' => 'absint',
			),
			'with_image' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Check if current user can generate content.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function generate_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can view generation status.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function status_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can apply generations.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function apply_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}
}