<?php
/**
 * Template management REST API endpoints.
 *
 * Handles all template-related REST API endpoints for CRUD operations.
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

/**
 * Template Endpoint class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 */
class Template_Endpoint extends WP_REST_Controller {

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
	 * Initialize the controller.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->namespace = 'waisg/v1';
		$this->db_handler = new DB_Handler();
	}

	/**
	 * Register the routes for the template endpoints.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		// List templates
		register_rest_route(
			$this->namespace,
			'/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'get_templates_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'create_template_permissions_check' ),
					'args'                => $this->get_template_args(),
				),
			)
		);

		// Single template operations
		register_rest_route(
			$this->namespace,
			'/templates/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template' ),
					'permission_callback' => array( $this, 'get_template_permissions_check' ),
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
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_template' ),
					'permission_callback' => array( $this, 'update_template_permissions_check' ),
					'args'                => $this->get_template_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_template' ),
					'permission_callback' => array( $this, 'delete_template_permissions_check' ),
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

		// Clone template
		register_rest_route(
			$this->namespace,
			'/templates/(?P<id>[\d]+)/clone',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clone_template' ),
					'permission_callback' => array( $this, 'create_template_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						),
						'name' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Export template
		register_rest_route(
			$this->namespace,
			'/templates/(?P<id>[\d]+)/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_template' ),
					'permission_callback' => array( $this, 'get_template_permissions_check' ),
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

		// Import template
		register_rest_route(
			$this->namespace,
			'/templates/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_template' ),
					'permission_callback' => array( $this, 'create_template_permissions_check' ),
					'args'                => array(
						'template_data' => array(
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function( $param ) {
								$decoded = json_decode( $param, true );
								return json_last_error() === JSON_ERROR_NONE && isset( $decoded['name'] );
							},
						),
					),
				),
			)
		);

		// Template categories
		register_rest_route(
			$this->namespace,
			'/templates/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template_categories' ),
					'permission_callback' => array( $this, 'get_templates_permissions_check' ),
				),
			)
		);

		// Template preview
		register_rest_route(
			$this->namespace,
			'/templates/(?P<id>[\d]+)/preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview_template' ),
					'permission_callback' => array( $this, 'get_template_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						),
						'variables' => array(
							'type'    => 'object',
							'default' => array(),
						),
					),
				),
			)
		);
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
			$page = $request->get_param( 'page' ) ?: 1;
			$per_page = $request->get_param( 'per_page' ) ?: 20;
			$search = $request->get_param( 'search' );
			$category = $request->get_param( 'category' );
			$type = $request->get_param( 'type' );
			$orderby = $request->get_param( 'orderby' ) ?: 'name';
			$order = $request->get_param( 'order' ) ?: 'ASC';

			$args = array(
				'limit'   => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
				'orderby' => $orderby,
				'order'   => $order,
			);

			if ( $search ) {
				$args['search'] = sanitize_text_field( $search );
			}

			if ( $category ) {
				$args['category'] = sanitize_text_field( $category );
			}

			if ( $type ) {
				$args['type'] = sanitize_text_field( $type );
			}

			// Check if user can see all templates or just their own
			if ( ! current_user_can( 'manage_options' ) ) {
				$args['user_id'] = get_current_user_id();
				$args['include_public'] = true;
			}

			$templates = $this->db_handler->get_templates( $args );
			$total = $this->db_handler->count_templates( $args );

			// Process templates for output
			foreach ( $templates as &$template ) {
				$template = $this->prepare_template_for_response( $template );
			}

			$response = new WP_REST_Response( $templates );
			$response->header( 'X-WP-Total', $total );
			$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

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
	 * Get a single template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_template( $request ) {
		try {
			$template_id = $request->get_param( 'id' );
			$template = $this->db_handler->get_template( $template_id );

			if ( ! $template ) {
				return new WP_Error(
					'not_found',
					__( 'Template not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Check permissions
			if ( ! $this->can_access_template( $template ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to view this template', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			$template = $this->prepare_template_for_response( $template, true );

			return new WP_REST_Response( $template );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Create a new template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function create_template( $request ) {
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

			// Check template limit for user
			$user_templates = $this->db_handler->count_templates( array(
				'user_id' => get_current_user_id(),
			) );

			$template_limit = get_option( 'waisg_usage_limits', array() )['max_templates'] ?? 50;
			if ( ! current_user_can( 'manage_options' ) && $user_templates >= $template_limit ) {
				return new WP_Error(
					'template_limit_exceeded',
					sprintf(
						__( 'You have reached the maximum of %d templates', 'wp-ai-site-generator' ),
						$template_limit
					),
					array( 'status' => 429 )
				);
			}

			// Prepare template data
			$template_data = array(
				'name'            => sanitize_text_field( $request->get_param( 'name' ) ),
				'slug'            => $this->generate_unique_slug( $request->get_param( 'name' ) ),
				'description'     => sanitize_textarea_field( $request->get_param( 'description' ) ),
				'type'            => sanitize_text_field( $request->get_param( 'type' ) ?: 'general' ),
				'category'        => sanitize_text_field( $request->get_param( 'category' ) ?: 'general' ),
				'prompt_template' => $this->sanitize_prompt_template( $request->get_param( 'prompt_template' ) ),
				'configuration'   => wp_json_encode( $request->get_param( 'configuration' ) ?: array() ),
				'variables'       => wp_json_encode( $request->get_param( 'variables' ) ?: array() ),
				'sections'        => wp_json_encode( $request->get_param( 'sections' ) ?: array() ),
				'is_public'       => (bool) $request->get_param( 'is_public' ),
				'created_by'      => get_current_user_id(),
			);

			// Validate prompt template
			if ( ! $this->validate_prompt_template( $template_data['prompt_template'] ) ) {
				return new WP_Error(
					'invalid_prompt',
					__( 'Invalid prompt template format', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			$template_id = $this->db_handler->create_template( $template_data );

			if ( ! $template_id ) {
				return new WP_Error(
					'create_failed',
					__( 'Failed to create template', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Log creation
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'template_created',
				'object_id'   => $template_id,
				'object_type' => 'template',
				'details'     => wp_json_encode( array(
					'name'     => $template_data['name'],
					'category' => $template_data['category'],
				) ),
			) );

			// Get created template
			$template = $this->db_handler->get_template( $template_id );
			$template = $this->prepare_template_for_response( $template );

			return new WP_REST_Response(
				array(
					'id'       => $template_id,
					'template' => $template,
					'message'  => __( 'Template created successfully', 'wp-ai-site-generator' ),
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
	 * Update a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_template( $request ) {
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

			$template_id = $request->get_param( 'id' );
			$template = $this->db_handler->get_template( $template_id );

			if ( ! $template ) {
				return new WP_Error(
					'not_found',
					__( 'Template not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Check ownership
			if ( ! $this->can_edit_template( $template ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to edit this template', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Prepare update data
			$update_data = array();

			if ( $request->has_param( 'name' ) ) {
				$update_data['name'] = sanitize_text_field( $request->get_param( 'name' ) );
				$update_data['slug'] = $this->generate_unique_slug( $update_data['name'], $template_id );
			}

			if ( $request->has_param( 'description' ) ) {
				$update_data['description'] = sanitize_textarea_field( $request->get_param( 'description' ) );
			}

			if ( $request->has_param( 'type' ) ) {
				$update_data['type'] = sanitize_text_field( $request->get_param( 'type' ) );
			}

			if ( $request->has_param( 'category' ) ) {
				$update_data['category'] = sanitize_text_field( $request->get_param( 'category' ) );
			}

			if ( $request->has_param( 'prompt_template' ) ) {
				$prompt = $this->sanitize_prompt_template( $request->get_param( 'prompt_template' ) );
				if ( ! $this->validate_prompt_template( $prompt ) ) {
					return new WP_Error(
						'invalid_prompt',
						__( 'Invalid prompt template format', 'wp-ai-site-generator' ),
						array( 'status' => 400 )
					);
				}
				$update_data['prompt_template'] = $prompt;
			}

			if ( $request->has_param( 'configuration' ) ) {
				$update_data['configuration'] = wp_json_encode( $request->get_param( 'configuration' ) );
			}

			if ( $request->has_param( 'variables' ) ) {
				$update_data['variables'] = wp_json_encode( $request->get_param( 'variables' ) );
			}

			if ( $request->has_param( 'sections' ) ) {
				$update_data['sections'] = wp_json_encode( $request->get_param( 'sections' ) );
			}

			if ( $request->has_param( 'is_public' ) ) {
				$update_data['is_public'] = (bool) $request->get_param( 'is_public' );
			}

			if ( empty( $update_data ) ) {
				return new WP_Error(
					'no_changes',
					__( 'No changes provided', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			$update_data['updated_at'] = current_time( 'mysql' );

			$updated = $this->db_handler->update_template( $template_id, $update_data );

			if ( ! $updated ) {
				return new WP_Error(
					'update_failed',
					__( 'Failed to update template', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Log update
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'template_updated',
				'object_id'   => $template_id,
				'object_type' => 'template',
				'details'     => wp_json_encode( array_keys( $update_data ) ),
			) );

			// Get updated template
			$template = $this->db_handler->get_template( $template_id );
			$template = $this->prepare_template_for_response( $template );

			return new WP_REST_Response(
				array(
					'template' => $template,
					'message'  => __( 'Template updated successfully', 'wp-ai-site-generator' ),
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
	 * Delete a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function delete_template( $request ) {
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

			$template_id = $request->get_param( 'id' );
			$template = $this->db_handler->get_template( $template_id );

			if ( ! $template ) {
				return new WP_Error(
					'not_found',
					__( 'Template not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Check ownership
			if ( ! $this->can_delete_template( $template ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to delete this template', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Check if template is in use
			$usage_count = $this->db_handler->count_template_usage( $template_id );
			if ( $usage_count > 0 ) {
				return new WP_Error(
					'template_in_use',
					sprintf(
						__( 'This template is being used in %d generations and cannot be deleted', 'wp-ai-site-generator' ),
						$usage_count
					),
					array( 'status' => 400 )
				);
			}

			$deleted = $this->db_handler->delete_template( $template_id );

			if ( ! $deleted ) {
				return new WP_Error(
					'delete_failed',
					__( 'Failed to delete template', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Log deletion
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'template_deleted',
				'object_id'   => $template_id,
				'object_type' => 'template',
				'details'     => wp_json_encode( array(
					'name' => $template->name,
				) ),
			) );

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Template deleted successfully', 'wp-ai-site-generator' ),
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
	 * Clone a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function clone_template( $request ) {
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

			$template_id = $request->get_param( 'id' );
			$template = $this->db_handler->get_template( $template_id );

			if ( ! $template ) {
				return new WP_Error(
					'not_found',
					__( 'Template not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Check permissions
			if ( ! $this->can_access_template( $template ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to clone this template', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Prepare cloned data
			$new_name = $request->get_param( 'name' ) ?: $template->name . ' (Copy)';

			$clone_data = array(
				'name'            => sanitize_text_field( $new_name ),
				'slug'            => $this->generate_unique_slug( $new_name ),
				'description'     => $template->description,
				'type'            => $template->type,
				'category'        => $template->category,
				'prompt_template' => $template->prompt_template,
				'configuration'   => $template->configuration,
				'variables'       => $template->variables,
				'sections'        => $template->sections,
				'is_public'       => false, // Clones are private by default
				'created_by'      => get_current_user_id(),
			);

			$clone_id = $this->db_handler->create_template( $clone_data );

			if ( ! $clone_id ) {
				return new WP_Error(
					'clone_failed',
					__( 'Failed to clone template', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Log cloning
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'template_cloned',
				'object_id'   => $clone_id,
				'object_type' => 'template',
				'details'     => wp_json_encode( array(
					'source_id'   => $template_id,
					'source_name' => $template->name,
					'clone_name'  => $new_name,
				) ),
			) );

			// Get cloned template
			$cloned = $this->db_handler->get_template( $clone_id );
			$cloned = $this->prepare_template_for_response( $cloned );

			return new WP_REST_Response(
				array(
					'id'       => $clone_id,
					'template' => $cloned,
					'message'  => __( 'Template cloned successfully', 'wp-ai-site-generator' ),
				),
				201
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'clone_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Export a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function export_template( $request ) {
		try {
			$template_id = $request->get_param( 'id' );
			$template = $this->db_handler->get_template( $template_id );

			if ( ! $template ) {
				return new WP_Error(
					'not_found',
					__( 'Template not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Check permissions
			if ( ! $this->can_access_template( $template ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to export this template', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Prepare export data
			$export_data = array(
				'version'         => '1.0.0',
				'exported_at'     => current_time( 'c' ),
				'name'            => $template->name,
				'description'     => $template->description,
				'type'            => $template->type,
				'category'        => $template->category,
				'prompt_template' => $template->prompt_template,
				'configuration'   => json_decode( $template->configuration, true ),
				'variables'       => json_decode( $template->variables, true ),
				'sections'        => json_decode( $template->sections, true ),
			);

			$export_json = wp_json_encode( $export_data, JSON_PRETTY_PRINT );

			return new WP_REST_Response(
				array(
					'filename' => sanitize_file_name( $template->slug . '-template.json' ),
					'data'     => $export_json,
					'message'  => __( 'Template exported successfully', 'wp-ai-site-generator' ),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'export_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Import a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function import_template( $request ) {
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

			$template_data = json_decode( $request->get_param( 'template_data' ), true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error(
					'invalid_json',
					__( 'Invalid template data format', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			// Validate required fields
			$required_fields = array( 'name', 'prompt_template' );
			foreach ( $required_fields as $field ) {
				if ( empty( $template_data[ $field ] ) ) {
					return new WP_Error(
						'missing_field',
						sprintf( __( 'Missing required field: %s', 'wp-ai-site-generator' ), $field ),
						array( 'status' => 400 )
					);
				}
			}

			// Prepare import data
			$import_data = array(
				'name'            => sanitize_text_field( $template_data['name'] . ' (Imported)' ),
				'slug'            => $this->generate_unique_slug( $template_data['name'] ),
				'description'     => sanitize_textarea_field( $template_data['description'] ?? '' ),
				'type'            => sanitize_text_field( $template_data['type'] ?? 'general' ),
				'category'        => sanitize_text_field( $template_data['category'] ?? 'general' ),
				'prompt_template' => $this->sanitize_prompt_template( $template_data['prompt_template'] ),
				'configuration'   => wp_json_encode( $template_data['configuration'] ?? array() ),
				'variables'       => wp_json_encode( $template_data['variables'] ?? array() ),
				'sections'        => wp_json_encode( $template_data['sections'] ?? array() ),
				'is_public'       => false,
				'created_by'      => get_current_user_id(),
			);

			// Validate prompt template
			if ( ! $this->validate_prompt_template( $import_data['prompt_template'] ) ) {
				return new WP_Error(
					'invalid_prompt',
					__( 'Invalid prompt template format', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			$template_id = $this->db_handler->create_template( $import_data );

			if ( ! $template_id ) {
				return new WP_Error(
					'import_failed',
					__( 'Failed to import template', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Log import
			$this->db_handler->log_activity( array(
				'user_id'     => get_current_user_id(),
				'action'      => 'template_imported',
				'object_id'   => $template_id,
				'object_type' => 'template',
				'details'     => wp_json_encode( array(
					'name'    => $import_data['name'],
					'version' => $template_data['version'] ?? 'unknown',
				) ),
			) );

			// Get imported template
			$template = $this->db_handler->get_template( $template_id );
			$template = $this->prepare_template_for_response( $template );

			return new WP_REST_Response(
				array(
					'id'       => $template_id,
					'template' => $template,
					'message'  => __( 'Template imported successfully', 'wp-ai-site-generator' ),
				),
				201
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'import_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get template categories.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_template_categories( $request ) {
		try {
			$categories = array(
				array(
					'slug'        => 'general',
					'name'        => __( 'General', 'wp-ai-site-generator' ),
					'description' => __( 'General purpose templates', 'wp-ai-site-generator' ),
					'count'       => $this->db_handler->count_templates( array( 'category' => 'general' ) ),
				),
				array(
					'slug'        => 'business',
					'name'        => __( 'Business', 'wp-ai-site-generator' ),
					'description' => __( 'Business and corporate templates', 'wp-ai-site-generator' ),
					'count'       => $this->db_handler->count_templates( array( 'category' => 'business' ) ),
				),
				array(
					'slug'        => 'portfolio',
					'name'        => __( 'Portfolio', 'wp-ai-site-generator' ),
					'description' => __( 'Portfolio and creative templates', 'wp-ai-site-generator' ),
					'count'       => $this->db_handler->count_templates( array( 'category' => 'portfolio' ) ),
				),
				array(
					'slug'        => 'blog',
					'name'        => __( 'Blog', 'wp-ai-site-generator' ),
					'description' => __( 'Blog and content templates', 'wp-ai-site-generator' ),
					'count'       => $this->db_handler->count_templates( array( 'category' => 'blog' ) ),
				),
				array(
					'slug'        => 'ecommerce',
					'name'        => __( 'E-commerce', 'wp-ai-site-generator' ),
					'description' => __( 'Online store templates', 'wp-ai-site-generator' ),
					'count'       => $this->db_handler->count_templates( array( 'category' => 'ecommerce' ) ),
				),
				array(
					'slug'        => 'landing',
					'name'        => __( 'Landing Page', 'wp-ai-site-generator' ),
					'description' => __( 'Landing page and campaign templates', 'wp-ai-site-generator' ),
					'count'       => $this->db_handler->count_templates( array( 'category' => 'landing' ) ),
				),
			);

			// Sort by count
			usort( $categories, function( $a, $b ) {
				return $b['count'] - $a['count'];
			} );

			return new WP_REST_Response( $categories );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'fetch_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Preview a template.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function preview_template( $request ) {
		try {
			$template_id = $request->get_param( 'id' );
			$variables = $request->get_param( 'variables' );

			$template = $this->db_handler->get_template( $template_id );

			if ( ! $template ) {
				return new WP_Error(
					'not_found',
					__( 'Template not found', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Check permissions
			if ( ! $this->can_access_template( $template ) ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to preview this template', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Process template with variables
			$processed_prompt = $this->process_template_variables(
				$template->prompt_template,
				$variables
			);

			// Get template variables
			$template_vars = json_decode( $template->variables, true ) ?: array();

			// Generate preview sections
			$sections = json_decode( $template->sections, true ) ?: array();
			$preview_sections = array();

			foreach ( $sections as $section ) {
				$preview_sections[] = array(
					'title'   => $section['title'] ?? '',
					'type'    => $section['type'] ?? 'content',
					'preview' => $this->generate_section_preview( $section, $variables ),
				);
			}

			return new WP_REST_Response(
				array(
					'prompt'    => $processed_prompt,
					'variables' => $template_vars,
					'sections'  => $preview_sections,
					'metadata'  => array(
						'name'        => $template->name,
						'description' => $template->description,
						'category'    => $template->category,
					),
				)
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'preview_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Prepare template for response.
	 *
	 * @since    1.0.0
	 * @param    object    $template    Template object.
	 * @param    bool      $full        Include full details.
	 * @return   array
	 */
	private function prepare_template_for_response( $template, $full = false ) {
		$data = array(
			'id'          => $template->id,
			'name'        => $template->name,
			'slug'        => $template->slug,
			'description' => $template->description,
			'type'        => $template->type,
			'category'    => $template->category,
			'is_public'   => (bool) $template->is_public,
			'created_at'  => $template->created_at,
			'updated_at'  => $template->updated_at,
			'created_by'  => $template->created_by,
		);

		// Add author info
		$author = get_userdata( $template->created_by );
		if ( $author ) {
			$data['author'] = array(
				'id'   => $author->ID,
				'name' => $author->display_name,
			);
		}

		// Add usage count
		$data['usage_count'] = $this->db_handler->count_template_usage( $template->id );

		if ( $full ) {
			$data['prompt_template'] = $template->prompt_template;
			$data['configuration'] = json_decode( $template->configuration, true );
			$data['variables'] = json_decode( $template->variables, true );
			$data['sections'] = json_decode( $template->sections, true );
		}

		return $data;
	}

	/**
	 * Generate unique slug for template.
	 *
	 * @since    1.0.0
	 * @param    string    $name           Template name.
	 * @param    int       $exclude_id     ID to exclude.
	 * @return   string
	 */
	private function generate_unique_slug( $name, $exclude_id = 0 ) {
		$slug = sanitize_title( $name );
		$original_slug = $slug;
		$counter = 1;

		while ( $this->db_handler->template_slug_exists( $slug, $exclude_id ) ) {
			$slug = $original_slug . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	/**
	 * Sanitize prompt template.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt    Prompt template.
	 * @return   string
	 */
	private function sanitize_prompt_template( $prompt ) {
		// Allow certain HTML tags and variables
		$allowed_tags = array(
			'strong' => array(),
			'em'     => array(),
			'code'   => array(),
			'br'     => array(),
		);

		$prompt = wp_kses( $prompt, $allowed_tags );

		// Preserve variable placeholders
		$prompt = preg_replace_callback(
			'/\{\{([^}]+)\}\}/',
			function( $matches ) {
				return '{{' . sanitize_text_field( $matches[1] ) . '}}';
			},
			$prompt
		);

		return $prompt;
	}

	/**
	 * Validate prompt template.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt    Prompt template.
	 * @return   bool
	 */
	private function validate_prompt_template( $prompt ) {
		// Check minimum length
		if ( strlen( $prompt ) < 10 ) {
			return false;
		}

		// Check for balanced variable brackets
		$open_count = substr_count( $prompt, '{{' );
		$close_count = substr_count( $prompt, '}}' );

		if ( $open_count !== $close_count ) {
			return false;
		}

		// Validate variable names
		if ( preg_match_all( '/\{\{([^}]+)\}\}/', $prompt, $matches ) ) {
			foreach ( $matches[1] as $var_name ) {
				if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', trim( $var_name ) ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Process template variables.
	 *
	 * @since    1.0.0
	 * @param    string    $template    Template string.
	 * @param    array     $variables   Variables to replace.
	 * @return   string
	 */
	private function process_template_variables( $template, $variables ) {
		if ( empty( $variables ) ) {
			return $template;
		}

		foreach ( $variables as $key => $value ) {
			$template = str_replace(
				'{{' . $key . '}}',
				sanitize_text_field( $value ),
				$template
			);
		}

		// Remove any remaining unprocessed variables
		$template = preg_replace( '/\{\{[^}]+\}\}/', '', $template );

		return $template;
	}

	/**
	 * Generate section preview.
	 *
	 * @since    1.0.0
	 * @param    array    $section     Section data.
	 * @param    array    $variables   Variables.
	 * @return   string
	 */
	private function generate_section_preview( $section, $variables ) {
		$preview = '';

		switch ( $section['type'] ) {
			case 'hero':
				$preview = sprintf(
					'<h1>%s</h1><p>%s</p>',
					$variables['headline'] ?? __( 'Your Headline Here', 'wp-ai-site-generator' ),
					$variables['tagline'] ?? __( 'Your tagline here', 'wp-ai-site-generator' )
				);
				break;

			case 'features':
				$preview = '<ul>';
				for ( $i = 1; $i <= 3; $i++ ) {
					$preview .= sprintf(
						'<li>%s</li>',
						$variables[ 'feature_' . $i ] ?? __( 'Feature', 'wp-ai-site-generator' ) . ' ' . $i
					);
				}
				$preview .= '</ul>';
				break;

			default:
				$preview = '<p>' . __( 'Section preview', 'wp-ai-site-generator' ) . '</p>';
		}

		return $preview;
	}

	/**
	 * Check if user can access template.
	 *
	 * @since    1.0.0
	 * @param    object    $template    Template object.
	 * @return   bool
	 */
	private function can_access_template( $template ) {
		// Admins can access all
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Owner can access
		if ( $template->created_by == get_current_user_id() ) {
			return true;
		}

		// Public templates are accessible
		if ( $template->is_public ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can edit template.
	 *
	 * @since    1.0.0
	 * @param    object    $template    Template object.
	 * @return   bool
	 */
	private function can_edit_template( $template ) {
		// Admins can edit all
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Only owner can edit
		return $template->created_by == get_current_user_id();
	}

	/**
	 * Check if user can delete template.
	 *
	 * @since    1.0.0
	 * @param    object    $template    Template object.
	 * @return   bool
	 */
	private function can_delete_template( $template ) {
		// Admins can delete all
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Only owner can delete
		return $template->created_by == get_current_user_id();
	}

	/**
	 * Get template arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_template_args() {
		return array(
			'name' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return ! empty( trim( $param ) ) && strlen( $param ) <= 200;
				},
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'type' => array(
				'type'              => 'string',
				'default'           => 'general',
				'enum'              => array( 'general', 'site', 'page', 'section', 'block' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category' => array(
				'type'              => 'string',
				'default'           => 'general',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'prompt_template' => array(
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => function( $param ) {
					return ! empty( trim( $param ) ) && strlen( $param ) >= 10;
				},
			),
			'configuration' => array(
				'type'    => 'object',
				'default' => array(),
			),
			'variables' => array(
				'type'    => 'array',
				'default' => array(),
			),
			'sections' => array(
				'type'    => 'array',
				'default' => array(),
			),
			'is_public' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Get template update arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_template_update_args() {
		$args = $this->get_template_args();

		// Make fields optional for updates
		unset( $args['name']['required'] );
		unset( $args['prompt_template']['required'] );

		// Add ID field
		$args['id'] = array(
			'required'          => true,
			'type'              => 'integer',
			'validate_callback' => function( $param ) {
				return is_numeric( $param );
			},
			'sanitize_callback' => 'absint',
		);

		return $args;
	}

	/**
	 * Check if current user can view templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_templates_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can view a template.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function get_template_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can create templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function create_template_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can update templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function update_template_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}

	/**
	 * Check if current user can delete templates.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function delete_template_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}
}