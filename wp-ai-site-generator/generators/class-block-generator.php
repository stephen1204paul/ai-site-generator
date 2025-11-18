<?php
/**
 * Block Generator Class.
 *
 * Handles generation of WordPress blocks using AI.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/generators
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Generators;

use WPAISiteGenerator\Providers\Provider_Manager;
use WPAISiteGenerator\Database\DB_Handler;

/**
 * Block Generator Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/generators
 */
class Block_Generator {

	/**
	 * Provider manager.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Provider_Manager    $provider_manager    Provider manager instance.
	 */
	private $provider_manager;

	/**
	 * Database handler.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler instance.
	 */
	private $db_handler;

	/**
	 * Block types.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $block_types    Supported block types.
	 */
	private $block_types = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    Provider_Manager    $provider_manager    Provider manager instance.
	 */
	public function __construct( Provider_Manager $provider_manager ) {
		$this->provider_manager = $provider_manager;
		$this->db_handler = new DB_Handler();
		$this->initialize_block_types();
	}

	/**
	 * Initialize supported block types.
	 *
	 * @since    1.0.0
	 */
	private function initialize_block_types() {
		$this->block_types = array(
			'paragraph' => array(
				'name'        => 'core/paragraph',
				'title'       => __( 'Paragraph', 'wp-ai-site-generator' ),
				'description' => __( 'Start with the basic building block of all narrative.', 'wp-ai-site-generator' ),
				'category'    => 'text',
			),
			'heading' => array(
				'name'        => 'core/heading',
				'title'       => __( 'Heading', 'wp-ai-site-generator' ),
				'description' => __( 'Introduce new sections and organize content.', 'wp-ai-site-generator' ),
				'category'    => 'text',
			),
			'list' => array(
				'name'        => 'core/list',
				'title'       => __( 'List', 'wp-ai-site-generator' ),
				'description' => __( 'Create ordered or unordered lists.', 'wp-ai-site-generator' ),
				'category'    => 'text',
			),
			'image' => array(
				'name'        => 'core/image',
				'title'       => __( 'Image', 'wp-ai-site-generator' ),
				'description' => __( 'Insert an image to make a visual statement.', 'wp-ai-site-generator' ),
				'category'    => 'media',
			),
			'gallery' => array(
				'name'        => 'core/gallery',
				'title'       => __( 'Gallery', 'wp-ai-site-generator' ),
				'description' => __( 'Display multiple images in a rich gallery.', 'wp-ai-site-generator' ),
				'category'    => 'media',
			),
			'quote' => array(
				'name'        => 'core/quote',
				'title'       => __( 'Quote', 'wp-ai-site-generator' ),
				'description' => __( 'Give quoted text visual emphasis.', 'wp-ai-site-generator' ),
				'category'    => 'text',
			),
			'button' => array(
				'name'        => 'core/button',
				'title'       => __( 'Button', 'wp-ai-site-generator' ),
				'description' => __( 'Prompt visitors to take action.', 'wp-ai-site-generator' ),
				'category'    => 'design',
			),
			'columns' => array(
				'name'        => 'core/columns',
				'title'       => __( 'Columns', 'wp-ai-site-generator' ),
				'description' => __( 'Display content in multiple columns.', 'wp-ai-site-generator' ),
				'category'    => 'design',
			),
			'group' => array(
				'name'        => 'core/group',
				'title'       => __( 'Group', 'wp-ai-site-generator' ),
				'description' => __( 'Gather blocks in a container.', 'wp-ai-site-generator' ),
				'category'    => 'design',
			),
			'cover' => array(
				'name'        => 'core/cover',
				'title'       => __( 'Cover', 'wp-ai-site-generator' ),
				'description' => __( 'Add an image or video with a text overlay.', 'wp-ai-site-generator' ),
				'category'    => 'media',
			),
			'media-text' => array(
				'name'        => 'core/media-text',
				'title'       => __( 'Media & Text', 'wp-ai-site-generator' ),
				'description' => __( 'Set media and words side-by-side.', 'wp-ai-site-generator' ),
				'category'    => 'media',
			),
			'spacer' => array(
				'name'        => 'core/spacer',
				'title'       => __( 'Spacer', 'wp-ai-site-generator' ),
				'description' => __( 'Add white space between blocks.', 'wp-ai-site-generator' ),
				'category'    => 'design',
			),
			'separator' => array(
				'name'        => 'core/separator',
				'title'       => __( 'Separator', 'wp-ai-site-generator' ),
				'description' => __( 'Create a break between sections.', 'wp-ai-site-generator' ),
				'category'    => 'design',
			),
		);

		// Allow custom block types
		$this->block_types = apply_filters( 'waisg_block_types', $this->block_types );
	}

