<?php
/**
 * Output Validator for AI-Generated Content
 *
 * Validates and ensures quality of AI-generated content for WordPress.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Output Validator Class
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Output_Validator {

	/**
	 * Validation rules by content type
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $rules    Validation rules.
	 */
	private $rules = [];

	/**
	 * Validation errors
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $errors    Current validation errors.
	 */
	private $errors = [];

	/**
	 * Warnings (non-critical issues)
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $warnings    Current validation warnings.
	 */
	private $warnings = [];

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->init_validation_rules();
	}

	/**
	 * Initialize validation rules
	 *
	 * @since    1.0.0
	 */
	private function init_validation_rules() {
		$this->rules = [
			'heading' => [
				'min_length' => 3,
				'max_length' => 60,
				'no_ending_punctuation' => true,
				'no_all_caps' => true,
				'no_special_chars_start' => true,
			],
			'meta_description' => [
				'min_length' => 120,
				'max_length' => 160,
				'contains_keyword' => true,
				'no_duplicate_phrases' => true,
				'has_call_to_action' => true,
			],
			'paragraph' => [
				'min_length' => 50,
				'max_length' => 300,
				'min_sentences' => 2,
				'max_sentences' => 8,
				'no_lorem_ipsum' => true,
			],
			'page_content' => [
				'min_word_count' => 300,
				'max_word_count' => 5000,
				'has_headings' => true,
				'proper_heading_hierarchy' => true,
				'unique_headings' => true,
			],
			'block_markup' => [
				'valid_html' => true,
				'valid_blocks' => true,
				'no_deprecated_blocks' => true,
				'proper_nesting' => true,
			],
			'seo_content' => [
				'keyword_density_min' => 0.005,
				'keyword_density_max' => 0.03,
				'has_internal_links' => true,
				'has_external_links' => false,
				'readable_urls' => true,
			],
			'accessibility' => [
				'images_have_alt' => true,
				'proper_heading_hierarchy' => true,
				'links_have_text' => true,
				'color_contrast' => true,
				'form_labels' => true,
			],
		];
	}

	/**
	 * Validate content
	 *
	 * @since    1.0.0
	 * @param    string    $content       Content to validate.
	 * @param    string    $type          Content type.
	 * @param    array     $context       Additional context.
	 * @return   array                    Validation result.
	 */
	public function validate( $content, $type, $context = [] ) {
		$this->errors = [];
		$this->warnings = [];

		if ( ! isset( $this->rules[ $type ] ) ) {
			$this->errors[] = sprintf( __( 'Unknown content type: %s', 'wp-ai-site-generator' ), $type );
			return $this->get_validation_result();
		}

		$rules = $this->rules[ $type ];

		foreach ( $rules as $rule => $value ) {
			$this->apply_rule( $content, $rule, $value, $context );
		}

		// Additional type-specific validation
		$this->apply_type_specific_validation( $content, $type, $context );

		return $this->get_validation_result();
	}

	/**
	 * Apply a validation rule
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to validate.
	 * @param    string    $rule       Rule name.
	 * @param    mixed     $value      Rule value/parameter.
	 * @param    array     $context    Additional context.
	 */
	private function apply_rule( $content, $rule, $value, $context ) {
		switch ( $rule ) {
			case 'min_length':
				if ( strlen( $content ) < $value ) {
					$this->errors[] = sprintf(
						__( 'Content is too short. Minimum length: %d characters, actual: %d', 'wp-ai-site-generator' ),
						$value,
						strlen( $content )
					);
				}
				break;

			case 'max_length':
				if ( strlen( $content ) > $value ) {
					$this->errors[] = sprintf(
						__( 'Content is too long. Maximum length: %d characters, actual: %d', 'wp-ai-site-generator' ),
						$value,
						strlen( $content )
					);
				}
				break;

			case 'min_word_count':
				$word_count = str_word_count( strip_tags( $content ) );
				if ( $word_count < $value ) {
					$this->errors[] = sprintf(
						__( 'Content has too few words. Minimum: %d words, actual: %d', 'wp-ai-site-generator' ),
						$value,
						$word_count
					);
				}
				break;

			case 'max_word_count':
				$word_count = str_word_count( strip_tags( $content ) );
				if ( $word_count > $value ) {
					$this->warnings[] = sprintf(
						__( 'Content exceeds recommended word count. Maximum: %d words, actual: %d', 'wp-ai-site-generator' ),
						$value,
						$word_count
					);
				}
				break;

			case 'contains_keyword':
				if ( $value && ! empty( $context['keyword'] ) ) {
					if ( stripos( $content, $context['keyword'] ) === false ) {
						$this->errors[] = sprintf(
							__( 'Content does not contain the required keyword: %s', 'wp-ai-site-generator' ),
							$context['keyword']
						);
					}
				}
				break;

			case 'no_ending_punctuation':
				if ( $value && preg_match( '/[.!?,;:]$/', trim( $content ) ) ) {
					$this->warnings[] = __( 'Headings should not end with punctuation', 'wp-ai-site-generator' );
				}
				break;

			case 'no_all_caps':
				if ( $value && strtoupper( $content ) === $content && strlen( $content ) > 3 ) {
					$this->warnings[] = __( 'Avoid using all capital letters', 'wp-ai-site-generator' );
				}
				break;

			case 'no_lorem_ipsum':
				if ( $value && preg_match( '/lorem\s+ipsum/i', $content ) ) {
					$this->errors[] = __( 'Content contains placeholder text (Lorem Ipsum)', 'wp-ai-site-generator' );
				}
				break;

			case 'valid_html':
				if ( $value && ! $this->is_valid_html( $content ) ) {
					$this->errors[] = __( 'Content contains invalid HTML', 'wp-ai-site-generator' );
				}
				break;

			case 'valid_blocks':
				if ( $value && ! $this->validate_block_markup( $content ) ) {
					$this->errors[] = __( 'Content contains invalid WordPress block markup', 'wp-ai-site-generator' );
				}
				break;

			case 'has_headings':
				if ( $value && ! preg_match( '/<h[1-6][^>]*>/i', $content ) ) {
					$this->errors[] = __( 'Content should contain headings for structure', 'wp-ai-site-generator' );
				}
				break;

			case 'proper_heading_hierarchy':
				if ( $value && ! $this->validate_heading_hierarchy( $content ) ) {
					$this->errors[] = __( 'Improper heading hierarchy detected', 'wp-ai-site-generator' );
				}
				break;

			case 'keyword_density_min':
			case 'keyword_density_max':
				if ( ! empty( $context['keyword'] ) ) {
					$this->validate_keyword_density( $content, $context['keyword'], $rule, $value );
				}
				break;

			case 'images_have_alt':
				if ( $value && ! $this->validate_image_alt_text( $content ) ) {
					$this->errors[] = __( 'Images are missing alt text', 'wp-ai-site-generator' );
				}
				break;

			case 'links_have_text':
				if ( $value && ! $this->validate_link_text( $content ) ) {
					$this->warnings[] = __( 'Links should have descriptive text', 'wp-ai-site-generator' );
				}
				break;
		}
	}

	/**
	 * Apply type-specific validation
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to validate.
	 * @param    string    $type       Content type.
	 * @param    array     $context    Additional context.
	 */
	private function apply_type_specific_validation( $content, $type, $context ) {
		switch ( $type ) {
			case 'page_content':
				$this->validate_page_structure( $content, $context );
				break;

			case 'block_markup':
				$this->validate_blocks_deep( $content );
				break;

			case 'seo_content':
				$this->validate_seo_requirements( $content, $context );
				break;

			case 'accessibility':
				$this->validate_accessibility_requirements( $content );
				break;
		}
	}

	/**
	 * Validate HTML
	 *
	 * @since    1.0.0
	 * @param    string    $content    HTML content.
	 * @return   bool                  True if valid.
	 */
	private function is_valid_html( $content ) {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$result = $dom->loadHTML( '<html><body>' . $content . '</body></html>' );
		libxml_clear_errors();
		return $result;
	}

	/**
	 * Validate WordPress block markup
	 *
	 * @since    1.0.0
	 * @param    string    $content    Block content.
	 * @return   bool                  True if valid.
	 */
	private function validate_block_markup( $content ) {
		// Check for proper block comment structure
		if ( strpos( $content, '<!-- wp:' ) === false ) {
			return false;
		}

		// Check for matching opening and closing comments
		preg_match_all( '/<!-- wp:([a-z0-9\-\/]+)(\s|>)/i', $content, $opening );
		preg_match_all( '/<!-- \/wp:([a-z0-9\-\/]+) -->/i', $content, $closing );

		if ( count( $opening[1] ) !== count( $closing[1] ) ) {
			return false;
		}

		// Validate block names
		$valid_blocks = $this->get_valid_block_names();
		foreach ( $opening[1] as $block_name ) {
			if ( ! in_array( $block_name, $valid_blocks, true ) && strpos( $block_name, '/' ) === false ) {
				$this->warnings[] = sprintf(
					__( 'Unknown block type: %s', 'wp-ai-site-generator' ),
					$block_name
				);
			}
		}

		return true;
	}

	/**
	 * Get valid WordPress block names
	 *
	 * @since    1.0.0
	 * @return   array    Valid block names.
	 */
	private function get_valid_block_names() {
		return [
			'paragraph', 'heading', 'image', 'list', 'quote',
			'gallery', 'columns', 'column', 'group', 'button',
			'buttons', 'separator', 'spacer', 'table', 'video',
			'audio', 'file', 'media-text', 'cover', 'code',
			'preformatted', 'pullquote', 'verse', 'html',
			'embed', 'shortcode', 'archives', 'calendar',
			'categories', 'latest-comments', 'latest-posts',
			'page-list', 'rss', 'search', 'social-links',
			'social-link', 'tag-cloud', 'navigation', 'site-logo',
			'site-title', 'site-tagline', 'query', 'post-title',
			'post-excerpt', 'post-featured-image', 'post-content',
			'post-author', 'post-date', 'post-terms', 'post-navigation-link',
			'read-more', 'comments', 'comment-template', 'comment-content',
			'comment-author-name', 'comment-date', 'comment-edit-link',
			'comment-reply-link', 'navigation-link', 'navigation-submenu',
			'loginout', 'term-description', 'query-title', 'post-author-biography',
		];
	}

	/**
	 * Validate heading hierarchy
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content with headings.
	 * @return   bool                  True if valid hierarchy.
	 */
	private function validate_heading_hierarchy( $content ) {
		preg_match_all( '/<h([1-6])[^>]*>/i', $content, $matches );

		if ( empty( $matches[1] ) ) {
			return true;
		}

		$levels = array_map( 'intval', $matches[1] );
		$previous_level = 0;

		// Check for single H1
		$h1_count = count( array_filter( $levels, function( $level ) {
			return $level === 1;
		} ) );

		if ( $h1_count > 1 ) {
			$this->warnings[] = __( 'Multiple H1 tags detected. Use only one H1 per page', 'wp-ai-site-generator' );
		}

		// Check hierarchy
		foreach ( $levels as $level ) {
			if ( $previous_level > 0 && $level > $previous_level + 1 ) {
				return false;
			}
			$previous_level = $level;
		}

		return true;
	}

	/**
	 * Validate keyword density
	 *
	 * @since    1.0.0
	 * @param    string    $content     Content to check.
	 * @param    string    $keyword     Keyword to check.
	 * @param    string    $rule        Rule name.
	 * @param    float     $value       Threshold value.
	 */
	private function validate_keyword_density( $content, $keyword, $rule, $value ) {
		$text = strip_tags( $content );
		$word_count = str_word_count( $text );
		$keyword_count = substr_count( strtolower( $text ), strtolower( $keyword ) );

		if ( $word_count > 0 ) {
			$density = $keyword_count / $word_count;

			if ( $rule === 'keyword_density_min' && $density < $value ) {
				$this->warnings[] = sprintf(
					__( 'Keyword density too low: %.1f%% (minimum: %.1f%%)', 'wp-ai-site-generator' ),
					$density * 100,
					$value * 100
				);
			} elseif ( $rule === 'keyword_density_max' && $density > $value ) {
				$this->warnings[] = sprintf(
					__( 'Keyword density too high: %.1f%% (maximum: %.1f%%)', 'wp-ai-site-generator' ),
					$density * 100,
					$value * 100
				);
			}
		}
	}

	/**
	 * Validate image alt text
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content with images.
	 * @return   bool                  True if all images have alt text.
	 */
	private function validate_image_alt_text( $content ) {
		preg_match_all( '/<img[^>]+>/i', $content, $images );

		foreach ( $images[0] as $image ) {
			if ( strpos( $image, 'alt=' ) === false ) {
				return false;
			}

			// Check for empty alt text (unless decorative)
			if ( preg_match( '/alt=["\']\s*["\']/', $image ) && strpos( $image, 'role="presentation"' ) === false ) {
				$this->warnings[] = __( 'Image has empty alt text', 'wp-ai-site-generator' );
			}
		}

		return true;
	}

	/**
	 * Validate link text
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content with links.
	 * @return   bool                  True if links have good text.
	 */
	private function validate_link_text( $content ) {
		preg_match_all( '/<a[^>]*>(.*?)<\/a>/i', $content, $links );

		$generic_phrases = ['click here', 'read more', 'here', 'link', 'more'];
		$issues_found = false;

		foreach ( $links[1] as $link_text ) {
			$clean_text = strip_tags( $link_text );
			$clean_text = trim( strtolower( $clean_text ) );

			if ( empty( $clean_text ) ) {
				$issues_found = true;
			} elseif ( in_array( $clean_text, $generic_phrases, true ) ) {
				$issues_found = true;
			}
		}

		return ! $issues_found;
	}

	/**
	 * Validate page structure
	 *
	 * @since    1.0.0
	 * @param    string    $content    Page content.
	 * @param    array     $context    Context information.
	 */
	private function validate_page_structure( $content, $context ) {
		// Check for required sections based on page type
		if ( isset( $context['page_type'] ) ) {
			$required_sections = $this->get_required_sections( $context['page_type'] );

			foreach ( $required_sections as $section ) {
				if ( ! $this->content_has_section( $content, $section ) ) {
					$this->warnings[] = sprintf(
						__( 'Page is missing recommended section: %s', 'wp-ai-site-generator' ),
						$section
					);
				}
			}
		}

		// Check for proper content flow
		$this->validate_content_flow( $content );
	}

	/**
	 * Get required sections for page type
	 *
	 * @since    1.0.0
	 * @param    string    $page_type    Type of page.
	 * @return   array                   Required sections.
	 */
	private function get_required_sections( $page_type ) {
		$sections = [
			'homepage' => ['hero', 'features', 'about', 'cta'],
			'about' => ['introduction', 'mission', 'team', 'values'],
			'services' => ['overview', 'service-list', 'process', 'cta'],
			'contact' => ['form', 'information', 'map', 'hours'],
			'landing' => ['hero', 'benefits', 'proof', 'offer', 'cta'],
		];

		return $sections[ $page_type ] ?? [];
	}

	/**
	 * Check if content has a section
	 *
	 * @since    1.0.0
	 * @param    string    $content     Content to check.
	 * @param    string    $section     Section identifier.
	 * @return   bool                   True if section exists.
	 */
	private function content_has_section( $content, $section ) {
		// Check for common section indicators
		$indicators = [
			'hero' => ['hero', 'banner', 'jumbotron'],
			'features' => ['features', 'benefits', 'services'],
			'about' => ['about', 'who we are', 'our story'],
			'cta' => ['contact', 'get started', 'call to action'],
			'form' => ['form', 'contact-form', 'wpforms', 'contact-form-7'],
			'mission' => ['mission', 'vision', 'purpose'],
			'team' => ['team', 'our people', 'leadership'],
			'values' => ['values', 'principles', 'beliefs'],
		];

		$section_indicators = $indicators[ $section ] ?? [ $section ];

		foreach ( $section_indicators as $indicator ) {
			if ( stripos( $content, $indicator ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate content flow
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to validate.
	 */
	private function validate_content_flow( $content ) {
		// Check for abrupt transitions
		$paragraphs = preg_split( '/<\/?(p|div|section)[^>]*>/i', $content );
		$paragraphs = array_filter( array_map( 'trim', $paragraphs ) );

		if ( count( $paragraphs ) > 1 ) {
			// Check for very short paragraphs that might indicate incomplete content
			foreach ( $paragraphs as $paragraph ) {
				if ( strlen( strip_tags( $paragraph ) ) > 10 && strlen( strip_tags( $paragraph ) ) < 30 ) {
					$this->warnings[] = __( 'Very short paragraph detected. Consider expanding for better flow', 'wp-ai-site-generator' );
					break;
				}
			}
		}
	}

	/**
	 * Validate blocks deeply
	 *
	 * @since    1.0.0
	 * @param    string    $content    Block content.
	 */
	private function validate_blocks_deep( $content ) {
		// Parse blocks
		if ( function_exists( 'parse_blocks' ) ) {
			$blocks = parse_blocks( $content );
			$this->validate_parsed_blocks( $blocks );
		}
	}

	/**
	 * Validate parsed blocks
	 *
	 * @since    1.0.0
	 * @param    array    $blocks    Parsed blocks.
	 */
	private function validate_parsed_blocks( $blocks ) {
		foreach ( $blocks as $block ) {
			// Check for deprecated blocks
			if ( $this->is_deprecated_block( $block['blockName'] ) ) {
				$this->warnings[] = sprintf(
					__( 'Deprecated block used: %s', 'wp-ai-site-generator' ),
					$block['blockName']
				);
			}

			// Validate block attributes
			if ( ! empty( $block['attrs'] ) ) {
				$this->validate_block_attributes( $block['blockName'], $block['attrs'] );
			}

			// Recursively validate inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->validate_parsed_blocks( $block['innerBlocks'] );
			}
		}
	}

	/**
	 * Check if block is deprecated
	 *
	 * @since    1.0.0
	 * @param    string    $block_name    Block name.
	 * @return   bool                     True if deprecated.
	 */
	private function is_deprecated_block( $block_name ) {
		$deprecated = [
			'core/subhead',
			'core/text-columns',
		];

		return in_array( $block_name, $deprecated, true );
	}

	/**
	 * Validate block attributes
	 *
	 * @since    1.0.0
	 * @param    string    $block_name    Block name.
	 * @param    array     $attributes    Block attributes.
	 */
	private function validate_block_attributes( $block_name, $attributes ) {
		// Validate image blocks
		if ( $block_name === 'core/image' ) {
			if ( empty( $attributes['alt'] ) && empty( $attributes['role'] ) ) {
				$this->warnings[] = __( 'Image block missing alt text', 'wp-ai-site-generator' );
			}
		}

		// Validate heading blocks
		if ( $block_name === 'core/heading' ) {
			if ( isset( $attributes['level'] ) && $attributes['level'] > 6 ) {
				$this->errors[] = __( 'Invalid heading level', 'wp-ai-site-generator' );
			}
		}

		// Validate button blocks
		if ( $block_name === 'core/button' ) {
			if ( empty( $attributes['text'] ) && empty( $attributes['placeholder'] ) ) {
				$this->errors[] = __( 'Button block missing text', 'wp-ai-site-generator' );
			}
		}
	}

	/**
	 * Validate SEO requirements
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to validate.
	 * @param    array     $context    Context information.
	 */
	private function validate_seo_requirements( $content, $context ) {
		// Check for meta title
		if ( isset( $context['meta_title'] ) ) {
			$title_length = strlen( $context['meta_title'] );
			if ( $title_length < 30 || $title_length > 60 ) {
				$this->warnings[] = sprintf(
					__( 'Meta title length should be 30-60 characters (current: %d)', 'wp-ai-site-generator' ),
					$title_length
				);
			}
		}

		// Check for internal links
		if ( ! preg_match( '/<a[^>]*href=["\'](?!http|\/\/|#)/', $content ) ) {
			$this->warnings[] = __( 'Content should include internal links', 'wp-ai-site-generator' );
		}

		// Check for headings with keywords
		if ( ! empty( $context['keyword'] ) ) {
			if ( ! preg_match( '/<h[1-6][^>]*>.*?' . preg_quote( $context['keyword'], '/' ) . '/i', $content ) ) {
				$this->warnings[] = __( 'Keywords should appear in headings', 'wp-ai-site-generator' );
			}
		}
	}

	/**
	 * Validate accessibility requirements
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to validate.
	 */
	private function validate_accessibility_requirements( $content ) {
		// Check for skip links
		if ( strpos( $content, 'skip-link' ) === false && strpos( $content, 'skip-to-content' ) === false ) {
			$this->warnings[] = __( 'Consider adding skip navigation links', 'wp-ai-site-generator' );
		}

		// Check for ARIA landmarks
		$landmarks = ['main', 'navigation', 'banner', 'contentinfo'];
		$has_landmarks = false;

		foreach ( $landmarks as $landmark ) {
			if ( strpos( $content, 'role="' . $landmark . '"' ) !== false ) {
				$has_landmarks = true;
				break;
			}
		}

		if ( ! $has_landmarks && strpos( $content, '<main' ) === false && strpos( $content, '<nav' ) === false ) {
			$this->warnings[] = __( 'Consider using ARIA landmarks or semantic HTML5 elements', 'wp-ai-site-generator' );
		}

		// Check for form labels
		if ( preg_match( '/<input[^>]*type=["\'](?!hidden|submit|button)/', $content ) ) {
			if ( ! preg_match( '/<label/', $content ) ) {
				$this->errors[] = __( 'Form inputs must have associated labels', 'wp-ai-site-generator' );
			}
		}

		// Check color contrast (simplified check)
		$this->validate_color_contrast( $content );
	}

	/**
	 * Validate color contrast
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to check.
	 */
	private function validate_color_contrast( $content ) {
		// Check for problematic color combinations
		$problematic_combos = [
			['#ffff00', '#ffffff'], // Yellow on white
			['#00ff00', '#ffffff'], // Light green on white
			['#ff0000', '#00ff00'], // Red on green
		];

		foreach ( $problematic_combos as $combo ) {
			if ( strpos( $content, $combo[0] ) !== false && strpos( $content, $combo[1] ) !== false ) {
				$this->warnings[] = __( 'Potential color contrast issue detected', 'wp-ai-site-generator' );
				break;
			}
		}
	}

	/**
	 * Get validation result
	 *
	 * @since    1.0.0
	 * @return   array    Validation result.
	 */
	private function get_validation_result() {
		$has_errors = ! empty( $this->errors );
		$has_warnings = ! empty( $this->warnings );

		return [
			'valid' => ! $has_errors,
			'errors' => $this->errors,
			'warnings' => $this->warnings,
			'score' => $this->calculate_validation_score(),
			'status' => $has_errors ? 'error' : ( $has_warnings ? 'warning' : 'success' ),
		];
	}

	/**
	 * Calculate validation score
	 *
	 * @since    1.0.0
	 * @return   float    Score from 0 to 100.
	 */
	private function calculate_validation_score() {
		$base_score = 100;
		$error_penalty = 10;
		$warning_penalty = 3;

		$score = $base_score;
		$score -= count( $this->errors ) * $error_penalty;
		$score -= count( $this->warnings ) * $warning_penalty;

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Validate batch of content
	 *
	 * @since    1.0.0
	 * @param    array    $content_items    Array of content items.
	 * @return   array                      Batch validation results.
	 */
	public function validate_batch( $content_items ) {
		$results = [];

		foreach ( $content_items as $key => $item ) {
			$results[ $key ] = $this->validate(
				$item['content'],
				$item['type'],
				$item['context'] ?? []
			);
		}

		return [
			'results' => $results,
			'summary' => $this->get_batch_summary( $results ),
		];
	}

	/**
	 * Get batch validation summary
	 *
	 * @since    1.0.0
	 * @param    array    $results    Individual validation results.
	 * @return   array                Summary statistics.
	 */
	private function get_batch_summary( $results ) {
		$total = count( $results );
		$valid = 0;
		$with_warnings = 0;
		$total_score = 0;

		foreach ( $results as $result ) {
			if ( $result['valid'] ) {
				$valid++;
			}
			if ( ! empty( $result['warnings'] ) ) {
				$with_warnings++;
			}
			$total_score += $result['score'];
		}

		return [
			'total' => $total,
			'valid' => $valid,
			'invalid' => $total - $valid,
			'with_warnings' => $with_warnings,
			'average_score' => $total > 0 ? round( $total_score / $total, 2 ) : 0,
			'success_rate' => $total > 0 ? round( ( $valid / $total ) * 100, 2 ) : 0,
		];
	}

	/**
	 * Fix common issues automatically
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to fix.
	 * @param    string    $type       Content type.
	 * @return   array                 Fixed content and changes made.
	 */
	public function auto_fix( $content, $type ) {
		$original = $content;
		$changes = [];

		// Fix heading punctuation
		if ( in_array( $type, ['heading'], true ) ) {
			$content = rtrim( $content, '.!?,;:' );
			if ( $content !== $original ) {
				$changes[] = 'Removed ending punctuation from heading';
			}
		}

		// Fix HTML entities
		$content = str_replace( '&nbsp;', ' ', $content );
		$content = str_replace( '  ', ' ', $content );

		// Fix empty alt attributes
		$content = preg_replace( '/alt=["\']\s*["\']/', 'alt="Image"', $content );

		// Fix generic link text
		$generic_replacements = [
			'>click here<' => '>Learn More<',
			'>here<' => '>View Details<',
			'>read more<' => '>Continue Reading<',
		];

		foreach ( $generic_replacements as $search => $replace ) {
			if ( stripos( $content, $search ) !== false ) {
				$content = str_ireplace( $search, $replace, $content );
				$changes[] = 'Improved generic link text';
			}
		}

		return [
			'content' => $content,
			'changes' => $changes,
			'modified' => $content !== $original,
		];
	}

	/**
	 * Get validation rules for a type
	 *
	 * @since    1.0.0
	 * @param    string    $type    Content type.
	 * @return   array              Rules for the type.
	 */
	public function get_rules( $type ) {
		return $this->rules[ $type ] ?? [];
	}

	/**
	 * Add custom validation rule
	 *
	 * @since    1.0.0
	 * @param    string    $type     Content type.
	 * @param    string    $rule     Rule name.
	 * @param    mixed     $value    Rule value.
	 */
	public function add_rule( $type, $rule, $value ) {
		if ( ! isset( $this->rules[ $type ] ) ) {
			$this->rules[ $type ] = [];
		}

		$this->rules[ $type ][ $rule ] = $value;
	}

	/**
	 * Remove validation rule
	 *
	 * @since    1.0.0
	 * @param    string    $type    Content type.
	 * @param    string    $rule    Rule name.
	 */
	public function remove_rule( $type, $rule ) {
		if ( isset( $this->rules[ $type ][ $rule ] ) ) {
			unset( $this->rules[ $type ][ $rule ] );
		}
	}
}