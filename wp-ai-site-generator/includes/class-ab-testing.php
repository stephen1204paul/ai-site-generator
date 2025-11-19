<?php
/**
 * A/B Testing Infrastructure
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Includes;

use WP_AI_Site_Generator\Database\Feedback_DB_Handler;

/**
 * A/B Testing Class
 * Manages A/B testing for prompt variations
 */
class AB_Testing {

    /**
     * Database handler
     *
     * @var Feedback_DB_Handler
     */
    private $db_handler;

    /**
     * Active tests cache
     *
     * @var array
     */
    private $active_tests = [];

    /**
     * Test configuration
     *
     * @var array
     */
    private $config = [
        'min_sample_size' => 100,
        'confidence_threshold' => 95,
        'max_test_duration_days' => 30,
        'traffic_split' => 50, // Percentage for variant B
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->db_handler = new Feedback_DB_Handler();
        $this->init_hooks();
        $this->load_active_tests();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_filter('wp_ai_site_generator_select_prompt', [$this, 'select_test_variant'], 10, 2);
        add_action('wp_ai_site_generator_generation_complete', [$this, 'track_test_assignment'], 10, 2);
        add_action('wp_ai_site_generator_feedback_submitted', [$this, 'record_test_result'], 10, 1);
        add_action('wp_ai_site_generator_daily_ab_check', [$this, 'check_test_completion']);

        // Schedule daily test check
        if (!wp_next_scheduled('wp_ai_site_generator_daily_ab_check')) {
            wp_schedule_event(time(), 'daily', 'wp_ai_site_generator_daily_ab_check');
        }
    }

