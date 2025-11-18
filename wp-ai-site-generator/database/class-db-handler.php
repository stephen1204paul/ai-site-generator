<?php
/**
 * Database Handler Class.
 *
 * Handles all database operations for the plugin.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database;

/**
 * Database Handler Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 */
class DB_Handler {

	/**
	 * WordPress database object.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      \wpdb    $wpdb    WordPress database object.
	 */
	private $wpdb;

	/**
	 * Table names.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $tables    Table names.
	 */
	private $tables;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->set_table_names();
	}

	/**
	 * Set table names.
	 *
	 * @since    1.0.0
	 */
	private function set_table_names() {
		$this->tables = array(
			'generations'      => $this->wpdb->prefix . 'waisg_generations',
			'generated_content'=> $this->wpdb->prefix . 'waisg_generated_content',
			'provider_usage'   => $this->wpdb->prefix . 'waisg_provider_usage',
			'templates'        => $this->wpdb->prefix . 'waisg_templates',
		);
	}

	/**
	 * Create a generation record.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Generation data.
	 * @return   int|false         Generation ID or false on failure.
	 */
	public function create_generation( $data ) {
		$defaults = array(
			'user_id'        => get_current_user_id(),
			'prompt'         => '',
			'provider'       => 'openai',
			'model'          => '',
			'status'         => 'pending',
			'progress'       => 0,
			'result'         => null,
			'error_message'  => null,
			'metadata'       => null,
			'started_at'     => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Encode arrays/objects to JSON
		if ( is_array( $data['metadata'] ) || is_object( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}
		if ( is_array( $data['result'] ) || is_object( $data['result'] ) ) {
			$data['result'] = wp_json_encode( $data['result'] );
		}

		$result = $this->wpdb->insert(
			$this->tables['generations'],
			$data,
			array(
				'%d', // user_id
				'%s', // prompt
				'%s', // provider
				'%s', // model
				'%s', // status
				'%d', // progress
				'%s', // result
				'%s', // error_message
				'%s', // metadata
				'%s', // started_at
			)
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Update a generation record.
	 *
	 * @since    1.0.0
	 * @param    int      $generation_id    Generation ID.
	 * @param    array    $data             Data to update.
	 * @return   bool                       True on success, false on failure.
	 */
	public function update_generation( $generation_id, $data ) {
		// Handle JSON encoding for complex fields
		if ( isset( $data['metadata'] ) && ( is_array( $data['metadata'] ) || is_object( $data['metadata'] ) ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}
		if ( isset( $data['result'] ) && ( is_array( $data['result'] ) || is_object( $data['result'] ) ) ) {
			$data['result'] = wp_json_encode( $data['result'] );
		}

		// Set completed_at if status is completed
		if ( isset( $data['status'] ) && 'completed' === $data['status'] ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		$result = $this->wpdb->update(
			$this->tables['generations'],
			$data,
			array( 'id' => $generation_id )
		);

		return $result !== false;
	}

	/**
	 * Get a generation record.
	 *
	 * @since    1.0.0
	 * @param    int    $generation_id    Generation ID.
	 * @return   object|null             Generation object or null.
	 */
	public function get_generation( $generation_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['generations']} WHERE id = %d",
			$generation_id
		);

		$generation = $this->wpdb->get_row( $query );

		if ( $generation ) {
			// Decode JSON fields
			if ( $generation->metadata ) {
				$generation->metadata = json_decode( $generation->metadata, true );
			}
			if ( $generation->result ) {
				$generation->result = json_decode( $generation->result, true );
			}
		}

		return $generation;
	}

	/**
	 * Get generations.
	 *
	 * @since    1.0.0
	 * @param    array    $args    Query arguments.
	 * @return   array             Array of generations.
	 */
	public function get_generations( $args = array() ) {
		$defaults = array(
			'user_id' => null,
			'status'  => null,
			'limit'   => 20,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );

		if ( $args['user_id'] ) {
			$where_clauses[] = $this->wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		if ( $args['status'] ) {
			$where_clauses[] = $this->wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Validate orderby
		$allowed_orderby = array( 'id', 'created_at', 'updated_at', 'status', 'progress' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

		// Validate order
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['generations']}
			WHERE {$where_sql}
			ORDER BY {$orderby} {$order}
			LIMIT %d OFFSET %d",
			$args['limit'],
			$args['offset']
		);

		$generations = $this->wpdb->get_results( $query );

		// Decode JSON fields
		foreach ( $generations as $generation ) {
			if ( $generation->metadata ) {
				$generation->metadata = json_decode( $generation->metadata, true );
			}
			if ( $generation->result ) {
				$generation->result = json_decode( $generation->result, true );
			}
		}

		return $generations;
	}

	/**
	 * Get generations count.
	 *
	 * @since    1.0.0
	 * @param    array    $args    Query arguments.
	 * @return   int               Number of generations.
	 */
	public function get_generations_count( $args = array() ) {
		$defaults = array(
			'user_id' => null,
			'status'  => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( '1=1' );

		if ( $args['user_id'] ) {
			$where_clauses[] = $this->wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		if ( $args['status'] ) {
			$where_clauses[] = $this->wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = "SELECT COUNT(*) FROM {$this->tables['generations']} WHERE {$where_sql}";

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Delete a generation.
	 *
	 * @since    1.0.0
	 * @param    int    $generation_id    Generation ID.
	 * @return   bool                     True on success, false on failure.
	 */
	public function delete_generation( $generation_id ) {
		// Delete associated content
		$this->wpdb->delete(
			$this->tables['generated_content'],
			array( 'generation_id' => $generation_id ),
			array( '%d' )
		);

		// Delete generation
		$result = $this->wpdb->delete(
			$this->tables['generations'],
			array( 'id' => $generation_id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Save generated content.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Content data.
	 * @return   int|false         Content ID or false on failure.
	 */
	public function save_generated_content( $data ) {
		$defaults = array(
			'generation_id' => null,
			'post_id'       => null,
			'block_type'    => '',
			'block_content' => '',
			'prompt'        => null,
			'provider'      => null,
			'model'         => null,
			'tokens_used'   => null,
			'cost'          => null,
			'metadata'      => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Encode metadata if needed
		if ( is_array( $data['metadata'] ) || is_object( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$result = $this->wpdb->insert(
			$this->tables['generated_content'],
			$data
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get generated content.
	 *
	 * @since    1.0.0
	 * @param    int    $content_id    Content ID.
	 * @return   object|null           Content object or null.
	 */
	public function get_generated_content( $content_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['generated_content']} WHERE id = %d",
			$content_id
		);

		$content = $this->wpdb->get_row( $query );

		if ( $content && $content->metadata ) {
			$content->metadata = json_decode( $content->metadata, true );
		}

		return $content;
	}

	/**
	 * Get generated content by post ID.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    Post ID.
	 * @return   array              Array of content objects.
	 */
	public function get_content_by_post( $post_id ) {
		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['generated_content']}
			WHERE post_id = %d
			ORDER BY created_at DESC",
			$post_id
		);

		$contents = $this->wpdb->get_results( $query );

		foreach ( $contents as $content ) {
			if ( $content->metadata ) {
				$content->metadata = json_decode( $content->metadata, true );
			}
		}

		return $contents;
	}

	/**
	 * Track provider usage.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @param    string    $model       Model used.
	 * @param    array     $usage_data  Usage data.
	 * @return   int|false              Usage ID or false on failure.
	 */
	public function track_provider_usage( $provider, $model, $usage_data ) {
		$data = array(
			'provider'      => $provider,
			'model'         => $model,
			'user_id'       => get_current_user_id(),
			'tokens_input'  => $usage_data['tokens_input'] ?? 0,
			'tokens_output' => $usage_data['tokens_output'] ?? 0,
			'tokens_total'  => $usage_data['tokens_total'] ?? 0,
			'cost'          => $usage_data['cost'] ?? 0.0,
			'request_type'  => $usage_data['request_type'] ?? null,
			'metadata'      => isset( $usage_data['metadata'] ) ? wp_json_encode( $usage_data['metadata'] ) : null,
		);

		$result = $this->wpdb->insert(
			$this->tables['provider_usage'],
			$data
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get provider usage statistics.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @param    string    $period      Time period (day, week, month, year).
	 * @return   array                  Usage statistics.
	 */
	public function get_provider_usage( $provider, $period = 'month' ) {
		$date_filter = $this->get_date_filter( $period );

		$query = $this->wpdb->prepare(
			"SELECT
				COUNT(*) as total_requests,
				SUM(tokens_input) as total_tokens_input,
				SUM(tokens_output) as total_tokens_output,
				SUM(tokens_total) as total_tokens,
				SUM(cost) as total_cost,
				AVG(tokens_total) as avg_tokens_per_request,
				AVG(cost) as avg_cost_per_request
			FROM {$this->tables['provider_usage']}
			WHERE provider = %s AND created_at >= %s",
			$provider,
			$date_filter
		);

		$stats = $this->wpdb->get_row( $query, ARRAY_A );

		// Get daily breakdown
		$daily_query = $this->wpdb->prepare(
			"SELECT
				DATE(created_at) as date,
				COUNT(*) as requests,
				SUM(tokens_total) as tokens,
				SUM(cost) as cost
			FROM {$this->tables['provider_usage']}
			WHERE provider = %s AND created_at >= %s
			GROUP BY DATE(created_at)
			ORDER BY date DESC",
			$provider,
			$date_filter
		);

		$stats['daily_breakdown'] = $this->wpdb->get_results( $daily_query, ARRAY_A );

		return $stats;
	}

	/**
	 * Get usage statistics.
	 *
	 * @since    1.0.0
	 * @param    string    $period    Time period.
	 * @return   array                Usage statistics.
	 */
	public function get_usage_statistics( $period = 'month' ) {
		$date_filter = $this->get_date_filter( $period );

		// Get generation statistics
		$gen_query = $this->wpdb->prepare(
			"SELECT
				COUNT(*) as total_generations,
				COUNT(DISTINCT user_id) as unique_users,
				COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_generations,
				COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_generations,
				AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL
					THEN TIMESTAMPDIFF(SECOND, started_at, completed_at) END) as avg_generation_time
			FROM {$this->tables['generations']}
			WHERE created_at >= %s",
			$date_filter
		);

		$generation_stats = $this->wpdb->get_row( $gen_query, ARRAY_A );

		// Get provider usage statistics
		$provider_query = $this->wpdb->prepare(
			"SELECT
				provider,
				COUNT(*) as requests,
				SUM(tokens_total) as tokens,
				SUM(cost) as cost
			FROM {$this->tables['provider_usage']}
			WHERE created_at >= %s
			GROUP BY provider",
			$date_filter
		);

		$provider_stats = $this->wpdb->get_results( $provider_query, ARRAY_A );

		// Get content statistics
		$content_query = $this->wpdb->prepare(
			"SELECT
				block_type,
				COUNT(*) as count
			FROM {$this->tables['generated_content']}
			WHERE created_at >= %s
			GROUP BY block_type",
			$date_filter
		);

		$content_stats = $this->wpdb->get_results( $content_query, ARRAY_A );

		return array(
			'period'     => $period,
			'start_date' => $date_filter,
			'generation' => $generation_stats,
			'providers'  => $provider_stats,
			'content'    => $content_stats,
		);
	}

	/**
	 * Create a template.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Template data.
	 * @return   int|false         Template ID or false on failure.
	 */
	public function create_template( $data ) {
		$defaults = array(
			'name'            => '',
			'slug'            => '',
			'description'     => null,
			'prompt_template' => '',
			'configuration'   => null,
			'category'        => 'general',
			'is_active'       => 1,
			'created_by'      => get_current_user_id(),
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate slug if not provided
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Encode configuration if needed
		if ( is_array( $data['configuration'] ) || is_object( $data['configuration'] ) ) {
			$data['configuration'] = wp_json_encode( $data['configuration'] );
		}

		$result = $this->wpdb->insert(
			$this->tables['templates'],
			$data
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get templates.
	 *
	 * @since    1.0.0
	 * @param    array    $args    Query arguments.
	 * @return   array             Array of templates.
	 */
	public function get_templates( $args = array() ) {
		$defaults = array(
			'category'  => null,
			'is_active' => true,
			'orderby'   => 'usage_count',
			'order'     => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();

		if ( $args['category'] ) {
			$where_clauses[] = $this->wpdb->prepare( 'category = %s', $args['category'] );
		}

		if ( $args['is_active'] !== null ) {
			$where_clauses[] = $this->wpdb->prepare( 'is_active = %d', $args['is_active'] ? 1 : 0 );
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Validate orderby
		$allowed_orderby = array( 'id', 'name', 'category', 'usage_count', 'created_at' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'usage_count';

		// Validate order
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$query = "SELECT * FROM {$this->tables['templates']}
				 {$where_sql}
				 ORDER BY {$orderby} {$order}";

		$templates = $this->wpdb->get_results( $query );

		// Decode configuration
		foreach ( $templates as $template ) {
			if ( $template->configuration ) {
				$template->configuration = json_decode( $template->configuration, true );
			}
		}

		return $templates;
	}

	/**
	 * Get a template.
	 *
	 * @since    1.0.0
	 * @param    int|string    $template    Template ID or slug.
	 * @return   object|null                Template object or null.
	 */
	public function get_template( $template ) {
		if ( is_numeric( $template ) ) {
			$query = $this->wpdb->prepare(
				"SELECT * FROM {$this->tables['templates']} WHERE id = %d",
				$template
			);
		} else {
			$query = $this->wpdb->prepare(
				"SELECT * FROM {$this->tables['templates']} WHERE slug = %s",
				$template
			);
		}

		$template = $this->wpdb->get_row( $query );

		if ( $template && $template->configuration ) {
			$template->configuration = json_decode( $template->configuration, true );
		}

		return $template;
	}

	/**
	 * Update template usage count.
	 *
	 * @since    1.0.0
	 * @param    int    $template_id    Template ID.
	 * @return   bool                   True on success, false on failure.
	 */
	public function increment_template_usage( $template_id ) {
		$query = $this->wpdb->prepare(
			"UPDATE {$this->tables['templates']}
			SET usage_count = usage_count + 1
			WHERE id = %d",
			$template_id
		);

		return $this->wpdb->query( $query ) !== false;
	}

	/**
	 * Clean up old logs.
	 *
	 * @since    1.0.0
	 * @param    int    $days    Number of days to keep logs.
	 * @return   int              Number of deleted records.
	 */
	public function cleanup_old_logs( $days = 30 ) {
		$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$deleted = 0;

		// Clean old generations
		$deleted += $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->tables['generations']}
				WHERE created_at < %s AND status IN ('completed', 'failed', 'cancelled')",
				$date_threshold
			)
		);

		// Clean old usage logs
		$deleted += $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->tables['provider_usage']}
				WHERE created_at < %s",
				$date_threshold
			)
		);

		return $deleted;
	}

	/**
	 * Log provider error.
	 *
	 * @since    1.0.0
	 * @param    string    $provider    Provider name.
	 * @param    string    $error       Error message.
	 * @param    array     $context     Error context.
	 * @return   int|false              Log ID or false on failure.
	 */
	public function log_provider_error( $provider, $error, $context = array() ) {
		return $this->track_provider_usage(
			$provider,
			'',
			array(
				'request_type' => 'error',
				'metadata'     => array(
					'error'   => $error,
					'context' => $context,
				),
			)
		);
	}

	/**
	 * Get date filter for queries.
	 *
	 * @since    1.0.0
	 * @param    string    $period    Time period.
	 * @return   string               Date filter.
	 */
	private function get_date_filter( $period ) {
		switch ( $period ) {
			case 'day':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
			case 'week':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 week' ) );
			case 'month':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );
			case 'year':
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
			default:
				return gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );
		}
	}
}