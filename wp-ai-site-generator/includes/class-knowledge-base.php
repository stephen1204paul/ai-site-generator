<?php
/**
 * Knowledge Base Manager
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

namespace AI_Site_Generator\Includes;

use AI_Site_Generator\Database\KB_DB_Handler;
use AI_Site_Generator\Includes\Document_Processor;
use AI_Site_Generator\Includes\Vector_Embeddings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Knowledge Base Manager Class
 */
class Knowledge_Base {
	/**
	 * Instance
	 *
	 * @var Knowledge_Base
	 */
	private static $instance = null;

	/**
	 * Database handler
	 *
	 * @var KB_DB_Handler
	 */
	private $db_handler;

	/**
	 * Document processor
	 *
	 * @var Document_Processor
	 */
	private $doc_processor;

	/**
	 * Vector embeddings
	 *
	 * @var Vector_Embeddings
	 */
	private $embeddings;

	/**
	 * KB Categories
	 *
	 * @var array
	 */
	private $categories = array(
		'brand'       => 'Brand Guidelines',
		'products'    => 'Product Information',
		'services'    => 'Service Descriptions',
		'company'     => 'Company Information',
		'tone'        => 'Tone & Style',
		'audience'    => 'Target Audience',
		'seo'         => 'SEO Guidelines',
		'design'      => 'Design Preferences',
		'competitors' => 'Competitor Information',
		'legal'       => 'Legal & Compliance',
	);

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->db_handler    = KB_DB_Handler::get_instance();
		$this->doc_processor = Document_Processor::get_instance();
		$this->embeddings    = Vector_Embeddings::get_instance();

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Get instance
	 *
	 * @return Knowledge_Base
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize
	 */
	public function init() {
		// Register upload directory
		add_filter( 'upload_mimes', array( $this, 'add_upload_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_filetype' ), 10, 4 );

		// Schedule cleanup tasks
		if ( ! wp_next_scheduled( 'ai_site_generator_kb_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'ai_site_generator_kb_cleanup' );
		}
		add_action( 'ai_site_generator_kb_cleanup', array( $this, 'cleanup_old_embeddings' ) );
	}

	/**
	 * Add allowed mime types
	 *
	 * @param array $mimes Mime types.
	 * @return array
	 */
	public function add_upload_mimes( $mimes ) {
		$mimes['md']   = 'text/markdown';
		$mimes['docx'] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
		return $mimes;
	}

	/**
	 * Check file type
	 *
	 * @param array  $data     File data.
	 * @param string $file     File path.
	 * @param string $filename File name.
	 * @param array  $mimes    Mime types.
	 * @return array
	 */
	public function check_filetype( $data, $file, $filename, $mimes ) {
		$filetype = wp_check_filetype( $filename, $mimes );
		return array(
			'ext'             => $filetype['ext'],
			'type'            => $filetype['type'],
			'proper_filename' => $data['proper_filename'],
		);
	}

	/**
	 * Upload document
	 *
	 * @param array $file     File data from $_FILES.
	 * @param array $metadata Document metadata.
	 * @return int|WP_Error Document ID or error
	 */
	public function upload_document( $file, $metadata = array() ) {
		try {
			// Validate file
			$validation = $this->validate_upload( $file );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			// Handle file upload
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$upload = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'test_type' => false,
				)
			);

			if ( isset( $upload['error'] ) ) {
				return new WP_Error( 'upload_failed', $upload['error'] );
			}

			// Extract text from document
			$extracted = $this->doc_processor->process_document( $upload['file'], $upload['type'] );
			if ( is_wp_error( $extracted ) ) {
				// Clean up uploaded file
				wp_delete_file( $upload['file'] );
				return $extracted;
			}

			// Prepare document data
			$document_data = array(
				'title'       => ! empty( $metadata['title'] ) ? $metadata['title'] : basename( $file['name'], '.' . pathinfo( $file['name'], PATHINFO_EXTENSION ) ),
				'category'    => ! empty( $metadata['category'] ) && isset( $this->categories[ $metadata['category'] ] ) ? $metadata['category'] : 'company',
				'file_path'   => $upload['file'],
				'file_url'    => $upload['url'],
				'file_type'   => $upload['type'],
				'file_size'   => filesize( $upload['file'] ),
				'content'     => $extracted['content'],
				'chunks'      => $extracted['chunks'],
				'metadata'    => wp_parse_args(
					$metadata,
					array(
						'author'      => wp_get_current_user()->display_name,
						'uploaded_at' => current_time( 'mysql' ),
						'keywords'    => $extracted['keywords'] ?? array(),
						'summary'     => $extracted['summary'] ?? '',
					)
				),
				'version'     => 1,
				'status'      => 'processing',
			);

			// Store in database
			$document_id = $this->db_handler->create_document( $document_data );
			if ( is_wp_error( $document_id ) ) {
				// Clean up uploaded file
				wp_delete_file( $upload['file'] );
				return $document_id;
			}

			// Process embeddings asynchronously
			wp_schedule_single_event(
				time(),
				'ai_site_generator_process_kb_embeddings',
				array( $document_id )
			);

			return $document_id;

		} catch ( \Exception $e ) {
			return new WP_Error( 'kb_upload_error', $e->getMessage() );
		}
	}

	/**
	 * Validate upload
	 *
	 * @param array $file File data.
	 * @return true|WP_Error
	 */
	private function validate_upload( $file ) {
		// Check for upload errors
		if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', __( 'File upload failed', 'ai-site-generator' ) );
		}

		// Check file size (max 10MB)
		$max_size = 10 * 1024 * 1024; // 10MB
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'file_too_large', __( 'File size exceeds 10MB limit', 'ai-site-generator' ) );
		}

		// Check file type
		$allowed_types = array(
			'application/pdf',
			'text/plain',
			'text/markdown',
			'text/html',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);

		$file_info = wp_check_filetype( $file['name'] );
		if ( ! in_array( $file['type'], $allowed_types, true ) && ! in_array( $file_info['type'], $allowed_types, true ) ) {
			return new WP_Error( 'invalid_file_type', __( 'Invalid file type. Allowed types: PDF, TXT, MD, HTML, DOCX', 'ai-site-generator' ) );
		}

		// Security check
		if ( ! wp_verify_nonce( $_REQUEST['kb_upload_nonce'] ?? '', 'kb_upload' ) ) {
			return new WP_Error( 'security_check_failed', __( 'Security check failed', 'ai-site-generator' ) );
		}

		return true;
	}

	/**
	 * Get document
	 *
	 * @param int $document_id Document ID.
	 * @return array|WP_Error
	 */
	public function get_document( $document_id ) {
		return $this->db_handler->get_document( $document_id );
	}

	/**
	 * Update document
	 *
	 * @param int   $document_id Document ID.
	 * @param array $data        Update data.
	 * @return bool|WP_Error
	 */
	public function update_document( $document_id, $data ) {
		$document = $this->get_document( $document_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		// Update version if content changed
		if ( isset( $data['content'] ) && $data['content'] !== $document['content'] ) {
			$data['version'] = $document['version'] + 1;

			// Reprocess chunks and embeddings
			$chunks = $this->doc_processor->chunk_text( $data['content'] );
			$data['chunks'] = $chunks;

			// Mark for reprocessing
			$data['status'] = 'reprocessing';

			// Schedule embedding update
			wp_schedule_single_event(
				time(),
				'ai_site_generator_process_kb_embeddings',
				array( $document_id )
			);
		}

		return $this->db_handler->update_document( $document_id, $data );
	}

	/**
	 * Delete document
	 *
	 * @param int $document_id Document ID.
	 * @return bool|WP_Error
	 */
	public function delete_document( $document_id ) {
		$document = $this->get_document( $document_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		// Delete file
		if ( ! empty( $document['file_path'] ) && file_exists( $document['file_path'] ) ) {
			wp_delete_file( $document['file_path'] );
		}

		// Delete embeddings
		$this->embeddings->delete_document_embeddings( $document_id );

		// Delete from database
		return $this->db_handler->delete_document( $document_id );
	}

	/**
	 * Search documents
	 *
	 * @param string $query      Search query.
	 * @param array  $filters    Filters.
	 * @param int    $limit      Result limit.
	 * @return array
	 */
	public function search_documents( $query, $filters = array(), $limit = 10 ) {
		// Use vector search for semantic matching
		if ( ! empty( $query ) ) {
			$results = $this->embeddings->semantic_search( $query, $filters, $limit );
			if ( ! empty( $results ) ) {
				return $results;
			}
		}

		// Fall back to database search
		return $this->db_handler->search_documents( $query, $filters, $limit );
	}

	/**
	 * Get documents by category
	 *
	 * @param string $category Category.
	 * @param int    $limit    Limit.
	 * @return array
	 */
	public function get_documents_by_category( $category, $limit = 50 ) {
		return $this->db_handler->get_documents_by_category( $category, $limit );
	}

	/**
	 * List all documents
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function list_documents( $args = array() ) {
		$defaults = array(
			'status'  => 'active',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 50,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );
		return $this->db_handler->list_documents( $args );
	}

	/**
	 * Get categories
	 *
	 * @return array
	 */
	public function get_categories() {
		return $this->categories;
	}

	/**
	 * Get category statistics
	 *
	 * @return array
	 */
	public function get_category_stats() {
		$stats = array();
		foreach ( $this->categories as $key => $label ) {
			$stats[ $key ] = array(
				'label' => $label,
				'count' => $this->db_handler->count_documents_by_category( $key ),
			);
		}
		return $stats;
	}

	/**
	 * Reprocess document
	 *
	 * @param int $document_id Document ID.
	 * @return bool|WP_Error
	 */
	public function reprocess_document( $document_id ) {
		$document = $this->get_document( $document_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		// Re-extract text if file exists
		if ( ! empty( $document['file_path'] ) && file_exists( $document['file_path'] ) ) {
			$extracted = $this->doc_processor->process_document( $document['file_path'], $document['file_type'] );
			if ( ! is_wp_error( $extracted ) ) {
				$this->update_document(
					$document_id,
					array(
						'content' => $extracted['content'],
						'chunks'  => $extracted['chunks'],
						'status'  => 'reprocessing',
					)
				);
			}
		}

		// Regenerate embeddings
		wp_schedule_single_event(
			time(),
			'ai_site_generator_process_kb_embeddings',
			array( $document_id )
		);

		return true;
	}

	/**
	 * Get document usage statistics
	 *
	 * @param int $document_id Document ID.
	 * @return array
	 */
	public function get_document_usage_stats( $document_id ) {
		return $this->db_handler->get_document_usage_stats( $document_id );
	}

	/**
	 * Record document usage
	 *
	 * @param int    $document_id Document ID.
	 * @param string $context     Usage context.
	 * @param array  $metadata    Additional metadata.
	 * @return bool
	 */
	public function record_usage( $document_id, $context = 'generation', $metadata = array() ) {
		return $this->db_handler->record_document_usage(
			$document_id,
			array(
				'context'  => $context,
				'user_id'  => get_current_user_id(),
				'metadata' => $metadata,
			)
		);
	}

	/**
	 * Get most used documents
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_most_used_documents( $limit = 10 ) {
		return $this->db_handler->get_most_used_documents( $limit );
	}

	/**
	 * Bulk upload documents
	 *
	 * @param array $files    Files array.
	 * @param array $metadata Common metadata.
	 * @return array Results
	 */
	public function bulk_upload( $files, $metadata = array() ) {
		$results = array(
			'success' => array(),
			'errors'  => array(),
		);

		foreach ( $files as $file ) {
			$result = $this->upload_document( $file, $metadata );
			if ( is_wp_error( $result ) ) {
				$results['errors'][] = array(
					'file'  => $file['name'],
					'error' => $result->get_error_message(),
				);
			} else {
				$results['success'][] = array(
					'file' => $file['name'],
					'id'   => $result,
				);
			}
		}

		return $results;
	}

	/**
	 * Export knowledge base
	 *
	 * @param array $document_ids Optional document IDs to export.
	 * @return array|WP_Error
	 */
	public function export_knowledge_base( $document_ids = array() ) {
		try {
			$export_data = array(
				'version'   => '1.0',
				'timestamp' => current_time( 'mysql' ),
				'documents' => array(),
			);

			if ( empty( $document_ids ) ) {
				$documents = $this->list_documents( array( 'limit' => 1000 ) );
			} else {
				$documents = array();
				foreach ( $document_ids as $id ) {
					$doc = $this->get_document( $id );
					if ( ! is_wp_error( $doc ) ) {
						$documents[] = $doc;
					}
				}
			}

			foreach ( $documents as $doc ) {
				// Include document content but exclude file paths
				$export_data['documents'][] = array(
					'title'    => $doc['title'],
					'category' => $doc['category'],
					'content'  => $doc['content'],
					'metadata' => $doc['metadata'],
				);
			}

			return $export_data;

		} catch ( \Exception $e ) {
			return new WP_Error( 'export_failed', $e->getMessage() );
		}
	}

	/**
	 * Import knowledge base
	 *
	 * @param array $import_data Import data.
	 * @return array Results
	 */
	public function import_knowledge_base( $import_data ) {
		$results = array(
			'success' => 0,
			'errors'  => 0,
		);

		if ( ! isset( $import_data['documents'] ) || ! is_array( $import_data['documents'] ) ) {
			return new WP_Error( 'invalid_import_data', __( 'Invalid import data format', 'ai-site-generator' ) );
		}

		foreach ( $import_data['documents'] as $doc ) {
			$document_data = array(
				'title'    => $doc['title'] ?? 'Imported Document',
				'category' => $doc['category'] ?? 'company',
				'content'  => $doc['content'] ?? '',
				'metadata' => $doc['metadata'] ?? array(),
				'status'   => 'processing',
			);

			// Process chunks
			if ( ! empty( $document_data['content'] ) ) {
				$chunks = $this->doc_processor->chunk_text( $document_data['content'] );
				$document_data['chunks'] = $chunks;
			}

			$document_id = $this->db_handler->create_document( $document_data );
			if ( ! is_wp_error( $document_id ) ) {
				$results['success']++;

				// Schedule embedding processing
				wp_schedule_single_event(
					time() + $results['success'] * 2, // Stagger processing
					'ai_site_generator_process_kb_embeddings',
					array( $document_id )
				);
			} else {
				$results['errors']++;
			}
		}

		return $results;
	}

	/**
	 * Cleanup old embeddings
	 */
	public function cleanup_old_embeddings() {
		// Remove embeddings for deleted documents
		$this->embeddings->cleanup_orphaned_embeddings();

		// Clear old cache entries
		$this->embeddings->clear_old_cache();
	}
}