<?php
/**
 * Block Pattern Generator Class.
 *
 * Generates WordPress block patterns from AI output.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/generators
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Generators;

use WPAISiteGenerator\Providers\Provider_Manager;

/**
 * Pattern Generator Class.
 *
 * @since      1.0.0
 */
class Pattern_Generator {

	/**
	 * Provider manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Provider_Manager    $provider_manager    Provider manager instance.
	 */
	private $provider_manager;

	/**
	 * Theme adapter.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Theme_Adapter    $theme_adapter    Theme adapter instance.
	 */
	private $theme_adapter;

	/**
	 * Pattern templates.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $pattern_templates    Available pattern templates.
	 */
	private $pattern_templates = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    Provider_Manager    $provider_manager    Provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->provider_manager = $provider_manager;
		$this->theme_adapter = new Theme_Adapter();
		$this->initialize_pattern_templates();
	}

	/**
	 * Initialize pattern templates.
	 *
	 * @since    1.0.0
	 */
	private function initialize_pattern_templates() {
		$this->pattern_templates = array(
			'hero-cta' => array(
				'title'       => __( 'Hero with CTA', 'wp-ai-site-generator' ),
				'description' => __( 'Hero section with call-to-action buttons', 'wp-ai-site-generator' ),
				'categories'  => array( 'featured', 'header' ),
				'blocks'      => array( 'waisg/hero' ),
			),
			'features-grid' => array(
				'title'       => __( 'Features Grid', 'wp-ai-site-generator' ),
				'description' => __( 'Grid layout for showcasing features', 'wp-ai-site-generator' ),
				'categories'  => array( 'featured', 'columns' ),
				'blocks'      => array( 'waisg/features' ),
			),
			'testimonials-carousel' => array(
				'title'       => __( 'Testimonials Carousel', 'wp-ai-site-generator' ),
				'description' => __( 'Customer testimonials in carousel format', 'wp-ai-site-generator' ),
				'categories'  => array( 'testimonials' ),
				'blocks'      => array( 'waisg/testimonials' ),
			),
			'pricing-table' => array(
				'title'       => __( 'Pricing Table', 'wp-ai-site-generator' ),
				'description' => __( 'Comparison pricing table', 'wp-ai-site-generator' ),
				'categories'  => array( 'featured' ),
				'blocks'      => array( 'waisg/pricing' ),
			),
			'team-grid' => array(
				'title'       => __( 'Team Members', 'wp-ai-site-generator' ),
				'description' => __( 'Team member profiles grid', 'wp-ai-site-generator' ),
				'categories'  => array( 'about' ),
				'blocks'      => array( 'waisg/team' ),
			),
			'faq-accordion' => array(
				'title'       => __( 'FAQ Accordion', 'wp-ai-site-generator' ),
				'description' => __( 'Frequently asked questions accordion', 'wp-ai-site-generator' ),
				'categories'  => array( 'text' ),
				'blocks'      => array( 'waisg/faq' ),
			),
			'contact-form-section' => array(
				'title'       => __( 'Contact Section', 'wp-ai-site-generator' ),
				'description' => __( 'Contact form with information', 'wp-ai-site-generator' ),
				'categories'  => array( 'contact' ),
				'blocks'      => array( 'waisg/contact' ),
			),
			'full-landing-page' => array(
				'title'       => __( 'Full Landing Page', 'wp-ai-site-generator' ),
				'description' => __( 'Complete landing page pattern', 'wp-ai-site-generator' ),
				'categories'  => array( 'featured', 'header' ),
				'blocks'      => array(
					'waisg/hero',
					'waisg/features',
					'waisg/testimonials',
					'waisg/pricing',
					'waisg/faq',
					'waisg/cta'
				),
			),
		);
	}

	/**
	 * Generate a block pattern from AI output.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt         AI prompt for pattern generation.
	 * @param    string    $pattern_type   Type of pattern to generate.
	 * @param    array     $options        Optional. Generation options.
	 * @return   array                     Generated pattern data.
	 * @throws   \Exception                If generation fails.
	 */
	public function generate_pattern( $prompt, $pattern_type = 'hero-cta', $options = array() ) {
		if ( ! isset( $this->pattern_templates[ $pattern_type ] ) ) {
			throw new \Exception( __( 'Invalid pattern type', 'wp-ai-site-generator' ) );
		}

		$template = $this->pattern_templates[ $pattern_type ];

		try {
			// Generate content using AI
			$ai_response = $this->provider_manager->generate_content(
				$prompt,
				array_merge( $options, array(
					'type'     => 'pattern',
					'template' => $pattern_type,
					'blocks'   => $template['blocks'],
				) )
			);

			// Parse AI response into block structure
			$block_content = $this->parse_ai_response( $ai_response, $pattern_type );

			// Apply theme adaptations
			$adapted_content = $this->theme_adapter->adapt_pattern( $block_content );

			// Generate pattern markup
			$pattern_markup = $this->generate_pattern_markup( $adapted_content, $template );

			// Register the pattern
			$pattern_name = $this->register_pattern( $pattern_markup, $template, $options );

			return array(
				'name'       => $pattern_name,
				'title'      => $template['title'],
				'content'    => $pattern_markup,
				'categories' => $template['categories'],
				'blocks'     => $template['blocks'],
				'metadata'   => array(
					'generated_at' => current_time( 'mysql' ),
					'prompt'       => $prompt,
					'theme'        => get_template(),
				),
			);
		} catch ( \Exception $e ) {
			error_log( sprintf(
				'Pattern generation failed: %s',
				$e->getMessage()
			) );
			throw $e;
		}
	}

	/**
	 * Generate multiple patterns for a complete page.
	 *
	 * @since    1.0.0
	 * @param    string    $page_type    Type of page to generate patterns for.
	 * @param    array     $content      Content data for patterns.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Array of generated patterns.
	 */
	public function generate_page_patterns( $page_type, $content, $options = array() ) {
		$patterns = array();
		$pattern_sequence = $this->get_pattern_sequence( $page_type );

		foreach ( $pattern_sequence as $pattern_type ) {
			try {
				$section_content = $this->extract_section_content( $content, $pattern_type );
				$patterns[] = $this->generate_pattern(
					$section_content['prompt'] ?? '',
					$pattern_type,
					array_merge( $options, $section_content['options'] ?? array() )
				);
			} catch ( \Exception $e ) {
				error_log( sprintf(
					'Failed to generate pattern %s: %s',
					$pattern_type,
					$e->getMessage()
				) );
			}
		}

		return $patterns;
	}

	/**
	 * Parse AI response into block structure.
	 *
	 * @since    1.0.0
	 * @param    mixed     $ai_response    AI response data.
	 * @param    string    $pattern_type   Type of pattern.
	 * @return   array                     Parsed block structure.
	 */
	private function parse_ai_response( $ai_response, $pattern_type ) {
		$blocks = array();

		// Handle different response formats
		if ( is_string( $ai_response ) ) {
			$ai_response = json_decode( $ai_response, true ) ?: array( 'content' => $ai_response );
		}

		// Generate blocks based on pattern type
		switch ( $pattern_type ) {
			case 'hero-cta':
				$blocks[] = $this->create_hero_block( $ai_response );
				break;

			case 'features-grid':
				$blocks[] = $this->create_features_block( $ai_response );
				break;

			case 'testimonials-carousel':
				$blocks[] = $this->create_testimonials_block( $ai_response );
				break;

			case 'pricing-table':
				$blocks[] = $this->create_pricing_block( $ai_response );
				break;

			case 'team-grid':
				$blocks[] = $this->create_team_block( $ai_response );
				break;

			case 'faq-accordion':
				$blocks[] = $this->create_faq_block( $ai_response );
				break;

			case 'contact-form-section':
				$blocks[] = $this->create_contact_block( $ai_response );
				break;

			case 'full-landing-page':
				$blocks = $this->create_landing_page_blocks( $ai_response );
				break;

			default:
				$blocks[] = $this->create_generic_block( $ai_response );
		}

		return $blocks;
	}

	/**
	 * Create hero block from AI response.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Hero block structure.
	 */
	private function create_hero_block( $data ) {
		return array(
			'blockName' => 'waisg/hero',
			'attrs'     => array(
				'title'              => $data['title'] ?? __( 'Welcome to Our Site', 'wp-ai-site-generator' ),
				'subtitle'           => $data['subtitle'] ?? '',
				'description'        => $data['description'] ?? '',
				'primaryButtonText'  => $data['primaryButton'] ?? __( 'Get Started', 'wp-ai-site-generator' ),
				'secondaryButtonText' => $data['secondaryButton'] ?? __( 'Learn More', 'wp-ai-site-generator' ),
				'contentAlignment'   => $data['alignment'] ?? 'center',
				'aiGenerated'        => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create features block from AI response.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Features block structure.
	 */
	private function create_features_block( $data ) {
		$features = array();

		if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
			foreach ( $data['features'] as $index => $feature ) {
				$features[] = array(
					'id'          => (string) ( $index + 1 ),
					'icon'        => $this->map_icon( $feature['icon'] ?? 'star' ),
					'title'       => $feature['title'] ?? sprintf( 'Feature %d', $index + 1 ),
					'description' => $feature['description'] ?? '',
				);
			}
		}

		return array(
			'blockName' => 'waisg/features',
			'attrs'     => array(
				'features'    => $features,
				'columns'     => $data['columns'] ?? 3,
				'aiGenerated' => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Generate pattern markup.
	 *
	 * @since    1.0.0
	 * @param    array    $blocks      Block structure.
	 * @param    array    $template    Pattern template.
	 * @return   string                Pattern markup.
	 */
	private function generate_pattern_markup( $blocks, $template ) {
		$markup = '';

		foreach ( $blocks as $block ) {
			$markup .= $this->serialize_block( $block );
		}

		// Wrap in pattern container if needed
		if ( ! empty( $template['wrapper'] ) ) {
			$markup = $this->wrap_pattern( $markup, $template['wrapper'] );
		}

		return $markup;
	}

	/**
	 * Serialize a block to markup.
	 *
	 * @since    1.0.0
	 * @param    array    $block    Block data.
	 * @return   string             Block markup.
	 */
	private function serialize_block( $block ) {
		$markup = '<!-- wp:' . $block['blockName'];

		// Add attributes
		if ( ! empty( $block['attrs'] ) ) {
			$markup .= ' ' . wp_json_encode( $block['attrs'] );
		}

		$markup .= ' -->' . "\n";

		// Add inner content
		if ( ! empty( $block['innerHTML'] ) ) {
			$markup .= $block['innerHTML'];
		}

		// Add inner blocks
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$markup .= $this->serialize_block( $inner_block );
			}
		}

		$markup .= "\n" . '<!-- /wp:' . $block['blockName'] . ' -->' . "\n";

		return $markup;
	}

	/**
	 * Register a pattern.
	 *
	 * @since    1.0.0
	 * @param    string    $content     Pattern content.
	 * @param    array     $template    Pattern template.
	 * @param    array     $options     Pattern options.
	 * @return   string                 Pattern name.
	 */
	private function register_pattern( $content, $template, $options = array() ) {
		$pattern_name = 'waisg/' . sanitize_title( $template['title'] ) . '-' . uniqid();

		register_block_pattern(
			$pattern_name,
			array(
				'title'         => $template['title'],
				'description'   => $template['description'],
				'content'       => $content,
				'categories'    => $template['categories'],
				'keywords'      => $options['keywords'] ?? array(),
				'viewportWidth' => $options['viewportWidth'] ?? 1200,
			)
		);

		return $pattern_name;
	}

	/**
	 * Get pattern sequence for page type.
	 *
	 * @since    1.0.0
	 * @param    string    $page_type    Type of page.
	 * @return   array                   Pattern sequence.
	 */
	private function get_pattern_sequence( $page_type ) {
		$sequences = array(
			'landing' => array(
				'hero-cta',
				'features-grid',
				'testimonials-carousel',
				'pricing-table',
				'faq-accordion',
			),
			'about' => array(
				'hero-cta',
				'team-grid',
				'features-grid',
				'testimonials-carousel',
			),
			'services' => array(
				'hero-cta',
				'features-grid',
				'pricing-table',
				'faq-accordion',
			),
			'contact' => array(
				'hero-cta',
				'contact-form-section',
			),
		);

		return $sequences[ $page_type ] ?? $sequences['landing'];
	}

	/**
	 * Map icon name to dashicon.
	 *
	 * @since    1.0.0
	 * @param    string    $icon    Icon name.
	 * @return   string             Dashicon class.
	 */
	private function map_icon( $icon ) {
		$icon_map = array(
			'star'     => 'dashicons-star-filled',
			'heart'    => 'dashicons-heart',
			'shield'   => 'dashicons-shield',
			'lock'     => 'dashicons-lock',
			'chart'    => 'dashicons-chart-bar',
			'settings' => 'dashicons-admin-generic',
			'users'    => 'dashicons-groups',
			'globe'    => 'dashicons-admin-site',
			'default'  => 'dashicons-admin-generic',
		);

		return $icon_map[ $icon ] ?? $icon_map['default'];
	}

	/**
	 * Extract section content for pattern.
	 *
	 * @since    1.0.0
	 * @param    array     $content        Full page content.
	 * @param    string    $pattern_type   Pattern type.
	 * @return   array                     Section content.
	 */
	private function extract_section_content( $content, $pattern_type ) {
		// Logic to extract relevant content for each pattern type
		return array(
			'prompt'  => $content[ $pattern_type ] ?? '',
			'options' => array(),
		);
	}

	/**
	 * Create testimonials block.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Testimonials block structure.
	 */
	private function create_testimonials_block( $data ) {
		$testimonials = array();

		if ( isset( $data['testimonials'] ) && is_array( $data['testimonials'] ) ) {
			foreach ( $data['testimonials'] as $testimonial ) {
				$testimonials[] = array(
					'content' => $testimonial['content'] ?? '',
					'author'  => $testimonial['author'] ?? '',
					'role'    => $testimonial['role'] ?? '',
					'rating'  => $testimonial['rating'] ?? 5,
				);
			}
		}

		return array(
			'blockName' => 'waisg/testimonials',
			'attrs'     => array(
				'testimonials' => $testimonials,
				'style'        => $data['style'] ?? 'carousel',
				'aiGenerated'  => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create pricing block.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Pricing block structure.
	 */
	private function create_pricing_block( $data ) {
		$plans = array();

		if ( isset( $data['plans'] ) && is_array( $data['plans'] ) ) {
			foreach ( $data['plans'] as $plan ) {
				$plans[] = array(
					'name'        => $plan['name'] ?? '',
					'price'       => $plan['price'] ?? '',
					'period'      => $plan['period'] ?? '/month',
					'features'    => $plan['features'] ?? array(),
					'highlighted' => $plan['highlighted'] ?? false,
					'buttonText'  => $plan['buttonText'] ?? __( 'Choose Plan', 'wp-ai-site-generator' ),
				);
			}
		}

		return array(
			'blockName' => 'waisg/pricing',
			'attrs'     => array(
				'plans'       => $plans,
				'columns'     => count( $plans ),
				'aiGenerated' => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create landing page blocks.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Array of blocks.
	 */
	private function create_landing_page_blocks( $data ) {
		$blocks = array();

		// Add sections in order
		if ( isset( $data['hero'] ) ) {
			$blocks[] = $this->create_hero_block( $data['hero'] );
		}

		if ( isset( $data['features'] ) ) {
			$blocks[] = $this->create_features_block( $data['features'] );
		}

		if ( isset( $data['testimonials'] ) ) {
			$blocks[] = $this->create_testimonials_block( $data['testimonials'] );
		}

		if ( isset( $data['pricing'] ) ) {
			$blocks[] = $this->create_pricing_block( $data['pricing'] );
		}

		if ( isset( $data['faq'] ) ) {
			$blocks[] = $this->create_faq_block( $data['faq'] );
		}

		return $blocks;
	}

	/**
	 * Create FAQ block.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             FAQ block structure.
	 */
	private function create_faq_block( $data ) {
		$questions = array();

		if ( isset( $data['questions'] ) && is_array( $data['questions'] ) ) {
			foreach ( $data['questions'] as $qa ) {
				$questions[] = array(
					'question' => $qa['question'] ?? '',
					'answer'   => $qa['answer'] ?? '',
				);
			}
		}

		return array(
			'blockName' => 'waisg/faq',
			'attrs'     => array(
				'questions'   => $questions,
				'aiGenerated' => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create team block.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Team block structure.
	 */
	private function create_team_block( $data ) {
		$members = array();

		if ( isset( $data['members'] ) && is_array( $data['members'] ) ) {
			foreach ( $data['members'] as $member ) {
				$members[] = array(
					'name'     => $member['name'] ?? '',
					'role'     => $member['role'] ?? '',
					'bio'      => $member['bio'] ?? '',
					'image'    => $member['image'] ?? '',
					'social'   => $member['social'] ?? array(),
				);
			}
		}

		return array(
			'blockName' => 'waisg/team',
			'attrs'     => array(
				'members'     => $members,
				'columns'     => $data['columns'] ?? 3,
				'aiGenerated' => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create contact block.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Contact block structure.
	 */
	private function create_contact_block( $data ) {
		return array(
			'blockName' => 'waisg/contact',
			'attrs'     => array(
				'title'       => $data['title'] ?? __( 'Contact Us', 'wp-ai-site-generator' ),
				'description' => $data['description'] ?? '',
				'email'       => $data['email'] ?? '',
				'phone'       => $data['phone'] ?? '',
				'address'     => $data['address'] ?? '',
				'showMap'     => $data['showMap'] ?? false,
				'aiGenerated' => true,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create generic block.
	 *
	 * @since    1.0.0
	 * @param    array    $data    AI response data.
	 * @return   array             Generic block structure.
	 */
	private function create_generic_block( $data ) {
		return array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'className' => 'waisg-generated-content',
			),
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => array(),
					'innerBlocks' => array(),
					'innerHTML'   => '<p>' . esc_html( $data['content'] ?? '' ) . '</p>',
				),
			),
		);
	}

	/**
	 * Wrap pattern in container.
	 *
	 * @since    1.0.0
	 * @param    string    $content    Pattern content.
	 * @param    array     $wrapper    Wrapper settings.
	 * @return   string                Wrapped pattern.
	 */
	private function wrap_pattern( $content, $wrapper ) {
		$before = '<!-- wp:' . ( $wrapper['block'] ?? 'core/group' );

		if ( ! empty( $wrapper['attrs'] ) ) {
			$before .= ' ' . wp_json_encode( $wrapper['attrs'] );
		}

		$before .= ' -->' . "\n";
		$after = "\n" . '<!-- /wp:' . ( $wrapper['block'] ?? 'core/group' ) . ' -->';

		return $before . $content . $after;
	}
}