<?php
/**
 * Settings Manager for the plugin
 *
 * Handles all plugin settings using WordPress Settings API with support
 * for validation, sanitization, import/export, and multi-site.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class
 *
 * Manages all plugin settings with proper validation and sanitization.
 *
 * @since 1.0.0
 */
class Settings_Manager {

	/**
	 * Instance of the Encryption class.
	 *
	 * @var Encryption
	 */
	private $encryption;

	/**
	 * Instance of this class.
	 *
	 * @var Settings_Manager|null
	 */
	private static $instance = null;

	/**
	 * Settings groups definition.
	 *
	 * @var array
	 */
	private $settings_groups = array(
		'general'    => 'waisg_general_settings',
		'providers'  => 'waisg_provider_settings',
		'generation' => 'waisg_generation_settings',
		'advanced'   => 'waisg_advanced_settings',
		'features'   => 'waisg_features_settings',
		'usage'      => 'waisg_usage_settings',
	);

	/**
	 * Default settings values.
	 *
	 * @var array
	 */
	private $defaults = array();

	/**
	 * Settings validation rules.
	 *
	 * @var array
	 */
	private $validation_rules = array();

	/**
	 * Get the single instance of this class.
	 *
	 * @return Settings_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->encryption = Encryption::get_instance();
		$this->set_defaults();
		$this->set_validation_rules();
	}

	/**
	 * Initialize settings.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add AJAX handlers
		add_action( 'wp_ajax_waisg_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_waisg_import_settings', array( $this, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_waisg_reset_settings', array( $this, 'ajax_reset_settings' ) );

		// Handle multisite
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'register_network_settings' ) );
		}
	}

	/**
	 * Set default values for all settings.
	 *
	 * @since 1.0.0
	 */
	private function set_defaults() {
		$this->defaults = array(
			'general' => array(
				'default_provider'    => '',
				'default_language'    => 'en',
				'content_tone'        => 'professional',
				'debug_mode'          => false,
				'enable_ai_assistant' => true,
				'auto_save_drafts'    => true,
				'cache_duration'      => 3600,
				'api_timeout'         => 60,
			),
			'providers' => array(
				'providers' => array(
					'openai' => array(
						'enabled'      => false,
						'api_key'      => '',
						'model'        => 'gpt-3.5-turbo',
						'organization' => '',
						'max_tokens'   => 4000,
					),
					'anthropic' => array(
						'enabled'    => false,
						'api_key'    => '',
						'model'      => 'claude-3-opus-20240229',
						'max_tokens' => 4000,
					),
					'google' => array(
						'enabled'    => false,
						'api_key'    => '',
						'model'      => 'gemini-pro',
						'max_tokens' => 4000,
					),
					'cohere' => array(
						'enabled'    => false,
						'api_key'    => '',
						'model'      => 'command',
						'max_tokens' => 4000,
					),
					'custom' => array(
						'enabled'  => false,
						'endpoint' => '',
						'api_key'  => '',
						'model'    => '',
					),
				),
				'default_provider'   => '',
				'fallback_provider'  => '',
				'retry_attempts'     => 3,
				'log_api_errors'     => false,
				'rate_limit'         => 10,
			),
			'generation' => array(
				'default_pages_count'    => 5,
				'default_content_length' => 'medium',
				'include_images'         => true,
				'include_seo'            => true,
				'default_page_types'     => array( 'home', 'about', 'services', 'contact', 'blog' ),
				'content_style'          => 'modern',
				'enable_schema_markup'   => true,
				'custom_prompts'         => array(),
				'temperature'            => 0.7,
				'creativity_level'       => 'balanced',
			),
			'advanced' => array(
				'cache_duration'           => 3600,
				'logging_level'            => 'error',
				'cleanup_old_generations'  => true,
				'cleanup_days'             => 30,
				'enable_analytics'         => false,
				'custom_css'               => '',
				'custom_js'                => '',
				'api_request_timeout'      => 60,
				'max_retries'              => 3,
				'enable_webhooks'          => false,
				'webhook_url'              => '',
				'export_format'            => 'json',
			),
			'features' => array(
				'chat_interface'         => true,
				'section_regeneration'   => true,
				'bulk_generation'        => true,
				'template_customization' => true,
				'ai_suggestions'         => true,
				'content_scheduling'     => false,
				'multilingual_support'   => false,
				'version_control'        => false,
				'collaborative_editing'  => false,
			),
			'usage' => array(
				'daily_limit'           => 10,
				'monthly_limit'         => 100,
				'max_tokens_per_request'=> 4000,
				'rate_limit_per_minute' => 10,
				'track_usage'           => true,
				'alert_threshold'       => 80,
			),
		);
	}

