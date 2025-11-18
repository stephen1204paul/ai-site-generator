<?php
/**
 * Anthropic Claude Provider Implementation.
 *
 * Provides integration with Anthropic's Claude models for content generation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

/**
 * Anthropic Provider Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
class Anthropic_Provider extends Base_Provider {

	/**
	 * API endpoint.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_endpoint    Anthropic API endpoint.
	 */
	private $api_endpoint = 'https://api.anthropic.com/v1';

	/**
	 * API version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_version    Anthropic API version.
	 */
	private $api_version = '2023-06-01';

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
		$this->name         = 'anthropic';
		$this->display_name = 'Anthropic Claude';
		$this->description  = 'Advanced AI models from Anthropic, including Claude 3.5 Sonnet and Haiku for safe and helpful content generation';

		// Define available models with metadata
		$this->models = array(
			'claude-3-5-sonnet-20241022' => array(
				'name'         => 'Claude 3.5 Sonnet',
				'description'  => 'Most intelligent model with superior performance',
				'context'      => 200000,
				'max_output'   => 8192,
				'cost_input'   => 0.003,  // per 1K tokens
				'cost_output'  => 0.015,  // per 1K tokens
			),
			'claude-3-5-haiku-20241022' => array(
				'name'         => 'Claude 3.5 Haiku',
				'description'  => 'Fast and cost-effective model',
				'context'      => 200000,
				'max_output'   => 8192,
				'cost_input'   => 0.0008,
				'cost_output'  => 0.004,
			),
			'claude-3-opus-20240229' => array(
				'name'         => 'Claude 3 Opus',
				'description'  => 'Previous generation powerful model',
				'context'      => 200000,
				'max_output'   => 4096,
				'cost_input'   => 0.015,
				'cost_output'  => 0.075,
			),
			'claude-3-sonnet-20240229' => array(
				'name'         => 'Claude 3 Sonnet',
				'description'  => 'Balanced performance and cost',
				'context'      => 200000,
				'max_output'   => 4096,
				'cost_input'   => 0.003,
				'cost_output'  => 0.015,
			),
		);

		// Define capabilities
		$this->capabilities = array(
			'chat_completion',
			'streaming',
			'system_prompt',
			'vision',
			'temperature_control',
			'token_counting',
			'batch_processing',
			'tool_use',
			'long_context',
			'safety_features',
		);

		// Define rate limits (requests per minute)
		$this->rate_limits = array(
			'claude-3-5-sonnet-20241022' => 1000,
			'claude-3-5-haiku-20241022'  => 2000,
			'claude-3-opus-20240229'      => 1000,
			'claude-3-sonnet-20240229'    => 1000,
		);

		// Set default model if not configured
		if ( empty( $this->settings['model'] ) ) {
			$this->settings['model'] = 'claude-3-5-sonnet-20241022';
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
			// Test with a simple message
			$response = $this->make_api_request(
				'/messages',
				array(
					'model'      => 'claude-3-5-haiku-20241022',
					'max_tokens' => 10,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => 'Hello',
						),
					),
				)
			);

			if ( isset( $response['content'] ) ) {
				return array(
					'success' => true,
					'message' => __( 'Successfully connected to Anthropic API', 'wp-ai-site-generator' ),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Unexpected response from Anthropic API', 'wp-ai-site-generator' ),
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
	 * Generate content using Anthropic Claude.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for content generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated content with metadata.
	 */
	public function generate( $prompt, $options = array() ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( __( 'Anthropic provider is not available', 'wp-ai-site-generator' ) );
		}

		$defaults = array(
			'model'         => $this->get_current_model(),
			'temperature'   => $this->settings['temperature'] ?? 0.7,
			'max_tokens'    => $this->settings['max_tokens'] ?? 4000,
			'stream'        => false,
			'system_prompt' => null,
		);

		$options = wp_parse_args( $options, $defaults );

		// Build messages array
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		// Build request body
		$body = array(
			'model'      => $options['model'],
			'messages'   => $messages,
			'max_tokens' => intval( $options['max_tokens'] ),
		);

		// Add system prompt if provided
		if ( ! empty( $options['system_prompt'] ) ) {
			$body['system'] = $options['system_prompt'];
		}

		// Add temperature if not default
		if ( $options['temperature'] != 1.0 ) {
			$body['temperature'] = floatval( $options['temperature'] );
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
				'/messages',
				$body,
				'POST',
				$options['stream']
			);

			$end_time = microtime( true );
			$duration = round( ( $end_time - $start_time ) * 1000 ); // milliseconds

			// Extract content from response
			$content = '';
			$stop_reason = '';
			$usage = array();

			if ( $options['stream'] ) {
				// Handle streaming response
				$content = $this->process_stream_response( $response );
			} else {
				// Extract text content from response
				if ( isset( $response['content'] ) && is_array( $response['content'] ) ) {
					$text_parts = array();
					foreach ( $response['content'] as $content_block ) {
						if ( $content_block['type'] === 'text' ) {
							$text_parts[] = $content_block['text'];
						}
					}
					$content = implode( "\n", $text_parts );
				}

				if ( isset( $response['stop_reason'] ) ) {
					$stop_reason = $response['stop_reason'];
				}

				if ( isset( $response['usage'] ) ) {
					$usage = $response['usage'];
				}
			}

			// Calculate costs
			$cost = $this->calculate_cost( $usage, $options['model'] );

			// Track usage
			$this->track_usage( array(
				'tokens_input'  => $usage['input_tokens'] ?? 0,
				'tokens_output' => $usage['output_tokens'] ?? 0,
				'tokens_total'  => ( $usage['input_tokens'] ?? 0 ) + ( $usage['output_tokens'] ?? 0 ),
				'cost'          => $cost,
				'duration'      => $duration,
				'model'         => $options['model'],
			) );

			return array(
				'content'      => $content,
				'model'        => $options['model'],
				'stop_reason'  => $stop_reason,
				'usage'        => $usage,
				'cost'         => $cost,
				'duration'     => $duration,
				'provider'     => $this->name,
			);

		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'Anthropic generation failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Make API request to Anthropic.
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
				'x-api-key'         => $this->settings['api_key'],
				'anthropic-version' => $this->api_version,
				'Content-Type'      => 'application/json',
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
			$error_message = $response_data['error']['message'] ?? 'Unknown error';
			$error_type = $response_data['error']['type'] ?? 'api_error';

			// Handle specific error types
			if ( $error_type === 'invalid_request_error' ) {
				throw new \Exception( sprintf( 'Invalid request: %s', $error_message ) );
			} elseif ( $error_type === 'authentication_error' ) {
				throw new \Exception( 'Authentication failed. Please check your API key.' );
			} elseif ( $error_type === 'permission_error' ) {
				throw new \Exception( 'Permission denied. Please check your API key permissions.' );
			} elseif ( $error_type === 'rate_limit_error' ) {
				throw new \Exception( 'Rate limit exceeded. Please try again later.' );
			} else {
				throw new \Exception( sprintf( 'Anthropic API error: %s', $error_message ) );
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
					$retry_delay = min( $retry_delay * 2, 30 ); // Exponential backoff with max 30 seconds
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
			'overloaded',
			'529',
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
		// For now, just return the content as-is
		if ( isset( $response['content'] ) && is_array( $response['content'] ) ) {
			$text_parts = array();
			foreach ( $response['content'] as $content_block ) {
				if ( $content_block['type'] === 'text' ) {
					$text_parts[] = $content_block['text'];
				}
			}
			return implode( "\n", $text_parts );
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
		$input_tokens = $usage['input_tokens'] ?? 0;
		$output_tokens = $usage['output_tokens'] ?? 0;

		$input_cost = ( $input_tokens / 1000 ) * $model_info['cost_input'];
		$output_cost = ( $output_tokens / 1000 ) * $model_info['cost_output'];

		return round( $input_cost + $output_cost, 6 );
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

		// Claude uses a similar token estimation to OpenAI
		$prompt_tokens = $this->count_tokens( $prompt );
		$max_output = $options['max_tokens'] ?? $this->settings['max_tokens'] ?? 4000;

		$usage = array(
			'input_tokens'  => $prompt_tokens,
			'output_tokens' => min( $max_output, $this->models[ $model ]['max_output'] ),
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
		// Simple approximation - Claude uses roughly 1 token per 3.5 characters
		return ceil( strlen( $text ) / 3.5 );
	}

	/**
	 * Get settings schema for Anthropic provider.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema.
	 */
	public function get_settings_schema() {
		$schema = parent::get_settings_schema();

		// Add Anthropic-specific settings
		$schema['enable_streaming'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Streaming', 'wp-ai-site-generator' ),
			'description' => __( 'Stream responses for real-time generation', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['enable_tool_use'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Tool Use', 'wp-ai-site-generator' ),
			'description' => __( 'Allow Claude to use tools for enhanced capabilities', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['safety_level'] = array(
			'type'        => 'select',
			'label'       => __( 'Safety Level', 'wp-ai-site-generator' ),
			'description' => __( 'Content safety filtering level', 'wp-ai-site-generator' ),
			'options'     => array(
				'default'  => __( 'Default', 'wp-ai-site-generator' ),
				'relaxed'  => __( 'Relaxed', 'wp-ai-site-generator' ),
				'strict'   => __( 'Strict', 'wp-ai-site-generator' ),
			),
			'default'     => 'default',
		);

		return $schema;
	}

	/**
	 * Generate layout using system prompts.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       Layout generation prompt.
	 * @param    string    $layout_type  Type of layout to generate.
	 * @return   array                   Generated layout data.
	 */
	public function generate_layout( $prompt, $layout_type ) {
		$system_prompts = array(
			'homepage' => 'You are an expert web designer specializing in homepage layouts. Create modern, conversion-focused homepage designs with clear navigation, compelling hero sections, and strategic CTAs.',
			'landing' => 'You are a conversion rate optimization expert. Design high-converting landing pages with clear value propositions, social proof, and persuasive copy.',
			'blog' => 'You are a content layout specialist. Create engaging blog layouts that maximize readability and user engagement.',
			'portfolio' => 'You are a portfolio design expert. Create visually stunning portfolio layouts that showcase work effectively.',
			'ecommerce' => 'You are an e-commerce design specialist. Create product-focused layouts that drive sales and improve user experience.',
		);

		$system_prompt = $system_prompts[ $layout_type ] ?? $system_prompts['homepage'];

		$formatted_prompt = sprintf(
			"Generate a detailed layout structure for: %s\n\nReturn the response as a structured list of sections with their content.",
			$prompt
		);

		$response = $this->generate(
			$formatted_prompt,
			array(
				'system_prompt' => $system_prompt,
				'temperature'   => 0.5, // More consistent for layouts
				'max_tokens'    => 2000,
			)
		);

		return $this->parse_layout_response( $response['content'] );
	}

	/**
	 * Parse layout response into structured data.
	 *
	 * @since    1.0.0
	 * @param    string    $content    Raw layout content.
	 * @return   array                 Structured layout data.
	 */
	private function parse_layout_response( $content ) {
		// Parse the content into structured sections
		$sections = array();
		$lines = explode( "\n", $content );
		$current_section = null;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			// Check if this is a new section header
			if ( preg_match( '/^#+\s+(.+)$/', $line, $matches ) ) {
				if ( $current_section ) {
					$sections[] = $current_section;
				}
				$current_section = array(
					'title'   => $matches[1],
					'content' => array(),
				);
			} elseif ( $current_section ) {
				$current_section['content'][] = $line;
			}
		}

		if ( $current_section ) {
			$sections[] = $current_section;
		}

		return array(
			'sections' => $sections,
			'raw'      => $content,
		);
	}
}