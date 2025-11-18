<?php
/**
 * Provider Manager Class.
 *
 * Manages all AI providers and their operations.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

/**
 * Provider Manager Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
class Provider_Manager {

	/**
	 * Registered providers.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $providers    Registered providers.
	 */
	private $providers = array();

	/**
	 * Active provider.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      AI_Provider_Interface    $active_provider    Active provider instance.
	 */
	private $active_provider = null;

	/**
	 * Provider settings.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $settings    Provider settings.
	 */
	private $settings;

	/**
	 * Provider health status cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $health_cache    Cached health status.
	 */
	private $health_cache = array();

	/**
	 * Fallback priority order.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $fallback_order    Order of providers for fallback.
	 */
	private $fallback_order = array( 'openai', 'anthropic', 'google', 'cohere' );

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_settings();
		$this->register_providers();
		$this->set_active_provider();
	}

	/**
	 * Load provider settings.
	 *
	 * @since    1.0.0
	 */
	private function load_settings() {
		$this->settings = get_option( 'waisg_provider_settings', array(
			'default_provider' => 'openai',
			'fallback_enabled' => true,
			'providers'        => array(),
		) );
	}

	/**
	 * Register all available providers.
	 *
	 * @since    1.0.0
	 */
	private function register_providers() {
		// Register built-in providers
		$this->register_builtin_providers();

		// Allow third-party providers to register
		do_action( 'waisg_register_providers', $this );
	}

	/**
	 * Register built-in providers.
	 *
	 * @since    1.0.0
	 */
	private function register_builtin_providers() {
		$builtin_providers = array(
			'openai'    => 'OpenAI_Provider',
			'anthropic' => 'Anthropic_Provider',
			'google'    => 'Google_Provider',
			'cohere'    => 'Cohere_Provider',
		);

		foreach ( $builtin_providers as $name => $class ) {
			$file = plugin_dir_path( dirname( __FILE__ ) ) . 'providers/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
				$full_class = __NAMESPACE__ . '\\' . $class;

				if ( class_exists( $full_class ) ) {
					$this->register_provider( new $full_class() );
				}
			}
		}
	}

	/**
	 * Register a provider.
	 *
	 * @since    1.0.0
	 * @param    AI_Provider_Interface    $provider    Provider instance.
	 */
	public function register_provider( AI_Provider_Interface $provider ) {
		$this->providers[ $provider->get_name() ] = $provider;
	}

	/**
	 * Set the active provider.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Optional. Provider name to set as active.
	 * @return   bool                        True if set, false otherwise.
	 */
	public function set_active_provider( $provider_name = null ) {
		if ( ! $provider_name ) {
			$provider_name = $this->settings['default_provider'] ?? 'openai';
		}

		if ( isset( $this->providers[ $provider_name ] ) && $this->providers[ $provider_name ]->is_available() ) {
			$this->active_provider = $this->providers[ $provider_name ];
			return true;
		}

		// Try fallback if enabled
		if ( $this->settings['fallback_enabled'] ) {
			foreach ( $this->providers as $provider ) {
				if ( $provider->is_available() ) {
					$this->active_provider = $provider;
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get the active provider.
	 *
	 * @since    1.0.0
	 * @return   AI_Provider_Interface|null    Active provider or null.
	 */
	public function get_active_provider() {
		return $this->active_provider;
	}

	/**
	 * Get all registered providers.
	 *
	 * @since    1.0.0
	 * @return   array    Array of provider instances.
	 */
	public function get_all_providers() {
		return $this->providers;
	}

	/**
	 * Get provider by name.
	 *
	 * @since    1.0.0
	 * @param    string    $name    Provider name.
	 * @return   AI_Provider_Interface|null    Provider instance or null.
	 */
	public function get_provider( $name ) {
		return $this->providers[ $name ] ?? null;
	}

	/**
	 * Get available providers.
	 *
	 * @since    1.0.0
	 * @return   array    Array of available providers.
	 */
	public function get_available_providers() {
		$available = array();

		foreach ( $this->providers as $name => $provider ) {
			if ( $provider->is_available() ) {
				$available[ $name ] = array(
					'name'         => $provider->get_name(),
					'display_name' => $provider->get_display_name(),
					'description'  => $provider->get_description(),
					'icon_url'     => $provider->get_icon_url(),
					'models'       => $provider->get_available_models(),
					'capabilities' => $provider->get_capabilities(),
				);
			}
		}

		return $available;
	}

	/**
	 * Check if a provider is available.
	 *
	 * @since    1.0.0
	 * @param    string    $name    Provider name.
	 * @return   bool              True if available, false otherwise.
	 */
	public function is_provider_available( $name ) {
		if ( isset( $this->providers[ $name ] ) ) {
			return $this->providers[ $name ]->is_available();
		}
		return false;
	}

	/**
	 * Test a provider connection.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Provider name.
	 * @return   array                       Test result.
	 */
	public function test_provider( $provider_name ) {
		if ( ! isset( $this->providers[ $provider_name ] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Provider not found', 'wp-ai-site-generator' ),
			);
		}

		try {
			$result = $this->providers[ $provider_name ]->test_connection();
			return $result;
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Generate content using active provider.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for generation.
	 * @param    array     $options      Optional. Generation options.
	 * @param    string    $provider     Optional. Specific provider to use.
	 * @return   array                   Generated content.
	 * @throws   \Exception              If no provider is available.
	 */
	public function generate( $prompt, $options = array(), $provider = null ) {
		$provider_instance = $this->get_provider_for_generation( $provider );

		if ( ! $provider_instance ) {
			throw new \Exception( __( 'No AI provider available for generation', 'wp-ai-site-generator' ) );
		}

		try {
			return $provider_instance->generate( $prompt, $options );
		} catch ( \Exception $e ) {
			// Try fallback provider if enabled
			if ( $this->settings['fallback_enabled'] && $provider_instance !== $this->active_provider ) {
				return $this->active_provider->generate( $prompt, $options );
			}
			throw $e;
		}
	}

	/**
	 * Generate site structure.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for generation.
	 * @param    array     $options      Optional. Generation options.
	 * @param    string    $provider     Optional. Specific provider to use.
	 * @return   array                   Generated site structure.
	 */
	public function generate_site_structure( $prompt, $options = array(), $provider = null ) {
		$provider_instance = $this->get_provider_for_generation( $provider );

		if ( ! $provider_instance ) {
			throw new \Exception( __( 'No AI provider available for generation', 'wp-ai-site-generator' ) );
		}

		return $provider_instance->generate_site_structure( $prompt, $options );
	}

	/**
	 * Generate page content.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for generation.
	 * @param    string    $page_type    Type of page.
	 * @param    array     $options      Optional. Generation options.
	 * @param    string    $provider     Optional. Specific provider to use.
	 * @return   array                   Generated page content.
	 */
	public function generate_page_content( $prompt, $page_type, $options = array(), $provider = null ) {
		$provider_instance = $this->get_provider_for_generation( $provider );

		if ( ! $provider_instance ) {
			throw new \Exception( __( 'No AI provider available for generation', 'wp-ai-site-generator' ) );
		}

		return $provider_instance->generate_page_content( $prompt, $page_type, $options );
	}

	/**
	 * Generate block content.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for generation.
	 * @param    string    $block_type   Type of block.
	 * @param    array     $options      Optional. Generation options.
	 * @param    string    $provider     Optional. Specific provider to use.
	 * @return   array                   Generated block content.
	 */
	public function generate_block( $prompt, $block_type, $options = array(), $provider = null ) {
		$provider_instance = $this->get_provider_for_generation( $provider );

		if ( ! $provider_instance ) {
			throw new \Exception( __( 'No AI provider available for generation', 'wp-ai-site-generator' ) );
		}

		return $provider_instance->generate_block( $prompt, $block_type, $options );
	}

	/**
	 * Get provider for generation.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Optional. Specific provider name.
	 * @return   AI_Provider_Interface|null    Provider instance or null.
	 */
	private function get_provider_for_generation( $provider_name = null ) {
		if ( $provider_name && isset( $this->providers[ $provider_name ] ) ) {
			if ( $this->providers[ $provider_name ]->is_available() ) {
				return $this->providers[ $provider_name ];
			}
		}

		return $this->active_provider;
	}

	/**
	 * Check all providers' health.
	 *
	 * @since    1.0.0
	 * @return   array    Health status for all providers.
	 */
	public function check_providers_health() {
		$health_status = array();

		foreach ( $this->providers as $name => $provider ) {
			$health_status[ $name ] = $provider->get_health_status();
		}

		// Store health check results
		set_transient( 'waisg_providers_health', $health_status, HOUR_IN_SECONDS );

		return $health_status;
	}

	/**
	 * Get provider statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Statistics for all providers.
	 */
	public function get_provider_statistics() {
		$statistics = array();

		foreach ( $this->providers as $name => $provider ) {
			$statistics[ $name ] = array(
				'name'        => $provider->get_display_name(),
				'available'   => $provider->is_available(),
				'usage'       => $provider->get_usage_stats(),
				'rate_limits' => $provider->get_rate_limits(),
			);
		}

		return $statistics;
	}

	/**
	 * Update provider settings.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Provider name.
	 * @param    array     $settings         New settings.
	 * @return   bool                        True if updated, false otherwise.
	 */
	public function update_provider_settings( $provider_name, $settings ) {
		if ( ! isset( $this->providers[ $provider_name ] ) ) {
			return false;
		}

		// Validate settings
		$validation = $this->providers[ $provider_name ]->validate_settings( $settings );

		if ( ! $validation['valid'] ) {
			return false;
		}

		// Update settings
		$this->settings['providers'][ $provider_name ] = $settings;
		update_option( 'waisg_provider_settings', $this->settings );

		// Reinitialize provider
		$this->providers[ $provider_name ] = null;
		$this->register_builtin_providers();

		return true;
	}

	/**
	 * Get provider settings schema.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema for all providers.
	 */
	public function get_settings_schema() {
		$schema = array();

		foreach ( $this->providers as $name => $provider ) {
			$schema[ $name ] = $provider->get_settings_schema();
		}

		return $schema;
	}

	/**
	 * Estimate cost for generation.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt.
	 * @param    array     $options      Generation options.
	 * @param    string    $provider     Optional. Specific provider.
	 * @return   float                   Estimated cost.
	 */
	public function estimate_cost( $prompt, $options = array(), $provider = null ) {
		$provider_instance = $this->get_provider_for_generation( $provider );

		if ( ! $provider_instance ) {
			return 0.0;
		}

		return $provider_instance->estimate_cost( $prompt, $options );
	}

	/**
	 * Optimize content using active provider.
	 *
	 * @since    1.0.0
	 * @param    string    $content      Content to optimize.
	 * @param    string    $type         Optimization type.
	 * @param    array     $options      Optional. Optimization options.
	 * @param    string    $provider     Optional. Specific provider to use.
	 * @return   string                  Optimized content.
	 */
	public function optimize_content( $content, $type = 'general', $options = array(), $provider = null ) {
		$provider_instance = $this->get_provider_for_generation( $provider );

		if ( ! $provider_instance ) {
			throw new \Exception( __( 'No AI provider available for optimization', 'wp-ai-site-generator' ) );
		}

		return $provider_instance->optimize_content( $content, $type, $options );
	}

	/**
	 * Execute generation with automatic fallback.
	 *
	 * @since    1.0.0
	 * @param    callable  $generation_callback    Callback for generation.
	 * @param    array     $providers             Optional. Providers to try.
	 * @return   mixed                           Generation result.
	 */
	private function execute_with_fallback( $generation_callback, $providers = null ) {
		if ( ! $providers ) {
			$providers = $this->get_fallback_chain();
		}

		$last_exception = null;
		$attempted_providers = array();

		foreach ( $providers as $provider_name ) {
			if ( ! isset( $this->providers[ $provider_name ] ) ) {
				continue;
			}

			$provider = $this->providers[ $provider_name ];

			if ( ! $provider->is_available() ) {
				continue;
			}

			$attempted_providers[] = $provider_name;

			try {
				// Execute the generation
				$result = call_user_func( $generation_callback, $provider );

				// Log successful generation
				$this->log_provider_usage( $provider_name, 'success' );

				return $result;

			} catch ( \Exception $e ) {
				$last_exception = $e;

				// Log failure
				$this->log_provider_usage( $provider_name, 'failure', $e->getMessage() );

				// Check if error is non-retryable
				if ( $this->is_non_retryable_error( $e->getMessage() ) ) {
					throw $e;
				}

				// Continue to next provider
				continue;
			}
		}

		// All providers failed
		if ( $last_exception ) {
			throw new \Exception(
				sprintf(
					/* translators: 1: Attempted providers, 2: Error message */
					__( 'All providers failed. Attempted: %1$s. Last error: %2$s', 'wp-ai-site-generator' ),
					implode( ', ', $attempted_providers ),
					$last_exception->getMessage()
				)
			);
		}

		throw new \Exception( __( 'No providers available for generation', 'wp-ai-site-generator' ) );
	}

	/**
	 * Get fallback chain of providers.
	 *
	 * @since    1.0.0
	 * @return   array    Ordered list of provider names for fallback.
	 */
	private function get_fallback_chain() {
		$chain = array();

		// Start with active provider
		if ( $this->active_provider ) {
			$chain[] = $this->active_provider->get_name();
		}

		// Add fallback providers based on health and priority
		foreach ( $this->fallback_order as $provider_name ) {
			if ( ! in_array( $provider_name, $chain ) && $this->is_provider_healthy( $provider_name ) ) {
				$chain[] = $provider_name;
			}
		}

		return $chain;
	}

	/**
	 * Check if a provider is healthy.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Provider name.
	 * @return   bool                        True if healthy, false otherwise.
	 */
	private function is_provider_healthy( $provider_name ) {
		// Check cache first
		if ( isset( $this->health_cache[ $provider_name ] ) ) {
			$cached = $this->health_cache[ $provider_name ];
			if ( $cached['timestamp'] > time() - 300 ) { // 5 minute cache
				return $cached['healthy'];
			}
		}

		// Check provider availability
		if ( ! isset( $this->providers[ $provider_name ] ) ) {
			return false;
		}

		$provider = $this->providers[ $provider_name ];

		if ( ! $provider->is_available() ) {
			$this->health_cache[ $provider_name ] = array(
				'healthy'   => false,
				'timestamp' => time(),
			);
			return false;
		}

		// Get recent error rate
		$error_rate = $this->get_provider_error_rate( $provider_name );

		// Consider unhealthy if error rate > 50%
		$healthy = $error_rate < 0.5;

		$this->health_cache[ $provider_name ] = array(
			'healthy'   => $healthy,
			'timestamp' => time(),
			'error_rate' => $error_rate,
		);

		return $healthy;
	}

	/**
	 * Get provider error rate.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Provider name.
	 * @return   float                       Error rate (0.0 to 1.0).
	 */
	private function get_provider_error_rate( $provider_name ) {
		$stats = get_transient( 'waisg_provider_stats_' . $provider_name );

		if ( ! $stats || ! isset( $stats['requests'] ) ) {
			return 0.0;
		}

		$total = $stats['requests']['total'] ?? 0;
		$failures = $stats['requests']['failures'] ?? 0;

		if ( $total == 0 ) {
			return 0.0;
		}

		return $failures / $total;
	}

	/**
	 * Log provider usage.
	 *
	 * @since    1.0.0
	 * @param    string    $provider_name    Provider name.
	 * @param    string    $status          Status (success/failure).
	 * @param    string    $error           Optional. Error message.
	 */
	private function log_provider_usage( $provider_name, $status, $error = null ) {
		$stats = get_transient( 'waisg_provider_stats_' . $provider_name );

		if ( ! $stats ) {
			$stats = array(
				'requests' => array(
					'total'    => 0,
					'success'  => 0,
					'failures' => 0,
				),
				'errors' => array(),
			);
		}

		$stats['requests']['total']++;

		if ( $status === 'success' ) {
			$stats['requests']['success']++;
		} else {
			$stats['requests']['failures']++;
			if ( $error ) {
				$stats['errors'][] = array(
					'message'   => $error,
					'timestamp' => current_time( 'mysql' ),
				);
				// Keep only last 10 errors
				$stats['errors'] = array_slice( $stats['errors'], -10 );
			}
		}

		set_transient( 'waisg_provider_stats_' . $provider_name, $stats, DAY_IN_SECONDS );
	}

	/**
	 * Check if an error is non-retryable.
	 *
	 * @since    1.0.0
	 * @param    string    $error_message    Error message.
	 * @return   bool                        True if non-retryable.
	 */
	private function is_non_retryable_error( $error_message ) {
		$non_retryable_patterns = array(
			'invalid api key',
			'authentication',
			'permission denied',
			'invalid request',
			'content policy',
			'safety',
		);

		$error_lower = strtolower( $error_message );

		foreach ( $non_retryable_patterns as $pattern ) {
			if ( strpos( $error_lower, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Monitor provider performance.
	 *
	 * @since    1.0.0
	 * @return   array    Performance metrics for all providers.
	 */
	public function monitor_performance() {
		$metrics = array();

		foreach ( $this->providers as $name => $provider ) {
			$stats = get_transient( 'waisg_provider_stats_' . $name );
			$health = $this->is_provider_healthy( $name );

			$metrics[ $name ] = array(
				'name'         => $provider->get_display_name(),
				'available'    => $provider->is_available(),
				'healthy'      => $health,
				'error_rate'   => $this->get_provider_error_rate( $name ),
				'usage'        => $provider->get_usage_stats(),
				'statistics'   => $stats,
				'rate_limits'  => $provider->get_rate_limits(),
			);
		}

		return $metrics;
	}

	/**
	 * Get recommended provider based on requirements.
	 *
	 * @since    1.0.0
	 * @param    array    $requirements    Requirements array.
	 * @return   string|null              Recommended provider name or null.
	 */
	public function get_recommended_provider( $requirements = array() ) {
		$candidates = array();

		foreach ( $this->providers as $name => $provider ) {
			if ( ! $provider->is_available() || ! $this->is_provider_healthy( $name ) ) {
				continue;
			}

			$score = 0;

			// Check capability requirements
			if ( isset( $requirements['capabilities'] ) ) {
				foreach ( $requirements['capabilities'] as $capability ) {
					if ( $provider->supports( $capability ) ) {
						$score += 10;
					}
				}
			}

			// Consider cost if specified
			if ( isset( $requirements['max_cost'] ) && isset( $requirements['prompt'] ) ) {
				$estimated_cost = $provider->estimate_cost( $requirements['prompt'] );
				if ( $estimated_cost <= $requirements['max_cost'] ) {
					$score += 5;
				}
			}

			// Consider context length
			if ( isset( $requirements['context_length'] ) ) {
				$models = $provider->get_available_models();
				foreach ( $models as $model_data ) {
					if ( isset( $model_data['context'] ) && $model_data['context'] >= $requirements['context_length'] ) {
						$score += 3;
						break;
					}
				}
			}

			// Consider performance (inverse of error rate)
			$score += ( 1 - $this->get_provider_error_rate( $name ) ) * 10;

			if ( $score > 0 ) {
				$candidates[ $name ] = $score;
			}
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		// Sort by score and return the best
		arsort( $candidates );
		return key( $candidates );
	}

	/**
	 * Clear provider health cache.
	 *
	 * @since    1.0.0
	 */
	public function clear_health_cache() {
		$this->health_cache = array();
		delete_transient( 'waisg_providers_health' );

		foreach ( $this->providers as $name => $provider ) {
			delete_transient( 'waisg_provider_stats_' . $name );
		}
	}
}