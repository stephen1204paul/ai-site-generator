<?php
/**
 * Block Library Class.
 *
 * Manages registration and organization of all custom blocks.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Block Library Class.
 *
 * @since      1.0.0
 */
class Block_Library {

	/**
	 * The single instance of the class.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Block_Library    $instance    Instance of this class.
	 */
	protected static $instance = null;

	/**
	 * Registered blocks.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $blocks    Array of registered blocks.
	 */
	private $blocks = array();

	/**
	 * Block categories.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $categories    Array of block categories.
	 */
	private $categories = array();

	/**
	 * Block patterns.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $patterns    Array of block patterns.
	 */
	private $patterns = array();

	/**
	 * Block variations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $variations    Array of block variations.
	 */
	private $variations = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Get instance of the class.
	 *
	 * @since    1.0.0
	 * @return   Block_Library    Instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the library.
	 *
	 * @since    1.0.0
	 */
	private function init() {
		add_action( 'init', array( $this, 'register_blocks' ), 10 );
		add_action( 'init', array( $this, 'register_block_patterns' ), 11 );
		add_filter( 'block_categories_all', array( $this, 'register_block_categories' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );
	}

	/**
	 * Register all custom blocks.
	 *
	 * @since    1.0.0
	 */
	public function register_blocks() {
		$blocks_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/build/';

		// List of blocks to register
		$block_names = array(
			'hero',
			'features',
			'testimonials',
			'cta',
			'team',
			'faq',
			'pricing',
			'contact',
		);

		foreach ( $block_names as $block_name ) {
			$block_path = $blocks_dir . $block_name;

			if ( file_exists( $block_path . '/block.json' ) ) {
				$block = register_block_type( $block_path );

				if ( $block ) {
					$this->blocks[ $block_name ] = array(
						'name'        => 'waisg/' . $block_name,
						'title'       => $block->title,
						'description' => $block->description,
						'category'    => $block->category,
						'keywords'    => $block->keywords ?? array(),
						'supports'    => $block->supports ?? array(),
						'attributes'  => $block->attributes ?? array(),
					);

					// Register block variations if they exist
					$this->register_block_variations( $block_name );
				}
			}
		}

		// Register dynamic blocks
		$this->register_dynamic_blocks();
	}

	/**
	 * Register dynamic blocks.
	 *
	 * @since    1.0.0
	 */
	private function register_dynamic_blocks() {
		// Register Contact Form block with server-side rendering
		register_block_type( 'waisg/contact-form', array(
			'render_callback' => array( $this, 'render_contact_form' ),
			'attributes'      => array(
				'recipient' => array(
					'type'    => 'string',
					'default' => get_option( 'admin_email' ),
				),
				'subject' => array(
					'type'    => 'string',
					'default' => __( 'New Contact Form Submission', 'wp-ai-site-generator' ),
				),
				'fields' => array(
					'type'    => 'array',
					'default' => array(),
				),
				'submitText' => array(
					'type'    => 'string',
					'default' => __( 'Submit', 'wp-ai-site-generator' ),
				),
			),
		) );

		// Register Blog Preview block
		register_block_type( 'waisg/blog-preview', array(
			'render_callback' => array( $this, 'render_blog_preview' ),
			'attributes'      => array(
				'posts_per_page' => array(
					'type'    => 'number',
					'default' => 3,
				),
				'category' => array(
					'type'    => 'string',
					'default' => '',
				),
				'show_excerpt' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
		) );
	}

	/**
	 * Register block categories.
	 *
	 * @since    1.0.0
	 * @param    array     $categories    Existing categories.
	 * @param    object    $post          Post object.
	 * @return   array                    Modified categories.
	 */
	public function register_block_categories( $categories, $post ) {
		$custom_categories = array(
			array(
				'slug'  => 'waisg-blocks',
				'title' => __( 'AI Site Generator', 'wp-ai-site-generator' ),
				'icon'  => 'dashicons-admin-site',
			),
			array(
				'slug'  => 'waisg-layouts',
				'title' => __( 'AI Layouts', 'wp-ai-site-generator' ),
				'icon'  => 'dashicons-layout',
			),
			array(
				'slug'  => 'waisg-patterns',
				'title' => __( 'AI Patterns', 'wp-ai-site-generator' ),
				'icon'  => 'dashicons-screenoptions',
			),
		);

		return array_merge( $custom_categories, $categories );
	}

	/**
	 * Register block patterns.
	 *
	 * @since    1.0.0
	 */
	public function register_block_patterns() {
		// Register pattern categories
		register_block_pattern_category( 'waisg-hero', array(
			'label' => __( 'AI Hero Sections', 'wp-ai-site-generator' ),
		) );

		register_block_pattern_category( 'waisg-features', array(
			'label' => __( 'AI Features', 'wp-ai-site-generator' ),
		) );

		register_block_pattern_category( 'waisg-testimonials', array(
			'label' => __( 'AI Testimonials', 'wp-ai-site-generator' ),
		) );

		register_block_pattern_category( 'waisg-pricing', array(
			'label' => __( 'AI Pricing', 'wp-ai-site-generator' ),
		) );

		register_block_pattern_category( 'waisg-landing', array(
			'label' => __( 'AI Landing Pages', 'wp-ai-site-generator' ),
		) );

		// Register patterns
		$this->register_hero_patterns();
		$this->register_feature_patterns();
		$this->register_testimonial_patterns();
		$this->register_pricing_patterns();
		$this->register_landing_page_patterns();
	}

	/**
	 * Register hero patterns.
	 *
	 * @since    1.0.0
	 */
	private function register_hero_patterns() {
		register_block_pattern(
			'waisg/hero-centered',
			array(
				'title'       => __( 'Hero Centered', 'wp-ai-site-generator' ),
				'description' => __( 'Centered hero section with CTA buttons', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-hero' ),
				'content'     => '<!-- wp:waisg/hero {"contentAlignment":"center","minHeight":"600px"} /-->',
			)
		);

		register_block_pattern(
			'waisg/hero-with-image',
			array(
				'title'       => __( 'Hero with Background', 'wp-ai-site-generator' ),
				'description' => __( 'Hero section with background image', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-hero' ),
				'content'     => '<!-- wp:waisg/hero {"contentAlignment":"center","minHeight":"700px","overlayOpacity":0.6} /-->',
			)
		);

		register_block_pattern(
			'waisg/hero-split',
			array(
				'title'       => __( 'Split Hero', 'wp-ai-site-generator' ),
				'description' => __( 'Hero with content on left and image on right', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-hero' ),
				'content'     => $this->get_split_hero_pattern(),
			)
		);
	}

	/**
	 * Register feature patterns.
	 *
	 * @since    1.0.0
	 */
	private function register_feature_patterns() {
		register_block_pattern(
			'waisg/features-3-column',
			array(
				'title'       => __( '3 Column Features', 'wp-ai-site-generator' ),
				'description' => __( 'Three column feature grid', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-features' ),
				'content'     => '<!-- wp:waisg/features {"columns":3} /-->',
			)
		);

		register_block_pattern(
			'waisg/features-4-column',
			array(
				'title'       => __( '4 Column Features', 'wp-ai-site-generator' ),
				'description' => __( 'Four column feature grid', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-features' ),
				'content'     => '<!-- wp:waisg/features {"columns":4} /-->',
			)
		);

		register_block_pattern(
			'waisg/features-with-icons',
			array(
				'title'       => __( 'Features with Icons', 'wp-ai-site-generator' ),
				'description' => __( 'Feature grid with prominent icons', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-features' ),
				'content'     => '<!-- wp:waisg/features {"columns":3,"iconSize":64,"cardStyle":"bordered"} /-->',
			)
		);
	}

	/**
	 * Register block variations.
	 *
	 * @since    1.0.0
	 * @param    string    $block_name    Block name.
	 */
	private function register_block_variations( $block_name ) {
		$variations = array();

		switch ( $block_name ) {
			case 'hero':
				$variations = array(
					array(
						'name'       => 'centered',
						'title'      => __( 'Centered Hero', 'wp-ai-site-generator' ),
						'attributes' => array( 'contentAlignment' => 'center' ),
					),
					array(
						'name'       => 'left-aligned',
						'title'      => __( 'Left Aligned Hero', 'wp-ai-site-generator' ),
						'attributes' => array( 'contentAlignment' => 'left' ),
					),
				);
				break;

			case 'features':
				$variations = array(
					array(
						'name'       => '3-columns',
						'title'      => __( '3 Column Features', 'wp-ai-site-generator' ),
						'attributes' => array( 'columns' => 3 ),
					),
					array(
						'name'       => '4-columns',
						'title'      => __( '4 Column Features', 'wp-ai-site-generator' ),
						'attributes' => array( 'columns' => 4 ),
					),
				);
				break;

			case 'pricing':
				$variations = array(
					array(
						'name'       => 'basic-pricing',
						'title'      => __( 'Basic Pricing', 'wp-ai-site-generator' ),
						'attributes' => array( 'style' => 'basic' ),
					),
					array(
						'name'       => 'featured-pricing',
						'title'      => __( 'Featured Pricing', 'wp-ai-site-generator' ),
						'attributes' => array( 'style' => 'featured' ),
					),
				);
				break;
		}

		if ( ! empty( $variations ) ) {
			$this->variations[ $block_name ] = $variations;
		}
	}

	/**
	 * Enqueue editor assets.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_editor_assets() {
		$asset_file = plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/build/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			$assets = include $asset_file;

			wp_enqueue_script(
				'waisg-blocks-editor',
				plugins_url( 'blocks/build/index.js', dirname( __FILE__ ) ),
				$assets['dependencies'],
				$assets['version'],
				true
			);

			wp_localize_script(
				'waisg-blocks-editor',
				'waisgBlockLibrary',
				array(
					'apiUrl'    => home_url( '/wp-json/waisg/v1' ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'blocks'    => $this->blocks,
					'patterns'  => $this->patterns,
					'variations' => $this->variations,
					'isPro'     => false,
				)
			);

			wp_enqueue_style(
				'waisg-blocks-editor',
				plugins_url( 'blocks/build/editor.css', dirname( __FILE__ ) ),
				array( 'wp-edit-blocks' ),
				$assets['version']
			);
		}
	}

	/**
	 * Enqueue block assets.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_block_assets() {
		$asset_file = plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/build/style.asset.php';

		if ( file_exists( $asset_file ) ) {
			$assets = include $asset_file;

			wp_enqueue_style(
				'waisg-blocks',
				plugins_url( 'blocks/build/style.css', dirname( __FILE__ ) ),
				array(),
				$assets['version']
			);
		}

		// Enqueue frontend JavaScript if needed
		if ( has_block( 'waisg/testimonials' ) || has_block( 'waisg/faq' ) ) {
			wp_enqueue_script(
				'waisg-blocks-frontend',
				plugins_url( 'blocks/build/frontend.js', dirname( __FILE__ ) ),
				array(),
				$assets['version'] ?? '1.0.0',
				true
			);
		}
	}

	/**
	 * Register REST API endpoints.
	 *
	 * @since    1.0.0
	 */
	public function register_rest_endpoints() {
		register_rest_route( 'waisg/v1', '/blocks', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_blocks' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );

		register_rest_route( 'waisg/v1', '/blocks/patterns', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_patterns' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );

		register_rest_route( 'waisg/v1', '/blocks/generate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate_block' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
			'args' => array(
				'prompt' => array(
					'required' => true,
					'type'     => 'string',
				),
				'blockType' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );
	}

	/**
	 * Get blocks via REST API.
	 *
	 * @since    1.0.0
	 * @param    \WP_REST_Request    $request    Request object.
	 * @return   \WP_REST_Response               Response object.
	 */
	public function get_blocks( $request ) {
		return new \WP_REST_Response( $this->blocks, 200 );
	}

	/**
	 * Get patterns via REST API.
	 *
	 * @since    1.0.0
	 * @param    \WP_REST_Request    $request    Request object.
	 * @return   \WP_REST_Response               Response object.
	 */
	public function get_patterns( $request ) {
		return new \WP_REST_Response( $this->patterns, 200 );
	}

	/**
	 * Generate block via REST API.
	 *
	 * @since    1.0.0
	 * @param    \WP_REST_Request    $request    Request object.
	 * @return   \WP_REST_Response               Response object.
	 */
	public function generate_block( $request ) {
		$prompt = $request->get_param( 'prompt' );
		$block_type = $request->get_param( 'blockType' );

		// Here we would integrate with the AI provider
		// For now, return mock data
		$generated_content = array(
			'success' => true,
			'data'    => array(
				'title'       => 'AI Generated Content',
				'description' => 'This content was generated based on: ' . $prompt,
			),
		);

		return new \WP_REST_Response( $generated_content, 200 );
	}

	/**
	 * Render contact form block.
	 *
	 * @since    1.0.0
	 * @param    array    $attributes    Block attributes.
	 * @return   string                  Rendered HTML.
	 */
	public function render_contact_form( $attributes ) {
		ob_start();
		?>
		<div class="waisg-contact-form">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
				<?php wp_nonce_field( 'waisg_contact_form', 'waisg_nonce' ); ?>
				<input type="hidden" name="action" value="waisg_submit_contact">

				<div class="waisg-form-field">
					<label for="name"><?php esc_html_e( 'Name', 'wp-ai-site-generator' ); ?></label>
					<input type="text" id="name" name="name" required>
				</div>

				<div class="waisg-form-field">
					<label for="email"><?php esc_html_e( 'Email', 'wp-ai-site-generator' ); ?></label>
					<input type="email" id="email" name="email" required>
				</div>

				<div class="waisg-form-field">
					<label for="message"><?php esc_html_e( 'Message', 'wp-ai-site-generator' ); ?></label>
					<textarea id="message" name="message" rows="5" required></textarea>
				</div>

				<button type="submit" class="wp-block-button__link">
					<?php echo esc_html( $attributes['submitText'] ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render blog preview block.
	 *
	 * @since    1.0.0
	 * @param    array    $attributes    Block attributes.
	 * @return   string                  Rendered HTML.
	 */
	public function render_blog_preview( $attributes ) {
		$args = array(
			'posts_per_page' => $attributes['posts_per_page'],
			'post_status'    => 'publish',
		);

		if ( ! empty( $attributes['category'] ) ) {
			$args['category_name'] = $attributes['category'];
		}

		$posts = get_posts( $args );

		ob_start();
		?>
		<div class="waisg-blog-preview">
			<div class="waisg-blog-preview__grid">
				<?php foreach ( $posts as $post ) : ?>
					<article class="waisg-blog-preview__item">
						<?php if ( has_post_thumbnail( $post ) ) : ?>
							<div class="waisg-blog-preview__image">
								<?php echo get_the_post_thumbnail( $post, 'medium' ); ?>
							</div>
						<?php endif; ?>
						<h3 class="waisg-blog-preview__title">
							<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
								<?php echo esc_html( $post->post_title ); ?>
							</a>
						</h3>
						<?php if ( $attributes['show_excerpt'] ) : ?>
							<div class="waisg-blog-preview__excerpt">
								<?php echo wp_trim_words( $post->post_excerpt ?: $post->post_content, 20 ); ?>
							</div>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get split hero pattern content.
	 *
	 * @since    1.0.0
	 * @return   string    Pattern content.
	 */
	private function get_split_hero_pattern() {
		return '<!-- wp:columns {"align":"full"} -->
<div class="wp-block-columns alignfull">
	<!-- wp:column {"verticalAlignment":"center"} -->
	<div class="wp-block-column is-vertically-aligned-center">
		<!-- wp:waisg/hero {"contentAlignment":"left","minHeight":"auto","backgroundImage":{}} /-->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:image {"sizeSlug":"large"} -->
		<figure class="wp-block-image size-large">
			<img src="" alt=""/>
		</figure>
		<!-- /wp:image -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->';
	}

	/**
	 * Get registered blocks.
	 *
	 * @since    1.0.0
	 * @return   array    Registered blocks.
	 */
	public function get_registered_blocks() {
		return $this->blocks;
	}

	/**
	 * Check if block is registered.
	 *
	 * @since    1.0.0
	 * @param    string    $block_name    Block name.
	 * @return   bool                     True if registered.
	 */
	public function is_block_registered( $block_name ) {
		return isset( $this->blocks[ $block_name ] );
	}

	/**
	 * Get block variations.
	 *
	 * @since    1.0.0
	 * @param    string    $block_name    Block name.
	 * @return   array                    Block variations.
	 */
	public function get_block_variations( $block_name ) {
		return $this->variations[ $block_name ] ?? array();
	}

	/**
	 * Register landing page patterns.
	 *
	 * @since    1.0.0
	 */
	private function register_landing_page_patterns() {
		register_block_pattern(
			'waisg/landing-startup',
			array(
				'title'       => __( 'Startup Landing Page', 'wp-ai-site-generator' ),
				'description' => __( 'Complete landing page for startups', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-landing' ),
				'content'     => $this->get_startup_landing_pattern(),
			)
		);
	}

	/**
	 * Register testimonial patterns.
	 *
	 * @since    1.0.0
	 */
	private function register_testimonial_patterns() {
		register_block_pattern(
			'waisg/testimonials-carousel',
			array(
				'title'       => __( 'Testimonials Carousel', 'wp-ai-site-generator' ),
				'description' => __( 'Carousel style testimonials', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-testimonials' ),
				'content'     => '<!-- wp:waisg/testimonials {"style":"carousel"} /-->',
			)
		);
	}

	/**
	 * Register pricing patterns.
	 *
	 * @since    1.0.0
	 */
	private function register_pricing_patterns() {
		register_block_pattern(
			'waisg/pricing-3-tier',
			array(
				'title'       => __( '3 Tier Pricing', 'wp-ai-site-generator' ),
				'description' => __( 'Three tier pricing table', 'wp-ai-site-generator' ),
				'categories'  => array( 'waisg-pricing' ),
				'content'     => '<!-- wp:waisg/pricing {"columns":3} /-->',
			)
		);
	}

	/**
	 * Get startup landing pattern.
	 *
	 * @since    1.0.0
	 * @return   string    Pattern content.
	 */
	private function get_startup_landing_pattern() {
		return '<!-- wp:waisg/hero /-->
<!-- wp:waisg/features {"columns":3} /-->
<!-- wp:waisg/testimonials {"style":"grid"} /-->
<!-- wp:waisg/pricing {"columns":3} /-->
<!-- wp:waisg/faq /-->
<!-- wp:waisg/cta /-->';
	}
}