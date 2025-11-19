<?php
/**
 * RAG (Retrieval Augmented Generation) Integration
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

namespace AI_Site_Generator\Includes;

use AI_Site_Generator\Includes\Knowledge_Base;
use AI_Site_Generator\Includes\Vector_Embeddings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RAG Integration Class
 */
class RAG_Integration {
	/**
	 * Instance
	 *
	 * @var RAG_Integration
	 */
	private static $instance = null;

	/**
	 * Knowledge base
	 *
	 * @var Knowledge_Base
	 */
	private $knowledge_base;

	/**
	 * Vector embeddings
	 *
	 * @var Vector_Embeddings
	 */
	private $embeddings;

	/**
	 * Default retrieval settings
	 *
	 * @var array
	 */
	private $default_settings = array(
		'top_k'                 => 5,
		'min_relevance_score'   => 0.7,
		'max_context_length'    => 3000,
		'include_citations'     => true,
		'deduplicate'           => true,
		'context_window_ratio'  => 0.3, // Use 30% of context window for KB
	);

	/**
	 * Context cache
	 *
	 * @var array
	 */
	private $context_cache = array();

	/**
	 * Usage tracking
	 *
	 * @var array
	 */
	private $usage_tracking = array();

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->knowledge_base = Knowledge_Base::get_instance();
		$this->embeddings     = Vector_Embeddings::get_instance();

		// Load settings
		$this->load_settings();

