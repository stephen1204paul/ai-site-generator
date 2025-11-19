<?php
/**
 * Generation Job Manager Class.
 *
 * Manages background job processing for long-running generation tasks.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Database\DB_Handler;
use WP_Error;

/**
 * Generation Job Manager Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Generation_Job {

	/**
	 * Database handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler.
	 */
	private $db_handler;

	/**
	 * Generation orchestrator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Generation_Orchestrator    $orchestrator    Generation orchestrator.
	 */
	private $orchestrator;

	/**
	 * Job queue.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $queue    Job queue.
	 */
	private $queue = array();

	/**
	 * Currently processing job.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object|null    $current_job    Current job.
	 */
	private $current_job = null;

	/**
	 * Maximum execution time per batch (in seconds).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $max_execution_time    Max execution time.
	 */
	private $max_execution_time = 30;

	/**
	 * Job status constants.
	 *
	 * @since    1.0.0
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED = 'failed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_PAUSED = 'paused';

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->db_handler = new DB_Handler();
		$this->orchestrator = new Generation_Orchestrator();

		// Set up hooks
		$this->setup_hooks();
	}

	/**
	 * Set up WordPress hooks.
	 *
	 * @since    1.0.0
	 */
	private function setup_hooks() {
		// Schedule cron for background processing
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( 'waisg_process_generation_queue', array( $this, 'process_queue' ) );

		// AJAX handlers for job control
		add_action( 'wp_ajax_waisg_pause_job', array( $this, 'ajax_pause_job' ) );
		add_action( 'wp_ajax_waisg_resume_job', array( $this, 'ajax_resume_job' ) );
		add_action( 'wp_ajax_waisg_cancel_job', array( $this, 'ajax_cancel_job' ) );
		add_action( 'wp_ajax_waisg_get_job_status', array( $this, 'ajax_get_job_status' ) );

		// Handle shutdown for graceful job suspension
		register_shutdown_function( array( $this, 'handle_shutdown' ) );
	}

	/**
	 * Schedule cron event for queue processing.
	 *
	 * @since    1.0.0
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( 'waisg_process_generation_queue' ) ) {
			wp_schedule_event( time(), 'waisg_every_minute', 'waisg_process_generation_queue' );
		}

		// Add custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @since    1.0.0
	 * @param    array    $schedules    Existing schedules.
	 * @return   array                  Modified schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['waisg_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'wp-ai-site-generator' ),
		);

		return $schedules;
	}

	/**
	 * Create a new generation job.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Job configuration.
	 * @return   int|WP_Error        Job ID or error.
	 */
	public function create_job( $config ) {
		try {
			// Validate configuration
			$validation = $this->validate_job_config( $config );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			// Check user limits
			$limit_check = $this->check_user_limits();
			if ( is_wp_error( $limit_check ) ) {
				return $limit_check;
			}

			// Create job record
			$job_data = array(
				'user_id'    => get_current_user_id(),
				'type'       => $config['type'] ?? 'generation',
				'priority'   => $config['priority'] ?? 5,
				'status'     => self::STATUS_PENDING,
				'config'     => wp_json_encode( $config ),
				'created_at' => current_time( 'mysql' ),
				'metadata'   => wp_json_encode( array(
					'source'     => $config['source'] ?? 'manual',
					'session_id' => $config['session_id'] ?? null,
					'retry_count'=> 0,
				) ),
			);

			$job_id = $this->db_handler->create_generation_job( $job_data );

			if ( ! $job_id ) {
				return new WP_Error(
					'job_creation_failed',
					__( 'Failed to create generation job', 'wp-ai-site-generator' )
				);
			}

			// Add to queue if immediate processing requested
			if ( ! empty( $config['immediate'] ) ) {
				$this->add_to_queue( $job_id, true );
			}

			// Trigger job created action
			do_action( 'waisg_job_created', $job_id, $config );

			return $job_id;

		} catch ( \Exception $e ) {
			return new WP_Error(
				'job_creation_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Add job to processing queue.
	 *
	 * @since    1.0.0
	 * @param    int     $job_id      Job ID.
	 * @param    bool    $priority    Priority flag.
	 * @return   bool                 Success status.
	 */
	public function add_to_queue( $job_id, $priority = false ) {
		$job = $this->db_handler->get_generation_job( $job_id );

		if ( ! $job ) {
			return false;
		}

		// Add to queue
		if ( $priority ) {
			array_unshift( $this->queue, $job );
		} else {
			$this->queue[] = $job;
		}

		// Sort by priority
		usort( $this->queue, function( $a, $b ) {
			return $b->priority - $a->priority;
		} );

		// Trigger immediate processing if not running
		if ( ! $this->is_processing() ) {
			$this->process_next();
		}

		return true;
	}

	/**
	 * Process job queue.
	 *
	 * @since    1.0.0
	 */
	public function process_queue() {
		// Check if already processing
		if ( $this->is_processing() ) {
			return;
		}

		// Get pending jobs from database
		$pending_jobs = $this->db_handler->get_pending_jobs( array(
			'limit'   => 10,
			'orderby' => 'priority',
			'order'   => 'DESC',
		) );

		if ( empty( $pending_jobs ) ) {
			return;
		}

		// Add to queue
		foreach ( $pending_jobs as $job ) {
			if ( ! $this->is_job_in_queue( $job->id ) ) {
				$this->queue[] = $job;
			}
		}

		// Process next job
		$this->process_next();
	}

	/**
	 * Process next job in queue.
	 *
	 * @since    1.0.0
	 * @return   bool    Success status.
	 */
	private function process_next() {
		if ( empty( $this->queue ) ) {
			return false;
		}

		// Get next job
		$job = array_shift( $this->queue );
		$this->current_job = $job;

		// Update job status
		$this->update_job_status( $job->id, self::STATUS_PROCESSING );

		// Set time limit for processing
		$start_time = time();
		set_time_limit( $this->max_execution_time + 30 );

		try {
			// Process based on job type
			$result = $this->process_job( $job );

			if ( is_wp_error( $result ) ) {
				$this->handle_job_failure( $job, $result );
			} else {
				$this->complete_job( $job, $result );
			}

		} catch ( \Exception $e ) {
			$this->handle_job_failure( $job, new WP_Error(
				'processing_error',
				$e->getMessage()
			) );
		}

		// Check execution time
		if ( ( time() - $start_time ) < $this->max_execution_time && ! empty( $this->queue ) ) {
			// Process another job if time allows
			$this->process_next();
		}

		$this->current_job = null;
		return true;
	}

	/**
	 * Process individual job.
	 *
	 * @since    1.0.0
	 * @param    object    $job    Job object.
	 * @return   array|WP_Error    Result or error.
	 */
	private function process_job( $job ) {
		$config = json_decode( $job->config, true );

		if ( ! $config ) {
			return new WP_Error(
				'invalid_config',
				__( 'Invalid job configuration', 'wp-ai-site-generator' )
			);
		}

		// Check if job is paused
		if ( $job->status === self::STATUS_PAUSED ) {
			// Re-add to queue for later
			$this->queue[] = $job;
			return array( 'paused' => true );
		}

		// Get partial result if resuming
		$partial_result = null;
		if ( ! empty( $job->partial_result ) ) {
			$partial_result = json_decode( $job->partial_result, true );
		}

		// Process based on type
		switch ( $job->type ) {
			case 'site_generation':
				return $this->process_site_generation( $config, $job, $partial_result );

			case 'page_generation':
				return $this->process_page_generation( $config, $job, $partial_result );

			case 'section_regeneration':
				return $this->process_section_regeneration( $config, $job, $partial_result );

			case 'bulk_generation':
				return $this->process_bulk_generation( $config, $job, $partial_result );

			default:
				return $this->process_custom_job( $job->type, $config, $job, $partial_result );
		}
	}

	/**
	 * Process site generation job.
	 *
	 * @since    1.0.0
	 * @param    array     $config           Job configuration.
	 * @param    object    $job              Job object.
	 * @param    array     $partial_result   Partial result if resuming.
	 * @return   array|WP_Error              Result or error.
	 */
	private function process_site_generation( $config, $job, $partial_result = null ) {
		// Update progress callback
		$config['progress_callback'] = function( $progress, $message ) use ( $job ) {
			$this->update_job_progress( $job->id, $progress, $message );
		};

		// Check if resuming
		if ( $partial_result && isset( $partial_result['completed_pages'] ) ) {
			$config['resume_from'] = $partial_result['completed_pages'];
		}

		// Process generation
		$result = $this->orchestrator->generate_site( $config );

		// Store partial result if needed
		if ( is_wp_error( $result ) && $result->get_error_code() === 'partial_completion' ) {
			$this->save_partial_result( $job->id, $result->get_error_data() );
		}

		return $result;
	}

	/**
	 * Process page generation job.
	 *
	 * @since    1.0.0
	 * @param    array     $config           Job configuration.
	 * @param    object    $job              Job object.
	 * @param    array     $partial_result   Partial result if resuming.
	 * @return   array|WP_Error              Result or error.
	 */
	private function process_page_generation( $config, $job, $partial_result = null ) {
		// Update progress callback
		$config['progress_callback'] = function( $progress, $message ) use ( $job ) {
			$this->update_job_progress( $job->id, $progress, $message );
		};

		return $this->orchestrator->generate_single_page( $config );
	}

	/**
	 * Process section regeneration job.
	 *
	 * @since    1.0.0
	 * @param    array     $config           Job configuration.
	 * @param    object    $job              Job object.
	 * @param    array     $partial_result   Partial result if resuming.
	 * @return   array|WP_Error              Result or error.
	 */
	private function process_section_regeneration( $config, $job, $partial_result = null ) {
		// Update progress callback
		$config['progress_callback'] = function( $progress, $message ) use ( $job ) {
			$this->update_job_progress( $job->id, $progress, $message );
		};

		return $this->orchestrator->regenerate_section( $config );
	}

	/**
	 * Process bulk generation job.
	 *
	 * @since    1.0.0
	 * @param    array     $config           Job configuration.
	 * @param    object    $job              Job object.
	 * @param    array     $partial_result   Partial result if resuming.
	 * @return   array|WP_Error              Result or error.
	 */
	private function process_bulk_generation( $config, $job, $partial_result = null ) {
		$results = array(
			'successful' => array(),
			'failed'     => array(),
			'total'      => count( $config['items'] ),
		);

		// Resume from last position if partial result exists
		$start_index = 0;
		if ( $partial_result ) {
			$results = array_merge( $results, $partial_result );
			$start_index = count( $results['successful'] ) + count( $results['failed'] );
		}

		// Process each item
		for ( $i = $start_index; $i < count( $config['items'] ); $i++ ) {
			$item = $config['items'][$i];

			// Check if should pause (time limit or pause request)
			if ( $this->should_pause_job( $job->id ) ) {
				$this->save_partial_result( $job->id, $results );
				$this->update_job_status( $job->id, self::STATUS_PAUSED );
				return array( 'paused' => true, 'partial' => $results );
			}

			// Update progress
			$progress = ( ( $i + 1 ) / count( $config['items'] ) ) * 100;
			$this->update_job_progress( $job->id, $progress, sprintf(
				__( 'Processing item %d of %d', 'wp-ai-site-generator' ),
				$i + 1,
				count( $config['items'] )
			) );

			// Process item
			$item_result = $this->orchestrator->generate_single_page( $item );

			if ( is_wp_error( $item_result ) ) {
				$results['failed'][] = array(
					'item'  => $item,
					'error' => $item_result->get_error_message(),
				);
			} else {
				$results['successful'][] = $item_result;
			}
		}

		return $results;
	}

	/**
	 * Process custom job type.
	 *
	 * @since    1.0.0
	 * @param    string    $type             Job type.
	 * @param    array     $config           Job configuration.
	 * @param    object    $job              Job object.
	 * @param    array     $partial_result   Partial result if resuming.
	 * @return   array|WP_Error              Result or error.
	 */
	private function process_custom_job( $type, $config, $job, $partial_result = null ) {
		// Allow extensions to handle custom job types
		return apply_filters( 'waisg_process_custom_job', null, $type, $config, $job, $partial_result );
	}

	/**
	 * Complete job successfully.
	 *
	 * @since    1.0.0
	 * @param    object    $job       Job object.
	 * @param    array     $result    Job result.
	 * @return   bool                 Success status.
	 */
	private function complete_job( $job, $result ) {
		$update = $this->db_handler->update_generation_job( $job->id, array(
			'status'       => self::STATUS_COMPLETED,
			'result'       => wp_json_encode( $result ),
			'completed_at' => current_time( 'mysql' ),
		) );

		// Clean up partial results
		$this->clear_partial_result( $job->id );

		// Trigger completion action
		do_action( 'waisg_job_completed', $job->id, $result );

		// Send notification if configured
		$this->send_job_notification( $job, 'completed', $result );

		return $update;
	}

	/**
	 * Handle job failure.
	 *
	 * @since    1.0.0
	 * @param    object      $job     Job object.
	 * @param    WP_Error    $error   Error object.
	 * @return   bool                 Success status.
	 */
	private function handle_job_failure( $job, $error ) {
		$metadata = json_decode( $job->metadata, true ) ?? array();
		$retry_count = $metadata['retry_count'] ?? 0;
		$max_retries = get_option( 'waisg_max_job_retries', 3 );

		// Check if should retry
		if ( $retry_count < $max_retries && $this->is_retryable_error( $error ) ) {
			// Update retry count
			$metadata['retry_count'] = $retry_count + 1;
			$metadata['last_error'] = $error->get_error_message();

			$this->db_handler->update_generation_job( $job->id, array(
				'status'   => self::STATUS_PENDING,
				'metadata' => wp_json_encode( $metadata ),
			) );

			// Re-add to queue with delay
			$this->schedule_retry( $job->id, $retry_count + 1 );

			return true;
		}

		// Mark as failed
		$update = $this->db_handler->update_generation_job( $job->id, array(
			'status'         => self::STATUS_FAILED,
			'error_message'  => $error->get_error_message(),
			'error_data'     => wp_json_encode( $error->get_error_data() ),
			'completed_at'   => current_time( 'mysql' ),
		) );

		// Trigger failure action
		do_action( 'waisg_job_failed', $job->id, $error );

		// Send notification
		$this->send_job_notification( $job, 'failed', $error );

		return $update;
	}

	/**
	 * Check if error is retryable.
	 *
	 * @since    1.0.0
	 * @param    WP_Error    $error    Error object.
	 * @return   bool                  Whether error is retryable.
	 */
	private function is_retryable_error( $error ) {
		$retryable_codes = array(
			'api_timeout',
			'rate_limit',
			'temporary_failure',
			'connection_error',
		);

		return in_array( $error->get_error_code(), $retryable_codes, true );
	}

	/**
	 * Schedule job retry.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id          Job ID.
	 * @param    int    $attempt         Attempt number.
	 * @return   void
	 */
	private function schedule_retry( $job_id, $attempt ) {
		// Exponential backoff: 60s, 120s, 240s, etc.
		$delay = 60 * pow( 2, $attempt - 1 );

		wp_schedule_single_event(
			time() + $delay,
			'waisg_retry_job',
			array( $job_id )
		);
	}

	/**
	 * Update job status.
	 *
	 * @since    1.0.0
	 * @param    int       $job_id    Job ID.
	 * @param    string    $status    New status.
	 * @return   bool                 Success status.
	 */
	private function update_job_status( $job_id, $status ) {
		return $this->db_handler->update_generation_job( $job_id, array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		) );
	}

	/**
	 * Update job progress.
	 *
	 * @since    1.0.0
	 * @param    int       $job_id     Job ID.
	 * @param    int       $progress   Progress percentage.
	 * @param    string    $message    Progress message.
	 * @return   bool                  Success status.
	 */
	private function update_job_progress( $job_id, $progress, $message = '' ) {
		$update = $this->db_handler->update_generation_job( $job_id, array(
			'progress'         => min( 100, max( 0, $progress ) ),
			'progress_message' => $message,
			'updated_at'       => current_time( 'mysql' ),
		) );

		// Trigger progress update action for real-time updates
		do_action( 'waisg_job_progress', $job_id, $progress, $message );

		return $update;
	}

	/**
	 * Save partial result for resumption.
	 *
	 * @since    1.0.0
	 * @param    int      $job_id          Job ID.
	 * @param    array    $partial_result  Partial result data.
	 * @return   bool                      Success status.
	 */
	private function save_partial_result( $job_id, $partial_result ) {
		return $this->db_handler->update_generation_job( $job_id, array(
			'partial_result' => wp_json_encode( $partial_result ),
			'updated_at'     => current_time( 'mysql' ),
		) );
	}

	/**
	 * Clear partial result.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool              Success status.
	 */
	private function clear_partial_result( $job_id ) {
		return $this->db_handler->update_generation_job( $job_id, array(
			'partial_result' => null,
		) );
	}

	/**
	 * Check if should pause job.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool              Whether to pause.
	 */
	private function should_pause_job( $job_id ) {
		// Check for pause request
		$pause_flag = get_transient( 'waisg_pause_job_' . $job_id );
		if ( $pause_flag ) {
			delete_transient( 'waisg_pause_job_' . $job_id );
			return true;
		}

		// Check execution time
		if ( $this->get_execution_time() > $this->max_execution_time ) {
			return true;
		}

		// Check memory usage
		$memory_limit = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT );
		$memory_usage = memory_get_usage( true );
		if ( $memory_usage > $memory_limit * 0.9 ) {
			return true;
		}

		return false;
	}

	/**
	 * Get current execution time.
	 *
	 * @since    1.0.0
	 * @return   int    Execution time in seconds.
	 */
	private function get_execution_time() {
		static $start_time;

		if ( ! isset( $start_time ) ) {
			$start_time = time();
		}

		return time() - $start_time;
	}

	/**
	 * Validate job configuration.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Job configuration.
	 * @return   bool|WP_Error       True if valid, error otherwise.
	 */
	private function validate_job_config( $config ) {
		if ( empty( $config['type'] ) ) {
			return new WP_Error(
				'missing_type',
				__( 'Job type is required', 'wp-ai-site-generator' )
			);
		}

		// Validate based on type
		switch ( $config['type'] ) {
			case 'site_generation':
				if ( empty( $config['pages'] ) ) {
					return new WP_Error(
						'missing_pages',
						__( 'Page configuration is required for site generation', 'wp-ai-site-generator' )
					);
				}
				break;

			case 'page_generation':
				if ( empty( $config['title'] ) && empty( $config['prompt'] ) ) {
					return new WP_Error(
						'missing_content',
						__( 'Title or prompt is required for page generation', 'wp-ai-site-generator' )
					);
				}
				break;

			case 'section_regeneration':
				if ( empty( $config['post_id'] ) || empty( $config['section_id'] ) ) {
					return new WP_Error(
						'missing_identifiers',
						__( 'Post ID and section ID are required for section regeneration', 'wp-ai-site-generator' )
					);
				}
				break;
		}

		return true;
	}

	/**
	 * Check user limits.
	 *
	 * @since    1.0.0
	 * @return   bool|WP_Error    True if within limits, error otherwise.
	 */
	private function check_user_limits() {
		$user_id = get_current_user_id();

		// Check concurrent job limit
		$active_jobs = $this->db_handler->count_user_active_jobs( $user_id );
		$job_limit = get_option( 'waisg_user_job_limit', 5 );

		if ( $active_jobs >= $job_limit ) {
			return new WP_Error(
				'job_limit_exceeded',
				sprintf(
					__( 'You have reached the maximum of %d active jobs', 'wp-ai-site-generator' ),
					$job_limit
				)
			);
		}

		// Check daily generation limit
		$daily_generations = $this->db_handler->count_user_daily_generations( $user_id );
		$daily_limit = get_option( 'waisg_daily_generation_limit', 50 );

		if ( $daily_generations >= $daily_limit ) {
			return new WP_Error(
				'daily_limit_exceeded',
				sprintf(
					__( 'You have reached the daily limit of %d generations', 'wp-ai-site-generator' ),
					$daily_limit
				)
			);
		}

		return true;
	}

	/**
	 * Check if queue is processing.
	 *
	 * @since    1.0.0
	 * @return   bool    Whether processing.
	 */
	private function is_processing() {
		$lock = get_transient( 'waisg_processing_lock' );
		return $lock !== false;
	}

	/**
	 * Check if job is in queue.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool              Whether in queue.
	 */
	private function is_job_in_queue( $job_id ) {
		foreach ( $this->queue as $job ) {
			if ( $job->id === $job_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Send job notification.
	 *
	 * @since    1.0.0
	 * @param    object    $job       Job object.
	 * @param    string    $status    Job status.
	 * @param    mixed     $data      Additional data.
	 * @return   void
	 */
	private function send_job_notification( $job, $status, $data = null ) {
		// Check if notifications are enabled
		if ( ! get_option( 'waisg_enable_job_notifications', false ) ) {
			return;
		}

		// Get user email
		$user = get_user_by( 'id', $job->user_id );
		if ( ! $user ) {
			return;
		}

		// Prepare notification
		$subject = sprintf(
			__( 'Generation Job %s: %s', 'wp-ai-site-generator' ),
			ucfirst( $status ),
			$job->type
		);

		$message = $this->prepare_notification_message( $job, $status, $data );

		// Send email
		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Prepare notification message.
	 *
	 * @since    1.0.0
	 * @param    object    $job       Job object.
	 * @param    string    $status    Job status.
	 * @param    mixed     $data      Additional data.
	 * @return   string               Notification message.
	 */
	private function prepare_notification_message( $job, $status, $data ) {
		$message = sprintf(
			__( "Hello,\n\nYour generation job #%d has %s.\n\n", 'wp-ai-site-generator' ),
			$job->id,
			$status === 'completed' ? 'completed successfully' : 'failed'
		);

		if ( $status === 'completed' && is_array( $data ) ) {
			if ( isset( $data['post_id'] ) ) {
				$message .= sprintf(
					__( "View your generated content: %s\n", 'wp-ai-site-generator' ),
					get_permalink( $data['post_id'] )
				);
			}
		} elseif ( $status === 'failed' && is_wp_error( $data ) ) {
			$message .= sprintf(
				__( "Error: %s\n", 'wp-ai-site-generator' ),
				$data->get_error_message()
			);
		}

		$message .= sprintf(
			__( "\nJob Details:\n- Type: %s\n- Created: %s\n- Completed: %s\n", 'wp-ai-site-generator' ),
			$job->type,
			$job->created_at,
			current_time( 'mysql' )
		);

		return $message;
	}

	/**
	 * Handle shutdown gracefully.
	 *
	 * @since    1.0.0
	 */
	public function handle_shutdown() {
		if ( $this->current_job ) {
			// Save current state
			$this->update_job_status( $this->current_job->id, self::STATUS_PAUSED );

			// Log shutdown
			error_log( sprintf(
				'WP AI Site Generator: Job %d paused due to shutdown',
				$this->current_job->id
			) );
		}
	}

	/**
	 * Get job status.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   array|WP_Error    Job status or error.
	 */
	public function get_job_status( $job_id ) {
		$job = $this->db_handler->get_generation_job( $job_id );

		if ( ! $job ) {
			return new WP_Error(
				'job_not_found',
				__( 'Job not found', 'wp-ai-site-generator' )
			);
		}

		return array(
			'id'               => $job->id,
			'type'             => $job->type,
			'status'           => $job->status,
			'progress'         => $job->progress ?? 0,
			'progress_message' => $job->progress_message ?? '',
			'created_at'       => $job->created_at,
			'completed_at'     => $job->completed_at,
			'error'            => $job->error_message,
			'result'           => $job->result ? json_decode( $job->result, true ) : null,
			'metadata'         => $job->metadata ? json_decode( $job->metadata, true ) : null,
		);
	}

	/**
	 * Get job history.
	 *
	 * @since    1.0.0
	 * @param    array    $args    Query arguments.
	 * @return   array             Job history.
	 */
	public function get_job_history( $args = array() ) {
		$defaults = array(
			'user_id' => get_current_user_id(),
			'limit'   => 20,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->db_handler->get_generation_jobs( $args );
	}

	/**
	 * Pause job.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool|WP_Error     Success or error.
	 */
	public function pause_job( $job_id ) {
		$job = $this->db_handler->get_generation_job( $job_id );

		if ( ! $job ) {
			return new WP_Error(
				'job_not_found',
				__( 'Job not found', 'wp-ai-site-generator' )
			);
		}

		if ( $job->status !== self::STATUS_PROCESSING ) {
			return new WP_Error(
				'invalid_status',
				__( 'Job is not currently processing', 'wp-ai-site-generator' )
			);
		}

		// Set pause flag
		set_transient( 'waisg_pause_job_' . $job_id, true, 300 );

		return true;
	}

	/**
	 * Resume job.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool|WP_Error     Success or error.
	 */
	public function resume_job( $job_id ) {
		$job = $this->db_handler->get_generation_job( $job_id );

		if ( ! $job ) {
			return new WP_Error(
				'job_not_found',
				__( 'Job not found', 'wp-ai-site-generator' )
			);
		}

		if ( $job->status !== self::STATUS_PAUSED ) {
			return new WP_Error(
				'invalid_status',
				__( 'Job is not paused', 'wp-ai-site-generator' )
			);
		}

		// Update status and add to queue
		$this->update_job_status( $job_id, self::STATUS_PENDING );
		$this->add_to_queue( $job_id, true );

		return true;
	}

	/**
	 * Cancel job.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool|WP_Error     Success or error.
	 */
	public function cancel_job( $job_id ) {
		$job = $this->db_handler->get_generation_job( $job_id );

		if ( ! $job ) {
			return new WP_Error(
				'job_not_found',
				__( 'Job not found', 'wp-ai-site-generator' )
			);
		}

		if ( in_array( $job->status, array( self::STATUS_COMPLETED, self::STATUS_FAILED ), true ) ) {
			return new WP_Error(
				'job_finished',
				__( 'Cannot cancel finished job', 'wp-ai-site-generator' )
			);
		}

		// Update status
		$this->update_job_status( $job_id, self::STATUS_CANCELLED );

		// Remove from queue if present
		$this->queue = array_filter( $this->queue, function( $queued_job ) use ( $job_id ) {
			return $queued_job->id !== $job_id;
		} );

		// Trigger cancellation action
		do_action( 'waisg_job_cancelled', $job_id );

		return true;
	}

	/**
	 * AJAX handler for pausing job.
	 *
	 * @since    1.0.0
	 */
	public function ajax_pause_job() {
		check_ajax_referer( 'waisg_ajax_nonce', 'nonce' );

		$job_id = intval( $_POST['job_id'] ?? 0 );

		if ( ! $job_id ) {
			wp_send_json_error( __( 'Invalid job ID', 'wp-ai-site-generator' ) );
		}

		$result = $this->pause_job( $job_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Job paused successfully', 'wp-ai-site-generator' ) );
	}

	/**
	 * AJAX handler for resuming job.
	 *
	 * @since    1.0.0
	 */
	public function ajax_resume_job() {
		check_ajax_referer( 'waisg_ajax_nonce', 'nonce' );

		$job_id = intval( $_POST['job_id'] ?? 0 );

		if ( ! $job_id ) {
			wp_send_json_error( __( 'Invalid job ID', 'wp-ai-site-generator' ) );
		}

		$result = $this->resume_job( $job_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Job resumed successfully', 'wp-ai-site-generator' ) );
	}

	/**
	 * AJAX handler for cancelling job.
	 *
	 * @since    1.0.0
	 */
	public function ajax_cancel_job() {
		check_ajax_referer( 'waisg_ajax_nonce', 'nonce' );

		$job_id = intval( $_POST['job_id'] ?? 0 );

		if ( ! $job_id ) {
			wp_send_json_error( __( 'Invalid job ID', 'wp-ai-site-generator' ) );
		}

		$result = $this->cancel_job( $job_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'Job cancelled successfully', 'wp-ai-site-generator' ) );
	}

	/**
	 * AJAX handler for getting job status.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_job_status() {
		check_ajax_referer( 'waisg_ajax_nonce', 'nonce' );

		$job_id = intval( $_GET['job_id'] ?? 0 );

		if ( ! $job_id ) {
			wp_send_json_error( __( 'Invalid job ID', 'wp-ai-site-generator' ) );
		}

		$status = $this->get_job_status( $job_id );

		if ( is_wp_error( $status ) ) {
			wp_send_json_error( $status->get_error_message() );
		}

		wp_send_json_success( $status );
	}
}