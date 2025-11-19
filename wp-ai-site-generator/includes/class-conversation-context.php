<?php
/**
 * Conversation Context Manager Class.
 *
 * Manages smart context summarization, information extraction, context compression,
 * user preference learning, topic tracking, and reference management.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Database\DB_Handler;
use WPAISiteGenerator\Providers\Provider_Manager;
use WP_Error;
use Exception;

/**
 * Conversation Context Manager Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Conversation_Context {

	/**
	 * Database handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler.
	 */
	private $db_handler;

	/**
	 * Provider manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Provider_Manager    $provider_manager    Provider manager.
	 */
	private $provider_manager;

	/**
	 * Context cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $context_cache    Context cache.
	 */
	private $context_cache = array();

	/**
	 * Maximum context tokens.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_context_tokens    Maximum tokens in context.
	 */
	private $max_context_tokens = 4000;

	/**
	 * User preferences.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $user_preferences    Learned user preferences.
	 */
	private $user_preferences = array();

	/**
	 * Topic tracking.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $topics    Current topics.
	 */
	private $topics = array();

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->db_handler = new DB_Handler();
		$this->provider_manager = new Provider_Manager();
		$this->load_user_preferences();
	}

	/**
	 * Initialize context for a conversation.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $metadata Initial metadata.
	 * @return   bool Success status.
	 */
	public function initialize_context( $conversation_id, $metadata = array() ) {
		$context = array(
			'conversation_id'     => $conversation_id,
			'created_at'          => current_time( 'mysql' ),
			'summary'             => '',
			'key_points'          => array(),
			'entities'            => array(),
			'preferences'         => array(),
			'current_topic'       => null,
			'topic_history'       => array(),
			'references'          => array(),
			'metadata'            => $metadata,
			'compression_level'   => 0,
			'token_count'         => 0,
		);

		$this->context_cache[ $conversation_id ] = $context;
		$this->save_context( $conversation_id, $context );

		return true;
	}

	/**
	 * Update context with new message.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $message New message data.
	 * @return   bool|WP_Error Success status or error.
	 */
	public function update_context( $conversation_id, $message ) {
		try {
			$context = $this->get_context( $conversation_id );
			if ( ! $context ) {
				$this->initialize_context( $conversation_id );
				$context = $this->get_context( $conversation_id );
			}

			// Extract entities from message.
			$entities = $this->extract_entities( $message['content'] );
			$context['entities'] = array_merge( $context['entities'], $entities );

			// Extract key points.
			$key_points = $this->extract_key_points( $message );
			if ( ! empty( $key_points ) ) {
				$context['key_points'][] = array(
					'message_id' => $message['id'] ?? null,
					'points'     => $key_points,
					'timestamp'  => $message['created_at'] ?? current_time( 'mysql' ),
				);
			}

			// Detect topic changes.
			$new_topic = $this->detect_topic( $message['content'] );
			if ( $new_topic && $new_topic !== $context['current_topic'] ) {
				if ( $context['current_topic'] ) {
					$context['topic_history'][] = array(
						'topic'    => $context['current_topic'],
						'ended_at' => current_time( 'mysql' ),
					);
				}
				$context['current_topic'] = $new_topic;
			}

			// Update token count.
			$context['token_count'] += $this->estimate_tokens( $message['content'] );

			// Check if compression needed.
			if ( $context['token_count'] > $this->max_context_tokens ) {
				$context = $this->compress_context( $context );
			}

			// Store updated context.
			$this->context_cache[ $conversation_id ] = $context;
			$this->save_context( $conversation_id, $context );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error(
				'context_update_failed',
				sprintf( __( 'Failed to update context: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Get context for a conversation.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   array|null Context data.
	 */
	public function get_context( $conversation_id ) {
		if ( isset( $this->context_cache[ $conversation_id ] ) ) {
			return $this->context_cache[ $conversation_id ];
		}

		$context = $this->load_context( $conversation_id );
		if ( $context ) {
			$this->context_cache[ $conversation_id ] = $context;
		}

		return $context;
	}

	/**
	 * Summarize conversation context.
	 *
	 * @since    1.0.0
	 * @param    int  $conversation_id Conversation ID.
	 * @param    bool $force Force regeneration.
	 * @return   array|WP_Error Summary or error.
	 */
	public function summarize_context( $conversation_id, $force = false ) {
		try {
			$context = $this->get_context( $conversation_id );
			if ( ! $context ) {
				return new WP_Error(
					'context_not_found',
					__( 'Context not found', 'wp-ai-site-generator' )
				);
			}

			// Check if summary exists and not forcing regeneration.
			if ( ! $force && ! empty( $context['summary'] ) ) {
				return array(
					'summary'     => $context['summary'],
					'key_points'  => $context['key_points'],
					'entities'    => $context['entities'],
					'topics'      => $this->get_topic_summary( $context ),
				);
			}

			// Generate summary using AI.
			$messages = $this->db_handler->get_messages( $conversation_id );
			$summary_prompt = $this->build_summary_prompt( $messages, $context );

			$provider = $this->provider_manager->get_active_provider();
			$response = $provider->generate_completion(
				array(
					array(
						'role'    => 'system',
						'content' => __( 'You are a conversation summarizer. Extract key information and provide concise summaries.', 'wp-ai-site-generator' ),
					),
					array(
						'role'    => 'user',
						'content' => $summary_prompt,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Parse and store summary.
			$summary_data = $this->parse_summary_response( $response );
			$context['summary'] = $summary_data['summary'];
			$context['key_points'] = array_merge( $context['key_points'], $summary_data['key_points'] ?? array() );

			$this->save_context( $conversation_id, $context );

			return $summary_data;

		} catch ( Exception $e ) {
			return new WP_Error(
				'summarization_failed',
				sprintf( __( 'Failed to summarize context: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Compress messages for API efficiency.
	 *
	 * @since    1.0.0
	 * @param    array $messages Messages to compress.
	 * @return   array Compressed messages.
	 */
	public function compress_messages( $messages ) {
		if ( count( $messages ) <= 3 ) {
			return $messages; // Too few to compress.
		}

		$compressed = array();
		$batch = array();
		$batch_tokens = 0;
		$max_batch_tokens = 500;

		foreach ( $messages as $message ) {
			$tokens = $this->estimate_tokens( $message['content'] );

			if ( $batch_tokens + $tokens > $max_batch_tokens && ! empty( $batch ) ) {
				// Compress batch.
				$compressed[] = $this->compress_batch( $batch );
				$batch = array();
				$batch_tokens = 0;
			}

			$batch[] = $message;
			$batch_tokens += $tokens;
		}

		// Handle remaining batch.
		if ( ! empty( $batch ) ) {
			if ( count( $batch ) === 1 ) {
				$compressed[] = $batch[0]; // Don't compress single message.
			} else {
				$compressed[] = $this->compress_batch( $batch );
			}
		}

		return $compressed;
	}

	/**
	 * Learn user preferences from conversation.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $message User message.
	 * @return   void
	 */
	public function learn_preferences( $conversation_id, $message ) {
		if ( 'user' !== $message['role'] ) {
			return;
		}

		// Extract preferences from message.
		$preferences = $this->extract_preferences( $message['content'] );

		if ( ! empty( $preferences ) ) {
			// Update global user preferences.
			$user_id = get_current_user_id();
			if ( $user_id ) {
				$this->update_user_preferences( $user_id, $preferences );
			}

			// Update conversation context.
			$context = $this->get_context( $conversation_id );
			if ( $context ) {
				$context['preferences'] = array_merge(
					$context['preferences'],
					$preferences
				);
				$this->save_context( $conversation_id, $context );
			}
		}
	}

	/**
	 * Switch conversation topic.
	 *
	 * @since    1.0.0
	 * @param    int    $conversation_id Conversation ID.
	 * @param    string $new_topic New topic.
	 * @return   bool|WP_Error Success or error.
	 */
	public function switch_topic( $conversation_id, $new_topic ) {
		try {
			$context = $this->get_context( $conversation_id );
			if ( ! $context ) {
				return new WP_Error(
					'context_not_found',
					__( 'Context not found', 'wp-ai-site-generator' )
				);
			}

			// Archive current topic.
			if ( $context['current_topic'] ) {
				$context['topic_history'][] = array(
					'topic'      => $context['current_topic'],
					'ended_at'   => current_time( 'mysql' ),
					'summary'    => $this->summarize_topic( $conversation_id, $context['current_topic'] ),
				);
			}

			// Set new topic.
			$context['current_topic'] = $new_topic;
			$context['topic_started_at'] = current_time( 'mysql' );

			$this->save_context( $conversation_id, $context );

			return true;

		} catch ( Exception $e ) {
			return new WP_Error(
				'topic_switch_failed',
				sprintf( __( 'Failed to switch topic: %s', 'wp-ai-site-generator' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Add reference to previous response.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    int   $message_id Referenced message ID.
	 * @param    array $reference_data Reference details.
	 * @return   bool Success status.
	 */
	public function add_reference( $conversation_id, $message_id, $reference_data ) {
		$context = $this->get_context( $conversation_id );
		if ( ! $context ) {
			return false;
		}

		$context['references'][] = array(
			'message_id'   => $message_id,
			'reference_id' => $reference_data['reference_id'] ?? null,
			'type'         => $reference_data['type'] ?? 'general',
			'description'  => $reference_data['description'] ?? '',
			'created_at'   => current_time( 'mysql' ),
		);

		$this->save_context( $conversation_id, $context );

		return true;
	}

	/**
	 * Get smart suggestions based on context.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   array Suggestions.
	 */
	public function get_suggestions( $conversation_id ) {
		$context = $this->get_context( $conversation_id );
		if ( ! $context ) {
			return array();
		}

		$suggestions = array();

		// Topic-based suggestions.
		if ( $context['current_topic'] ) {
			$suggestions = array_merge(
				$suggestions,
				$this->get_topic_suggestions( $context['current_topic'] )
			);
		}

		// Preference-based suggestions.
		if ( ! empty( $context['preferences'] ) ) {
			$suggestions = array_merge(
				$suggestions,
				$this->get_preference_suggestions( $context['preferences'] )
			);
		}

		// Context-based suggestions.
		if ( ! empty( $context['key_points'] ) ) {
			$suggestions = array_merge(
				$suggestions,
				$this->get_context_suggestions( $context['key_points'] )
			);
		}

		// Remove duplicates and limit.
		$suggestions = array_unique( $suggestions );
		$suggestions = array_slice( $suggestions, 0, 5 );

		return $suggestions;
	}

	/**
	 * Get context summary.
	 *
	 * @since    1.0.0
	 * @param    int $conversation_id Conversation ID.
	 * @return   array Summary data.
	 */
	public function get_context_summary( $conversation_id ) {
		$context = $this->get_context( $conversation_id );
		if ( ! $context ) {
			return array();
		}

		return array(
			'summary'            => $context['summary'],
			'current_topic'      => $context['current_topic'],
			'entities'           => array_slice( $context['entities'], -10 ), // Last 10 entities.
			'key_points'         => array_slice( $context['key_points'], -5 ), // Last 5 key points.
			'preferences'        => $context['preferences'],
			'compression_level'  => $context['compression_level'],
			'token_count'        => $context['token_count'],
			'topics_covered'     => count( $context['topic_history'] ) + ( $context['current_topic'] ? 1 : 0 ),
		);
	}

	/**
	 * Branch context for new conversation.
	 *
	 * @since    1.0.0
	 * @param    int $parent_id Parent conversation ID.
	 * @param    int $branch_id Branch conversation ID.
	 * @param    int $branch_point Branch point message ID.
	 * @return   bool Success status.
	 */
	public function branch_context( $parent_id, $branch_id, $branch_point ) {
		$parent_context = $this->get_context( $parent_id );
		if ( ! $parent_context ) {
			return false;
		}

		// Copy relevant context to branch.
		$branch_context = array(
			'conversation_id'   => $branch_id,
			'created_at'        => current_time( 'mysql' ),
			'summary'           => $parent_context['summary'],
			'key_points'        => $parent_context['key_points'],
			'entities'          => $parent_context['entities'],
			'preferences'       => $parent_context['preferences'],
			'current_topic'     => $parent_context['current_topic'],
			'topic_history'     => $parent_context['topic_history'],
			'references'        => array(),
			'metadata'          => array_merge(
				$parent_context['metadata'],
				array(
					'branched_from' => $parent_id,
					'branch_point'  => $branch_point,
				)
			),
			'compression_level' => 0,
			'token_count'       => 0,
		);

		$this->context_cache[ $branch_id ] = $branch_context;
		$this->save_context( $branch_id, $branch_context );

		return true;
	}

	/**
	 * Store compressed context.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $compressed_data Compressed data.
	 * @return   bool Success status.
	 */
	public function store_compressed_context( $conversation_id, $compressed_data ) {
		$context = $this->get_context( $conversation_id );
		if ( ! $context ) {
			return false;
		}

		$context['compressed_history'][] = array(
			'data'          => $compressed_data,
			'compressed_at' => current_time( 'mysql' ),
			'token_count'   => $this->estimate_tokens( wp_json_encode( $compressed_data ) ),
		);

		$context['compression_level']++;
		$this->save_context( $conversation_id, $context );

		return true;
	}

	/**
	 * Restore context from saved data.
	 *
	 * @since    1.0.0
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $saved_context Saved context data.
	 * @return   bool Success status.
	 */
	public function restore_context( $conversation_id, $saved_context ) {
		$this->context_cache[ $conversation_id ] = $saved_context;
		$this->save_context( $conversation_id, $saved_context );
		return true;
	}

	/**
	 * Extract entities from text.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $text Text to analyze.
	 * @return   array Entities.
	 */
	private function extract_entities( $text ) {
		$entities = array();

		// Extract URLs.
		preg_match_all( '/https?:\/\/[^\s]+/', $text, $urls );
		if ( ! empty( $urls[0] ) ) {
			foreach ( $urls[0] as $url ) {
				$entities[] = array(
					'type'  => 'url',
					'value' => $url,
				);
			}
		}

		// Extract email addresses.
		preg_match_all( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $emails );
		if ( ! empty( $emails[0] ) ) {
			foreach ( $emails[0] as $email ) {
				$entities[] = array(
					'type'  => 'email',
					'value' => $email,
				);
			}
		}

		// Extract code blocks.
		preg_match_all( '/```[\s\S]*?```/', $text, $code_blocks );
		if ( ! empty( $code_blocks[0] ) ) {
			foreach ( $code_blocks[0] as $code ) {
				$entities[] = array(
					'type'  => 'code',
					'value' => substr( $code, 3, -3 ),
				);
			}
		}

		// Extract numbers and statistics.
		preg_match_all( '/\b\d+(?:\.\d+)?%?\b/', $text, $numbers );
		if ( ! empty( $numbers[0] ) ) {
			foreach ( $numbers[0] as $number ) {
				if ( strpos( $number, '%' ) !== false ) {
					$entities[] = array(
						'type'  => 'percentage',
						'value' => $number,
					);
				}
			}
		}

		return $entities;
	}

	/**
	 * Extract key points from message.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $message Message data.
	 * @return   array Key points.
	 */
	private function extract_key_points( $message ) {
		$key_points = array();
		$content = $message['content'];

		// Look for lists.
		preg_match_all( '/^[\*\-\d]+\.?\s+(.+)$/m', $content, $list_items );
		if ( ! empty( $list_items[1] ) ) {
			foreach ( $list_items[1] as $item ) {
				$key_points[] = trim( $item );
			}
		}

		// Look for questions.
		preg_match_all( '/[^.!?]*\?/', $content, $questions );
		if ( ! empty( $questions[0] ) ) {
			foreach ( $questions[0] as $question ) {
				$key_points[] = array(
					'type'    => 'question',
					'content' => trim( $question ),
				);
			}
		}

		// Look for decisions or conclusions.
		$decision_keywords = array( 'decided', 'choose', 'select', 'prefer', 'will use', 'going with' );
		foreach ( $decision_keywords as $keyword ) {
			if ( stripos( $content, $keyword ) !== false ) {
				preg_match( '/[^.]*' . preg_quote( $keyword, '/' ) . '[^.]*\./', $content, $decision );
				if ( ! empty( $decision[0] ) ) {
					$key_points[] = array(
						'type'    => 'decision',
						'content' => trim( $decision[0] ),
					);
				}
			}
		}

		return $key_points;
	}

	/**
	 * Detect topic from text.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $text Text to analyze.
	 * @return   string|null Topic.
	 */
	private function detect_topic( $text ) {
		$topics = array(
			'site_creation'   => array( 'website', 'site', 'homepage', 'landing page', 'web page' ),
			'content'         => array( 'blog', 'post', 'article', 'content', 'writing' ),
			'design'          => array( 'design', 'layout', 'theme', 'style', 'appearance' ),
			'seo'             => array( 'seo', 'search', 'ranking', 'keywords', 'optimization' ),
			'ecommerce'       => array( 'product', 'shop', 'store', 'cart', 'payment' ),
			'technical'       => array( 'error', 'bug', 'issue', 'problem', 'fix' ),
			'media'           => array( 'image', 'video', 'gallery', 'media', 'photo' ),
			'user_management' => array( 'user', 'member', 'registration', 'login', 'account' ),
		);

		$text_lower = strtolower( $text );
		$detected_topics = array();

		foreach ( $topics as $topic => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( strpos( $text_lower, $keyword ) !== false ) {
					if ( ! isset( $detected_topics[ $topic ] ) ) {
						$detected_topics[ $topic ] = 0;
					}
					$detected_topics[ $topic ]++;
				}
			}
		}

		if ( ! empty( $detected_topics ) ) {
			arsort( $detected_topics );
			return key( $detected_topics );
		}

		return null;
	}

	/**
	 * Extract user preferences from text.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $text Text to analyze.
	 * @return   array Preferences.
	 */
	private function extract_preferences( $text ) {
		$preferences = array();

		// Look for preference indicators.
		$preference_patterns = array(
			'/i (?:prefer|like|want|need|require) (.+?)(?:\.|,|$)/i',
			'/(?:please|always|never) (.+?)(?:\.|,|$)/i',
			'/(?:make sure|ensure|remember) (?:to )(.+?)(?:\.|,|$)/i',
		);

		foreach ( $preference_patterns as $pattern ) {
			preg_match_all( $pattern, $text, $matches );
			if ( ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $preference ) {
					$preferences[] = trim( $preference );
				}
			}
		}

		// Look for style preferences.
		$style_keywords = array(
			'formal'       => array( 'formal', 'professional', 'business' ),
			'casual'       => array( 'casual', 'informal', 'friendly' ),
			'technical'    => array( 'technical', 'detailed', 'specific' ),
			'simple'       => array( 'simple', 'easy', 'basic' ),
			'creative'     => array( 'creative', 'unique', 'original' ),
			'minimalist'   => array( 'minimal', 'clean', 'simple' ),
		);

		$text_lower = strtolower( $text );
		foreach ( $style_keywords as $style => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( strpos( $text_lower, $keyword ) !== false ) {
					$preferences['style'] = $style;
					break 2;
				}
			}
		}

		return $preferences;
	}

	/**
	 * Compress context data.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $context Context to compress.
	 * @return   array Compressed context.
	 */
	private function compress_context( $context ) {
		// Keep only recent key points.
		if ( count( $context['key_points'] ) > 10 ) {
			$context['key_points'] = array_slice( $context['key_points'], -10 );
		}

		// Deduplicate entities.
		$unique_entities = array();
		foreach ( $context['entities'] as $entity ) {
			$key = $entity['type'] . ':' . $entity['value'];
			$unique_entities[ $key ] = $entity;
		}
		$context['entities'] = array_values( $unique_entities );

		// Limit entity count.
		if ( count( $context['entities'] ) > 50 ) {
			$context['entities'] = array_slice( $context['entities'], -50 );
		}

		// Compress old topics.
		if ( count( $context['topic_history'] ) > 5 ) {
			$old_topics = array_slice( $context['topic_history'], 0, -5 );
			$context['compressed_topics'][] = array(
				'topics'        => array_column( $old_topics, 'topic' ),
				'compressed_at' => current_time( 'mysql' ),
			);
			$context['topic_history'] = array_slice( $context['topic_history'], -5 );
		}

		$context['compression_level']++;
		$context['token_count'] = $this->estimate_tokens( wp_json_encode( $context ) );

		return $context;
	}

	/**
	 * Compress batch of messages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $messages Messages to compress.
	 * @return   array Compressed message.
	 */
	private function compress_batch( $messages ) {
		$roles = array();
		$summary = '';

		foreach ( $messages as $message ) {
			if ( ! isset( $roles[ $message['role'] ] ) ) {
				$roles[ $message['role'] ] = 0;
			}
			$roles[ $message['role'] ]++;

			// Extract main points.
			$sentences = preg_split( '/[.!?]+/', $message['content'], -1, PREG_SPLIT_NO_EMPTY );
			if ( count( $sentences ) > 2 ) {
				$summary .= $sentences[0] . '. ';
			} else {
				$summary .= $message['content'] . ' ';
			}
		}

		return array(
			'role'       => 'system',
			'content'    => sprintf(
				__( '[Compressed %d messages: %s] %s', 'wp-ai-site-generator' ),
				count( $messages ),
				implode( ', ', array_map( function( $role, $count ) {
					return $count . ' ' . $role;
				}, array_keys( $roles ), $roles ) ),
				trim( $summary )
			),
			'compressed' => true,
			'metadata'   => array(
				'message_count' => count( $messages ),
				'roles'         => $roles,
			),
		);
	}

	/**
	 * Build summary prompt.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $messages Messages to summarize.
	 * @param    array $context Current context.
	 * @return   string Prompt.
	 */
	private function build_summary_prompt( $messages, $context ) {
		$conversation_text = '';
		foreach ( $messages as $message ) {
			$conversation_text .= sprintf(
				"%s: %s\n\n",
				ucfirst( $message['role'] ),
				$message['content']
			);
		}

		$prompt = __( "Please summarize the following conversation:\n\n", 'wp-ai-site-generator' );
		$prompt .= $conversation_text;
		$prompt .= __( "\n\nProvide:\n1. A concise summary (2-3 sentences)\n2. Key points discussed\n3. Any decisions made\n4. Main topics covered", 'wp-ai-site-generator' );

		return $prompt;
	}

	/**
	 * Parse summary response from AI.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $response AI response.
	 * @return   array Parsed summary data.
	 */
	private function parse_summary_response( $response ) {
		$data = array(
			'summary'    => '',
			'key_points' => array(),
			'decisions'  => array(),
			'topics'     => array(),
		);

		// Simple parsing - can be enhanced with better NLP.
		$sections = preg_split( '/\n(?=\d+\.|Summary:|Key [Pp]oints:|Decisions:|Topics:)/i', $response );

		foreach ( $sections as $section ) {
			$section = trim( $section );

			if ( stripos( $section, 'summary' ) === 0 || ! preg_match( '/^\d+\./', $section ) ) {
				$data['summary'] = preg_replace( '/^summary:?\s*/i', '', $section );
			} elseif ( stripos( $section, 'key points' ) !== false ) {
				preg_match_all( '/[-*]\s*(.+)/', $section, $matches );
				if ( ! empty( $matches[1] ) ) {
					$data['key_points'] = array_map( 'trim', $matches[1] );
				}
			} elseif ( stripos( $section, 'decisions' ) !== false ) {
				preg_match_all( '/[-*]\s*(.+)/', $section, $matches );
				if ( ! empty( $matches[1] ) ) {
					$data['decisions'] = array_map( 'trim', $matches[1] );
				}
			} elseif ( stripos( $section, 'topics' ) !== false ) {
				preg_match_all( '/[-*]\s*(.+)/', $section, $matches );
				if ( ! empty( $matches[1] ) ) {
					$data['topics'] = array_map( 'trim', $matches[1] );
				}
			}
		}

		// Fallback to full response as summary if parsing failed.
		if ( empty( $data['summary'] ) ) {
			$data['summary'] = trim( $response );
		}

		return $data;
	}

	/**
	 * Get topic summary.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $context Context data.
	 * @return   array Topic summary.
	 */
	private function get_topic_summary( $context ) {
		$topics = array();

		if ( $context['current_topic'] ) {
			$topics[] = array(
				'topic'  => $context['current_topic'],
				'status' => 'current',
			);
		}

		foreach ( $context['topic_history'] as $historical_topic ) {
			$topics[] = array(
				'topic'  => $historical_topic['topic'],
				'status' => 'completed',
			);
		}

		return $topics;
	}

	/**
	 * Summarize a specific topic.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $conversation_id Conversation ID.
	 * @param    string $topic Topic to summarize.
	 * @return   string Topic summary.
	 */
	private function summarize_topic( $conversation_id, $topic ) {
		// This would analyze messages related to the topic.
		// For now, return a simple summary.
		return sprintf(
			__( 'Discussion about %s completed', 'wp-ai-site-generator' ),
			$topic
		);
	}

	/**
	 * Get topic-based suggestions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $topic Current topic.
	 * @return   array Suggestions.
	 */
	private function get_topic_suggestions( $topic ) {
		$suggestions = array();

		$topic_suggestions = array(
			'site_creation' => array(
				__( 'What pages should the site include?', 'wp-ai-site-generator' ),
				__( 'Do you have brand colors or a logo?', 'wp-ai-site-generator' ),
				__( 'What functionality do you need?', 'wp-ai-site-generator' ),
			),
			'content' => array(
				__( 'What keywords should we target?', 'wp-ai-site-generator' ),
				__( 'How long should the content be?', 'wp-ai-site-generator' ),
				__( 'What tone should we use?', 'wp-ai-site-generator' ),
			),
			'seo' => array(
				__( 'Analyze current SEO performance', 'wp-ai-site-generator' ),
				__( 'Generate meta descriptions', 'wp-ai-site-generator' ),
				__( 'Find related keywords', 'wp-ai-site-generator' ),
			),
			'technical' => array(
				__( 'Check error logs', 'wp-ai-site-generator' ),
				__( 'Test in different browsers', 'wp-ai-site-generator' ),
				__( 'Review recent changes', 'wp-ai-site-generator' ),
			),
		);

		return $topic_suggestions[ $topic ] ?? array();
	}

	/**
	 * Get preference-based suggestions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $preferences User preferences.
	 * @return   array Suggestions.
	 */
	private function get_preference_suggestions( $preferences ) {
		$suggestions = array();

		if ( isset( $preferences['style'] ) ) {
			switch ( $preferences['style'] ) {
				case 'formal':
					$suggestions[] = __( 'Review for professional tone', 'wp-ai-site-generator' );
					break;
				case 'creative':
					$suggestions[] = __( 'Add unique design elements', 'wp-ai-site-generator' );
					break;
				case 'minimalist':
					$suggestions[] = __( 'Simplify the layout', 'wp-ai-site-generator' );
					break;
			}
		}

		return $suggestions;
	}

	/**
	 * Get context-based suggestions.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array $key_points Key points from context.
	 * @return   array Suggestions.
	 */
	private function get_context_suggestions( $key_points ) {
		$suggestions = array();

		// Look for unanswered questions.
		foreach ( $key_points as $point_group ) {
			if ( is_array( $point_group['points'] ) ) {
				foreach ( $point_group['points'] as $point ) {
					if ( is_array( $point ) && isset( $point['type'] ) && 'question' === $point['type'] ) {
						$suggestions[] = sprintf(
							__( 'Answer: %s', 'wp-ai-site-generator' ),
							$point['content']
						);
					}
				}
			}
		}

		return array_slice( $suggestions, 0, 3 );
	}

	/**
	 * Estimate token count.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    string $text Text to estimate.
	 * @return   int Estimated tokens.
	 */
	private function estimate_tokens( $text ) {
		// Rough estimation: ~4 characters per token.
		return ceil( strlen( $text ) / 4 );
	}

	/**
	 * Save context to database.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $conversation_id Conversation ID.
	 * @param    array $context Context data.
	 * @return   bool Success status.
	 */
	private function save_context( $conversation_id, $context ) {
		// Store in database or user meta.
		update_post_meta( $conversation_id, '_wpaisg_context', $context );
		return true;
	}

	/**
	 * Load context from database.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int $conversation_id Conversation ID.
	 * @return   array|null Context data.
	 */
	private function load_context( $conversation_id ) {
		$context = get_post_meta( $conversation_id, '_wpaisg_context', true );
		return $context ?: null;
	}

	/**
	 * Load user preferences.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   void
	 */
	private function load_user_preferences() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$this->user_preferences = get_user_meta( $user_id, 'wpaisg_preferences', true );
			if ( ! is_array( $this->user_preferences ) ) {
				$this->user_preferences = array();
			}
		}
	}

	/**
	 * Update user preferences.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int   $user_id User ID.
	 * @param    array $preferences New preferences.
	 * @return   void
	 */
	private function update_user_preferences( $user_id, $preferences ) {
		$existing = get_user_meta( $user_id, 'wpaisg_preferences', true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$updated = array_merge( $existing, $preferences );
		update_user_meta( $user_id, 'wpaisg_preferences', $updated );
		$this->user_preferences = $updated;
	}
}