		// Register hooks
		add_filter( 'ai_site_generator_pre_prompt', array( $this, 'inject_context' ), 10, 3 );
		add_action( 'ai_site_generator_post_generation', array( $this, 'record_usage' ), 10, 2 );
	}

	/**
	 * Get instance
	 *
	 * @return RAG_Integration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load settings
	 */
	private function load_settings() {
		$settings                 = get_option( 'ai_site_generator_rag_settings', array() );
		$this->default_settings = wp_parse_args( $settings, $this->default_settings );
	}

	/**
	 * Retrieve relevant context
	 *
	 * @param string $query    Query text.
	 * @param array  $filters  Optional filters.
	 * @param array  $settings Retrieval settings.
	 * @return array
	 */
	public function retrieve_context( $query, $filters = array(), $settings = array() ) {
		$settings = wp_parse_args( $settings, $this->default_settings );

		// Check cache
		$cache_key = md5( $query . serialize( $filters ) . serialize( $settings ) );
		if ( isset( $this->context_cache[ $cache_key ] ) ) {
			return $this->context_cache[ $cache_key ];
		}

		// Perform semantic search
		$results = $this->embeddings->semantic_search(
			$query,
			$filters,
			$settings['top_k'] * 2 // Get more results for filtering
		);

		if ( empty( $results ) ) {
			// Fall back to keyword search
			$results = $this->knowledge_base->search_documents(
				$query,
				$filters,
				$settings['top_k']
			);
		}

		// Filter by relevance score
		$relevant_results = array();
		foreach ( $results as $result ) {
			if ( isset( $result['relevance_score'] ) && $result['relevance_score'] >= $settings['min_relevance_score'] ) {
				$relevant_results[] = $result;
			} elseif ( ! isset( $result['relevance_score'] ) ) {
				// Include results from keyword search
				$relevant_results[] = $result;
			}
		}

		// Limit to top_k
		$relevant_results = array_slice( $relevant_results, 0, $settings['top_k'] );

		// Deduplicate if enabled
		if ( $settings['deduplicate'] ) {
			$relevant_results = $this->deduplicate_results( $relevant_results );
		}

		// Build context
		$context = $this->build_context( $relevant_results, $settings );

		// Cache the result
		$this->context_cache[ $cache_key ] = $context;

		// Track which documents were used
		$this->track_usage( $relevant_results );

		return $context;
	}

	/**
	 * Inject context into prompt
	 *
	 * @param string $prompt   Original prompt.
	 * @param string $type     Generation type.
	 * @param array  $metadata Generation metadata.
	 * @return string
	 */
	public function inject_context( $prompt, $type = 'general', $metadata = array() ) {
		// Check if RAG is enabled for this type
		if ( ! $this->should_use_rag( $type, $metadata ) ) {
			return $prompt;
		}

		// Extract query from prompt
		$query = $this->extract_query( $prompt, $metadata );

		// Determine filters based on type
		$filters = $this->determine_filters( $type, $metadata );

		// Retrieve relevant context
		$context = $this->retrieve_context( $query, $filters );

		if ( empty( $context['content'] ) ) {
			return $prompt;
		}

		// Inject context into prompt
		$enhanced_prompt = $this->format_prompt_with_context( $prompt, $context );

		return $enhanced_prompt;
	}

	/**
	 * Build context from results
	 *
	 * @param array $results  Search results.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function build_context( $results, $settings ) {
		$context = array(
			'content'     => '',
			'citations'   => array(),
			'metadata'    => array(),
			'documents'   => array(),
			'total_chars' => 0,
		);

		$current_length = 0;
		$max_length     = $settings['max_context_length'];

		foreach ( $results as $result ) {
			// Get chunk content
			$chunk_content = '';
			if ( isset( $result['chunk'] ) ) {
				$chunk_content = $result['chunk']['content'];
			} elseif ( isset( $result['content'] ) ) {
				$chunk_content = $result['content'];
			}

			// Check if adding this would exceed limit
			$chunk_length = strlen( $chunk_content );
			if ( $current_length + $chunk_length > $max_length ) {
				// Add partial content if there's room
				$remaining = $max_length - $current_length;
				if ( $remaining > 100 ) {
					$chunk_content = substr( $chunk_content, 0, $remaining );
				} else {
					break;
				}
			}

			// Add to context
			if ( ! empty( $chunk_content ) ) {
				// Add separator if not first item
				if ( ! empty( $context['content'] ) ) {
					$context['content'] .= "\n\n---\n\n";
					$current_length     += 8;
				}

				// Add source information if citations enabled
				if ( $settings['include_citations'] && isset( $result['document'] ) ) {
					$citation = sprintf(
						"[Source: %s - %s]\n",
						$result['document']['title'] ?? 'Document',
						$result['document']['category'] ?? 'General'
					);
					$context['content'] .= $citation;
					$current_length     += strlen( $citation );

					// Track citation
					$context['citations'][] = array(
						'document_id' => $result['document_id'] ?? null,
						'title'       => $result['document']['title'] ?? 'Unknown',
						'category'    => $result['document']['category'] ?? 'general',
						'chunk_index' => $result['chunk_index'] ?? null,
					);
				}

				// Add content
				$context['content'] .= $chunk_content;
				$current_length     += strlen( $chunk_content );

				// Track document usage
				if ( isset( $result['document_id'] ) && ! in_array( $result['document_id'], $context['documents'], true ) ) {
					$context['documents'][] = $result['document_id'];
				}

				// Add metadata
				if ( isset( $result['metadata'] ) ) {
					$context['metadata'][] = $result['metadata'];
				}
			}
		}

		$context['total_chars'] = $current_length;

		return $context;
	}

	/**
	 * Format prompt with context
	 *
	 * @param string $prompt  Original prompt.
	 * @param array  $context Retrieved context.
	 * @return string
	 */
	private function format_prompt_with_context( $prompt, $context ) {
		if ( empty( $context['content'] ) ) {
			return $prompt;
		}

		$enhanced_prompt = "## Knowledge Base Context\n\n";
		$enhanced_prompt .= "The following information from the knowledge base is relevant to this request:\n\n";
		$enhanced_prompt .= $context['content'];
		$enhanced_prompt .= "\n\n## Instructions\n\n";
		$enhanced_prompt .= "Please use the above knowledge base information to inform your response. ";
		$enhanced_prompt .= "Ensure your output aligns with the provided context, including any brand guidelines, tone, and specific information mentioned.\n\n";
		$enhanced_prompt .= "## Original Request\n\n";
		$enhanced_prompt .= $prompt;

		// Add citation footer if citations are included
		if ( ! empty( $context['citations'] ) ) {
			$enhanced_prompt .= "\n\n## Note\n";
			$enhanced_prompt .= 'This response uses information from ' . count( $context['citations'] ) . ' knowledge base source(s).';
		}

		return $enhanced_prompt;
	}

	/**
	 * Should use RAG for this generation
	 *
	 * @param string $type     Generation type.
	 * @param array  $metadata Metadata.
	 * @return bool
	 */
	private function should_use_rag( $type, $metadata ) {
		// Check if RAG is globally enabled
		$settings    = get_option( 'ai_site_generator_settings', array() );
		$rag_enabled = $settings['enable_rag'] ?? true;

		if ( ! $rag_enabled ) {
			return false;
		}

		// Check if explicitly disabled in metadata
		if ( isset( $metadata['disable_rag'] ) && $metadata['disable_rag'] ) {
			return false;
		}

		// Check if knowledge base has content
		$kb_stats = $this->knowledge_base->get_category_stats();
		$total_docs = array_sum( array_column( $kb_stats, 'count' ) );

		if ( 0 === $total_docs ) {
			return false;
		}

		// Enable for specific generation types
		$enabled_types = array(
			'page',
			'post',
			'block',
			'section',
			'content',
			'marketing',
			'funnel',
			'email',
		);

		return in_array( $type, $enabled_types, true );
	}

	/**
	 * Extract query from prompt
	 *
	 * @param string $prompt   Prompt.
	 * @param array  $metadata Metadata.
	 * @return string
	 */
	private function extract_query( $prompt, $metadata ) {
		// Use metadata query if available
		if ( ! empty( $metadata['query'] ) ) {
			return $metadata['query'];
		}

		// Use metadata topic if available
		if ( ! empty( $metadata['topic'] ) ) {
			return $metadata['topic'];
		}

		// Extract from prompt
		// Take first 500 characters as query
		$query = substr( $prompt, 0, 500 );

		// Try to extract the main request
		if ( preg_match( '/(?:create|generate|write|build)\s+(?:a|an)?\s*(.+?)(?:\.|$)/i', $prompt, $matches ) ) {
			$query = $matches[1];
		}

		return $query;
	}

	/**
	 * Determine filters based on type
	 *
	 * @param string $type     Generation type.
	 * @param array  $metadata Metadata.
	 * @return array
	 */
	private function determine_filters( $type, $metadata ) {
		$filters = array();

		// Add category filters based on type
		switch ( $type ) {
			case 'marketing':
			case 'funnel':
				$filters['categories'] = array( 'products', 'services', 'audience', 'brand' );
				break;

			case 'email':
				$filters['categories'] = array( 'tone', 'brand', 'audience' );
				break;

			case 'page':
			case 'post':
				$filters['categories'] = array( 'seo', 'tone', 'brand', 'company' );
				break;

			case 'block':
			case 'section':
				$filters['categories'] = array( 'design', 'brand', 'tone' );
				break;
		}

		// Add metadata filters
		if ( ! empty( $metadata['categories'] ) ) {
			$filters['categories'] = $metadata['categories'];
		}

		if ( ! empty( $metadata['exclude_categories'] ) ) {
			$filters['exclude_categories'] = $metadata['exclude_categories'];
		}

		// Add status filter
		$filters['status'] = 'active';

		return $filters;
	}

	/**
	 * Deduplicate results
	 *
	 * @param array $results Results to deduplicate.
	 * @return array
	 */
	private function deduplicate_results( $results ) {
		$seen         = array();
		$deduplicated = array();

		foreach ( $results as $result ) {
			// Create a unique key
			$key = '';
			if ( isset( $result['document_id'] ) && isset( $result['chunk_index'] ) ) {
				$key = $result['document_id'] . '_' . $result['chunk_index'];
			} elseif ( isset( $result['content'] ) ) {
				$key = md5( $result['content'] );
			}

			if ( ! empty( $key ) && ! isset( $seen[ $key ] ) ) {
				$seen[ $key ]   = true;
				$deduplicated[] = $result;
			}
		}

		return $deduplicated;
	}

	/**
	 * Track usage
	 *
	 * @param array $results Used results.
	 */
	private function track_usage( $results ) {
		foreach ( $results as $result ) {
			if ( isset( $result['document_id'] ) ) {
				if ( ! isset( $this->usage_tracking[ $result['document_id'] ] ) ) {
					$this->usage_tracking[ $result['document_id'] ] = 0;
				}
				$this->usage_tracking[ $result['document_id'] ]++;
			}
		}
	}

	/**
	 * Record usage
	 *
	 * @param string $generation_id Generation ID.
	 * @param array  $metadata      Generation metadata.
	 */
	public function record_usage( $generation_id, $metadata ) {
		if ( empty( $this->usage_tracking ) ) {
			return;
		}

		foreach ( $this->usage_tracking as $document_id => $count ) {
			$this->knowledge_base->record_usage(
				$document_id,
				'generation',
				array(
					'generation_id' => $generation_id,
					'usage_count'   => $count,
					'metadata'      => $metadata,
				)
			);
		}

		// Clear tracking
		$this->usage_tracking = array();
	}

	/**
	 * Get context for specific categories
	 *
	 * @param array $categories Categories to retrieve.
	 * @param int   $limit      Document limit per category.
	 * @return array
	 */
	public function get_category_context( $categories, $limit = 3 ) {
		$context = array(
			'content'   => '',
			'documents' => array(),
		);

		foreach ( $categories as $category ) {
			$documents = $this->knowledge_base->get_documents_by_category( $category, $limit );

			foreach ( $documents as $document ) {
				if ( ! empty( $document['content'] ) ) {
					$context['content'] .= "\n\n## " . $this->knowledge_base->get_categories()[ $category ] . "\n\n";
					$context['content'] .= $document['content'];
					$context['documents'][] = $document['id'];
				}
			}
		}

		return $context;
	}

	/**
	 * Get hybrid context (semantic + keyword)
	 *
	 * @param string $query    Query.
	 * @param array  $keywords Additional keywords.
	 * @param array  $settings Settings.
	 * @return array
	 */
	public function get_hybrid_context( $query, $keywords = array(), $settings = array() ) {
		$settings = wp_parse_args( $settings, $this->default_settings );

		// Get semantic results
		$semantic_results = $this->embeddings->semantic_search(
			$query,
			array(),
			ceil( $settings['top_k'] / 2 )
		);

		// Get keyword results
		$keyword_query = $query;
		if ( ! empty( $keywords ) ) {
			$keyword_query .= ' ' . implode( ' ', $keywords );
		}

		$keyword_results = $this->knowledge_base->search_documents(
			$keyword_query,
			array(),
			ceil( $settings['top_k'] / 2 )
		);

		// Merge and deduplicate
		$all_results = array_merge( $semantic_results, $keyword_results );
		$all_results = $this->deduplicate_results( $all_results );

		// Build context
		return $this->build_context( $all_results, $settings );
	}

	/**
	 * Get context statistics
	 *
	 * @return array
	 */
	public function get_statistics() {
		return array(
			'cache_size'        => count( $this->context_cache ),
			'active_tracking'   => count( $this->usage_tracking ),
			'default_top_k'     => $this->default_settings['top_k'],
			'min_relevance'     => $this->default_settings['min_relevance_score'],
			'max_context_chars' => $this->default_settings['max_context_length'],
		);
	}

	/**
	 * Clear context cache
	 */
	public function clear_cache() {
		$this->context_cache = array();
	}

	/**
	 * Update settings
	 *
	 * @param array $settings New settings.
	 * @return bool
	 */
	public function update_settings( $settings ) {
		$this->default_settings = wp_parse_args( $settings, $this->default_settings );
		return update_option( 'ai_site_generator_rag_settings', $this->default_settings );
	}

	/**
	 * Test RAG system
	 *
	 * @param string $query Test query.
	 * @return array Test results.
	 */
	public function test_rag_system( $query ) {
		$results = array(
			'query'            => $query,
			'semantic_search'  => array(),
			'keyword_search'   => array(),
			'final_context'    => array(),
			'performance'      => array(),
		);

		$start_time = microtime( true );

		// Test semantic search
		$semantic_results = $this->embeddings->semantic_search( $query, array(), 5 );
		$results['semantic_search'] = array(
			'count'   => count( $semantic_results ),
			'results' => array_map(
				function ( $r ) {
					return array(
						'title'     => $r['document']['title'] ?? 'Unknown',
						'score'     => $r['relevance_score'] ?? 0,
						'preview'   => substr( $r['content'] ?? '', 0, 100 ),
					);
				},
				$semantic_results
			),
		);

		// Test keyword search
		$keyword_results = $this->knowledge_base->search_documents( $query, array(), 5 );
		$results['keyword_search'] = array(
			'count'   => count( $keyword_results ),
			'results' => array_map(
				function ( $r ) {
					return array(
						'title'   => $r['title'] ?? 'Unknown',
						'preview' => substr( $r['content'] ?? '', 0, 100 ),
					);
				},
				$keyword_results
			),
		);

		// Test context retrieval
		$context = $this->retrieve_context( $query );
		$results['final_context'] = array(
			'content_length' => strlen( $context['content'] ?? '' ),
			'citations'      => count( $context['citations'] ?? array() ),
			'documents_used' => count( $context['documents'] ?? array() ),
		);

		$end_time = microtime( true );
		$results['performance'] = array(
			'total_time' => round( ( $end_time - $start_time ) * 1000, 2 ) . 'ms',
			'cache_used' => isset( $this->context_cache[ md5( $query ) ] ),
		);

		return $results;
	}
}