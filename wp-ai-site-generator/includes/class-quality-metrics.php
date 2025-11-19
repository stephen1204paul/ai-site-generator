<?php
/**
 * Quality Metrics Tracker
 *
 * Tracks and manages quality metrics for all AI content generations
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Quality Metrics Tracker class.
 *
 * @since 1.0.0
 */
class Quality_Metrics {

	/**
	 * Table name for metrics storage.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'waisg_quality_metrics';
	}

	/**
	 * Track quality score for a generation.
	 *
	 * @param int    $generation_id  Generation ID.
	 * @param float  $quality_score  Quality score (0-100).
	 * @param array  $metrics        Detailed metrics data.
	 * @param string $provider       AI provider used.
	 * @param string $content_type   Type of content generated.
	 * @return int|false Metric ID on success, false on failure.
	 */
	public function track_quality_score( $generation_id, $quality_score, $metrics = array(), $provider = '', $content_type = '' ) {
		global $wpdb;

		$data = array(
			'generation_id'   => $generation_id,
			'quality_score'   => $quality_score,
			'provider'        => $provider,
			'content_type'    => $content_type,
			'user_id'         => get_current_user_id(),
			'created_at'      => current_time( 'mysql' ),
			'metrics_data'    => wp_json_encode( $metrics ),
		);

		$result = $wpdb->insert( $this->table_name, $data );

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Store validation results.
	 *
	 * @param int   $generation_id     Generation ID.
	 * @param array $validation_results Validation results array.
	 * @return bool Success status.
	 */
	public function store_validation_results( $generation_id, $validation_results ) {
		global $wpdb;

		// Get existing metric or create new one
		$metric = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE generation_id = %d",
				$generation_id
			)
		);

		if ( ! $metric ) {
			// Create new metric entry
			return $this->track_quality_score(
				$generation_id,
				$validation_results['overall_score'] ?? 0,
				array( 'validation_results' => $validation_results )
			);
		}

		// Update existing metric
		$metrics_data = json_decode( $metric->metrics_data, true ) ?: array();
		$metrics_data['validation_results'] = $validation_results;

		$result = $wpdb->update(
			$this->table_name,
			array(
				'metrics_data' => wp_json_encode( $metrics_data ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $metric->id )
		);

		return false !== $result;
	}

