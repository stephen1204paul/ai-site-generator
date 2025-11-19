<?php
/**
 * Quality Metrics Integration
 *
 * Integrates quality tracking with generation processes
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Includes\Quality_Scorer;
use WPAISiteGenerator\Includes\Quality_Metrics;
use WPAISiteGenerator\Includes\Output_Validator;

/**
 * Quality Integration class.
 *
 * @since 1.0.0
 */
class Quality_Integration {

	/**
	 * Quality metrics tracker instance.
	 *
	 * @var Quality_Metrics
	 */
	protected $metrics_tracker;

	/**
	 * Quality scorer instance.
	 *
	 * @var Quality_Scorer
	 */
	protected $quality_scorer;

	/**
	 * Output validator instance.
	 *
	 * @var Output_Validator
	 */
	protected $output_validator;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		$this->metrics_tracker = new Quality_Metrics();
		$this->quality_scorer = new Quality_Scorer();
		$this->output_validator = new Output_Validator();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	protected function init_hooks() {
		// Hook into generation completion
		add_action( 'waisg_generation_completed', array( $this, 'track_generation_quality' ), 10, 3 );

		// Hook into regeneration
		add_action( 'waisg_content_regenerated', array( $this, 'track_regeneration' ), 10, 4 );

		// Hook into validation
		add_filter( 'waisg_validate_output', array( $this, 'track_validation_results' ), 10, 3 );

		// Add quality score to generation response
		add_filter( 'waisg_generation_response', array( $this, 'add_quality_to_response' ), 10, 2 );

		// Track quality for batch operations
		add_action( 'waisg_batch_generation_item_completed', array( $this, 'track_batch_item_quality' ), 10, 4 );
	}

	/**
	 * Track quality score for a generation.
	 *
	 * @param int    $generation_id Generation ID.
	 * @param array  $content       Generated content.
	 * @param string $provider      AI provider used.
	 */
	public function track_generation_quality( $generation_id, $content, $provider ) {
		// Get content type
		$content_type = $this->determine_content_type( $content );

		// Calculate quality score
		$quality_data = $this->quality_scorer->score_content(
			$content['content'] ?? '',
			array(
				'type' => $content_type,
				'provider' => $provider,
			)
		);

		// Track the quality score
		$this->metrics_tracker->track_quality_score(
			$generation_id,
			$quality_data['overall_score'],
			$quality_data,
			$provider,
			$content_type
		);

		// Store quality data in generation meta
		if ( function_exists( 'update_generation_meta' ) ) {
			update_generation_meta( $generation_id, 'quality_score', $quality_data['overall_score'] );
			update_generation_meta( $generation_id, 'quality_data', $quality_data );
		}
	}

	/**
	 * Track regeneration with quality metrics.
	 *
	 * @param int    $original_id     Original generation ID.
	 * @param int    $new_id          New generation ID.
	 * @param string $reason          Reason for regeneration.
	 * @param array  $details         Additional details.
	 */
	public function track_regeneration( $original_id, $new_id, $reason, $details = array() ) {
		// Track regeneration in quality metrics
		$this->metrics_tracker->track_regeneration(
			$original_id,
			$new_id,
			$reason,
			$details
		);

		// Compare quality scores if both exist
		if ( function_exists( 'get_generation_meta' ) ) {
			$original_score = get_generation_meta( $original_id, 'quality_score', true );
			$new_score = get_generation_meta( $new_id, 'quality_score', true );

			if ( $original_score && $new_score ) {
				$improvement = $new_score - $original_score;
				update_generation_meta( $new_id, 'quality_improvement', $improvement );
			}
		}
	}

	/**
	 * Track validation results.
	 *
	 * @param bool  $valid         Whether content is valid.
	 * @param array $content       Content being validated.
	 * @param array $validation    Validation results.
	 * @return bool Original validation result.
	 */
	public function track_validation_results( $valid, $content, $validation ) {
		// Get generation ID if available
		$generation_id = $content['generation_id'] ?? 0;

		if ( $generation_id ) {
			// Store validation results
			$this->metrics_tracker->store_validation_results(
				$generation_id,
				array(
					'passed' => $valid,
					'errors' => $validation['errors'] ?? array(),
					'warnings' => $validation['warnings'] ?? array(),
					'timestamp' => current_time( 'mysql' ),
				)
			);
		}

		return $valid;
	}

	/**
	 * Add quality score to generation response.
	 *
	 * @param array $response      Generation response.
	 * @param int   $generation_id Generation ID.
	 * @return array Modified response.
	 */
	public function add_quality_to_response( $response, $generation_id ) {
		if ( function_exists( 'get_generation_meta' ) ) {
			$quality_score = get_generation_meta( $generation_id, 'quality_score', true );
			$quality_data = get_generation_meta( $generation_id, 'quality_data', true );

			if ( $quality_score ) {
				$response['quality'] = array(
					'score' => $quality_score,
					'grade' => $this->get_quality_grade( $quality_score ),
					'data' => $quality_data,
				);
			}
		}

		return $response;
	}

