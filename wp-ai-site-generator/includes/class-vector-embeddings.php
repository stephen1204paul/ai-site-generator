<?php
/**
 * Vector Embeddings System
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

namespace AI_Site_Generator\Includes;

use AI_Site_Generator\Database\KB_DB_Handler;
use AI_Site_Generator\Providers\AI_Provider_Factory;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vector Embeddings Class
 */
class Vector_Embeddings {
	/**
	 * Instance
	 *
	 * @var Vector_Embeddings
	 */
	private static $instance = null;

	/**
	 * Database handler
	 *
	 * @var KB_DB_Handler
	 */
	private $db_handler;

	/**
	 * AI provider
	 *
	 * @var object
	 */
	private $ai_provider;

	/**
	 * Embedding model
	 *
	 * @var string
	 */
	private $embedding_model = 'text-embedding-ada-002';

	/**
	 * Cache duration (seconds)
	 *
	 * @var int
	 */
	private $cache_duration = 86400; // 24 hours

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->db_handler = KB_DB_Handler::get_instance();
		$this->init_ai_provider();

		// Register hooks
		add_action( 'ai_site_generator_process_kb_embeddings', array( $this, 'process_document_embeddings' ) );
	}

	/**
	 * Get instance
	 *
	 * @return Vector_Embeddings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize AI provider
	 */
	private function init_ai_provider() {
		try {
			$settings          = get_option( 'ai_site_generator_settings', array() );
			$provider_settings = $settings['ai_provider'] ?? array();
			$provider_type     = $provider_settings['type'] ?? 'openai';

			// Get provider instance
			$factory           = new AI_Provider_Factory();
			$this->ai_provider = $factory->create( $provider_type, $provider_settings );

			// Update embedding model based on provider
			switch ( $provider_type ) {
				case 'openai':
					$this->embedding_model = 'text-embedding-ada-002';
					break;
				case 'anthropic':
					// Anthropic doesn't have embeddings, fall back to OpenAI
					$this->embedding_model = 'text-embedding-ada-002';
					break;
				case 'cohere':
					$this->embedding_model = 'embed-english-v2.0';
					break;
			}
		} catch ( \Exception $e ) {
			error_log( 'Failed to initialize AI provider for embeddings: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate embedding for text
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error
	 */
	public function generate_embedding( $text ) {
		if ( empty( $text ) ) {
			return new WP_Error( 'empty_text', __( 'Text cannot be empty', 'ai-site-generator' ) );
		}

		// Check cache first
		$cache_key = 'embedding_' . md5( $text );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		try {
			// Truncate text if too long (8191 tokens max for OpenAI)
			$max_length = 8000; // Conservative limit
			if ( strlen( $text ) > $max_length ) {
				$text = substr( $text, 0, $max_length );
			}

			// Generate embedding using AI provider
			$response = $this->call_embedding_api( $text );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// Cache the result
			set_transient( $cache_key, $response, $this->cache_duration );

			return $response;

		} catch ( \Exception $e ) {
			return new WP_Error( 'embedding_generation_failed', $e->getMessage() );
		}
	}

	/**
	 * Call embedding API
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error
	 */
	private function call_embedding_api( $text ) {
		$settings      = get_option( 'ai_site_generator_settings', array() );
		$provider_type = $settings['ai_provider']['type'] ?? 'openai';

		switch ( $provider_type ) {
			case 'openai':
				return $this->generate_openai_embedding( $text );

			case 'cohere':
				return $this->generate_cohere_embedding( $text );

			default:
				// Fall back to OpenAI for providers without embedding support
				return $this->generate_openai_embedding( $text );
		}
	}

	/**
	 * Generate OpenAI embedding
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error
	 */
	private function generate_openai_embedding( $text ) {
		$settings = get_option( 'ai_site_generator_settings', array() );
		$api_key  = $settings['ai_provider']['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key not configured', 'ai-site-generator' ) );
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/embeddings',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => $this->embedding_model,
						'input' => $text,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'api_error', $data['error']['message'] ?? 'Unknown API error' );
		}

		if ( ! isset( $data['data'][0]['embedding'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid API response', 'ai-site-generator' ) );
		}

		return $data['data'][0]['embedding'];
	}

	/**
	 * Generate Cohere embedding
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error
	 */
	private function generate_cohere_embedding( $text ) {
		$settings = get_option( 'ai_site_generator_settings', array() );
		$api_key  = $settings['ai_provider']['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'Cohere API key not configured', 'ai-site-generator' ) );
		}

		$response = wp_remote_post(
			'https://api.cohere.ai/v1/embed',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => $this->embedding_model,
						'texts' => array( $text ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['message'] ) ) {
			return new WP_Error( 'api_error', $data['message'] );
		}

		if ( ! isset( $data['embeddings'][0] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid API response', 'ai-site-generator' ) );
		}

		return $data['embeddings'][0];
	}

	/**
	 * Process document embeddings
	 *
	 * @param int $document_id Document ID.
	 * @return bool|WP_Error
	 */
	public function process_document_embeddings( $document_id ) {
		try {
			$document = KB_DB_Handler::get_instance()->get_document( $document_id );
			if ( is_wp_error( $document ) ) {
				return $document;
			}

			if ( empty( $document['chunks'] ) ) {
				return new WP_Error( 'no_chunks', __( 'Document has no chunks to process', 'ai-site-generator' ) );
			}

			$embeddings_data = array();

			foreach ( $document['chunks'] as $chunk ) {
				$embedding = $this->generate_embedding( $chunk['content'] );
				if ( ! is_wp_error( $embedding ) ) {
					$embeddings_data[] = array(
						'document_id' => $document_id,
						'chunk_index' => $chunk['index'],
						'embedding'   => $embedding,
						'chunk_hash'  => $chunk['hash'],
					);
				} else {
					error_log( 'Failed to generate embedding for chunk ' . $chunk['index'] . ': ' . $embedding->get_error_message() );
				}

				// Add small delay to avoid rate limiting
				usleep( 100000 ); // 0.1 seconds
			}

			// Store embeddings in database
			if ( ! empty( $embeddings_data ) ) {
				$result = $this->db_handler->store_embeddings( $embeddings_data );
				if ( ! is_wp_error( $result ) ) {
					// Update document status
					$this->db_handler->update_document(
						$document_id,
						array( 'status' => 'active' )
					);
				}
				return $result;
			}

			return true;

		} catch ( \Exception $e ) {
			return new WP_Error( 'processing_error', $e->getMessage() );
		}
	}

	/**
	 * Semantic search
	 *
	 * @param string $query   Search query.
	 * @param array  $filters Filters.
	 * @param int    $limit   Result limit.
	 * @return array
	 */
	public function semantic_search( $query, $filters = array(), $limit = 10 ) {
		try {
			// Generate embedding for query
			$query_embedding = $this->generate_embedding( $query );
			if ( is_wp_error( $query_embedding ) ) {
				error_log( 'Failed to generate query embedding: ' . $query_embedding->get_error_message() );
				return array();
			}

			// Search for similar embeddings
			$results = $this->db_handler->search_by_embedding( $query_embedding, $filters, $limit );

			// Calculate relevance scores
			foreach ( $results as &$result ) {
				$result['relevance_score'] = $this->calculate_similarity(
					$query_embedding,
					$result['embedding']
				);
			}

			// Sort by relevance
			usort(
				$results,
				function ( $a, $b ) {
					return $b['relevance_score'] <=> $a['relevance_score'];
				}
			);

			return $results;

		} catch ( \Exception $e ) {
			error_log( 'Semantic search failed: ' . $e->getMessage() );
			return array();
		}
	}

	/**
	 * Calculate cosine similarity
	 *
	 * @param array $vector1 First vector.
	 * @param array $vector2 Second vector.
	 * @return float
	 */
	public function calculate_similarity( $vector1, $vector2 ) {
		if ( count( $vector1 ) !== count( $vector2 ) ) {
			return 0;
		}

		$dot_product = 0;
		$magnitude1  = 0;
		$magnitude2  = 0;

		for ( $i = 0; $i < count( $vector1 ); $i++ ) {
			$dot_product += $vector1[ $i ] * $vector2[ $i ];
			$magnitude1  += $vector1[ $i ] * $vector1[ $i ];
			$magnitude2  += $vector2[ $i ] * $vector2[ $i ];
		}

		$magnitude1 = sqrt( $magnitude1 );
		$magnitude2 = sqrt( $magnitude2 );

		if ( $magnitude1 * $magnitude2 == 0 ) {
			return 0;
		}

		return $dot_product / ( $magnitude1 * $magnitude2 );
	}

	/**
	 * Find similar chunks
	 *
	 * @param string $text  Text to find similar chunks for.
	 * @param int    $limit Result limit.
	 * @return array
	 */
	public function find_similar_chunks( $text, $limit = 5 ) {
		$embedding = $this->generate_embedding( $text );
		if ( is_wp_error( $embedding ) ) {
			return array();
		}

		return $this->db_handler->find_similar_embeddings( $embedding, $limit );
	}

	/**
	 * Get embeddings for document
	 *
	 * @param int $document_id Document ID.
	 * @return array
	 */
	public function get_document_embeddings( $document_id ) {
		return $this->db_handler->get_document_embeddings( $document_id );
	}

	/**
	 * Delete document embeddings
	 *
	 * @param int $document_id Document ID.
	 * @return bool
	 */
	public function delete_document_embeddings( $document_id ) {
		return $this->db_handler->delete_document_embeddings( $document_id );
	}

	/**
	 * Update embedding for chunk
	 *
	 * @param int    $document_id Document ID.
	 * @param int    $chunk_index Chunk index.
	 * @param string $text        New text.
	 * @return bool|WP_Error
	 */
	public function update_chunk_embedding( $document_id, $chunk_index, $text ) {
		$embedding = $this->generate_embedding( $text );
		if ( is_wp_error( $embedding ) ) {
			return $embedding;
		}

		return $this->db_handler->update_chunk_embedding( $document_id, $chunk_index, $embedding );
	}

	/**
	 * Clear old cache entries
	 */
	public function clear_old_cache() {
		global $wpdb;

		// Delete transients older than cache duration
		$expired = time() - $this->cache_duration;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s
				AND option_value < %d",
				'_transient_timeout_embedding_%',
				'%',
				$expired
			)
		);
	}

	/**
	 * Cleanup orphaned embeddings
	 */
	public function cleanup_orphaned_embeddings() {
		return $this->db_handler->cleanup_orphaned_embeddings();
	}

	/**
	 * Get embedding statistics
	 *
	 * @return array
	 */
	public function get_statistics() {
		return array(
			'total_embeddings'    => $this->db_handler->count_embeddings(),
			'total_documents'     => $this->db_handler->count_documents_with_embeddings(),
			'average_chunk_size'  => $this->db_handler->get_average_chunk_size(),
			'cache_entries'       => $this->count_cache_entries(),
			'embedding_model'     => $this->embedding_model,
		);
	}

	/**
	 * Count cache entries
	 *
	 * @return int
	 */
	private function count_cache_entries() {
		global $wpdb;
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_embedding_%'"
		);
	}

	/**
	 * Batch process embeddings
	 *
	 * @param array $texts Texts to process.
	 * @return array
	 */
	public function batch_generate_embeddings( $texts ) {
		$results = array();

		// Process in batches to avoid rate limiting
		$batch_size = 20;
		$batches    = array_chunk( $texts, $batch_size );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $text ) {
				$embedding = $this->generate_embedding( $text );
				if ( ! is_wp_error( $embedding ) ) {
					$results[] = $embedding;
				} else {
					$results[] = null;
				}
				usleep( 50000 ); // 0.05 seconds between requests
			}
			sleep( 1 ); // 1 second between batches
		}

		return $results;
	}

	/**
	 * Optimize embeddings storage
	 *
	 * @return bool
	 */
	public function optimize_storage() {
		// Compress old embeddings
		return $this->db_handler->compress_old_embeddings();
	}
}