	/**
	 * Set validation rules for settings.
	 *
	 * @since 1.0.0
	 */
	private function set_validation_rules() {
		$this->validation_rules = array(
			'general' => array(
				'default_language' => array(
					'type'    => 'select',
					'options' => array( 'en', 'es', 'fr', 'de', 'it', 'pt', 'nl', 'ru', 'ja', 'zh' ),
				),
				'content_tone' => array(
					'type'    => 'select',
					'options' => array( 'professional', 'casual', 'friendly', 'formal', 'creative' ),
				),
				'debug_mode' => array(
					'type' => 'boolean',
				),
				'cache_duration' => array(
					'type' => 'integer',
					'min'  => 0,
					'max'  => 86400,
				),
				'api_timeout' => array(
					'type' => 'integer',
					'min'  => 10,
					'max'  => 300,
				),
			),
			'providers' => array(
				'retry_attempts' => array(
					'type' => 'integer',
					'min'  => 0,
					'max'  => 10,
				),
				'rate_limit' => array(
					'type' => 'integer',
					'min'  => 1,
					'max'  => 100,
				),
			),
			'generation' => array(
				'default_pages_count' => array(
					'type' => 'integer',
					'min'  => 1,
					'max'  => 50,
				),
				'default_content_length' => array(
					'type'    => 'select',
					'options' => array( 'short', 'medium', 'long', 'extra-long' ),
				),
				'temperature' => array(
					'type' => 'float',
					'min'  => 0.0,
					'max'  => 2.0,
				),
			),
			'usage' => array(
				'daily_limit' => array(
					'type' => 'integer',
					'min'  => 0,
					'max'  => 10000,
				),
				'monthly_limit' => array(
					'type' => 'integer',
					'min'  => 0,
					'max'  => 100000,
				),
				'max_tokens_per_request' => array(
					'type' => 'integer',
					'min'  => 100,
					'max'  => 32000,
				),
			),
		);
	}

	/**
	 * Register all plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		foreach ( $this->settings_groups as $group => $option_name ) {
			register_setting(
				$option_name . '_group',
				$option_name,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this, 'sanitize_' . $group . '_settings' ),
					'default'           => $this->defaults[ $group ] ?? array(),
				)
			);

			// Add settings sections
			$this->add_settings_sections( $group, $option_name );
		}
	}

	/**
	 * Add settings sections and fields.
	 *
	 * @since 1.0.0
	 * @param string $group       Settings group.
	 * @param string $option_name Option name.
	 */
	private function add_settings_sections( $group, $option_name ) {
		$section_id = $option_name . '_section';

		// Add main section
		add_settings_section(
			$section_id,
			$this->get_section_title( $group ),
			array( $this, 'render_section_description' ),
			$option_name
		);

		// Add fields based on group
		$this->add_settings_fields( $group, $section_id, $option_name );
	}

	/**
	 * Get section title based on group.
	 *
	 * @since  1.0.0
	 * @param  string $group Settings group.
	 * @return string
	 */
	private function get_section_title( $group ) {
		$titles = array(
			'general'    => __( 'General Settings', 'wp-ai-site-generator' ),
			'providers'  => __( 'AI Provider Settings', 'wp-ai-site-generator' ),
			'generation' => __( 'Content Generation Settings', 'wp-ai-site-generator' ),
			'advanced'   => __( 'Advanced Settings', 'wp-ai-site-generator' ),
			'features'   => __( 'Feature Settings', 'wp-ai-site-generator' ),
			'usage'      => __( 'Usage Limits', 'wp-ai-site-generator' ),
		);

		return $titles[ $group ] ?? '';
	}

	/**
	 * Add settings fields for a group.
	 *
	 * @since 1.0.0
	 * @param string $group       Settings group.
	 * @param string $section_id  Section ID.
	 * @param string $option_name Option name.
	 */
	private function add_settings_fields( $group, $section_id, $option_name ) {
		// This would be expanded with actual field definitions
		// For brevity, showing a simplified version
	}

