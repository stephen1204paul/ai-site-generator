<?php
/**
 * Base AI Provider Class.
 *
 * Abstract base class for AI provider implementations.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

use WPAISiteGenerator\Database\DB_Handler;

/**
 * Base AI Provider Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
abstract class Base_Provider implements AI_Provider_Interface {

	/**
	 * Provider name.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $name    Provider name.
	 */
	protected $name;

	/**
	 * Provider display name.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $display_name    Provider display name.
	 */
	protected $display_name;

	/**
	 * Provider description.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $description    Provider description.
	 */
	protected $description;

	/**
	 * Provider settings.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $settings    Provider settings.
	 */
	protected $settings;

	/**
	 * API client.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      mixed    $client    API client instance.
	 */
	protected $client;

	/**
	 * Current model.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $model    Current model.
	 */
	protected $model;

	/**
	 * Available models.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $models    Available models.
	 */
	protected $models = array();

	/**
	 * Provider capabilities.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $capabilities    Provider capabilities.
	 */
	protected $capabilities = array();

	/**
	 * Rate limits.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $rate_limits    Rate limits.
	 */
	protected $rate_limits = array();

	/**
	 * Database handler.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      DB_Handler    $db_handler    Database handler.
	 */
	protected $db_handler;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_settings();
		$this->initialize();
		$this->db_handler = new DB_Handler();
	}

	/**
	 * Initialize provider.
	 *
	 * @since    1.0.0
	 */
	abstract protected function initialize();

	/**
	 * Load provider settings.
	 *
	 * @since    1.0.0
	 */
	protected function load_settings() {
		$provider_settings = get_option( 'waisg_provider_settings', array() );

		if ( isset( $provider_settings['providers'][ $this->name ] ) ) {
			$this->settings = $provider_settings['providers'][ $this->name ];
		} else {
			$this->settings = $this->get_default_settings();
		}
	}

	/**
	 * Get default settings.
	 *
	 * @since    1.0.0
	 * @return   array    Default settings.
	 */
	protected function get_default_settings() {
		return array(
			'enabled'     => false,
			'api_key'     => '',
			'model'       => '',
			'temperature' => 0.7,
			'max_tokens'  => 4000,
			'timeout'     => 30,
		);
	}

	/**
	 * Get provider name.
	 *
	 * @since    1.0.0
	 * @return   string    Provider name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get provider display name.
	 *
	 * @since    1.0.0
	 * @return   string    Provider display name.
	 */
	public function get_display_name() {
		return $this->display_name;
	}

	/**
	 * Get provider description.
	 *
	 * @since    1.0.0
	 * @return   string    Provider description.
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get provider icon URL.
	 *
	 * @since    1.0.0
	 * @return   string    Provider icon URL.
	 */
	public function get_icon_url() {
		return WAISG_PLUGIN_URL . 'assets/images/providers/' . $this->name . '.png';
	}

	/**
	 * Check if provider is available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if available, false otherwise.
	 */
	public function is_available() {
		return ! empty( $this->settings['enabled'] ) && ! empty( $this->settings['api_key'] );
	}

	/**
	 * Generate multiple content items in batch.
	 *
	 * @since    1.0.0
	 * @param    array     $prompts      Array of prompts.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Array of generated content.
	 */
	public function generate_batch( $prompts, $options = array() ) {
		$results = array();

		foreach ( $prompts as $prompt ) {
			try {
				$results[] = $this->generate( $prompt, $options );
			} catch ( \Exception $e ) {
				$results[] = array(
					'error'   => true,
					'message' => $e->getMessage(),
					'prompt'  => $prompt,
				);
			}
		}

		return $results;
	}

	/**
	 * Generate a website structure.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for site generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated site structure.
	 */
	public function generate_site_structure( $prompt, $options = array() ) {
		$system_prompt = $this->get_system_prompt( 'site_structure' );
		$formatted_prompt = $this->format_prompt( $prompt, 'site_structure' );

		$response = $this->generate(
			$formatted_prompt,
			array_merge(
				$options,
				array(
					'system_prompt' => $system_prompt,
					'response_format' => 'json',
				)
			)
		);

		return $this->parse_site_structure( $response );
	}

	/**
	 * Generate page content.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for page generation.
	 * @param    string    $page_type    Type of page.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated page content.
	 */
	public function generate_page_content( $prompt, $page_type, $options = array() ) {
		$system_prompt = $this->get_system_prompt( 'page_content' );
		$formatted_prompt = $this->format_prompt( $prompt, 'page_content', array( 'page_type' => $page_type ) );

		$response = $this->generate(
			$formatted_prompt,
			array_merge(
				$options,
				array(
					'system_prompt' => $system_prompt,
				)
			)
		);

		return $this->parse_page_content( $response );
	}

	/**
	 * Generate a block.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for block generation.
	 * @param    string    $block_type   Type of block to generate.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated block content.
	 */
	public function generate_block( $prompt, $block_type, $options = array() ) {
		$system_prompt = $this->get_system_prompt( 'block' );
		$formatted_prompt = $this->format_prompt( $prompt, 'block', array( 'block_type' => $block_type ) );

		$response = $this->generate(
			$formatted_prompt,
			array_merge(
				$options,
				array(
					'system_prompt' => $system_prompt,
				)
			)
		);

		return $this->parse_block_content( $response, $block_type );
	}

	/**
	 * Generate metadata for SEO.
	 *
	 * @since    1.0.0
	 * @param    string    $content      The content to generate metadata for.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated metadata.
	 */
	public function generate_meta( $content, $options = array() ) {
		$system_prompt = $this->get_system_prompt( 'meta' );
		$formatted_prompt = $this->format_prompt( $content, 'meta' );

		$response = $this->generate(
			$formatted_prompt,
			array_merge(
				$options,
				array(
					'system_prompt'   => $system_prompt,
					'response_format' => 'json',
					'max_tokens'      => 500,
				)
			)
		);

		return $this->parse_meta_content( $response );
	}

	/**
	 * Optimize generated content.
	 *
	 * @since    1.0.0
	 * @param    string    $content      The content to optimize.
	 * @param    string    $type         Type of optimization.
	 * @param    array     $options      Optional. Optimization options.
	 * @return   string                  Optimized content.
	 */
	public function optimize_content( $content, $type = 'general', $options = array() ) {
		$system_prompt = $this->get_system_prompt( 'optimize_' . $type );
		$formatted_prompt = sprintf(
			"Optimize the following content for %s:\n\n%s",
			$type,
			$content
		);

		$response = $this->generate(
			$formatted_prompt,
			array_merge(
				$options,
				array(
					'system_prompt' => $system_prompt,
				)
			)
		);

		return $response['content'] ?? $content;
	}

	/**
	 * Get available models.
	 *
	 * @since    1.0.0
	 * @return   array    Array of available models.
	 */
	public function get_available_models() {
		return $this->models;
	}

	/**
	 * Get current model.
	 *
	 * @since    1.0.0
	 * @return   string    Current model identifier.
	 */
	public function get_current_model() {
		return $this->model ?? $this->settings['model'] ?? '';
	}

	/**
	 * Set model to use.
	 *
	 * @since    1.0.0
	 * @param    string    $model    Model identifier.
	 * @return   bool                True if model was set, false otherwise.
	 */
	public function set_model( $model ) {
		if ( in_array( $model, array_keys( $this->models ), true ) ) {
			$this->model = $model;
			return true;
		}
		return false;
	}

	/**
	 * Get provider capabilities.
	 *
	 * @since    1.0.0
	 * @return   array    Array of capabilities.
	 */
	public function get_capabilities() {
		return $this->capabilities;
	}

	/**
	 * Check if provider supports a capability.
	 *
	 * @since    1.0.0
	 * @param    string    $capability    Capability to check.
	 * @return   bool                     True if supported, false otherwise.
	 */
	public function supports( $capability ) {
		return in_array( $capability, $this->capabilities, true );
	}

	/**
	 * Get usage statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Usage statistics.
	 */
	public function get_usage_stats() {
		return $this->db_handler->get_provider_usage( $this->name );
	}

	/**
	 * Get rate limits.
	 *
	 * @since    1.0.0
	 * @return   array    Rate limit information.
	 */
	public function get_rate_limits() {
		return $this->rate_limits;
	}

	/**
	 * Get provider settings schema.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema for provider configuration.
	 */
	public function get_settings_schema() {
		return array(
			'api_key' => array(
				'type'        => 'string',
				'label'       => __( 'API Key', 'wp-ai-site-generator' ),
				'description' => __( 'Your API key for this provider', 'wp-ai-site-generator' ),
				'required'    => true,
				'sensitive'   => true,
			),
			'model' => array(
				'type'        => 'select',
				'label'       => __( 'Model', 'wp-ai-site-generator' ),
				'description' => __( 'AI model to use', 'wp-ai-site-generator' ),
				'options'     => $this->models,
				'default'     => array_key_first( $this->models ),
			),
			'temperature' => array(
				'type'        => 'number',
				'label'       => __( 'Temperature', 'wp-ai-site-generator' ),
				'description' => __( 'Controls randomness (0.0 to 1.0)', 'wp-ai-site-generator' ),
				'min'         => 0,
				'max'         => 1,
				'step'        => 0.1,
				'default'     => 0.7,
			),
			'max_tokens' => array(
				'type'        => 'number',
				'label'       => __( 'Max Tokens', 'wp-ai-site-generator' ),
				'description' => __( 'Maximum tokens to generate', 'wp-ai-site-generator' ),
				'min'         => 100,
				'max'         => 8000,
				'default'     => 4000,
			),
		);
	}

	/**
	 * Validate provider settings.
	 *
	 * @since    1.0.0
	 * @param    array     $settings    Settings to validate.
	 * @return   array                  Validation result.
	 */
	public function validate_settings( $settings ) {
		$errors = array();
		$schema = $this->get_settings_schema();

		foreach ( $schema as $key => $field ) {
			if ( ! empty( $field['required'] ) && empty( $settings[ $key ] ) ) {
				$errors[ $key ] = sprintf(
					/* translators: %s: Field label */
					__( '%s is required', 'wp-ai-site-generator' ),
					$field['label']
				);
			}

			if ( isset( $settings[ $key ] ) && isset( $field['type'] ) ) {
				switch ( $field['type'] ) {
					case 'number':
						if ( ! is_numeric( $settings[ $key ] ) ) {
							$errors[ $key ] = sprintf(
								/* translators: %s: Field label */
								__( '%s must be a number', 'wp-ai-site-generator' ),
								$field['label']
							);
						} elseif ( isset( $field['min'] ) && $settings[ $key ] < $field['min'] ) {
							$errors[ $key ] = sprintf(
								/* translators: 1: Field label, 2: Minimum value */
								__( '%1$s must be at least %2$s', 'wp-ai-site-generator' ),
								$field['label'],
								$field['min']
							);
						} elseif ( isset( $field['max'] ) && $settings[ $key ] > $field['max'] ) {
							$errors[ $key ] = sprintf(
								/* translators: 1: Field label, 2: Maximum value */
								__( '%1$s must be at most %2$s', 'wp-ai-site-generator' ),
								$field['label'],
								$field['max']
							);
						}
						break;

					case 'select':
						if ( ! in_array( $settings[ $key ], array_keys( $field['options'] ), true ) ) {
							$errors[ $key ] = sprintf(
								/* translators: %s: Field label */
								__( 'Invalid value for %s', 'wp-ai-site-generator' ),
								$field['label']
							);
						}
						break;
				}
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * Get provider-specific prompt template.
	 *
	 * @since    1.0.0
	 * @param    string    $type        Template type.
	 * @return   string                 Prompt template.
	 */
	public function get_prompt_template( $type ) {
		$templates = $this->get_prompt_templates();
		return $templates[ $type ] ?? '';
	}

	/**
	 * Get all prompt templates.
	 *
	 * @since    1.0.0
	 * @return   array    Prompt templates.
	 */
	protected function get_prompt_templates() {
		return array(
			'site_structure' => 'Generate a website structure for: {prompt}',
			'page_content'   => 'Create content for a {page_type} page: {prompt}',
			'block'          => 'Generate a {block_type} block: {prompt}',
			'meta'           => 'Generate SEO metadata for this content: {content}',
			'optimize_seo'   => 'Optimize this content for SEO: {content}',
		);
	}

	/**
	 * Format prompt for provider.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt      The raw prompt.
	 * @param    string    $type        Type of content being generated.
	 * @param    array     $context     Additional context for prompt.
	 * @return   string                 Formatted prompt.
	 */
	public function format_prompt( $prompt, $type = 'general', $context = array() ) {
		$template = $this->get_prompt_template( $type );

		if ( $template ) {
			$replacements = array_merge(
				array(
					'{prompt}' => $prompt,
					'{type}'   => $type,
				),
				$context
			);

			foreach ( $replacements as $key => $value ) {
				$template = str_replace( $key, $value, $template );
			}

			return $template;
		}

		return $prompt;
	}

	/**
	 * Handle provider-specific errors.
	 *
	 * @since    1.0.0
	 * @param    \Exception    $exception    The exception to handle.
	 * @return   array                       Formatted error response.
	 */
	public function handle_error( $exception ) {
		$error_type = 'general_error';
		$user_message = __( 'An error occurred while processing your request.', 'wp-ai-site-generator' );

		// Log the error
		error_log( sprintf(
			'WP AI Site Generator - Provider Error (%s): %s',
			$this->name,
			$exception->getMessage()
		) );

		// Track error in database
		$this->db_handler->log_provider_error(
			$this->name,
			$exception->getMessage(),
			$exception->getTrace()
		);

		return array(
			'error'      => true,
			'type'       => $error_type,
			'message'    => $user_message,
			'details'    => WP_DEBUG ? $exception->getMessage() : null,
			'provider'   => $this->name,
			'timestamp'  => current_time( 'mysql' ),
		);
	}

	/**
	 * Get provider health status.
	 *
	 * @since    1.0.0
	 * @return   array    Health status information.
	 */
	public function get_health_status() {
		$status = array(
			'provider'    => $this->name,
			'available'   => $this->is_available(),
			'configured'  => ! empty( $this->settings['api_key'] ),
			'last_check'  => current_time( 'mysql' ),
		);

		if ( $status['available'] ) {
			try {
				$test_result = $this->test_connection();
				$status['healthy'] = $test_result['success'];
				$status['message'] = $test_result['message'];
			} catch ( \Exception $e ) {
				$status['healthy'] = false;
				$status['message'] = $e->getMessage();
			}
		} else {
			$status['healthy'] = false;
			$status['message'] = __( 'Provider is not configured', 'wp-ai-site-generator' );
		}

		// Get recent usage stats
		$status['usage'] = $this->get_usage_stats();

		// Get rate limit status
		$status['rate_limits'] = $this->get_rate_limits();

		return $status;
	}

	/**
	 * Get system prompt for specific generation type.
	 *
	 * @since    1.0.0
	 * @param    string    $type    Generation type.
	 * @return   string             System prompt.
	 */
	protected function get_system_prompt( $type ) {
		$prompts = array(
			'site_structure' => 'You are a professional web designer and developer. Generate structured website content.',
			'page_content'   => 'You are a professional content writer. Create engaging and SEO-optimized page content.',
			'block'          => 'You are a WordPress block editor expert. Generate properly formatted block content.',
			'meta'           => 'You are an SEO expert. Generate optimized metadata for web content.',
			'optimize_seo'   => 'You are an SEO specialist. Optimize content for search engines.',
		);

		return $prompts[ $type ] ?? 'You are a helpful AI assistant.';
	}

	/**
	 * Parse site structure response.
	 *
	 * @since    1.0.0
	 * @param    array     $response    AI response.
	 * @return   array                  Parsed site structure.
	 */
	protected function parse_site_structure( $response ) {
		// Override in specific provider implementations
		return $response;
	}

	/**
	 * Parse page content response.
	 *
	 * @since    1.0.0
	 * @param    array     $response    AI response.
	 * @return   array                  Parsed page content.
	 */
	protected function parse_page_content( $response ) {
		// Override in specific provider implementations
		return $response;
	}

	/**
	 * Parse block content response.
	 *
	 * @since    1.0.0
	 * @param    array     $response       AI response.
	 * @param    string    $block_type    Block type.
	 * @return   array                    Parsed block content.
	 */
	protected function parse_block_content( $response, $block_type ) {
		// Override in specific provider implementations
		return $response;
	}

	/**
	 * Parse meta content response.
	 *
	 * @since    1.0.0
	 * @param    array     $response    AI response.
	 * @return   array                  Parsed meta content.
	 */
	protected function parse_meta_content( $response ) {
		// Override in specific provider implementations
		return $response;
	}

	/**
	 * Track API usage.
	 *
	 * @since    1.0.0
	 * @param    array    $usage_data    Usage data to track.
	 */
	protected function track_usage( $usage_data ) {
		$this->db_handler->track_provider_usage(
			$this->name,
			$this->get_current_model(),
			$usage_data
		);
	}
}