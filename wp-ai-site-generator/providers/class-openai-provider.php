<?php
/**
 * OpenAI Provider Implementation.
 *
 * Provides integration with OpenAI's GPT models for content generation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

/**
 * OpenAI Provider Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
class OpenAI_Provider extends Base_Provider {

	/**
	 * API endpoint.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_endpoint    OpenAI API endpoint.
	 */
	private $api_endpoint = 'https://api.openai.com/v1';

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
		$this->name         = 'openai';
		$this->display_name = 'OpenAI';
		$this->description  = 'Advanced AI models including GPT-4o and GPT-4o-mini for high-quality content generation';

		// Define available models with metadata
		$this->models = array(
			'gpt-4o' => array(
				'name'         => 'GPT-4o',
				'description'  => 'Most advanced model with superior reasoning and multimodal capabilities',
				'context'      => 128000,
				'max_output'   => 4096,
				'cost_input'   => 0.0025, // per 1K tokens
				'cost_output'  => 0.01,   // per 1K tokens
			),
			'gpt-4o-mini' => array(
				'name'         => 'GPT-4o Mini',
				'description'  => 'Cost-effective model for faster generation',
				'context'      => 128000,
				'max_output'   => 16384,
				'cost_input'   => 0.00015,
				'cost_output'  => 0.0006,
			),
			'gpt-4-turbo' => array(
				'name'         => 'GPT-4 Turbo',
				'description'  => 'Previous generation advanced model',
				'context'      => 128000,
				'max_output'   => 4096,
				'cost_input'   => 0.01,
				'cost_output'  => 0.03,
			),
			'gpt-3.5-turbo' => array(
				'name'         => 'GPT-3.5 Turbo',
				'description'  => 'Fast and cost-effective for simple tasks',
				'context'      => 16385,
				'max_output'   => 4096,
				'cost_input'   => 0.0005,
				'cost_output'  => 0.0015,
			),
		);

		// Define capabilities
		$this->capabilities = array(
			'chat_completion',
			'streaming',
			'function_calling',
			'structured_output',
			'vision',
			'json_mode',
			'system_prompt',
			'temperature_control',
			'token_counting',
			'batch_processing',
		);

		// Define rate limits (requests per minute)
		$this->rate_limits = array(
			'gpt-4o'        => 500,
			'gpt-4o-mini'   => 5000,
			'gpt-4-turbo'   => 500,
			'gpt-3.5-turbo' => 3500,
		);

		// Set default model if not configured
		if ( empty( $this->settings['model'] ) ) {
			$this->settings['model'] = 'gpt-4o-mini';
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
			$response = $this->make_api_request(
				'/models',
				array(),
				'GET'
			);

			if ( ! empty( $response['data'] ) ) {
				return array(
					'success' => true,
					'message' => __( 'Successfully connected to OpenAI API', 'wp-ai-site-generator' ),
					'models'  => array_column( $response['data'], 'id' ),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Unable to fetch models from OpenAI', 'wp-ai-site-generator' ),
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
	 * Generate content using OpenAI.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for content generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated content with metadata.
	 */
	public function generate( $prompt, $options = array() ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( __( 'OpenAI provider is not available', 'wp-ai-site-generator' ) );
		}

		$defaults = array(
			'model'           => $this->get_current_model(),
			'temperature'     => $this->settings['temperature'] ?? 0.7,
			'max_tokens'      => $this->settings['max_tokens'] ?? 4000,
			'stream'          => false,
			'response_format' => null,
			'system_prompt'   => null,
		);

		$options = wp_parse_args( $options, $defaults );

		// Build messages array
		$messages = array();

		if ( ! empty( $options['system_prompt'] ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $options['system_prompt'],
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $prompt,
		);

		// Build request body
		$body = array(
			'model'       => $options['model'],
			'messages'    => $messages,
			'temperature' => floatval( $options['temperature'] ),
			'max_tokens'  => intval( $options['max_tokens'] ),
		);

		// Add response format if specified
		if ( $options['response_format'] === 'json' ) {
			$body['response_format'] = array( 'type' => 'json_object' );
		}

		// Add streaming if enabled
		if ( $options['stream'] ) {
			$body['stream'] = true;
		}

		// Track start time for performance monitoring
		$start_time = microtime( true );

		try {
			// Make API request with retry logic
			$response = $this->make_api_request_with_retry(
				'/chat/completions',
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

			if ( $options['stream'] ) {
				// Handle streaming response
				$content = $this->process_stream_response( $response );
			} else {
				if ( isset( $response['choices'][0]['message']['content'] ) ) {
					$content = $response['choices'][0]['message']['content'];
				}

				if ( isset( $response['choices'][0]['finish_reason'] ) ) {
					$finish_reason = $response['choices'][0]['finish_reason'];
				}

				if ( isset( $response['usage'] ) ) {
					$usage = $response['usage'];
				}
			}

			// Parse JSON if response format was JSON
			if ( $options['response_format'] === 'json' && ! empty( $content ) ) {
				$json_content = json_decode( $content, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$content = $json_content;
				}
			}

			// Calculate costs
			$cost = $this->calculate_cost( $usage, $options['model'] );

			// Track usage
			$this->track_usage( array(
				'tokens_input'  => $usage['prompt_tokens'] ?? 0,
				'tokens_output' => $usage['completion_tokens'] ?? 0,
				'tokens_total'  => $usage['total_tokens'] ?? 0,
				'cost'          => $cost,
				'duration'      => $duration,
				'model'         => $options['model'],
			) );

			return array(
				'content'       => $content,
				'model'         => $options['model'],
				'finish_reason' => $finish_reason,
				'usage'         => $usage,
				'cost'          => $cost,
				'duration'      => $duration,
				'provider'      => $this->name,
			);

		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'OpenAI generation failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Make API request to OpenAI.
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
			throw new \Exception( sprintf( 'OpenAI API error: %s', $error_message ) );
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
					$retry_delay *= 2; // Exponential backoff
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
		// This is a simplified implementation that collects the full response
		// In production, you'd want to use a more sophisticated streaming approach

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
		// In a real implementation, this would process SSE events
		if ( isset( $response['choices'][0]['message']['content'] ) ) {
			return $response['choices'][0]['message']['content'];
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

		// Estimate tokens (rough approximation: 1 token ≈ 4 characters)
		$prompt_tokens = ceil( strlen( $prompt ) / 4 );
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
	 * @param    string    $model   Model to use for counting.
	 * @return   int                 Estimated token count.
	 */
	public function count_tokens( $text, $model = null ) {
		// Simple approximation - in production you'd use tiktoken
		// GPT models use roughly 1 token per 4 characters
		return ceil( strlen( $text ) / 4 );
	}

	/**
	 * Get settings schema for OpenAI provider.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema.
	 */
	public function get_settings_schema() {
		$schema = parent::get_settings_schema();

		// Add OpenAI-specific settings
		$schema['organization'] = array(
			'type'        => 'string',
			'label'       => __( 'Organization ID', 'wp-ai-site-generator' ),
			'description' => __( 'Optional OpenAI organization ID', 'wp-ai-site-generator' ),
			'required'    => false,
		);

		$schema['enable_streaming'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Streaming', 'wp-ai-site-generator' ),
			'description' => __( 'Stream responses for real-time generation', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['enable_function_calling'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Function Calling', 'wp-ai-site-generator' ),
			'description' => __( 'Allow models to call functions for enhanced capabilities', 'wp-ai-site-generator' ),
			'default'     => true,
		);

		return $schema;
	}

	/**
	 * Generate structured output for layouts.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       Layout generation prompt.
	 * @param    array     $schema       JSON schema for output structure.
	 * @return   array                   Structured layout data.
	 */
	public function generate_structured_output( $prompt, $schema ) {
		$system_prompt = "You are a web layout designer. Generate structured JSON output according to the provided schema.";

		$formatted_prompt = sprintf(
			"%s\n\nJSON Schema:\n%s",
			$prompt,
			wp_json_encode( $schema, JSON_PRETTY_PRINT )
		);

		$response = $this->generate(
			$formatted_prompt,
			array(
				'system_prompt'   => $system_prompt,
				'response_format' => 'json',
				'temperature'     => 0.3, // Lower temperature for structured output
			)
		);

		return $response['content'] ?? array();
	}
}