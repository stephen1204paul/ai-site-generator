<?php
/**
 * Generation Orchestrator Class.
 *
 * Main orchestrator class that coordinates the entire generation process.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Providers\Provider_Manager;
use WPAISiteGenerator\Generators\Pattern_Generator;
use WPAISiteGenerator\Generators\Page_Builder;
use WPAISiteGenerator\Generators\Theme_Adapter;
use WPAISiteGenerator\Includes\Quality_Scorer;
use WPAISiteGenerator\Includes\Output_Validator;
use WPAISiteGenerator\Includes\Prompts\Prompt_Templates;
use WPAISiteGenerator\Database\DB_Handler;
use WP_Error;

/**
 * Generation Orchestrator Class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Generation_Orchestrator {

	/**
	 * Provider manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Provider_Manager    $provider_manager    Provider manager instance.
	 */
	private $provider_manager;

	/**
	 * Pattern generator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Pattern_Generator    $pattern_generator    Pattern generator instance.
	 */
	private $pattern_generator;

	/**
	 * Page builder instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Page_Builder    $page_builder    Page builder instance.
	 */
	private $page_builder;

	/**
	 * Theme adapter instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Theme_Adapter    $theme_adapter    Theme adapter instance.
	 */
	private $theme_adapter;

	/**
	 * Quality scorer instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Quality_Scorer    $quality_scorer    Quality scorer instance.
	 */
	private $quality_scorer;

	/**
	 * Output validator instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Output_Validator    $validator    Output validator instance.
	 */
	private $validator;

	/**
	 * Prompt templates instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Prompt_Templates    $prompt_templates    Prompt templates instance.
	 */
	private $prompt_templates;

	/**
	 * Database handler instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      DB_Handler    $db_handler    Database handler instance.
	 */
	private $db_handler;

	/**
	 * Current generation job ID.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      int    $current_job_id    Current job ID.
	 */
	private $current_job_id;

	/**
	 * Generation status.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $status    Current generation status.
	 */
	private $status = array(
		'state'    => 'idle',
		'progress' => 0,
		'message'  => '',
		'errors'   => array(),
		'data'     => array(),
	);

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->provider_manager = new Provider_Manager();
		$this->pattern_generator = new Pattern_Generator();
		$this->page_builder = new Page_Builder();
		$this->theme_adapter = new Theme_Adapter();
		$this->quality_scorer = new Quality_Scorer();
		$this->validator = new Output_Validator();
		$this->prompt_templates = new Prompt_Templates();
		$this->db_handler = new DB_Handler();
	}

	/**
	 * Generate complete site.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Site generation configuration.
	 * @return   array|WP_Error       Generation result or error.
	 */
	public function generate_site( $config ) {
		try {
			// Initialize generation
			$this->update_status( 'initializing', 0, __( 'Starting site generation...', 'wp-ai-site-generator' ) );

			// Create generation job
			$job_id = $this->create_generation_job( array(
				'type'   => 'site',
				'config' => $config,
			) );

			if ( is_wp_error( $job_id ) ) {
				return $job_id;
			}

			$this->current_job_id = $job_id;

			// Build generation context
			$context = $this->build_generation_context( $config );
			if ( is_wp_error( $context ) ) {
				$this->fail_generation( $context->get_error_message() );
				return $context;
			}

			$this->update_status( 'generating', 10, __( 'Building site structure...', 'wp-ai-site-generator' ) );

			// Generate site structure
			$structure = $this->generate_site_structure( $context );
			if ( is_wp_error( $structure ) ) {
				$this->fail_generation( $structure->get_error_message() );
				return $structure;
			}

			// Generate pages
			$pages = array();
			$page_count = count( $structure['pages'] );
			$progress_per_page = 70 / max( $page_count, 1 );

			foreach ( $structure['pages'] as $index => $page_config ) {
				$page_progress = 20 + ( $index * $progress_per_page );
				$this->update_status(
					'generating',
					$page_progress,
					sprintf( __( 'Generating page %d of %d: %s', 'wp-ai-site-generator' ),
						$index + 1,
						$page_count,
						$page_config['title']
					)
				);

				// Generate single page
				$page_result = $this->generate_single_page( array_merge( $page_config, array(
					'site_context' => $context,
					'theme_config' => $config['theme_config'] ?? array(),
				) ) );

				if ( is_wp_error( $page_result ) ) {
					$this->add_error( sprintf(
						__( 'Failed to generate page "%s": %s', 'wp-ai-site-generator' ),
						$page_config['title'],
						$page_result->get_error_message()
					) );
					continue;
				}

				$pages[] = $page_result;
			}

			// Apply theme adaptations
			$this->update_status( 'finalizing', 90, __( 'Applying theme adaptations...', 'wp-ai-site-generator' ) );

			foreach ( $pages as $page ) {
				if ( isset( $page['post_id'] ) ) {
					$this->theme_adapter->adapt_page( $page['post_id'], $config['theme_config'] ?? array() );
				}
			}

			// Complete generation
			$this->update_status( 'completed', 100, __( 'Site generation complete!', 'wp-ai-site-generator' ) );

			$result = array(
				'success'   => true,
				'job_id'    => $job_id,
				'pages'     => $pages,
				'structure' => $structure,
				'errors'    => $this->status['errors'],
				'metrics'   => $this->gather_metrics( $pages ),
			);

			// Update job with result
			$this->complete_generation_job( $job_id, $result );

			return $result;

		} catch ( \Exception $e ) {
			$this->fail_generation( $e->getMessage() );
			return new WP_Error(
				'generation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Generate single page.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Page generation configuration.
	 * @return   array|WP_Error       Generation result or error.
	 */
	public function generate_single_page( $config ) {
		try {
			// Initialize if not part of larger generation
			if ( ! $this->current_job_id ) {
				$this->update_status( 'initializing', 0, __( 'Starting page generation...', 'wp-ai-site-generator' ) );

				$job_id = $this->create_generation_job( array(
					'type'   => 'page',
					'config' => $config,
				) );

				if ( is_wp_error( $job_id ) ) {
					return $job_id;
				}

				$this->current_job_id = $job_id;
			}

			// Build context
			$context = isset( $config['site_context'] )
				? $config['site_context']
				: $this->build_generation_context( $config );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			// Generate content with AI
			$this->update_status( 'generating', 20, __( 'Generating content with AI...', 'wp-ai-site-generator' ) );

			$ai_content = $this->generate_ai_content( array(
				'type'    => $config['type'] ?? 'page',
				'title'   => $config['title'] ?? '',
				'prompt'  => $config['prompt'] ?? '',
				'context' => $context,
			) );

			if ( is_wp_error( $ai_content ) ) {
				return $ai_content;
			}

			// Validate and score content
			$this->update_status( 'validating', 40, __( 'Validating generated content...', 'wp-ai-site-generator' ) );

			$validation = $this->validator->validate_output( $ai_content['content'], 'page' );
			$quality_score = $this->quality_scorer->score_content( $ai_content['content'] );

			// Check quality threshold
			$min_quality = get_option( 'waisg_quality_threshold', 0.7 );
			if ( $quality_score['overall_score'] < $min_quality ) {
				// Attempt regeneration once
				$this->update_status( 'regenerating', 45, __( 'Quality below threshold, regenerating...', 'wp-ai-site-generator' ) );

				$ai_content = $this->regenerate_with_improvements( $ai_content, $quality_score );
				if ( is_wp_error( $ai_content ) ) {
					return $ai_content;
				}

				$quality_score = $this->quality_scorer->score_content( $ai_content['content'] );
			}

			// Generate blocks
			$this->update_status( 'building', 60, __( 'Building page blocks...', 'wp-ai-site-generator' ) );

			$blocks = $this->pattern_generator->generate_from_content(
				$ai_content['content'],
				$config['block_preferences'] ?? array()
			);

			if ( is_wp_error( $blocks ) ) {
				return $blocks;
			}

			// Create WordPress page
			$this->update_status( 'creating', 80, __( 'Creating WordPress page...', 'wp-ai-site-generator' ) );

			$page_data = array(
				'title'   => $ai_content['title'] ?? $config['title'],
				'content' => $blocks,
				'meta'    => $ai_content['meta'] ?? array(),
				'status'  => $config['status'] ?? 'draft',
			);

			$post_id = $this->page_builder->create_page( $page_data );

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			// Apply theme adaptations
			if ( isset( $config['theme_config'] ) ) {
				$this->theme_adapter->adapt_page( $post_id, $config['theme_config'] );
			}

			// Complete
			$result = array(
				'success'       => true,
				'post_id'       => $post_id,
				'title'         => $page_data['title'],
				'content'       => $blocks,
				'quality_score' => $quality_score,
				'validation'    => $validation,
				'ai_metadata'   => array(
					'provider'     => $ai_content['provider'] ?? '',
					'model'        => $ai_content['model'] ?? '',
					'tokens_used'  => $ai_content['tokens_used'] ?? 0,
				),
			);

			// Update job if this was a standalone page generation
			if ( ! isset( $config['site_context'] ) ) {
				$this->complete_generation_job( $this->current_job_id, $result );
				$this->update_status( 'completed', 100, __( 'Page generation complete!', 'wp-ai-site-generator' ) );
			}

			return $result;

		} catch ( \Exception $e ) {
			return new WP_Error(
				'page_generation_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Regenerate section of a page.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Section regeneration configuration.
	 * @return   array|WP_Error       Generation result or error.
	 */
	public function regenerate_section( $config ) {
		try {
			$this->update_status( 'initializing', 0, __( 'Starting section regeneration...', 'wp-ai-site-generator' ) );

			// Validate inputs
			if ( empty( $config['post_id'] ) || empty( $config['section_id'] ) ) {
				return new WP_Error(
					'invalid_config',
					__( 'Post ID and section ID are required', 'wp-ai-site-generator' )
				);
			}

			// Get existing page content
			$post = get_post( $config['post_id'] );
			if ( ! $post ) {
				return new WP_Error(
					'post_not_found',
					__( 'Page not found', 'wp-ai-site-generator' )
				);
			}

			// Parse blocks to find section
			$blocks = parse_blocks( $post->post_content );
			$section_found = false;
			$section_content = '';

			foreach ( $blocks as $index => $block ) {
				if ( isset( $block['attrs']['id'] ) && $block['attrs']['id'] === $config['section_id'] ) {
					$section_found = true;
					$section_content = render_block( $block );
					break;
				}
			}

			if ( ! $section_found ) {
				return new WP_Error(
					'section_not_found',
					__( 'Section not found in page', 'wp-ai-site-generator' )
				);
			}

			// Build context
			$context = $this->build_generation_context( array_merge( $config, array(
				'page_title'   => $post->post_title,
				'page_content' => $post->post_content,
			) ) );

			// Generate new section content
			$this->update_status( 'generating', 30, __( 'Generating new section content...', 'wp-ai-site-generator' ) );

			$ai_content = $this->generate_ai_content( array(
				'type'    => 'section',
				'prompt'  => $config['prompt'] ?? sprintf(
					__( 'Regenerate this section with improvements: %s', 'wp-ai-site-generator' ),
					wp_trim_words( $section_content, 50 )
				),
				'context' => $context,
			) );

			if ( is_wp_error( $ai_content ) ) {
				return $ai_content;
			}

			// Validate new content
			$this->update_status( 'validating', 50, __( 'Validating new content...', 'wp-ai-site-generator' ) );

			$validation = $this->validator->validate_output( $ai_content['content'], 'section' );
			$quality_score = $this->quality_scorer->score_content( $ai_content['content'] );

			// Generate new blocks for section
			$this->update_status( 'building', 70, __( 'Building new section blocks...', 'wp-ai-site-generator' ) );

			$new_blocks = $this->pattern_generator->generate_from_content(
				$ai_content['content'],
				array( 'type' => 'section' )
			);

			if ( is_wp_error( $new_blocks ) ) {
				return $new_blocks;
			}

			// Replace section in page
			$blocks[$index] = parse_blocks( $new_blocks )[0];
			$new_content = '';
			foreach ( $blocks as $block ) {
				$new_content .= serialize_block( $block );
			}

			// Update page
			$this->update_status( 'updating', 90, __( 'Updating page...', 'wp-ai-site-generator' ) );

			$update_result = wp_update_post( array(
				'ID'           => $config['post_id'],
				'post_content' => $new_content,
			) );

			if ( is_wp_error( $update_result ) ) {
				return $update_result;
			}

			// Track regeneration
			$this->track_regeneration( array(
				'post_id'       => $config['post_id'],
				'section_id'    => $config['section_id'],
				'quality_score' => $quality_score,
			) );

			$this->update_status( 'completed', 100, __( 'Section regeneration complete!', 'wp-ai-site-generator' ) );

			return array(
				'success'       => true,
				'post_id'       => $config['post_id'],
				'section_id'    => $config['section_id'],
				'new_content'   => $new_blocks,
				'quality_score' => $quality_score,
				'validation'    => $validation,
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'section_regeneration_failed',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Generate AI content.
	 *
	 * @since    1.0.0
	 * @param    array    $params    Generation parameters.
	 * @return   array|WP_Error       Generated content or error.
	 */
	private function generate_ai_content( $params ) {
		// Get prompt template
		$prompt = $this->prompt_templates->get_prompt(
			$params['type'],
			array(
				'title'   => $params['title'] ?? '',
				'context' => $params['context'] ?? array(),
				'custom'  => $params['prompt'] ?? '',
			)
		);

		if ( empty( $prompt ) ) {
			return new WP_Error(
				'invalid_prompt',
				__( 'Failed to generate prompt', 'wp-ai-site-generator' )
			);
		}

		// Get provider
		$provider = $this->provider_manager->get_active_provider();
		if ( ! $provider ) {
			return new WP_Error(
				'no_provider',
				__( 'No AI provider configured', 'wp-ai-site-generator' )
			);
		}

		// Generate content
		$response = $provider->generate( $prompt, array(
			'temperature' => 0.7,
			'max_tokens'  => 4000,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse response
		$content = $this->parse_ai_response( $response );

		return array(
			'content'     => $content['content'] ?? $response,
			'title'       => $content['title'] ?? $params['title'],
			'meta'        => $content['meta'] ?? array(),
			'provider'    => $provider->get_name(),
			'model'       => $provider->get_model(),
			'tokens_used' => $response['usage']['total_tokens'] ?? 0,
		);
	}

	/**
	 * Parse AI response.
	 *
	 * @since    1.0.0
	 * @param    mixed    $response    AI response.
	 * @return   array                 Parsed content.
	 */
	private function parse_ai_response( $response ) {
		if ( is_string( $response ) ) {
			return array( 'content' => $response );
		}

		if ( is_array( $response ) && isset( $response['content'] ) ) {
			// Try to parse as JSON if it looks like JSON
			$content = $response['content'];
			if ( strpos( trim( $content ), '{' ) === 0 ) {
				$json = json_decode( $content, true );
				if ( $json && ! empty( $json ) ) {
					return $json;
				}
			}

			return array( 'content' => $content );
		}

		return array( 'content' => print_r( $response, true ) );
	}

	/**
	 * Regenerate content with quality improvements.
	 *
	 * @since    1.0.0
	 * @param    array    $original       Original content.
	 * @param    array    $quality_score  Quality scores.
	 * @return   array|WP_Error           Improved content or error.
	 */
	private function regenerate_with_improvements( $original, $quality_score ) {
		// Build improvement prompt
		$improvements = array();

		if ( $quality_score['readability'] < 0.7 ) {
			$improvements[] = __( 'Improve readability with shorter sentences and simpler language', 'wp-ai-site-generator' );
		}

		if ( $quality_score['structure'] < 0.7 ) {
			$improvements[] = __( 'Better organize content with clear headings and sections', 'wp-ai-site-generator' );
		}

		if ( $quality_score['seo_optimization'] < 0.7 ) {
			$improvements[] = __( 'Optimize for SEO with better keyword usage and meta descriptions', 'wp-ai-site-generator' );
		}

		$improvement_prompt = sprintf(
			__( "Improve this content with the following requirements:\n%s\n\nOriginal content:\n%s", 'wp-ai-site-generator' ),
			implode( "\n", $improvements ),
			$original['content']
		);

		return $this->generate_ai_content( array(
			'type'    => 'improvement',
			'prompt'  => $improvement_prompt,
			'context' => array( 'quality_scores' => $quality_score ),
		) );
	}

	/**
	 * Generate site structure.
	 *
	 * @since    1.0.0
	 * @param    array    $context    Generation context.
	 * @return   array|WP_Error        Site structure or error.
	 */
	private function generate_site_structure( $context ) {
		$prompt = $this->prompt_templates->get_prompt( 'site_structure', $context );

		$provider = $this->provider_manager->get_active_provider();
		if ( ! $provider ) {
			return new WP_Error(
				'no_provider',
				__( 'No AI provider configured', 'wp-ai-site-generator' )
			);
		}

		$response = $provider->generate( $prompt, array(
			'temperature' => 0.5,
			'max_tokens'  => 2000,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Parse structure
		$structure = $this->parse_ai_response( $response );

		// Ensure required pages
		$default_pages = array(
			array( 'title' => 'Home', 'type' => 'home' ),
			array( 'title' => 'About', 'type' => 'about' ),
			array( 'title' => 'Contact', 'type' => 'contact' ),
		);

		if ( empty( $structure['pages'] ) ) {
			$structure['pages'] = $default_pages;
		}

		return $structure;
	}

	/**
	 * Build generation context.
	 *
	 * @since    1.0.0
	 * @param    array    $config    Configuration.
	 * @return   array|WP_Error       Context array or error.
	 */
	private function build_generation_context( $config ) {
		$context_builder = new Generation_Context();
		return $context_builder->build( $config );
	}

	/**
	 * Create generation job.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Job data.
	 * @return   int|WP_Error       Job ID or error.
	 */
	private function create_generation_job( $data ) {
		return $this->db_handler->create_generation( array(
			'user_id'  => get_current_user_id(),
			'prompt'   => wp_json_encode( $data['config'] ),
			'provider' => $this->provider_manager->get_active_provider()->get_name(),
			'status'   => 'processing',
			'metadata' => wp_json_encode( array(
				'type'      => $data['type'],
				'timestamp' => current_time( 'mysql' ),
			) ),
		) );
	}

	/**
	 * Complete generation job.
	 *
	 * @since    1.0.0
	 * @param    int      $job_id    Job ID.
	 * @param    array    $result    Generation result.
	 * @return   bool
	 */
	private function complete_generation_job( $job_id, $result ) {
		return $this->db_handler->update_generation( $job_id, array(
			'status'      => 'completed',
			'progress'    => 100,
			'result'      => wp_json_encode( $result ),
			'completed_at'=> current_time( 'mysql' ),
		) );
	}

	/**
	 * Fail generation job.
	 *
	 * @since    1.0.0
	 * @param    string    $error_message    Error message.
	 * @return   bool
	 */
	private function fail_generation( $error_message ) {
		$this->update_status( 'failed', $this->status['progress'], $error_message );

		if ( $this->current_job_id ) {
			return $this->db_handler->update_generation( $this->current_job_id, array(
				'status'        => 'failed',
				'error_message' => $error_message,
				'completed_at'  => current_time( 'mysql' ),
			) );
		}

		return false;
	}

	/**
	 * Update generation status.
	 *
	 * @since    1.0.0
	 * @param    string    $state      Status state.
	 * @param    int       $progress   Progress percentage.
	 * @param    string    $message    Status message.
	 * @return   void
	 */
	private function update_status( $state, $progress, $message = '' ) {
		$this->status = array(
			'state'    => $state,
			'progress' => $progress,
			'message'  => $message,
			'errors'   => $this->status['errors'],
			'data'     => $this->status['data'],
		);

		// Update job progress if exists
		if ( $this->current_job_id ) {
			$this->db_handler->update_generation( $this->current_job_id, array(
				'progress' => $progress,
				'metadata' => wp_json_encode( array_merge(
					json_decode( $this->db_handler->get_generation( $this->current_job_id )->metadata, true ) ?? array(),
					array(
						'last_status' => $message,
						'updated_at'  => current_time( 'mysql' ),
					)
				) ),
			) );
		}

		// Trigger status update hook
		do_action( 'waisg_generation_status_update', $this->status, $this->current_job_id );
	}

	/**
	 * Add error to status.
	 *
	 * @since    1.0.0
	 * @param    string    $error    Error message.
	 * @return   void
	 */
	private function add_error( $error ) {
		$this->status['errors'][] = $error;
	}

	/**
	 * Gather generation metrics.
	 *
	 * @since    1.0.0
	 * @param    array    $pages    Generated pages.
	 * @return   array              Metrics data.
	 */
	private function gather_metrics( $pages ) {
		$metrics = array(
			'total_pages'         => count( $pages ),
			'successful_pages'    => 0,
			'failed_pages'        => 0,
			'average_quality'     => 0,
			'total_tokens'        => 0,
			'generation_time'     => 0,
		);

		$quality_scores = array();

		foreach ( $pages as $page ) {
			if ( isset( $page['success'] ) && $page['success'] ) {
				$metrics['successful_pages']++;

				if ( isset( $page['quality_score']['overall_score'] ) ) {
					$quality_scores[] = $page['quality_score']['overall_score'];
				}

				if ( isset( $page['ai_metadata']['tokens_used'] ) ) {
					$metrics['total_tokens'] += $page['ai_metadata']['tokens_used'];
				}
			} else {
				$metrics['failed_pages']++;
			}
		}

		if ( ! empty( $quality_scores ) ) {
			$metrics['average_quality'] = array_sum( $quality_scores ) / count( $quality_scores );
		}

		return $metrics;
	}

	/**
	 * Track section regeneration.
	 *
	 * @since    1.0.0
	 * @param    array    $data    Regeneration data.
	 * @return   void
	 */
	private function track_regeneration( $data ) {
		// Store regeneration history
		update_post_meta(
			$data['post_id'],
			'_waisg_regenerations',
			array(
				'section_id'    => $data['section_id'],
				'quality_score' => $data['quality_score'],
				'timestamp'     => current_time( 'mysql' ),
			)
		);

		// Track metrics
		do_action( 'waisg_track_regeneration', $data );
	}

	/**
	 * Get current status.
	 *
	 * @since    1.0.0
	 * @return   array    Current status.
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Get job status.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   array|WP_Error    Job status or error.
	 */
	public function get_job_status( $job_id ) {
		$job = $this->db_handler->get_generation( $job_id );

		if ( ! $job ) {
			return new WP_Error(
				'job_not_found',
				__( 'Generation job not found', 'wp-ai-site-generator' )
			);
		}

		return array(
			'id'       => $job->id,
			'status'   => $job->status,
			'progress' => $job->progress,
			'result'   => json_decode( $job->result, true ),
			'error'    => $job->error_message,
			'metadata' => json_decode( $job->metadata, true ),
		);
	}

	/**
	 * Cancel generation job.
	 *
	 * @since    1.0.0
	 * @param    int    $job_id    Job ID.
	 * @return   bool|WP_Error      Success or error.
	 */
	public function cancel_job( $job_id ) {
		$job = $this->db_handler->get_generation( $job_id );

		if ( ! $job ) {
			return new WP_Error(
				'job_not_found',
				__( 'Generation job not found', 'wp-ai-site-generator' )
			);
		}

		if ( $job->status === 'completed' || $job->status === 'failed' ) {
			return new WP_Error(
				'job_finished',
				__( 'Cannot cancel finished job', 'wp-ai-site-generator' )
			);
		}

		return $this->db_handler->update_generation( $job_id, array(
			'status'        => 'cancelled',
			'error_message' => __( 'Job cancelled by user', 'wp-ai-site-generator' ),
			'completed_at'  => current_time( 'mysql' ),
		) );
	}
}