	/**
	 * Get a specific setting value.
	 *
	 * @since  1.0.0
	 * @param  string $group Setting group.
	 * @param  string $key   Setting key.
	 * @param  mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get( $group, $key = null, $default = null ) {
		if ( ! isset( $this->settings_groups[ $group ] ) ) {
			return $default;
		}

		$option_name = $this->settings_groups[ $group ];
		$settings = get_option( $option_name, $this->defaults[ $group ] ?? array() );

		// Decrypt sensitive data if needed
		if ( 'providers' === $group && isset( $settings['providers'] ) ) {
			foreach ( $settings['providers'] as $provider => &$config ) {
				if ( isset( $config['api_key'] ) && $this->encryption->is_encrypted( $config['api_key'] ) ) {
					$config['api_key'] = $this->encryption->decrypt( $config['api_key'] );
				}
			}
		}

		if ( null === $key ) {
			return $settings;
		}

		// Handle nested keys with dot notation
		if ( strpos( $key, '.' ) !== false ) {
			return $this->get_nested_value( $settings, $key, $default );
		}

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Set a specific setting value.
	 *
	 * @since  1.0.0
	 * @param  string $group Setting group.
	 * @param  string $key   Setting key.
	 * @param  mixed  $value Setting value.
	 * @return bool
	 */
	public function set( $group, $key, $value ) {
		if ( ! isset( $this->settings_groups[ $group ] ) ) {
			return false;
		}

		$option_name = $this->settings_groups[ $group ];
		$settings = get_option( $option_name, $this->defaults[ $group ] ?? array() );

		// Handle nested keys with dot notation
		if ( strpos( $key, '.' ) !== false ) {
			$this->set_nested_value( $settings, $key, $value );
		} else {
			$settings[ $key ] = $value;
		}

		// Validate and sanitize
		$sanitize_method = 'sanitize_' . $group . '_settings';
		if ( method_exists( $this, $sanitize_method ) ) {
			$settings = $this->$sanitize_method( $settings );
		}

		return update_option( $option_name, $settings );
	}

	/**
	 * Get nested value using dot notation.
	 *
	 * @since  1.0.0
	 * @param  array  $array   Array to search.
	 * @param  string $key     Dot-notated key.
	 * @param  mixed  $default Default value.
	 * @return mixed
	 */
	private function get_nested_value( $array, $key, $default = null ) {
		$keys = explode( '.', $key );

		foreach ( $keys as $k ) {
			if ( ! is_array( $array ) || ! isset( $array[ $k ] ) ) {
				return $default;
			}
			$array = $array[ $k ];
		}

		return $array;
	}

	/**
	 * Set nested value using dot notation.
	 *
	 * @since 1.0.0
	 * @param array  &$array Array to modify.
	 * @param string $key    Dot-notated key.
	 * @param mixed  $value  Value to set.
	 */
	private function set_nested_value( &$array, $key, $value ) {
		$keys = explode( '.', $key );
		$last_key = array_pop( $keys );

		foreach ( $keys as $k ) {
			if ( ! isset( $array[ $k ] ) || ! is_array( $array[ $k ] ) ) {
				$array[ $k ] = array();
			}
			$array = &$array[ $k ];
		}

		$array[ $last_key ] = $value;
	}

