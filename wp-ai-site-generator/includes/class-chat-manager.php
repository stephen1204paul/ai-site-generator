<?php
/**
 * Advanced Chat Manager Class.
 *
 * Manages conversation memory, context tracking, multi-turn conversations, branching,
 * session persistence, and conversation templates.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Database\DB_Handler;
use WP_Error;
use Exception;

/**
 * Advanced Chat Manager Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Chat_Manager {

	/**
	 * Database handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler.
	 */
	private $db_handler;

	/**
	 * Context manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Conversation_Context    $context_manager    Context manager.
	 */
	private $context_manager;

	/**
	 * Maximum messages in context window.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_context_messages    Maximum context messages.
	 */
	private $max_context_messages = 50;

	/**
	 * Conversation templates.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $templates    Conversation templates.
	 */
	private $templates = array();

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->db_handler = new DB_Handler();
		$this->context_manager = new Conversation_Context();
		$this->load_templates();
	}

	/**
	 * Create a new conversation.
	 *
	 * @since    1.0.0
	 * @param    array $data Conversation data.
	 * @return   int|WP_Error Conversation ID or error.
	 */
	public function create_conversation( $data = array() ) {
		try {
			$defaults = array(
				'user_id'     => get_current_user_id(),
				'title'       => __( 'New Conversation', 'wp-ai-site-generator' ),
				'type'        => 'general',
				'template_id' => null,
				'parent_id'   => null,
				'metadata'    => array(),
				'created_at'  => current_time( 'mysql' ),
				'status'      => 'active',
			);

			$conversation = wp_parse_args( $data, $defaults );

			// If using a template, apply template data.
			if ( ! empty( $conversation['template_id'] ) ) {
				$template_data = $this->get_template( $conversation['template_id'] );
				if ( $template_data ) {
					$conversation['metadata']['template'] = $template_data;
					$conversation['type'] = $template_data['type'] ?? 'general';
				}
			}

			// Store in database.
			$conversation_id = $this->db_handler->insert_conversation( $conversation );

			if ( is_wp_error( $conversation_id ) ) {
				return $conversation_id;
			}

			// Initialize context for new conversation.
			$this->context_manager->initialize_context( $conversation_id, $conversation['metadata'] );

			// If template has initial messages, add them.
			if ( ! empty( $template_data['initial_messages'] ) ) {
				foreach ( $template_data['initial_messages'] as $message ) {
					$this->add_message( $conversation_id, $message );
				}
			}

			return $conversation_id;

		} catch ( Exception $e ) {
			return new WP_Error(
				'conversation_creation_failed',
				sprintf( __( 'Failed to create conversation: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Add a message to a conversation.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $message Message data.
	 * @return   int|WP_Error Message ID or error.
	 */
	public function add_message( $conversation_id, $message ) {
		try {
			$defaults = array(
				'role'         => 'user',
				'content'      => '',
				'metadata'     => array(),
				'tokens'       => 0,
				'created_at'   => current_time( 'mysql' ),
				'reference_id' => null,
			);

			$message_data = wp_parse_args( $message, $defaults );
			$message_data['conversation_id'] = $conversation_id;

			// Estimate token count if not provided.
			if ( empty( $message_data['tokens'] ) ) {
				$message_data['tokens'] = $this->estimate_tokens( $message_data['content'] );
			}

			// Store message.
			$message_id = $this->db_handler->insert_message( $message_data );

			if ( is_wp_error( $message_id ) ) {
				return $message_id;
			}

			// Update conversation context.
			$this->context_manager->update_context( $conversation_id, $message_data );

			// Manage context window.
			$this->manage_context_window( $conversation_id );

			// Learn from user preferences if applicable.
			if ( 'user' === $message_data['role'] ) {
				$this->context_manager->learn_preferences( $conversation_id, $message_data );
			}

			return $message_id;

		} catch ( Exception $e ) {
			return new WP_Error(
				'message_addition_failed',
				sprintf( __( 'Failed to add message: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Create a conversation branch.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Parent conversation ID.
	 * @param    int   $branch_point_message_id Message ID to branch from.
	 * @param    array $data Branch data.
	 * @return   int|WP_Error New conversation ID or error.
	 */
	public function create_branch( $conversation_id, $branch_point_message_id, $data = array() ) {
		try {
			// Get parent conversation.
			$parent_conversation = $this->get_conversation( $conversation_id );
			if ( is_wp_error( $parent_conversation ) ) {
				return $parent_conversation;
			}

			// Get messages up to branch point.
			$messages = $this->get_messages_up_to( $conversation_id, $branch_point_message_id );
			if ( is_wp_error( $messages ) ) {
				return $messages;
			}

			// Create new conversation as branch.
			$branch_data = wp_parse_args(
				$data,
				array(
					'title'     => sprintf(
						__( 'Branch from: %s', 'wp-ai-site-generator' ),
						$parent_conversation['title']
					),
					'parent_id' => $conversation_id,
					'metadata'  => array(
						'branch_point' => $branch_point_message_id,
						'parent_context' => $parent_conversation['metadata'],
					),
				)
			);

			$branch_id = $this->create_conversation( $branch_data );
			if ( is_wp_error( $branch_id ) ) {
				return $branch_id;
			}

			// Copy messages to branch.
			foreach ( $messages as $message ) {
				unset( $message['id'] );
				$message['conversation_id'] = $branch_id;
				$this->db_handler->insert_message( $message );
			}

			// Initialize branch context based on parent.
			$this->context_manager->branch_context( $conversation_id, $branch_id, $branch_point_message_id );

			return $branch_id;

		} catch ( Exception $e ) {
			return new WP_Error(
				'branch_creation_failed',
				sprintf( __( 'Failed to create branch: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Get conversation with full context.
	 *
	 * @since    1.0.0
	 * @param    int  $conversation_id Conversation ID.
	 * @param    bool $include_messages Include messages.
	 * @return   array|WP_Error Conversation data or error.
	 */
	public function get_conversation( $conversation_id, $include_messages = false ) {
		try {
			$conversation = $this->db_handler->get_conversation( $conversation_id );
			if ( ! $conversation ) {
				return new WP_Error(
					'conversation_not_found',
					__( 'Conversation not found', 'wp-ai-site-generator' )
				);
			}

			// Add context summary.
			$conversation['context'] = $this->context_manager->get_context_summary( $conversation_id );

			// Add statistics.
			$conversation['stats'] = $this->get_conversation_stats( $conversation_id );

			// Include messages if requested.
			if ( $include_messages ) {
				$conversation['messages'] = $this->get_messages( $conversation_id );
			}

			// Add branches if any.
			$conversation['branches'] = $this->get_branches( $conversation_id );

			return $conversation;

		} catch ( Exception $e ) {
			return new WP_Error(
				'conversation_retrieval_failed',
				sprintf( __( 'Failed to get conversation: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Get conversation messages with context management.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $args Query arguments.
	 * @return   array|WP_Error Messages or error.
	 */
	public function get_messages( $conversation_id, $args = array() ) {
		$defaults = array(
			'limit'      => $this->max_context_messages,
			'offset'     => 0,
			'order'      => 'ASC',
			'compressed' => false,
		);

		$args = wp_parse_args( $args, $defaults );

		try {
			$messages = $this->db_handler->get_messages( $conversation_id, $args );

			if ( is_wp_error( $messages ) ) {
				return $messages;
			}

			// Apply context compression if requested.
			if ( $args['compressed'] ) {
				$messages = $this->context_manager->compress_messages( $messages );
			}

			return $messages;

		} catch ( Exception $e ) {
			return new WP_Error(
				'messages_retrieval_failed',
				sprintf( __( 'Failed to get messages: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Export conversation.
	 *
	 * @since    1.0.0
	 * @param    int    $conversation_id Conversation ID.
	 * @param    string $format Export format.
	 * @return   array|WP_Error Export data or error.
	 */
	public function export_conversation( $conversation_id, $format = 'json' ) {
		try {
			$conversation = $this->get_conversation( $conversation_id, true );
			if ( is_wp_error( $conversation ) ) {
				return $conversation;
			}

			$export_data = array(
				'version'      => '1.0.0',
				'exported_at'  => current_time( 'mysql' ),
				'conversation' => $conversation,
			);

			switch ( $format ) {
				case 'json':
					return array(
						'format'  => 'json',
						'content' => wp_json_encode( $export_data, JSON_PRETTY_PRINT ),
						'mime'    => 'application/json',
					);

				case 'markdown':
					return array(
						'format'  => 'markdown',
						'content' => $this->format_as_markdown( $export_data ),
						'mime'    => 'text/markdown',
					);

				case 'html':
					return array(
						'format'  => 'html',
						'content' => $this->format_as_html( $export_data ),
						'mime'    => 'text/html',
					);

				default:
					return new WP_Error(
						'unsupported_format',
						sprintf( __( 'Unsupported export format: %s', 'wp-ai-site-generator' ), $format )
					);
			}

		} catch ( Exception $e ) {
			return new WP_Error(
				'export_failed',
				sprintf( __( 'Failed to export conversation: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Import conversation.
	 *
	 * @since    1.0.0
	 * @param    string $data Import data.
	 * @param    string $format Import format.
	 * @return   int|WP_Error Conversation ID or error.
	 */
	public function import_conversation( $data, $format = 'json' ) {
		try {
			$import_data = null;

			switch ( $format ) {
				case 'json':
					$import_data = json_decode( $data, true );
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						return new WP_Error(
							'invalid_json',
							__( 'Invalid JSON data', 'wp-ai-site-generator' )
						);
					}
					break;

				default:
					return new WP_Error(
						'unsupported_format',
						sprintf( __( 'Unsupported import format: %s', 'wp-ai-site-generator' ), $format )
					);
			}

			if ( ! isset( $import_data['conversation'] ) ) {
				return new WP_Error(
					'invalid_import_data',
					__( 'Invalid import data structure', 'wp-ai-site-generator' )
				);
			}

			$conversation_data = $import_data['conversation'];
			unset( $conversation_data['id'] );
			$conversation_data['imported_at'] = current_time( 'mysql' );

			// Create new conversation.
			$conversation_id = $this->create_conversation( $conversation_data );
			if ( is_wp_error( $conversation_id ) ) {
				return $conversation_id;
			}

			// Import messages.
			if ( ! empty( $conversation_data['messages'] ) ) {
				foreach ( $conversation_data['messages'] as $message ) {
					unset( $message['id'] );
					$this->add_message( $conversation_id, $message );
				}
			}

			return $conversation_id;

		} catch ( Exception $e ) {
			return new WP_Error(
				'import_failed',
				sprintf( __( 'Failed to import conversation: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Get conversation templates.
	 *
	 * @since    1.0.0
	 * @param    string $type Template type filter.
	 * @return   array Templates.
	 */
	public function get_templates( $type = null ) {
		if ( $type ) {
			return array_filter(
				$this->templates,
				function( $template ) use ( $type ) {
					return $template['type'] === $type;
				}
			);
		}
		return $this->templates;
	}

	/**
	 * Get a specific template.
	 *
	 * @since    1.0.0
	 * @param    string $template_id Template ID.
	 * @return   array|null Template data.
	 */
	public function get_template( $template_id ) {
		return $this->templates[ $template_id ] ?? null;
	}

	/**
	 * Save conversation session.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   bool|WP_Error Success or error.
	 */
	public function save_session( $conversation_id ) {
		try {
			$session_data = array(
				'conversation_id' => $conversation_id,
				'context'         => $this->context_manager->get_context( $conversation_id ),
				'timestamp'       => current_time( 'mysql' ),
			);

			// Store in user meta for persistence.
			$user_id = get_current_user_id();
			if ( $user_id ) {
				$sessions = get_user_meta( $user_id, 'wpaisg_chat_sessions', true );
				if ( ! is_array( $sessions ) ) {
					$sessions = array();
				}
				$sessions[ $conversation_id ] = $session_data;
				update_user_meta( $user_id, 'wpaisg_chat_sessions', $sessions );
			}

			// Also store in transient for quick access.
			set_transient( 'wpaisg_session_' . $conversation_id, $session_data, DAY_IN_SECONDS );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error(
				'session_save_failed',
				sprintf( __( 'Failed to save session: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Restore conversation session.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   array|WP_Error Session data or error.
	 */
	public function restore_session( $conversation_id ) {
		try {
			// Try transient first.
			$session_data = get_transient( 'wpaisg_session_' . $conversation_id );

			if ( false === $session_data ) {
				// Try user meta.
				$user_id = get_current_user_id();
				if ( $user_id ) {
					$sessions = get_user_meta( $user_id, 'wpaisg_chat_sessions', true );
					if ( is_array( $sessions ) && isset( $sessions[ $conversation_id ] ) ) {
						$session_data = $sessions[ $conversation_id ];
						// Refresh transient.
						set_transient( 'wpaisg_session_' . $conversation_id, $session_data, DAY_IN_SECONDS );
					}
				}
			}

			if ( ! $session_data ) {
				return new WP_Error(
					'session_not_found',
					__( 'Session not found', 'wp-ai-site-generator' )
				);
			}

			// Restore context.
			if ( isset( $session_data['context'] ) ) {
				$this->context_manager->restore_context( $conversation_id, $session_data['context'] );
			}

			return $session_data;

		} catch ( Exception $e ) {
			return new WP_Error(
				'session_restore_failed',
				sprintf( __( 'Failed to restore session: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Manage context window size.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $conversation_id Conversation ID.
	 * @return   void
	 */
	private function manage_context_window( $conversation_id ) {
		$messages = $this->db_handler->get_messages( $conversation_id );

		if ( count( $messages ) > $this->max_context_messages ) {
			// Get messages to compress.
			$messages_to_compress = array_slice(
				$messages,
				0,
				count( $messages ) - $this->max_context_messages
			);

			// Compress old messages.
			$compressed_context = $this->context_manager->compress_messages( $messages_to_compress );

			// Store compressed context.
			$this->context_manager->store_compressed_context( $conversation_id, $compressed_context );
		}
	}

	/**
	 * Get conversation statistics.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $conversation_id Conversation ID.
	 * @return   array Statistics.
	 */
	private function get_conversation_stats( $conversation_id ) {
		$messages = $this->db_handler->get_messages( $conversation_id );

		$stats = array(
			'total_messages' => count( $messages ),
			'user_messages'  => 0,
			'ai_messages'    => 0,
			'total_tokens'   => 0,
			'branches_count' => count( $this->get_branches( $conversation_id ) ),
		);

		foreach ( $messages as $message ) {
			if ( 'user' === $message['role'] ) {
				$stats['user_messages']++;
			} elseif ( 'assistant' === $message['role'] ) {
				$stats['ai_messages']++;
			}
			$stats['total_tokens'] += $message['tokens'] ?? 0;
		}

		return $stats;
	}

	/**
	 * Get conversation branches.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $conversation_id Parent conversation ID.
	 * @return   array Branches.
	 */
	private function get_branches( $conversation_id ) {
		return $this->db_handler->get_conversations_by_parent( $conversation_id );
	}

	/**
	 * Get messages up to a specific message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $conversation_id Conversation ID.
	 * @param    int $message_id Target message ID.
	 * @return   array|WP_Error Messages or error.
	 */
	private function get_messages_up_to( $conversation_id, $message_id ) {
		$all_messages = $this->db_handler->get_messages( $conversation_id );
		$messages = array();

		foreach ( $all_messages as $message ) {
			$messages[] = $message;
			if ( $message['id'] == $message_id ) {
				break;
			}
		}

		return $messages;
	}

	/**
	 * Estimate token count for text.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $text Text to estimate.
	 * @return   int Estimated tokens.
	 */
	private function estimate_tokens( $text ) {
		// Simple estimation: ~4 characters per token.
		return ceil( strlen( $text ) / 4 );
	}

	/**
	 * Format conversation as Markdown.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data Export data.
	 * @return   string Markdown content.
	 */
	private function format_as_markdown( $data ) {
		$markdown = "# " . $data['conversation']['title'] . "\n\n";
		$markdown .= "**Exported:** " . $data['exported_at'] . "\n\n";
		$markdown .= "---\n\n";

		if ( ! empty( $data['conversation']['messages'] ) ) {
			foreach ( $data['conversation']['messages'] as $message ) {
				$role = ucfirst( $message['role'] );
				$markdown .= "## $role\n\n";
				$markdown .= $message['content'] . "\n\n";
				$markdown .= "---\n\n";
			}
		}

		return $markdown;
	}

	/**
	 * Format conversation as HTML.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $data Export data.
	 * @return   string HTML content.
	 */
	private function format_as_html( $data ) {
		$html = '<!DOCTYPE html><html><head>';
		$html .= '<meta charset="UTF-8">';
		$html .= '<title>' . esc_html( $data['conversation']['title'] ) . '</title>';
		$html .= '<style>
			body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
			.message { margin: 20px 0; padding: 15px; border-radius: 8px; }
			.user { background: #e3f2fd; }
			.assistant { background: #f5f5f5; }
			.role { font-weight: bold; margin-bottom: 10px; }
		</style>';
		$html .= '</head><body>';
		$html .= '<h1>' . esc_html( $data['conversation']['title'] ) . '</h1>';
		$html .= '<p><em>Exported: ' . esc_html( $data['exported_at'] ) . '</em></p>';

		if ( ! empty( $data['conversation']['messages'] ) ) {
			foreach ( $data['conversation']['messages'] as $message ) {
				$html .= '<div class="message ' . esc_attr( $message['role'] ) . '">';
				$html .= '<div class="role">' . esc_html( ucfirst( $message['role'] ) ) . '</div>';
				$html .= '<div class="content">' . wp_kses_post( wpautop( $message['content'] ) ) . '</div>';
				$html .= '</div>';
			}
		}

		$html .= '</body></html>';
		return $html;
	}

	/**
	 * Load conversation templates.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_templates() {
		$this->templates = array(
			'site_creation' => array(
				'id'          => 'site_creation',
				'name'        => __( 'Site Creation Wizard', 'wp-ai-site-generator' ),
				'description' => __( 'Guide through creating a new website', 'wp-ai-site-generator' ),
				'type'        => 'wizard',
				'initial_messages' => array(
					array(
						'role'    => 'assistant',
						'content' => __( "Let's create your website! What type of site would you like to build? (e.g., blog, portfolio, business, e-commerce)", 'wp-ai-site-generator' ),
					),
				),
				'prompts' => array(
					'site_type',
					'target_audience',
					'key_features',
					'design_preferences',
				),
			),
			'content_generation' => array(
				'id'          => 'content_generation',
				'name'        => __( 'Content Generator', 'wp-ai-site-generator' ),
				'description' => __( 'Generate various types of content', 'wp-ai-site-generator' ),
				'type'        => 'generator',
				'initial_messages' => array(
					array(
						'role'    => 'assistant',
						'content' => __( 'What type of content would you like to generate? I can help with blog posts, pages, product descriptions, and more.', 'wp-ai-site-generator' ),
					),
				),
			),
			'seo_optimization' => array(
				'id'          => 'seo_optimization',
				'name'        => __( 'SEO Optimizer', 'wp-ai-site-generator' ),
				'description' => __( 'Optimize content for search engines', 'wp-ai-site-generator' ),
				'type'        => 'optimizer',
				'initial_messages' => array(
					array(
						'role'    => 'assistant',
						'content' => __( 'I can help optimize your content for search engines. Please provide the content or URL you want to optimize.', 'wp-ai-site-generator' ),
					),
				),
			),
			'technical_support' => array(
				'id'          => 'technical_support',
				'name'        => __( 'Technical Support', 'wp-ai-site-generator' ),
				'description' => __( 'Get help with technical issues', 'wp-ai-site-generator' ),
				'type'        => 'support',
				'initial_messages' => array(
					array(
						'role'    => 'assistant',
						'content' => __( "I'm here to help with any technical issues. What problem are you experiencing?", 'wp-ai-site-generator' ),
					),
				),
			),
			'brainstorming' => array(
				'id'          => 'brainstorming',
				'name'        => __( 'Brainstorming Session', 'wp-ai-site-generator' ),
				'description' => __( 'Brainstorm ideas for your project', 'wp-ai-site-generator' ),
				'type'        => 'creative',
				'initial_messages' => array(
					array(
						'role'    => 'assistant',
						'content' => __( "Let's brainstorm together! What topic or challenge would you like to explore?", 'wp-ai-site-generator' ),
					),
				),
			),
		);

		/**
		 * Filter conversation templates.
		 *
		 * @since 1.0.0
		 * @param array $templates Templates array.
		 */
		$this->templates = apply_filters( 'wpaisg_conversation_templates', $this->templates );
	}
}