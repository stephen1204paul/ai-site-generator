<?php
/**
 * Feedback Database Handler
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Database;

/**
 * Feedback Database Handler Class
 */
class Feedback_DB_Handler {

    /**
     * WordPress database instance
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Table names
     *
     * @var array
     */
    private $tables = [
        'feedback' => 'wp_ai_feedback',
        'feedback_ratings' => 'wp_ai_feedback_ratings',
        'quick_feedback' => 'wp_ai_quick_feedback',
        'issues' => 'wp_ai_issues',
        'suggestions' => 'wp_ai_suggestions',
        'feedback_stats' => 'wp_ai_feedback_stats',
        'ab_tests' => 'wp_ai_ab_tests',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Set table names with prefix
        foreach ($this->tables as $key => $table) {
            $this->tables[$key] = $wpdb->prefix . 'ai_' . $key;
        }
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        // Main feedback table
        $sql_feedback = "CREATE TABLE {$this->tables['feedback']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            generation_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            text_feedback text,
            categories text,
            section_id varchar(100),
            is_anonymous tinyint(1) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_generation (generation_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";

        // Feedback ratings table (normalized for flexibility)
        $sql_ratings = "CREATE TABLE {$this->tables['feedback_ratings']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            feedback_id bigint(20) unsigned NOT NULL,
            dimension varchar(50) NOT NULL,
            rating tinyint(1) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_feedback (feedback_id),
            INDEX idx_dimension (dimension),
            INDEX idx_rating (rating)
        ) $charset_collate;";

        // Quick feedback table
        $sql_quick = "CREATE TABLE {$this->tables['quick_feedback']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            generation_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            value tinyint(2) NOT NULL,
            section_id varchar(100),
            ip_hash varchar(64),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_generation (generation_id),
            INDEX idx_value (value),
            INDEX idx_timestamp (timestamp)
        ) $charset_collate;";

