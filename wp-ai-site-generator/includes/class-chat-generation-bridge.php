<?php
/**
 * Chat to Generation Bridge Class.
 *
 * Converts chat conversations into generation requests and handles chat-based generation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Database\DB_Handler;
use WPAISiteGenerator\Providers\Provider_Manager;
use WP_Error;

/**
 * Chat to Generation Bridge Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Chat_Generation_Bridge {

	/**
	 * Generation orchestrator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Generation_Orchestrator    $orchestrator    Generation orchestrator.
	 */
	private $orchestrator;

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
	 * Intent patterns for generation detection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $intent_patterns    Intent detection patterns.
	 */
	private $intent_patterns = array(
		'create_site' => array(
			'/create.*(?:website|site|homepage)/i',
			'/build.*(?:website|site|pages)/i',
			'/generate.*(?:entire|full|complete).*site/i',
			'/make.*new.*(?:website|site)/i',
		),
		'create_page' => array(
			'/create.*page/i',
			'/add.*page/i',
			'/generate.*(?:landing|about|contact|service).*page/i',
			'/build.*page.*(?:about|for|with)/i',
		),
		'regenerate_section' => array(
			'/(?:regenerate|redo|improve|fix).*section/i',
			'/make.*section.*better/i',
			'/change.*(?:header|footer|hero|content)/i',
			'/update.*(?:this|that).*part/i',
		),
		'modify_content' => array(
			'/(?:change|modify|update|edit).*content/i',
			'/make.*(?:shorter|longer|simpler|better)/i',
			'/improve.*(?:readability|seo|quality)/i',
			'/add.*(?:images|videos|media)/i',
		),
	);

	/**
	 * Generation type extractors.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $type_extractors    Content type extraction patterns.
	 */
	private $type_extractors = array(
		'page_types' => array(
			'home'     => '/(?:home|main|index|front).*page/i',
			'about'    => '/about.*(?:us|company|page)/i',
			'contact'  => '/contact.*(?:us|form|page)/i',
			'services' => '/service.*page/i',
			'products' => '/product.*page/i',
			'blog'     => '/blog.*page/i',
			'landing'  => '/landing.*page/i',
			'pricing'  => '/pricing.*page/i',
			'faq'      => '/(?:faq|questions).*page/i',
			'portfolio'=> '/portfolio.*page/i',
		),
		'industries' => array(
			'technology' => '/(?:tech|software|app|saas|startup)/i',
			'healthcare' => '/(?:health|medical|clinic|doctor|hospital)/i',
			'education'  => '/(?:education|school|course|learning|academy)/i',
			'ecommerce'  => '/(?:shop|store|ecommerce|product|selling)/i',
			'restaurant' => '/(?:restaurant|food|dining|cafe|bistro)/i',
			'realestate' => '/(?:real estate|property|housing|realtor)/i',
			'fitness'    => '/(?:fitness|gym|workout|training|wellness)/i',
			'consulting' => '/(?:consulting|advisory|professional services)/i',
			'nonprofit'  => '/(?:nonprofit|charity|foundation|ngo)/i',
			'agency'     => '/(?:agency|marketing|creative|digital)/i',
		),
		'styles' => array(
			'modern'      => '/(?:modern|contemporary|sleek|minimal)/i',
			'professional'=> '/(?:professional|corporate|business|formal)/i',
			'creative'    => '/(?:creative|artistic|colorful|vibrant)/i',
			'simple'      => '/(?:simple|clean|basic|straightforward)/i',
			'elegant'     => '/(?:elegant|sophisticated|luxury|premium)/i',
			'playful'     => '/(?:playful|fun|friendly|casual)/i',
		),
	);

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->orchestrator = new Generation_Orchestrator();
		$this->db_handler = new DB_Handler();
		$this->provider_manager = new Provider_Manager();
	}

	/**
	 * Process chat message for generation intent.
	 *
	 * @since    1.0.0
	 * @param    array    $message_data    Chat message data.
	 * @return   array                     Processing result.
	 */
	public function process_message( $message_data ) {
		try {
			$message = $message_data['message'] ?? '';
			$session_id = $message_data['session_id'] ?? null;
			$context = $message_data['context'] ?? array();

			// Detect generation intent
			$intent = $this->detect_intent( $message );

			if ( ! $intent ) {
				return array(
					'requires_generation' => false,
					'response' => null,
				);
			}

			// Extract generation parameters
			$params = $this->extract_parameters( $message, $intent, $context );

			// Get conversation context if session exists
			if ( $session_id ) {
				$conversation_context = $this->build_conversation_context( $session_id );
				$params['conversation_context'] = $conversation_context;
			}

			// Trigger appropriate generation
			$result = $this->trigger_generation( $intent, $params );

			if ( is_wp_error( $result ) ) {
				return array(
					'requires_generation' => true,
					'error'               => $result->get_error_message(),
				);
			}

			// Update chat with result
			if ( $session_id ) {
				$this->update_chat_with_result( $session_id, $result );
			}

			return array(
				'requires_generation' => true,
				'generation_triggered'=> true,
				'intent'              => $intent,
				'result'              => $result,
				'response'            => $this->format_generation_response( $intent, $result ),
			);

		} catch ( \Exception $e ) {
			return array(
				'requires_generation' => false,
				'error'               => $e->getMessage(),
			);
		}
	}

	/**
	 * Detect generation intent from message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    User message.
	 * @return   string|null           Intent type or null.
	 */
	private function detect_intent( $message ) {
		foreach ( $this->intent_patterns as $intent => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $message ) ) {
					return $intent;
				}
			}
		}

		// Use AI for more complex intent detection
		$ai_intent = $this->detect_intent_with_ai( $message );
		if ( $ai_intent && isset( $this->intent_patterns[ $ai_intent ] ) ) {
			return $ai_intent;
		}

		return null;
	}

	/**
	 * Detect intent using AI.
	 *
	 * @since    1.0.0
	 * @param    string    $message    User message.
	 * @return   string|null           Detected intent or null.
	 */
	private function detect_intent_with_ai( $message ) {
		$provider = $this->provider_manager->get_active_provider();
		if ( ! $provider ) {
			return null;
		}

		$prompt = sprintf(
			"Analyze this message and determine if the user wants to generate website content.
			If yes, classify the intent as one of: create_site, create_page, regenerate_section, modify_content, or none.
			Message: %s

			Respond with only the intent classification.",
			$message
		);

		$response = $provider->generate( $prompt, array(
			'temperature' => 0.3,
			'max_tokens'  => 50,
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$intent = strtolower( trim( $response ) );
		return in_array( $intent, array( 'create_site', 'create_page', 'regenerate_section', 'modify_content' ) )
			? $intent
			: null;
	}

	/**
	 * Extract generation parameters from message.
	 *
	 * @since    1.0.0
	 * @param    string    $message    User message.
	 * @param    string    $intent     Detected intent.
	 * @param    array     $context    Additional context.
	 * @return   array                 Extracted parameters.
	 */
	private function extract_parameters( $message, $intent, $context = array() ) {
		$params = array(
			'raw_message' => $message,
			'intent'      => $intent,
		);

		// Extract page types
		foreach ( $this->type_extractors['page_types'] as $type => $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				$params['page_type'] = $type;
				break;
			}
		}

		// Extract industry
		foreach ( $this->type_extractors['industries'] as $industry => $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				$params['industry'] = $industry;
				break;
			}
		}

		// Extract style
		foreach ( $this->type_extractors['styles'] as $style => $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				$params['style'] = $style;
				break;
			}
		}

		// Extract specific requirements using AI
		$ai_params = $this->extract_parameters_with_ai( $message, $intent );
		if ( $ai_params ) {
			$params = array_merge( $params, $ai_params );
		}

		// Add context
		$params['context'] = $context;

		return $params;
	}

	/**
	 * Extract parameters using AI.
	 *
	 * @since    1.0.0
	 * @param    string    $message    User message.
	 * @param    string    $intent     Intent type.
	 * @return   array|null            Extracted parameters or null.
	 */
	private function extract_parameters_with_ai( $message, $intent ) {
		$provider = $this->provider_manager->get_active_provider();
		if ( ! $provider ) {
			return null;
		}

		$prompt = sprintf(
			"Extract specific requirements from this message for %s.
			Message: %s

			Extract and return as JSON:
			- business_name: Company or website name
			- title: Page or site title
			- description: Brief description
			- colors: Preferred colors
			- features: Specific features requested
			- content_tone: Tone of content (professional, casual, etc.)
			- target_audience: Target audience
			- special_requirements: Any special requirements

			Return only valid JSON.",
			$intent,
			$message
		);

		$response = $provider->generate( $prompt, array(
			'temperature' => 0.3,
			'max_tokens'  => 500,
		) );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		// Parse JSON response
		$json = json_decode( $response, true );
		return is_array( $json ) ? $json : null;
	}

	/**
	 * Build conversation context from chat history.
	 *
	 * @since    1.0.0
	 * @param    int    $session_id    Chat session ID.
	 * @return   array                 Conversation context.
	 */
	private function build_conversation_context( $session_id ) {
		// Get recent messages
		$messages = $this->db_handler->get_session_messages( $session_id, array(
			'limit' => 20,
			'order' => 'DESC',
		) );

		$context = array(
			'messages'     => array(),
			'preferences'  => array(),
			'mentioned'    => array(),
		);

		foreach ( array_reverse( $messages ) as $message ) {
			$context['messages'][] = array(
				'role'    => $message->role,
				'content' => $message->content,
			);

			// Extract mentioned preferences
			$this->extract_preferences( $message->content, $context['preferences'] );

			// Track mentioned items
			$this->extract_mentions( $message->content, $context['mentioned'] );
		}

		return $context;
	}

	/**
	 * Extract preferences from message.
	 *
	 * @since    1.0.0
	 * @param    string    $content        Message content.
	 * @param    array     &$preferences   Preferences array to update.
	 * @return   void
	 */
	private function extract_preferences( $content, &$preferences ) {
		// Extract color preferences
		if ( preg_match_all( '/\b(blue|green|red|orange|purple|black|white|gray)\b/i', $content, $matches ) ) {
			if ( ! isset( $preferences['colors'] ) ) {
				$preferences['colors'] = array();
			}
			$preferences['colors'] = array_unique( array_merge( $preferences['colors'], $matches[1] ) );
		}

		// Extract style preferences
		if ( preg_match( '/\b(modern|classic|minimal|bold|elegant)\b/i', $content, $match ) ) {
			$preferences['style'] = strtolower( $match[1] );
		}

		// Extract business type
		if ( preg_match( '/\b(?:we are|i am|our company is|business is)\s+(?:a|an)?\s*(\w+(?:\s+\w+)?)/i', $content, $match ) ) {
			$preferences['business_type'] = $match[1];
		}
	}

	/**
	 * Extract mentions from message.
	 *
	 * @since    1.0.0
	 * @param    string    $content      Message content.
	 * @param    array     &$mentioned   Mentioned items array.
	 * @return   void
	 */
	private function extract_mentions( $content, &$mentioned ) {
		// Extract page mentions
		if ( preg_match_all( '/\b(?:page|section|component|feature)\s+(?:called|named|titled)\s+"([^"]+)"/i', $content, $matches ) ) {
			if ( ! isset( $mentioned['pages'] ) ) {
				$mentioned['pages'] = array();
			}
			$mentioned['pages'] = array_unique( array_merge( $mentioned['pages'], $matches[1] ) );
		}

		// Extract feature mentions
		if ( preg_match_all( '/\b(?:need|want|require|include)\s+(?:a|an)?\s*(\w+(?:\s+\w+)?)\b/i', $content, $matches ) ) {
			if ( ! isset( $mentioned['features'] ) ) {
				$mentioned['features'] = array();
			}
			$mentioned['features'] = array_unique( array_merge( $mentioned['features'], $matches[1] ) );
		}
	}

	/**
	 * Trigger generation based on intent.
	 *
	 * @since    1.0.0
	 * @param    string    $intent    Generation intent.
	 * @param    array     $params    Generation parameters.
	 * @return   array|WP_Error        Generation result or error.
	 */
	private function trigger_generation( $intent, $params ) {
		switch ( $intent ) {
			case 'create_site':
				return $this->trigger_site_generation( $params );

			case 'create_page':
				return $this->trigger_page_generation( $params );

			case 'regenerate_section':
				return $this->trigger_section_regeneration( $params );

			case 'modify_content':
				return $this->trigger_content_modification( $params );

			default:
				return new WP_Error(
					'unknown_intent',
					sprintf( __( 'Unknown generation intent: %s', 'wp-ai-site-generator' ), $intent )
				);
		}
	}

	/**
	 * Trigger site generation.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   array|WP_Error       Generation result or error.
	 */
	private function trigger_site_generation( $params ) {
		$config = array(
			'type'          => 'complete_site',
			'business_name' => $params['business_name'] ?? __( 'My Business', 'wp-ai-site-generator' ),
			'industry'      => $params['industry'] ?? 'general',
			'style'         => $params['style'] ?? 'modern',
			'pages'         => $this->determine_site_pages( $params ),
			'theme_config'  => array(
				'colors' => $params['colors'] ?? array(),
				'style'  => $params['style'] ?? 'modern',
			),
			'content_preferences' => array(
				'tone'     => $params['content_tone'] ?? 'professional',
				'audience' => $params['target_audience'] ?? 'general',
			),
		);

		// Add conversation context if available
		if ( isset( $params['conversation_context'] ) ) {
			$config['context'] = $params['conversation_context'];
		}

		return $this->orchestrator->generate_site( $config );
	}

	/**
	 * Trigger page generation.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   array|WP_Error       Generation result or error.
	 */
	private function trigger_page_generation( $params ) {
		$config = array(
			'type'   => $params['page_type'] ?? 'page',
			'title'  => $params['title'] ?? $this->generate_page_title( $params ),
			'prompt' => $params['description'] ?? '',
			'style'  => $params['style'] ?? 'modern',
			'theme_config' => array(
				'colors' => $params['colors'] ?? array(),
				'style'  => $params['style'] ?? 'modern',
			),
		);

		// Add conversation context if available
		if ( isset( $params['conversation_context'] ) ) {
			$config['context'] = $params['conversation_context'];
		}

		return $this->orchestrator->generate_single_page( $config );
	}

	/**
	 * Trigger section regeneration.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   array|WP_Error       Generation result or error.
	 */
	private function trigger_section_regeneration( $params ) {
		// Need to identify the page and section
		$post_id = $this->identify_target_page( $params );
		$section_id = $this->identify_target_section( $params, $post_id );

		if ( ! $post_id || ! $section_id ) {
			return new WP_Error(
				'target_not_found',
				__( 'Could not identify the page or section to regenerate', 'wp-ai-site-generator' )
			);
		}

		$config = array(
			'post_id'    => $post_id,
			'section_id' => $section_id,
			'prompt'     => $params['raw_message'],
			'improvements' => $params['special_requirements'] ?? array(),
		);

		return $this->orchestrator->regenerate_section( $config );
	}

	/**
	 * Trigger content modification.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   array|WP_Error       Generation result or error.
	 */
	private function trigger_content_modification( $params ) {
		// This would handle various content modifications
		// For now, treat as section regeneration
		return $this->trigger_section_regeneration( $params );
	}

	/**
	 * Determine site pages based on parameters.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   array               Pages configuration.
	 */
	private function determine_site_pages( $params ) {
		$base_pages = array(
			array( 'type' => 'home', 'title' => __( 'Home', 'wp-ai-site-generator' ) ),
			array( 'type' => 'about', 'title' => __( 'About Us', 'wp-ai-site-generator' ) ),
			array( 'type' => 'contact', 'title' => __( 'Contact', 'wp-ai-site-generator' ) ),
		);

		// Add industry-specific pages
		if ( isset( $params['industry'] ) ) {
			switch ( $params['industry'] ) {
				case 'ecommerce':
					$base_pages[] = array( 'type' => 'products', 'title' => __( 'Products', 'wp-ai-site-generator' ) );
					$base_pages[] = array( 'type' => 'cart', 'title' => __( 'Cart', 'wp-ai-site-generator' ) );
					break;

				case 'restaurant':
					$base_pages[] = array( 'type' => 'menu', 'title' => __( 'Menu', 'wp-ai-site-generator' ) );
					$base_pages[] = array( 'type' => 'reservations', 'title' => __( 'Reservations', 'wp-ai-site-generator' ) );
					break;

				case 'consulting':
				case 'agency':
					$base_pages[] = array( 'type' => 'services', 'title' => __( 'Services', 'wp-ai-site-generator' ) );
					$base_pages[] = array( 'type' => 'portfolio', 'title' => __( 'Portfolio', 'wp-ai-site-generator' ) );
					break;

				default:
					$base_pages[] = array( 'type' => 'services', 'title' => __( 'Services', 'wp-ai-site-generator' ) );
			}
		}

		// Add requested pages from conversation
		if ( isset( $params['conversation_context']['mentioned']['pages'] ) ) {
			foreach ( $params['conversation_context']['mentioned']['pages'] as $page_name ) {
				$base_pages[] = array(
					'type'  => 'custom',
					'title' => $page_name,
				);
			}
		}

		return $base_pages;
	}

	/**
	 * Generate page title from parameters.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   string              Generated title.
	 */
	private function generate_page_title( $params ) {
		if ( isset( $params['title'] ) && ! empty( $params['title'] ) ) {
			return $params['title'];
		}

		if ( isset( $params['page_type'] ) ) {
			$type_titles = array(
				'home'     => __( 'Home', 'wp-ai-site-generator' ),
				'about'    => __( 'About Us', 'wp-ai-site-generator' ),
				'contact'  => __( 'Contact Us', 'wp-ai-site-generator' ),
				'services' => __( 'Our Services', 'wp-ai-site-generator' ),
				'products' => __( 'Products', 'wp-ai-site-generator' ),
				'portfolio'=> __( 'Portfolio', 'wp-ai-site-generator' ),
				'blog'     => __( 'Blog', 'wp-ai-site-generator' ),
				'faq'      => __( 'FAQ', 'wp-ai-site-generator' ),
			);

			if ( isset( $type_titles[ $params['page_type'] ] ) ) {
				return $type_titles[ $params['page_type'] ];
			}
		}

		return __( 'New Page', 'wp-ai-site-generator' );
	}

	/**
	 * Identify target page for modification.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Parameters.
	 * @return   int|null            Post ID or null.
	 */
	private function identify_target_page( $params ) {
		// Check if post ID is directly mentioned
		if ( isset( $params['post_id'] ) ) {
			return (int) $params['post_id'];
		}

		// Check current context
		if ( isset( $params['context']['current_page_id'] ) ) {
			return (int) $params['context']['current_page_id'];
		}

		// Try to find from message
		if ( preg_match( '/(?:page|post)\s+(?:id|#)\s*(\d+)/i', $params['raw_message'], $match ) ) {
			return (int) $match[1];
		}

		// Get most recent generated page
		$recent_generations = $this->db_handler->get_generations( array(
			'user_id' => get_current_user_id(),
			'status'  => 'completed',
			'limit'   => 1,
			'orderby' => 'completed_at',
			'order'   => 'DESC',
		) );

		if ( ! empty( $recent_generations ) ) {
			$result = json_decode( $recent_generations[0]->result, true );
			if ( isset( $result['post_id'] ) ) {
				return $result['post_id'];
			}
		}

		return null;
	}

	/**
	 * Identify target section for modification.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Parameters.
	 * @param    int      $post_id   Post ID.
	 * @return   string|null         Section ID or null.
	 */
	private function identify_target_section( $params, $post_id ) {
		if ( ! $post_id ) {
			return null;
		}

		// Check if section ID is directly mentioned
		if ( isset( $params['section_id'] ) ) {
			return $params['section_id'];
		}

		// Try to identify from message
		$section_keywords = array(
			'header'  => '/header|top|navigation/i',
			'hero'    => '/hero|banner|main.*image/i',
			'content' => '/content|main|body/i',
			'footer'  => '/footer|bottom/i',
		);

		foreach ( $section_keywords as $section => $pattern ) {
			if ( preg_match( $pattern, $params['raw_message'] ) ) {
				// Find matching section in page
				$post = get_post( $post_id );
				if ( $post ) {
					$blocks = parse_blocks( $post->post_content );
					foreach ( $blocks as $block ) {
						if ( isset( $block['attrs']['className'] ) &&
						     strpos( $block['attrs']['className'], $section ) !== false ) {
							return $block['attrs']['id'] ?? $section;
						}
					}
				}
				return $section;
			}
		}

		// Default to first content section
		return 'content';
	}

	/**
	 * Update chat with generation result.
	 *
	 * @since    1.0.0
	 * @param    int      $session_id    Chat session ID.
	 * @param    array    $result        Generation result.
	 * @return   void
	 */
	private function update_chat_with_result( $session_id, $result ) {
		$message = $this->format_generation_response( $result['intent'] ?? 'generation', $result );

		// Save assistant response
		$this->db_handler->save_chat_message( array(
			'session_id' => $session_id,
			'role'       => 'assistant',
			'content'    => $message,
			'metadata'   => wp_json_encode( array(
				'generation_result' => $result,
				'type'              => 'generation_response',
			) ),
		) );

		// Update session metadata
		$session = $this->db_handler->get_chat_session( $session_id );
		if ( $session ) {
			$metadata = json_decode( $session->metadata, true ) ?? array();
			$metadata['last_generation'] = array(
				'job_id'    => $result['job_id'] ?? null,
				'type'      => $result['intent'] ?? 'generation',
				'timestamp' => current_time( 'mysql' ),
			);

			$this->db_handler->update_chat_session( $session_id, array(
				'metadata' => wp_json_encode( $metadata ),
			) );
		}
	}

	/**
	 * Format generation response for chat.
	 *
	 * @since    1.0.0
	 * @param    string    $intent    Generation intent.
	 * @param    array     $result    Generation result.
	 * @return   string               Formatted response.
	 */
	private function format_generation_response( $intent, $result ) {
		if ( isset( $result['error'] ) ) {
			return sprintf(
				__( 'I encountered an error while generating content: %s. Please try again or provide more details.', 'wp-ai-site-generator' ),
				$result['error']
			);
		}

		$response = '';

		switch ( $intent ) {
			case 'create_site':
				if ( isset( $result['pages'] ) && is_array( $result['pages'] ) ) {
					$page_count = count( $result['pages'] );
					$successful = count( array_filter( $result['pages'], function( $p ) {
						return isset( $p['success'] ) && $p['success'];
					} ) );

					$response = sprintf(
						__( "I've successfully generated your website with %d pages!\n\n", 'wp-ai-site-generator' ),
						$successful
					);

					$response .= __( "Here's what I created:\n", 'wp-ai-site-generator' );
					foreach ( $result['pages'] as $page ) {
						if ( isset( $page['title'] ) && isset( $page['post_id'] ) ) {
							$response .= sprintf(
								"• %s - [View Page](%s)\n",
								$page['title'],
								get_permalink( $page['post_id'] )
							);
						}
					}

					if ( isset( $result['metrics']['average_quality'] ) ) {
						$response .= sprintf(
							__( "\nContent Quality Score: %.0f%%\n", 'wp-ai-site-generator' ),
							$result['metrics']['average_quality'] * 100
						);
					}

					if ( ! empty( $result['errors'] ) ) {
						$response .= sprintf(
							__( "\nNote: Some issues were encountered:\n%s", 'wp-ai-site-generator' ),
							implode( "\n", array_map( function( $e ) { return "• $e"; }, $result['errors'] ) )
						);
					}
				}
				break;

			case 'create_page':
				if ( isset( $result['post_id'] ) && isset( $result['title'] ) ) {
					$response = sprintf(
						__( "Great! I've created the '%s' page for you.\n\n", 'wp-ai-site-generator' ),
						$result['title']
					);

					$response .= sprintf(
						__( "[View the page](%s) | [Edit in WordPress](%s)\n", 'wp-ai-site-generator' ),
						get_permalink( $result['post_id'] ),
						get_edit_post_link( $result['post_id'] )
					);

					if ( isset( $result['quality_score']['overall_score'] ) ) {
						$response .= sprintf(
							__( "\nContent Quality Score: %.0f%%\n", 'wp-ai-site-generator' ),
							$result['quality_score']['overall_score'] * 100
						);

						if ( $result['quality_score']['overall_score'] < 0.7 ) {
							$response .= __( "The content quality could be improved. Would you like me to enhance it?", 'wp-ai-site-generator' );
						}
					}
				}
				break;

			case 'regenerate_section':
				if ( isset( $result['success'] ) && $result['success'] ) {
					$response = __( "I've successfully regenerated the section with your requested improvements.\n\n", 'wp-ai-site-generator' );

					if ( isset( $result['post_id'] ) ) {
						$response .= sprintf(
							__( "[View updated page](%s)\n", 'wp-ai-site-generator' ),
							get_permalink( $result['post_id'] )
						);
					}

					if ( isset( $result['quality_score']['overall_score'] ) ) {
						$response .= sprintf(
							__( "\nNew Quality Score: %.0f%%\n", 'wp-ai-site-generator' ),
							$result['quality_score']['overall_score'] * 100
						);
					}
				}
				break;

			default:
				if ( isset( $result['success'] ) && $result['success'] ) {
					$response = __( "I've completed the content generation successfully!", 'wp-ai-site-generator' );
				} else {
					$response = __( "The generation process completed with some issues. Please review the results.", 'wp-ai-site-generator' );
				}
		}

		// Add helpful next steps
		$response .= __( "\n\nWhat would you like to do next? You can:\n", 'wp-ai-site-generator' );
		$response .= __( "• Ask me to modify any section\n", 'wp-ai-site-generator' );
		$response .= __( "• Create additional pages\n", 'wp-ai-site-generator' );
		$response .= __( "• Adjust the design or content style\n", 'wp-ai-site-generator' );
		$response .= __( "• Preview your site\n", 'wp-ai-site-generator' );

		return $response;
	}

	/**
	 * Get generation status from chat context.
	 *
	 * @since    1.0.0
	 * @param    int    $session_id    Chat session ID.
	 * @return   array|null            Generation status or null.
	 */
	public function get_generation_status( $session_id ) {
		$session = $this->db_handler->get_chat_session( $session_id );
		if ( ! $session ) {
			return null;
		}

		$metadata = json_decode( $session->metadata, true );
		if ( ! isset( $metadata['last_generation']['job_id'] ) ) {
			return null;
		}

		return $this->orchestrator->get_job_status( $metadata['last_generation']['job_id'] );
	}

	/**
	 * Cancel generation from chat.
	 *
	 * @since    1.0.0
	 * @param    int    $session_id    Chat session ID.
	 * @return   bool|WP_Error         Success or error.
	 */
	public function cancel_generation( $session_id ) {
		$session = $this->db_handler->get_chat_session( $session_id );
		if ( ! $session ) {
			return new WP_Error(
				'session_not_found',
				__( 'Chat session not found', 'wp-ai-site-generator' )
			);
		}

		$metadata = json_decode( $session->metadata, true );
		if ( ! isset( $metadata['last_generation']['job_id'] ) ) {
			return new WP_Error(
				'no_generation',
				__( 'No active generation to cancel', 'wp-ai-site-generator' )
			);
		}

		return $this->orchestrator->cancel_job( $metadata['last_generation']['job_id'] );
	}
}