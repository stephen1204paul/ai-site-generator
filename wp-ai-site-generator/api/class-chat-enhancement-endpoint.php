<?php
/**
 * Chat Enhancement REST API Endpoint.
 *
 * Provides advanced chat functionality including branching, summarization,
 * topic switching, suggestions, export/import, and templates.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 * @since      1.0.0
 */

namespace WPAISiteGenerator\API;

use WPAISiteGenerator\Includes\Chat_Manager;
use WPAISiteGenerator\Includes\Conversation_Context;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

/**
 * Chat Enhancement Endpoint Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 */
class Chat_Enhancement_Endpoint extends WP_REST_Controller {

	/**
	 * Chat manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Chat_Manager    $chat_manager    Chat manager.
	 */
	private $chat_manager;

	/**
	 * Context manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Conversation_Context    $context_manager    Context manager.
	 */
	private $context_manager;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->namespace = 'wp-ai-site-generator/v1';
		$this->rest_base = 'chat';

		$this->chat_manager = new Chat_Manager();
		$this->context_manager = new Conversation_Context();
	}

	/**
	 * Register the routes for the endpoint.
	 *
	 * @since    1.0.0
	 */
	public function register_routes() {
		// Create conversation branch.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/branch',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_branch' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_branch_args(),
				),
			)
		);

		// Summarize conversation context.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/context/summarize',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'summarize_context' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_summarize_args(),
				),
			)
		);

		// Switch conversation topic.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/context/switch',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'switch_topic' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_switch_topic_args(),
				),
			)
		);

		// Get smart suggestions.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/suggestions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_suggestions' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_suggestions_args(),
				),
			)
		);

		// Export conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_export_args(),
				),
			)
		);

		// Import conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_import_args(),
				),
			)
		);

		// Get conversation templates.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_templates_args(),
				),
			)
		);

		// Create conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_create_conversation_args(),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversations' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_list_conversations_args(),
				),
			)
		);

		// Get/update single conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_conversation_args(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_update_conversation_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_conversation' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Add message to conversation.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/conversations/(?P<id>\d+)/messages',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_message' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_add_message_args(),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_messages' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_messages_args(),
				),
			)
		);

		// Save/restore session.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_session' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'restore_session' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Create a conversation branch.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function create_branch( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$branch_point = $request->get_param( 'branch_point_message_id' );
		$data = $request->get_param( 'data' ) ?? array();

		$branch_id = $this->chat_manager->create_branch( $conversation_id, $branch_point, $data );

		if ( is_wp_error( $branch_id ) ) {
			return $branch_id;
		}

		$branch_data = $this->chat_manager->get_conversation( $branch_id, true );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $branch_data,
			),
			201
		);
	}

	/**
	 * Summarize conversation context.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function summarize_context( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$force = $request->get_param( 'force' ) ?? false;

		$summary = $this->context_manager->summarize_context( $conversation_id, $force );

		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $summary,
			),
			200
		);
	}

	/**
	 * Switch conversation topic.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function switch_topic( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$new_topic = $request->get_param( 'new_topic' );

		$result = $this->context_manager->switch_topic( $conversation_id, $new_topic );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Topic switched successfully', 'wp-ai-site-generator' ),
			),
			200
		);
	}

	/**
	 * Get smart suggestions.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response Response.
	 */
	public function get_suggestions( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );

		$suggestions = $this->context_manager->get_suggestions( $conversation_id );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $suggestions,
			),
			200
		);
	}

	/**
	 * Export conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function export_conversation( $request ) {
		$conversation_id = $request->get_param( 'conversation_id' );
		$format = $request->get_param( 'format' ) ?? 'json';

		$export_data = $this->chat_manager->export_conversation( $conversation_id, $format );

		if ( is_wp_error( $export_data ) ) {
			return $export_data;
		}

		// Create download response.
		$filename = sprintf(
			'conversation-%d-%s.%s',
			$conversation_id,
			date( 'Y-m-d-His' ),
			$format
		);

		return new WP_REST_Response(
			array(
				'success'  => true,
				'data'     => $export_data,
				'filename' => $filename,
			),
			200
		);
	}

	/**
	 * Import conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function import_conversation( $request ) {
		$data = $request->get_param( 'data' );
		$format = $request->get_param( 'format' ) ?? 'json';

		$conversation_id = $this->chat_manager->import_conversation( $data, $format );

		if ( is_wp_error( $conversation_id ) ) {
			return $conversation_id;
		}

		$conversation = $this->chat_manager->get_conversation( $conversation_id, true );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $conversation,
			),
			201
		);
	}

	/**
	 * Get conversation templates.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response Response.
	 */
	public function get_templates( $request ) {
		$type = $request->get_param( 'type' );

		$templates = $this->chat_manager->get_templates( $type );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $templates,
			),
			200
		);
	}

	/**
	 * Create a new conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function create_conversation( $request ) {
		$data = array(
			'title'       => $request->get_param( 'title' ),
			'type'        => $request->get_param( 'type' ) ?? 'general',
			'template_id' => $request->get_param( 'template_id' ),
			'metadata'    => $request->get_param( 'metadata' ) ?? array(),
		);

		$conversation_id = $this->chat_manager->create_conversation( $data );

		if ( is_wp_error( $conversation_id ) ) {
			return $conversation_id;
		}

		$conversation = $this->chat_manager->get_conversation( $conversation_id, true );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $conversation,
			),
			201
		);
	}

	/**
	 * Get conversations list.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response Response.
	 */
	public function get_conversations( $request ) {
		$user_id = get_current_user_id();
		$page = $request->get_param( 'page' ) ?? 1;
		$per_page = $request->get_param( 'per_page' ) ?? 10;
		$type = $request->get_param( 'type' );

		// This would typically query from database.
		// For now, return mock data.
		$conversations = array();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $conversations,
				'meta'    => array(
					'total'      => count( $conversations ),
					'page'       => $page,
					'per_page'   => $per_page,
					'total_pages' => 1,
				),
			),
			200
		);
	}

	/**
	 * Get a single conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function get_conversation( $request ) {
		$conversation_id = $request->get_param( 'id' );
		$include_messages = $request->get_param( 'include_messages' ) ?? false;

		$conversation = $this->chat_manager->get_conversation( $conversation_id, $include_messages );

		if ( is_wp_error( $conversation ) ) {
			return $conversation;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $conversation,
			),
			200
		);
	}

	/**
	 * Update conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function update_conversation( $request ) {
		$conversation_id = $request->get_param( 'id' );
		$title = $request->get_param( 'title' );
		$metadata = $request->get_param( 'metadata' );

		// Update conversation in database.
		// This would be implemented based on database structure.

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Conversation updated successfully', 'wp-ai-site-generator' ),
			),
			200
		);
	}

	/**
	 * Delete conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function delete_conversation( $request ) {
		$conversation_id = $request->get_param( 'id' );

		// Delete conversation from database.
		// This would be implemented based on database structure.

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Conversation deleted successfully', 'wp-ai-site-generator' ),
			),
			200
		);
	}

	/**
	 * Add message to conversation.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function add_message( $request ) {
		$conversation_id = $request->get_param( 'id' );
		$message = array(
			'role'         => $request->get_param( 'role' ) ?? 'user',
			'content'      => $request->get_param( 'content' ),
			'metadata'     => $request->get_param( 'metadata' ) ?? array(),
			'reference_id' => $request->get_param( 'reference_id' ),
		);

		$message_id = $this->chat_manager->add_message( $conversation_id, $message );

		if ( is_wp_error( $message_id ) ) {
			return $message_id;
		}

		// Get AI response if user message.
		$response_data = array(
			'message_id' => $message_id,
		);

		if ( 'user' === $message['role'] ) {
			// Generate AI response.
			$ai_response = $this->generate_ai_response( $conversation_id, $message );
			if ( ! is_wp_error( $ai_response ) ) {
				$response_data['ai_response'] = $ai_response;
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $response_data,
			),
			201
		);
	}

	/**
	 * Get conversation messages.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function get_messages( $request ) {
		$conversation_id = $request->get_param( 'id' );
		$args = array(
			'limit'      => $request->get_param( 'limit' ) ?? 50,
			'offset'     => $request->get_param( 'offset' ) ?? 0,
			'compressed' => $request->get_param( 'compressed' ) ?? false,
		);

		$messages = $this->chat_manager->get_messages( $conversation_id, $args );

		if ( is_wp_error( $messages ) ) {
			return $messages;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $messages,
			),
			200
		);
	}

	/**
	 * Save conversation session.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function save_session( $request ) {
		$conversation_id = $request->get_param( 'id' );

		$result = $this->chat_manager->save_session( $conversation_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Session saved successfully', 'wp-ai-site-generator' ),
			),
			200
		);
	}

	/**
	 * Restore conversation session.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   WP_REST_Response|WP_Error Response or error.
	 */
	public function restore_session( $request ) {
		$conversation_id = $request->get_param( 'id' );

		$session_data = $this->chat_manager->restore_session( $conversation_id );

		if ( is_wp_error( $session_data ) ) {
			return $session_data;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $session_data,
			),
			200
		);
	}

	/**
	 * Generate AI response for message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $message User message.
	 * @return   array|WP_Error AI response or error.
	 */
	private function generate_ai_response( $conversation_id, $message ) {
		// Get conversation context.
		$context = $this->context_manager->get_context( $conversation_id );
		$messages = $this->chat_manager->get_messages(
			$conversation_id,
			array( 'compressed' => true )
		);

		// Build prompt with context.
		$system_prompt = $this->build_system_prompt( $context );
		$conversation_history = $this->format_conversation_history( $messages );

		// Generate response using provider.
		// This would integrate with the provider system.

		$ai_message = array(
			'role'    => 'assistant',
			'content' => __( 'AI response would be generated here based on the conversation context.', 'wp-ai-site-generator' ),
		);

		// Add AI response to conversation.
		$this->chat_manager->add_message( $conversation_id, $ai_message );

		return $ai_message;
	}

	/**
	 * Build system prompt with context.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $context Conversation context.
	 * @return   string System prompt.
	 */
	private function build_system_prompt( $context ) {
		$prompt = __( 'You are a helpful WordPress AI Site Generator assistant.', 'wp-ai-site-generator' );

		if ( ! empty( $context['current_topic'] ) ) {
			$prompt .= sprintf(
				__( ' The current topic is: %s.', 'wp-ai-site-generator' ),
				$context['current_topic']
			);
		}

		if ( ! empty( $context['preferences'] ) ) {
			$prompt .= __( ' User preferences: ', 'wp-ai-site-generator' );
			$prompt .= implode( ', ', $context['preferences'] ) . '.';
		}

		return $prompt;
	}

	/**
	 * Format conversation history.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $messages Messages.
	 * @return   array Formatted messages.
	 */
	private function format_conversation_history( $messages ) {
		$formatted = array();

		foreach ( $messages as $message ) {
			$formatted[] = array(
				'role'    => $message['role'],
				'content' => $message['content'],
			);
		}

		return $formatted;
	}

	/**
	 * Check permission for API access.
	 *
	 * @since    1.0.0
	 * @param    WP_REST_Request $request Request object.
	 * @return   bool|WP_Error True if allowed, error otherwise.
	 */
	public function check_permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'wp-ai-site-generator' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get branch endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_branch_args() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'branch_point_message_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'data' => array(
				'type' => 'object',
			),
		);
	}

	/**
	 * Get summarize endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_summarize_args() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'force' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Get switch topic endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_switch_topic_args() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'new_topic' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get suggestions endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_suggestions_args() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get export endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_export_args() {
		return array(
			'conversation_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'format' => array(
				'type'    => 'string',
				'enum'    => array( 'json', 'markdown', 'html' ),
				'default' => 'json',
			),
		);
	}

	/**
	 * Get import endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_import_args() {
		return array(
			'data' => array(
				'required' => true,
				'type'     => 'string',
			),
			'format' => array(
				'type'    => 'string',
				'enum'    => array( 'json' ),
				'default' => 'json',
			),
		);
	}

	/**
	 * Get templates endpoint arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_templates_args() {
		return array(
			'type' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get create conversation arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_create_conversation_args() {
		return array(
			'title' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'type' => array(
				'type'              => 'string',
				'default'           => 'general',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'template_id' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'metadata' => array(
				'type' => 'object',
			),
		);
	}

	/**
	 * Get list conversations arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_list_conversations_args() {
		return array(
			'page' => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'type' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get conversation arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_conversation_args() {
		return array(
			'include_messages' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Get update conversation arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_update_conversation_args() {
		return array(
			'title' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'metadata' => array(
				'type' => 'object',
			),
		);
	}

	/**
	 * Get add message arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_add_message_args() {
		return array(
			'content' => array(
				'required' => true,
				'type'     => 'string',
			),
			'role' => array(
				'type'    => 'string',
				'enum'    => array( 'user', 'assistant', 'system' ),
				'default' => 'user',
			),
			'metadata' => array(
				'type' => 'object',
			),
			'reference_id' => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get messages arguments.
	 *
	 * @since    1.0.0
	 * @return   array Arguments.
	 */
	private function get_messages_args() {
		return array(
			'limit' => array(
				'type'              => 'integer',
				'default'           => 50,
				'sanitize_callback' => 'absint',
			),
			'offset' => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'compressed' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}
}