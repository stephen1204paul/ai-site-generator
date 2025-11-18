<?php
/**
 * Google Gemini Provider Implementation.
 *
 * Provides integration with Google's Gemini models for content generation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

/**
 * Google Provider Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
class Google_Provider extends Base_Provider {

	/**
	 * API endpoint.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_endpoint    Google API endpoint.
	 */
	private $api_endpoint = 'https://generativelanguage.googleapis.com/v1';

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
		$this->name         = 'google';
		$this->display_name = 'Google Gemini';
		$this->description  = 'Google\'s advanced AI models with multimodal capabilities and large context windows';

		// Define available models with metadata
		$this->models = array(
			'gemini-1.5-pro' => array(
				'name'         => 'Gemini 1.5 Pro',
				'description'  => 'Most capable model with 2M token context window',
				'context'      => 2097152, // 2M tokens
				'max_output'   => 8192,
				'cost_input'   => 0.00125, // per 1K tokens
				'cost_output'  => 0.005,   // per 1K tokens
			),
			'gemini-1.5-flash' => array(
				'name'         => 'Gemini 1.5 Flash',
				'description'  => 'Fast and efficient model for most tasks',
				'context'      => 1048576, // 1M tokens
				'max_output'   => 8192,
				'cost_input'   => 0.000075,
				'cost_output'  => 0.0003,
			),
			'gemini-1.5-flash-8b' => array(
				'name'         => 'Gemini 1.5 Flash-8B',
				'description'  => 'Smallest and fastest model',
				'context'      => 1048576,
				'max_output'   => 8192,
				'cost_input'   => 0.0000375,
				'cost_output'  => 0.00015,
			),
			'gemini-pro' => array(
				'name'         => 'Gemini Pro',
				'description'  => 'Previous generation balanced model',
				'context'      => 32768,
				'max_output'   => 2048,
				'cost_input'   => 0.0005,
				'cost_output'  => 0.0015,
			),
		);

		// Define capabilities
		$this->capabilities = array(
			'chat_completion',
			'streaming',
			'function_calling',
			'multimodal',
			'vision',
			'audio',
			'video',
			'code_execution',
			'grounding',
			'system_prompt',
			'temperature_control',
			'token_counting',
			'batch_processing',
			'long_context',
			'safety_settings',
		);

		// Define rate limits (requests per minute)
		$this->rate_limits = array(
			'gemini-1.5-pro'     => 360,
			'gemini-1.5-flash'   => 1000,
			'gemini-1.5-flash-8b' => 2000,
			'gemini-pro'         => 60,
		);

		// Set default model if not configured
		if ( empty( $this->settings['model'] ) ) {
			$this->settings['model'] = 'gemini-1.5-flash';
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
			// List available models to test connection
			$response = $this->make_api_request(
				'/models',
				array(),
				'GET'
			);

			if ( isset( $response['models'] ) && is_array( $response['models'] ) ) {
				$model_names = array_map( function( $model ) {
					return str_replace( 'models/', '', $model['name'] );
				}, $response['models'] );

				return array(
					'success' => true,
					'message' => __( 'Successfully connected to Google Gemini API', 'wp-ai-site-generator' ),
					'models'  => $model_names,
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Unable to fetch models from Google', 'wp-ai-site-generator' ),
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
	 * Generate content using Google Gemini.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for content generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated content with metadata.
	 */
	public function generate( $prompt, $options = array() ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( __( 'Google provider is not available', 'wp-ai-site-generator' ) );
		}

		$defaults = array(
			'model'           => $this->get_current_model(),
			'temperature'     => $this->settings['temperature'] ?? 0.7,
			'max_tokens'      => $this->settings['max_tokens'] ?? 4000,
			'stream'          => false,
			'system_prompt'   => null,
			'safety_settings' => 'default',
		);

		$options = wp_parse_args( $options, $defaults );

		// Build the content parts
		$parts = array();

		// Add system instruction if provided
		if ( ! empty( $options['system_prompt'] ) ) {
			$system_instruction = array(
				'text' => $options['system_prompt']
			);
		}

		// Add user prompt
		$parts[] = array(
			'text' => $prompt
		);

		// Build request body
		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => $parts,
				),
			),
			'generationConfig' => array(
				'temperature'     => floatval( $options['temperature'] ),
				'maxOutputTokens' => intval( $options['max_tokens'] ),
				'topP'            => 0.95,
				'topK'            => 40,
			),
		);

		// Add system instruction to body if set
		if ( isset( $system_instruction ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( $system_instruction )
			);
		}

		// Add safety settings
		$body['safetySettings'] = $this->get_safety_settings( $options['safety_settings'] );

		// Track start time
		$start_time = microtime( true );

		try {
			// Construct endpoint for specific model
			$endpoint = sprintf( '/models/%s:generateContent', $options['model'] );

			// Make API request with retry logic
			$response = $this->make_api_request_with_retry(
				$endpoint,
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

			if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
				$content = $response['candidates'][0]['content']['parts'][0]['text'];
			}

			if ( isset( $response['candidates'][0]['finishReason'] ) ) {
				$finish_reason = $response['candidates'][0]['finishReason'];
			}

			// Extract usage metadata if available
			if ( isset( $response['usageMetadata'] ) ) {
				$usage = array(
					'prompt_tokens'     => $response['usageMetadata']['promptTokenCount'] ?? 0,
					'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
					'total_tokens'      => $response['usageMetadata']['totalTokenCount'] ?? 0,
				);
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
					__( 'Google generation failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Make API request to Google.
	 *
	 * @since    1.0.0
	 * @param    string    $endpoint    API endpoint.
	 * @param    array     $body        Request body.
	 * @param    string    $method      HTTP method.
	 * @return   array                  API response.
	 */
	private function make_api_request( $endpoint, $body = array(), $method = 'POST' ) {
		// Append API key to URL
		$url = $this->api_endpoint . $endpoint;
		$url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . 'key=' . $this->settings['api_key'];

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Content-Type' => 'application/json',
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
			$error_message = 'Unknown error';
			if ( isset( $response_data['error']['message'] ) ) {
				$error_message = $response_data['error']['message'];
			}

			// Handle specific error codes
			if ( $response_code === 400 ) {
				throw new \Exception( sprintf( 'Bad request: %s', $error_message ) );
			} elseif ( $response_code === 401 ) {
				throw new \Exception( 'Authentication failed. Please check your API key.' );
			} elseif ( $response_code === 403 ) {
				throw new \Exception( 'Permission denied. Please check your API key permissions.' );
			} elseif ( $response_code === 429 ) {
				throw new \Exception( 'Rate limit exceeded. Please try again later.' );
			} else {
				throw new \Exception( sprintf( 'Google API error: %s', $error_message ) );
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
			'503',
			'504',
			'resource exhausted',
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
		// Google supports streaming through streamGenerateContent endpoint
		$stream_endpoint = str_replace( ':generateContent', ':streamGenerateContent', $endpoint );

		// For now, fall back to non-streaming
		return $this->make_api_request( $endpoint, $body, 'POST' );
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
		// Google uses roughly 1 token per 4 characters
		return ceil( strlen( $text ) / 4 );
	}

	/**
	 * Get safety settings for content generation.
	 *
	 * @since    1.0.0
	 * @param    string    $level    Safety level.
	 * @return   array               Safety settings array.
	 */
	private function get_safety_settings( $level = 'default' ) {
		$categories = array(
			'HARM_CATEGORY_HARASSMENT',
			'HARM_CATEGORY_HATE_SPEECH',
			'HARM_CATEGORY_SEXUALLY_EXPLICIT',
			'HARM_CATEGORY_DANGEROUS_CONTENT',
		);

		$threshold = 'BLOCK_MEDIUM_AND_ABOVE'; // Default

		if ( $level === 'relaxed' ) {
			$threshold = 'BLOCK_ONLY_HIGH';
		} elseif ( $level === 'strict' ) {
			$threshold = 'BLOCK_LOW_AND_ABOVE';
		}

		$settings = array();
		foreach ( $categories as $category ) {
			$settings[] = array(
				'category'  => $category,
				'threshold' => $threshold,
			);
		}

		return $settings;
	}

	/**
	 * Get settings schema for Google provider.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema.
	 */
	public function get_settings_schema() {
		$schema = parent::get_settings_schema();

		// Add Google-specific settings
		$schema['enable_grounding'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Grounding', 'wp-ai-site-generator' ),
			'description' => __( 'Ground responses with Google Search data', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['enable_code_execution'] = array(
			'type'        => 'checkbox',
			'label'       => __( 'Enable Code Execution', 'wp-ai-site-generator' ),
			'description' => __( 'Allow models to execute code for computations', 'wp-ai-site-generator' ),
			'default'     => false,
		);

		$schema['safety_level'] = array(
			'type'        => 'select',
			'label'       => __( 'Safety Level', 'wp-ai-site-generator' ),
			'description' => __( 'Content safety filtering level', 'wp-ai-site-generator' ),
			'options'     => array(
				'default' => __( 'Default', 'wp-ai-site-generator' ),
				'relaxed' => __( 'Relaxed', 'wp-ai-site-generator' ),
				'strict'  => __( 'Strict', 'wp-ai-site-generator' ),
			),
			'default'     => 'default',
		);

		return $schema;
	}

	/**
	 * Generate with function calling.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for generation.
	 * @param    array     $functions    Array of function definitions.
	 * @return   array                   Generated response with function calls.
	 */
	public function generate_with_functions( $prompt, $functions ) {
		// Build function declarations
		$function_declarations = array();
		foreach ( $functions as $function ) {
			$function_declarations[] = array(
				'name'        => $function['name'],
				'description' => $function['description'],
				'parameters'  => $function['parameters'],
			);
		}

		// Build request body with function declarations
		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
			'tools' => array(
				array(
					'functionDeclarations' => $function_declarations,
				),
			),
			'generationConfig' => array(
				'temperature'     => 0.5,
				'maxOutputTokens' => 2048,
			),
		);

		try {
			$endpoint = sprintf( '/models/%s:generateContent', $this->get_current_model() );
			$response = $this->make_api_request( $endpoint, $body );

			// Extract function calls from response
			$function_calls = array();
			if ( isset( $response['candidates'][0]['content']['parts'] ) ) {
				foreach ( $response['candidates'][0]['content']['parts'] as $part ) {
					if ( isset( $part['functionCall'] ) ) {
						$function_calls[] = $part['functionCall'];
					}
				}
			}

			return array(
				'content'        => $response['candidates'][0]['content']['parts'][0]['text'] ?? '',
				'function_calls' => $function_calls,
				'provider'       => $this->name,
			);

		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: Error message */
					__( 'Function calling failed: %s', 'wp-ai-site-generator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Process multimodal content (images, etc.).
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       Text prompt.
	 * @param    array     $media        Array of media items.
	 * @return   array                   Generated response.
	 */
	public function process_multimodal( $prompt, $media ) {
		$parts = array(
			array( 'text' => $prompt ),
		);

		// Add media parts
		foreach ( $media as $item ) {
			if ( $item['type'] === 'image' ) {
				$parts[] = array(
					'inlineData' => array(
						'mimeType' => $item['mime_type'],
						'data'     => base64_encode( $item['data'] ),
					),
				);
			}
		}

		return $this->generate( '', array(
			'parts' => $parts,
		) );
	}
}