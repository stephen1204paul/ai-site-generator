<?php
/**
 * Template Curator
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Includes;

/**
 * Class Template_Curator
 *
 * Automatically curates quality templates, flags inappropriate content,
 * suggests featured templates, and calculates template scores.
 */
class Template_Curator {

    /**
     * Database handler instance
     *
     * @var \WP_AI_Site_Generator\Database\Template_DB_Handler
     */
    private $db_handler;

    /**
     * Spam keywords for content filtering
     *
     * @var array
     */
    private $spam_keywords = array(
        'viagra', 'cialis', 'casino', 'poker', 'gambling',
        'pharmacy', 'pills', 'xxx', 'porn', 'adult',
        'earn money fast', 'get rich quick', 'mlm',
        'bitcoin trader', 'forex', 'binary options',
    );

    /**
     * Quality indicators for templates
     *
     * @var array
     */
    private $quality_indicators = array(
        'has_description'     => 10,
        'has_screenshots'     => 15,
        'has_documentation'   => 20,
        'has_demo'           => 15,
        'has_support'        => 10,
        'proper_formatting'   => 10,
        'unique_content'     => 20,
    );

    /**
     * Trending score weights
     *
     * @var array
     */
    private $trending_weights = array(
        'recent_downloads'  => 0.3,
        'recent_installs'   => 0.25,
        'recent_ratings'    => 0.2,
        'recent_reviews'    => 0.15,
        'velocity'          => 0.1,
    );

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize database handler
        if ( class_exists( '\WP_AI_Site_Generator\Database\Template_DB_Handler' ) ) {
            $this->db_handler = new \WP_AI_Site_Generator\Database\Template_DB_Handler();
        }

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        add_action( 'wp_ai_template_submitted', array( $this, 'evaluate_submission' ), 10, 2 );
        add_action( 'wp_ai_daily_curation', array( $this, 'run_daily_curation' ) );
        add_action( 'wp_ai_hourly_curation', array( $this, 'run_hourly_curation' ) );
        add_filter( 'wp_ai_template_quality_score', array( $this, 'calculate_quality_score' ), 10, 2 );
    }

    /**
     * Evaluate template submission
     *
     * @param int   $template_id Template ID
     * @param array $template_data Template data
     */
    public function evaluate_submission( $template_id, $template_data ) {
        // Check for spam
        if ( $this->is_spam_content( $template_data ) ) {
            $this->flag_template( $template_id, 'spam', __( 'Content flagged as spam', 'wp-ai-site-generator' ) );
            return;
        }

        // Check for inappropriate content
        if ( $this->is_inappropriate_content( $template_data ) ) {
            $this->flag_template( $template_id, 'inappropriate', __( 'Content flagged as inappropriate', 'wp-ai-site-generator' ) );
            return;
        }

        // Check for duplicate content
        if ( $this->is_duplicate_content( $template_data ) ) {
            $this->flag_template( $template_id, 'duplicate', __( 'Duplicate content detected', 'wp-ai-site-generator' ) );
            return;
        }

        // Calculate initial quality score
        $quality_score = $this->calculate_quality_score( $template_id, $template_data );

        // Auto-approve if quality score is high enough
        if ( $quality_score >= 70 ) {
            $this->approve_template( $template_id );
        } elseif ( $quality_score < 30 ) {
            $this->flag_template( $template_id, 'low_quality', __( 'Low quality score', 'wp-ai-site-generator' ) );
        } else {
            // Mark for manual review
            $this->queue_for_review( $template_id, 'manual_review', __( 'Requires manual review', 'wp-ai-site-generator' ) );
        }
    }

    /**
     * Check if content is spam
     *
     * @param array $template_data Template data
     * @return bool
     */
    public function is_spam_content( $template_data ) {
        $content = strtolower(
            $template_data['title'] . ' ' .
            $template_data['description'] . ' ' .
            $template_data['content']
        );

        // Check for spam keywords
        foreach ( $this->spam_keywords as $keyword ) {
            if ( strpos( $content, $keyword ) !== false ) {
                return true;
            }
        }

        // Check for excessive links
        $link_count = substr_count( $content, 'http://' ) + substr_count( $content, 'https://' );
        if ( $link_count > 10 ) {
            return true;
        }

        // Check for repetitive content
        if ( $this->has_repetitive_content( $content ) ) {
            return true;
        }

        // Use Akismet if available
        if ( function_exists( 'akismet_check_comment' ) ) {
            $akismet_data = array(
                'comment_author'       => get_userdata( $template_data['author_id'] )->display_name,
                'comment_author_email' => get_userdata( $template_data['author_id'] )->user_email,
                'comment_content'      => $content,
                'comment_type'         => 'template',
            );

            $is_spam = akismet_check_comment( $akismet_data );
            if ( $is_spam ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content is inappropriate
     *
     * @param array $template_data Template data
     * @return bool
     */
    public function is_inappropriate_content( $template_data ) {
        $content = strtolower(
            $template_data['title'] . ' ' .
            $template_data['description'] . ' ' .
            $template_data['content']
        );

        // Check for profanity (basic check - can be enhanced with proper profanity filter)
        $inappropriate_words = apply_filters( 'wp_ai_inappropriate_words', array(
            // Add inappropriate words here
        ) );

        foreach ( $inappropriate_words as $word ) {
            if ( strpos( $content, $word ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content is duplicate
     *
     * @param array $template_data Template data
     * @return bool
     */
    public function is_duplicate_content( $template_data ) {
        global $wpdb;

        // Check for exact title match
        $similar_title = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => 1,
            'title'          => $template_data['title'],
            'post_status'    => 'any',
        ) );

        if ( ! empty( $similar_title ) ) {
            return true;
        }

        // Check for similar content using content hash
        $content_hash = md5( $template_data['content'] );
        $similar = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                WHERE meta_key = '_template_content_hash' AND meta_value = %s",
                $content_hash
            )
        );

        if ( $similar > 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Check for repetitive content
     *
     * @param string $content Content to check
     * @return bool
     */
    private function has_repetitive_content( $content ) {
        // Split into words
        $words = str_word_count( $content, 1 );
        $total_words = count( $words );

        if ( $total_words < 10 ) {
            return false;
        }

        // Count word frequency
        $word_counts = array_count_values( $words );

        // Check if any word appears too frequently
        foreach ( $word_counts as $word => $count ) {
            if ( strlen( $word ) > 3 && ( $count / $total_words ) > 0.1 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate quality score for template
     *
     * @param int   $template_id Template ID
     * @param array $template_data Template data
     * @return int Quality score (0-100)
     */
    public function calculate_quality_score( $template_id, $template_data = null ) {
        if ( null === $template_data ) {
            $template = get_post( $template_id );
            if ( ! $template ) {
                return 0;
            }

            $template_data = array(
                'title'             => $template->post_title,
                'description'       => $template->post_excerpt,
                'content'           => $template->post_content,
                'screenshots'       => get_post_meta( $template_id, '_template_screenshots', true ),
                'demo_url'          => get_post_meta( $template_id, '_template_demo_url', true ),
                'documentation_url' => get_post_meta( $template_id, '_template_documentation_url', true ),
                'support_url'       => get_post_meta( $template_id, '_template_support_url', true ),
            );
        }

        $score = 0;

        // Check description
        if ( ! empty( $template_data['description'] ) && strlen( $template_data['description'] ) > 50 ) {
            $score += $this->quality_indicators['has_description'];
        }

        // Check screenshots
        if ( ! empty( $template_data['screenshots'] ) && is_array( $template_data['screenshots'] ) ) {
            $score += $this->quality_indicators['has_screenshots'];
        }

        // Check documentation
        if ( ! empty( $template_data['documentation_url'] ) ) {
            $score += $this->quality_indicators['has_documentation'];
        }

        // Check demo
        if ( ! empty( $template_data['demo_url'] ) ) {
            $score += $this->quality_indicators['has_demo'];
        }

        // Check support
        if ( ! empty( $template_data['support_url'] ) ) {
            $score += $this->quality_indicators['has_support'];
        }

        // Check formatting
        if ( $this->has_proper_formatting( $template_data['content'] ) ) {
            $score += $this->quality_indicators['proper_formatting'];
        }

        // Check uniqueness
        if ( $this->has_unique_content( $template_data ) ) {
            $score += $this->quality_indicators['unique_content'];
        }

        // Store quality score
        if ( $template_id ) {
            update_post_meta( $template_id, '_template_quality_score', $score );
        }

        return $score;
    }

    /**
     * Check if content has proper formatting
     *
     * @param string $content Content to check
     * @return bool
     */
    private function has_proper_formatting( $content ) {
        // Check for basic structure
        $has_structure = false;

        // Check if it's valid JSON (for template configuration)
        $json_decoded = json_decode( $content, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $has_structure = true;
        }

        // Check for minimum content length
        if ( strlen( $content ) < 100 ) {
            return false;
        }

        return $has_structure;
    }

    /**
     * Check if content is unique
     *
     * @param array $template_data Template data
     * @return bool
     */
    private function has_unique_content( $template_data ) {
        // Calculate similarity with existing templates
        $similar_templates = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_template_category',
                    'value'   => $template_data['category'] ?? '',
                    'compare' => '=',
                ),
            ),
        ) );

        foreach ( $similar_templates as $template ) {
            $similarity = $this->calculate_similarity(
                $template_data['content'],
                $template->post_content
            );

            if ( $similarity > 0.8 ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate similarity between two strings
     *
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0-1)
     */
    private function calculate_similarity( $str1, $str2 ) {
        $percent = 0;
        similar_text( $str1, $str2, $percent );
        return $percent / 100;
    }

    /**
     * Calculate trending score for template
     *
     * @param int $template_id Template ID
     * @return float Trending score
     */
    public function calculate_trending_score( $template_id ) {
        if ( ! $this->db_handler ) {
            return 0;
        }

        // Get recent statistics (last 7 days)
        $recent_stats = $this->db_handler->get_template_stats( $template_id, 'week' );

        if ( empty( $recent_stats ) ) {
            return 0;
        }

        $score = 0;
        $total_downloads = 0;
        $total_installs = 0;
        $total_ratings = 0;
        $total_reviews = 0;

        foreach ( $recent_stats as $stat ) {
            $total_downloads += $stat->downloads;
            $total_installs  += $stat->installs;
            $total_ratings   += $stat->ratings_added;
            $total_reviews   += $stat->reviews_added;
        }

        // Calculate velocity (growth rate)
        $velocity = 0;
        if ( count( $recent_stats ) > 1 ) {
            $first_half = array_slice( $recent_stats, 0, floor( count( $recent_stats ) / 2 ) );
            $second_half = array_slice( $recent_stats, floor( count( $recent_stats ) / 2 ) );

            $first_half_downloads = array_sum( array_column( $first_half, 'downloads' ) );
            $second_half_downloads = array_sum( array_column( $second_half, 'downloads' ) );

            if ( $first_half_downloads > 0 ) {
                $velocity = ( $second_half_downloads - $first_half_downloads ) / $first_half_downloads;
            }
        }

        // Calculate weighted score
        $score = (
            $total_downloads * $this->trending_weights['recent_downloads'] +
            $total_installs * $this->trending_weights['recent_installs'] +
            $total_ratings * $this->trending_weights['recent_ratings'] * 10 +
            $total_reviews * $this->trending_weights['recent_reviews'] * 5 +
            $velocity * 100 * $this->trending_weights['velocity']
        );

        // Factor in overall rating
        $rating = floatval( get_post_meta( $template_id, '_template_rating', true ) );
        if ( $rating > 0 ) {
            $score *= ( $rating / 5 );
        }

        return round( $score, 2 );
    }

    /**
     * Update trending score for template
     *
     * @param int $template_id Template ID
     */
    public function update_trending_score( $template_id ) {
        $score = $this->calculate_trending_score( $template_id );
        update_post_meta( $template_id, '_template_trending_score', $score );

        // Update database if handler available
        if ( $this->db_handler ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ai_marketplace_templates',
                array( 'trending_score' => $score ),
                array( 'post_id' => $template_id ),
                array( '%f' ),
                array( '%d' )
            );
        }
    }

    /**
     * Calculate trending scores for all templates
     */
    public function calculate_trending_scores() {
        $templates = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_template_status',
                    'value'   => 'approved',
                    'compare' => '=',
                ),
            ),
        ) );

        foreach ( $templates as $template ) {
            $this->update_trending_score( $template->ID );
        }
    }

    /**
     * Suggest featured templates
     *
     * @param int $limit Number of templates to suggest
     * @return array Suggested template IDs
     */
    public function suggest_featured_templates( $limit = 6 ) {
        global $wpdb;

        // Get high-quality, popular templates
        $templates = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => $limit * 2, // Get more to filter
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_template_status',
                    'value'   => 'approved',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_template_quality_score',
                    'value'   => 70,
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => '_template_rating',
                    'value'   => 4,
                    'compare' => '>=',
                    'type'    => 'DECIMAL',
                ),
            ),
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_template_downloads',
            'order'          => 'DESC',
        ) );

        $suggestions = array();

        foreach ( $templates as $template ) {
            // Additional criteria for featuring
            $downloads = intval( get_post_meta( $template->ID, '_template_downloads', true ) );
            $rating    = floatval( get_post_meta( $template->ID, '_template_rating', true ) );
            $reviews   = intval( get_post_meta( $template->ID, '_template_rating_count', true ) );

            // Must have minimum engagement
            if ( $downloads >= 100 && $rating >= 4.0 && $reviews >= 5 ) {
                $suggestions[] = $template->ID;
            }

            if ( count( $suggestions ) >= $limit ) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * Flag template for review
     *
     * @param int    $template_id Template ID
     * @param string $reason Flagging reason
     * @param string $details Additional details
     */
    public function flag_template( $template_id, $reason, $details = '' ) {
        // Update template status
        update_post_meta( $template_id, '_template_status', 'flagged' );
        update_post_meta( $template_id, '_flag_reason', $reason );
        update_post_meta( $template_id, '_flag_details', $details );
        update_post_meta( $template_id, '_flag_date', current_time( 'mysql' ) );

        // Add to moderation queue
        if ( $this->db_handler ) {
            $priority = $reason === 'spam' ? 10 : 5;
            $this->db_handler->add_to_moderation( $template_id, 'review_flag', $details, $priority );
        }

        // Notify administrators
        $this->notify_moderators( $template_id, $reason, $details );
    }

    /**
     * Queue template for review
     *
     * @param int    $template_id Template ID
     * @param string $type Review type
     * @param string $reason Review reason
     */
    public function queue_for_review( $template_id, $type, $reason = '' ) {
        update_post_meta( $template_id, '_template_status', 'pending' );

        if ( $this->db_handler ) {
            $this->db_handler->add_to_moderation( $template_id, $type, $reason, 3 );
        }
    }

    /**
     * Approve template
     *
     * @param int $template_id Template ID
     */
    public function approve_template( $template_id ) {
        // Update post status
        wp_update_post( array(
            'ID'          => $template_id,
            'post_status' => 'publish',
        ) );

        // Update template status
        update_post_meta( $template_id, '_template_status', 'approved' );
        update_post_meta( $template_id, '_approved_date', current_time( 'mysql' ) );
        update_post_meta( $template_id, '_approved_by', get_current_user_id() );

        // Calculate and store content hash for duplicate detection
        $template = get_post( $template_id );
        if ( $template ) {
            $content_hash = md5( $template->post_content );
            update_post_meta( $template_id, '_template_content_hash', $content_hash );
        }

        // Notify author
        $this->notify_author( $template_id, 'approved' );
    }

    /**
     * Reject template
     *
     * @param int    $template_id Template ID
     * @param string $reason Rejection reason
     */
    public function reject_template( $template_id, $reason ) {
        // Update post status
        wp_update_post( array(
            'ID'          => $template_id,
            'post_status' => 'draft',
        ) );

        // Update template status
        update_post_meta( $template_id, '_template_status', 'rejected' );
        update_post_meta( $template_id, '_rejection_reason', $reason );
        update_post_meta( $template_id, '_rejected_date', current_time( 'mysql' ) );
        update_post_meta( $template_id, '_rejected_by', get_current_user_id() );

        // Notify author
        $this->notify_author( $template_id, 'rejected', $reason );
    }

    /**
     * Moderate pending templates
     */
    public function moderate_pending_templates() {
        if ( ! $this->db_handler ) {
            return;
        }

        // Get pending items from moderation queue
        $items = $this->db_handler->get_moderation_queue( array(
            'status' => 'pending',
            'limit'  => 20,
        ) );

        foreach ( $items as $item ) {
            $template_id = $item->template_id;

            // Re-evaluate template
            $template = get_post( $template_id );
            if ( ! $template ) {
                // Template deleted, resolve moderation item
                $this->db_handler->resolve_moderation( $item->id, 'resolved', 'Template no longer exists' );
                continue;
            }

            $template_data = array(
                'title'       => $template->post_title,
                'description' => $template->post_excerpt,
                'content'     => $template->post_content,
                'author_id'   => $template->post_author,
            );

            // Re-check for spam
            if ( $this->is_spam_content( $template_data ) ) {
                $this->reject_template( $template_id, __( 'Spam content detected', 'wp-ai-site-generator' ) );
                $this->db_handler->resolve_moderation( $item->id, 'rejected', 'Spam detected' );
                continue;
            }

            // Re-calculate quality score
            $quality_score = $this->calculate_quality_score( $template_id );

            if ( $quality_score >= 60 ) {
                // Auto-approve improved templates
                $this->approve_template( $template_id );
                $this->db_handler->resolve_moderation( $item->id, 'approved', 'Auto-approved based on quality score' );
            } elseif ( $quality_score < 30 ) {
                // Reject very low quality templates
                $this->reject_template( $template_id, __( 'Quality standards not met', 'wp-ai-site-generator' ) );
                $this->db_handler->resolve_moderation( $item->id, 'rejected', 'Low quality score' );
            }
            // Templates with scores between 30-60 remain in queue for manual review
        }
    }

    /**
     * Run daily curation tasks
     */
    public function run_daily_curation() {
        // Update trending scores
        $this->calculate_trending_scores();

        // Update featured templates suggestions
        $featured = $this->suggest_featured_templates();
        update_option( 'wp_ai_suggested_featured_templates', $featured );

        // Clean up old flagged templates
        $this->cleanup_old_flags();

        // Generate curation report
        $this->generate_curation_report();
    }

    /**
     * Run hourly curation tasks
     */
    public function run_hourly_curation() {
        // Process moderation queue
        $this->moderate_pending_templates();

        // Check for suspicious activity
        $this->detect_suspicious_activity();
    }

    /**
     * Clean up old flagged templates
     */
    private function cleanup_old_flags() {
        // Remove flags older than 30 days if template was improved
        $old_flags = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_template_status',
                    'value'   => 'flagged',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_flag_date',
                    'value'   => date( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
        ) );

        foreach ( $old_flags as $template ) {
            // Re-evaluate
            $quality_score = $this->calculate_quality_score( $template->ID );
            if ( $quality_score >= 50 ) {
                // Clear flag and queue for review
                delete_post_meta( $template->ID, '_flag_reason' );
                delete_post_meta( $template->ID, '_flag_details' );
                delete_post_meta( $template->ID, '_flag_date' );
                $this->queue_for_review( $template->ID, 'flag_cleared', 'Flag cleared after improvement' );
            }
        }
    }

    /**
     * Detect suspicious activity
     */
    private function detect_suspicious_activity() {
        global $wpdb;

        // Check for users submitting too many templates
        $recent_submissions = $wpdb->get_results(
            "SELECT post_author, COUNT(*) as count
            FROM {$wpdb->posts}
            WHERE post_type = 'ai_marketplace_tpl'
                AND post_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY post_author
            HAVING count > 5"
        );

        foreach ( $recent_submissions as $submission ) {
            // Flag user's recent templates for review
            $user_templates = get_posts( array(
                'post_type'      => 'ai_marketplace_tpl',
                'posts_per_page' => -1,
                'author'         => $submission->post_author,
                'date_query'     => array(
                    array(
                        'after' => '1 hour ago',
                    ),
                ),
            ) );

            foreach ( $user_templates as $template ) {
                $this->flag_template( $template->ID, 'suspicious_activity', 'Too many submissions in short period' );
            }
        }
    }

    /**
     * Generate curation report
     */
    private function generate_curation_report() {
        $report = array(
            'date'              => current_time( 'Y-m-d' ),
            'total_templates'   => wp_count_posts( 'ai_marketplace_tpl' )->publish,
            'pending_review'    => wp_count_posts( 'ai_marketplace_tpl' )->pending,
            'flagged'          => $this->count_flagged_templates(),
            'approved_today'   => $this->count_approved_today(),
            'rejected_today'   => $this->count_rejected_today(),
            'trending_updated' => count( $this->get_trending_templates() ),
            'featured_suggestions' => get_option( 'wp_ai_suggested_featured_templates', array() ),
        );

        // Store report
        update_option( 'wp_ai_curation_report_' . current_time( 'Y-m-d' ), $report );

        // Send report to admins if configured
        if ( apply_filters( 'wp_ai_send_curation_report', false ) ) {
            $this->send_curation_report( $report );
        }
    }

    /**
     * Count flagged templates
     *
     * @return int
     */
    private function count_flagged_templates() {
        $count = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => array(
                array(
                    'key'     => '_template_status',
                    'value'   => 'flagged',
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ) );

        return count( $count );
    }

    /**
     * Count templates approved today
     *
     * @return int
     */
    private function count_approved_today() {
        $count = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_approved_date',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ) );

        return count( $count );
    }

    /**
     * Count templates rejected today
     *
     * @return int
     */
    private function count_rejected_today() {
        $count = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => -1,
            'post_status'    => 'draft',
            'meta_query'     => array(
                array(
                    'key'     => '_rejected_date',
                    'value'   => current_time( 'Y-m-d' ),
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ) );

        return count( $count );
    }

    /**
     * Get trending templates
     *
     * @return array
     */
    private function get_trending_templates() {
        return get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
            'meta_key'       => '_template_trending_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );
    }

    /**
     * Notify moderators about flagged template
     *
     * @param int    $template_id Template ID
     * @param string $reason Flag reason
     * @param string $details Additional details
     */
    private function notify_moderators( $template_id, $reason, $details ) {
        $template = get_post( $template_id );
        if ( ! $template ) {
            return;
        }

        $moderators = get_users( array(
            'role' => 'administrator',
        ) );

        $subject = sprintf(
            __( '[%s] Template Flagged for Review: %s', 'wp-ai-site-generator' ),
            get_bloginfo( 'name' ),
            $template->post_title
        );

        $message = sprintf(
            __( "A template has been flagged for review.\n\nTemplate: %s\nReason: %s\nDetails: %s\n\nReview URL: %s", 'wp-ai-site-generator' ),
            $template->post_title,
            $reason,
            $details,
            admin_url( 'admin.php?page=wp-ai-marketplace-moderation&template=' . $template_id )
        );

        foreach ( $moderators as $moderator ) {
            wp_mail( $moderator->user_email, $subject, $message );
        }
    }

    /**
     * Notify author about template status
     *
     * @param int    $template_id Template ID
     * @param string $status Template status
     * @param string $reason Optional reason
     */
    private function notify_author( $template_id, $status, $reason = '' ) {
        $template = get_post( $template_id );
        if ( ! $template ) {
            return;
        }

        $author = get_userdata( $template->post_author );
        if ( ! $author ) {
            return;
        }

        $subject_map = array(
            'approved' => __( 'Your template has been approved', 'wp-ai-site-generator' ),
            'rejected' => __( 'Your template needs revision', 'wp-ai-site-generator' ),
        );

        $subject = sprintf(
            '[%s] %s: %s',
            get_bloginfo( 'name' ),
            $subject_map[ $status ],
            $template->post_title
        );

        if ( $status === 'approved' ) {
            $message = sprintf(
                __( "Great news! Your template '%s' has been approved and is now available in the marketplace.\n\nView your template: %s", 'wp-ai-site-generator' ),
                $template->post_title,
                get_permalink( $template_id )
            );
        } else {
            $message = sprintf(
                __( "Your template '%s' needs some revisions before it can be published.\n\nReason: %s\n\nPlease update your template and resubmit for review.\n\nEdit template: %s", 'wp-ai-site-generator' ),
                $template->post_title,
                $reason,
                admin_url( 'admin.php?page=wp-ai-marketplace&action=edit&template=' . $template_id )
            );
        }

        wp_mail( $author->user_email, $subject, $message );
    }

    /**
     * Send curation report to admins
     *
     * @param array $report Report data
     */
    private function send_curation_report( $report ) {
        $admins = get_users( array(
            'role' => 'administrator',
        ) );

        $subject = sprintf(
            '[%s] Daily Template Curation Report - %s',
            get_bloginfo( 'name' ),
            $report['date']
        );

        $message = "Daily Template Marketplace Curation Report\n";
        $message .= "=====================================\n\n";
        $message .= sprintf( "Total Published Templates: %d\n", $report['total_templates'] );
        $message .= sprintf( "Pending Review: %d\n", $report['pending_review'] );
        $message .= sprintf( "Flagged Templates: %d\n", $report['flagged'] );
        $message .= sprintf( "Approved Today: %d\n", $report['approved_today'] );
        $message .= sprintf( "Rejected Today: %d\n", $report['rejected_today'] );
        $message .= sprintf( "Trending Templates Updated: %d\n", $report['trending_updated'] );
        $message .= sprintf( "Featured Suggestions: %d\n\n", count( $report['featured_suggestions'] ) );
        $message .= sprintf( "View full report: %s\n", admin_url( 'admin.php?page=wp-ai-marketplace-reports' ) );

        foreach ( $admins as $admin ) {
            wp_mail( $admin->user_email, $subject, $message );
        }
    }

    /**
     * Get template recommendations for user
     *
     * @param int $user_id User ID
     * @param int $limit Number of recommendations
     * @return array Recommended template IDs
     */
    public function get_recommendations( $user_id, $limit = 6 ) {
        // Get user's installed templates
        $installed = get_user_meta( $user_id, 'wp_ai_installed_templates', true );
        if ( ! is_array( $installed ) ) {
            $installed = array();
        }

        // Get user's favorite templates
        if ( $this->db_handler ) {
            $favorites = $this->db_handler->get_user_favorites( $user_id );
        } else {
            $favorites = array();
        }

        // Analyze user preferences
        $preferred_categories = array();
        $preferred_industries = array();

        foreach ( array_merge( $installed, $favorites ) as $template_id ) {
            $categories = wp_get_post_terms( $template_id, 'ai_template_category', array( 'fields' => 'slugs' ) );
            $industries = wp_get_post_terms( $template_id, 'ai_template_industry', array( 'fields' => 'slugs' ) );

            $preferred_categories = array_merge( $preferred_categories, $categories );
            $preferred_industries = array_merge( $preferred_industries, $industries );
        }

        $preferred_categories = array_unique( $preferred_categories );
        $preferred_industries = array_unique( $preferred_industries );

        // Get recommendations based on preferences
        $recommendations = get_posts( array(
            'post_type'      => 'ai_marketplace_tpl',
            'posts_per_page' => $limit * 2,
            'post_status'    => 'publish',
            'post__not_in'   => array_merge( $installed, $favorites ),
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_template_status',
                    'value'   => 'approved',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_template_rating',
                    'value'   => 3.5,
                    'compare' => '>=',
                    'type'    => 'DECIMAL',
                ),
            ),
            'tax_query' => array(
                'relation' => 'OR',
                array(
                    'taxonomy' => 'ai_template_category',
                    'field'    => 'slug',
                    'terms'    => $preferred_categories,
                ),
                array(
                    'taxonomy' => 'ai_template_industry',
                    'field'    => 'slug',
                    'terms'    => $preferred_industries,
                ),
            ),
            'orderby' => 'meta_value_num',
            'meta_key' => '_template_trending_score',
            'order' => 'DESC',
        ) );

        $recommendation_ids = array();
        foreach ( $recommendations as $template ) {
            $recommendation_ids[] = $template->ID;
            if ( count( $recommendation_ids ) >= $limit ) {
                break;
            }
        }

        // If not enough recommendations, get popular templates
        if ( count( $recommendation_ids ) < $limit ) {
            $popular = get_posts( array(
                'post_type'      => 'ai_marketplace_tpl',
                'posts_per_page' => $limit - count( $recommendation_ids ),
                'post_status'    => 'publish',
                'post__not_in'   => array_merge( $installed, $favorites, $recommendation_ids ),
                'meta_query'     => array(
                    array(
                        'key'     => '_template_status',
                        'value'   => 'approved',
                        'compare' => '=',
                    ),
                ),
                'orderby' => 'meta_value_num',
                'meta_key' => '_template_downloads',
                'order' => 'DESC',
            ) );

            foreach ( $popular as $template ) {
                $recommendation_ids[] = $template->ID;
            }
        }

        return $recommendation_ids;
    }
}