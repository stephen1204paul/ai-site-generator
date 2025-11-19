<?php
/**
 * Page Builder Class.
 *
 * Assembles multiple blocks into complete pages.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/generators
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Generators;

use WPAISiteGenerator\Providers\Provider_Manager;
use WPAISiteGenerator\Database\DB_Handler;

/**
 * Page Builder Class.
 *
 * @since      1.0.0
 */
class Page_Builder {

	/**
	 * Block generator.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Block_Generator    $block_generator    Block generator instance.
	 */
	private $block_generator;

	/**
	 * Pattern generator.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Pattern_Generator    $pattern_generator    Pattern generator instance.
	 */
	private $pattern_generator;

	/**
	 * Theme adapter.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Theme_Adapter    $theme_adapter    Theme adapter instance.
	 */
	private $theme_adapter;

	/**
	 * Database handler.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler instance.
	 */
	private $db_handler;

	/**
	 * Page templates.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $page_templates    Available page templates.
	 */
	private $page_templates = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    Provider_Manager    $provider_manager    Provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->block_generator = new Block_Generator( $provider_manager );
		$this->pattern_generator = new Pattern_Generator( $provider_manager );
		$this->theme_adapter = new Theme_Adapter();
		$this->db_handler = new DB_Handler();
		$this->initialize_page_templates();
	}

	/**
	 * Initialize page templates.
	 *
	 * @since    1.0.0
	 */
	private function initialize_page_templates() {
		$this->page_templates = array(
			'landing' => array(
				'name'        => __( 'Landing Page', 'wp-ai-site-generator' ),
				'description' => __( 'Complete landing page with all sections', 'wp-ai-site-generator' ),
				'sections'    => array(
					'hero',
					'features',
					'benefits',
					'testimonials',
					'pricing',
					'faq',
					'cta',
					'footer',
				),
				'layout' => 'full-width',
			),
			'homepage' => array(
				'name'        => __( 'Homepage', 'wp-ai-site-generator' ),
				'description' => __( 'Main website homepage', 'wp-ai-site-generator' ),
				'sections'    => array(
					'hero',
					'features',
					'about-preview',
					'services',
					'testimonials',
					'blog-preview',
					'cta',
					'footer',
				),
				'layout' => 'full-width',
			),
			'about' => array(
				'name'        => __( 'About Page', 'wp-ai-site-generator' ),
				'description' => __( 'About us/company page', 'wp-ai-site-generator' ),
				'sections'    => array(
					'hero-simple',
					'mission',
					'story',
					'team',
					'values',
					'achievements',
					'cta',
					'footer',
				),
				'layout' => 'standard',
			),
			'services' => array(
				'name'        => __( 'Services Page', 'wp-ai-site-generator' ),
				'description' => __( 'Services or products page', 'wp-ai-site-generator' ),
				'sections'    => array(
					'hero-simple',
					'services-grid',
					'process',
					'benefits',
					'testimonials',
					'pricing',
					'cta',
					'footer',
				),
				'layout' => 'standard',
			),
			'contact' => array(
				'name'        => __( 'Contact Page', 'wp-ai-site-generator' ),
				'description' => __( 'Contact information and form', 'wp-ai-site-generator' ),
				'sections'    => array(
					'hero-simple',
					'contact-info',
					'contact-form',
					'map',
					'faq-contact',
					'footer',
				),
				'layout' => 'standard',
			),
			'pricing' => array(
				'name'        => __( 'Pricing Page', 'wp-ai-site-generator' ),
				'description' => __( 'Detailed pricing page', 'wp-ai-site-generator' ),
				'sections'    => array(
					'hero-simple',
					'pricing-table',
					'features-comparison',
					'testimonials',
					'faq-pricing',
					'cta',
					'footer',
				),
				'layout' => 'standard',
			),
		);
	}

	/**
	 * Build a complete page.
	 *
	 * @since    1.0.0
	 * @param    string    $template      Page template to use.
	 * @param    array     $content       Content data for the page.
	 * @param    array     $options       Optional. Build options.
	 * @return   array                    Built page data.
	 * @throws   \Exception               If build fails.
	 */
	public function build_page( $template, $content, $options = array() ) {
		if ( ! isset( $this->page_templates[ $template ] ) ) {
			throw new \Exception( __( 'Invalid page template', 'wp-ai-site-generator' ) );
		}

		$page_template = $this->page_templates[ $template ];
		$page_blocks = array();
		$errors = array();

		try {
			// Add page wrapper if needed
			if ( $page_template['layout'] === 'full-width' ) {
				$page_blocks[] = $this->create_page_wrapper_start();
			}

			// Build each section
			foreach ( $page_template['sections'] as $section ) {
				try {
					$section_blocks = $this->build_section( $section, $content, $options );
					$page_blocks = array_merge( $page_blocks, $section_blocks );
				} catch ( \Exception $e ) {
					$errors[] = sprintf(
						__( 'Failed to build section %s: %s', 'wp-ai-site-generator' ),
						$section,
						$e->getMessage()
					);
				}
			}

			// Close page wrapper if needed
			if ( $page_template['layout'] === 'full-width' ) {
				$page_blocks[] = $this->create_page_wrapper_end();
			}

			// Apply theme adaptations
			$page_blocks = $this->theme_adapter->adapt_pattern( $page_blocks );

			// Generate page content
			$page_content = $this->serialize_blocks( $page_blocks );

			// Save page data
			$page_id = $this->save_page( array(
				'title'    => $content['title'] ?? $page_template['name'],
				'content'  => $page_content,
				'template' => $template,
				'meta'     => $options,
			) );

			return array(
				'id'       => $page_id,
				'title'    => $content['title'] ?? $page_template['name'],
				'content'  => $page_content,
				'blocks'   => $page_blocks,
				'template' => $template,
				'errors'   => $errors,
				'metadata' => array(
					'generated_at' => current_time( 'mysql' ),
					'theme'        => get_template(),
					'sections'     => count( $page_blocks ),
				),
			);
		} catch ( \Exception $e ) {
			error_log( sprintf(
				'Page build failed: %s',
				$e->getMessage()
			) );
			throw $e;
		}
	}

	/**
	 * Build a multi-page site.
	 *
	 * @since    1.0.0
	 * @param    array    $site_structure    Site structure definition.
	 * @param    array    $content           Content for all pages.
	 * @param    array    $options           Optional. Build options.
	 * @return   array                       Built site data.
	 */
	public function build_site( $site_structure, $content, $options = array() ) {
		$pages = array();
		$menu_items = array();
		$errors = array();

		foreach ( $site_structure as $page_def ) {
			try {
				// Build individual page
				$page = $this->build_page(
					$page_def['template'] ?? 'landing',
					$content[ $page_def['slug'] ] ?? array(),
					array_merge( $options, $page_def['options'] ?? array() )
				);

				$pages[ $page_def['slug'] ] = $page;

				// Add to menu structure
				$menu_items[] = array(
					'title' => $page_def['title'] ?? $page['title'],
					'slug'  => $page_def['slug'],
					'id'    => $page['id'],
					'order' => $page_def['order'] ?? 0,
				);
			} catch ( \Exception $e ) {
				$errors[ $page_def['slug'] ] = $e->getMessage();
			}
		}

		// Create navigation menu
		$menu_id = $this->create_navigation_menu( $menu_items );

		return array(
			'pages'      => $pages,
			'menu_id'    => $menu_id,
			'page_count' => count( $pages ),
			'errors'     => $errors,
			'metadata'   => array(
				'generated_at' => current_time( 'mysql' ),
				'theme'        => get_template(),
			),
		);
	}

	/**
	 * Build a section of the page.
	 *
	 * @since    1.0.0
	 * @param    string    $section     Section identifier.
	 * @param    array     $content     Content data.
	 * @param    array     $options     Build options.
	 * @return   array                  Section blocks.
	 */
	private function build_section( $section, $content, $options = array() ) {
		$blocks = array();

		// Map section to pattern or blocks
		switch ( $section ) {
			case 'hero':
				$blocks[] = $this->create_hero_section( $content['hero'] ?? array() );
				break;

			case 'hero-simple':
				$blocks[] = $this->create_simple_hero( $content['hero'] ?? array() );
				break;

			case 'features':
				$blocks[] = $this->create_features_section( $content['features'] ?? array() );
				break;

			case 'testimonials':
				$blocks[] = $this->create_testimonials_section( $content['testimonials'] ?? array() );
				break;

			case 'pricing':
			case 'pricing-table':
				$blocks[] = $this->create_pricing_section( $content['pricing'] ?? array() );
				break;

			case 'faq':
			case 'faq-pricing':
			case 'faq-contact':
				$blocks[] = $this->create_faq_section( $content['faq'] ?? array() );
				break;

			case 'team':
				$blocks[] = $this->create_team_section( $content['team'] ?? array() );
				break;

			case 'cta':
				$blocks[] = $this->create_cta_section( $content['cta'] ?? array() );
				break;

			case 'contact-form':
				$blocks[] = $this->create_contact_form_section( $content['contact'] ?? array() );
				break;

			case 'footer':
				$blocks[] = $this->create_footer_section( $content['footer'] ?? array() );
				break;

			case 'services-grid':
				$blocks[] = $this->create_services_grid( $content['services'] ?? array() );
				break;

			case 'process':
				$blocks[] = $this->create_process_section( $content['process'] ?? array() );
				break;

			case 'blog-preview':
				$blocks[] = $this->create_blog_preview( $content['blog'] ?? array() );
				break;

			default:
				// Try to generate as a pattern
				$pattern = $this->pattern_generator->generate_pattern(
					$content['prompt'] ?? '',
					'hero-cta',
					$options
				);
				$blocks = $this->parse_pattern_blocks( $pattern['content'] );
		}

		// Add section wrapper if needed
		if ( ! empty( $options['wrap_sections'] ) ) {
			$blocks = $this->wrap_in_section( $blocks, $section );
		}

		return $blocks;
	}

	/**
	 * Create hero section.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Hero data.
	 * @return   array             Hero block.
	 */
	private function create_hero_section( $data ) {
		return array(
			'blockName' => 'waisg/hero',
			'attrs'     => array(
				'title'               => $data['title'] ?? __( 'Welcome', 'wp-ai-site-generator' ),
				'subtitle'            => $data['subtitle'] ?? '',
				'description'         => $data['description'] ?? '',
				'primaryButtonText'   => $data['primaryButton'] ?? __( 'Get Started', 'wp-ai-site-generator' ),
				'secondaryButtonText' => $data['secondaryButton'] ?? __( 'Learn More', 'wp-ai-site-generator' ),
				'backgroundImage'     => $data['image'] ?? array(),
				'contentAlignment'    => $data['alignment'] ?? 'center',
				'minHeight'           => '600px',
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create features section.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Features data.
	 * @return   array             Features block.
	 */
	private function create_features_section( $data ) {
		$features = array();

		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $index => $feature ) {
				$features[] = array(
					'id'          => (string) ( $index + 1 ),
					'icon'        => $feature['icon'] ?? 'dashicons-yes',
					'title'       => $feature['title'] ?? sprintf( 'Feature %d', $index + 1 ),
					'description' => $feature['description'] ?? '',
				);
			}
		}

		return array(
			'blockName' => 'waisg/features',
			'attrs'     => array(
				'features'  => $features,
				'columns'   => $data['columns'] ?? 3,
				'cardStyle' => $data['style'] ?? 'default',
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create testimonials section.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Testimonials data.
	 * @return   array             Testimonials block.
	 */
	private function create_testimonials_section( $data ) {
		$testimonials = array();

		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $testimonial ) {
				$testimonials[] = array(
					'content' => $testimonial['content'] ?? '',
					'author'  => $testimonial['author'] ?? '',
					'role'    => $testimonial['role'] ?? '',
					'company' => $testimonial['company'] ?? '',
					'rating'  => $testimonial['rating'] ?? 5,
					'image'   => $testimonial['image'] ?? '',
				);
			}
		}

		return array(
			'blockName' => 'waisg/testimonials',
			'attrs'     => array(
				'testimonials' => $testimonials,
				'style'        => $data['style'] ?? 'carousel',
				'columns'      => $data['columns'] ?? 1,
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create pricing section.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Pricing data.
	 * @return   array             Pricing block.
	 */
	private function create_pricing_section( $data ) {
		$plans = array();

		if ( isset( $data['plans'] ) && is_array( $data['plans'] ) ) {
			foreach ( $data['plans'] as $plan ) {
				$plans[] = array(
					'name'        => $plan['name'] ?? '',
					'price'       => $plan['price'] ?? '0',
					'currency'    => $plan['currency'] ?? '$',
					'period'      => $plan['period'] ?? '/month',
					'description' => $plan['description'] ?? '',
					'features'    => $plan['features'] ?? array(),
					'buttonText'  => $plan['buttonText'] ?? __( 'Choose Plan', 'wp-ai-site-generator' ),
					'buttonUrl'   => $plan['buttonUrl'] ?? '#',
					'highlighted' => $plan['highlighted'] ?? false,
				);
			}
		}

		return array(
			'blockName' => 'waisg/pricing',
			'attrs'     => array(
				'plans'   => $plans,
				'columns' => count( $plans ),
				'style'   => $data['style'] ?? 'default',
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create FAQ section.
	 *
	 * @since    1.0.0
	 * @param    array    $data    FAQ data.
	 * @return   array             FAQ block.
	 */
	private function create_faq_section( $data ) {
		$questions = array();

		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$questions[] = array(
					'question' => $item['question'] ?? '',
					'answer'   => $item['answer'] ?? '',
				);
			}
		}

		return array(
			'blockName' => 'waisg/faq',
			'attrs'     => array(
				'questions' => $questions,
				'style'     => $data['style'] ?? 'accordion',
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create CTA section.
	 *
	 * @since    1.0.0
	 * @param    array    $data    CTA data.
	 * @return   array             CTA block.
	 */
	private function create_cta_section( $data ) {
		return array(
			'blockName' => 'waisg/cta',
			'attrs'     => array(
				'title'       => $data['title'] ?? __( 'Ready to Get Started?', 'wp-ai-site-generator' ),
				'description' => $data['description'] ?? '',
				'buttonText'  => $data['buttonText'] ?? __( 'Start Now', 'wp-ai-site-generator' ),
				'buttonUrl'   => $data['buttonUrl'] ?? '#',
				'style'       => $data['style'] ?? 'centered',
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Serialize blocks to content.
	 *
	 * @since    1.0.0
	 * @param    array    $blocks    Blocks array.
	 * @return   string              Serialized content.
	 */
	private function serialize_blocks( $blocks ) {
		$content = '';

		foreach ( $blocks as $block ) {
			$content .= serialize_block( $block );
		}

		return $content;
	}

	/**
	 * Save page to database.
	 *
	 * @since    1.0.0
	 * @param    array    $page_data    Page data.
	 * @return   int                    Page ID.
	 */
	private function save_page( $page_data ) {
		$post_data = array(
			'post_title'   => $page_data['title'],
			'post_content' => $page_data['content'],
			'post_status'  => 'draft',
			'post_type'    => 'page',
			'meta_input'   => array(
				'_waisg_generated'  => true,
				'_waisg_template'   => $page_data['template'],
				'_waisg_metadata'   => wp_json_encode( $page_data['meta'] ),
				'_waisg_created_at' => current_time( 'mysql' ),
			),
		);

		$page_id = wp_insert_post( $post_data );

		if ( is_wp_error( $page_id ) ) {
			throw new \Exception( $page_id->get_error_message() );
		}

		return $page_id;
	}

	/**
	 * Create navigation menu.
	 *
	 * @since    1.0.0
	 * @param    array    $menu_items    Menu items.
	 * @return   int                     Menu ID.
	 */
	private function create_navigation_menu( $menu_items ) {
		$menu_name = 'AI Generated Menu ' . date( 'Y-m-d H:i:s' );
		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		foreach ( $menu_items as $item ) {
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'   => $item['title'],
				'menu-item-object'  => 'page',
				'menu-item-object-id' => $item['id'],
				'menu-item-type'    => 'post_type',
				'menu-item-status'  => 'publish',
				'menu-item-position' => $item['order'],
			) );
		}

		return $menu_id;
	}

	/**
	 * Create page wrapper start.
	 *
	 * @since    1.0.0
	 * @return   array    Wrapper block.
	 */
	private function create_page_wrapper_start() {
		return array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'tagName'   => 'main',
				'className' => 'waisg-page-wrapper',
				'layout'    => array(
					'type' => 'constrained',
				),
			),
			'innerBlocks' => array(),
		);
	}

	/**
	 * Create page wrapper end.
	 *
	 * @since    1.0.0
	 * @return   array    Closing wrapper block.
	 */
	private function create_page_wrapper_end() {
		// WordPress blocks handle closing automatically
		return array();
	}

	/**
	 * Wrap blocks in section.
	 *
	 * @since    1.0.0
	 * @param    array     $blocks     Blocks to wrap.
	 * @param    string    $section    Section identifier.
	 * @return   array                 Wrapped blocks.
	 */
	private function wrap_in_section( $blocks, $section ) {
		return array(
			array(
				'blockName' => 'core/group',
				'attrs'     => array(
					'className' => 'waisg-section waisg-section--' . $section,
					'tagName'   => 'section',
				),
				'innerBlocks' => $blocks,
			),
		);
	}

	/**
	 * Get available page templates.
	 *
	 * @since    1.0.0
	 * @return   array    Page templates.
	 */
	public function get_page_templates() {
		return $this->page_templates;
	}

	/**
	 * Register custom page template.
	 *
	 * @since    1.0.0
	 * @param    string    $key         Template key.
	 * @param    array     $template    Template definition.
	 */
	public function register_page_template( $key, $template ) {
		$this->page_templates[ $key ] = $template;
	}
}