<?php
/**
 * AI Provider Interface.
 *
 * Defines the contract for all AI provider implementations.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Providers;

/**
 * AI Provider Interface.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/providers
 */
interface AI_Provider_Interface {

	/**
	 * Get provider name.
	 *
	 * @since    1.0.0
	 * @return   string    Provider name.
	 */
	public function get_name();

	/**
	 * Get provider display name.
	 *
	 * @since    1.0.0
	 * @return   string    Provider display name.
	 */
	public function get_display_name();

	/**
	 * Get provider description.
	 *
	 * @since    1.0.0
	 * @return   string    Provider description.
	 */
	public function get_description();

	/**
	 * Get provider icon/logo URL.
	 *
	 * @since    1.0.0
	 * @return   string    Provider icon URL.
	 */
	public function get_icon_url();

	/**
	 * Check if provider is available.
	 *
	 * @since    1.0.0
	 * @return   bool    True if available, false otherwise.
	 */
	public function is_available();

	/**
	 * Test provider connection.
	 *
	 * @since    1.0.0
	 * @return   array    Test result with 'success' and 'message' keys.
	 */
	public function test_connection();

	/**
	 * Generate content using the AI provider.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for content generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated content with metadata.
	 */
	public function generate( $prompt, $options = array() );

	/**
	 * Generate multiple content items in batch.
	 *
	 * @since    1.0.0
	 * @param    array     $prompts      Array of prompts.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Array of generated content.
	 */
	public function generate_batch( $prompts, $options = array() );

	/**
	 * Generate a website structure.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for site generation.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated site structure.
	 */
	public function generate_site_structure( $prompt, $options = array() );

	/**
	 * Generate page content.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for page generation.
	 * @param    string    $page_type    Type of page (homepage, about, services, etc.).
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated page content.
	 */
	public function generate_page_content( $prompt, $page_type, $options = array() );

	/**
	 * Generate a block.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt for block generation.
	 * @param    string    $block_type   Type of block to generate.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated block content.
	 */
	public function generate_block( $prompt, $block_type, $options = array() );

	/**
	 * Generate metadata for SEO.
	 *
	 * @since    1.0.0
	 * @param    string    $content      The content to generate metadata for.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Generated metadata.
	 */
	public function generate_meta( $content, $options = array() );

	/**
	 * Optimize generated content.
	 *
	 * @since    1.0.0
	 * @param    string    $content      The content to optimize.
	 * @param    string    $type         Type of optimization (seo, readability, etc.).
	 * @param    array     $options      Optional. Optimization options.
	 * @return   string                  Optimized content.
	 */
	public function optimize_content( $content, $type = 'general', $options = array() );

	/**
	 * Get available models.
	 *
	 * @since    1.0.0
	 * @return   array    Array of available models.
	 */
	public function get_available_models();

	/**
	 * Get current model.
	 *
	 * @since    1.0.0
	 * @return   string    Current model identifier.
	 */
	public function get_current_model();

	/**
	 * Set model to use.
	 *
	 * @since    1.0.0
	 * @param    string    $model    Model identifier.
	 * @return   bool                True if model was set, false otherwise.
	 */
	public function set_model( $model );

	/**
	 * Get provider capabilities.
	 *
	 * @since    1.0.0
	 * @return   array    Array of capabilities.
	 */
	public function get_capabilities();

	/**
	 * Check if provider supports a capability.
	 *
	 * @since    1.0.0
	 * @param    string    $capability    Capability to check.
	 * @return   bool                     True if supported, false otherwise.
	 */
	public function supports( $capability );

	/**
	 * Get usage statistics.
	 *
	 * @since    1.0.0
	 * @return   array    Usage statistics.
	 */
	public function get_usage_stats();

	/**
	 * Get rate limits.
	 *
	 * @since    1.0.0
	 * @return   array    Rate limit information.
	 */
	public function get_rate_limits();

	/**
	 * Get estimated cost for generation.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt       The prompt to estimate cost for.
	 * @param    array     $options      Optional. Generation options.
	 * @return   float                   Estimated cost.
	 */
	public function estimate_cost( $prompt, $options = array() );

	/**
	 * Get provider settings schema.
	 *
	 * @since    1.0.0
	 * @return   array    Settings schema for provider configuration.
	 */
	public function get_settings_schema();

	/**
	 * Validate provider settings.
	 *
	 * @since    1.0.0
	 * @param    array     $settings    Settings to validate.
	 * @return   array                  Validation result with 'valid' and 'errors' keys.
	 */
	public function validate_settings( $settings );

	/**
	 * Get provider-specific prompt template.
	 *
	 * @since    1.0.0
	 * @param    string    $type        Template type.
	 * @return   string                 Prompt template.
	 */
	public function get_prompt_template( $type );

	/**
	 * Format prompt for provider.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt      The raw prompt.
	 * @param    string    $type        Type of content being generated.
	 * @param    array     $context     Additional context for prompt.
	 * @return   string                 Formatted prompt.
	 */
	public function format_prompt( $prompt, $type = 'general', $context = array() );

	/**
	 * Handle provider-specific errors.
	 *
	 * @since    1.0.0
	 * @param    \Exception    $exception    The exception to handle.
	 * @return   array                       Formatted error response.
	 */
	public function handle_error( $exception );

	/**
	 * Get provider health status.
	 *
	 * @since    1.0.0
	 * @return   array    Health status information.
	 */
	public function get_health_status();
}