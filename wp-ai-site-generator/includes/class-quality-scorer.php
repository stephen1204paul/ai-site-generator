<?php
/**
 * Quality Scorer for AI-Generated Content
 *
 * Provides comprehensive quality scoring for AI-generated content.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Quality Scorer Class
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Quality_Scorer {

	/**
	 * Scoring weights for different aspects
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $weights    Scoring weights.
	 */
	private $weights = [
		'seo' => 0.25,
		'readability' => 0.20,
		'relevance' => 0.20,
		'technical' => 0.15,
		'accessibility' => 0.10,
		'engagement' => 0.10,
	];

	/**
	 * Score thresholds
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $thresholds    Score thresholds.
	 */
	private $thresholds = [
		'excellent' => 90,
		'good' => 75,
		'fair' => 60,
		'poor' => 40,
	];

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		// Allow filtering of weights
		$this->weights = apply_filters( 'waisg_quality_scorer_weights', $this->weights );
		$this->thresholds = apply_filters( 'waisg_quality_scorer_thresholds', $this->thresholds );
	}

	/**
	 * Calculate overall quality score
	 *
	 * @since    1.0.0
	 * @param    string    $content     Content to score.
	 * @param    array     $context     Context information.
	 * @return   array                  Score results.
	 */
	public function calculate_score( $content, $context = [] ) {
		$scores = [
			'seo' => $this->calculate_seo_score( $content, $context ),
			'readability' => $this->calculate_readability_score( $content ),
			'relevance' => $this->calculate_relevance_score( $content, $context ),
			'technical' => $this->calculate_technical_score( $content ),
			'accessibility' => $this->calculate_accessibility_score( $content ),
			'engagement' => $this->calculate_engagement_score( $content ),
		];

		// Calculate weighted total
		$total_score = 0;
		foreach ( $scores as $aspect => $score ) {
			$total_score += $score * $this->weights[ $aspect ];
		}

		return [
			'total_score' => round( $total_score, 2 ),
			'breakdown' => $scores,
			'grade' => $this->get_grade( $total_score ),
			'recommendations' => $this->get_recommendations( $scores ),
			'strengths' => $this->identify_strengths( $scores ),
			'weaknesses' => $this->identify_weaknesses( $scores ),
			'metadata' => $this->get_score_metadata( $content, $context ),
		];
	}

	/**
	 * Calculate SEO score
	 *
	 * @since    1.0.0
	 * @param    string    $content     Content to analyze.
	 * @param    array     $context     Context information.
	 * @return   float                  SEO score (0-100).
	 */
	private function calculate_seo_score( $content, $context ) {
		$score = 100;
		$deductions = 0;

		// Check for primary keyword
		if ( isset( $context['keyword'] ) ) {
			$keyword = $context['keyword'];
			$text = strip_tags( $content );
			$text_lower = strtolower( $text );
			$keyword_lower = strtolower( $keyword );

			// Keyword in title
			if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/i', $content, $h1_match ) ) {
				if ( stripos( $h1_match[1], $keyword ) === false ) {
					$deductions += 10;
				}
			} else {
				$deductions += 15; // No H1
			}

			// Keyword density
			$word_count = str_word_count( $text );
			if ( $word_count > 0 ) {
				$keyword_count = substr_count( $text_lower, $keyword_lower );
				$density = ( $keyword_count / $word_count ) * 100;

				if ( $density < 0.5 ) {
					$deductions += 10; // Too low
				} elseif ( $density > 3 ) {
					$deductions += 10; // Too high (keyword stuffing)
				}
			}

			// Keyword in first paragraph
			$paragraphs = preg_split( '/<\/p>/i', $content );
			if ( ! empty( $paragraphs[0] ) && stripos( $paragraphs[0], $keyword ) === false ) {
				$deductions += 5;
			}
		}

		// Check meta description length (if provided)
		if ( isset( $context['meta_description'] ) ) {
			$meta_length = strlen( $context['meta_description'] );
			if ( $meta_length < 120 || $meta_length > 160 ) {
				$deductions += 10;
			}
		}

		// Check for internal links
		if ( ! preg_match( '/<a[^>]*href=["\'](?!http|\/\/|#)[^"\']*["\'][^>]*>/i', $content ) ) {
			$deductions += 10;
		}

		// Check for images with alt text
		preg_match_all( '/<img[^>]+>/i', $content, $images );
		if ( ! empty( $images[0] ) ) {
			$images_without_alt = 0;
			foreach ( $images[0] as $img ) {
				if ( strpos( $img, 'alt=' ) === false || preg_match( '/alt=["\']\s*["\']/', $img ) ) {
					$images_without_alt++;
				}
			}
			if ( $images_without_alt > 0 ) {
				$deductions += min( 15, $images_without_alt * 5 );
			}
		}

		// Check heading structure
		if ( ! $this->has_proper_heading_structure( $content ) ) {
			$deductions += 10;
		}

		// Check content length
		$word_count = str_word_count( strip_tags( $content ) );
		if ( $word_count < 300 ) {
			$deductions += 20;
		} elseif ( $word_count < 600 ) {
			$deductions += 10;
		}

		return max( 0, $score - $deductions );
	}

	/**
	 * Calculate readability score
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to analyze.
	 * @return   float                 Readability score (0-100).
	 */
	private function calculate_readability_score( $content ) {
		$text = strip_tags( $content );

		if ( empty( $text ) ) {
			return 0;
		}

		// Calculate Flesch Reading Ease score
		$flesch_score = $this->calculate_flesch_reading_ease( $text );

		// Convert Flesch score to 0-100 scale
		// Flesch scores: 90-100 = very easy, 60-70 = standard, 0-30 = very difficult
		if ( $flesch_score >= 60 ) {
			$score = 100;
		} elseif ( $flesch_score >= 50 ) {
			$score = 90;
		} elseif ( $flesch_score >= 40 ) {
			$score = 75;
		} elseif ( $flesch_score >= 30 ) {
			$score = 60;
		} else {
			$score = max( 30, $flesch_score * 2 );
		}

		// Additional readability factors
		$adjustments = 0;

		// Check paragraph length
		$paragraphs = explode( "\n\n", $text );
		$long_paragraphs = 0;
		foreach ( $paragraphs as $paragraph ) {
			if ( str_word_count( $paragraph ) > 150 ) {
				$long_paragraphs++;
			}
		}
		if ( $long_paragraphs > 2 ) {
			$adjustments -= 10;
		}

		// Check sentence variety
		if ( $this->has_sentence_variety( $text ) ) {
			$adjustments += 5;
		}

		// Check for subheadings
		$heading_count = preg_match_all( '/<h[2-6][^>]*>/i', $content, $matches );
		$word_count = str_word_count( $text );
		if ( $word_count > 300 && $heading_count < 2 ) {
			$adjustments -= 10;
		}

		// Check for lists (improve scannability)
		if ( preg_match( '/<(ul|ol)[^>]*>/i', $content ) ) {
			$adjustments += 5;
		}

		return max( 0, min( 100, $score + $adjustments ) );
	}

	/**
	 * Calculate Flesch Reading Ease score
	 *
	 * @since    1.0.0
	 * @param    string    $text    Text to analyze.
	 * @return   float              Flesch score.
	 */
	private function calculate_flesch_reading_ease( $text ) {
		$sentences = $this->count_sentences( $text );
		$words = str_word_count( $text );
		$syllables = $this->count_syllables( $text );

		if ( $sentences == 0 || $words == 0 ) {
			return 0;
		}

		$avg_sentence_length = $words / $sentences;
		$avg_syllables_per_word = $syllables / $words;

		// Flesch Reading Ease formula
		$score = 206.835 - ( 1.015 * $avg_sentence_length ) - ( 84.6 * $avg_syllables_per_word );

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Count sentences in text
	 *
	 * @since    1.0.0
	 * @param    string    $text    Text to analyze.
	 * @return   int                Number of sentences.
	 */
	private function count_sentences( $text ) {
		// Remove abbreviations to avoid false sentence endings
		$text = preg_replace( '/\b(?:Mr|Mrs|Dr|Ms|Prof|Sr|Jr)\./i', '', $text );

		// Count sentence endings
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		return count( $sentences );
	}

	/**
	 * Count syllables in text
	 *
	 * @since    1.0.0
	 * @param    string    $text    Text to analyze.
	 * @return   int                Number of syllables.
	 */
	private function count_syllables( $text ) {
		$words = str_word_count( $text, 1 );
		$total_syllables = 0;

		foreach ( $words as $word ) {
			$total_syllables += $this->count_word_syllables( $word );
		}

		return $total_syllables;
	}

	/**
	 * Count syllables in a word
	 *
	 * @since    1.0.0
	 * @param    string    $word    Word to analyze.
	 * @return   int                Number of syllables.
	 */
	private function count_word_syllables( $word ) {
		$word = strtolower( $word );
		$count = 0;
		$previous_was_vowel = false;

		$vowels = ['a', 'e', 'i', 'o', 'u', 'y'];

		for ( $i = 0; $i < strlen( $word ); $i++ ) {
			$is_vowel = in_array( $word[ $i ], $vowels, true );

			if ( $is_vowel && ! $previous_was_vowel ) {
				$count++;
			}

			$previous_was_vowel = $is_vowel;
		}

		// Adjust for silent e
		if ( substr( $word, -1 ) === 'e' ) {
			$count--;
		}

		// Ensure at least one syllable
		return max( 1, $count );
	}

	/**
	 * Check for sentence variety
	 *
	 * @since    1.0.0
	 * @param    string    $text    Text to analyze.
	 * @return   bool               True if has good variety.
	 */
	private function has_sentence_variety( $text ) {
		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( count( $sentences ) < 3 ) {
			return true; // Not enough sentences to judge
		}

		$lengths = array_map( 'str_word_count', $sentences );
		$std_dev = $this->calculate_standard_deviation( $lengths );

		// Good variety if standard deviation is between 3 and 15 words
		return $std_dev >= 3 && $std_dev <= 15;
	}

	/**
	 * Calculate standard deviation
	 *
	 * @since    1.0.0
	 * @param    array    $values    Array of values.
	 * @return   float               Standard deviation.
	 */
	private function calculate_standard_deviation( $values ) {
		$count = count( $values );

		if ( $count < 2 ) {
			return 0;
		}

		$mean = array_sum( $values ) / $count;
		$variance = 0;

		foreach ( $values as $value ) {
			$variance += pow( $value - $mean, 2 );
		}

		$variance /= ( $count - 1 );
		return sqrt( $variance );
	}

	/**
	 * Calculate relevance score
	 *
	 * @since    1.0.0
	 * @param    string    $content     Content to analyze.
	 * @param    array     $context     Context information.
	 * @return   float                  Relevance score (0-100).
	 */
	private function calculate_relevance_score( $content, $context ) {
		$score = 100;
		$deductions = 0;

		$text = strtolower( strip_tags( $content ) );

		// Check for topic relevance
		if ( isset( $context['topic'] ) ) {
			if ( stripos( $text, strtolower( $context['topic'] ) ) === false ) {
				$deductions += 20;
			}
		}

		// Check for industry terms
		if ( isset( $context['industry'] ) ) {
			$industry_terms = $this->get_industry_terms( $context['industry'] );
			$found_terms = 0;

			foreach ( $industry_terms as $term ) {
				if ( stripos( $text, strtolower( $term ) ) !== false ) {
					$found_terms++;
				}
			}

			if ( $found_terms < 2 ) {
				$deductions += 15;
			}
		}

		// Check for target audience alignment
		if ( isset( $context['audience'] ) ) {
			$audience_indicators = $this->get_audience_indicators( $context['audience'] );
			$found_indicators = 0;

			foreach ( $audience_indicators as $indicator ) {
				if ( stripos( $text, $indicator ) !== false ) {
					$found_indicators++;
				}
			}

			if ( $found_indicators === 0 ) {
				$deductions += 10;
			}
		}

		// Check for brand consistency
		if ( isset( $context['brand_name'] ) ) {
			$brand_mentions = substr_count( $text, strtolower( $context['brand_name'] ) );
			$word_count = str_word_count( $text );

			if ( $word_count > 200 && $brand_mentions === 0 ) {
				$deductions += 10;
			} elseif ( $word_count > 0 && ( $brand_mentions / $word_count ) > 0.05 ) {
				$deductions += 10; // Over-use of brand name
			}
		}

		// Check for required elements
		if ( isset( $context['required_elements'] ) ) {
			foreach ( $context['required_elements'] as $element ) {
				if ( stripos( $content, $element ) === false ) {
					$deductions += 5;
				}
			}
		}

		return max( 0, $score - $deductions );
	}

	/**
	 * Get industry-specific terms
	 *
	 * @since    1.0.0
	 * @param    string    $industry    Industry name.
	 * @return   array                  Industry terms.
	 */
	private function get_industry_terms( $industry ) {
		$terms = [
			'technology' => ['innovation', 'digital', 'software', 'solution', 'platform', 'technology'],
			'healthcare' => ['patient', 'care', 'health', 'medical', 'treatment', 'wellness'],
			'finance' => ['investment', 'financial', 'portfolio', 'assets', 'returns', 'growth'],
			'retail' => ['products', 'shopping', 'customer', 'quality', 'selection', 'service'],
			'education' => ['learning', 'students', 'education', 'courses', 'knowledge', 'skills'],
		];

		$industry_lower = strtolower( $industry );
		return $terms[ $industry_lower ] ?? [];
	}

	/**
	 * Get audience indicators
	 *
	 * @since    1.0.0
	 * @param    string    $audience    Target audience.
	 * @return   array                  Audience indicators.
	 */
	private function get_audience_indicators( $audience ) {
		$indicators = [
			'business' => ['roi', 'efficiency', 'growth', 'productivity', 'competitive'],
			'consumer' => ['easy', 'affordable', 'convenient', 'quality', 'value'],
			'professional' => ['expertise', 'advanced', 'professional', 'industry', 'certified'],
			'technical' => ['features', 'specifications', 'performance', 'capabilities', 'integration'],
		];

		$audience_lower = strtolower( $audience );

		foreach ( $indicators as $key => $values ) {
			if ( stripos( $audience_lower, $key ) !== false ) {
				return $values;
			}
		}

		return [];
	}

	/**
	 * Calculate technical score
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to analyze.
	 * @return   float                 Technical score (0-100).
	 */
	private function calculate_technical_score( $content ) {
		$score = 100;
		$deductions = 0;

		// Validate HTML
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<html><body>' . $content . '</body></html>' );
		$html_errors = libxml_get_errors();
		libxml_clear_errors();

		if ( ! $loaded || count( $html_errors ) > 0 ) {
			$deductions += min( 30, count( $html_errors ) * 5 );
		}

		// Check WordPress block validity
		if ( strpos( $content, '<!-- wp:' ) !== false ) {
			if ( ! $this->validate_wordpress_blocks( $content ) ) {
				$deductions += 20;
			}
		}

		// Check for proper encoding
		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content ) ) {
			$deductions += 10; // Contains control characters
		}

		// Check for broken links (simplified check)
		if ( preg_match( '/<a[^>]*href=["\']\s*["\']/', $content ) ) {
			$deductions += 10; // Empty href attributes
		}

		// Check for proper nesting
		if ( ! $this->check_proper_nesting( $content ) ) {
			$deductions += 15;
		}

		// Check image sources
		preg_match_all( '/<img[^>]+src=["\'](.*?)["\']/', $content, $images );
		foreach ( $images[1] as $src ) {
			if ( empty( $src ) || $src === '#' ) {
				$deductions += 5;
			}
		}

		return max( 0, $score - $deductions );
	}

	/**
	 * Validate WordPress blocks
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content with blocks.
	 * @return   bool                  True if valid.
	 */
	private function validate_wordpress_blocks( $content ) {
		// Check for matching block comments
		preg_match_all( '/<!-- wp:([a-z0-9\-\/]+)/i', $content, $opening );
		preg_match_all( '/<!-- \/wp:([a-z0-9\-\/]+)/i', $content, $closing );

		return count( $opening[1] ) === count( $closing[1] );
	}

	/**
	 * Check proper HTML nesting
	 *
	 * @since    1.0.0
	 * @param    string    $content    HTML content.
	 * @return   bool                  True if properly nested.
	 */
	private function check_proper_nesting( $content ) {
		$stack = [];
		$pattern = '/<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/';

		preg_match_all( $pattern, $content, $matches );

		foreach ( $matches[0] as $index => $match ) {
			$tag = $matches[1][ $index ];

			// Self-closing tags
			if ( in_array( $tag, ['img', 'br', 'hr', 'input', 'meta', 'link'], true ) ) {
				continue;
			}

			if ( strpos( $match, '</' ) === 0 ) {
				// Closing tag
				if ( empty( $stack ) || array_pop( $stack ) !== $tag ) {
					return false;
				}
			} elseif ( substr( $match, -2 ) !== '/>' ) {
				// Opening tag (not self-closing)
				$stack[] = $tag;
			}
		}

		return empty( $stack );
	}

	/**
	 * Calculate accessibility score
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to analyze.
	 * @return   float                 Accessibility score (0-100).
	 */
	private function calculate_accessibility_score( $content ) {
		$score = 100;
		$deductions = 0;

		// Check images for alt text
		preg_match_all( '/<img[^>]+>/i', $content, $images );
		foreach ( $images[0] as $img ) {
			if ( strpos( $img, 'alt=' ) === false ) {
				$deductions += 10;
				break;
			}
			if ( preg_match( '/alt=["\']\s*["\']/', $img ) && strpos( $img, 'role="presentation"' ) === false ) {
				$deductions += 5;
				break;
			}
		}

		// Check heading hierarchy
		if ( ! $this->has_proper_heading_structure( $content ) ) {
			$deductions += 15;
		}

		// Check for form labels
		if ( preg_match( '/<input[^>]*type=["\'](?!hidden|submit|button)/', $content ) ) {
			if ( ! preg_match( '/<label/', $content ) && ! preg_match( '/aria-label/', $content ) ) {
				$deductions += 15;
			}
		}

		// Check link text
		preg_match_all( '/<a[^>]*>(.*?)<\/a>/i', $content, $links );
		$generic_count = 0;
		foreach ( $links[1] as $link_text ) {
			$clean_text = strip_tags( strtolower( trim( $link_text ) ) );
			if ( in_array( $clean_text, ['click here', 'here', 'read more', 'link'], true ) ) {
				$generic_count++;
			}
		}
		if ( $generic_count > 0 ) {
			$deductions += min( 15, $generic_count * 5 );
		}

		// Check for ARIA attributes when needed
		if ( strpos( $content, 'role=' ) === false &&
			 strpos( $content, 'aria-' ) === false &&
			 strlen( strip_tags( $content ) ) > 500 ) {
			$deductions += 5; // Consider using ARIA for complex content
		}

		// Check color contrast indicators
		if ( preg_match( '/style=["\'][^"\']*color:\s*#[0-9a-f]{6}/i', $content ) ) {
			// Simplified check - in real implementation would calculate actual contrast
			$deductions += 5;
		}

		return max( 0, $score - $deductions );
	}

	/**
	 * Check for proper heading structure
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to check.
	 * @return   bool                  True if proper structure.
	 */
	private function has_proper_heading_structure( $content ) {
		preg_match_all( '/<h([1-6])[^>]*>/i', $content, $matches );

		if ( empty( $matches[1] ) ) {
			return false;
		}

		$levels = array_map( 'intval', $matches[1] );

		// Check for single H1
		$h1_count = count( array_filter( $levels, function( $level ) {
			return $level === 1;
		} ) );

		if ( $h1_count !== 1 ) {
			return false;
		}

		// Check for skipped levels
		$previous_level = 0;
		foreach ( $levels as $level ) {
			if ( $previous_level > 0 && $level > $previous_level + 1 ) {
				return false;
			}
			$previous_level = $level;
		}

		return true;
	}

	/**
	 * Calculate engagement score
	 *
	 * @since    1.0.0
	 * @param    string    $content    Content to analyze.
	 * @return   float                 Engagement score (0-100).
	 */
	private function calculate_engagement_score( $content ) {
		$score = 70; // Base score
		$adjustments = 0;

		// Check for questions (engaging readers)
		if ( preg_match( '/\?/', strip_tags( $content ) ) ) {
			$adjustments += 10;
		}

		// Check for action words
		$action_words = ['discover', 'learn', 'explore', 'get', 'start', 'join', 'try', 'download', 'subscribe'];
		$text_lower = strtolower( strip_tags( $content ) );
		$action_count = 0;

		foreach ( $action_words as $word ) {
			if ( stripos( $text_lower, $word ) !== false ) {
				$action_count++;
			}
		}

		if ( $action_count >= 3 ) {
			$adjustments += 10;
		} elseif ( $action_count >= 1 ) {
			$adjustments += 5;
		}

		// Check for CTAs
		if ( preg_match( '/<(button|a[^>]*class=["\'][^"\']*button)/', $content ) ) {
			$adjustments += 10;
		}

		// Check for multimedia elements
		if ( preg_match( '/<(img|video|iframe)/', $content ) ) {
			$adjustments += 5;
		}

		// Check for lists (easy to scan)
		if ( preg_match( '/<(ul|ol)/', $content ) ) {
			$adjustments += 5;
		}

		// Check for emotional triggers
		$emotional_words = ['amazing', 'incredible', 'essential', 'powerful', 'transform', 'revolutionary', 'breakthrough'];
		$emotional_count = 0;

		foreach ( $emotional_words as $word ) {
			if ( stripos( $text_lower, $word ) !== false ) {
				$emotional_count++;
			}
		}

		// Don't overuse emotional words
		if ( $emotional_count >= 1 && $emotional_count <= 3 ) {
			$adjustments += 5;
		} elseif ( $emotional_count > 5 ) {
			$adjustments -= 10; // Too many, seems spammy
		}

		// Check for social proof elements
		if ( preg_match( '/testimonial|review|rated|trusted by|clients|customers/i', $content ) ) {
			$adjustments += 5;
		}

		return max( 0, min( 100, $score + $adjustments ) );
	}

	/**
	 * Get grade based on score
	 *
	 * @since    1.0.0
	 * @param    float     $score    Numeric score.
	 * @return   string              Letter grade.
	 */
	private function get_grade( $score ) {
		if ( $score >= $this->thresholds['excellent'] ) {
			return 'A';
		} elseif ( $score >= $this->thresholds['good'] ) {
			return 'B';
		} elseif ( $score >= $this->thresholds['fair'] ) {
			return 'C';
		} elseif ( $score >= $this->thresholds['poor'] ) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Get recommendations based on scores
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Individual aspect scores.
	 * @return   array               Recommendations.
	 */
	private function get_recommendations( $scores ) {
		$recommendations = [];

		foreach ( $scores as $aspect => $score ) {
			if ( $score < $this->thresholds['fair'] ) {
				$recommendations[] = $this->get_aspect_recommendation( $aspect, $score );
			}
		}

		// Sort by priority (lowest scores first)
		usort( $recommendations, function( $a, $b ) use ( $scores ) {
			$score_a = $scores[ $a['aspect'] ] ?? 100;
			$score_b = $scores[ $b['aspect'] ] ?? 100;
			return $score_a - $score_b;
		} );

		return array_slice( $recommendations, 0, 5 ); // Top 5 recommendations
	}

	/**
	 * Get recommendation for specific aspect
	 *
	 * @since    1.0.0
	 * @param    string    $aspect    Aspect name.
	 * @param    float     $score     Aspect score.
	 * @return   array                Recommendation.
	 */
	private function get_aspect_recommendation( $aspect, $score ) {
		$recommendations = [
			'seo' => [
				'aspect' => 'seo',
				'priority' => 'high',
				'title' => __( 'Improve SEO Optimization', 'wp-ai-site-generator' ),
				'description' => __( 'Add primary keywords to headings, improve keyword density, and ensure meta descriptions are optimal length.', 'wp-ai-site-generator' ),
				'actions' => [
					__( 'Include primary keyword in H1 and H2 tags', 'wp-ai-site-generator' ),
					__( 'Add internal links to related content', 'wp-ai-site-generator' ),
					__( 'Optimize meta description (120-160 characters)', 'wp-ai-site-generator' ),
				],
			],
			'readability' => [
				'aspect' => 'readability',
				'priority' => 'high',
				'title' => __( 'Enhance Content Readability', 'wp-ai-site-generator' ),
				'description' => __( 'Simplify complex sentences, add subheadings, and break up long paragraphs.', 'wp-ai-site-generator' ),
				'actions' => [
					__( 'Break long paragraphs into smaller ones', 'wp-ai-site-generator' ),
					__( 'Add subheadings every 200-300 words', 'wp-ai-site-generator' ),
					__( 'Use simpler words and shorter sentences', 'wp-ai-site-generator' ),
				],
			],
			'relevance' => [
				'aspect' => 'relevance',
				'priority' => 'medium',
				'title' => __( 'Increase Content Relevance', 'wp-ai-site-generator' ),
				'description' => __( 'Align content more closely with target audience and industry context.', 'wp-ai-site-generator' ),
				'actions' => [
					__( 'Include industry-specific terminology', 'wp-ai-site-generator' ),
					__( 'Address audience pain points directly', 'wp-ai-site-generator' ),
					__( 'Add brand mentions naturally', 'wp-ai-site-generator' ),
				],
			],
			'technical' => [
				'aspect' => 'technical',
				'priority' => 'high',
				'title' => __( 'Fix Technical Issues', 'wp-ai-site-generator' ),
				'description' => __( 'Resolve HTML validation errors and ensure proper WordPress block structure.', 'wp-ai-site-generator' ),
				'actions' => [
					__( 'Fix invalid HTML markup', 'wp-ai-site-generator' ),
					__( 'Ensure WordPress blocks are properly formatted', 'wp-ai-site-generator' ),
					__( 'Remove broken links and empty hrefs', 'wp-ai-site-generator' ),
				],
			],
			'accessibility' => [
				'aspect' => 'accessibility',
				'priority' => 'high',
				'title' => __( 'Improve Accessibility', 'wp-ai-site-generator' ),
				'description' => __( 'Make content more accessible to all users, including those using assistive technologies.', 'wp-ai-site-generator' ),
				'actions' => [
					__( 'Add descriptive alt text to all images', 'wp-ai-site-generator' ),
					__( 'Use descriptive link text instead of "click here"', 'wp-ai-site-generator' ),
					__( 'Ensure proper heading hierarchy', 'wp-ai-site-generator' ),
				],
			],
			'engagement' => [
				'aspect' => 'engagement',
				'priority' => 'medium',
				'title' => __( 'Boost Content Engagement', 'wp-ai-site-generator' ),
				'description' => __( 'Add elements that encourage user interaction and maintain interest.', 'wp-ai-site-generator' ),
				'actions' => [
					__( 'Include clear calls-to-action', 'wp-ai-site-generator' ),
					__( 'Add questions to engage readers', 'wp-ai-site-generator' ),
					__( 'Include social proof or testimonials', 'wp-ai-site-generator' ),
				],
			],
		];

		return $recommendations[ $aspect ] ?? [
			'aspect' => $aspect,
			'priority' => 'low',
			'title' => sprintf( __( 'Improve %s', 'wp-ai-site-generator' ), ucfirst( $aspect ) ),
			'description' => sprintf( __( 'Score for %s is low (%d). Consider improvements.', 'wp-ai-site-generator' ), $aspect, $score ),
			'actions' => [],
		];
	}

	/**
	 * Identify content strengths
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Individual aspect scores.
	 * @return   array               Strengths.
	 */
	private function identify_strengths( $scores ) {
		$strengths = [];

		foreach ( $scores as $aspect => $score ) {
			if ( $score >= $this->thresholds['good'] ) {
				$strengths[] = [
					'aspect' => $aspect,
					'score' => $score,
					'description' => $this->get_strength_description( $aspect, $score ),
				];
			}
		}

		// Sort by score (highest first)
		usort( $strengths, function( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		return $strengths;
	}

	/**
	 * Get strength description
	 *
	 * @since    1.0.0
	 * @param    string    $aspect    Aspect name.
	 * @param    float     $score     Aspect score.
	 * @return   string               Description.
	 */
	private function get_strength_description( $aspect, $score ) {
		$descriptions = [
			'seo' => __( 'Excellent SEO optimization with proper keyword usage and structure', 'wp-ai-site-generator' ),
			'readability' => __( 'Content is easy to read and well-structured', 'wp-ai-site-generator' ),
			'relevance' => __( 'Content is highly relevant to the target audience and context', 'wp-ai-site-generator' ),
			'technical' => __( 'Clean, valid markup with proper technical implementation', 'wp-ai-site-generator' ),
			'accessibility' => __( 'Content meets accessibility standards', 'wp-ai-site-generator' ),
			'engagement' => __( 'Content includes engaging elements that encourage interaction', 'wp-ai-site-generator' ),
		];

		return $descriptions[ $aspect ] ?? sprintf( __( '%s is performing well', 'wp-ai-site-generator' ), ucfirst( $aspect ) );
	}

	/**
	 * Identify content weaknesses
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Individual aspect scores.
	 * @return   array               Weaknesses.
	 */
	private function identify_weaknesses( $scores ) {
		$weaknesses = [];

		foreach ( $scores as $aspect => $score ) {
			if ( $score < $this->thresholds['fair'] ) {
				$weaknesses[] = [
					'aspect' => $aspect,
					'score' => $score,
					'severity' => $this->get_severity( $score ),
					'description' => $this->get_weakness_description( $aspect, $score ),
				];
			}
		}

		// Sort by score (lowest first)
		usort( $weaknesses, function( $a, $b ) {
			return $a['score'] - $b['score'];
		} );

		return $weaknesses;
	}

	/**
	 * Get severity level
	 *
	 * @since    1.0.0
	 * @param    float     $score    Score value.
	 * @return   string              Severity level.
	 */
	private function get_severity( $score ) {
		if ( $score < $this->thresholds['poor'] ) {
			return 'critical';
		} elseif ( $score < $this->thresholds['fair'] ) {
			return 'major';
		} else {
			return 'minor';
		}
	}

	/**
	 * Get weakness description
	 *
	 * @since    1.0.0
	 * @param    string    $aspect    Aspect name.
	 * @param    float     $score     Aspect score.
	 * @return   string               Description.
	 */
	private function get_weakness_description( $aspect, $score ) {
		$descriptions = [
			'seo' => __( 'SEO optimization needs improvement', 'wp-ai-site-generator' ),
			'readability' => __( 'Content is difficult to read or poorly structured', 'wp-ai-site-generator' ),
			'relevance' => __( 'Content lacks relevance to target audience', 'wp-ai-site-generator' ),
			'technical' => __( 'Technical issues detected in markup', 'wp-ai-site-generator' ),
			'accessibility' => __( 'Accessibility improvements needed', 'wp-ai-site-generator' ),
			'engagement' => __( 'Content lacks engaging elements', 'wp-ai-site-generator' ),
		];

		return $descriptions[ $aspect ] ?? sprintf( __( '%s needs improvement', 'wp-ai-site-generator' ), ucfirst( $aspect ) );
	}

	/**
	 * Get score metadata
	 *
	 * @since    1.0.0
	 * @param    string    $content     Content analyzed.
	 * @param    array     $context     Context information.
	 * @return   array                  Metadata.
	 */
	private function get_score_metadata( $content, $context ) {
		$text = strip_tags( $content );

		return [
			'word_count' => str_word_count( $text ),
			'sentence_count' => $this->count_sentences( $text ),
			'paragraph_count' => substr_count( $content, '</p>' ),
			'heading_count' => preg_match_all( '/<h[1-6][^>]*>/i', $content, $matches ),
			'image_count' => substr_count( $content, '<img' ),
			'link_count' => substr_count( $content, '<a ' ),
			'analyzed_at' => current_time( 'mysql' ),
			'context_provided' => ! empty( $context ),
		];
	}

	/**
	 * Compare two scores
	 *
	 * @since    1.0.0
	 * @param    array    $score1    First score result.
	 * @param    array    $score2    Second score result.
	 * @return   array               Comparison result.
	 */
	public function compare_scores( $score1, $score2 ) {
		$comparison = [
			'improvement' => $score2['total_score'] - $score1['total_score'],
			'improvement_percentage' => 0,
			'aspect_changes' => [],
			'grade_change' => [
				'from' => $score1['grade'],
				'to' => $score2['grade'],
			],
		];

		if ( $score1['total_score'] > 0 ) {
			$comparison['improvement_percentage'] = round(
				( ( $score2['total_score'] - $score1['total_score'] ) / $score1['total_score'] ) * 100,
				2
			);
		}

		foreach ( $score1['breakdown'] as $aspect => $value ) {
			$comparison['aspect_changes'][ $aspect ] = [
				'before' => $value,
				'after' => $score2['breakdown'][ $aspect ] ?? 0,
				'change' => ( $score2['breakdown'][ $aspect ] ?? 0 ) - $value,
			];
		}

		return $comparison;
	}

	/**
	 * Get score history analysis
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Array of score results over time.
	 * @return   array               Analysis result.
	 */
	public function analyze_score_history( $scores ) {
		if ( empty( $scores ) ) {
			return [];
		}

		$total_scores = array_column( $scores, 'total_score' );

		return [
			'trend' => $this->calculate_trend( $total_scores ),
			'average' => array_sum( $total_scores ) / count( $total_scores ),
			'min' => min( $total_scores ),
			'max' => max( $total_scores ),
			'latest' => end( $total_scores ),
			'improvement_rate' => $this->calculate_improvement_rate( $total_scores ),
			'consistency' => $this->calculate_consistency( $total_scores ),
		];
	}

	/**
	 * Calculate score trend
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Array of scores.
	 * @return   string              Trend direction.
	 */
	private function calculate_trend( $scores ) {
		if ( count( $scores ) < 2 ) {
			return 'stable';
		}

		$first_half = array_slice( $scores, 0, floor( count( $scores ) / 2 ) );
		$second_half = array_slice( $scores, floor( count( $scores ) / 2 ) );

		$first_avg = array_sum( $first_half ) / count( $first_half );
		$second_avg = array_sum( $second_half ) / count( $second_half );

		if ( $second_avg > $first_avg + 5 ) {
			return 'improving';
		} elseif ( $second_avg < $first_avg - 5 ) {
			return 'declining';
		} else {
			return 'stable';
		}
	}

	/**
	 * Calculate improvement rate
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Array of scores.
	 * @return   float               Improvement rate.
	 */
	private function calculate_improvement_rate( $scores ) {
		if ( count( $scores ) < 2 ) {
			return 0;
		}

		$improvements = 0;
		for ( $i = 1; $i < count( $scores ); $i++ ) {
			if ( $scores[ $i ] > $scores[ $i - 1 ] ) {
				$improvements++;
			}
		}

		return round( ( $improvements / ( count( $scores ) - 1 ) ) * 100, 2 );
	}

	/**
	 * Calculate consistency
	 *
	 * @since    1.0.0
	 * @param    array    $scores    Array of scores.
	 * @return   string              Consistency level.
	 */
	private function calculate_consistency( $scores ) {
		if ( count( $scores ) < 2 ) {
			return 'unknown';
		}

		$std_dev = $this->calculate_standard_deviation( $scores );

		if ( $std_dev < 5 ) {
			return 'very_consistent';
		} elseif ( $std_dev < 10 ) {
			return 'consistent';
		} elseif ( $std_dev < 20 ) {
			return 'moderately_consistent';
		} else {
			return 'inconsistent';
		}
	}
}