	/**
	 * Generate a block.
	 *
	 * @since    1.0.0
	 * @param    string    $prompt         The prompt for block generation.
	 * @param    string    $block_type     Type of block to generate.
	 * @param    string    $provider       Optional. Specific provider to use.
	 * @param    array     $options        Optional. Generation options.
	 * @return   array                     Generated block data.
	 * @throws   \Exception                If generation fails.
	 */
	public function generate( $prompt, $block_type = 'paragraph', $provider = null, $options = array() ) {
		// Validate block type
		if ( ! isset( $this->block_types[ $block_type ] ) ) {
			throw new \Exception( __( 'Invalid block type', 'wp-ai-site-generator' ) );
		}

		// Start generation tracking
		$generation_start = microtime( true );

		try {
			// Generate block content using AI
			$ai_response = $this->provider_manager->generate_block(
				$prompt,
				$block_type,
				$options,
				$provider
			);

			// Format block data
			$block_data = $this->format_block_data( $ai_response, $block_type );

			// Generate block HTML
			$block_html = $this->generate_block_html( $block_data, $block_type );

			// Save generated block to database
			$block_id = $this->save_generated_block( array(
				'block_type'    => $block_type,
				'block_content' => $block_html,
				'prompt'        => $prompt,
				'provider'      => $provider ?? 'default',
				'metadata'      => wp_json_encode( $block_data ),
			) );

			// Track generation time
			$generation_time = microtime( true ) - $generation_start;

			return array(
				'id'          => $block_id,
				'type'        => $this->block_types[ $block_type ]['name'],
				'content'     => $block_html,
				'attributes'  => $block_data['attributes'] ?? array(),
				'innerBlocks' => $block_data['innerBlocks'] ?? array(),
				'metadata'    => array(
					'generated_at'    => current_time( 'mysql' ),
					'generation_time' => $generation_time,
					'provider'        => $provider ?? 'default',
					'prompt'          => $prompt,
				),
			);
		} catch ( \Exception $e ) {
			// Log error
			error_log( sprintf(
				'Block generation failed: %s',
				$e->getMessage()
			) );

			throw $e;
		}
	}

	/**
	 * Generate multiple blocks.
	 *
	 * @since    1.0.0
	 * @param    array     $prompts      Array of prompts with block types.
	 * @param    string    $provider     Optional. Specific provider to use.
	 * @param    array     $options      Optional. Generation options.
	 * @return   array                   Array of generated blocks.
	 */
	public function generate_batch( $prompts, $provider = null, $options = array() ) {
		$blocks = array();
		$errors = array();

		foreach ( $prompts as $index => $prompt_data ) {
			try {
				$prompt = is_array( $prompt_data ) ? $prompt_data['prompt'] : $prompt_data;
				$block_type = is_array( $prompt_data ) ? ( $prompt_data['type'] ?? 'paragraph' ) : 'paragraph';

				$blocks[] = $this->generate( $prompt, $block_type, $provider, $options );
			} catch ( \Exception $e ) {
				$errors[ $index ] = $e->getMessage();
			}
		}

		return array(
			'blocks' => $blocks,
			'errors' => $errors,
			'success_count' => count( $blocks ),
			'error_count' => count( $errors ),
		);
	}

	/**
	 * Generate blocks for a page.
	 *
	 * @since    1.0.0
	 * @param    string    $page_content    Page content or structure.
	 * @param    string    $page_type       Type of page.
	 * @param    array     $options         Optional. Generation options.
	 * @return   array                      Array of blocks for the page.
	 */
	public function generate_page_blocks( $page_content, $page_type = 'page', $options = array() ) {
		$blocks = array();

		// Parse content structure
		$structure = $this->parse_content_structure( $page_content );

		// Generate blocks based on structure
		foreach ( $structure as $section ) {
			$block_type = $this->determine_block_type( $section );
			$blocks[] = $this->generate(
				$section['content'] ?? $section,
				$block_type,
				$options['provider'] ?? null,
				$options
			);
		}

		return $blocks;
	}