        // Issues table
        $sql_issues = "CREATE TABLE {$this->tables['issues']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            generation_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            issue_type varchar(50) NOT NULL,
            severity varchar(20) DEFAULT 'medium',
            description text NOT NULL,
            affected_section varchar(100),
            steps_to_reproduce text,
            expected_behavior text,
            actual_behavior text,
            status varchar(20) DEFAULT 'open',
            resolution text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_at datetime,
            PRIMARY KEY (id),
            INDEX idx_generation (generation_id),
            INDEX idx_type (issue_type),
            INDEX idx_severity (severity),
            INDEX idx_status (status)
        ) $charset_collate;";

        // Feature suggestions table
        $sql_suggestions = "CREATE TABLE {$this->tables['suggestions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT 0,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            category varchar(50),
            priority varchar(20) DEFAULT 'medium',
            use_case text,
            status varchar(20) DEFAULT 'pending',
            votes int unsigned DEFAULT 0,
            implementation_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            implemented_at datetime,
            PRIMARY KEY (id),
            INDEX idx_user (user_id),
            INDEX idx_category (category),
            INDEX idx_priority (priority),
            INDEX idx_status (status),
            INDEX idx_votes (votes)
        ) $charset_collate;";

        // Aggregated feedback statistics table
        $sql_stats = "CREATE TABLE {$this->tables['feedback_stats']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            generation_id bigint(20) unsigned NOT NULL,
            total_feedback int unsigned DEFAULT 0,
            avg_overall_rating decimal(3,2),
            avg_quality_rating decimal(3,2),
            avg_accuracy_rating decimal(3,2),
            avg_relevance_rating decimal(3,2),
            avg_usefulness_rating decimal(3,2),
            positive_quick int unsigned DEFAULT 0,
            negative_quick int unsigned DEFAULT 0,
            issues_count int unsigned DEFAULT 0,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_generation (generation_id),
            INDEX idx_ratings (avg_overall_rating, avg_quality_rating)
        ) $charset_collate;";

        // A/B testing table
        $sql_ab_tests = "CREATE TABLE {$this->tables['ab_tests']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            test_name varchar(100) NOT NULL,
            variant_a text NOT NULL,
            variant_b text NOT NULL,
            variant_a_count int unsigned DEFAULT 0,
            variant_b_count int unsigned DEFAULT 0,
            variant_a_rating decimal(3,2),
            variant_b_rating decimal(3,2),
            winner varchar(1),
            status varchar(20) DEFAULT 'active',
            confidence_level decimal(5,2),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime,
            PRIMARY KEY (id),
            INDEX idx_status (status),
            INDEX idx_test_name (test_name)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_feedback);
        dbDelta($sql_ratings);
        dbDelta($sql_quick);
        dbDelta($sql_issues);
        dbDelta($sql_suggestions);
        dbDelta($sql_stats);
        dbDelta($sql_ab_tests);

        // Store database version
        update_option('wp_ai_site_generator_feedback_db_version', '1.0.0');
    }

    /**
     * Insert feedback record
     *
     * @param array $data Feedback data
     * @return int|false Feedback ID or false on failure
     */
    public function insert_feedback($data) {
        // Insert main feedback record
        $feedback_result = $this->wpdb->insert(
            $this->tables['feedback'],
            [
                'generation_id' => $data['generation_id'],
                'user_id' => $data['user_id'],
                'text_feedback' => $data['text_feedback'],
                'categories' => maybe_serialize($data['categories']),
                'section_id' => $data['section_id'],
                'is_anonymous' => $data['is_anonymous'],
                'metadata' => wp_json_encode($data['metadata']),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        if ($feedback_result === false) {
            return false;
        }

        $feedback_id = $this->wpdb->insert_id;

        // Insert individual ratings
        foreach ($data['ratings'] as $dimension => $rating) {
            if ($rating !== null) {
                $this->wpdb->insert(
                    $this->tables['feedback_ratings'],
                    [
                        'feedback_id' => $feedback_id,
                        'dimension' => $dimension,
                        'rating' => $rating,
                    ],
                    ['%d', '%s', '%d']
                );
            }
        }

        // Update statistics
        $this->update_feedback_statistics($data['generation_id']);

        return $feedback_id;
    }

    /**
     * Insert quick feedback
     *
     * @param array $data Quick feedback data
     * @return int|false Quick feedback ID or false
     */
    public function insert_quick_feedback($data) {
        $result = $this->wpdb->insert(
            $this->tables['quick_feedback'],
            [
                'generation_id' => $data['generation_id'],
                'user_id' => $data['user_id'],
                'value' => $data['value'],
                'section_id' => $data['section_id'],
                'ip_hash' => $data['ip_hash'],
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );

        if ($result) {
            $this->update_quick_feedback_statistics($data['generation_id']);
            return $this->wpdb->insert_id;
        }

        return false;
    }

    /**
     * Insert issue report
     *
     * @param array $data Issue data
     * @return int|false Issue ID or false
     */
    public function insert_issue_report($data) {
        $result = $this->wpdb->insert(
            $this->tables['issues'],
            [
                'generation_id' => $data['generation_id'],
                'user_id' => $data['user_id'],
                'issue_type' => $data['issue_type'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'affected_section' => $data['affected_section'],
                'steps_to_reproduce' => $data['steps_to_reproduce'],
                'expected_behavior' => $data['expected_behavior'],
                'actual_behavior' => $data['actual_behavior'],
                'metadata' => wp_json_encode($data['metadata']),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            $this->increment_issue_count($data['generation_id']);
            return $this->wpdb->insert_id;
        }

        return false;
    }

    /**
     * Insert feature suggestion
     *
     * @param array $data Suggestion data
     * @return int|false Suggestion ID or false
     */
    public function insert_feature_suggestion($data) {
        $result = $this->wpdb->insert(
            $this->tables['suggestions'],
            [
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'use_case' => $data['use_case'],
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get feedback statistics for a generation
     *
     * @param int $generation_id Generation ID
     * @return array Statistics
     */
    public function get_feedback_stats($generation_id) {
        // Get cached stats first
        $cached_stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['feedback_stats']} WHERE generation_id = %d",
                $generation_id
            ),
            ARRAY_A
        );

        if ($cached_stats) {
            // Add additional computed stats
            $cached_stats['distribution'] = $this->get_rating_distribution($generation_id);
            $cached_stats['top_categories'] = $this->get_top_feedback_categories($generation_id);
            $cached_stats['text_feedback'] = $this->get_text_feedback($generation_id);

            return $cached_stats;
        }

        // Calculate stats if not cached
        return $this->calculate_feedback_stats($generation_id);
    }

    /**
     * Get aggregated insights for a period
     *
     * @param string $period Time period
     * @param string $type Generation type
     * @return array Insights
     */
    public function get_aggregated_insights($period, $type) {
        $date_from = $this->calculate_date_from($period);

        // Get overall statistics
        $overall_stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    COUNT(DISTINCT f.generation_id) as total_generations,
                    COUNT(f.id) as total_feedback,
                    AVG(r.rating) as avg_rating
                FROM {$this->tables['feedback']} f
                LEFT JOIN {$this->tables['feedback_ratings']} r ON f.id = r.feedback_id
                WHERE f.created_at >= %s AND r.dimension = 'overall'",
                $date_from
            ),
            ARRAY_A
        );

        // Get rating history for trends
        $rating_history = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    DATE(f.created_at) as date,
                    AVG(r.rating) as avg_rating
                FROM {$this->tables['feedback']} f
                LEFT JOIN {$this->tables['feedback_ratings']} r ON f.id = r.feedback_id
                WHERE f.created_at >= %s AND r.dimension = 'overall'
                GROUP BY DATE(f.created_at)
                ORDER BY date ASC",
                $date_from
            ),
            ARRAY_A
        );

        // Get top performing prompts
        $top_prompts = $this->wpdb->get_results(
            "SELECT
                g.prompt_template,
                AVG(s.avg_overall_rating) as avg_rating,
                COUNT(DISTINCT g.id) as usage_count
            FROM {$this->wpdb->prefix}ai_generations g
            LEFT JOIN {$this->tables['feedback_stats']} s ON g.id = s.generation_id
            WHERE s.avg_overall_rating IS NOT NULL
            GROUP BY g.prompt_template
            ORDER BY avg_rating DESC
            LIMIT 10",
            ARRAY_A
        );

        // Get common issues
        $common_issues = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    issue_type,
                    COUNT(*) as count,
                    AVG(CASE severity
                        WHEN 'critical' THEN 3
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 1
                        ELSE 0
                    END) as avg_severity
                FROM {$this->tables['issues']}
                WHERE created_at >= %s
                GROUP BY issue_type
                ORDER BY count DESC
                LIMIT 10",
                $date_from
            ),
            ARRAY_A
        );

        // Get top feature requests
        $top_feature_requests = $this->wpdb->get_results(
            "SELECT
                title,
                category,
                votes,
                priority
            FROM {$this->tables['suggestions']}
            WHERE status = 'pending'
            ORDER BY votes DESC, created_at DESC
            LIMIT 10",
            ARRAY_A
        );

        // Get quick feedback ratio
        $quick_feedback = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN value > 0 THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN value < 0 THEN 1 ELSE 0 END) as negative,
                    COUNT(*) as total
                FROM {$this->tables['quick_feedback']}
                WHERE timestamp >= %s",
                $date_from
            ),
            ARRAY_A
        );

        return [
            'overall_stats' => $overall_stats,
            'rating_history' => array_column($rating_history, 'avg_rating'),
            'top_prompts' => $top_prompts,
            'common_issues' => $common_issues,
            'top_feature_requests' => $top_feature_requests,
            'quick_feedback' => $quick_feedback,
            'average_ratings' => $this->get_average_ratings_by_dimension($date_from),
            'issue_rate' => $this->calculate_issue_rate($date_from),
            'repeat_usage_rate' => $this->calculate_repeat_usage_rate($date_from),
        ];
    }

    /**
     * Update generation statistics
     *
     * @param int $generation_id Generation ID
     * @param array $ratings Ratings
     */
    public function update_generation_stats($generation_id, $ratings) {
        $this->update_feedback_statistics($generation_id);
    }

    /**
     * Update quick feedback statistics
     *
     * @param int $generation_id Generation ID
     * @param bool $is_positive Positive feedback
     */
    public function update_quick_feedback_stats($generation_id, $is_positive) {
        $this->update_quick_feedback_statistics($generation_id);
    }

    /**
     * Update feedback statistics for a generation
     *
     * @param int $generation_id Generation ID
     */
    private function update_feedback_statistics($generation_id) {
        // Calculate average ratings
        $avg_ratings = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    r.dimension,
                    AVG(r.rating) as avg_rating
                FROM {$this->tables['feedback']} f
                JOIN {$this->tables['feedback_ratings']} r ON f.id = r.feedback_id
                WHERE f.generation_id = %d
                GROUP BY r.dimension",
                $generation_id
            ),
            ARRAY_A
        );

        $rating_data = [];
        foreach ($avg_ratings as $rating) {
            $rating_data['avg_' . $rating['dimension'] . '_rating'] = $rating['avg_rating'];
        }

        // Count total feedback
        $total_feedback = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['feedback']} WHERE generation_id = %d",
                $generation_id
            )
        );

        // Count issues
        $issues_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tables['issues']} WHERE generation_id = %d",
                $generation_id
            )
        );

        // Update or insert stats
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->tables['feedback_stats']} WHERE generation_id = %d",
                $generation_id
            )
        );

        $stats_data = array_merge($rating_data, [
            'generation_id' => $generation_id,
            'total_feedback' => $total_feedback,
            'issues_count' => $issues_count,
        ]);

        if ($existing) {
            $this->wpdb->update(
                $this->tables['feedback_stats'],
                $stats_data,
                ['generation_id' => $generation_id]
            );
        } else {
            $this->wpdb->insert(
                $this->tables['feedback_stats'],
                $stats_data
            );
        }
    }

    /**
     * Update quick feedback statistics
     *
     * @param int $generation_id Generation ID
     */
    private function update_quick_feedback_statistics($generation_id) {
        // Count positive and negative feedback
        $counts = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN value > 0 THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN value < 0 THEN 1 ELSE 0 END) as negative
                FROM {$this->tables['quick_feedback']}
                WHERE generation_id = %d",
                $generation_id
            ),
            ARRAY_A
        );

        // Update stats
        $this->wpdb->update(
            $this->tables['feedback_stats'],
            [
                'positive_quick' => $counts['positive'] ?? 0,
                'negative_quick' => $counts['negative'] ?? 0,
            ],
            ['generation_id' => $generation_id]
        );
    }

    /**
     * Get rating distribution
     *
     * @param int $generation_id Generation ID
     * @return array Distribution
     */
    private function get_rating_distribution($generation_id) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    r.rating,
                    COUNT(*) as count
                FROM {$this->tables['feedback']} f
                JOIN {$this->tables['feedback_ratings']} r ON f.id = r.feedback_id
                WHERE f.generation_id = %d AND r.dimension = 'overall'
                GROUP BY r.rating
                ORDER BY r.rating",
                $generation_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get top feedback categories
     *
     * @param int $generation_id Generation ID
     * @return array Categories
     */
    private function get_top_feedback_categories($generation_id) {
        $feedbacks = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT categories FROM {$this->tables['feedback']} WHERE generation_id = %d",
                $generation_id
            ),
            ARRAY_A
        );

        $category_counts = [];
        foreach ($feedbacks as $feedback) {
            $categories = maybe_unserialize($feedback['categories']);
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    $category_counts[$category] = ($category_counts[$category] ?? 0) + 1;
                }
            }
        }

        arsort($category_counts);
        return array_slice($category_counts, 0, 5, true);
    }

    /**
     * Get text feedback for a generation
     *
     * @param int $generation_id Generation ID
     * @return array Text feedback
     */
    private function get_text_feedback($generation_id) {
        return $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT text_feedback FROM {$this->tables['feedback']}
                WHERE generation_id = %d AND text_feedback IS NOT NULL AND text_feedback != ''",
                $generation_id
            )
        );
    }

    /**
     * Calculate feedback statistics
     *
     * @param int $generation_id Generation ID
     * @return array Statistics
     */
    private function calculate_feedback_stats($generation_id) {
        // This would calculate stats on demand if not cached
        $this->update_feedback_statistics($generation_id);
        return $this->get_feedback_stats($generation_id);
    }

    /**
     * Calculate date from period
     *
     * @param string $period Period string
     * @return string Date string
     */
    private function calculate_date_from($period) {
        switch ($period) {
            case '7days':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            case '90days':
                return date('Y-m-d H:i:s', strtotime('-90 days'));
            case '1year':
                return date('Y-m-d H:i:s', strtotime('-1 year'));
            default:
                return date('Y-m-d H:i:s', strtotime('-30 days'));
        }
    }

    /**
     * Get average ratings by dimension
     *
     * @param string $date_from Start date
     * @return array Average ratings
     */
    private function get_average_ratings_by_dimension($date_from) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    r.dimension,
                    AVG(r.rating) as avg_rating
                FROM {$this->tables['feedback']} f
                JOIN {$this->tables['feedback_ratings']} r ON f.id = r.feedback_id
                WHERE f.created_at >= %s
                GROUP BY r.dimension",
                $date_from
            ),
            ARRAY_A
        );

        $ratings = [];
        foreach ($results as $result) {
            $ratings[$result['dimension']] = floatval($result['avg_rating']);
        }

        return $ratings;
    }

    /**
     * Calculate issue rate
     *
     * @param string $date_from Start date
     * @return float Issue rate
     */
    private function calculate_issue_rate($date_from) {
        $total_generations = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT generation_id) FROM {$this->tables['feedback']} WHERE created_at >= %s",
                $date_from
            )
        );

        $generations_with_issues = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT generation_id) FROM {$this->tables['issues']} WHERE created_at >= %s",
                $date_from
            )
        );

        return $total_generations > 0 ? $generations_with_issues / $total_generations : 0;
    }

    /**
     * Calculate repeat usage rate
     *
     * @param string $date_from Start date
     * @return float Repeat usage rate
     */
    private function calculate_repeat_usage_rate($date_from) {
        $total_users = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$this->tables['feedback']}
                WHERE created_at >= %s AND user_id > 0",
                $date_from
            )
        );

        $repeat_users = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM (
                    SELECT user_id, COUNT(*) as count
                    FROM {$this->tables['feedback']}
                    WHERE created_at >= %s AND user_id > 0
                    GROUP BY user_id
                    HAVING count > 1
                ) as repeat_table",
                $date_from
            )
        );

        return $total_users > 0 ? $repeat_users / $total_users : 0;
    }

    /**
     * Increment issue count for a generation
     *
     * @param int $generation_id Generation ID
     */
    private function increment_issue_count($generation_id) {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->tables['feedback_stats']}
                SET issues_count = issues_count + 1
                WHERE generation_id = %d",
                $generation_id
            )
        );
    }

    /**
     * Get A/B test data
     *
     * @param int $test_id Test ID
     * @return array Test data
     */
    public function get_ab_test($test_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->tables['ab_tests']} WHERE id = %d",
                $test_id
            ),
            ARRAY_A
        );
    }

    /**
     * Update A/B test results
     *
     * @param int $test_id Test ID
     * @param string $variant Variant (a or b)
     * @param float $rating Rating
     */
    public function update_ab_test_result($test_id, $variant, $rating) {
        $column_count = 'variant_' . $variant . '_count';
        $column_rating = 'variant_' . $variant . '_rating';

        // Get current values
        $current = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT $column_count as count, $column_rating as rating
                FROM {$this->tables['ab_tests']} WHERE id = %d",
                $test_id
            ),
            ARRAY_A
        );

        // Calculate new average
        $new_count = $current['count'] + 1;
        $new_rating = (($current['rating'] ?? 0) * $current['count'] + $rating) / $new_count;

        // Update
        $this->wpdb->update(
            $this->tables['ab_tests'],
            [
                $column_count => $new_count,
                $column_rating => $new_rating,
            ],
            ['id' => $test_id]
        );

        // Check for statistical significance and declare winner
        $this->check_ab_test_completion($test_id);
    }

    /**
     * Check if A/B test should be completed
     *
     * @param int $test_id Test ID
     */
    private function check_ab_test_completion($test_id) {
        $test = $this->get_ab_test($test_id);

        // Minimum sample size per variant
        $min_sample_size = 100;

        if ($test['variant_a_count'] >= $min_sample_size && $test['variant_b_count'] >= $min_sample_size) {
            // Calculate statistical significance
            $confidence = $this->calculate_confidence_level(
                $test['variant_a_rating'],
                $test['variant_a_count'],
                $test['variant_b_rating'],
                $test['variant_b_count']
            );

            if ($confidence >= 95) {
                $winner = $test['variant_a_rating'] > $test['variant_b_rating'] ? 'a' : 'b';

                $this->wpdb->update(
                    $this->tables['ab_tests'],
                    [
                        'winner' => $winner,
                        'status' => 'completed',
                        'confidence_level' => $confidence,
                        'completed_at' => current_time('mysql'),
                    ],
                    ['id' => $test_id]
                );
            }
        }
    }

    /**
     * Calculate confidence level for A/B test
     *
     * @param float $mean_a Mean of variant A
     * @param int $count_a Count of variant A
     * @param float $mean_b Mean of variant B
     * @param int $count_b Count of variant B
     * @return float Confidence level
     */
    private function calculate_confidence_level($mean_a, $count_a, $mean_b, $count_b) {
        // Simplified confidence calculation
        // In production, use proper statistical test (t-test, etc.)
        $diff = abs($mean_a - $mean_b);
        $total_samples = $count_a + $count_b;

        // Basic confidence calculation
        $confidence = min(95, $diff * 20 * log($total_samples));

        return $confidence;
    }
}