    /**
     * Create a new A/B test
     *
     * @param array $test_data Test configuration
     * @return int|false Test ID or false on failure
     */
    public function create_test($test_data) {
        $validated_data = $this->validate_test_data($test_data);

        if (is_wp_error($validated_data)) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'ai_ab_tests',
            [
                'test_name' => $validated_data['test_name'],
                'variant_a' => $validated_data['variant_a'],
                'variant_b' => $validated_data['variant_b'],
                'variant_a_count' => 0,
                'variant_b_count' => 0,
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        if ($result) {
            $test_id = $wpdb->insert_id;
            $this->load_active_tests(); // Reload cache

            // Log test creation
            $this->log_test_event($test_id, 'created', $validated_data);

            return $test_id;
        }

        return false;
    }

    /**
     * Test multiple prompt variations
     *
     * @param array $variations Array of prompt variations
     * @param array $config Test configuration
     * @return array Test results
     */
    public function test_prompt_variations($variations, $config = []) {
        if (count($variations) < 2) {
            return new \WP_Error('insufficient_variations', __('At least 2 variations required', 'wp-ai-site-generator'));
        }

        $tests = [];

        // Create pairwise tests
        for ($i = 0; $i < count($variations) - 1; $i++) {
            for ($j = $i + 1; $j < count($variations); $j++) {
                $test_data = [
                    'test_name' => sprintf(
                        __('Variation %d vs %d', 'wp-ai-site-generator'),
                        $i + 1,
                        $j + 1
                    ),
                    'variant_a' => $variations[$i],
                    'variant_b' => $variations[$j],
                ];

                $test_id = $this->create_test(array_merge($test_data, $config));

                if ($test_id) {
                    $tests[] = [
                        'test_id' => $test_id,
                        'variant_a_index' => $i,
                        'variant_b_index' => $j,
                    ];
                }
            }
        }

        return $tests;
    }

    /**
     * Select test variant for generation
     *
     * @param string $prompt Original prompt
     * @param array $context Generation context
     * @return string Selected prompt variant
     */
    public function select_test_variant($prompt, $context) {
        // Check if there's an active test for this prompt type
        $active_test = $this->find_active_test_for_prompt($prompt, $context);

        if (!$active_test) {
            return $prompt;
        }

        // Determine variant based on traffic split
        $use_variant_b = $this->should_use_variant_b($active_test);

        // Store selection for tracking
        $this->store_variant_selection($active_test['id'], $use_variant_b ? 'b' : 'a', $context);

        return $use_variant_b ? $active_test['variant_b'] : $active_test['variant_a'];
    }

    /**
     * Track which variant was used for generation
     *
     * @param int $generation_id Generation ID
     * @param array $generation_data Generation data
     */
    public function track_test_assignment($generation_id, $generation_data) {
        $assignment = $this->get_stored_assignment($generation_id);

        if ($assignment) {
            update_post_meta($generation_id, '_ab_test_data', [
                'test_id' => $assignment['test_id'],
                'variant' => $assignment['variant'],
                'timestamp' => current_time('mysql'),
            ]);

            // Increment variant count
            $this->increment_variant_count($assignment['test_id'], $assignment['variant']);
        }
    }

    /**
     * Record test result when feedback is submitted
     *
     * @param array $feedback_data Feedback data
     */
    public function record_test_result($feedback_data) {
        $generation_id = $feedback_data['generation_id'];
        $test_data = get_post_meta($generation_id, '_ab_test_data', true);

        if ($test_data) {
            $rating = $feedback_data['ratings']['overall'] ?? 0;

            // Update test results
            $this->db_handler->update_ab_test_result(
                $test_data['test_id'],
                $test_data['variant'],
                $rating
            );

            // Check if test should be completed
            $this->check_test_for_completion($test_data['test_id']);
        }
    }

    /**
     * Track conversion from feedback to action
     *
     * @param int $test_id Test ID
     * @param string $variant Variant (a or b)
     * @param string $action Action taken
     * @return bool Success
     */
    public function track_conversion($test_id, $variant, $action) {
        global $wpdb;

        // Store conversion data
        $result = $wpdb->insert(
            $wpdb->prefix . 'ai_ab_conversions',
            [
                'test_id' => $test_id,
                'variant' => $variant,
                'action' => $action,
                'timestamp' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        if ($result) {
            // Update conversion rate
            $this->update_conversion_rate($test_id, $variant);
            return true;
        }

        return false;
    }

    /**
     * Perform statistical significance testing
     *
     * @param int $test_id Test ID
     * @return array Test results with statistical analysis
     */
    public function calculate_statistical_significance($test_id) {
        $test = $this->db_handler->get_ab_test($test_id);

        if (!$test) {
            return null;
        }

        // Get sample sizes and means
        $n_a = $test['variant_a_count'];
        $n_b = $test['variant_b_count'];
        $mean_a = $test['variant_a_rating'];
        $mean_b = $test['variant_b_rating'];

        // Calculate standard deviations (would need actual data for accurate calculation)
        $std_a = $this->calculate_standard_deviation($test_id, 'a');
        $std_b = $this->calculate_standard_deviation($test_id, 'b');

        // Perform t-test
        $t_statistic = $this->calculate_t_statistic($mean_a, $mean_b, $std_a, $std_b, $n_a, $n_b);
        $degrees_of_freedom = $n_a + $n_b - 2;
        $p_value = $this->calculate_p_value($t_statistic, $degrees_of_freedom);

        // Calculate confidence interval
        $confidence_interval = $this->calculate_confidence_interval(
            $mean_a - $mean_b,
            $std_a,
            $std_b,
            $n_a,
            $n_b
        );

        // Calculate effect size (Cohen's d)
        $effect_size = $this->calculate_effect_size($mean_a, $mean_b, $std_a, $std_b);

        return [
            'test_id' => $test_id,
            'sample_size_a' => $n_a,
            'sample_size_b' => $n_b,
            'mean_a' => $mean_a,
            'mean_b' => $mean_b,
            'std_dev_a' => $std_a,
            'std_dev_b' => $std_b,
            't_statistic' => $t_statistic,
            'p_value' => $p_value,
            'significant' => $p_value < 0.05,
            'confidence_level' => (1 - $p_value) * 100,
            'confidence_interval' => $confidence_interval,
            'effect_size' => $effect_size,
            'winner' => $this->determine_winner($mean_a, $mean_b, $p_value),
            'recommendation' => $this->generate_recommendation($test),
        ];
    }

    /**
     * Automatically select winner when significance reached
     *
     * @param int $test_id Test ID
     * @return string|null Winner variant or null
     */
    public function select_winner($test_id) {
        $significance = $this->calculate_statistical_significance($test_id);

        if ($significance['significant'] && $significance['winner']) {
            global $wpdb;

            // Update test status
            $wpdb->update(
                $wpdb->prefix . 'ai_ab_tests',
                [
                    'winner' => $significance['winner'],
                    'status' => 'completed',
                    'confidence_level' => $significance['confidence_level'],
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $test_id]
            );

            // Apply winning variant
            $this->apply_winning_variant($test_id, $significance['winner']);

            // Notify administrators
            $this->notify_test_completion($test_id, $significance);

            return $significance['winner'];
        }

        return null;
    }

    /**
     * Get test management report
     *
     * @param array $filters Report filters
     * @return array Test report
     */
    public function get_test_report($filters = []) {
        global $wpdb;

        $status_filter = $filters['status'] ?? 'all';
        $period = $filters['period'] ?? '30days';

        $where = '';
        if ($status_filter !== 'all') {
            $where = $wpdb->prepare("WHERE status = %s", $status_filter);
        }

        $tests = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ai_ab_tests
            $where
            ORDER BY created_at DESC",
            ARRAY_A
        );

        $report = [
            'summary' => [
                'total_tests' => count($tests),
                'active_tests' => 0,
                'completed_tests' => 0,
                'average_confidence' => 0,
                'total_participants' => 0,
            ],
            'tests' => [],
        ];

        $total_confidence = 0;
        $confidence_count = 0;

        foreach ($tests as $test) {
            // Calculate statistics for each test
            $stats = $this->calculate_statistical_significance($test['id']);

            $test_data = array_merge($test, [
                'statistics' => $stats,
                'duration_days' => $this->calculate_test_duration($test),
                'completion_rate' => $this->calculate_completion_rate($test),
            ]);

            $report['tests'][] = $test_data;

            // Update summary
            if ($test['status'] === 'active') {
                $report['summary']['active_tests']++;
            } elseif ($test['status'] === 'completed') {
                $report['summary']['completed_tests']++;
            }

            if ($test['confidence_level']) {
                $total_confidence += $test['confidence_level'];
                $confidence_count++;
            }

            $report['summary']['total_participants'] += $test['variant_a_count'] + $test['variant_b_count'];
        }

        if ($confidence_count > 0) {
            $report['summary']['average_confidence'] = $total_confidence / $confidence_count;
        }

        // Add insights
        $report['insights'] = $this->generate_test_insights($report['tests']);

        return $report;
    }

    /**
     * Validate test data
     *
     * @param array $data Test data
     * @return array|WP_Error Validated data or error
     */
    private function validate_test_data($data) {
        if (empty($data['test_name']) || empty($data['variant_a']) || empty($data['variant_b'])) {
            return new \WP_Error('missing_data', __('Test name and both variants are required', 'wp-ai-site-generator'));
        }

        if ($data['variant_a'] === $data['variant_b']) {
            return new \WP_Error('identical_variants', __('Variants must be different', 'wp-ai-site-generator'));
        }

        return $data;
    }

    /**
     * Load active tests into cache
     */
    private function load_active_tests() {
        global $wpdb;

        $this->active_tests = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ai_ab_tests WHERE status = 'active'",
            ARRAY_A
        );
    }

    /**
     * Find active test for prompt
     *
     * @param string $prompt Prompt
     * @param array $context Context
     * @return array|null Test data or null
     */
    private function find_active_test_for_prompt($prompt, $context) {
        foreach ($this->active_tests as $test) {
            // Check if prompt matches variant A (original)
            if ($this->prompts_match($prompt, $test['variant_a'], $context)) {
                return $test;
            }
        }

        return null;
    }

    /**
     * Check if prompts match
     *
     * @param string $prompt1 First prompt
     * @param string $prompt2 Second prompt
     * @param array $context Context
     * @return bool Match
     */
    private function prompts_match($prompt1, $prompt2, $context) {
        // Simple similarity check - can be enhanced
        $similarity = similar_text($prompt1, $prompt2, $percent);
        return $percent > 80; // 80% similarity threshold
    }

    /**
     * Determine if variant B should be used
     *
     * @param array $test Test data
     * @return bool Use variant B
     */
    private function should_use_variant_b($test) {
        // Use hash-based assignment for consistency
        $user_id = get_current_user_id();
        $session_id = session_id() ?: wp_generate_password(32, false);
        $hash = md5($test['id'] . $user_id . $session_id);
        $hash_value = hexdec(substr($hash, 0, 8)) / 0xFFFFFFFF;

        return $hash_value < ($this->config['traffic_split'] / 100);
    }

    /**
     * Store variant selection
     *
     * @param int $test_id Test ID
     * @param string $variant Variant
     * @param array $context Context
     */
    private function store_variant_selection($test_id, $variant, $context) {
        set_transient(
            'ab_test_selection_' . wp_generate_password(8, false),
            [
                'test_id' => $test_id,
                'variant' => $variant,
                'context' => $context,
            ],
            HOUR_IN_SECONDS
        );
    }

    /**
     * Get stored assignment
     *
     * @param int $generation_id Generation ID
     * @return array|null Assignment data
     */
    private function get_stored_assignment($generation_id) {
        // Implementation would retrieve stored assignment
        // This is simplified for demonstration
        return null;
    }

    /**
     * Increment variant count
     *
     * @param int $test_id Test ID
     * @param string $variant Variant
     */
    private function increment_variant_count($test_id, $variant) {
        global $wpdb;

        $column = 'variant_' . $variant . '_count';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ai_ab_tests
                SET $column = $column + 1
                WHERE id = %d",
                $test_id
            )
        );
    }

    /**
     * Check test for completion
     *
     * @param int $test_id Test ID
     */
    private function check_test_for_completion($test_id) {
        $test = $this->db_handler->get_ab_test($test_id);

        if (!$test || $test['status'] !== 'active') {
            return;
        }

        // Check if minimum sample size reached
        if ($test['variant_a_count'] >= $this->config['min_sample_size'] &&
            $test['variant_b_count'] >= $this->config['min_sample_size']) {

            // Check statistical significance
            $significance = $this->calculate_statistical_significance($test_id);

            if ($significance['significant']) {
                $this->select_winner($test_id);
            }
        }

        // Check if max duration exceeded
        $duration = $this->calculate_test_duration($test);
        if ($duration > $this->config['max_test_duration_days']) {
            $this->end_test($test_id, 'timeout');
        }
    }

    /**
     * Check all active tests for completion
     */
    public function check_test_completion() {
        foreach ($this->active_tests as $test) {
            $this->check_test_for_completion($test['id']);
        }
    }

    /**
     * Calculate test duration
     *
     * @param array $test Test data
     * @return int Duration in days
     */
    private function calculate_test_duration($test) {
        $start = strtotime($test['created_at']);
        $end = $test['completed_at'] ? strtotime($test['completed_at']) : time();
        return floor(($end - $start) / DAY_IN_SECONDS);
    }

    /**
     * Calculate completion rate
     *
     * @param array $test Test data
     * @return float Completion percentage
     */
    private function calculate_completion_rate($test) {
        $total = $test['variant_a_count'] + $test['variant_b_count'];
        $target = $this->config['min_sample_size'] * 2;

        if ($target === 0) {
            return 0;
        }

        return min(100, ($total / $target) * 100);
    }

    /**
     * Calculate standard deviation
     *
     * @param int $test_id Test ID
     * @param string $variant Variant
     * @return float Standard deviation
     */
    private function calculate_standard_deviation($test_id, $variant) {
        global $wpdb;

        // Get all ratings for the variant
        $ratings = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT fr.rating
                FROM {$wpdb->prefix}ai_generations g
                JOIN {$wpdb->prefix}ai_feedback f ON g.id = f.generation_id
                JOIN {$wpdb->prefix}ai_feedback_ratings fr ON f.id = fr.feedback_id
                WHERE JSON_EXTRACT(g.meta, '$.ab_test_id') = %d
                AND JSON_EXTRACT(g.meta, '$.ab_variant') = %s
                AND fr.dimension = 'overall'",
                $test_id,
                $variant
            )
        );

        if (empty($ratings)) {
            return 1.0; // Default standard deviation
        }

        $mean = array_sum($ratings) / count($ratings);
        $variance = 0;

        foreach ($ratings as $rating) {
            $variance += pow($rating - $mean, 2);
        }

        $variance = $variance / count($ratings);
        return sqrt($variance);
    }

    /**
     * Calculate t-statistic
     *
     * @param float $mean_a Mean A
     * @param float $mean_b Mean B
     * @param float $std_a Std dev A
     * @param float $std_b Std dev B
     * @param int $n_a Sample size A
     * @param int $n_b Sample size B
     * @return float T-statistic
     */
    private function calculate_t_statistic($mean_a, $mean_b, $std_a, $std_b, $n_a, $n_b) {
        if ($n_a === 0 || $n_b === 0) {
            return 0;
        }

        $pooled_std = sqrt((pow($std_a, 2) / $n_a) + (pow($std_b, 2) / $n_b));

        if ($pooled_std === 0) {
            return 0;
        }

        return ($mean_a - $mean_b) / $pooled_std;
    }

    /**
     * Calculate p-value
     *
     * @param float $t_statistic T-statistic
     * @param int $df Degrees of freedom
     * @return float P-value
     */
    private function calculate_p_value($t_statistic, $df) {
        // Simplified p-value calculation
        // In production, use proper statistical library
        $t_abs = abs($t_statistic);

        if ($t_abs < 1.96) {
            return 0.10; // Not significant
        } elseif ($t_abs < 2.58) {
            return 0.05; // Marginally significant
        } else {
            return 0.01; // Highly significant
        }
    }

    /**
     * Calculate confidence interval
     *
     * @param float $diff Mean difference
     * @param float $std_a Std dev A
     * @param float $std_b Std dev B
     * @param int $n_a Sample size A
     * @param int $n_b Sample size B
     * @return array Confidence interval
     */
    private function calculate_confidence_interval($diff, $std_a, $std_b, $n_a, $n_b) {
        $standard_error = sqrt((pow($std_a, 2) / $n_a) + (pow($std_b, 2) / $n_b));
        $margin = 1.96 * $standard_error; // 95% confidence

        return [
            'lower' => $diff - $margin,
            'upper' => $diff + $margin,
        ];
    }

    /**
     * Calculate effect size (Cohen's d)
     *
     * @param float $mean_a Mean A
     * @param float $mean_b Mean B
     * @param float $std_a Std dev A
     * @param float $std_b Std dev B
     * @return float Effect size
     */
    private function calculate_effect_size($mean_a, $mean_b, $std_a, $std_b) {
        $pooled_std = sqrt((pow($std_a, 2) + pow($std_b, 2)) / 2);

        if ($pooled_std === 0) {
            return 0;
        }

        return abs($mean_a - $mean_b) / $pooled_std;
    }

    /**
     * Determine winner
     *
     * @param float $mean_a Mean A
     * @param float $mean_b Mean B
     * @param float $p_value P-value
     * @return string|null Winner
     */
    private function determine_winner($mean_a, $mean_b, $p_value) {
        if ($p_value >= 0.05) {
            return null; // Not significant
        }

        return $mean_a > $mean_b ? 'a' : 'b';
    }

    /**
     * Generate recommendation based on test results
     *
     * @param array $test Test data
     * @return string Recommendation
     */
    private function generate_recommendation($test) {
        if ($test['winner']) {
            $improvement = abs($test['variant_a_rating'] - $test['variant_b_rating']);
            return sprintf(
                __('Variant %s shows %.1f%% improvement. Recommend implementing.', 'wp-ai-site-generator'),
                strtoupper($test['winner']),
                ($improvement / min($test['variant_a_rating'], $test['variant_b_rating'])) * 100
            );
        }

        if ($test['variant_a_count'] < $this->config['min_sample_size']) {
            return __('Continue testing to reach minimum sample size.', 'wp-ai-site-generator');
        }

        return __('No significant difference detected. Consider testing different variations.', 'wp-ai-site-generator');
    }

    /**
     * Apply winning variant
     *
     * @param int $test_id Test ID
     * @param string $winner Winner variant
     */
    private function apply_winning_variant($test_id, $winner) {
        $test = $this->db_handler->get_ab_test($test_id);

        if ($test) {
            $winning_prompt = $winner === 'a' ? $test['variant_a'] : $test['variant_b'];

            // Store winning variant for future use
            update_option('wp_ai_winning_prompts', array_merge(
                get_option('wp_ai_winning_prompts', []),
                [$test_id => $winning_prompt]
            ));

            // Log application
            $this->log_test_event($test_id, 'winner_applied', ['winner' => $winner]);
        }
    }

    /**
     * Notify administrators of test completion
     *
     * @param int $test_id Test ID
     * @param array $results Test results
     */
    private function notify_test_completion($test_id, $results) {
        $admins = get_users(['role' => 'administrator']);
        $test = $this->db_handler->get_ab_test($test_id);

        $subject = sprintf(
            __('A/B Test "%s" Completed', 'wp-ai-site-generator'),
            $test['test_name']
        );

        $message = sprintf(
            __("The A/B test has completed with the following results:\n\n" .
               "Winner: Variant %s\n" .
               "Confidence Level: %.1f%%\n" .
               "Sample Size A: %d\n" .
               "Sample Size B: %d\n" .
               "Mean Rating A: %.2f\n" .
               "Mean Rating B: %.2f\n" .
               "Effect Size: %.2f\n\n" .
               "Recommendation: %s\n\n" .
               "View full results in the admin dashboard.",
               'wp-ai-site-generator'),
            strtoupper($results['winner']),
            $results['confidence_level'],
            $results['sample_size_a'],
            $results['sample_size_b'],
            $results['mean_a'],
            $results['mean_b'],
            $results['effect_size'],
            $results['recommendation']
        );

        foreach ($admins as $admin) {
            wp_mail($admin->user_email, $subject, $message);
        }
    }

    /**
     * End test manually
     *
     * @param int $test_id Test ID
     * @param string $reason Reason for ending
     */
    public function end_test($test_id, $reason = 'manual') {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ai_ab_tests',
            [
                'status' => 'ended',
                'completed_at' => current_time('mysql'),
            ],
            ['id' => $test_id]
        );

        $this->log_test_event($test_id, 'ended', ['reason' => $reason]);
        $this->load_active_tests(); // Reload cache
    }

    /**
     * Update conversion rate
     *
     * @param int $test_id Test ID
     * @param string $variant Variant
     */
    private function update_conversion_rate($test_id, $variant) {
        global $wpdb;

        // Calculate conversion rate
        $conversions = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ai_ab_conversions
                WHERE test_id = %d AND variant = %s",
                $test_id,
                $variant
            )
        );

        $test = $this->db_handler->get_ab_test($test_id);
        $variant_count = $variant === 'a' ? $test['variant_a_count'] : $test['variant_b_count'];

        if ($variant_count > 0) {
            $conversion_rate = ($conversions / $variant_count) * 100;

            // Store conversion rate
            update_post_meta($test_id, "conversion_rate_$variant", $conversion_rate);
        }
    }

    /**
     * Generate insights from test results
     *
     * @param array $tests Test data
     * @return array Insights
     */
    private function generate_test_insights($tests) {
        $insights = [
            'winning_patterns' => [],
            'losing_patterns' => [],
            'recommendations' => [],
        ];

        foreach ($tests as $test) {
            if ($test['winner']) {
                $winning_variant = $test['winner'] === 'a' ? $test['variant_a'] : $test['variant_b'];
                $losing_variant = $test['winner'] === 'a' ? $test['variant_b'] : $test['variant_a'];

                // Analyze patterns in winning variants
                $insights['winning_patterns'][] = $this->extract_patterns($winning_variant);
                $insights['losing_patterns'][] = $this->extract_patterns($losing_variant);
            }
        }

        // Generate recommendations based on patterns
        $insights['recommendations'] = $this->generate_pattern_recommendations(
            $insights['winning_patterns'],
            $insights['losing_patterns']
        );

        return $insights;
    }

    /**
     * Extract patterns from prompt
     *
     * @param string $prompt Prompt text
     * @return array Patterns
     */
    private function extract_patterns($prompt) {
        return [
            'length' => strlen($prompt),
            'word_count' => str_word_count($prompt),
            'has_examples' => strpos($prompt, 'example') !== false,
            'has_instructions' => strpos($prompt, 'instructions') !== false,
            'tone' => $this->detect_tone($prompt),
        ];
    }

    /**
     * Detect tone of prompt
     *
     * @param string $prompt Prompt text
     * @return string Tone
     */
    private function detect_tone($prompt) {
        if (strpos($prompt, 'please') !== false || strpos($prompt, 'kindly') !== false) {
            return 'polite';
        } elseif (strpos($prompt, '!') !== false) {
            return 'emphatic';
        } else {
            return 'neutral';
        }
    }

    /**
     * Generate recommendations from patterns
     *
     * @param array $winning_patterns Winning patterns
     * @param array $losing_patterns Losing patterns
     * @return array Recommendations
     */
    private function generate_pattern_recommendations($winning_patterns, $losing_patterns) {
        $recommendations = [];

        // Analyze common characteristics of winners
        if (!empty($winning_patterns)) {
            $avg_length = array_sum(array_column($winning_patterns, 'length')) / count($winning_patterns);
            $recommendations[] = sprintf(
                __('Optimal prompt length appears to be around %d characters', 'wp-ai-site-generator'),
                $avg_length
            );
        }

        return $recommendations;
    }

    /**
     * Log test event
     *
     * @param int $test_id Test ID
     * @param string $event Event type
     * @param array $data Event data
     */
    private function log_test_event($test_id, $event, $data = []) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ai_ab_test_logs',
            [
                'test_id' => $test_id,
                'event' => $event,
                'data' => wp_json_encode($data),
                'timestamp' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );
    }
}