	/**
	 * Sanitize general settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_general_settings( $settings ) {
		$sanitized = array();

		// Default language
		if ( isset( $settings['default_language'] ) ) {
			$valid_languages = array( 'en', 'es', 'fr', 'de', 'it', 'pt', 'nl', 'ru', 'ja', 'zh' );
			$sanitized['default_language'] = in_array( $settings['default_language'], $valid_languages, true )
				? $settings['default_language']
				: 'en';
		}

		// Content tone
		if ( isset( $settings['content_tone'] ) ) {
			$valid_tones = array( 'professional', 'casual', 'friendly', 'formal', 'creative' );
			$sanitized['content_tone'] = in_array( $settings['content_tone'], $valid_tones, true )
				? $settings['content_tone']
				: 'professional';
		}

		// Boolean fields
		$boolean_fields = array( 'debug_mode', 'enable_ai_assistant', 'auto_save_drafts' );
		foreach ( $boolean_fields as $field ) {
			$sanitized[ $field ] = ! empty( $settings[ $field ] );
		}

		// Integer fields
		$integer_fields = array(
			'cache_duration' => array( 'min' => 0, 'max' => 86400 ),
			'api_timeout'    => array( 'min' => 10, 'max' => 300 ),
		);

		foreach ( $integer_fields as $field => $limits ) {
			if ( isset( $settings[ $field ] ) ) {
				$value = intval( $settings[ $field ] );
				$sanitized[ $field ] = max( $limits['min'], min( $limits['max'], $value ) );
			}
		}

		// Default provider
		if ( isset( $settings['default_provider'] ) ) {
			$sanitized['default_provider'] = sanitize_text_field( $settings['default_provider'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize provider settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_provider_settings( $settings ) {
		$sanitized = array();

		// Process providers
		if ( isset( $settings['providers'] ) && is_array( $settings['providers'] ) ) {
			$sanitized['providers'] = array();

			foreach ( $settings['providers'] as $provider => $config ) {
				$provider = sanitize_key( $provider );
				$sanitized['providers'][ $provider ] = array();

				// Enabled flag
				$sanitized['providers'][ $provider ]['enabled'] = ! empty( $config['enabled'] );

				// API Key (encrypt if provided)
				if ( isset( $config['api_key'] ) ) {
					$api_key = sanitize_text_field( $config['api_key'] );
					if ( ! empty( $api_key ) ) {
						$sanitized['providers'][ $provider ]['api_key'] = $this->encryption->sanitize_and_encrypt_api_key( $api_key );
					} else {
						$sanitized['providers'][ $provider ]['api_key'] = '';
					}
				}

				// Model
				if ( isset( $config['model'] ) ) {
					$sanitized['providers'][ $provider ]['model'] = sanitize_text_field( $config['model'] );
				}

				// Max tokens
				if ( isset( $config['max_tokens'] ) ) {
					$sanitized['providers'][ $provider ]['max_tokens'] = max( 100, min( 128000, intval( $config['max_tokens'] ) ) );
				}

				// Provider-specific fields
				if ( 'openai' === $provider && isset( $config['organization'] ) ) {
					$sanitized['providers'][ $provider ]['organization'] = sanitize_text_field( $config['organization'] );
				}

				if ( 'custom' === $provider && isset( $config['endpoint'] ) ) {
					$sanitized['providers'][ $provider ]['endpoint'] = esc_url_raw( $config['endpoint'] );
				}
			}
		}

		// Global provider settings
		if ( isset( $settings['default_provider'] ) ) {
			$sanitized['default_provider'] = sanitize_key( $settings['default_provider'] );
		}

		if ( isset( $settings['fallback_provider'] ) ) {
			$sanitized['fallback_provider'] = sanitize_key( $settings['fallback_provider'] );
		}

		if ( isset( $settings['retry_attempts'] ) ) {
			$sanitized['retry_attempts'] = max( 0, min( 10, intval( $settings['retry_attempts'] ) ) );
		}

		if ( isset( $settings['rate_limit'] ) ) {
			$sanitized['rate_limit'] = max( 1, min( 100, intval( $settings['rate_limit'] ) ) );
		}

		$sanitized['log_api_errors'] = ! empty( $settings['log_api_errors'] );

		return $sanitized;
	}

	/**
	 * Sanitize generation settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_generation_settings( $settings ) {
		$sanitized = array();

		// Pages count
		if ( isset( $settings['default_pages_count'] ) ) {
			$sanitized['default_pages_count'] = max( 1, min( 50, intval( $settings['default_pages_count'] ) ) );
		}

		// Content length
		if ( isset( $settings['default_content_length'] ) ) {
			$valid_lengths = array( 'short', 'medium', 'long', 'extra-long' );
			$sanitized['default_content_length'] = in_array( $settings['default_content_length'], $valid_lengths, true )
				? $settings['default_content_length']
				: 'medium';
		}

		// Boolean fields
		$boolean_fields = array( 'include_images', 'include_seo', 'enable_schema_markup' );
		foreach ( $boolean_fields as $field ) {
			$sanitized[ $field ] = ! empty( $settings[ $field ] );
		}

		// Page types
		if ( isset( $settings['default_page_types'] ) && is_array( $settings['default_page_types'] ) ) {
			$sanitized['default_page_types'] = array_map( 'sanitize_text_field', $settings['default_page_types'] );
		}

		// Content style
		if ( isset( $settings['content_style'] ) ) {
			$sanitized['content_style'] = sanitize_text_field( $settings['content_style'] );
		}

		// Temperature (float between 0 and 2)
		if ( isset( $settings['temperature'] ) ) {
			$sanitized['temperature'] = max( 0.0, min( 2.0, floatval( $settings['temperature'] ) ) );
		}

		// Custom prompts
		if ( isset( $settings['custom_prompts'] ) && is_array( $settings['custom_prompts'] ) ) {
			$sanitized['custom_prompts'] = array();
			foreach ( $settings['custom_prompts'] as $key => $prompt ) {
				$sanitized['custom_prompts'][ sanitize_key( $key ) ] = wp_kses_post( $prompt );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize advanced settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_advanced_settings( $settings ) {
		$sanitized = array();

		// Integer fields
		$integer_fields = array(
			'cache_duration'      => array( 'min' => 0, 'max' => 86400 ),
			'cleanup_days'        => array( 'min' => 1, 'max' => 365 ),
			'api_request_timeout' => array( 'min' => 10, 'max' => 300 ),
			'max_retries'         => array( 'min' => 0, 'max' => 10 ),
		);

		foreach ( $integer_fields as $field => $limits ) {
			if ( isset( $settings[ $field ] ) ) {
				$value = intval( $settings[ $field ] );
				$sanitized[ $field ] = max( $limits['min'], min( $limits['max'], $value ) );
			}
		}

		// Boolean fields
		$boolean_fields = array(
			'cleanup_old_generations',
			'enable_analytics',
			'enable_webhooks',
		);

		foreach ( $boolean_fields as $field ) {
			$sanitized[ $field ] = ! empty( $settings[ $field ] );
		}

		// Logging level
		if ( isset( $settings['logging_level'] ) ) {
			$valid_levels = array( 'none', 'error', 'warning', 'info', 'debug' );
			$sanitized['logging_level'] = in_array( $settings['logging_level'], $valid_levels, true )
				? $settings['logging_level']
				: 'error';
		}

		// Custom CSS
		if ( isset( $settings['custom_css'] ) ) {
			$sanitized['custom_css'] = wp_strip_all_tags( $settings['custom_css'] );
		}

		// Custom JS
		if ( isset( $settings['custom_js'] ) ) {
			$sanitized['custom_js'] = wp_strip_all_tags( $settings['custom_js'] );
		}

		// Webhook URL
		if ( isset( $settings['webhook_url'] ) ) {
			$sanitized['webhook_url'] = esc_url_raw( $settings['webhook_url'] );
		}

		// Export format
		if ( isset( $settings['export_format'] ) ) {
			$valid_formats = array( 'json', 'xml', 'csv' );
			$sanitized['export_format'] = in_array( $settings['export_format'], $valid_formats, true )
				? $settings['export_format']
				: 'json';
		}

		return $sanitized;
	}

	/**
	 * Sanitize feature settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_features_settings( $settings ) {
		$sanitized = array();

		// All feature settings are boolean
		$features = array(
			'chat_interface',
			'section_regeneration',
			'bulk_generation',
			'template_customization',
			'ai_suggestions',
			'content_scheduling',
			'multilingual_support',
			'version_control',
			'collaborative_editing',
		);

		foreach ( $features as $feature ) {
			$sanitized[ $feature ] = ! empty( $settings[ $feature ] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize usage settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings Raw settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_usage_settings( $settings ) {
		$sanitized = array();

		// Integer fields
		$integer_fields = array(
			'daily_limit'            => array( 'min' => 0, 'max' => 10000 ),
			'monthly_limit'          => array( 'min' => 0, 'max' => 100000 ),
			'max_tokens_per_request' => array( 'min' => 100, 'max' => 32000 ),
			'rate_limit_per_minute'  => array( 'min' => 1, 'max' => 100 ),
			'alert_threshold'        => array( 'min' => 0, 'max' => 100 ),
		);

		foreach ( $integer_fields as $field => $limits ) {
			if ( isset( $settings[ $field ] ) ) {
				$value = intval( $settings[ $field ] );
				$sanitized[ $field ] = max( $limits['min'], min( $limits['max'], $value ) );
			}
		}

		// Boolean fields
		$sanitized['track_usage'] = ! empty( $settings['track_usage'] );

		return $sanitized;
	}

	/**
	 * Reset settings to defaults.
	 *
	 * @since  1.0.0
	 * @param  string $group Optional. Specific group to reset.
	 * @return bool
	 */
	public function reset_to_defaults( $group = null ) {
		if ( null !== $group ) {
			if ( isset( $this->settings_groups[ $group ] ) ) {
				return update_option( $this->settings_groups[ $group ], $this->defaults[ $group ] );
			}
			return false;
		}

		// Reset all settings
		foreach ( $this->settings_groups as $g => $option_name ) {
			update_option( $option_name, $this->defaults[ $g ] );
		}

		return true;
	}