	/**
	 * Format block data from AI response.
	 *
	 * @since    1.0.0
	 * @param    array     $ai_response    AI response data.
	 * @param    string    $block_type     Type of block.
	 * @return   array                     Formatted block data.
	 */
	private function format_block_data( $ai_response, $block_type ) {
		$block_data = array(
			'attributes' => array(),
			'content'    => '',
		);

		// Extract content from AI response
		if ( is_array( $ai_response ) ) {
			$block_data['content'] = $ai_response['content'] ?? '';
			$block_data['attributes'] = $ai_response['attributes'] ?? array();
		} else {
			$block_data['content'] = $ai_response;
		}

		// Apply block-specific formatting
		switch ( $block_type ) {
			case 'heading':
				$block_data['attributes']['level'] = $block_data['attributes']['level'] ?? 2;
				break;

			case 'list':
				$block_data['attributes']['ordered'] = $block_data['attributes']['ordered'] ?? false;
				break;

			case 'button':
				$block_data['attributes']['text'] = $block_data['content'];
				$block_data['attributes']['url'] = $block_data['attributes']['url'] ?? '#';
				break;

			case 'image':
				$block_data['attributes']['alt'] = $block_data['attributes']['alt'] ?? '';
				$block_data['attributes']['caption'] = $block_data['attributes']['caption'] ?? '';
				break;

			case 'columns':
				$block_data['innerBlocks'] = $this->generate_column_blocks(
					$block_data['content'],
					$block_data['attributes']['columns'] ?? 2
				);
				break;
		}

		return $block_data;
	}

	/**
	 * Generate block HTML.
	 *
	 * @since    1.0.0
	 * @param    array     $block_data    Block data.
	 * @param    string    $block_type    Type of block.
	 * @return   string                   Block HTML.
	 */
	private function generate_block_html( $block_data, $block_type ) {
		$block_name = $this->block_types[ $block_type ]['name'];
		$content = $block_data['content'] ?? '';
		$attributes = $block_data['attributes'] ?? array();

		// Generate block comment delimiter
		$block_html = '<!-- wp:' . $block_name;

		// Add attributes if present
		if ( ! empty( $attributes ) ) {
			$block_html .= ' ' . wp_json_encode( $attributes );
		}

		$block_html .= ' -->' . "\n";

		// Add block content based on type
		switch ( $block_type ) {
			case 'paragraph':
				$block_html .= '<p>' . esc_html( $content ) . '</p>';
				break;

			case 'heading':
				$level = $attributes['level'] ?? 2;
				$block_html .= '<h' . $level . '>' . esc_html( $content ) . '</h' . $level . '>';
				break;

			case 'list':
				$tag = ! empty( $attributes['ordered'] ) ? 'ol' : 'ul';
				$items = explode( "\n", $content );
				$block_html .= '<' . $tag . '>';
				foreach ( $items as $item ) {
					if ( trim( $item ) ) {
						$block_html .= '<li>' . esc_html( trim( $item ) ) . '</li>';
					}
				}
				$block_html .= '</' . $tag . '>';
				break;

			case 'button':
				$block_html .= '<div class="wp-block-button">';
				$block_html .= '<a class="wp-block-button__link" href="' . esc_url( $attributes['url'] ?? '#' ) . '">';
				$block_html .= esc_html( $attributes['text'] ?? $content );
				$block_html .= '</a></div>';
				break;

			case 'quote':
				$block_html .= '<blockquote class="wp-block-quote">';
				$block_html .= '<p>' . esc_html( $content ) . '</p>';
				if ( ! empty( $attributes['citation'] ) ) {
					$block_html .= '<cite>' . esc_html( $attributes['citation'] ) . '</cite>';
				}
				$block_html .= '</blockquote>';
				break;

			case 'separator':
				$block_html .= '<hr class="wp-block-separator"/>';
				break;

			case 'spacer':
				$height = $attributes['height'] ?? 100;
				$block_html .= '<div style="height:' . intval( $height ) . 'px"></div>';
				break;

			default:
				// For custom or complex blocks, use provided content
				$block_html .= $content;
				break;
		}

		// Close block comment delimiter
		$block_html .= "\n" . '<!-- /wp:' . $block_name . ' -->' . "\n";

		return $block_html;
	}

