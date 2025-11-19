<?php
/**
 * Feedback Learning System
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Includes;

use WP_AI_Site_Generator\Database\Feedback_DB_Handler;

/**
 * Feedback Learning System Class
 * Analyzes feedback patterns and improves AI generation
 */
class Feedback_Learner {

    /**
     * Database handler
     *
     * @var Feedback_DB_Handler
     */
    private $db_handler;

    /**
     * Learning thresholds
     *
     * @var array
     */
    private $thresholds = [
        'min_feedback_for_learning' => 10,
        'low_rating_threshold' => 3.0,
        'high_rating_threshold' => 4.0,
        'significant_improvement' => 0.5,
        'confidence_threshold' => 0.75,
    ];

    /**
     * Pattern analyzers
     *
     * @var array
     */
    private $analyzers = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->db_handler = new Feedback_DB_Handler();
        $this->init_analyzers();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ai_site_generator_feedback_submitted', [$this, 'process_new_feedback'], 10, 1);
        add_action('wp_ai_site_generator_daily_learning', [$this, 'run_daily_learning']);
        add_filter('wp_ai_site_generator_prompt_template', [$this, 'apply_learned_improvements'], 10, 2);
        add_filter('wp_ai_site_generator_generation_parameters', [$this, 'optimize_parameters'], 10, 2);