	/**
	 * Export all settings.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function export_settings() {
		$export = array(
			'plugin'    => 'wp-ai-site-generator',
			'version'   => WAISG_VERSION,
			'exported'  => current_time( 'mysql' ),
			'site_url'  => get_site_url(),
			'settings'  => array(),
		);

		foreach ( $this->settings_groups as $group => $option_name ) {
			$settings = get_option( $option_name, array() );

			// Decrypt sensitive data for export
			if ( 'providers' === $group && isset( $settings['providers'] ) ) {
				foreach ( $settings['providers'] as $provider => &$config ) {
					if ( isset( $config['api_key'] ) && $this->encryption->is_encrypted( $config['api_key'] ) ) {
						// Keep encrypted for security
						$config['api_key'] = '[ENCRYPTED]';
					}
				}
			}

			$export['settings'][ $group ] = $settings;
		}

		return $export;
	}

	/**
	 * Import settings from export data.
	 *
	 * @since  1.0.0
	 * @param  array $import_data Import data.
	 * @return bool|WP_Error
	 */
	public function import_settings( $import_data ) {
		// Validate import data
		if ( ! is_array( $import_data ) || ! isset( $import_data['plugin'] ) || 'wp-ai-site-generator' !== $import_data['plugin'] ) {
			return new \WP_Error( 'invalid_import', __( 'Invalid import file format.', 'wp-ai-site-generator' ) );
		}

		if ( ! isset( $import_data['settings'] ) || ! is_array( $import_data['settings'] ) ) {
			return new \WP_Error( 'missing_settings', __( 'No settings found in import file.', 'wp-ai-site-generator' ) );
		}

		// Import each settings group
		foreach ( $import_data['settings'] as $group => $settings ) {
			if ( ! isset( $this->settings_groups[ $group ] ) ) {
				continue;
			}

			// Skip encrypted API keys
			if ( 'providers' === $group && isset( $settings['providers'] ) ) {
				foreach ( $settings['providers'] as $provider => &$config ) {
					if ( isset( $config['api_key'] ) && '[ENCRYPTED]' === $config['api_key'] ) {
						// Get existing encrypted key
						$existing = $this->get( 'providers', 'providers.' . $provider . '.api_key' );
						if ( $existing ) {
							$config['api_key'] = $existing;
						} else {
							$config['api_key'] = '';
						}
					}
				}
			}

			// Sanitize and save
			$sanitize_method = 'sanitize_' . $group . '_settings';
			if ( method_exists( $this, $sanitize_method ) ) {
				$settings = $this->$sanitize_method( $settings );
			}

			update_option( $this->settings_groups[ $group ], $settings );
		}

		return true;
	}

