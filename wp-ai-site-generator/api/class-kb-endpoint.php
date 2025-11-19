<?php
/**
 * Knowledge Base REST API Endpoint
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

namespace AI_Site_Generator\API;

use AI_Site_Generator\API\REST_Controller;
use AI_Site_Generator\Includes\Knowledge_Base;
use AI_Site_Generator\Includes\RAG_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KB Endpoint Class
 */
class KB_Endpoint extends REST_Controller {
	/**
	 * Knowledge Base instance
	 *
	 * @var Knowledge_Base
	 */
	private $knowledge_base;

	/**
	 * RAG Integration instance
	 *
	 * @var RAG_Integration
	 */
	private $rag;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->namespace      = 'ai-site-generator/v1';
		$this->rest_base      = 'kb';
		$this->knowledge_base = Knowledge_Base::get_instance();
		$this->rag            = RAG_Integration::get_instance();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {
		// Upload document
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upload',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload_document' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'title'    => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Document title',
						),
						'category' => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Document category',
							'enum'        => array_keys( $this->knowledge_base->get_categories() ),
						),
						'metadata' => array(
							'type'        => 'object',
							'required'    => false,
							'description' => 'Additional metadata',
						),
					),
				),
			)
		);

		// List documents
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/list',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_documents' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Filter by category',
						),
						'status'   => array(
							'type'        => 'string',
							'required'    => false,
							'default'     => 'active',
							'description' => 'Filter by status',
						),
						'limit'    => array(
							'type'        => 'integer',
							'required'    => false,
							'default'     => 50,
							'description' => 'Number of results',
						),
						'offset'   => array(
							'type'        => 'integer',
							'required'    => false,
							'default'     => 0,
							'description' => 'Results offset',
						),
					),
				),
			)
		);

		// Get specific document
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_document' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'        => 'integer',
							'required'    => true,
							'description' => 'Document ID',
						),
					),
				),
			)
		);

		// Update document
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_document' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'       => array(
							'type'        => 'integer',
							'required'    => true,
							'description' => 'Document ID',
						),
						'title'    => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Document title',
						),
						'category' => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Document category',
						),
						'content'  => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Document content',
						),
						'metadata' => array(
							'type'        => 'object',
							'required'    => false,
							'description' => 'Document metadata',
						),
					),
				),
			)
		);

		// Delete document
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_document' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'        => 'integer',
							'required'    => true,
							'description' => 'Document ID',
						),
					),
				),
			)
		);

		// Search knowledge base
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/search',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'search_kb' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'query'      => array(
							'type'        => 'string',
							'required'    => true,
							'description' => 'Search query',
						),
						'categories' => array(
							'type'        => 'array',
							'required'    => false,
							'description' => 'Filter by categories',
						),
						'limit'      => array(
							'type'        => 'integer',
							'required'    => false,
							'default'     => 10,
							'description' => 'Number of results',
						),
						'type'       => array(
							'type'        => 'string',
							'required'    => false,
							'default'     => 'semantic',
							'enum'        => array( 'semantic', 'keyword', 'hybrid' ),
							'description' => 'Search type',
						),
					),
				),
			)
		);

		// Get categories
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/categories',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_categories' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Reprocess document
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/reprocess',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'reprocess_document' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'type'        => 'integer',
							'required'    => true,
							'description' => 'Document ID',
						),
					),
				),
			)
		);

		// Bulk upload
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk-upload',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'bulk_upload' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'category' => array(
							'type'        => 'string',
							'required'    => false,
							'description' => 'Common category for all documents',
						),
						'metadata' => array(
							'type'        => 'object',
							'required'    => false,
							'description' => 'Common metadata for all documents',
						),
					),
				),
			)
		);

		// Get usage statistics
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Test RAG system
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test-rag',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'test_rag' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'query' => array(
							'type'        => 'string',
							'required'    => true,
							'description' => 'Test query',
						),
					),
				),
			)
		);

		// Export knowledge base
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'export_kb' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'document_ids' => array(
							'type'        => 'array',
							'required'    => false,
							'description' => 'Specific document IDs to export',
						),
					),
				),
			)
		);

		// Import knowledge base
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'import_kb' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Upload document
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_document( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded', 'ai-site-generator' ), array( 'status' => 400 ) );
		}

		// Create nonce for security
		$_REQUEST['kb_upload_nonce'] = wp_create_nonce( 'kb_upload' );

		$metadata = array(
			'title'    => $request->get_param( 'title' ),
			'category' => $request->get_param( 'category' ),
		);

		// Add additional metadata if provided
		$extra_metadata = $request->get_param( 'metadata' );
		if ( $extra_metadata ) {
			$metadata = array_merge( $metadata, $extra_metadata );
		}

		$result = $this->knowledge_base->upload_document( $files['file'], $metadata );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$document = $this->knowledge_base->get_document( $result );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $document,
			)
		);
	}

	/**
	 * List documents
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function list_documents( $request ) {
		$args = array(
			'category' => $request->get_param( 'category' ),
			'status'   => $request->get_param( 'status' ),
			'limit'    => $request->get_param( 'limit' ),
			'offset'   => $request->get_param( 'offset' ),
		);

		$documents = $this->knowledge_base->list_documents( array_filter( $args ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $documents,
			)
		);
	}

	/**
	 * Get document
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_document( $request ) {
		$document_id = $request->get_param( 'id' );
		$document    = $this->knowledge_base->get_document( $document_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		// Get usage stats
		$document['usage_stats'] = $this->knowledge_base->get_document_usage_stats( $document_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $document,
			)
		);
	}

	/**
	 * Update document
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_document( $request ) {
		$document_id = $request->get_param( 'id' );

		$update_data = array_filter(
			array(
				'title'    => $request->get_param( 'title' ),
				'category' => $request->get_param( 'category' ),
				'content'  => $request->get_param( 'content' ),
				'metadata' => $request->get_param( 'metadata' ),
			)
		);

		if ( empty( $update_data ) ) {
			return new WP_Error( 'no_update_data', __( 'No update data provided', 'ai-site-generator' ), array( 'status' => 400 ) );
		}

		$result = $this->knowledge_base->update_document( $document_id, $update_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$document = $this->knowledge_base->get_document( $document_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $document,
			)
		);
	}

	/**
	 * Delete document
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_document( $request ) {
		$document_id = $request->get_param( 'id' );
		$result      = $this->knowledge_base->delete_document( $document_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Document deleted successfully', 'ai-site-generator' ),
			)
		);
	}

	/**
	 * Search knowledge base
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search_kb( $request ) {
		$query      = $request->get_param( 'query' );
		$categories = $request->get_param( 'categories' );
		$limit      = $request->get_param( 'limit' );
		$type       = $request->get_param( 'type' );

		$filters = array();
		if ( $categories ) {
			$filters['categories'] = $categories;
		}

		$results = array();

		switch ( $type ) {
			case 'semantic':
				$context = $this->rag->retrieve_context( $query, $filters, array( 'top_k' => $limit ) );
				$results = $context['citations'] ?? array();
				break;

			case 'hybrid':
				$context = $this->rag->get_hybrid_context( $query, array(), array( 'top_k' => $limit ) );
				$results = $context['citations'] ?? array();
				break;

			case 'keyword':
			default:
				$results = $this->knowledge_base->search_documents( $query, $filters, $limit );
				break;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'query'   => $query,
					'type'    => $type,
					'results' => $results,
				),
			)
		);
	}

	/**
	 * Get categories
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_categories( $request ) {
		$categories = $this->knowledge_base->get_categories();
		$stats      = $this->knowledge_base->get_category_stats();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'categories' => $categories,
					'stats'      => $stats,
				),
			)
		);
	}

	/**
	 * Reprocess document
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reprocess_document( $request ) {
		$document_id = $request->get_param( 'id' );
		$result      = $this->knowledge_base->reprocess_document( $document_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Document reprocessing started', 'ai-site-generator' ),
			)
		);
	}

	/**
	 * Bulk upload
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function bulk_upload( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files ) ) {
			return new WP_Error( 'no_files', __( 'No files uploaded', 'ai-site-generator' ), array( 'status' => 400 ) );
		}

		// Create nonce for security
		$_REQUEST['kb_upload_nonce'] = wp_create_nonce( 'kb_upload' );

		$metadata = array_filter(
			array(
				'category' => $request->get_param( 'category' ),
				'metadata' => $request->get_param( 'metadata' ),
			)
		);

		$results = $this->knowledge_base->bulk_upload( $files, $metadata );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $results,
			)
		);
	}

	/**
	 * Get statistics
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$stats = array(
			'total_documents' => count( $this->knowledge_base->list_documents() ),
			'categories'      => $this->knowledge_base->get_category_stats(),
			'most_used'       => $this->knowledge_base->get_most_used_documents( 5 ),
			'rag_stats'       => $this->rag->get_statistics(),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $stats,
			)
		);
	}

	/**
	 * Test RAG system
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function test_rag( $request ) {
		$query   = $request->get_param( 'query' );
		$results = $this->rag->test_rag_system( $query );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $results,
			)
		);
	}

	/**
	 * Export knowledge base
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_kb( $request ) {
		$document_ids = $request->get_param( 'document_ids' );
		$export_data  = $this->knowledge_base->export_knowledge_base( $document_ids );

		if ( is_wp_error( $export_data ) ) {
			return $export_data;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $export_data,
			)
		);
	}

	/**
	 * Import knowledge base
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_kb( $request ) {
		$import_data = $request->get_json_params();

		if ( empty( $import_data ) ) {
			return new WP_Error( 'no_import_data', __( 'No import data provided', 'ai-site-generator' ), array( 'status' => 400 ) );
		}

		$results = $this->knowledge_base->import_knowledge_base( $import_data );

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $results,
			)
		);
	}

	/**
	 * Check permission
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function check_permission( $request ) {
		return current_user_can( 'manage_options' );
	}
}