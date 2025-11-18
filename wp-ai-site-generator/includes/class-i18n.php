<?php
/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class I18n {

	/**
	 * The domain specified for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $domain    The domain identifier for this plugin.
	 */
	private $domain;

	/**
	 * The supported languages.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $languages    The supported languages.
	 */
	private $languages;

	/**
	 * Initialize the internationalization functionality.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->domain = 'wp-ai-site-generator';
		$this->set_supported_languages();
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			$this->domain,
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Set the supported languages.
	 *
	 * @since    1.0.0
	 */
	private function set_supported_languages() {
		$this->languages = array(
			'en_US' => __( 'English (United States)', 'wp-ai-site-generator' ),
			'es_ES' => __( 'Spanish (Spain)', 'wp-ai-site-generator' ),
			'fr_FR' => __( 'French (France)', 'wp-ai-site-generator' ),
			'de_DE' => __( 'German (Germany)', 'wp-ai-site-generator' ),
			'it_IT' => __( 'Italian (Italy)', 'wp-ai-site-generator' ),
			'pt_BR' => __( 'Portuguese (Brazil)', 'wp-ai-site-generator' ),
			'nl_NL' => __( 'Dutch (Netherlands)', 'wp-ai-site-generator' ),
			'ja'    => __( 'Japanese', 'wp-ai-site-generator' ),
			'zh_CN' => __( 'Chinese (Simplified)', 'wp-ai-site-generator' ),
			'ru_RU' => __( 'Russian', 'wp-ai-site-generator' ),
			'ar'    => __( 'Arabic', 'wp-ai-site-generator' ),
			'ko_KR' => __( 'Korean', 'wp-ai-site-generator' ),
		);
	}

	/**
	 * Get the text domain.
	 *
	 * @since    1.0.0
	 * @return   string    The text domain.
	 */
	public function get_domain() {
		return $this->domain;
	}

	/**
	 * Get supported languages.
	 *
	 * @since    1.0.0
	 * @return   array    The supported languages.
	 */
	public function get_supported_languages() {
		return $this->languages;
	}

	/**
	 * Get the current language.
	 *
	 * @since    1.0.0
	 * @return   string    The current language code.
	 */
	public function get_current_language() {
		return get_locale();
	}

	/**
	 * Check if a language is supported.
	 *
	 * @since    1.0.0
	 * @param    string    $language    The language code to check.
	 * @return   bool                   True if supported, false otherwise.
	 */
	public function is_language_supported( $language ) {
		return array_key_exists( $language, $this->languages );
	}

	/**
	 * Get translatable strings for JavaScript.
	 *
	 * @since    1.0.0
	 * @return   array    Translatable strings.
	 */
	public function get_js_translations() {
		return array(
			// General
			'loading'           => __( 'Loading...', 'wp-ai-site-generator' ),
			'saving'            => __( 'Saving...', 'wp-ai-site-generator' ),
			'saved'             => __( 'Saved', 'wp-ai-site-generator' ),
			'error'             => __( 'Error', 'wp-ai-site-generator' ),
			'success'           => __( 'Success', 'wp-ai-site-generator' ),
			'warning'           => __( 'Warning', 'wp-ai-site-generator' ),
			'info'              => __( 'Info', 'wp-ai-site-generator' ),
			'confirm'           => __( 'Confirm', 'wp-ai-site-generator' ),
			'cancel'            => __( 'Cancel', 'wp-ai-site-generator' ),
			'close'             => __( 'Close', 'wp-ai-site-generator' ),
			'delete'            => __( 'Delete', 'wp-ai-site-generator' ),
			'edit'              => __( 'Edit', 'wp-ai-site-generator' ),
			'save'              => __( 'Save', 'wp-ai-site-generator' ),
			'update'            => __( 'Update', 'wp-ai-site-generator' ),
			'generate'          => __( 'Generate', 'wp-ai-site-generator' ),
			'regenerate'        => __( 'Regenerate', 'wp-ai-site-generator' ),
			'preview'           => __( 'Preview', 'wp-ai-site-generator' ),
			'publish'           => __( 'Publish', 'wp-ai-site-generator' ),

			// Generation specific
			'generating'        => __( 'Generating...', 'wp-ai-site-generator' ),
			'generation_complete' => __( 'Generation complete!', 'wp-ai-site-generator' ),
			'generation_failed' => __( 'Generation failed', 'wp-ai-site-generator' ),
			'generation_cancelled' => __( 'Generation cancelled', 'wp-ai-site-generator' ),
			'starting_generation' => __( 'Starting generation...', 'wp-ai-site-generator' ),
			'processing'        => __( 'Processing...', 'wp-ai-site-generator' ),
			'analyzing'         => __( 'Analyzing prompt...', 'wp-ai-site-generator' ),
			'creating_structure' => __( 'Creating site structure...', 'wp-ai-site-generator' ),
			'generating_content' => __( 'Generating content...', 'wp-ai-site-generator' ),
			'applying_design'   => __( 'Applying design...', 'wp-ai-site-generator' ),
			'optimizing'        => __( 'Optimizing...', 'wp-ai-site-generator' ),
			'finalizing'        => __( 'Finalizing...', 'wp-ai-site-generator' ),

			// Prompts and messages
			'enter_prompt'      => __( 'Enter your prompt', 'wp-ai-site-generator' ),
			'prompt_placeholder' => __( 'Describe the website you want to create...', 'wp-ai-site-generator' ),
			'no_prompt'         => __( 'Please enter a prompt', 'wp-ai-site-generator' ),
			'invalid_prompt'    => __( 'Invalid prompt', 'wp-ai-site-generator' ),
			'confirm_delete'    => __( 'Are you sure you want to delete this?', 'wp-ai-site-generator' ),
			'confirm_cancel'    => __( 'Are you sure you want to cancel the generation?', 'wp-ai-site-generator' ),
			'unsaved_changes'   => __( 'You have unsaved changes. Are you sure you want to leave?', 'wp-ai-site-generator' ),

			// Provider related
			'provider_error'    => __( 'Provider error', 'wp-ai-site-generator' ),
			'provider_unavailable' => __( 'Provider unavailable', 'wp-ai-site-generator' ),
			'api_key_required'  => __( 'API key required', 'wp-ai-site-generator' ),
			'invalid_api_key'   => __( 'Invalid API key', 'wp-ai-site-generator' ),
			'rate_limit_exceeded' => __( 'Rate limit exceeded', 'wp-ai-site-generator' ),
			'quota_exceeded'    => __( 'Quota exceeded', 'wp-ai-site-generator' ),

			// Validation
			'required_field'    => __( 'This field is required', 'wp-ai-site-generator' ),
			'invalid_format'    => __( 'Invalid format', 'wp-ai-site-generator' ),
			'min_length'        => __( 'Minimum length not met', 'wp-ai-site-generator' ),
			'max_length'        => __( 'Maximum length exceeded', 'wp-ai-site-generator' ),

			// Time and dates
			'just_now'          => __( 'Just now', 'wp-ai-site-generator' ),
			'minute_ago'        => __( '1 minute ago', 'wp-ai-site-generator' ),
			'minutes_ago'       => __( '%d minutes ago', 'wp-ai-site-generator' ),
			'hour_ago'          => __( '1 hour ago', 'wp-ai-site-generator' ),
			'hours_ago'         => __( '%d hours ago', 'wp-ai-site-generator' ),
			'day_ago'           => __( '1 day ago', 'wp-ai-site-generator' ),
			'days_ago'          => __( '%d days ago', 'wp-ai-site-generator' ),

			// Status messages
			'status_pending'    => __( 'Pending', 'wp-ai-site-generator' ),
			'status_processing' => __( 'Processing', 'wp-ai-site-generator' ),
			'status_completed'  => __( 'Completed', 'wp-ai-site-generator' ),
			'status_failed'     => __( 'Failed', 'wp-ai-site-generator' ),
			'status_cancelled'  => __( 'Cancelled', 'wp-ai-site-generator' ),
			'status_paused'     => __( 'Paused', 'wp-ai-site-generator' ),

			// Features
			'feature_unavailable' => __( 'This feature is not available', 'wp-ai-site-generator' ),
			'feature_coming_soon' => __( 'This feature is coming soon', 'wp-ai-site-generator' ),
			'feature_pro_only'  => __( 'This feature is available in Pro version', 'wp-ai-site-generator' ),
		);
	}

	/**
	 * Register JavaScript translation scripts.
	 *
	 * @since    1.0.0
	 * @param    string    $handle    Script handle.
	 */
	public function register_script_translations( $handle ) {
		wp_set_script_translations(
			$handle,
			$this->domain,
			plugin_dir_path( dirname( __FILE__ ) ) . 'languages'
		);
	}

	/**
	 * Get language for AI generation.
	 *
	 * Maps WordPress locale to language names for AI prompts.
	 *
	 * @since    1.0.0
	 * @param    string    $locale    Optional. WordPress locale code.
	 * @return   string               Language name for AI generation.
	 */
	public function get_ai_language( $locale = null ) {
		if ( ! $locale ) {
			$locale = get_locale();
		}

		$language_map = array(
			'en_US' => 'English',
			'es_ES' => 'Spanish',
			'fr_FR' => 'French',
			'de_DE' => 'German',
			'it_IT' => 'Italian',
			'pt_BR' => 'Portuguese',
			'nl_NL' => 'Dutch',
			'ja'    => 'Japanese',
			'zh_CN' => 'Chinese',
			'ru_RU' => 'Russian',
			'ar'    => 'Arabic',
			'ko_KR' => 'Korean',
		);

		return isset( $language_map[ $locale ] ) ? $language_map[ $locale ] : 'English';
	}

	/**
	 * Get direction for language (LTR or RTL).
	 *
	 * @since    1.0.0
	 * @param    string    $locale    Optional. WordPress locale code.
	 * @return   string               'ltr' or 'rtl'.
	 */
	public function get_language_direction( $locale = null ) {
		if ( ! $locale ) {
			$locale = get_locale();
		}

		$rtl_languages = array( 'ar', 'he_IL', 'fa_IR', 'ur' );

		// Check if locale starts with RTL language code
		foreach ( $rtl_languages as $rtl_lang ) {
			if ( strpos( $locale, $rtl_lang ) === 0 ) {
				return 'rtl';
			}
		}

		return 'ltr';
	}

	/**
	 * Get language-specific content generation instructions.
	 *
	 * @since    1.0.0
	 * @param    string    $locale    Optional. WordPress locale code.
	 * @return   array                Language-specific instructions.
	 */
	public function get_language_instructions( $locale = null ) {
		if ( ! $locale ) {
			$locale = get_locale();
		}

		$language = $this->get_ai_language( $locale );
		$direction = $this->get_language_direction( $locale );

		return array(
			'language'  => $language,
			'direction' => $direction,
			'prompt'    => sprintf(
				'Generate content in %s language. Text direction is %s.',
				$language,
				strtoupper( $direction )
			),
		);
	}
}