	/**
	 * Track regeneration reason.
	 *
	 * @param int    $generation_id Original generation ID.
	 * @param int    $new_generation_id New generation ID.
	 * @param string $reason Reason for regeneration.
	 * @param array  $details Additional details.
	 * @return bool Success status.
	 */
	public function track_regeneration( $generation_id, $new_generation_id, $reason, $details = array() ) {
		global $wpdb;

		$data = array(
			'original_generation_id' => $generation_id,
			'new_generation_id'      => $new_generation_id,
			'regeneration_reason'    => $reason,
			'regeneration_details'   => wp_json_encode( $details ),
			'regenerated_at'         => current_time( 'mysql' ),
		);

		// Store in metrics data
		$metric = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE generation_id = %d",
				$generation_id
			)
		);

		if ( $metric ) {
			$metrics_data = json_decode( $metric->metrics_data, true ) ?: array();
			$metrics_data['regenerations'][] = $data;

			$result = $wpdb->update(
				$this->table_name,
				array(
					'metrics_data' => wp_json_encode( $metrics_data ),
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $metric->id )
			);

			return false !== $result;
		}

		return false;
	}

	/**
	 * Get quality metrics for dashboard.
	 *
	 * @param array $args Query arguments.
	 * @return array Metrics data.
	 */
	public function get_metrics( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'        => 100,
			'offset'       => 0,
			'provider'     => '',
			'content_type' => '',
			'date_from'    => '',
			'date_to'      => '',
			'min_score'    => 0,
			'max_score'    => 100,
			'user_id'      => 0,
			'orderby'      => 'created_at',
			'order'        => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build query
		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $args['provider'] ) ) {
			$where_clauses[] = 'provider = %s';
			$where_values[] = $args['provider'];
		}

		if ( ! empty( $args['content_type'] ) ) {
			$where_clauses[] = 'content_type = %s';
			$where_values[] = $args['content_type'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $args['date_to'];
		}

		if ( $args['min_score'] > 0 ) {
			$where_clauses[] = 'quality_score >= %f';
			$where_values[] = $args['min_score'];
		}

		if ( $args['max_score'] < 100 ) {
			$where_clauses[] = 'quality_score <= %f';
			$where_values[] = $args['max_score'];
		}

		if ( $args['user_id'] > 0 ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[] = $args['user_id'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Prepare base query
		$base_query = "FROM {$this->table_name} WHERE {$where_sql}";

		// Get total count
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( "SELECT COUNT(*) {$base_query}", ...$where_values );
		} else {
			$count_query = "SELECT COUNT(*) {$base_query}";
		}
		$total = $wpdb->get_var( $count_query );

		// Get data
		$order_by = in_array( $args['orderby'], array( 'created_at', 'quality_score', 'provider' ), true ) ? $args['orderby'] : 'created_at';
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		if ( ! empty( $where_values ) ) {
			$data_query = $wpdb->prepare(
				"SELECT * {$base_query} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				array_merge( $where_values, array( $args['limit'], $args['offset'] ) )
			);
		} else {
			$data_query = $wpdb->prepare(
				"SELECT * {$base_query} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			);
		}

		$results = $wpdb->get_results( $data_query );

		// Decode JSON fields
		foreach ( $results as &$result ) {
			$result->metrics_data = json_decode( $result->metrics_data, true );
		}

		return array(
			'total'   => $total,
			'results' => $results,
			'args'    => $args,
		);
	}

	/**
	 * Get score trends over time.
	 *
	 * @param string $period Period for grouping (hour, day, week, month).
	 * @param array  $filters Additional filters.
	 * @return array Trend data.
	 */
	public function get_score_trends( $period = 'day', $filters = array() ) {
		global $wpdb;

		// Determine date format based on period
		$date_formats = array(
			'hour'  => '%Y-%m-%d %H:00:00',
			'day'   => '%Y-%m-%d',
			'week'  => '%Y-%u',
			'month' => '%Y-%m',
		);

		$date_format = $date_formats[ $period ] ?? $date_formats['day'];

		// Build where clause
		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $filters['provider'] ) ) {
			$where_clauses[] = 'provider = %s';
			$where_values[] = $filters['provider'];
		}

		if ( ! empty( $filters['content_type'] ) ) {
			$where_clauses[] = 'content_type = %s';
			$where_values[] = $filters['content_type'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $filters['date_to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Build and execute query
		$query = "
			SELECT
				DATE_FORMAT(created_at, %s) as period,
				AVG(quality_score) as avg_score,
				MIN(quality_score) as min_score,
				MAX(quality_score) as max_score,
				COUNT(*) as generation_count,
				SUM(CASE WHEN quality_score >= 80 THEN 1 ELSE 0 END) as high_quality_count,
				SUM(CASE WHEN quality_score < 50 THEN 1 ELSE 0 END) as low_quality_count
			FROM {$this->table_name}
			WHERE {$where_sql}
			GROUP BY period
			ORDER BY period DESC
		";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, array_merge( array( $date_format ), $where_values ) );
		} else {
			$query = $wpdb->prepare( $query, $date_format );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get provider performance comparison.
	 *
	 * @param array $date_range Date range filter.
	 * @return array Provider comparison data.
	 */
	public function get_provider_comparison( $date_range = array() ) {
		global $wpdb;

		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $date_range['from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[] = $date_range['from'];
		}

		if ( ! empty( $date_range['to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[] = $date_range['to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = "
			SELECT
				provider,
				COUNT(*) as total_generations,
				AVG(quality_score) as avg_score,
				MIN(quality_score) as min_score,
				MAX(quality_score) as max_score,
				STDDEV(quality_score) as score_stddev,
				SUM(CASE WHEN quality_score >= 80 THEN 1 ELSE 0 END) as high_quality_count,
				SUM(CASE WHEN quality_score >= 60 AND quality_score < 80 THEN 1 ELSE 0 END) as medium_quality_count,
				SUM(CASE WHEN quality_score < 60 THEN 1 ELSE 0 END) as low_quality_count
			FROM {$this->table_name}
			WHERE {$where_sql}
			GROUP BY provider
			ORDER BY avg_score DESC
		";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get validation failure breakdown.
	 *
	 * @param array $filters Filters for the query.
	 * @return array Validation failure data.
	 */
	public function get_validation_breakdown( $filters = array() ) {
		global $wpdb;

		$metrics = $this->get_metrics( $filters );
		$validation_stats = array(
			'total_validations' => 0,
			'passed'            => 0,
			'failed'            => 0,
			'failure_reasons'   => array(),
			'common_issues'     => array(),
		);

		foreach ( $metrics['results'] as $metric ) {
			if ( isset( $metric->metrics_data['validation_results'] ) ) {
				$validation_stats['total_validations']++;

				$validation = $metric->metrics_data['validation_results'];

				if ( $validation['passed'] ?? false ) {
					$validation_stats['passed']++;
				} else {
					$validation_stats['failed']++;

					// Track failure reasons
					if ( isset( $validation['errors'] ) ) {
						foreach ( $validation['errors'] as $error ) {
							$reason = $error['type'] ?? 'unknown';
							if ( ! isset( $validation_stats['failure_reasons'][ $reason ] ) ) {
								$validation_stats['failure_reasons'][ $reason ] = 0;
							}
							$validation_stats['failure_reasons'][ $reason ]++;
						}
					}
				}
			}
		}

		// Sort failure reasons by frequency
		arsort( $validation_stats['failure_reasons'] );

		// Get top 5 common issues
		$validation_stats['common_issues'] = array_slice( $validation_stats['failure_reasons'], 0, 5, true );

		return $validation_stats;
	}

	/**
	 * Get cost and token usage by quality score.
	 *
	 * @param array $filters Filters for the query.
	 * @return array Cost and usage data.
	 */
	public function get_cost_by_quality( $filters = array() ) {
		global $wpdb;

		$where_clauses = array( '1=1' );
		$where_values = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$where_clauses[] = 'm.created_at >= %s';
			$where_values[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_clauses[] = 'm.created_at <= %s';
			$where_values[] = $filters['date_to'];
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Join with generation history table to get token usage
		$query = "
			SELECT
				CASE
					WHEN m.quality_score >= 80 THEN 'High (80-100)'
					WHEN m.quality_score >= 60 THEN 'Medium (60-79)'
					WHEN m.quality_score >= 40 THEN 'Low (40-59)'
					ELSE 'Very Low (0-39)'
				END as quality_tier,
				COUNT(*) as generation_count,
				AVG(m.quality_score) as avg_score,
				SUM(gh.tokens_used) as total_tokens,
				SUM(gh.estimated_cost) as total_cost,
				AVG(gh.tokens_used) as avg_tokens,
				AVG(gh.estimated_cost) as avg_cost
			FROM {$this->table_name} m
			LEFT JOIN {$wpdb->prefix}waisg_generation_history gh ON m.generation_id = gh.id
			WHERE {$where_sql}
			GROUP BY quality_tier
			ORDER BY
				CASE quality_tier
					WHEN 'High (80-100)' THEN 1
					WHEN 'Medium (60-79)' THEN 2
					WHEN 'Low (40-59)' THEN 3
					ELSE 4
				END
		";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Get improvement recommendations based on metrics.
	 *
	 * @return array Recommendations.
	 */
	public function get_recommendations() {
		$recommendations = array();

		// Get recent metrics
		$recent_metrics = $this->get_metrics( array(
			'limit'     => 100,
			'date_from' => date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
		) );

		// Analyze average scores
		$total_score = 0;
		$count = 0;
		$provider_scores = array();

		foreach ( $recent_metrics['results'] as $metric ) {
			$total_score += $metric->quality_score;
			$count++;

			if ( ! isset( $provider_scores[ $metric->provider ] ) ) {
				$provider_scores[ $metric->provider ] = array(
					'total' => 0,
					'count' => 0,
				);
			}

			$provider_scores[ $metric->provider ]['total'] += $metric->quality_score;
			$provider_scores[ $metric->provider ]['count']++;
		}

		// Overall quality recommendation
		if ( $count > 0 ) {
			$avg_score = $total_score / $count;

			if ( $avg_score < 60 ) {
				$recommendations[] = array(
					'type'     => 'critical',
					'title'    => __( 'Low Overall Quality', 'wp-ai-site-generator' ),
					'message'  => __( 'Average quality score is below 60%. Consider adjusting generation parameters or switching providers.', 'wp-ai-site-generator' ),
					'priority' => 1,
				);
			} elseif ( $avg_score < 75 ) {
				$recommendations[] = array(
					'type'     => 'warning',
					'title'    => __( 'Moderate Quality', 'wp-ai-site-generator' ),
					'message'  => __( 'Average quality score could be improved. Review validation failures and optimize prompts.', 'wp-ai-site-generator' ),
					'priority' => 2,
				);
			}
		}

		// Provider-specific recommendations
		foreach ( $provider_scores as $provider => $scores ) {
			if ( $scores['count'] > 5 ) {
				$provider_avg = $scores['total'] / $scores['count'];

				if ( $provider_avg < 50 ) {
					$recommendations[] = array(
						'type'     => 'warning',
						'title'    => sprintf( __( '%s Performance Issue', 'wp-ai-site-generator' ), $provider ),
						'message'  => sprintf( __( '%s provider has low average quality (%d%%). Consider using a different provider or adjusting settings.', 'wp-ai-site-generator' ), $provider, round( $provider_avg ) ),
						'priority' => 2,
					);
				}
			}
		}

		// Get validation breakdown
		$validation_stats = $this->get_validation_breakdown( array(
			'date_from' => date( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
		) );

		// Validation failure recommendation
		if ( $validation_stats['total_validations'] > 0 ) {
			$failure_rate = ( $validation_stats['failed'] / $validation_stats['total_validations'] ) * 100;

			if ( $failure_rate > 30 ) {
				$recommendations[] = array(
					'type'     => 'critical',
					'title'    => __( 'High Validation Failure Rate', 'wp-ai-site-generator' ),
					'message'  => sprintf( __( '%d%% of generations are failing validation. Review common issues and adjust validation rules or generation parameters.', 'wp-ai-site-generator' ), round( $failure_rate ) ),
					'priority' => 1,
				);
			}
		}

		// Sort by priority
		usort( $recommendations, function( $a, $b ) {
			return $a['priority'] <=> $b['priority'];
		} );

		return $recommendations;
	}

	/**
	 * Export quality report.
	 *
	 * @param string $format Export format (csv, json).
	 * @param array  $filters Filters for the export.
	 * @return array Export data.
	 */
	public function export_report( $format = 'csv', $filters = array() ) {
		$metrics = $this->get_metrics( array_merge( $filters, array( 'limit' => 10000 ) ) );
		$trends = $this->get_score_trends( 'day', $filters );
		$provider_comparison = $this->get_provider_comparison( $filters );
		$validation_breakdown = $this->get_validation_breakdown( $filters );
		$cost_by_quality = $this->get_cost_by_quality( $filters );

		$report_data = array(
			'summary' => array(
				'total_generations' => $metrics['total'],
				'date_range'        => array(
					'from' => $filters['date_from'] ?? 'All time',
					'to'   => $filters['date_to'] ?? 'Present',
				),
				'export_date'       => current_time( 'Y-m-d H:i:s' ),
			),
			'metrics'             => $metrics['results'],
			'trends'              => $trends,
			'provider_comparison' => $provider_comparison,
			'validation_stats'    => $validation_breakdown,
			'cost_analysis'       => $cost_by_quality,
		);

		if ( 'csv' === $format ) {
			return $this->format_csv_report( $report_data );
		}

		return $report_data;
	}

	/**
	 * Format report data as CSV.
	 *
	 * @param array $data Report data.
	 * @return string CSV formatted data.
	 */
	private function format_csv_report( $data ) {
		$csv_output = '';

		// Summary section
		$csv_output .= "Quality Report Summary\n";
		$csv_output .= "Total Generations," . $data['summary']['total_generations'] . "\n";
		$csv_output .= "Date Range," . $data['summary']['date_range']['from'] . " to " . $data['summary']['date_range']['to'] . "\n";
		$csv_output .= "Export Date," . $data['summary']['export_date'] . "\n\n";

		// Metrics section
		$csv_output .= "Generation Metrics\n";
		$csv_output .= "Generation ID,Quality Score,Provider,Content Type,Created At\n";

		foreach ( $data['metrics'] as $metric ) {
			$csv_output .= sprintf(
				"%d,%.2f,%s,%s,%s\n",
				$metric->generation_id,
				$metric->quality_score,
				$metric->provider,
				$metric->content_type,
				$metric->created_at
			);
		}

		return $csv_output;
	}
}