	/**
	 * Get all settings for display.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_all_settings() {
		$all_settings = array();

		foreach ( $this->settings_groups as $group => $option_name ) {
			$all_settings[ $group ] = $this->get( $group );
		}

		return $all_settings;
	}

	/**
	 * AJAX handler for exporting settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_export_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-ai-site-generator' ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'waisg_admin_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'wp-ai-site-generator' ) );
		}

		$export = $this->export_settings();
		wp_send_json_success( $export );
	}

	/**
	 * AJAX handler for importing settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_import_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-ai-site-generator' ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'waisg_admin_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'wp-ai-site-generator' ) );
		}

		$settings_json = wp_unslash( $_POST['settings'] ?? '' );
		$import_data = json_decode( $settings_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( __( 'Invalid JSON format.', 'wp-ai-site-generator' ) );
		}

		$result = $this->import_settings( $import_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Settings imported successfully.', 'wp-ai-site-generator' ) );
	}

	/**
	 * AJAX handler for resetting settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_reset_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'wp-ai-site-generator' ) );
		}

		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'waisg_admin_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'wp-ai-site-generator' ) );
		}

		$group = sanitize_key( $_POST['group'] ?? '' );
		$result = $this->reset_to_defaults( $group ?: null );

		if ( $result ) {
			wp_send_json_success( __( 'Settings reset successfully.', 'wp-ai-site-generator' ) );
		} else {
			wp_send_json_error( __( 'Failed to reset settings.', 'wp-ai-site-generator' ) );
		}
	}

	/**
	 * Handle multisite network settings.
	 *
	 * @since 1.0.0
	 */
	public function register_network_settings() {
		// Add network admin settings if needed
		if ( ! is_multisite() ) {
			return;
		}

		// Register network-wide settings
		add_site_option( 'waisg_network_settings', array(
			'enable_for_new_sites' => true,
			'network_wide_limits'  => false,
			'shared_api_keys'      => false,
		) );
	}

