<?php
/**
 * Generation Context Builder Class.
 *
 * Builds comprehensive context for AI generation from various sources.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Database\DB_Handler;
use WP_Error;

/**
 * Generation Context Builder Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Generation_Context {

	/**
	 * Database handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler.
	 */
	private $db_handler;

	/**
	 * Settings manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Settings_Manager    $settings_manager    Settings manager.
	 */
	private $settings_manager;

	/**
	 * Context cache.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $cache    Cached context data.
	 */
	private $cache = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->db_handler = new DB_Handler();
		$this->settings_manager = new Settings_Manager();
	}

	/**
	 * Build comprehensive context for generation.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Generation configuration.
	 * @return   array|WP_Error       Context array or error.
	 */
	public function build( $config = array() ) {
		try {
			$context = array(
				'site'        => $this->get_site_context(),
				'theme'       => $this->get_theme_context(),
				'user'        => $this->get_user_context(),
				'brand'       => $this->get_brand_guidelines( $config ),
				'seo'         => $this->get_seo_context( $config ),
				'content'     => $this->get_content_preferences( $config ),
				'technical'   => $this->get_technical_context(),
				'industry'    => $this->get_industry_context( $config ),
				'competition' => $this->get_competition_context( $config ),
				'custom'      => $config['custom_context'] ?? array(),
			);

			// Add specific page context if generating a single page
			if ( isset( $config['page_type'] ) ) {
				$context['page'] = $this->get_page_context( $config['page_type'], $config );
			}

			// Add existing content context if regenerating
			if ( isset( $config['existing_content'] ) ) {
				$context['existing'] = $this->analyze_existing_content( $config['existing_content'] );
			}

			// Filter empty values
			$context = array_filter( $context, function( $value ) {
				return ! empty( $value );
			} );

			// Validate context
			$validation = $this->validate_context( $context );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			// Cache context for reuse
			$this->cache_context( $context );

			return $context;

		} catch ( \Exception $e ) {
			return new WP_Error(
				'context_build_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get site context.
	 *
	 * @since    1.0.0
	 * @return   array    Site context.
	 */
	private function get_site_context() {
		if ( isset( $this->cache['site'] ) ) {
			return $this->cache['site'];
		}

		$context = array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => get_site_url(),
			'language'    => get_locale(),
			'timezone'    => get_option( 'timezone_string' ),
			'date_format' => get_option( 'date_format' ),
			'time_format' => get_option( 'time_format' ),
			'admin_email' => get_option( 'admin_email' ),
		);

		// Add WordPress version and environment
		global $wp_version;
		$context['wordpress'] = array(
			'version'     => $wp_version,
			'multisite'   => is_multisite(),
			'network'     => is_network_admin(),
			'environment' => wp_get_environment_type(),
		);

		// Add installed plugins context
		$active_plugins = get_option( 'active_plugins', array() );
		$context['plugins'] = array(
			'total'  => count( get_plugins() ),
			'active' => count( $active_plugins ),
			'list'   => $this->get_relevant_plugins( $active_plugins ),
		);

		// Add content statistics
		$context['content_stats'] = array(
			'posts'      => wp_count_posts( 'post' )->publish,
			'pages'      => wp_count_posts( 'page' )->publish,
			'comments'   => wp_count_comments()->approved,
			'users'      => count_users()['total_users'],
			'categories' => wp_count_terms( 'category' ),
			'tags'       => wp_count_terms( 'post_tag' ),
		);

		$this->cache['site'] = $context;
		return $context;
	}

	/**
	 * Get theme context.
	 *
	 * @since    1.0.0
	 * @return   array    Theme context.
	 */
	private function get_theme_context() {
		if ( isset( $this->cache['theme'] ) ) {
			return $this->cache['theme'];
		}

		$theme = wp_get_theme();
		$context = array(
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'author'      => $theme->get( 'Author' ),
			'description' => $theme->get( 'Description' ),
			'template'    => $theme->get_template(),
			'stylesheet'  => $theme->get_stylesheet(),
			'text_domain' => $theme->get( 'TextDomain' ),
			'parent'      => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
		);

		// Get theme features
		$context['features'] = array(
			'custom_logo'       => current_theme_supports( 'custom-logo' ),
			'custom_header'     => current_theme_supports( 'custom-header' ),
			'custom_background' => current_theme_supports( 'custom-background' ),
			'post_thumbnails'   => current_theme_supports( 'post-thumbnails' ),
			'post_formats'      => current_theme_supports( 'post-formats' ),
			'html5'             => current_theme_supports( 'html5' ),
			'title_tag'         => current_theme_supports( 'title-tag' ),
			'widgets'           => current_theme_supports( 'widgets' ),
			'block_templates'   => current_theme_supports( 'block-templates' ),
			'align_wide'        => current_theme_supports( 'align-wide' ),
			'responsive_embeds' => current_theme_supports( 'responsive-embeds' ),
		);

		// Get color palette
		$color_palette = get_theme_support( 'editor-color-palette' );
		if ( $color_palette && isset( $color_palette[0] ) ) {
			$context['color_palette'] = array_map( function( $color ) {
				return array(
					'name'  => $color['name'],
					'slug'  => $color['slug'],
					'color' => $color['color'],
				);
			}, $color_palette[0] );
		}

		// Get font sizes
		$font_sizes = get_theme_support( 'editor-font-sizes' );
		if ( $font_sizes && isset( $font_sizes[0] ) ) {
			$context['font_sizes'] = array_map( function( $size ) {
				return array(
					'name' => $size['name'],
					'slug' => $size['slug'],
					'size' => $size['size'],
				);
			}, $font_sizes[0] );
		}

		// Get block patterns
		$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$context['patterns'] = array_map( function( $pattern ) {
			return array(
				'name'        => $pattern['name'],
				'title'       => $pattern['title'],
				'description' => $pattern['description'] ?? '',
				'categories'  => $pattern['categories'] ?? array(),
			);
		}, array_slice( $patterns, 0, 10 ) ); // Limit to 10 patterns

		$this->cache['theme'] = $context;
		return $context;
	}

	/**
	 * Get user context.
	 *
	 * @since    1.0.0
	 * @return   array    User context.
	 */
	private function get_user_context() {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return array();
		}

		$context = array(
			'id'           => $user->ID,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'role'         => $user->roles[0] ?? 'subscriber',
			'registered'   => $user->user_registered,
			'locale'       => get_user_locale( $user->ID ),
		);

		// Get user preferences from settings
		$preferences = get_user_meta( $user->ID, 'waisg_preferences', true );
		if ( $preferences ) {
			$context['preferences'] = $preferences;
		}

		// Get generation history stats
		$history = $this->db_handler->get_user_generation_stats( $user->ID );
		if ( $history ) {
			$context['generation_history'] = array(
				'total_generations'  => $history['total'] ?? 0,
				'successful'         => $history['successful'] ?? 0,
				'preferred_provider' => $history['preferred_provider'] ?? null,
				'average_quality'    => $history['average_quality'] ?? 0,
			);
		}

		return $context;
	}

	/**
	 * Get brand guidelines.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Configuration.
	 * @return   array              Brand guidelines.
	 */
	private function get_brand_guidelines( $config ) {
		$guidelines = array();

		// Check for configured brand settings
		$brand_settings = get_option( 'waisg_brand_guidelines', array() );

		if ( ! empty( $brand_settings ) ) {
			$guidelines = array_merge( $guidelines, $brand_settings );
		}

		// Override with config values
		if ( isset( $config['brand'] ) ) {
			$guidelines = array_merge( $guidelines, $config['brand'] );
		}

		// Extract from site if not provided
		if ( empty( $guidelines['name'] ) ) {
			$guidelines['name'] = get_bloginfo( 'name' );
		}

		if ( empty( $guidelines['tagline'] ) ) {
			$guidelines['tagline'] = get_bloginfo( 'description' );
		}

		// Get logo if exists
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$guidelines['logo'] = array(
				'id'  => $custom_logo_id,
				'url' => wp_get_attachment_url( $custom_logo_id ),
			);
		}

		// Get colors from customizer
		$guidelines['colors'] = array(
			'primary'    => get_theme_mod( 'primary_color' ),
			'secondary'  => get_theme_mod( 'secondary_color' ),
			'accent'     => get_theme_mod( 'accent_color' ),
			'text'       => get_theme_mod( 'text_color' ),
			'background' => get_background_color(),
		);

		// Filter empty colors
		$guidelines['colors'] = array_filter( $guidelines['colors'] );

		// Get typography settings
		$guidelines['typography'] = array(
			'heading_font' => get_theme_mod( 'heading_font_family' ),
			'body_font'    => get_theme_mod( 'body_font_family' ),
			'font_size'    => get_theme_mod( 'base_font_size' ),
		);

		// Filter empty values
		$guidelines['typography'] = array_filter( $guidelines['typography'] );

		return array_filter( $guidelines );
	}

	/**
	 * Get SEO context.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Configuration.
	 * @return   array              SEO context.
	 */
	private function get_seo_context( $config ) {
		$seo = array();

		// Check if popular SEO plugins are active
		$seo_plugins = array(
			'wordpress-seo/wp-seo.php'           => 'yoast',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'aioseo',
			'seo-by-rank-math/rank-math.php'     => 'rankmath',
		);

		$active_seo_plugin = null;
		foreach ( $seo_plugins as $plugin => $name ) {
			if ( is_plugin_active( $plugin ) ) {
				$active_seo_plugin = $name;
				break;
			}
		}

		$seo['plugin'] = $active_seo_plugin;

		// Get SEO settings based on active plugin
		if ( $active_seo_plugin ) {
			$seo['settings'] = $this->get_seo_plugin_settings( $active_seo_plugin );
		}

		// Get keywords from config
		if ( isset( $config['keywords'] ) ) {
			$seo['keywords'] = is_array( $config['keywords'] )
				? $config['keywords']
				: array_map( 'trim', explode( ',', $config['keywords'] ) );
		}

		// Get focus keywords from existing content
		if ( isset( $config['post_id'] ) ) {
			$focus_keywords = get_post_meta( $config['post_id'], '_yoast_wpseo_focuskw', true );
			if ( $focus_keywords ) {
				$seo['focus_keywords'] = $focus_keywords;
			}
		}

		// Default SEO requirements
		$seo['requirements'] = array(
			'title_length'       => array( 'min' => 30, 'max' => 60 ),
			'description_length' => array( 'min' => 120, 'max' => 160 ),
			'keyword_density'    => array( 'min' => 0.5, 'max' => 2.5 ),
			'headings'           => array( 'h1' => 1, 'h2' => 2, 'h3' => 3 ),
			'images_alt_text'    => true,
			'internal_links'     => array( 'min' => 2, 'max' => 5 ),
			'external_links'     => array( 'min' => 1, 'max' => 3 ),
		);

		// Override with config
		if ( isset( $config['seo_requirements'] ) ) {
			$seo['requirements'] = array_merge( $seo['requirements'], $config['seo_requirements'] );
		}

		return $seo;
	}

	/**
	 * Get SEO plugin settings.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin    Plugin name.
	 * @return   array                Settings array.
	 */
	private function get_seo_plugin_settings( $plugin ) {
		$settings = array();

		switch ( $plugin ) {
			case 'yoast':
				$settings = array(
					'company_name' => get_option( 'wpseo_titles' )['company_name'] ?? '',
					'separator'    => get_option( 'wpseo_titles' )['separator'] ?? '',
				);
				break;

			case 'aioseo':
				$aioseo_options = get_option( 'aioseo_options' );
				if ( $aioseo_options ) {
					$settings = array(
						'site_name'  => $aioseo_options['searchAppearance']['global']['siteName'] ?? '',
						'separator'  => $aioseo_options['searchAppearance']['global']['separator'] ?? '',
					);
				}
				break;

			case 'rankmath':
				$settings = array(
					'company_name' => get_option( 'rank_math_options_titles' )['knowledgegraph_name'] ?? '',
				);
				break;
		}

		return array_filter( $settings );
	}

	/**
	 * Get content preferences.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Configuration.
	 * @return   array              Content preferences.
	 */
	private function get_content_preferences( $config ) {
		$preferences = array(
			'tone'           => 'professional',
			'style'          => 'informative',
			'perspective'    => 'third_person',
			'formality'      => 'formal',
			'length'         => 'standard',
			'reading_level'  => 'general',
			'target_audience'=> 'general',
		);

		// Get from user settings
		$user_preferences = get_option( 'waisg_content_preferences', array() );
		if ( ! empty( $user_preferences ) ) {
			$preferences = array_merge( $preferences, $user_preferences );
		}

		// Override with config
		if ( isset( $config['content_preferences'] ) ) {
			$preferences = array_merge( $preferences, $config['content_preferences'] );
		}

		// Add specific content types
		$preferences['include'] = array(
			'headings'     => true,
			'paragraphs'   => true,
			'lists'        => true,
			'quotes'       => isset( $config['include_quotes'] ) ? $config['include_quotes'] : false,
			'statistics'   => isset( $config['include_stats'] ) ? $config['include_stats'] : true,
			'examples'     => isset( $config['include_examples'] ) ? $config['include_examples'] : true,
			'call_to_action' => true,
		);

		// Content restrictions
		$preferences['avoid'] = array(
			'technical_jargon' => isset( $config['avoid_jargon'] ) ? $config['avoid_jargon'] : false,
			'passive_voice'    => true,
			'cliches'          => true,
			'redundancy'       => true,
		);

		return $preferences;
	}

	/**
	 * Get technical context.
	 *
	 * @since    1.0.0
	 * @return   array    Technical context.
	 */
	private function get_technical_context() {
		global $wp_version;

		$context = array(
			'php_version'       => phpversion(),
			'mysql_version'     => $this->get_mysql_version(),
			'server_software'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'max_upload_size'   => wp_max_upload_size(),
			'memory_limit'      => WP_MEMORY_LIMIT,
			'max_execution_time'=> ini_get( 'max_execution_time' ),
			'gutenberg_active'  => is_plugin_active( 'gutenberg/gutenberg.php' ),
			'classic_editor'    => is_plugin_active( 'classic-editor/classic-editor.php' ),
			'ssl_enabled'       => is_ssl(),
		);

		// Check for page builders
		$page_builders = array(
			'elementor/elementor.php'         => 'Elementor',
			'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
			'siteorigin-panels/siteorigin-panels.php' => 'SiteOrigin',
			'brizy/brizy.php'                 => 'Brizy',
		);

		foreach ( $page_builders as $plugin => $name ) {
			if ( is_plugin_active( $plugin ) ) {
				$context['page_builder'] = $name;
				break;
			}
		}

		// Get active block types
		$block_types = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		$context['block_types_count'] = count( $block_types );
		$context['core_blocks'] = array_filter( array_keys( $block_types ), function( $name ) {
			return strpos( $name, 'core/' ) === 0;
		} );

		return $context;
	}

	/**
	 * Get MySQL version.
	 *
	 * @since    1.0.0
	 * @return   string    MySQL version.
	 */
	private function get_mysql_version() {
		global $wpdb;
		$version = $wpdb->get_var( 'SELECT VERSION()' );
		return $version ? $version : 'Unknown';
	}

	/**
	 * Get industry context.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Configuration.
	 * @return   array              Industry context.
	 */
	private function get_industry_context( $config ) {
		if ( ! isset( $config['industry'] ) ) {
			return array();
		}

		$industry_data = array(
			'technology' => array(
				'keywords'     => array( 'innovation', 'software', 'solution', 'platform', 'digital', 'cloud' ),
				'tone'         => 'professional-casual',
				'audience'     => 'tech-savvy professionals',
				'pain_points'  => array( 'efficiency', 'scalability', 'integration', 'security' ),
				'benefits'     => array( 'automation', 'productivity', 'insights', 'collaboration' ),
			),
			'healthcare' => array(
				'keywords'     => array( 'patient', 'care', 'health', 'medical', 'treatment', 'wellness' ),
				'tone'         => 'professional-empathetic',
				'audience'     => 'patients and healthcare professionals',
				'pain_points'  => array( 'accessibility', 'quality care', 'wait times', 'costs' ),
				'benefits'     => array( 'better outcomes', 'convenience', 'expertise', 'compassionate care' ),
			),
			'ecommerce' => array(
				'keywords'     => array( 'shop', 'products', 'quality', 'shipping', 'deals', 'customer' ),
				'tone'         => 'friendly-persuasive',
				'audience'     => 'online shoppers',
				'pain_points'  => array( 'selection', 'price', 'shipping', 'returns' ),
				'benefits'     => array( 'convenience', 'variety', 'savings', 'fast delivery' ),
			),
			'education' => array(
				'keywords'     => array( 'learning', 'students', 'courses', 'knowledge', 'skills', 'growth' ),
				'tone'         => 'encouraging-informative',
				'audience'     => 'students and educators',
				'pain_points'  => array( 'engagement', 'accessibility', 'outcomes', 'costs' ),
				'benefits'     => array( 'personalized learning', 'flexibility', 'expert instruction', 'career advancement' ),
			),
			'restaurant' => array(
				'keywords'     => array( 'menu', 'dining', 'cuisine', 'fresh', 'atmosphere', 'service' ),
				'tone'         => 'warm-inviting',
				'audience'     => 'food enthusiasts and diners',
				'pain_points'  => array( 'quality', 'ambiance', 'service', 'value' ),
				'benefits'     => array( 'memorable experience', 'exceptional food', 'great atmosphere', 'excellent service' ),
			),
		);

		$industry = $config['industry'];
		if ( isset( $industry_data[ $industry ] ) ) {
			return $industry_data[ $industry ];
		}

		// Default/generic industry context
		return array(
			'keywords'    => array(),
			'tone'        => 'professional',
			'audience'    => 'general public',
			'pain_points' => array( 'quality', 'value', 'service' ),
			'benefits'    => array( 'satisfaction', 'reliability', 'expertise' ),
		);
	}

	/**
	 * Get competition context.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Configuration.
	 * @return   array              Competition context.
	 */
	private function get_competition_context( $config ) {
		$context = array();

		// Get competitors from config
		if ( isset( $config['competitors'] ) ) {
			$context['competitors'] = is_array( $config['competitors'] )
				? $config['competitors']
				: array_map( 'trim', explode( ',', $config['competitors'] ) );
		}

		// Get differentiators
		if ( isset( $config['differentiators'] ) ) {
			$context['differentiators'] = $config['differentiators'];
		} else {
			// Default differentiators
			$context['differentiators'] = array(
				'quality'    => 'Superior quality and attention to detail',
				'service'    => 'Exceptional customer service',
				'experience' => 'Years of industry experience',
				'value'      => 'Best value for money',
			);
		}

		// Get unique selling propositions
		if ( isset( $config['usp'] ) ) {
			$context['usp'] = $config['usp'];
		}

		return array_filter( $context );
	}

	/**
	 * Get page-specific context.
	 *
	 * @since    1.0.0
	 * @param    string    $page_type    Page type.
	 * @param    array     $config       Configuration.
	 * @return   array                   Page context.
	 */
	private function get_page_context( $page_type, $config ) {
		$page_contexts = array(
			'home' => array(
				'purpose'     => 'Welcome visitors and provide overview',
				'sections'    => array( 'hero', 'features', 'benefits', 'testimonials', 'cta' ),
				'key_elements'=> array( 'value proposition', 'main services', 'trust indicators' ),
				'cta_focus'   => 'Learn more or get started',
			),
			'about' => array(
				'purpose'     => 'Build trust and credibility',
				'sections'    => array( 'story', 'mission', 'team', 'values', 'achievements' ),
				'key_elements'=> array( 'company history', 'expertise', 'differentiators' ),
				'cta_focus'   => 'Contact us or view services',
			),
			'services' => array(
				'purpose'     => 'Showcase offerings and expertise',
				'sections'    => array( 'overview', 'service-list', 'process', 'benefits', 'pricing' ),
				'key_elements'=> array( 'service descriptions', 'benefits', 'process' ),
				'cta_focus'   => 'Get quote or consultation',
			),
			'contact' => array(
				'purpose'     => 'Enable easy communication',
				'sections'    => array( 'contact-form', 'contact-info', 'map', 'hours' ),
				'key_elements'=> array( 'contact form', 'phone/email', 'location', 'response time' ),
				'cta_focus'   => 'Send message or call',
			),
			'products' => array(
				'purpose'     => 'Display products and drive sales',
				'sections'    => array( 'product-grid', 'categories', 'featured', 'reviews' ),
				'key_elements'=> array( 'product cards', 'filters', 'pricing', 'add to cart' ),
				'cta_focus'   => 'Add to cart or learn more',
			),
			'blog' => array(
				'purpose'     => 'Share knowledge and improve SEO',
				'sections'    => array( 'recent-posts', 'categories', 'sidebar', 'newsletter' ),
				'key_elements'=> array( 'post excerpts', 'featured images', 'categories', 'search' ),
				'cta_focus'   => 'Read more or subscribe',
			),
			'landing' => array(
				'purpose'     => 'Convert visitors for specific campaign',
				'sections'    => array( 'hero', 'benefits', 'social-proof', 'offer', 'form' ),
				'key_elements'=> array( 'headline', 'value props', 'testimonials', 'form' ),
				'cta_focus'   => 'Sign up or claim offer',
			),
		);

		$context = $page_contexts[ $page_type ] ?? array(
			'purpose'     => 'Provide information',
			'sections'    => array( 'content' ),
			'key_elements'=> array( 'main content' ),
			'cta_focus'   => 'Take action',
		);

		// Add custom sections from config
		if ( isset( $config['custom_sections'] ) ) {
			$context['sections'] = array_merge( $context['sections'], $config['custom_sections'] );
		}

		// Add page-specific requirements
		if ( isset( $config['page_requirements'] ) ) {
			$context['requirements'] = $config['page_requirements'];
		}

		return $context;
	}

	/**
	 * Analyze existing content.
	 *
	 * @since    1.0.0
	 * @param    string    $content    Existing content.
	 * @return   array                 Content analysis.
	 */
	private function analyze_existing_content( $content ) {
		$analysis = array(
			'word_count'   => str_word_count( strip_tags( $content ) ),
			'char_count'   => strlen( $content ),
			'has_headings' => preg_match( '/<h[1-6]/i', $content ) ? true : false,
			'has_images'   => preg_match( '/<img/i', $content ) ? true : false,
			'has_links'    => preg_match( '/<a\s/i', $content ) ? true : false,
			'has_lists'    => preg_match( '/<(ul|ol)/i', $content ) ? true : false,
		);

		// Count headings
		preg_match_all( '/<h([1-6])/i', $content, $headings );
		if ( ! empty( $headings[1] ) ) {
			$analysis['heading_structure'] = array_count_values( $headings[1] );
		}

		// Extract keywords (simple frequency analysis)
		$text = strip_tags( $content );
		$words = str_word_count( strtolower( $text ), 1 );
		$word_freq = array_count_values( $words );

		// Filter common words
		$common_words = array( 'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at' );
		$keywords = array_diff_key( $word_freq, array_flip( $common_words ) );
		arsort( $keywords );

		$analysis['top_keywords'] = array_slice( array_keys( $keywords ), 0, 10 );

		// Analyze blocks if Gutenberg content
		if ( has_blocks( $content ) ) {
			$blocks = parse_blocks( $content );
			$analysis['block_types'] = array_unique( array_column( $blocks, 'blockName' ) );
			$analysis['block_count'] = count( $blocks );
		}

		return $analysis;
	}

	/**
	 * Get relevant plugins.
	 *
	 * @since    1.0.0
	 * @param    array    $active_plugins    Active plugin list.
	 * @return   array                       Relevant plugins.
	 */
	private function get_relevant_plugins( $active_plugins ) {
		$relevant = array();
		$important_plugins = array(
			'woocommerce'     => 'WooCommerce',
			'contact-form-7'  => 'Contact Form 7',
			'wpforms'         => 'WPForms',
			'elementor'       => 'Elementor',
			'yoast'           => 'Yoast SEO',
			'jetpack'         => 'Jetpack',
			'akismet'         => 'Akismet',
			'wordfence'       => 'Wordfence',
			'updraftplus'     => 'UpdraftPlus',
			'w3-total-cache'  => 'W3 Total Cache',
		);

		foreach ( $active_plugins as $plugin ) {
			foreach ( $important_plugins as $key => $name ) {
				if ( strpos( $plugin, $key ) !== false ) {
					$relevant[] = $name;
					break;
				}
			}
		}

		return $relevant;
	}

	/**
	 * Validate context.
	 *
	 * @since    1.0.0
	 * @param    array    $context    Context to validate.
	 * @return   bool|WP_Error         True if valid, error otherwise.
	 */
	private function validate_context( $context ) {
		// Check for required elements
		if ( empty( $context['site'] ) ) {
			return new WP_Error(
				'missing_site_context',
				__( 'Site context is required', 'wp-ai-site-generator' )
			);
		}

		// Validate context size (prevent too large contexts)
		$json_size = strlen( wp_json_encode( $context ) );
		$max_size = 100000; // 100KB limit

		if ( $json_size > $max_size ) {
			// Try to reduce context size
			$context = $this->reduce_context_size( $context );
			$json_size = strlen( wp_json_encode( $context ) );

			if ( $json_size > $max_size ) {
				return new WP_Error(
					'context_too_large',
					sprintf( __( 'Context size exceeds maximum limit of %s bytes', 'wp-ai-site-generator' ), $max_size )
				);
			}
		}

		return true;
	}

	/**
	 * Reduce context size.
	 *
	 * @since    1.0.0
	 * @param    array    $context    Context array.
	 * @return   array                Reduced context.
	 */
	private function reduce_context_size( $context ) {
		// Remove less critical elements
		$removable = array( 'theme.patterns', 'theme.features', 'technical.core_blocks' );

		foreach ( $removable as $path ) {
			$keys = explode( '.', $path );
			if ( count( $keys ) === 2 && isset( $context[ $keys[0] ][ $keys[1] ] ) ) {
				unset( $context[ $keys[0] ][ $keys[1] ] );
			}
		}

		// Limit array sizes
		if ( isset( $context['theme']['color_palette'] ) && count( $context['theme']['color_palette'] ) > 5 ) {
			$context['theme']['color_palette'] = array_slice( $context['theme']['color_palette'], 0, 5 );
		}

		return $context;
	}

	/**
	 * Cache context for reuse.
	 *
	 * @since    1.0.0
	 * @param    array    $context    Context to cache.
	 * @return   void
	 */
	private function cache_context( $context ) {
		// Store in transient for 1 hour
		$cache_key = 'waisg_context_' . get_current_user_id();
		set_transient( $cache_key, $context, HOUR_IN_SECONDS );

		// Also store in memory for this request
		$this->cache = array_merge( $this->cache, $context );
	}

	/**
	 * Get cached context.
	 *
	 * @since    1.0.0
	 * @return   array|false    Cached context or false.
	 */
	public function get_cached_context() {
		$cache_key = 'waisg_context_' . get_current_user_id();
		return get_transient( $cache_key );
	}

	/**
	 * Clear cached context.
	 *
	 * @since    1.0.0
	 * @return   void
	 */
	public function clear_cache() {
		$cache_key = 'waisg_context_' . get_current_user_id();
		delete_transient( $cache_key );
		$this->cache = array();
	}
}