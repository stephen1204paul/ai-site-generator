<?php
/**
 * Knowledge Base Database Handler
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

namespace AI_Site_Generator\Database;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KB Database Handler Class
 */
class KB_DB_Handler {
	/**
	 * Instance
	 *
	 * @var KB_DB_Handler
	 */
	private static $instance = null;

	/**
	 * Documents table name
	 *
	 * @var string
	 */
	private $documents_table;

	/**
	 * Chunks table name
	 *
	 * @var string
	 */
	private $chunks_table;

	/**
	 * Embeddings table name
	 *
	 * @var string
	 */
	private $embeddings_table;

	/**
	 * Usage table name
	 *
	 * @var string
	 */
	private $usage_table;

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;

		$this->documents_table  = $wpdb->prefix . 'ai_site_generator_kb_documents';
		$this->chunks_table     = $wpdb->prefix . 'ai_site_generator_kb_chunks';
		$this->embeddings_table = $wpdb->prefix . 'ai_site_generator_kb_embeddings';
		$this->usage_table      = $wpdb->prefix . 'ai_site_generator_kb_usage';
	}

	/**
	 * Get instance
	 *
	 * @return KB_DB_Handler
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Create tables
	 */
	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Documents table
		$sql_documents = "CREATE TABLE IF NOT EXISTS {$this->documents_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			category varchar(50) NOT NULL,
			file_path text,
			file_url text,
			file_type varchar(100),
			file_size bigint(20) unsigned,
			content longtext,
			metadata longtext,
			version int(11) DEFAULT 1,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			user_id bigint(20) unsigned,
			PRIMARY KEY (id),
			KEY category (category),
			KEY status (status),
			KEY created_at (created_at),
			FULLTEXT KEY content_search (title, content)
		) $charset_collate;";

		// Chunks table
		$sql_chunks = "CREATE TABLE IF NOT EXISTS {$this->chunks_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			chunk_index int(11) NOT NULL,
			content longtext NOT NULL,
			chunk_hash varchar(32),
			chunk_size int(11),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY document_id (document_id),
			KEY chunk_index (chunk_index),
			KEY chunk_hash (chunk_hash),
			FULLTEXT KEY chunk_search (content)
		) $charset_collate;";

		// Embeddings table
		$sql_embeddings = "CREATE TABLE IF NOT EXISTS {$this->embeddings_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			chunk_id bigint(20) unsigned,
			chunk_index int(11),
			embedding longtext NOT NULL,
			model varchar(100),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY document_id (document_id),
			KEY chunk_id (chunk_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Usage tracking table
		$sql_usage = "CREATE TABLE IF NOT EXISTS {$this->usage_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			context varchar(50),
			user_id bigint(20) unsigned,
			metadata longtext,
			used_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY document_id (document_id),
			KEY context (context),
			KEY user_id (user_id),
			KEY used_at (used_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_documents );
		dbDelta( $sql_chunks );
		dbDelta( $sql_embeddings );
		dbDelta( $sql_usage );

		// Store version
		update_option( 'ai_site_generator_kb_db_version', '1.0.0' );
	}

	/**
	 * Drop tables
	 */
	public function drop_tables() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$this->usage_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$this->embeddings_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$this->chunks_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$this->documents_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		delete_option( 'ai_site_generator_kb_db_version' );
	}

	/**
	 * Create document
	 *
	 * @param array $data Document data.
	 * @return int|WP_Error
	 */
	public function create_document( $data ) {
		global $wpdb;

		// Prepare data
		$insert_data = array(
			'title'     => $data['title'] ?? '',
			'category'  => $data['category'] ?? 'company',
			'file_path' => $data['file_path'] ?? null,
			'file_url'  => $data['file_url'] ?? null,
			'file_type' => $data['file_type'] ?? null,
			'file_size' => $data['file_size'] ?? null,
			'content'   => $data['content'] ?? '',
			'metadata'  => wp_json_encode( $data['metadata'] ?? array() ),
			'version'   => $data['version'] ?? 1,
			'status'    => $data['status'] ?? 'active',
			'user_id'   => get_current_user_id(),
		);

		$result = $wpdb->insert( $this->documents_table, $insert_data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return new WP_Error( 'db_insert_error', $wpdb->last_error );
		}

		$document_id = $wpdb->insert_id;

		// Store chunks if provided
		if ( ! empty( $data['chunks'] ) ) {
			$this->store_chunks( $document_id, $data['chunks'] );
		}

		return $document_id;
	}

	/**
	 * Get document
	 *
	 * @param int $document_id Document ID.
	 * @return array|WP_Error
	 */
	public function get_document( $document_id ) {
		global $wpdb;

		$document = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->documents_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$document_id
			),
			ARRAY_A
		);

		if ( null === $document ) {
			return new WP_Error( 'document_not_found', __( 'Document not found', 'ai-site-generator' ) );
		}

		// Decode metadata
		$document['metadata'] = json_decode( $document['metadata'], true ) ?? array();

		// Get chunks
		$document['chunks'] = $this->get_document_chunks( $document_id );

		return $document;
	}

	/**
	 * Update document
	 *
	 * @param int   $document_id Document ID.
	 * @param array $data        Update data.
	 * @return bool|WP_Error
	 */
	public function update_document( $document_id, $data ) {
		global $wpdb;

		// Prepare update data
		$update_data = array();
		$allowed_fields = array( 'title', 'category', 'content', 'metadata', 'version', 'status' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				if ( 'metadata' === $field ) {
					$update_data[ $field ] = wp_json_encode( $data[ $field ] );
				} else {
					$update_data[ $field ] = $data[ $field ];
				}
			}
		}

		if ( empty( $update_data ) ) {
			return true;
		}

		$result = $wpdb->update(
			$this->documents_table,
			$update_data,
			array( 'id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return new WP_Error( 'db_update_error', $wpdb->last_error );
		}

		// Update chunks if provided
		if ( isset( $data['chunks'] ) ) {
			// Delete old chunks
			$this->delete_document_chunks( $document_id );
			// Store new chunks
			$this->store_chunks( $document_id, $data['chunks'] );
		}

		return true;
	}

	/**
	 * Delete document
	 *
	 * @param int $document_id Document ID.
	 * @return bool|WP_Error
	 */
	public function delete_document( $document_id ) {
		global $wpdb;

		// Delete usage records
		$wpdb->delete(
			$this->usage_table,
			array( 'document_id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Delete embeddings
		$wpdb->delete(
			$this->embeddings_table,
			array( 'document_id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Delete chunks
		$wpdb->delete(
			$this->chunks_table,
			array( 'document_id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Delete document
		$result = $wpdb->delete(
			$this->documents_table,
			array( 'id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return new WP_Error( 'db_delete_error', $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Store chunks
	 *
	 * @param int   $document_id Document ID.
	 * @param array $chunks      Chunks data.
	 * @return bool
	 */
	private function store_chunks( $document_id, $chunks ) {
		global $wpdb;

		foreach ( $chunks as $chunk ) {
			$wpdb->insert(
				$this->chunks_table,
				array(
					'document_id' => $document_id,
					'chunk_index' => $chunk['index'] ?? 0,
					'content'     => $chunk['content'] ?? '',
					'chunk_hash'  => $chunk['hash'] ?? md5( $chunk['content'] ?? '' ),
					'chunk_size'  => $chunk['size'] ?? strlen( $chunk['content'] ?? '' ),
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return true;
	}

	/**
	 * Get document chunks
	 *
	 * @param int $document_id Document ID.
	 * @return array
	 */
	private function get_document_chunks( $document_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->chunks_table} WHERE document_id = %d ORDER BY chunk_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$document_id
			),
			ARRAY_A
		);
	}

	/**
	 * Delete document chunks
	 *
	 * @param int $document_id Document ID.
	 * @return bool
	 */
	private function delete_document_chunks( $document_id ) {
		global $wpdb;

		$wpdb->delete(
			$this->chunks_table,
			array( 'document_id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}

	/**
	 * Search documents
	 *
	 * @param string $query   Search query.
	 * @param array  $filters Filters.
	 * @param int    $limit   Result limit.
	 * @return array
	 */
	public function search_documents( $query, $filters = array(), $limit = 10 ) {
		global $wpdb;

		$where_clauses = array( "status = 'active'" );
		$params = array();

		// Add search query
		if ( ! empty( $query ) ) {
			$where_clauses[] = "MATCH(title, content) AGAINST(%s IN BOOLEAN MODE)";
			$params[] = $query;
		}

		// Add category filter
		if ( ! empty( $filters['category'] ) ) {
			$where_clauses[] = 'category = %s';
			$params[] = $filters['category'];
		}

		// Add categories filter (multiple)
		if ( ! empty( $filters['categories'] ) && is_array( $filters['categories'] ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $filters['categories'] ), '%s' ) );
			$where_clauses[] = "category IN ($placeholders)";
			$params = array_merge( $params, $filters['categories'] );
		}

		// Build query
		$where = implode( ' AND ', $where_clauses );
		$sql = "SELECT * FROM {$this->documents_table} WHERE {$where} ORDER BY created_at DESC LIMIT %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params[] = $limit;

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		// Decode metadata
		foreach ( $results as &$result ) {
			$result['metadata'] = json_decode( $result['metadata'], true ) ?? array();
		}

		return $results;
	}

	/**
	 * List documents
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function list_documents( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => 'active',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 50,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where_clauses[] = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['category'] ) ) {
			$where_clauses[] = 'category = %s';
			$params[] = $args['category'];
		}

		$where = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Validate orderby
		$allowed_orderby = array( 'created_at', 'updated_at', 'title', 'category', 'file_size' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';

		// Validate order
		$order = in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $args['order'] ) : 'DESC';

		$sql = "SELECT * FROM {$this->documents_table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		// Decode metadata
		foreach ( $results as &$result ) {
			$result['metadata'] = json_decode( $result['metadata'], true ) ?? array();
		}

		return $results;
	}

	/**
	 * Get documents by category
	 *
	 * @param string $category Category.
	 * @param int    $limit    Limit.
	 * @return array
	 */
	public function get_documents_by_category( $category, $limit = 50 ) {
		return $this->list_documents(
			array(
				'category' => $category,
				'limit'    => $limit,
			)
		);
	}

	/**
	 * Count documents by category
	 *
	 * @param string $category Category.
	 * @return int
	 */
	public function count_documents_by_category( $category ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->documents_table} WHERE category = %s AND status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$category
			)
		);
	}

	/**
	 * Store embeddings
	 *
	 * @param array $embeddings_data Embeddings data.
	 * @return bool|WP_Error
	 */
	public function store_embeddings( $embeddings_data ) {
		global $wpdb;

		foreach ( $embeddings_data as $data ) {
			// Get chunk ID if not provided
			$chunk_id = null;
			if ( ! isset( $data['chunk_id'] ) && isset( $data['document_id'], $data['chunk_index'] ) ) {
				$chunk_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$this->chunks_table} WHERE document_id = %d AND chunk_index = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$data['document_id'],
						$data['chunk_index']
					)
				);
			} else {
				$chunk_id = $data['chunk_id'] ?? null;
			}

			$result = $wpdb->insert(
				$this->embeddings_table,
				array(
					'document_id' => $data['document_id'],
					'chunk_id'    => $chunk_id,
					'chunk_index' => $data['chunk_index'] ?? null,
					'embedding'   => wp_json_encode( $data['embedding'] ),
					'model'       => $data['model'] ?? 'text-embedding-ada-002',
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			if ( false === $result ) {
				return new WP_Error( 'embedding_store_error', $wpdb->last_error );
			}
		}

		return true;
	}

	/**
	 * Search by embedding
	 *
	 * @param array $query_embedding Query embedding.
	 * @param array $filters         Filters.
	 * @param int   $limit           Result limit.
	 * @return array
	 */
	public function search_by_embedding( $query_embedding, $filters = array(), $limit = 10 ) {
		global $wpdb;

		// Get all embeddings with filters
		$where_clauses = array();
		$params = array();

		if ( ! empty( $filters['category'] ) ) {
			$where_clauses[] = 'd.category = %s';
			$params[] = $filters['category'];
		}

		if ( ! empty( $filters['categories'] ) && is_array( $filters['categories'] ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $filters['categories'] ), '%s' ) );
			$where_clauses[] = "d.category IN ($placeholders)";
			$params = array_merge( $params, $filters['categories'] );
		}

		$where_clauses[] = "d.status = 'active'";

		$where = implode( ' AND ', $where_clauses );

		$sql = "SELECT e.*, d.title, d.category, d.metadata as doc_metadata, c.content as chunk_content
				FROM {$this->embeddings_table} e
				JOIN {$this->documents_table} d ON e.document_id = d.id
				LEFT JOIN {$this->chunks_table} c ON e.chunk_id = c.id
				WHERE {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$embeddings = $wpdb->get_results(
			! empty( $params ) ? $wpdb->prepare( $sql, $params ) : $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		// Calculate similarities
		$results = array();
		foreach ( $embeddings as $embedding ) {
			$stored_embedding = json_decode( $embedding['embedding'], true );
			$similarity = $this->cosine_similarity( $query_embedding, $stored_embedding );

			$results[] = array(
				'document_id'     => $embedding['document_id'],
				'chunk_id'        => $embedding['chunk_id'],
				'chunk_index'     => $embedding['chunk_index'],
				'content'         => $embedding['chunk_content'],
				'document'        => array(
					'title'    => $embedding['title'],
					'category' => $embedding['category'],
					'metadata' => json_decode( $embedding['doc_metadata'], true ),
				),
				'relevance_score' => $similarity,
				'embedding'       => $stored_embedding,
			);
		}

		// Sort by similarity
		usort(
			$results,
			function ( $a, $b ) {
				return $b['relevance_score'] <=> $a['relevance_score'];
			}
		);

		// Return top results
		return array_slice( $results, 0, $limit );
	}

	/**
	 * Calculate cosine similarity
	 *
	 * @param array $vec1 Vector 1.
	 * @param array $vec2 Vector 2.
	 * @return float
	 */
	private function cosine_similarity( $vec1, $vec2 ) {
		if ( count( $vec1 ) !== count( $vec2 ) ) {
			return 0;
		}

		$dot = 0;
		$mag1 = 0;
		$mag2 = 0;

		for ( $i = 0; $i < count( $vec1 ); $i++ ) {
			$dot  += $vec1[ $i ] * $vec2[ $i ];
			$mag1 += $vec1[ $i ] * $vec1[ $i ];
			$mag2 += $vec2[ $i ] * $vec2[ $i ];
		}

		$mag1 = sqrt( $mag1 );
		$mag2 = sqrt( $mag2 );

		if ( 0 == $mag1 * $mag2 ) {
			return 0;
		}

		return $dot / ( $mag1 * $mag2 );
	}

	/**
	 * Get document embeddings
	 *
	 * @param int $document_id Document ID.
	 * @return array
	 */
	public function get_document_embeddings( $document_id ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->embeddings_table} WHERE document_id = %d ORDER BY chunk_index", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$document_id
			),
			ARRAY_A
		);

		// Decode embeddings
		foreach ( $results as &$result ) {
			$result['embedding'] = json_decode( $result['embedding'], true );
		}

		return $results;
	}

	/**
	 * Delete document embeddings
	 *
	 * @param int $document_id Document ID.
	 * @return bool
	 */
	public function delete_document_embeddings( $document_id ) {
		global $wpdb;

		$wpdb->delete(
			$this->embeddings_table,
			array( 'document_id' => $document_id )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}

	/**
	 * Update chunk embedding
	 *
	 * @param int   $document_id Document ID.
	 * @param int   $chunk_index Chunk index.
	 * @param array $embedding   New embedding.
	 * @return bool
	 */
	public function update_chunk_embedding( $document_id, $chunk_index, $embedding ) {
		global $wpdb;

		// Delete old embedding
		$wpdb->delete(
			$this->embeddings_table,
			array(
				'document_id' => $document_id,
				'chunk_index' => $chunk_index,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Insert new embedding
		return $wpdb->insert(
			$this->embeddings_table,
			array(
				'document_id' => $document_id,
				'chunk_index' => $chunk_index,
				'embedding'   => wp_json_encode( $embedding ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Record document usage
	 *
	 * @param int   $document_id Document ID.
	 * @param array $data        Usage data.
	 * @return bool
	 */
	public function record_document_usage( $document_id, $data ) {
		global $wpdb;

		return $wpdb->insert(
			$this->usage_table,
			array(
				'document_id' => $document_id,
				'context'     => $data['context'] ?? 'generation',
				'user_id'     => $data['user_id'] ?? get_current_user_id(),
				'metadata'    => wp_json_encode( $data['metadata'] ?? array() ),
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get document usage statistics
	 *
	 * @param int $document_id Document ID.
	 * @return array
	 */
	public function get_document_usage_stats( $document_id ) {
		global $wpdb;

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->usage_table} WHERE document_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$document_id
			)
		);

		$by_context = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT context, COUNT(*) as count FROM {$this->usage_table} WHERE document_id = %d GROUP BY context", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$document_id
			),
			ARRAY_A
		);

		$last_used = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(used_at) FROM {$this->usage_table} WHERE document_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$document_id
			)
		);

		return array(
			'total_uses'  => (int) $total,
			'by_context'  => $by_context,
			'last_used'   => $last_used,
		);
	}

	/**
	 * Get most used documents
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_most_used_documents( $limit = 10 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.*, COUNT(u.id) as usage_count
				FROM {$this->documents_table} d
				LEFT JOIN {$this->usage_table} u ON d.id = u.document_id
				WHERE d.status = 'active'
				GROUP BY d.id
				ORDER BY usage_count DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Find similar embeddings
	 *
	 * @param array $embedding Embedding to match.
	 * @param int   $limit     Result limit.
	 * @return array
	 */
	public function find_similar_embeddings( $embedding, $limit = 5 ) {
		return $this->search_by_embedding( $embedding, array(), $limit );
	}

	/**
	 * Count embeddings
	 *
	 * @return int
	 */
	public function count_embeddings() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->embeddings_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Count documents with embeddings
	 *
	 * @return int
	 */
	public function count_documents_with_embeddings() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT document_id) FROM {$this->embeddings_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get average chunk size
	 *
	 * @return float
	 */
	public function get_average_chunk_size() {
		global $wpdb;
		return (float) $wpdb->get_var( "SELECT AVG(chunk_size) FROM {$this->chunks_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Cleanup orphaned embeddings
	 *
	 * @return int Number of deleted records.
	 */
	public function cleanup_orphaned_embeddings() {
		global $wpdb;

		// Delete embeddings for non-existent documents
		$deleted = $wpdb->query(
			"DELETE e FROM {$this->embeddings_table} e
			LEFT JOIN {$this->documents_table} d ON e.document_id = d.id
			WHERE d.id IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Delete chunks for non-existent documents
		$deleted += $wpdb->query(
			"DELETE c FROM {$this->chunks_table} c
			LEFT JOIN {$this->documents_table} d ON c.document_id = d.id
			WHERE d.id IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $deleted;
	}

	/**
	 * Compress old embeddings
	 *
	 * @return bool
	 */
	public function compress_old_embeddings() {
		// This could implement compression strategies for old embeddings
		// For now, just return true
		return true;
	}
}