	/**
	 * Track quality for batch generation item.
	 *
	 * @param int    $batch_id      Batch ID.
	 * @param int    $item_id       Item ID.
	 * @param int    $generation_id Generation ID.
	 * @param string $provider      Provider used.
	 */
	public function track_batch_item_quality( $batch_id, $item_id, $generation_id, $provider ) {
		// Track quality for batch item
		$this->track_generation_quality( $generation_id, array(), $provider );

		// Update batch statistics
		$this->update_batch_quality_stats( $batch_id );
	}

	/**
	 * Update batch quality statistics.
	 *
	 * @param int $batch_id Batch ID.
	 */
	protected function update_batch_quality_stats( $batch_id ) {
		// Get all generations in batch
		global $wpdb;
		$table_name = $wpdb->prefix . 'waisg_generation_history';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT AVG(quality_score) as avg_score,
						MIN(quality_score) as min_score,
						MAX(quality_score) as max_score,
						COUNT(*) as total
				FROM {$wpdb->prefix}waisg_quality_metrics
				WHERE generation_id IN (
					SELECT id FROM {$table_name} WHERE batch_id = %d
				)",
				$batch_id
			)
		);

		if ( $results && function_exists( 'update_batch_meta' ) ) {
			update_batch_meta( $batch_id, 'quality_stats', $results[0] );
		}
	}

	/**
	 * Determine content type from content array.
	 *
	 * @param array $content Content array.
	 * @return string Content type.
	 */
	protected function determine_content_type( $content ) {
		if ( isset( $content['type'] ) ) {
			return $content['type'];
		}

		if ( isset( $content['post_type'] ) ) {
			return $content['post_type'];
		}

		// Default fallback
		return 'general';
	}

	/**
	 * Get quality grade from score.
	 *
	 * @param float $score Quality score.
	 * @return string Quality grade.
	 */
	protected function get_quality_grade( $score ) {
		if ( $score >= 90 ) {
			return 'A+';
		} elseif ( $score >= 80 ) {
			return 'A';
		} elseif ( $score >= 70 ) {
			return 'B';
		} elseif ( $score >= 60 ) {
			return 'C';
		} elseif ( $score >= 50 ) {
			return 'D';
		} else {
			return 'F';
		}
	}

	/**
	 * Get quality recommendations based on score.
	 *
	 * @param float $score    Quality score.
	 * @param array $details  Quality details.
	 * @return array Recommendations.
	 */
	public function get_quality_recommendations( $score, $details = array() ) {
		$recommendations = array();

		if ( $score < 60 ) {
			$recommendations[] = __( 'Consider regenerating content with different parameters', 'wp-ai-site-generator' );
		}

		if ( isset( $details['readability_score'] ) && $details['readability_score'] < 50 ) {
			$recommendations[] = __( 'Improve content readability by using simpler sentences', 'wp-ai-site-generator' );
		}

		if ( isset( $details['keyword_density'] ) && $details['keyword_density'] < 1 ) {
			$recommendations[] = __( 'Add more relevant keywords for better SEO', 'wp-ai-site-generator' );
		}

		if ( isset( $details['uniqueness_score'] ) && $details['uniqueness_score'] < 70 ) {
			$recommendations[] = __( 'Enhance content uniqueness to avoid duplication', 'wp-ai-site-generator' );
		}

		return $recommendations;
	}

	/**
	 * Calculate quality trend.
	 *
	 * @param int $days Number of days to analyze.
	 * @return array Trend data.
	 */
	public function calculate_quality_trend( $days = 7 ) {
		$trends = $this->metrics_tracker->get_score_trends( 'day', array(
			'date_from' => date( 'Y-m-d', strtotime( "-{$days} days" ) ),
		) );

		if ( empty( $trends ) ) {
			return array(
				'direction' => 'stable',
				'percentage' => 0,
			);
		}

		// Calculate trend direction
		$first_period = array_slice( $trends, 0, ceil( count( $trends ) / 2 ) );
		$last_period = array_slice( $trends, floor( count( $trends ) / 2 ) );

		$first_avg = array_sum( array_column( $first_period, 'avg_score' ) ) / count( $first_period );
		$last_avg = array_sum( array_column( $last_period, 'avg_score' ) ) / count( $last_period );

		$change = $last_avg - $first_avg;
		$percentage = $first_avg > 0 ? ( $change / $first_avg ) * 100 : 0;

		return array(
			'direction' => $change > 0 ? 'improving' : ( $change < 0 ? 'declining' : 'stable' ),
			'percentage' => round( $percentage, 1 ),
			'first_avg' => round( $first_avg, 1 ),
			'last_avg' => round( $last_avg, 1 ),
		);
	}
}