	/**
	 * Get network settings.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_network_settings() {
		if ( ! is_multisite() ) {
			return array();
		}

		return get_site_option( 'waisg_network_settings', array() );
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @since  1.0.0
	 * @param  string $feature Feature name.
	 * @return bool
	 */
	public function is_feature_enabled( $feature ) {
		$features = $this->get( 'features' );
		return ! empty( $features[ $feature ] );
	}

	/**
	 * Get provider configuration.
	 *
	 * @since  1.0.0
	 * @param  string $provider Provider name.
	 * @return array|null
	 */
	public function get_provider_config( $provider ) {
		$providers = $this->get( 'providers', 'providers' );

		if ( isset( $providers[ $provider ] ) ) {
			return $providers[ $provider ];
		}

		return null;
	}

	/**
	 * Get active providers.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public function get_active_providers() {
		$providers = $this->get( 'providers', 'providers' );
		$active = array();

		foreach ( $providers as $name => $config ) {
			if ( ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) ) {
				$active[ $name ] = $config;
			}
		}

		return $active;
	}

	/**
	 * Validate setting value against rules.
	 *
	 * @since  1.0.0
	 * @param  string $group Settings group.
	 * @param  string $key   Setting key.
	 * @param  mixed  $value Value to validate.
	 * @return bool|WP_Error
	 */
	public function validate_setting( $group, $key, $value ) {
		if ( ! isset( $this->validation_rules[ $group ][ $key ] ) ) {
			return true; // No validation rules defined
		}

		$rules = $this->validation_rules[ $group ][ $key ];

		switch ( $rules['type'] ) {
			case 'boolean':
				return is_bool( $value ) || in_array( $value, array( 0, 1, '0', '1' ), true );

			case 'integer':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid_type', __( 'Value must be a number.', 'wp-ai-site-generator' ) );
				}
				$value = intval( $value );
				if ( isset( $rules['min'] ) && $value < $rules['min'] ) {
					return new \WP_Error( 'too_small', sprintf( __( 'Value must be at least %d.', 'wp-ai-site-generator' ), $rules['min'] ) );
				}
				if ( isset( $rules['max'] ) && $value > $rules['max'] ) {
					return new \WP_Error( 'too_large', sprintf( __( 'Value must be at most %d.', 'wp-ai-site-generator' ), $rules['max'] ) );
				}
				return true;

			case 'float':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid_type', __( 'Value must be a number.', 'wp-ai-site-generator' ) );
				}
				$value = floatval( $value );
				if ( isset( $rules['min'] ) && $value < $rules['min'] ) {
					return new \WP_Error( 'too_small', sprintf( __( 'Value must be at least %f.', 'wp-ai-site-generator' ), $rules['min'] ) );
				}
				if ( isset( $rules['max'] ) && $value > $rules['max'] ) {
					return new \WP_Error( 'too_large', sprintf( __( 'Value must be at most %f.', 'wp-ai-site-generator' ), $rules['max'] ) );
				}
				return true;

			case 'select':
				if ( ! in_array( $value, $rules['options'], true ) ) {
					return new \WP_Error( 'invalid_option', __( 'Invalid option selected.', 'wp-ai-site-generator' ) );
				}
				return true;

			default:
				return true;
		}
	}

	/**
	 * Get setting with fallback to default.
	 *
	 * @since  1.0.0
	 * @param  string $group Setting group.
	 * @param  string $key   Setting key.
	 * @return mixed
	 */
	public function get_with_default( $group, $key ) {
		$value = $this->get( $group, $key );

		if ( null === $value && isset( $this->defaults[ $group ][ $key ] ) ) {
			return $this->defaults[ $group ][ $key ];
		}

		return $value;
	}
}