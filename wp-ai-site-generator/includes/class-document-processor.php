<?php
/**
 * Document Processor
 *
 * @package AI_Site_Generator
 * @since 1.0.0
 */

namespace AI_Site_Generator\Includes;

use WP_Error;
use DOMDocument;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document Processor Class
 */
class Document_Processor {
	/**
	 * Instance
	 *
	 * @var Document_Processor
	 */
	private static $instance = null;

	/**
	 * Chunk size for text splitting
	 *
	 * @var int
	 */
	private $chunk_size = 1500;

	/**
	 * Chunk overlap
	 *
	 * @var int
	 */
	private $chunk_overlap = 200;

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check for required extensions
		$this->check_dependencies();
	}

	/**
	 * Get instance
	 *
	 * @return Document_Processor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check dependencies
	 */
	private function check_dependencies() {
		$missing = array();

		if ( ! class_exists( 'ZipArchive' ) ) {
			$missing[] = 'ZipArchive (for DOCX support)';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			$missing[] = 'DOMDocument (for HTML parsing)';
		}

		if ( ! empty( $missing ) ) {
			error_log( 'AI Site Generator - Missing dependencies: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Process document
	 *
	 * @param string $file_path File path.
	 * @param string $mime_type MIME type.
	 * @return array|WP_Error
	 */
	public function process_document( $file_path, $mime_type ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'File not found', 'ai-site-generator' ) );
		}

		try {
			$content = '';

			// Extract text based on file type
			switch ( $mime_type ) {
				case 'application/pdf':
					$content = $this->extract_pdf_text( $file_path );
					break;

				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
					$content = $this->extract_docx_text( $file_path );
					break;

				case 'text/plain':
					$content = file_get_contents( $file_path );
					break;

				case 'text/markdown':
					$content = $this->process_markdown( file_get_contents( $file_path ) );
					break;

				case 'text/html':
				case 'application/xhtml+xml':
					$content = $this->extract_html_text( file_get_contents( $file_path ) );
					break;

				default:
					// Try to detect by extension if MIME type not recognized
					$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
					switch ( strtolower( $extension ) ) {
						case 'md':
							$content = $this->process_markdown( file_get_contents( $file_path ) );
							break;
						case 'txt':
							$content = file_get_contents( $file_path );
							break;
						case 'html':
						case 'htm':
							$content = $this->extract_html_text( file_get_contents( $file_path ) );
							break;
						default:
							return new WP_Error( 'unsupported_file_type', __( 'Unsupported file type', 'ai-site-generator' ) );
					}
			}

			if ( empty( $content ) ) {
				return new WP_Error( 'no_content_extracted', __( 'No content could be extracted from the file', 'ai-site-generator' ) );
			}

			// Clean and normalize text
			$content = $this->clean_text( $content );

			// Extract metadata
			$metadata = $this->extract_metadata( $content, $mime_type );

			// Chunk the content
			$chunks = $this->chunk_text( $content );

			// Extract keywords
			$keywords = $this->extract_keywords( $content );

			// Generate summary
			$summary = $this->generate_summary( $content );

			return array(
				'content'  => $content,
				'chunks'   => $chunks,
				'metadata' => $metadata,
				'keywords' => $keywords,
				'summary'  => $summary,
			);

		} catch ( \Exception $e ) {
			return new WP_Error( 'processing_error', $e->getMessage() );
		}
	}

	/**
	 * Extract text from PDF
	 *
	 * @param string $file_path File path.
	 * @return string
	 */
	private function extract_pdf_text( $file_path ) {
		$text = '';

		// Use pdftotext command if available
		if ( $this->is_command_available( 'pdftotext' ) ) {
			$temp_file = tempnam( sys_get_temp_dir(), 'pdf_text' );
			$command   = escapeshellcmd( "pdftotext -layout '{$file_path}' '{$temp_file}'" );
			exec( $command, $output, $return_code );

			if ( 0 === $return_code && file_exists( $temp_file ) ) {
				$text = file_get_contents( $temp_file );
				unlink( $temp_file );
			}
		}

		// Fallback: Use basic PDF parsing
		if ( empty( $text ) ) {
			$text = $this->basic_pdf_parser( $file_path );
		}

		return $text;
	}

	/**
	 * Basic PDF text extraction
	 *
	 * @param string $file_path File path.
	 * @return string
	 */
	private function basic_pdf_parser( $file_path ) {
		$content = file_get_contents( $file_path );
		$text    = '';

		// Extract text between BT and ET markers (simplified)
		if ( preg_match_all( '/BT\s*(.*?)\s*ET/s', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				// Extract text from Tj and TJ operators
				if ( preg_match_all( '/\((.*?)\)\s*Tj/s', $match, $text_matches ) ) {
					foreach ( $text_matches[1] as $text_match ) {
						// Decode escaped characters
						$decoded = str_replace(
							array( '\(', '\)', '\\\\' ),
							array( '(', ')', '\\' ),
							$text_match
						);
						$text .= $decoded . ' ';
					}
				}
			}
		}

		// Also try to extract from stream objects
		if ( preg_match_all( '/stream\s*(.*?)\s*endstream/s', $content, $streams ) ) {
			foreach ( $streams[1] as $stream ) {
				// Look for text patterns
				if ( preg_match_all( '/\((.*?)\)/s', $stream, $text_matches ) ) {
					foreach ( $text_matches[1] as $text_match ) {
						if ( $this->is_readable_text( $text_match ) ) {
							$text .= $text_match . ' ';
						}
					}
				}
			}
		}

		return $text;
	}

	/**
	 * Extract text from DOCX
	 *
	 * @param string $file_path File path.
	 * @return string
	 */
	private function extract_docx_text( $file_path ) {
		$text = '';

		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) === true ) {
			// Read main document
			$xml = $zip->getFromName( 'word/document.xml' );
			if ( $xml ) {
				$text .= $this->extract_text_from_xml( $xml );
			}

			// Read headers
			$header_index = 1;
			while ( ( $header_xml = $zip->getFromName( "word/header{$header_index}.xml" ) ) !== false ) {
				$text .= "\n" . $this->extract_text_from_xml( $header_xml );
				$header_index++;
			}

			// Read footers
			$footer_index = 1;
			while ( ( $footer_xml = $zip->getFromName( "word/footer{$footer_index}.xml" ) ) !== false ) {
				$text .= "\n" . $this->extract_text_from_xml( $footer_xml );
				$footer_index++;
			}

			// Read footnotes
			$footnotes_xml = $zip->getFromName( 'word/footnotes.xml' );
			if ( $footnotes_xml ) {
				$text .= "\n" . $this->extract_text_from_xml( $footnotes_xml );
			}

			// Read endnotes
			$endnotes_xml = $zip->getFromName( 'word/endnotes.xml' );
			if ( $endnotes_xml ) {
				$text .= "\n" . $this->extract_text_from_xml( $endnotes_xml );
			}

			$zip->close();
		}

		return $text;
	}

	/**
	 * Extract text from XML
	 *
	 * @param string $xml XML content.
	 * @return string
	 */
	private function extract_text_from_xml( $xml ) {
		$text = '';
		$dom  = new DOMDocument();

		// Suppress warnings for invalid XML
		libxml_use_internal_errors( true );
		$dom->loadXML( $xml );
		libxml_clear_errors();

		// Extract text from w:t elements
		$xpath = new \DOMXPath( $dom );
		$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

		$paragraphs = $xpath->query( '//w:p' );
		foreach ( $paragraphs as $paragraph ) {
			$para_text = '';
			$texts     = $xpath->query( './/w:t', $paragraph );
			foreach ( $texts as $text_node ) {
				$para_text .= $text_node->nodeValue;
			}
			if ( ! empty( $para_text ) ) {
				$text .= trim( $para_text ) . "\n";
			}
		}

		return $text;
	}

	/**
	 * Process Markdown
	 *
	 * @param string $content Markdown content.
	 * @return string
	 */
	private function process_markdown( $content ) {
		// Remove code blocks but keep their content labeled
		$content = preg_replace_callback(
			'/```([a-z]*)\n(.*?)\n```/s',
			function ( $matches ) {
				$lang = ! empty( $matches[1] ) ? $matches[1] : 'code';
				return "\n[CODE BLOCK - {$lang}]:\n" . $matches[2] . "\n";
			},
			$content
		);

		// Convert headers to plain text with emphasis
		$content = preg_replace( '/^#{1,6}\s+(.*)$/m', '[$1]', $content );

		// Remove markdown formatting but keep structure
		$content = preg_replace( '/\*\*([^*]+)\*\*/', '$1', $content ); // Bold
		$content = preg_replace( '/\*([^*]+)\*/', '$1', $content );     // Italic
		$content = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $content ); // Links

		// Keep list structure
		$content = preg_replace( '/^[-*+]\s+/m', '• ', $content );
		$content = preg_replace( '/^\d+\.\s+/m', '• ', $content );

		return $content;
	}

	/**
	 * Extract text from HTML
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	private function extract_html_text( $html ) {
		$dom = new DOMDocument();

		// Suppress warnings for invalid HTML
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		// Remove script and style elements
		$xpath = new \DOMXPath( $dom );
		foreach ( $xpath->query( '//script|//style' ) as $node ) {
			$node->parentNode->removeChild( $node );
		}

		// Extract text content
		$text = $dom->textContent;

		// Clean up whitespace
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Clean text
	 *
	 * @param string $text Text to clean.
	 * @return string
	 */
	public function clean_text( $text ) {
		// Remove non-printable characters
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		// Normalize whitespace
		$text = preg_replace( '/\r\n|\r/', "\n", $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );

		// Remove leading/trailing whitespace from lines
		$lines = explode( "\n", $text );
		$lines = array_map( 'trim', $lines );
		$text  = implode( "\n", $lines );

		// Remove empty lines at the start and end
		$text = trim( $text );

		// Decode HTML entities if present
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return $text;
	}

	/**
	 * Chunk text into semantic sections
	 *
	 * @param string $text    Text to chunk.
	 * @param int    $size    Chunk size.
	 * @param int    $overlap Overlap size.
	 * @return array
	 */
	public function chunk_text( $text, $size = null, $overlap = null ) {
		$size    = $size ?? $this->chunk_size;
		$overlap = $overlap ?? $this->chunk_overlap;

		$chunks = array();

		// First, try to split by paragraphs
		$paragraphs = preg_split( '/\n\n+/', $text );

		$current_chunk = '';
		$current_size  = 0;

		foreach ( $paragraphs as $paragraph ) {
			$para_size = strlen( $paragraph );

			// If paragraph is too large, split it
			if ( $para_size > $size ) {
				// Save current chunk if it has content
				if ( ! empty( $current_chunk ) ) {
					$chunks[]      = trim( $current_chunk );
					$current_chunk = '';
					$current_size  = 0;
				}

				// Split large paragraph by sentences
				$sentences      = preg_split( '/(?<=[.!?])\s+/', $paragraph );
				$sentence_chunk = '';
				$sentence_size  = 0;

				foreach ( $sentences as $sentence ) {
					$sent_size = strlen( $sentence );

					if ( $sentence_size + $sent_size > $size && ! empty( $sentence_chunk ) ) {
						$chunks[]       = trim( $sentence_chunk );
						$sentence_chunk = substr( $sentence_chunk, -$overlap );
						$sentence_size  = strlen( $sentence_chunk );
					}

					$sentence_chunk .= ' ' . $sentence;
					$sentence_size  += $sent_size + 1;
				}

				if ( ! empty( $sentence_chunk ) ) {
					$chunks[] = trim( $sentence_chunk );
				}
			} elseif ( $current_size + $para_size > $size ) {
				// Save current chunk and start new one
				$chunks[] = trim( $current_chunk );

				// Start new chunk with overlap from previous
				if ( $overlap > 0 && strlen( $current_chunk ) > $overlap ) {
					$current_chunk = substr( $current_chunk, -$overlap ) . "\n\n" . $paragraph;
				} else {
					$current_chunk = $paragraph;
				}
				$current_size = strlen( $current_chunk );
			} else {
				// Add to current chunk
				if ( ! empty( $current_chunk ) ) {
					$current_chunk .= "\n\n";
					$current_size  += 2;
				}
				$current_chunk .= $paragraph;
				$current_size  += $para_size;
			}
		}

		// Add remaining chunk
		if ( ! empty( $current_chunk ) ) {
			$chunks[] = trim( $current_chunk );
		}

		// Add chunk metadata
		$chunked_data = array();
		foreach ( $chunks as $index => $chunk ) {
			$chunked_data[] = array(
				'index'   => $index,
				'content' => $chunk,
				'size'    => strlen( $chunk ),
				'hash'    => md5( $chunk ),
			);
		}

		return $chunked_data;
	}

	/**
	 * Extract metadata from content
	 *
	 * @param string $content   Content.
	 * @param string $mime_type MIME type.
	 * @return array
	 */
	private function extract_metadata( $content, $mime_type ) {
		$metadata = array(
			'word_count'     => str_word_count( $content ),
			'character_count' => strlen( $content ),
			'line_count'     => substr_count( $content, "\n" ) + 1,
			'processed_at'   => current_time( 'mysql' ),
		);

		// Extract potential title from first line or header
		$lines = explode( "\n", $content, 2 );
		if ( ! empty( $lines[0] ) && strlen( $lines[0] ) < 200 ) {
			$potential_title = trim( $lines[0] );
			// Remove common markdown or formatting
			$potential_title    = preg_replace( '/^[#\-*_=]+\s*/', '', $potential_title );
			$metadata['title'] = $potential_title;
		}

		// Extract dates
		if ( preg_match_all( '/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2})\b/', $content, $date_matches ) ) {
			$metadata['dates_found'] = array_unique( $date_matches[1] );
		}

		// Extract emails
		if ( preg_match_all( '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $content, $email_matches ) ) {
			$metadata['emails_found'] = array_unique( $email_matches[0] );
		}

		// Extract URLs
		if ( preg_match_all( '/https?:\/\/[^\s<>"{}|\\^\[\]`]+/', $content, $url_matches ) ) {
			$metadata['urls_found'] = array_unique( $url_matches[0] );
		}

		return $metadata;
	}

	/**
	 * Extract keywords
	 *
	 * @param string $content Content.
	 * @param int    $limit   Keyword limit.
	 * @return array
	 */
	private function extract_keywords( $content, $limit = 20 ) {
		// Convert to lowercase
		$content = strtolower( $content );

		// Remove punctuation
		$content = preg_replace( '/[^\w\s]/', ' ', $content );

		// Get word frequency
		$words      = str_word_count( $content, 1 );
		$word_count = array_count_values( $words );

		// Remove common stop words
		$stop_words = $this->get_stop_words();
		foreach ( $stop_words as $stop_word ) {
			unset( $word_count[ $stop_word ] );
		}

		// Remove short words
		foreach ( $word_count as $word => $count ) {
			if ( strlen( $word ) < 4 ) {
				unset( $word_count[ $word ] );
			}
		}

		// Sort by frequency
		arsort( $word_count );

		// Return top keywords
		return array_keys( array_slice( $word_count, 0, $limit ) );
	}

	/**
	 * Generate summary
	 *
	 * @param string $content Content.
	 * @param int    $length  Summary length.
	 * @return string
	 */
	private function generate_summary( $content, $length = 500 ) {
		// Get first few paragraphs
		$paragraphs = preg_split( '/\n\n+/', $content );
		$summary    = '';

		foreach ( $paragraphs as $paragraph ) {
			if ( strlen( $summary ) + strlen( $paragraph ) > $length ) {
				// Add partial paragraph if there's room
				if ( strlen( $summary ) < $length / 2 ) {
					$remaining = $length - strlen( $summary );
					$sentences = preg_split( '/(?<=[.!?])\s+/', $paragraph );
					foreach ( $sentences as $sentence ) {
						if ( strlen( $summary ) + strlen( $sentence ) <= $length ) {
							$summary .= ' ' . $sentence;
						} else {
							break;
						}
					}
				}
				break;
			}
			if ( ! empty( $summary ) ) {
				$summary .= ' ';
			}
			$summary .= $paragraph;
		}

		// Add ellipsis if truncated
		if ( strlen( $summary ) < strlen( $content ) ) {
			$summary = rtrim( $summary ) . '...';
		}

		return trim( $summary );
	}

	/**
	 * Get stop words
	 *
	 * @return array
	 */
	private function get_stop_words() {
		return array(
			'the',
			'and',
			'for',
			'are',
			'but',
			'not',
			'you',
			'all',
			'can',
			'had',
			'her',
			'was',
			'one',
			'our',
			'out',
			'his',
			'has',
			'have',
			'with',
			'this',
			'that',
			'from',
			'were',
			'been',
			'their',
			'they',
			'what',
			'when',
			'where',
			'who',
			'will',
			'with',
			'would',
		);
	}

	/**
	 * Check if command is available
	 *
	 * @param string $command Command to check.
	 * @return bool
	 */
	private function is_command_available( $command ) {
		$output = array();
		$return = 0;
		exec( 'which ' . escapeshellarg( $command ), $output, $return );
		return 0 === $return;
	}

	/**
	 * Check if text is readable
	 *
	 * @param string $text Text to check.
	 * @return bool
	 */
	private function is_readable_text( $text ) {
		// Check if text contains mostly printable characters
		$printable_count = preg_match_all( '/[[:print:]]/', $text );
		$total_count     = strlen( $text );

		if ( 0 === $total_count ) {
			return false;
		}

		$ratio = $printable_count / $total_count;
		return $ratio > 0.8;
	}

	/**
	 * Extract images from document
	 *
	 * @param string $file_path File path.
	 * @param string $mime_type MIME type.
	 * @return array
	 */
	public function extract_images( $file_path, $mime_type ) {
		$images = array();

		switch ( $mime_type ) {
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				$images = $this->extract_docx_images( $file_path );
				break;

			case 'application/pdf':
				// PDF image extraction would require additional libraries
				// For now, return empty array
				break;
		}

		return $images;
	}

	/**
	 * Extract images from DOCX
	 *
	 * @param string $file_path File path.
	 * @return array
	 */
	private function extract_docx_images( $file_path ) {
		$images = array();

		if ( ! class_exists( 'ZipArchive' ) ) {
			return $images;
		}

		$zip = new ZipArchive();
		if ( $zip->open( $file_path ) === true ) {
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$filename = $zip->getNameIndex( $i );
				if ( preg_match( '/word\/media\/(.+)$/', $filename, $matches ) ) {
					$images[] = array(
						'name' => $matches[1],
						'data' => $zip->getFromIndex( $i ),
					);
				}
			}
			$zip->close();
		}

		return $images;
	}
}