        // Schedule daily learning if not already scheduled
        if (!wp_next_scheduled('wp_ai_site_generator_daily_learning')) {
            wp_schedule_event(time(), 'daily', 'wp_ai_site_generator_daily_learning');
        }
    }

    /**
     * Initialize pattern analyzers
     */
    private function init_analyzers() {
        $this->analyzers = [
            'content_quality' => new ContentQualityAnalyzer(),
            'prompt_effectiveness' => new PromptEffectivenessAnalyzer(),
            'user_preference' => new UserPreferenceAnalyzer(),
            'issue_pattern' => new IssuePatternAnalyzer(),
            'success_pattern' => new SuccessPatternAnalyzer(),
        ];
    }

    /**
     * Process new feedback in real-time
     *
     * @param array $feedback_data Feedback data
     */
    public function process_new_feedback($feedback_data) {
        // Quick analysis for immediate adjustments
        $this->analyze_immediate_patterns($feedback_data);

        // Update prompt scoring
        $this->update_prompt_score($feedback_data);

        // Check for critical issues
        if ($this->is_critical_feedback($feedback_data)) {
            $this->handle_critical_feedback($feedback_data);
        }

        // Trigger learning if threshold met
        $this->check_learning_threshold($feedback_data['generation_id']);
    }

    /**
     * Run comprehensive daily learning
     */
    public function run_daily_learning() {
        // Get all feedback from last 24 hours
        $recent_feedback = $this->get_recent_feedback(24);

        // Analyze patterns
        $patterns = $this->analyze_feedback_patterns($recent_feedback);

        // Generate insights
        $insights = $this->generate_insights($patterns);

        // Update prompt templates
        $this->update_prompt_templates($insights);

        // Optimize generation parameters
        $this->optimize_generation_parameters($insights);

        // Store learning results
        $this->store_learning_results($insights);

        // Send admin report
        $this->send_learning_report($insights);
    }

    /**
     * Analyze feedback patterns
     *
     * @param array $feedback_data Feedback data
     * @return array Patterns
     */
    public function analyze_feedback_patterns($feedback_data) {
        $patterns = [
            'rating_patterns' => $this->analyze_rating_patterns($feedback_data),
            'issue_patterns' => $this->analyze_issue_patterns($feedback_data),
            'success_patterns' => $this->analyze_success_patterns($feedback_data),
            'temporal_patterns' => $this->analyze_temporal_patterns($feedback_data),
            'user_patterns' => $this->analyze_user_patterns($feedback_data),
            'content_patterns' => $this->analyze_content_patterns($feedback_data),
        ];

        // Run specialized analyzers
        foreach ($this->analyzers as $name => $analyzer) {
            $patterns[$name] = $analyzer->analyze($feedback_data);
        }

        return $patterns;
    }

    /**
     * Identify common issues from feedback
     *
     * @param array $feedback_data Feedback data
     * @return array Common issues
     */
    public function identify_common_issues($feedback_data) {
        $issues = [];
        $issue_counts = [];

        // Extract issues from text feedback
        foreach ($feedback_data as $feedback) {
            if (!empty($feedback['text_feedback'])) {
                $extracted_issues = $this->extract_issues_from_text($feedback['text_feedback']);
                foreach ($extracted_issues as $issue) {
                    $issue_counts[$issue] = ($issue_counts[$issue] ?? 0) + 1;
                }
            }
        }

        // Sort by frequency
        arsort($issue_counts);

        // Build issue list with recommendations
        foreach ($issue_counts as $issue => $count) {
            if ($count >= 3) { // Minimum threshold
                $issues[] = [
                    'issue' => $issue,
                    'frequency' => $count,
                    'severity' => $this->calculate_issue_severity($issue, $count),
                    'recommendation' => $this->generate_issue_recommendation($issue),
                ];
            }
        }

        return $issues;
    }

    /**
     * Suggest prompt improvements based on feedback
     *
     * @param string $prompt_template Current prompt template
     * @param array $feedback_data Related feedback
     * @return array Suggested improvements
     */
    public function suggest_prompt_improvements($prompt_template, $feedback_data) {
        $suggestions = [];

        // Analyze what works well
        $successful_patterns = $this->identify_successful_patterns($feedback_data);

        // Analyze what needs improvement
        $improvement_areas = $this->identify_improvement_areas($feedback_data);

        // Generate specific suggestions
        foreach ($improvement_areas as $area) {
            $suggestion = $this->generate_prompt_suggestion($area, $prompt_template);
            if ($suggestion) {
                $suggestions[] = $suggestion;
            }
        }

        // Add reinforcement for successful patterns
        foreach ($successful_patterns as $pattern) {
            $suggestions[] = [
                'type' => 'reinforce',
                'pattern' => $pattern,
                'recommendation' => sprintf(
                    __('Continue emphasizing %s as it receives positive feedback', 'wp-ai-site-generator'),
                    $pattern
                ),
            ];
        }

        return $suggestions;
    }

    /**
     * Track which prompts get best ratings
     *
     * @return array Top-rated prompts
     */
    public function get_top_rated_prompts() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT
                g.prompt_template,
                AVG(fs.avg_overall_rating) as avg_rating,
                COUNT(DISTINCT g.id) as usage_count,
                SUM(fs.total_feedback) as total_feedback
            FROM {$wpdb->prefix}ai_generations g
            INNER JOIN {$wpdb->prefix}ai_feedback_stats fs ON g.id = fs.generation_id
            WHERE fs.avg_overall_rating IS NOT NULL
            GROUP BY g.prompt_template
            HAVING usage_count >= 5
            ORDER BY avg_rating DESC
            LIMIT 20",
            ARRAY_A
        );

        // Enhance with pattern analysis
        foreach ($results as &$result) {
            $result['patterns'] = $this->extract_prompt_patterns($result['prompt_template']);
            $result['characteristics'] = $this->analyze_prompt_characteristics($result['prompt_template']);
        }

        return $results;
    }

    /**
     * Aggregate A/B test results
     *
     * @param int $test_id Test ID
     * @return array Aggregated results
     */
    public function aggregate_ab_test_results($test_id) {
        $test_data = $this->db_handler->get_ab_test($test_id);

        if (!$test_data) {
            return null;
        }

        $results = [
            'test_id' => $test_id,
            'test_name' => $test_data['test_name'],
            'variant_a' => [
                'prompt' => $test_data['variant_a'],
                'count' => $test_data['variant_a_count'],
                'average_rating' => $test_data['variant_a_rating'],
                'performance_index' => $this->calculate_performance_index($test_data, 'a'),
            ],
            'variant_b' => [
                'prompt' => $test_data['variant_b'],
                'count' => $test_data['variant_b_count'],
                'average_rating' => $test_data['variant_b_rating'],
                'performance_index' => $this->calculate_performance_index($test_data, 'b'),
            ],
            'winner' => $test_data['winner'],
            'confidence_level' => $test_data['confidence_level'],
            'status' => $test_data['status'],
            'insights' => $this->generate_ab_test_insights($test_data),
        ];

        return $results;
    }

    /**
     * Generate insights and recommendations
     *
     * @param array $patterns Analyzed patterns
     * @return array Insights
     */
    public function generate_insights($patterns) {
        $insights = [
            'key_findings' => [],
            'recommendations' => [],
            'action_items' => [],
            'trends' => [],
        ];

        // Analyze rating patterns
        if (isset($patterns['rating_patterns'])) {
            $rating_insights = $this->generate_rating_insights($patterns['rating_patterns']);
            $insights['key_findings'] = array_merge($insights['key_findings'], $rating_insights['findings']);
            $insights['recommendations'] = array_merge($insights['recommendations'], $rating_insights['recommendations']);
        }

        // Analyze issue patterns
        if (isset($patterns['issue_patterns'])) {
            $issue_insights = $this->generate_issue_insights($patterns['issue_patterns']);
            $insights['action_items'] = array_merge($insights['action_items'], $issue_insights['actions']);
        }

        // Analyze success patterns
        if (isset($patterns['success_patterns'])) {
            $success_insights = $this->generate_success_insights($patterns['success_patterns']);
            $insights['recommendations'][] = [
                'type' => 'maintain',
                'message' => __('Continue using successful patterns', 'wp-ai-site-generator'),
                'patterns' => $success_insights,
            ];
        }

        // Analyze trends
        if (isset($patterns['temporal_patterns'])) {
            $insights['trends'] = $this->analyze_trends($patterns['temporal_patterns']);
        }

        // Generate priority scores
        $insights = $this->prioritize_insights($insights);

        return $insights;
    }

    /**
     * Auto-adjust prompt templates based on feedback
     *
     * @param array $insights Learning insights
     */
    public function auto_adjust_prompt_templates($insights) {
        $adjustments = [];

        // Get current prompt templates
        $templates = get_option('wp_ai_site_generator_prompt_templates', []);

        foreach ($templates as $key => $template) {
            $template_feedback = $this->get_template_feedback($key);

            if ($this->should_adjust_template($template_feedback)) {
                $adjusted_template = $this->adjust_template($template, $template_feedback, $insights);

                if ($adjusted_template !== $template) {
                    $adjustments[] = [
                        'template_key' => $key,
                        'original' => $template,
                        'adjusted' => $adjusted_template,
                        'reason' => $this->get_adjustment_reason($template_feedback),
                    ];

                    // Store adjustment for A/B testing
                    $this->create_ab_test_for_adjustment($key, $template, $adjusted_template);
                }
            }
        }

        // Log adjustments
        $this->log_template_adjustments($adjustments);

        return $adjustments;
    }

    /**
     * Apply learned improvements to prompt template
     *
     * @param string $template Original template
     * @param array $context Generation context
     * @return string Modified template
     */
    public function apply_learned_improvements($template, $context) {
        // Get learned improvements for this template type
        $improvements = get_option('wp_ai_site_generator_learned_improvements', []);
        $template_type = $context['type'] ?? 'default';

        if (isset($improvements[$template_type])) {
            foreach ($improvements[$template_type] as $improvement) {
                if ($this->should_apply_improvement($improvement, $context)) {
                    $template = $this->apply_improvement($template, $improvement);
                }
            }
        }

        return $template;
    }

    /**
     * Optimize generation parameters
     *
     * @param array $parameters Original parameters
     * @param array $context Generation context
     * @return array Optimized parameters
     */
    public function optimize_parameters($parameters, $context) {
        // Get learned optimizations
        $optimizations = get_option('wp_ai_site_generator_parameter_optimizations', []);

        // Apply relevant optimizations
        foreach ($optimizations as $optimization) {
            if ($this->is_optimization_applicable($optimization, $context)) {
                $parameters = $this->apply_optimization($parameters, $optimization);
            }
        }

        return $parameters;
    }

    /**
     * Analyze rating patterns
     *
     * @param array $feedback_data Feedback data
     * @return array Rating patterns
     */
    private function analyze_rating_patterns($feedback_data) {
        $patterns = [
            'distribution' => [],
            'correlations' => [],
            'anomalies' => [],
        ];

        // Calculate rating distribution
        foreach ($feedback_data as $feedback) {
            if (isset($feedback['ratings'])) {
                foreach ($feedback['ratings'] as $dimension => $rating) {
                    if (!isset($patterns['distribution'][$dimension])) {
                        $patterns['distribution'][$dimension] = [];
                    }
                    $patterns['distribution'][$dimension][] = $rating;
                }
            }
        }

        // Find correlations between dimensions
        $patterns['correlations'] = $this->calculate_rating_correlations($patterns['distribution']);

        // Detect anomalies
        $patterns['anomalies'] = $this->detect_rating_anomalies($patterns['distribution']);

        return $patterns;
    }

    /**
     * Analyze issue patterns
     *
     * @param array $feedback_data Feedback data
     * @return array Issue patterns
     */
    private function analyze_issue_patterns($feedback_data) {
        $patterns = [
            'common_issues' => [],
            'issue_clusters' => [],
            'severity_distribution' => [],
        ];

        // Extract and categorize issues
        foreach ($feedback_data as $feedback) {
            if (!empty($feedback['issue_type'])) {
                $patterns['common_issues'][$feedback['issue_type']] =
                    ($patterns['common_issues'][$feedback['issue_type']] ?? 0) + 1;

                $patterns['severity_distribution'][$feedback['severity']] =
                    ($patterns['severity_distribution'][$feedback['severity']] ?? 0) + 1;
            }
        }

        // Cluster related issues
        $patterns['issue_clusters'] = $this->cluster_issues($patterns['common_issues']);

        return $patterns;
    }

    /**
     * Analyze success patterns
     *
     * @param array $feedback_data Feedback data
     * @return array Success patterns
     */
    private function analyze_success_patterns($feedback_data) {
        $patterns = [];

        // Filter high-rated feedback
        $successful_feedback = array_filter($feedback_data, function($feedback) {
            return isset($feedback['ratings']['overall']) &&
                   $feedback['ratings']['overall'] >= $this->thresholds['high_rating_threshold'];
        });

        // Extract common characteristics
        foreach ($successful_feedback as $feedback) {
            $characteristics = $this->extract_content_characteristics($feedback);
            foreach ($characteristics as $char => $value) {
                if (!isset($patterns[$char])) {
                    $patterns[$char] = [];
                }
                $patterns[$char][] = $value;
            }
        }

        return $this->summarize_patterns($patterns);
    }

    /**
     * Analyze temporal patterns
     *
     * @param array $feedback_data Feedback data
     * @return array Temporal patterns
     */
    private function analyze_temporal_patterns($feedback_data) {
        $patterns = [
            'hourly' => [],
            'daily' => [],
            'weekly' => [],
            'trends' => [],
        ];

        foreach ($feedback_data as $feedback) {
            $timestamp = strtotime($feedback['created_at']);
            $hour = date('H', $timestamp);
            $day = date('N', $timestamp);
            $week = date('W', $timestamp);

            // Aggregate by time periods
            $patterns['hourly'][$hour][] = $feedback['ratings']['overall'] ?? 0;
            $patterns['daily'][$day][] = $feedback['ratings']['overall'] ?? 0;
            $patterns['weekly'][$week][] = $feedback['ratings']['overall'] ?? 0;
        }

        // Calculate trends
        $patterns['trends'] = $this->calculate_temporal_trends($patterns);

        return $patterns;
    }

    /**
     * Analyze user patterns
     *
     * @param array $feedback_data Feedback data
     * @return array User patterns
     */
    private function analyze_user_patterns($feedback_data) {
        $patterns = [
            'user_preferences' => [],
            'user_segments' => [],
            'satisfaction_by_user_type' => [],
        ];

        // Group by user
        $user_feedback = [];
        foreach ($feedback_data as $feedback) {
            $user_id = $feedback['user_id'] ?? 'anonymous';
            if (!isset($user_feedback[$user_id])) {
                $user_feedback[$user_id] = [];
            }
            $user_feedback[$user_id][] = $feedback;
        }

        // Analyze preferences per user
        foreach ($user_feedback as $user_id => $feedbacks) {
            $preferences = $this->extract_user_preferences($feedbacks);
            $patterns['user_preferences'][$user_id] = $preferences;
        }

        // Segment users
        $patterns['user_segments'] = $this->segment_users($patterns['user_preferences']);

        return $patterns;
    }

    /**
     * Analyze content patterns
     *
     * @param array $feedback_data Feedback data
     * @return array Content patterns
     */
    private function analyze_content_patterns($feedback_data) {
        $patterns = [
            'content_types' => [],
            'content_length' => [],
            'content_structure' => [],
            'keywords' => [],
        ];

        foreach ($feedback_data as $feedback) {
            // Analyze content characteristics
            if (isset($feedback['content'])) {
                $content_analysis = $this->analyze_content($feedback['content']);

                $patterns['content_types'][] = $content_analysis['type'];
                $patterns['content_length'][] = $content_analysis['length'];
                $patterns['content_structure'][] = $content_analysis['structure'];

                foreach ($content_analysis['keywords'] as $keyword) {
                    $patterns['keywords'][$keyword] = ($patterns['keywords'][$keyword] ?? 0) + 1;
                }
            }
        }

        return $patterns;
    }

    /**
     * Check if feedback is critical
     *
     * @param array $feedback_data Feedback data
     * @return bool Is critical
     */
    private function is_critical_feedback($feedback_data) {
        // Check for very low ratings
        if (isset($feedback_data['ratings']['overall']) &&
            $feedback_data['ratings']['overall'] <= 2) {
            return true;
        }

        // Check for critical issues
        if (isset($feedback_data['issue_severity']) &&
            $feedback_data['issue_severity'] === 'critical') {
            return true;
        }

        return false;
    }

    /**
     * Handle critical feedback
     *
     * @param array $feedback_data Feedback data
     */
    private function handle_critical_feedback($feedback_data) {
        // Notify administrators
        $this->notify_admins_critical_feedback($feedback_data);

        // Flag the generation
        $this->flag_generation($feedback_data['generation_id']);

        // Trigger immediate learning
        $this->trigger_immediate_learning($feedback_data);
    }

    /**
     * Update prompt score based on feedback
     *
     * @param array $feedback_data Feedback data
     */
    private function update_prompt_score($feedback_data) {
        // Get the prompt template used
        $generation = $this->get_generation_data($feedback_data['generation_id']);

        if ($generation && isset($generation['prompt_template'])) {
            $current_score = get_option('wp_ai_prompt_scores', []);
            $template_key = md5($generation['prompt_template']);

            if (!isset($current_score[$template_key])) {
                $current_score[$template_key] = [
                    'score' => 0,
                    'count' => 0,
                    'template' => $generation['prompt_template'],
                ];
            }

            // Update score
            $rating = $feedback_data['ratings']['overall'] ?? 3;
            $current_score[$template_key]['score'] =
                (($current_score[$template_key]['score'] * $current_score[$template_key]['count']) + $rating) /
                ($current_score[$template_key]['count'] + 1);
            $current_score[$template_key]['count']++;

            update_option('wp_ai_prompt_scores', $current_score);
        }
    }

    /**
     * Get recent feedback
     *
     * @param int $hours Hours to look back
     * @return array Feedback data
     */
    private function get_recent_feedback($hours) {
        global $wpdb;

        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, GROUP_CONCAT(CONCAT(r.dimension, ':', r.rating)) as ratings_str
                FROM {$wpdb->prefix}ai_feedback f
                LEFT JOIN {$wpdb->prefix}ai_feedback_ratings r ON f.id = r.feedback_id
                WHERE f.created_at >= %s
                GROUP BY f.id",
                $since
            ),
            ARRAY_A
        );
    }

    /**
     * Store learning results
     *
     * @param array $insights Insights
     */
    private function store_learning_results($insights) {
        update_option('wp_ai_site_generator_latest_insights', $insights);
        update_option('wp_ai_site_generator_last_learning', current_time('mysql'));

        // Store historical data
        $history = get_option('wp_ai_site_generator_learning_history', []);
        $history[] = [
            'timestamp' => current_time('mysql'),
            'insights' => $insights,
        ];

        // Keep only last 30 days
        $history = array_slice($history, -30);
        update_option('wp_ai_site_generator_learning_history', $history);
    }

    /**
     * Send learning report to admins
     *
     * @param array $insights Insights
     */
    private function send_learning_report($insights) {
        if (get_option('wp_ai_site_generator_send_learning_reports', true)) {
            $admins = get_users(['role' => 'administrator']);
            $report = $this->format_learning_report($insights);

            foreach ($admins as $admin) {
                wp_mail(
                    $admin->user_email,
                    __('AI Site Generator Learning Report', 'wp-ai-site-generator'),
                    $report,
                    ['Content-Type: text/html; charset=UTF-8']
                );
            }
        }
    }

    /**
     * Format learning report
     *
     * @param array $insights Insights
     * @return string HTML report
     */
    private function format_learning_report($insights) {
        ob_start();
        ?>
        <html>
        <body>
            <h2>AI Site Generator Learning Report</h2>

            <h3>Key Findings</h3>
            <ul>
                <?php foreach ($insights['key_findings'] as $finding): ?>
                    <li><?php echo esc_html($finding); ?></li>
                <?php endforeach; ?>
            </ul>

            <h3>Recommendations</h3>
            <ul>
                <?php foreach ($insights['recommendations'] as $recommendation): ?>
                    <li><?php echo esc_html($recommendation['message'] ?? $recommendation); ?></li>
                <?php endforeach; ?>
            </ul>

            <h3>Action Items</h3>
            <ul>
                <?php foreach ($insights['action_items'] as $action): ?>
                    <li><?php echo esc_html($action); ?></li>
                <?php endforeach; ?>
            </ul>

            <h3>Trends</h3>
            <ul>
                <?php foreach ($insights['trends'] as $trend): ?>
                    <li><?php echo esc_html($trend); ?></li>
                <?php endforeach; ?>
            </ul>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

/**
 * Content Quality Analyzer
 */
class ContentQualityAnalyzer {
    public function analyze($feedback_data) {
        // Analyze content quality patterns
        return [];
    }
}

/**
 * Prompt Effectiveness Analyzer
 */
class PromptEffectivenessAnalyzer {
    public function analyze($feedback_data) {
        // Analyze prompt effectiveness
        return [];
    }
}

/**
 * User Preference Analyzer
 */
class UserPreferenceAnalyzer {
    public function analyze($feedback_data) {
        // Analyze user preferences
        return [];
    }
}

/**
 * Issue Pattern Analyzer
 */
class IssuePatternAnalyzer {
    public function analyze($feedback_data) {
        // Analyze issue patterns
        return [];
    }
}

/**
 * Success Pattern Analyzer
 */
class SuccessPatternAnalyzer {
    public function analyze($feedback_data) {
        // Analyze success patterns
        return [];
    }
}