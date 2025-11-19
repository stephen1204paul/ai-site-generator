<?php
/**
 * Quality Metrics REST API Endpoints
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/api
 * @since      1.0.0
 */

namespace WPAISiteGenerator\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPAISiteGenerator\Includes\Quality_Metrics;

/**
 * Quality metrics endpoint handler.
 *
 * @since 1.0.0
 */
class Quality_Endpoint extends WP_REST_Controller {

	/**
	 * The namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'waisg/v1';

	/**
	 * Rest base for the current object.
	 *
	 * @var string
	 */
	protected $rest_base = 'quality';

	/**
	 * Quality metrics tracker instance.
	 *
	 * @var Quality_Metrics
	 */
	protected $metrics_tracker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->metrics_tracker = new Quality_Metrics();
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get quality metrics
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/metrics',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_metrics' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_metrics_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_metrics' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_metrics_params(),
				),
			)
		);

		// Get trend data
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/trends',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_trends' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_trends_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_trends' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_trends_params(),
				),
			)
		);

		// Get detailed breakdown
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/breakdown',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_breakdown' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_breakdown_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'get_breakdown' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_breakdown_params(),
				),
			)
		);

		// Get improvement recommendations
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/recommendations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_recommendations' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Export quality report
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_report' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_export_params(),
				),
			)
		);

		// Track quality score (for internal use)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/track',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_score' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_track_params(),
				),
			)
		);

		// Get real-time metrics
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/realtime',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_realtime_metrics' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Get quality metrics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_metrics( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Build filter arguments
		$args = array(
			'limit'        => $params['limit'] ?? 100,
			'offset'       => $params['offset'] ?? 0,
			'provider'     => $params['provider'] ?? '',
			'content_type' => $params['content_type'] ?? '',
			'date_from'    => $params['date_from'] ?? '',
			'date_to'      => $params['date_to'] ?? '',
			'min_score'    => $params['min_score'] ?? 0,
			'max_score'    => $params['max_score'] ?? 100,
			'user_id'      => $params['user_id'] ?? 0,
			'orderby'      => $params['orderby'] ?? 'created_at',
			'order'        => $params['order'] ?? 'DESC',
		);

		// Get metrics from tracker
		$metrics = $this->metrics_tracker->get_metrics( $args );

		// Calculate additional statistics
		$stats = $this->calculate_statistics( $metrics['results'] );

		// Build response
		$response_data = array(
			'success'  => true,
			'total'    => $metrics['total'],
			'metrics'  => $metrics['results'],
			'stats'    => $stats,
			'filters'  => $args,
			'realtime' => $this->get_realtime_data(),
			'distribution' => $this->calculate_distribution( $metrics['results'] ),
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Get trend data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_trends( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Get period (hour, day, week, month)
		$period = $params['period'] ?? 'day';

		// Build filters
		$filters = array(
			'provider'     => $params['provider'] ?? '',
			'content_type' => $params['content_type'] ?? '',
			'date_from'    => $params['date_from'] ?? date( 'Y-m-d', strtotime( '-30 days' ) ),
			'date_to'      => $params['date_to'] ?? date( 'Y-m-d' ),
		);

		// Get trends from tracker
		$trends = $this->metrics_tracker->get_score_trends( $period, $filters );

		// Process trends for chart display
		$processed_trends = array();
		foreach ( $trends as $trend ) {
			$processed_trends[] = array(
				'period'             => $trend->period,
				'avg_score'          => floatval( $trend->avg_score ),
				'min_score'          => floatval( $trend->min_score ),
				'max_score'          => floatval( $trend->max_score ),
				'generation_count'   => intval( $trend->generation_count ),
				'high_quality_count' => intval( $trend->high_quality_count ),
				'low_quality_count'  => intval( $trend->low_quality_count ),
				'quality_rate'       => $trend->generation_count > 0
					? ( $trend->high_quality_count / $trend->generation_count ) * 100
					: 0,
			);
		}

		// Calculate trend direction
		$trend_direction = $this->calculate_trend_direction( $processed_trends );

		$response_data = array(
			'success'   => true,
			'trends'    => $processed_trends,
			'period'    => $period,
			'filters'   => $filters,
			'direction' => $trend_direction,
			'summary'   => $this->get_trend_summary( $processed_trends ),
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Get detailed breakdown.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_breakdown( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Build filters
		$filters = array(
			'date_from'    => $params['date_from'] ?? date( 'Y-m-d', strtotime( '-7 days' ) ),
			'date_to'      => $params['date_to'] ?? date( 'Y-m-d' ),
			'provider'     => $params['provider'] ?? '',
			'content_type' => $params['content_type'] ?? '',
		);

		// Get various breakdowns
		$provider_comparison = $this->metrics_tracker->get_provider_comparison(
			array( 'from' => $filters['date_from'], 'to' => $filters['date_to'] )
		);

		$validation_breakdown = $this->metrics_tracker->get_validation_breakdown( $filters );
		$cost_by_quality = $this->metrics_tracker->get_cost_by_quality( $filters );

		// Process provider comparison for charts
		$processed_providers = array();
		foreach ( $provider_comparison as $provider ) {
			$processed_providers[] = array(
				'provider'            => $provider->provider,
				'total_generations'   => intval( $provider->total_generations ),
				'avg_score'           => floatval( $provider->avg_score ),
				'min_score'           => floatval( $provider->min_score ),
				'max_score'           => floatval( $provider->max_score ),
				'score_stddev'        => floatval( $provider->score_stddev ),
				'high_quality_count'  => intval( $provider->high_quality_count ),
				'medium_quality_count'=> intval( $provider->medium_quality_count ),
				'low_quality_count'   => intval( $provider->low_quality_count ),
				'quality_distribution'=> array(
					'high'   => $provider->total_generations > 0
						? ( $provider->high_quality_count / $provider->total_generations ) * 100
						: 0,
					'medium' => $provider->total_generations > 0
						? ( $provider->medium_quality_count / $provider->total_generations ) * 100
						: 0,
					'low'    => $provider->total_generations > 0
						? ( $provider->low_quality_count / $provider->total_generations ) * 100
						: 0,
				),
			);
		}

		// Process cost analysis
		$processed_costs = array();
		$total_cost = 0;
		$total_tokens = 0;

		foreach ( $cost_by_quality as $tier ) {
			$processed_costs[] = array(
				'quality_tier'      => $tier->quality_tier,
				'generation_count'  => intval( $tier->generation_count ),
				'total_tokens'      => intval( $tier->total_tokens ),
				'total_cost'        => floatval( $tier->total_cost ),
				'avg_tokens'        => floatval( $tier->avg_tokens ),
				'avg_cost'          => floatval( $tier->avg_cost ),
			);
			$total_cost += floatval( $tier->total_cost );
			$total_tokens += intval( $tier->total_tokens );
		}

		$response_data = array(
			'success'              => true,
			'providers'            => $processed_providers,
			'validation'           => $validation_breakdown,
			'costs'                => $processed_costs,
			'cost_summary'         => array(
				'total_cost'   => $total_cost,
				'total_tokens' => $total_tokens,
				'avg_cost_per_generation' => count( $processed_costs ) > 0
					? $total_cost / array_sum( array_column( $processed_costs, 'generation_count' ) )
					: 0,
			),
			'filters'              => $filters,
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Get improvement recommendations.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_recommendations( WP_REST_Request $request ) {
		// Get recommendations from tracker
		$recommendations = $this->metrics_tracker->get_recommendations();

		// Add additional insights
		$insights = $this->generate_insights();

		$response_data = array(
			'success'         => true,
			'recommendations' => $recommendations,
			'insights'        => $insights,
			'generated_at'    => current_time( 'mysql' ),
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Export quality report.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_report( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Get export format
		$format = $params['format'] ?? 'csv';

		// Build filters
		$filters = array(
			'date_from'    => $params['date_from'] ?? '',
			'date_to'      => $params['date_to'] ?? '',
			'provider'     => $params['provider'] ?? '',
			'content_type' => $params['content_type'] ?? '',
		);

		// Export report
		$report_data = $this->metrics_tracker->export_report( $format, $filters );

		if ( 'csv' === $format ) {
			// Return CSV data
			return new WP_REST_Response(
				array(
					'success' => true,
					'format'  => 'csv',
					'data'    => $report_data,
				),
				200,
				array(
					'Content-Type'        => 'text/csv',
					'Content-Disposition' => 'attachment; filename="quality-report-' . date( 'Y-m-d' ) . '.csv"',
				)
			);
		}

		// Return JSON data
		$response_data = array(
			'success' => true,
			'format'  => 'json',
			'data'    => $report_data,
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Track quality score.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function track_score( WP_REST_Request $request ) {
		$params = $request->get_params();

		// Validate required parameters
		if ( empty( $params['generation_id'] ) || ! isset( $params['quality_score'] ) ) {
			return new WP_Error(
				'missing_parameters',
				__( 'Generation ID and quality score are required', 'wp-ai-site-generator' ),
				array( 'status' => 400 )
			);
		}

		// Track the score
		$metric_id = $this->metrics_tracker->track_quality_score(
			$params['generation_id'],
			$params['quality_score'],
			$params['metrics'] ?? array(),
			$params['provider'] ?? '',
			$params['content_type'] ?? ''
		);

		if ( false === $metric_id ) {
			return new WP_Error(
				'tracking_failed',
				__( 'Failed to track quality score', 'wp-ai-site-generator' ),
				array( 'status' => 500 )
			);
		}

		// Store validation results if provided
		if ( ! empty( $params['validation_results'] ) ) {
			$this->metrics_tracker->store_validation_results(
				$params['generation_id'],
				$params['validation_results']
			);
		}

		$response_data = array(
			'success'   => true,
			'metric_id' => $metric_id,
			'message'   => __( 'Quality score tracked successfully', 'wp-ai-site-generator' ),
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Get real-time metrics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_realtime_metrics( WP_REST_Request $request ) {
		$realtime_data = $this->get_realtime_data();

		$response_data = array(
			'success'  => true,
			'realtime' => $realtime_data,
			'timestamp' => current_time( 'timestamp' ),
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Check permission for accessing endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access this endpoint.', 'wp-ai-site-generator' ),
				array( 'status' => 401 )
			);
		}

		// Check for manage_options capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'wp-ai-site-generator' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get parameters for metrics endpoint.
	 *
	 * @return array
	 */
	protected function get_metrics_params() {
		return array(
			'limit' => array(
				'description'       => __( 'Number of results to return', 'wp-ai-site-generator' ),
				'type'              => 'integer',
				'default'           => 100,
				'sanitize_callback' => 'absint',
			),
			'offset' => array(
				'description'       => __( 'Offset for pagination', 'wp-ai-site-generator' ),
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'provider' => array(
				'description'       => __( 'Filter by AI provider', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content_type' => array(
				'description'       => __( 'Filter by content type', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from' => array(
				'description'       => __( 'Start date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to' => array(
				'description'       => __( 'End date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'min_score' => array(
				'description'       => __( 'Minimum quality score', 'wp-ai-site-generator' ),
				'type'              => 'number',
				'default'           => 0,
				'minimum'           => 0,
				'maximum'           => 100,
			),
			'max_score' => array(
				'description'       => __( 'Maximum quality score', 'wp-ai-site-generator' ),
				'type'              => 'number',
				'default'           => 100,
				'minimum'           => 0,
				'maximum'           => 100,
			),
			'user_id' => array(
				'description'       => __( 'Filter by user ID', 'wp-ai-site-generator' ),
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'orderby' => array(
				'description'       => __( 'Order results by field', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'default'           => 'created_at',
				'enum'              => array( 'created_at', 'quality_score', 'provider' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order' => array(
				'description'       => __( 'Order direction', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get parameters for trends endpoint.
	 *
	 * @return array
	 */
	protected function get_trends_params() {
		return array(
			'period' => array(
				'description'       => __( 'Time period for grouping', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'default'           => 'day',
				'enum'              => array( 'hour', 'day', 'week', 'month' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'provider' => array(
				'description'       => __( 'Filter by AI provider', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content_type' => array(
				'description'       => __( 'Filter by content type', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from' => array(
				'description'       => __( 'Start date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to' => array(
				'description'       => __( 'End date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get parameters for breakdown endpoint.
	 *
	 * @return array
	 */
	protected function get_breakdown_params() {
		return array(
			'provider' => array(
				'description'       => __( 'Filter by AI provider', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content_type' => array(
				'description'       => __( 'Filter by content type', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from' => array(
				'description'       => __( 'Start date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to' => array(
				'description'       => __( 'End date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get parameters for export endpoint.
	 *
	 * @return array
	 */
	protected function get_export_params() {
		return array(
			'format' => array(
				'description'       => __( 'Export format', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'default'           => 'csv',
				'enum'              => array( 'csv', 'json' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'provider' => array(
				'description'       => __( 'Filter by AI provider', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content_type' => array(
				'description'       => __( 'Filter by content type', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from' => array(
				'description'       => __( 'Start date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to' => array(
				'description'       => __( 'End date for filtering', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'format'            => 'date',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Get parameters for track endpoint.
	 *
	 * @return array
	 */
	protected function get_track_params() {
		return array(
			'generation_id' => array(
				'description'       => __( 'Generation ID', 'wp-ai-site-generator' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'quality_score' => array(
				'description'       => __( 'Quality score (0-100)', 'wp-ai-site-generator' ),
				'type'              => 'number',
				'required'          => true,
				'minimum'           => 0,
				'maximum'           => 100,
			),
			'metrics' => array(
				'description'       => __( 'Additional metrics data', 'wp-ai-site-generator' ),
				'type'              => 'object',
			),
			'provider' => array(
				'description'       => __( 'AI provider used', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content_type' => array(
				'description'       => __( 'Type of content generated', 'wp-ai-site-generator' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'validation_results' => array(
				'description'       => __( 'Validation results', 'wp-ai-site-generator' ),
				'type'              => 'object',
			),
		);
	}

	/**
	 * Calculate statistics from metrics.
	 *
	 * @param array $metrics Metrics data.
	 * @return array Statistics.
	 */
	protected function calculate_statistics( $metrics ) {
		if ( empty( $metrics ) ) {
			return array(
				'average_score' => 0,
				'median_score'  => 0,
				'min_score'     => 0,
				'max_score'     => 0,
				'std_deviation' => 0,
			);
		}

		$scores = array_column( $metrics, 'quality_score' );
		$count = count( $scores );

		// Calculate average
		$average = array_sum( $scores ) / $count;

		// Calculate median
		sort( $scores );
		$median = $count % 2 === 0
			? ( $scores[ $count / 2 - 1 ] + $scores[ $count / 2 ] ) / 2
			: $scores[ floor( $count / 2 ) ];

		// Calculate standard deviation
		$variance = 0;
		foreach ( $scores as $score ) {
			$variance += pow( $score - $average, 2 );
		}
		$std_deviation = sqrt( $variance / $count );

		return array(
			'average_score' => round( $average, 2 ),
			'median_score'  => round( $median, 2 ),
			'min_score'     => min( $scores ),
			'max_score'     => max( $scores ),
			'std_deviation' => round( $std_deviation, 2 ),
		);
	}

	/**
	 * Calculate score distribution.
	 *
	 * @param array $metrics Metrics data.
	 * @return array Distribution data.
	 */
	protected function calculate_distribution( $metrics ) {
		$distribution = array(
			'0-20'   => 0,
			'20-40'  => 0,
			'40-60'  => 0,
			'60-80'  => 0,
			'80-100' => 0,
		);

		foreach ( $metrics as $metric ) {
			$score = $metric->quality_score;

			if ( $score <= 20 ) {
				$distribution['0-20']++;
			} elseif ( $score <= 40 ) {
				$distribution['20-40']++;
			} elseif ( $score <= 60 ) {
				$distribution['40-60']++;
			} elseif ( $score <= 80 ) {
				$distribution['60-80']++;
			} else {
				$distribution['80-100']++;
			}
		}

		return array_values( $distribution );
	}

	/**
	 * Calculate trend direction.
	 *
	 * @param array $trends Trend data.
	 * @return string Trend direction (improving, declining, stable).
	 */
	protected function calculate_trend_direction( $trends ) {
		if ( count( $trends ) < 2 ) {
			return 'stable';
		}

		// Get first and last period averages
		$first_half = array_slice( $trends, 0, ceil( count( $trends ) / 2 ) );
		$second_half = array_slice( $trends, floor( count( $trends ) / 2 ) );

		$first_avg = array_sum( array_column( $first_half, 'avg_score' ) ) / count( $first_half );
		$second_avg = array_sum( array_column( $second_half, 'avg_score' ) ) / count( $second_half );

		$difference = $second_avg - $first_avg;

		if ( $difference > 5 ) {
			return 'improving';
		} elseif ( $difference < -5 ) {
			return 'declining';
		}

		return 'stable';
	}

	/**
	 * Get trend summary.
	 *
	 * @param array $trends Trend data.
	 * @return array Summary data.
	 */
	protected function get_trend_summary( $trends ) {
		if ( empty( $trends ) ) {
			return array();
		}

		$total_generations = array_sum( array_column( $trends, 'generation_count' ) );
		$total_high_quality = array_sum( array_column( $trends, 'high_quality_count' ) );
		$total_low_quality = array_sum( array_column( $trends, 'low_quality_count' ) );

		$avg_scores = array_column( $trends, 'avg_score' );

		return array(
			'total_generations'  => $total_generations,
			'high_quality_rate'  => $total_generations > 0
				? ( $total_high_quality / $total_generations ) * 100
				: 0,
			'low_quality_rate'   => $total_generations > 0
				? ( $total_low_quality / $total_generations ) * 100
				: 0,
			'overall_avg_score'  => ! empty( $avg_scores )
				? array_sum( $avg_scores ) / count( $avg_scores )
				: 0,
			'best_period'        => $this->get_best_period( $trends ),
			'worst_period'       => $this->get_worst_period( $trends ),
		);
	}

	/**
	 * Get best performing period.
	 *
	 * @param array $trends Trend data.
	 * @return array Best period data.
	 */
	protected function get_best_period( $trends ) {
		if ( empty( $trends ) ) {
			return null;
		}

		$best = null;
		$best_score = 0;

		foreach ( $trends as $trend ) {
			if ( $trend['avg_score'] > $best_score ) {
				$best_score = $trend['avg_score'];
				$best = $trend;
			}
		}

		return $best;
	}

	/**
	 * Get worst performing period.
	 *
	 * @param array $trends Trend data.
	 * @return array Worst period data.
	 */
	protected function get_worst_period( $trends ) {
		if ( empty( $trends ) ) {
			return null;
		}

		$worst = null;
		$worst_score = 100;

		foreach ( $trends as $trend ) {
			if ( $trend['avg_score'] < $worst_score ) {
				$worst_score = $trend['avg_score'];
				$worst = $trend;
			}
		}

		return $worst;
	}

	/**
	 * Get real-time data.
	 *
	 * @return array Real-time metrics.
	 */
	protected function get_realtime_data() {
		// Get metrics for last hour
		$last_hour_metrics = $this->metrics_tracker->get_metrics( array(
			'date_from' => date( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
			'limit'     => 1000,
		) );

		// Get today's metrics
		$today_metrics = $this->metrics_tracker->get_metrics( array(
			'date_from' => date( 'Y-m-d 00:00:00' ),
			'limit'     => 1000,
		) );

		// Calculate real-time statistics
		$last_hour_avg = 0;
		if ( ! empty( $last_hour_metrics['results'] ) ) {
			$scores = array_column( $last_hour_metrics['results'], 'quality_score' );
			$last_hour_avg = ! empty( $scores ) ? array_sum( $scores ) / count( $scores ) : 0;
		}

		// Get active generations (assuming status is tracked)
		global $wpdb;
		$active_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}waisg_generation_history
			WHERE status = 'processing'
			AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
		);

		return array(
			'active'       => intval( $active_count ),
			'lastHourAvg'  => round( $last_hour_avg, 1 ),
			'lastHourCount'=> $last_hour_metrics['total'],
			'todayCount'   => $today_metrics['total'],
			'todayAvg'     => $this->calculate_statistics( $today_metrics['results'] )['average_score'],
		);
	}

	/**
	 * Generate insights based on metrics.
	 *
	 * @return array Insights.
	 */
	protected function generate_insights() {
		$insights = array();

		// Get recent trends
		$recent_trends = $this->metrics_tracker->get_score_trends( 'day', array(
			'date_from' => date( 'Y-m-d', strtotime( '-7 days' ) ),
		) );

		// Analyze trends
		if ( ! empty( $recent_trends ) ) {
			$trend_direction = $this->calculate_trend_direction( $recent_trends );

			if ( 'improving' === $trend_direction ) {
				$insights[] = array(
					'type'    => 'positive',
					'message' => __( 'Quality scores have been improving over the past week.', 'wp-ai-site-generator' ),
				);
			} elseif ( 'declining' === $trend_direction ) {
				$insights[] = array(
					'type'    => 'negative',
					'message' => __( 'Quality scores have been declining. Consider reviewing recent changes.', 'wp-ai-site-generator' ),
				);
			}
		}

		// Get provider comparison
		$provider_comparison = $this->metrics_tracker->get_provider_comparison();

		if ( count( $provider_comparison ) > 1 ) {
			// Find best provider
			$best_provider = null;
			$best_score = 0;

			foreach ( $provider_comparison as $provider ) {
				if ( $provider->avg_score > $best_score && $provider->total_generations > 5 ) {
					$best_score = $provider->avg_score;
					$best_provider = $provider->provider;
				}
			}

			if ( $best_provider ) {
				$insights[] = array(
					'type'    => 'info',
					'message' => sprintf(
						__( '%s is currently your best performing provider with an average score of %s%%.', 'wp-ai-site-generator' ),
						ucfirst( $best_provider ),
						round( $best_score, 1 )
					),
				);
			}
		}

		return $insights;
	}
}