<?php
/**
 * Cohere Provider Implementation.
 *
 * Provides integration with Cohere's Command models for content generation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

/**
 * Cohere Provider Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
class Cohere_Provider extends Base_Provider {

	/**
	 * API endpoint.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_endpoint    Cohere API endpoint.
	 */
	private $api_endpoint = 'https://api.cohere.ai/v1';

	/**
	 * Retry attempts.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_retries    Maximum retry attempts.
	 */
	private $max_retries = 3;

	/**
	 * Initialize provider.
	 *
	 * @since    1.0.0
	 */
	protected function initialize() {
		$this->name         = 'cohere';
		$this->display_name = 'Cohere';
		$this->description  = 'Powerful language models with RAG capabilities and enterprise-ready features';

		// Define available models with metadata
		$this->models = array(
			'command-r-plus-08-2024' => array(
				'name'         => 'Command R+',
				'description'  => 'Most performant model for complex RAG workflows',
				'context'      => 128000,
				'max_output'   => 4000,
				'cost_input'   => 0.0025,  // per 1K tokens
				'cost_output'  => 0.01,    // per 1K tokens
			),
			'command-r-08-2024' => array(
				'name'         => 'Command R',
				'description'  => 'Balanced performance for RAG and tool use',
				'context'      => 128000,
				'max_output'   => 4000,
				'cost_input'   => 0.00015,
				'cost_output'  => 0.0006,
			),
			'command-light' => array(
				'name'         => 'Command Light',
				'description'  => 'Lightweight model for simple tasks',
				'context'      => 4096,
				'max_output'   => 4000,
				'cost_input'   => 0.00015,
				'cost_output'  => 0.0006,
			),
			'command-nightly' => array(
				'name'         => 'Command Nightly',
				'description'  => 'Experimental features and improvements',
				'context'      => 128000,
				'max_output'   => 4000,
				'cost_input'   => 0.0025,
				'cost_output'  => 0.01,
			),
		);

		// Define capabilities
		$this->capabilities = array(
			'chat_completion',
			'streaming',
			'rag',
			'tool_use',
			'connectors',
			'web_search',
			'citations',
			'temperature_control',
			'token_counting',
			'batch_processing',
			'rerank',
			'embed',
			'classify',
		);

		// Define rate limits (requests per minute)
		$this->rate_limits = array(
			'command-r-plus-08-2024' => 1000,
			'command-r-08-2024'      => 1000,
			'command-light'          => 10000,
			'command-nightly'        => 1000,
		);

		// Set default model if not configured
		if ( empty( $this->settings['model'] ) ) {
			$this->settings['model'] = 'command-r-08-2024';
		}
	}

	/**
	 * Test provider connection.
	 *
	 * @since    1.0.0
	 * @return   array    Test result.
	 */
	public function test_connection() {
		if ( empty( $this->settings['api_key'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'API key is not configured', 'wp-ai-site-generator' ),
			);
		}

		try {
			// Test with a simple chat request
			$response = $this->make_api_request(
				'/chat',
				array(
					'model'       => 'command-light',
					'message'     => 'Hello',
					'max_tokens'  => 10,
				)
			);

			if ( isset( $response['text'] ) ) {
				return array(
					'success' => true,
					'message' => __( 'Successfully connected to Cohere API', 'wp-ai-site-generator' ),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Unexpected response from Cohere API', 'wp-ai-site-generator' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Connection failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Generate content using Cohere.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for content generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated content with metadata.
	 */
	public function generate( $prompt, $options = array() ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( __( 'Cohere provider is not available', 'wp-ai-site-generator' ) );
		}

		$defaults = array(
			'model'         => $this->get_current_model(),
			'temperature'   => $this->settings['temperature'] ?? 0.7,
			'max_tokens'    => $this->settings['max_tokens'] ?? 4000,
			'stream'        => false,
			'preamble'      => null,
			'connectors'    => array(),
			'search_queries_only' => false,
		);

		$options = wp_parse_args( $options, $defaults );

		// Build chat history if provided context
		$chat_history = array();
		if ( ! empty( $options['context'] ) ) {
			foreach ( $options['context'] as $message ) {
				$chat_history[] = array(
					'role'    => $message['role'] === 'assistant' ? 'CHATBOT' : 'USER',
					'message' => $message['content'],
				);
			}
		}

		// Build request body
		$body = array(
			'model'        => $options['model'],
			'message'      => $prompt,
			'temperature'  => floatval( $options['temperature'] ),
			'max_tokens'   => intval( $options['max_tokens'] ),
			'chat_history' => $chat_history,
		);

		// Add preamble (system prompt) if provided
		if ( ! empty( $options['preamble'] ) || ! empty( $options['system_prompt'] ) ) {
			$body['preamble'] = $options['preamble'] ?? $options['system_prompt'];
		}

		// Add connectors if enabled (for RAG)
		if ( ! empty( $options['connectors'] ) ) {
			$body['connectors'] = $options['connectors'];
		}

		// Add search queries only mode if enabled
		if ( $options['search_queries_only'] ) {
			$body['search_queries_only'] = true;
		}

		// Add streaming if enabled
		if ( $options['stream'] ) {
			$body['stream'] = true;
		}

		// Track start time
		$start_time = microtime( true );

		try {
			// Make API request with retry logic
			$response = $this->make_api_request_with_retry(
				'/chat',
				$body,
				'POST',
				$options['stream']
			);

			$end_time = microtime( true );
			$duration = round( ( $end_time - $start_time ) * 1000 ); // milliseconds

			// Extract content from response
			$content = '';
			$finish_reason = '';
			$usage = array();
			$citations = array();
			$search_queries = array();

			if ( $options['stream'] ) {
				// Handle streaming response
				$content = $this->process_stream_response( $response );
			} else {
				if ( isset( $response['text'] ) ) {
					$content = $response['text'];
				}

				if ( isset( $response['finish_reason'] ) ) {
					$finish_reason = $response['finish_reason'];
				}

				// Extract token usage
				if ( isset( $response['meta'] ) ) {
					$usage = array(
						'prompt_tokens'     => $response['meta']['billed_units']['input_tokens'] ?? 0,
						'completion_tokens' => $response['meta']['billed_units']['output_tokens'] ?? 0,
						'search_tokens'     => $response['meta']['billed_units']['search_units'] ?? 0,
						'total_tokens'      => ( $response['meta']['billed_units']['input_tokens'] ?? 0 ) +
						                      ( $response['meta']['billed_units']['output_tokens'] ?? 0 ),
					);
				}

				// Extract citations if available
				if ( isset( $response['citations'] ) ) {
					$citations = $response['citations'];
				}

				// Extract search queries if available
				if ( isset( $response['search_queries'] ) ) {
					$search_queries = $response['search_queries'];
				}
			}

			// Calculate costs
			$cost = $this->calculate_cost( $usage, $options['model'] );

			// Track usage
			$this->track_usage( array(
				'tokens_input'  => $usage['prompt_tokens'] ?? 0,
				'tokens_output' => $usage['completion_tokens'] ?? 0,
				'tokens_total'  => $usage['total_tokens'] ?? 0,
				'search_units'  => $usage['search_tokens'] ?? 0,
				'cost'          => $cost,
				'duration'      => $duration,
				'model'         => $options['model'],
			) );

			$result = array(
				'content'       => $content,
				'model'         => $options['model'],
				'finish_reason' => $finish_reason,
				'usage'         => $usage,
				'cost'          => $cost,
				'duration'      => $duration,
				'provider'      => $this->name,
			);

			// Add citations and search queries if available
			if ( ! empty( $citations ) ) {
				$result['citations'] = $citations;
			}

			if ( ! empty( $search_queries ) ) {
				$result['search_queries'] = $search_queries;
			}

			return $result;

		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'Cohere generation failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Make API request to Cohere.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    API endpoint.
	 * @param    array     $body        Request body.
	 * @param    string    $method      HTTP method.
	 * @return   array                  API response.
	 */
	private function make_api_request( $endpoint, $body = array(), $method = 'POST' ) {
		$url = $this->api_endpoint . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->settings['api_key'],
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => $this->settings['timeout'] ?? 30,
		);

		if ( ! empty( $body ) && $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( $response_code >= 400 ) {
			$error_message = $response_data['message'] ?? 'Unknown error';

			// Handle specific error codes
			if ( $response_code === 400 ) {
				throw new \Exception( sprintf( 'Bad request: %s', $error_message ) );
			} elseif ( $response_code === 401 ) {
				throw new \Exception( 'Authentication failed. Please check your API key.' );
			} elseif ( $response_code === 403 ) {
				throw new \Exception( 'Permission denied. Please check your API key permissions.' );
			} elseif ( $response_code === 429 ) {
				throw new \Exception( 'Rate limit exceeded. Please try again later.' );
			} elseif ( $response_code === 500 ) {
				throw new \Exception( 'Cohere server error. Please try again later.' );
			} else {
				throw new \Exception( sprintf( 'Cohere API error: %s', $error_message ) );
			}
		}

		return $response_data;
	}

	/**
	 * Make API request with retry logic.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    API endpoint.
	 * @param    array     $body        Request body.
	 * @param    string    $method      HTTP method.
	 * @param    bool      $stream      Whether to stream response.
	 * @return   array                  API response.
	 */
	private function make_api_request_with_retry( $endpoint, $body, $method = 'POST', $stream = false ) {
		$last_exception = null;
		$retry_delay = 1; // Start with 1 second

		for ( $attempt = 0; $attempt <= $this->max_retries; $attempt++ ) {
			try {
				if ( $stream ) {
					return $this->make_streaming_request( $endpoint, $body );
				} else {
					return $this->make_api_request( $endpoint, $body, $method );
				}
			} catch ( \Exception $e ) {
				$last_exception = $e;

				// Check if error is retryable
				if ( ! $this->is_retryable_error( $e->getMessage() ) ) {
					throw $e;
				}

				// If we have more retries left, wait and try again
				if ( $attempt < $this->max_retries ) {
					sleep( $retry_delay );
					$retry_delay = min( $retry_delay * 2, 30 ); // Exponential backoff
				}
			}
		}

		// All retries exhausted
		throw $last_exception;
	}

	/**
	 * Check if an error is retryable.
	 *
	 * @since    1.0.0
	 * @param    string    $error_message    Error message.
	 * @return   bool                        Whether error is retryable.
	 */
	private function is_retryable_error( $error_message ) {
		$retryable_patterns = array(
			'rate limit',
			'timeout',
			'temporarily unavailable',
			'connection reset',
			'server error',
			'500',
			'502',
			'503',
			'504',
		);

		foreach ( $retryable_patterns as $pattern ) {
			if ( stripos( $error_message, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Make streaming API request.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    API endpoint.
	 * @param    array     $body        Request body.
	 * @return   array                  Streamed response data.
	 */
	private function make_streaming_request( $endpoint, $body ) {
		// Note: WordPress doesn't natively support SSE streams
		// This is a simplified implementation
		$body['stream'] = false; // Disable streaming for now
		return $this->make_api_request( $endpoint, $body, 'POST' );
	}

	/**
	 * Process streaming response.
	 *
	 * @since    1.0.0
	 * @param    array    $response    Streaming response.
	 * @return   string                Complete response content.
	 */
	private function process_stream_response( $response ) {
		// For now, just return the text content
		if ( isset( $response['text'] ) ) {
			return $response['text'];
		}
		return '';
	}

	/**
	 * Calculate cost based on token usage.
	 *
	 * @since    1.0.0
	 * @param    array     $usage    Token usage data.
	 * @param    string    $model    Model used.
	 * @return   float               Estimated cost in USD.
	 */
	private function calculate_cost( $usage, $model ) {
		if ( empty( $usage ) || ! isset( $this->models[ $model ] ) ) {
			return 0;
		}

		$model_info = $this->models[ $model ];
		$input_tokens = $usage['prompt_tokens'] ?? 0;
		$output_tokens = $usage['completion_tokens'] ?? 0;

		$input_cost = ( $input_tokens / 1000 ) * $model_info['cost_input'];
		$output_cost = ( $output_tokens / 1000 ) * $model_info['cost_output'];

		// Add search unit costs if applicable (estimated at $0.001 per search unit)
		$search_cost = ( $usage['search_tokens'] ?? 0 ) * 0.001;

		return round( $input_cost + $output_cost + $search_cost, 6 );
	}

	/**
	 * Estimate cost for generation.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt to estimate cost for.
	 * @param    array     $options      Optional. Generation options.
	 * @return   float                   Estimated cost.
	 */
	public function estimate_cost( $prompt, $options = array() ) {
		$model = $options['model'] ?? $this->get_current_model();

		if ( ! isset( $this->models[ $model ] ) ) {
			return 0;
		}

		// Estimate tokens
		$prompt_tokens = $this->count_tokens( $prompt );
		$max_output = $options['max_tokens'] ?? $this->settings['max_tokens'] ?? 4000;

		$usage = array(
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => min( $max_output, $this->models[ $model ]['max_output'] ),
		);

		return $this->calculate_cost( $usage, $model );
	}

	/**
	 * Count tokens in text.
	 *
	 * @since    1.0.0
	 * @param    string    $text    Text to count tokens in.
	 * @return   int                 Estimated token count.
	 */
	public function count_tokens( $text ) {
		// Cohere uses roughly 1 token per 4 characters
		return ceil( strlen( $text ) / 4 );
	}

	/**
	 * Get settings schema for Cohere provider.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema.
	 */
	public function get_settings_schema() {
		$schema = parent::get_settings_schema();

		// Add Cohere-specific settings
		$schema['enable_rag'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable RAG', 'wp-ai-site-generator' ),
			'description' => __( 'Enable Retrieval-Augmented Generation for enhanced accuracy', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['enable_web_search'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Web Search', 'wp-ai-site-generator' ),
			'description' => __( 'Allow searching the web for up-to-date information', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['enable_citations'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Citations', 'wp-ai-site-generator' ),
			'description' => __( 'Include source citations in generated content', 'wp-ai-site-generator' ),
			'default'     => true,
		);

		$schema['connectors'] = array(
			'type'        => 'textarea',
			'label'       => __( 'Connectors', 'wp-ai-site-generator' ),
			'description' => __( 'Configure data connectors for RAG (JSON format)', 'wp-ai-site-generator' ),
			'required'    => false,
		);

		return $schema;
	}

	/**
	 * Perform RAG-enhanced generation.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for generation.
	 * @param    array     $documents    Documents for context.
	 * @return   array                   Generated response with citations.
	 */
	public function generate_with_rag( $prompt, $documents = array() ) {
		$options = array(
			'connectors' => array(
				array(
					'id' => 'web-search',
				),
			),
			'preamble' => 'You are a helpful assistant with access to web search. Always provide accurate information and cite your sources.',
		);

		// If documents are provided, use them for context
		if ( ! empty( $documents ) ) {
			$context = "Context documents:\n";
			foreach ( $documents as $doc ) {
				$context .= "---\n" . $doc . "\n";
			}
			$prompt = $context . "\n\nQuery: " . $prompt;
		}

		return $this->generate( $prompt, $options );
	}

	/**
	 * Rerank documents based on relevance.
	 *
	 * @since    1.0.0
	 * @param    string    $query        Search query.
	 * @param    array     $documents    Documents to rerank.
	 * @return   array                   Reranked documents.
	 */
	public function rerank( $query, $documents ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( __( 'Cohere provider is not available', 'wp-ai-site-generator' ) );
		}

		$body = array(
			'model'     => 'rerank-english-v3.0',
			'query'     => $query,
			'documents' => $documents,
		);

		try {
			$response = $this->make_api_request( '/rerank', $body );

			if ( isset( $response['results'] ) ) {
				// Sort documents by relevance score
				usort( $response['results'], function( $a, $b ) {
					return $b['relevance_score'] <=> $a['relevance_score'];
				} );

				return $response['results'];
			}

			return $documents;

		} catch ( \Exception $e ) {
			// If reranking fails, return original documents
			error_log( 'Cohere rerank failed: ' . $e->getMessage() );
			return $documents;
		}
	}

	/**
	 * Generate embeddings for text.
	 *
	 * @since    1.0.0
	 * @param    array     $texts    Array of texts to embed.
	 * @return   array               Array of embeddings.
	 */
	public function generate_embeddings( $texts ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( __( 'Cohere provider is not available', 'wp-ai-site-generator' ) );
		}

		$body = array(
			'model'      => 'embed-english-v3.0',
			'texts'      => $texts,
			'input_type' => 'search_document',
		);

		try {
			$response = $this->make_api_request( '/embed', $body );

			if ( isset( $response['embeddings'] ) ) {
				return $response['embeddings'];
			}

			return array();

		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'Embedding generation failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				)
			);
		}
	}
}