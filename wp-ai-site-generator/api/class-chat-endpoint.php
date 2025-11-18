<?php
/**
 * Chat-specific REST API endpoints.
 *
 * Handles all chat-related REST API endpoints including sessions and messages.
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
 * Chat Endpoint class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 */
class Chat_Endpoint extends WP_REST_Controller {

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
	 * Register the routes for the chat endpoints.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		// Send chat message
		register_rest_route(
			$this->namespace,
			'/chat/message',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'chat_permissions_check' ),
					'args'                => $this->get_message_args(),
				),
			)
		);

		// List chat sessions
		register_rest_route(
			$this->namespace,
			'/chat/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_sessions' ),
					'permission_callback' => array( $this, 'chat_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// Create new session
		register_rest_route(
			$this->namespace,
			'/chat/session',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_session' ),
					'permission_callback' => array( $this, 'chat_permissions_check' ),
					'args'                => $this->get_session_args(),
				),
			)
		);

		// Delete session
		register_rest_route(
			$this->namespace,
			'/chat/session/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_session' ),
					'permission_callback' => array( $this, 'chat_permissions_check' ),
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

		// Get session messages
		register_rest_route(
			$this->namespace,
			'/chat/session/(?P<id>[\d]+)/messages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_session_messages' ),
					'permission_callback' => array( $this, 'chat_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						),
						'page' => array(
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Send a chat message.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function send_message( $request ) {
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

			$session_id = $request->get_param( 'session_id' );
			$message = $request->get_param( 'message' );
			$context = $request->get_param( 'context' );
			$provider = $request->get_param( 'provider' ) ?: 'openai';

			// Validate session exists and belongs to user
			$session = $this->db_handler->get_chat_session( $session_id );
			if ( ! $session || $session->user_id != get_current_user_id() ) {
				return new WP_Error(
					'invalid_session',
					__( 'Invalid or unauthorized session', 'wp-ai-site-generator' ),
					array( 'status' => 404 )
				);
			}

			// Save user message
			$user_message_id = $this->db_handler->save_chat_message( array(
				'session_id' => $session_id,
				'role'       => 'user',
				'content'    => sanitize_textarea_field( $message ),
				'metadata'   => wp_json_encode( array( 'context' => $context ) ),
			) );

			// Get conversation history for context
			$history = $this->db_handler->get_session_messages( $session_id, array(
				'limit' => 10,
				'order' => 'DESC',
			) );

			// Prepare messages for AI provider
			$messages = array();
			foreach ( array_reverse( $history ) as $msg ) {
				$messages[] = array(
					'role'    => $msg->role,
					'content' => $msg->content,
				);
			}

			// Get AI response
			$provider_instance = $this->provider_manager->get_provider( $provider );
			if ( ! $provider_instance ) {
				return new WP_Error(
					'provider_not_found',
					__( 'AI provider not available', 'wp-ai-site-generator' ),
					array( 'status' => 400 )
				);
			}

			$response = $provider_instance->chat( $messages, array(
				'temperature' => 0.7,
				'max_tokens'  => 1000,
			) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Save AI response
			$ai_message_id = $this->db_handler->save_chat_message( array(
				'session_id' => $session_id,
				'role'       => 'assistant',
				'content'    => sanitize_textarea_field( $response['content'] ),
				'metadata'   => wp_json_encode( array(
					'provider'     => $provider,
					'tokens_used'  => $response['usage']['total_tokens'] ?? 0,
				) ),
			) );

			// Update session last activity
			$this->db_handler->update_chat_session( $session_id, array(
				'last_activity' => current_time( 'mysql' ),
				'message_count' => $session->message_count + 2,
			) );

			// Track usage
			$this->db_handler->track_usage( array(
				'user_id'      => get_current_user_id(),
				'provider'     => $provider,
				'action'       => 'chat_message',
				'tokens_used'  => $response['usage']['total_tokens'] ?? 0,
				'cost'         => $this->calculate_cost( $provider, $response['usage'] ?? array() ),
			) );

			return new WP_REST_Response(
				array(
					'message_id' => $ai_message_id,
					'response'   => $response['content'],
					'usage'      => $response['usage'] ?? array(),
				),
				201
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'message_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get chat sessions.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_sessions( $request ) {
		try {
			$page = $request->get_param( 'page' ) ?: 1;
			$per_page = $request->get_param( 'per_page' ) ?: 10;
			$search = $request->get_param( 'search' );
			$status = $request->get_param( 'status' );

			$args = array(
				'user_id' => get_current_user_id(),
				'limit'   => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
				'orderby' => $request->get_param( 'orderby' ) ?: 'last_activity',
				'order'   => $request->get_param( 'order' ) ?: 'DESC',
			);

			if ( $search ) {
				$args['search'] = sanitize_text_field( $search );
			}

			if ( $status ) {
				$args['status'] = sanitize_text_field( $status );
			}

			$sessions = $this->db_handler->get_chat_sessions( $args );
			$total = $this->db_handler->count_chat_sessions( $args );

			// Enhance session data
			foreach ( $sessions as &$session ) {
				$session->last_message = $this->db_handler->get_last_session_message( $session->id );
			}

			$response = new WP_REST_Response( $sessions );
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
	 * Create a new chat session.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function create_session( $request ) {
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

			$title = $request->get_param( 'title' );
			$context = $request->get_param( 'context' );
			$type = $request->get_param( 'type' ) ?: 'general';

			// Check session limit
			$active_sessions = $this->db_handler->count_chat_sessions( array(
				'user_id' => get_current_user_id(),
				'status'  => 'active',
			) );

			$session_limit = get_option( 'waisg_usage_limits', array() )['max_sessions'] ?? 10;
			if ( $active_sessions >= $session_limit ) {
				return new WP_Error(
					'session_limit_exceeded',
					sprintf(
						__( 'You have reached the maximum of %d active sessions', 'wp-ai-site-generator' ),
						$session_limit
					),
					array( 'status' => 429 )
				);
			}

			$session_id = $this->db_handler->create_chat_session( array(
				'user_id'       => get_current_user_id(),
				'title'         => sanitize_text_field( $title ),
				'type'          => sanitize_text_field( $type ),
				'status'        => 'active',
				'context'       => wp_json_encode( $context ),
				'last_activity' => current_time( 'mysql' ),
			) );

			if ( ! $session_id ) {
				return new WP_Error(
					'create_failed',
					__( 'Failed to create chat session', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			// Add initial system message if context provided
			if ( ! empty( $context['system_prompt'] ) ) {
				$this->db_handler->save_chat_message( array(
					'session_id' => $session_id,
					'role'       => 'system',
					'content'    => sanitize_textarea_field( $context['system_prompt'] ),
				) );
			}

			return new WP_REST_Response(
				array(
					'session_id' => $session_id,
					'message'    => __( 'Chat session created successfully', 'wp-ai-site-generator' ),
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
	 * Delete a chat session.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function delete_session( $request ) {
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

			$session_id = $request->get_param( 'id' );

			// Verify ownership
			$session = $this->db_handler->get_chat_session( $session_id );
			if ( ! $session || $session->user_id != get_current_user_id() ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to delete this session', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			// Delete messages first
			$this->db_handler->delete_session_messages( $session_id );

			// Delete session
			$deleted = $this->db_handler->delete_chat_session( $session_id );

			if ( ! $deleted ) {
				return new WP_Error(
					'delete_failed',
					__( 'Failed to delete chat session', 'wp-ai-site-generator' ),
					array( 'status' => 500 )
				);
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Chat session deleted successfully', 'wp-ai-site-generator' ),
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
	 * Get messages for a specific session.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request    $request    The request object.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_session_messages( $request ) {
		try {
			$session_id = $request->get_param( 'id' );
			$page = $request->get_param( 'page' ) ?: 1;
			$per_page = $request->get_param( 'per_page' ) ?: 20;

			// Verify session ownership
			$session = $this->db_handler->get_chat_session( $session_id );
			if ( ! $session || $session->user_id != get_current_user_id() ) {
				return new WP_Error(
					'unauthorized',
					__( 'You are not authorized to view this session', 'wp-ai-site-generator' ),
					array( 'status' => 403 )
				);
			}

			$args = array(
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
				'order'  => 'ASC',
			);

			$messages = $this->db_handler->get_session_messages( $session_id, $args );
			$total = $this->db_handler->count_session_messages( $session_id );

			// Process messages for output
			foreach ( $messages as &$message ) {
				$message->metadata = json_decode( $message->metadata );
				$message->formatted_date = human_time_diff(
					strtotime( $message->created_at ),
					current_time( 'timestamp' )
				) . ' ' . __( 'ago', 'wp-ai-site-generator' );
			}

			$response = new WP_REST_Response( array(
				'session'  => $session,
				'messages' => $messages,
			) );
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
	 * Calculate cost for API usage.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    The provider name.
	 * @param    array     $usage       Usage data.
	 * @return   float
	 */
	private function calculate_cost( $provider, $usage ) {
		$pricing = array(
			'openai' => array(
				'input'  => 0.0015 / 1000, // per token
				'output' => 0.002 / 1000,
			),
			'claude' => array(
				'input'  => 0.008 / 1000,
				'output' => 0.024 / 1000,
			),
			'groq' => array(
				'input'  => 0.00027 / 1000,
				'output' => 0.00027 / 1000,
			),
		);

		if ( ! isset( $pricing[ $provider ] ) ) {
			return 0;
		}

		$cost = 0;
		$cost += ( $usage['prompt_tokens'] ?? 0 ) * $pricing[ $provider ]['input'];
		$cost += ( $usage['completion_tokens'] ?? 0 ) * $pricing[ $provider ]['output'];

		return round( $cost, 6 );
	}

	/**
	 * Get message arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_message_args() {
		return array(
			'session_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => function( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			),
			'message' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => function( $param ) {
					return ! empty( trim( $param ) );
				},
			),
			'context' => array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => function( $param ) {
					return array_map( 'sanitize_text_field', $param );
				},
			),
			'provider' => array(
				'type'              => 'string',
				'default'           => 'openai',
				'enum'              => array( 'openai', 'claude', 'groq', 'cohere' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get session arguments.
	 *
	 * @since    1.0.0
	 * @return   array
	 */
	private function get_session_args() {
		return array(
			'title' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return ! empty( trim( $param ) ) && strlen( $param ) <= 200;
				},
			),
			'type' => array(
				'type'              => 'string',
				'default'           => 'general',
				'enum'              => array( 'general', 'site_planning', 'content_generation', 'design', 'support' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'context' => array(
				'type'              => 'object',
				'default'           => array(),
				'sanitize_callback' => function( $param ) {
					return array_map( 'sanitize_textarea_field', $param );
				},
			),
		);
	}

	/**
	 * Check if current user can access chat features.
	 *
	 * @since    1.0.0
	 * @return   bool
	 */
	public function chat_permissions_check() {
		return is_user_logged_in() && current_user_can( 'edit_posts' );
	}
}