	/**
	 * Generate column blocks for columns block.
	 *
	 * @since    1.0.0
	 * @param    string    $content         Content to distribute.
	 * @param    int       $column_count    Number of columns.
	 * @return   array                      Array of column blocks.
	 */
	private function generate_column_blocks( $content, $column_count = 2 ) {
		$columns = array();
		$content_parts = $this->split_content( $content, $column_count );

		foreach ( $content_parts as $part ) {
			$columns[] = array(
				'blockName'  => 'core/column',
				'attrs'      => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'core/paragraph',
						'attrs'       => array(),
						'innerBlocks' => array(),
						'innerHTML'   => '<p>' . esc_html( $part ) . '</p>',
					),
				),
			);
		}

		return $columns;
	}

	/**
	 * Save generated block to database.
	 *
	 * @since    1.0.0
	 * @param    array    $block_data    Block data to save.
	 * @return   int                     Block ID.
	 */
	private function save_generated_block( $block_data ) {
		return $this->db_handler->save_generated_content( $block_data );
	}

	/**
	 * Parse content structure.
	 *
	 * @since    1.0.0
	 * @param    mixed    $content    Content to parse.
	 * @return   array                Parsed structure.
	 */
	private function parse_content_structure( $content ) {
		if ( is_array( $content ) ) {
			return $content;
		}

		// Simple parsing - split by double newlines
		$sections = explode( "\n\n", $content );
		$structure = array();

		foreach ( $sections as $section ) {
			if ( trim( $section ) ) {
				$structure[] = array(
					'content' => trim( $section ),
					'type'    => $this->detect_content_type( $section ),
				);
			}
		}

		return $structure;
	}

	/**
	 * Determine block type from content.
	 *
	 * @since    1.0.0
	 * @param    array|string    $section    Content section.
	 * @return   string                      Block type.
	 */
	private function determine_block_type( $section ) {
		if ( is_array( $section ) && isset( $section['type'] ) ) {
			return $section['type'];
		}

		$content = is_array( $section ) ? $section['content'] : $section;

		// Detect based on content patterns
		if ( preg_match( '/^#+ /m', $content ) ) {
			return 'heading';
		}

		if ( preg_match( '/^[\*\-\+\d]\. /m', $content ) ) {
			return 'list';
		}

		if ( preg_match( '/^>/', $content ) ) {
			return 'quote';
		}

		return 'paragraph';
	}

	/**
	 * Detect content type from text.
	 *
	 * @since    1.0.0
	 * @param    string    $text    Text to analyze.
	 * @return   string             Detected content type.
	 */
	private function detect_content_type( $text ) {
		// Check for headings
		if ( preg_match( '/^#+ /m', $text ) ) {
			return 'heading';
		}

		// Check for lists
		if ( preg_match( '/^[\*\-\+] /m', $text ) ) {
			return 'list';
		}

		// Check for quotes
		if ( preg_match( '/^>/', $text ) ) {
			return 'quote';
		}

		// Default to paragraph
		return 'paragraph';
	}

	/**
	 * Split content into parts.
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to split.
	 * @param    int       $parts      Number of parts.
	 * @return   array                 Array of content parts.
	 */
	private function split_content( $content, $parts = 2 ) {
		$words = explode( ' ', $content );
		$words_per_part = ceil( count( $words ) / $parts );
		$result = array();

		for ( $i = 0; $i < $parts; $i++ ) {
			$start = $i * $words_per_part;
			$part_words = array_slice( $words, $start, $words_per_part );
			$result[] = implode( ' ', $part_words );
		}

		return $result;
	}

	/**
	 * Get supported block types.
	 *
	 * @since    1.0.0
	 * @return   array    Array of supported block types.
	 */
	public function get_block_types() {
		return $this->block_types;
	}

	/**
	 * Get block type info.
	 *
	 * @since    1.0.0
	 * @param    string    $block_type    Block type key.
	 * @return   array|null               Block type info or null.
	 */
	public function get_block_type_info( $block_type ) {
		return $this->block_types[ $block_type ] ?? null;
	}

	/**
	 * Register custom block type.
	 *
	 * @since    1.0.0
	 * @param    string    $key     Block type key.
	 * @param    array     $info    Block type information.
	 */
	public function register_block_type( $key, $info ) {
		$this->block_types[ $key ] = $info;
	}
}