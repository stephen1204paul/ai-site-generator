<?php
/**
 * User Feedback System
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Includes;

use WP_AI_Site_Generator\Database\Feedback_DB_Handler;

/**
 * User Feedback System Class
 */
class User_Feedback {

    /**
     * Database handler instance
     *
     * @var Feedback_DB_Handler
     */
    private $db_handler;

    /**
     * Feedback types
     *
     * @var array
     */
    private $feedback_types = [
        'rating',
        'quality',
        'accuracy',
        'relevance',
        'usefulness',
        'issue_report',
        'feature_suggestion',
        'section_specific',
        'quick_feedback',
    ];

    /**
     * Feedback categories/tags
     *
     * @var array
     */
    private $feedback_categories = [
        'content_quality',
        'technical_accuracy',
        'relevance',
        'completeness',
        'creativity',
        'formatting',
        'tone_style',
        'length',
        'structure',
        'usefulness',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->db_handler = new Feedback_DB_Handler();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_submit_feedback', [$this, 'handle_feedback_submission']);
        add_action('wp_ajax_nopriv_submit_feedback', [$this, 'handle_feedback_submission']);
        add_action('wp_ajax_quick_feedback', [$this, 'handle_quick_feedback']);
        add_action('wp_ajax_report_issue', [$this, 'handle_issue_report']);
        add_filter('wp_ai_site_generator_after_generation', [$this, 'inject_feedback_prompt'], 10, 2);
    }

    /**
     * Submit comprehensive feedback
     *
     * @param array $feedback_data Feedback data
     * @return array Result
     */
    public function submit_feedback($feedback_data) {
        $validated_data = $this->validate_feedback($feedback_data);

        if (is_wp_error($validated_data)) {
            return [
                'success' => false,
                'message' => $validated_data->get_error_message(),
            ];
        }

        // Process multi-dimensional ratings
        $ratings = [
            'overall' => $validated_data['overall_rating'] ?? null,
            'quality' => $validated_data['quality_rating'] ?? null,
            'accuracy' => $validated_data['accuracy_rating'] ?? null,
            'relevance' => $validated_data['relevance_rating'] ?? null,
            'usefulness' => $validated_data['usefulness_rating'] ?? null,
        ];

        // Prepare feedback record
        $feedback_record = [
            'generation_id' => $validated_data['generation_id'],
            'user_id' => get_current_user_id() ?: 0,
            'ratings' => $ratings,
            'text_feedback' => sanitize_textarea_field($validated_data['text_feedback'] ?? ''),
            'categories' => $validated_data['categories'] ?? [],
            'section_id' => $validated_data['section_id'] ?? null,
            'is_anonymous' => $validated_data['is_anonymous'] ?? false,
            'metadata' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_hash' => $this->hash_ip($_SERVER['REMOTE_ADDR'] ?? ''),
                'timestamp' => current_time('mysql'),
                'context' => $validated_data['context'] ?? [],
            ],
        ];

        // Store feedback
        $result = $this->db_handler->insert_feedback($feedback_record);

        if ($result) {
            // Trigger learning system
            do_action('wp_ai_site_generator_feedback_submitted', $feedback_record);

            // Update generation statistics
            $this->update_generation_stats($validated_data['generation_id'], $ratings);

            return [
                'success' => true,
                'message' => __('Thank you for your feedback!', 'wp-ai-site-generator'),
                'feedback_id' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to save feedback. Please try again.', 'wp-ai-site-generator'),
        ];
    }

    /**
     * Handle quick feedback (thumbs up/down)
     *
     * @param int $generation_id Generation ID
     * @param bool $is_positive Positive or negative feedback
     * @param string $section_id Optional section ID
     * @return array Result
     */
    public function submit_quick_feedback($generation_id, $is_positive, $section_id = null) {
        $feedback_data = [
            'generation_id' => $generation_id,
            'user_id' => get_current_user_id() ?: 0,
            'type' => 'quick_feedback',
            'value' => $is_positive ? 1 : -1,
            'section_id' => $section_id,
            'timestamp' => current_time('mysql'),
            'ip_hash' => $this->hash_ip($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        $result = $this->db_handler->insert_quick_feedback($feedback_data);

        if ($result) {
            // Update quick feedback stats
            $this->update_quick_feedback_stats($generation_id, $is_positive);

            return [
                'success' => true,
                'message' => __('Feedback recorded', 'wp-ai-site-generator'),
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to record feedback', 'wp-ai-site-generator'),
        ];
    }

    /**
     * Report an issue with generated content
     *
     * @param array $issue_data Issue data
     * @return array Result
     */
    public function report_issue($issue_data) {
        $validated_data = $this->validate_issue_report($issue_data);

        if (is_wp_error($validated_data)) {
            return [
                'success' => false,
                'message' => $validated_data->get_error_message(),
            ];
        }

        $issue_record = [
            'generation_id' => $validated_data['generation_id'],
            'user_id' => get_current_user_id() ?: 0,
            'issue_type' => $validated_data['issue_type'],
            'severity' => $validated_data['severity'] ?? 'medium',
            'description' => sanitize_textarea_field($validated_data['description']),
            'affected_section' => $validated_data['affected_section'] ?? null,
            'steps_to_reproduce' => $validated_data['steps_to_reproduce'] ?? '',
            'expected_behavior' => $validated_data['expected_behavior'] ?? '',
            'actual_behavior' => $validated_data['actual_behavior'] ?? '',
            'metadata' => [
                'browser' => $this->detect_browser(),
                'timestamp' => current_time('mysql'),
                'screenshot' => $validated_data['screenshot'] ?? null,
            ],
        ];

        $result = $this->db_handler->insert_issue_report($issue_record);

        if ($result) {
            // Notify administrators
            $this->notify_admins_of_issue($issue_record);

            return [
                'success' => true,
                'message' => __('Issue reported successfully. We\'ll look into it.', 'wp-ai-site-generator'),
                'issue_id' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to report issue', 'wp-ai-site-generator'),
        ];
    }

    /**
     * Submit feature suggestion
     *
     * @param array $suggestion_data Suggestion data
     * @return array Result
     */
    public function submit_feature_suggestion($suggestion_data) {
        $validated_data = [
            'user_id' => get_current_user_id() ?: 0,
            'title' => sanitize_text_field($suggestion_data['title']),
            'description' => sanitize_textarea_field($suggestion_data['description']),
            'category' => $suggestion_data['category'] ?? 'general',
            'priority' => $suggestion_data['priority'] ?? 'medium',
            'use_case' => sanitize_textarea_field($suggestion_data['use_case'] ?? ''),
            'timestamp' => current_time('mysql'),
        ];

        $result = $this->db_handler->insert_feature_suggestion($validated_data);

        if ($result) {
            return [
                'success' => true,
                'message' => __('Thank you for your suggestion!', 'wp-ai-site-generator'),
                'suggestion_id' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to save suggestion', 'wp-ai-site-generator'),
        ];
    }

    /**
     * Get feedback statistics for a generation
     *
     * @param int $generation_id Generation ID
     * @return array Statistics
     */
    public function get_generation_feedback_stats($generation_id) {
        $stats = $this->db_handler->get_feedback_stats($generation_id);

        return [
            'total_feedback' => $stats['total'] ?? 0,
            'average_ratings' => [
                'overall' => $stats['avg_overall'] ?? 0,
                'quality' => $stats['avg_quality'] ?? 0,
                'accuracy' => $stats['avg_accuracy'] ?? 0,
                'relevance' => $stats['avg_relevance'] ?? 0,
                'usefulness' => $stats['avg_usefulness'] ?? 0,
            ],
            'rating_distribution' => $stats['distribution'] ?? [],
            'quick_feedback' => [
                'positive' => $stats['positive_quick'] ?? 0,
                'negative' => $stats['negative_quick'] ?? 0,
            ],
            'issues_reported' => $stats['issues_count'] ?? 0,
            'top_categories' => $stats['top_categories'] ?? [],
            'sentiment' => $this->analyze_sentiment($stats['text_feedback'] ?? []),
        ];
    }

    /**
     * Get aggregated feedback insights
     *
     * @param array $filters Filters for insights
     * @return array Insights
     */
    public function get_feedback_insights($filters = []) {
        $period = $filters['period'] ?? '30days';
        $generation_type = $filters['type'] ?? 'all';

        $insights = $this->db_handler->get_aggregated_insights($period, $generation_type);

        return [
            'performance_trends' => $this->calculate_trends($insights),
            'problem_areas' => $this->identify_problem_areas($insights),
            'improvement_suggestions' => $this->generate_improvement_suggestions($insights),
            'user_satisfaction_index' => $this->calculate_satisfaction_index($insights),
            'top_performing_prompts' => $insights['top_prompts'] ?? [],
            'common_issues' => $insights['common_issues'] ?? [],
            'feature_requests' => $insights['top_feature_requests'] ?? [],
        ];
    }

    /**
     * Validate feedback data
     *
     * @param array $data Feedback data
     * @return array|WP_Error Validated data or error
     */
    private function validate_feedback($data) {
        $errors = [];

        // Required fields
        if (empty($data['generation_id'])) {
            $errors[] = __('Generation ID is required', 'wp-ai-site-generator');
        }

        // Validate ratings (1-5 scale)
        $rating_fields = ['overall_rating', 'quality_rating', 'accuracy_rating', 'relevance_rating', 'usefulness_rating'];
        foreach ($rating_fields as $field) {
            if (isset($data[$field]) && ($data[$field] < 1 || $data[$field] > 5)) {
                $errors[] = sprintf(__('Invalid %s value', 'wp-ai-site-generator'), $field);
            }
        }

        // Validate categories
        if (!empty($data['categories'])) {
            $invalid_categories = array_diff($data['categories'], $this->feedback_categories);
            if (!empty($invalid_categories)) {
                $errors[] = __('Invalid feedback categories', 'wp-ai-site-generator');
            }
        }

        // GDPR compliance check
        if (!$data['is_anonymous'] && !$this->check_consent($data)) {
            $errors[] = __('User consent required for non-anonymous feedback', 'wp-ai-site-generator');
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', implode('. ', $errors));
        }

        return $data;
    }

    /**
     * Validate issue report data
     *
     * @param array $data Issue data
     * @return array|WP_Error Validated data or error
     */
    private function validate_issue_report($data) {
        if (empty($data['generation_id']) || empty($data['description'])) {
            return new \WP_Error('validation_failed', __('Generation ID and description are required', 'wp-ai-site-generator'));
        }

        $valid_issue_types = ['bug', 'error', 'quality', 'formatting', 'content', 'performance', 'other'];
        if (!in_array($data['issue_type'], $valid_issue_types)) {
            return new \WP_Error('validation_failed', __('Invalid issue type', 'wp-ai-site-generator'));
        }

        return $data;
    }

    /**
     * Update generation statistics
     *
     * @param int $generation_id Generation ID
     * @param array $ratings Ratings
     */
    private function update_generation_stats($generation_id, $ratings) {
        $this->db_handler->update_generation_stats($generation_id, $ratings);
    }

    /**
     * Update quick feedback statistics
     *
     * @param int $generation_id Generation ID
     * @param bool $is_positive Positive feedback
     */
    private function update_quick_feedback_stats($generation_id, $is_positive) {
        $this->db_handler->update_quick_feedback_stats($generation_id, $is_positive);
    }

    /**
     * Hash IP address for privacy
     *
     * @param string $ip IP address
     * @return string Hashed IP
     */
    private function hash_ip($ip) {
        return hash('sha256', $ip . wp_salt());
    }

    /**
     * Detect browser information
     *
     * @return array Browser info
     */
    private function detect_browser() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $browser = [
            'name' => 'Unknown',
            'version' => '',
            'platform' => 'Unknown',
        ];

        // Simple browser detection (can be enhanced)
        if (preg_match('/Chrome\/([0-9.]+)/', $user_agent, $matches)) {
            $browser['name'] = 'Chrome';
            $browser['version'] = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $user_agent, $matches)) {
            $browser['name'] = 'Firefox';
            $browser['version'] = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $user_agent, $matches)) {
            $browser['name'] = 'Safari';
            $browser['version'] = $matches[1];
        }

        // Platform detection
        if (strpos($user_agent, 'Windows') !== false) {
            $browser['platform'] = 'Windows';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            $browser['platform'] = 'Mac';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            $browser['platform'] = 'Linux';
        }

        return $browser;
    }

    /**
     * Check user consent for data collection
     *
     * @param array $data Feedback data
     * @return bool Has consent
     */
    private function check_consent($data) {
        // Check for consent flag or cookie
        return isset($data['consent_given']) && $data['consent_given'] === true;
    }

    /**
     * Analyze sentiment from text feedback
     *
     * @param array $feedback_texts Text feedback array
     * @return array Sentiment analysis
     */
    private function analyze_sentiment($feedback_texts) {
        // Basic sentiment analysis (can be enhanced with NLP)
        $positive_words = ['excellent', 'great', 'good', 'perfect', 'amazing', 'helpful', 'useful'];
        $negative_words = ['bad', 'poor', 'terrible', 'awful', 'useless', 'wrong', 'incorrect'];

        $sentiment_scores = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
        ];

        foreach ($feedback_texts as $text) {
            $text_lower = strtolower($text);
            $positive_count = 0;
            $negative_count = 0;

            foreach ($positive_words as $word) {
                $positive_count += substr_count($text_lower, $word);
            }

            foreach ($negative_words as $word) {
                $negative_count += substr_count($text_lower, $word);
            }

            if ($positive_count > $negative_count) {
                $sentiment_scores['positive']++;
            } elseif ($negative_count > $positive_count) {
                $sentiment_scores['negative']++;
            } else {
                $sentiment_scores['neutral']++;
            }
        }

        return $sentiment_scores;
    }

    /**
     * Calculate performance trends
     *
     * @param array $insights Insights data
     * @return array Trends
     */
    private function calculate_trends($insights) {
        // Calculate trend indicators
        return [
            'rating_trend' => $this->calculate_rating_trend($insights),
            'feedback_volume_trend' => $this->calculate_volume_trend($insights),
            'issue_trend' => $this->calculate_issue_trend($insights),
            'satisfaction_trend' => $this->calculate_satisfaction_trend($insights),
        ];
    }

    /**
     * Identify problem areas from feedback
     *
     * @param array $insights Insights data
     * @return array Problem areas
     */
    private function identify_problem_areas($insights) {
        $problems = [];

        // Check for low ratings
        if (isset($insights['average_ratings'])) {
            foreach ($insights['average_ratings'] as $dimension => $rating) {
                if ($rating < 3.0) {
                    $problems[] = [
                        'area' => $dimension,
                        'severity' => 'high',
                        'average_rating' => $rating,
                        'recommendation' => $this->get_improvement_recommendation($dimension, $rating),
                    ];
                }
            }
        }

        return $problems;
    }

    /**
     * Generate improvement suggestions
     *
     * @param array $insights Insights data
     * @return array Suggestions
     */
    private function generate_improvement_suggestions($insights) {
        $suggestions = [];

        // Analyze patterns and generate suggestions
        if (isset($insights['common_issues'])) {
            foreach ($insights['common_issues'] as $issue) {
                $suggestions[] = $this->generate_suggestion_for_issue($issue);
            }
        }

        return $suggestions;
    }

    /**
     * Calculate user satisfaction index
     *
     * @param array $insights Insights data
     * @return float Satisfaction index (0-100)
     */
    private function calculate_satisfaction_index($insights) {
        $factors = [
            'average_rating' => isset($insights['average_ratings']['overall']) ? $insights['average_ratings']['overall'] / 5 * 40 : 0,
            'positive_feedback_ratio' => isset($insights['quick_feedback']) ?
                ($insights['quick_feedback']['positive'] / max($insights['quick_feedback']['total'], 1)) * 30 : 0,
            'issue_rate' => isset($insights['issue_rate']) ? (1 - $insights['issue_rate']) * 20 : 20,
            'repeat_usage' => isset($insights['repeat_usage_rate']) ? $insights['repeat_usage_rate'] * 10 : 0,
        ];

        return array_sum($factors);
    }

    /**
     * Calculate rating trend
     *
     * @param array $insights Insights data
     * @return string Trend direction
     */
    private function calculate_rating_trend($insights) {
        if (!isset($insights['rating_history'])) {
            return 'stable';
        }

        $history = $insights['rating_history'];
        $recent = array_slice($history, -7);
        $previous = array_slice($history, -14, 7);

        $recent_avg = array_sum($recent) / count($recent);
        $previous_avg = array_sum($previous) / count($previous);

        if ($recent_avg > $previous_avg + 0.2) {
            return 'improving';
        } elseif ($recent_avg < $previous_avg - 0.2) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Calculate volume trend
     *
     * @param array $insights Insights data
     * @return string Trend direction
     */
    private function calculate_volume_trend($insights) {
        // Similar implementation to rating trend
        return 'stable';
    }

    /**
     * Calculate issue trend
     *
     * @param array $insights Insights data
     * @return string Trend direction
     */
    private function calculate_issue_trend($insights) {
        // Similar implementation to rating trend
        return 'stable';
    }

    /**
     * Calculate satisfaction trend
     *
     * @param array $insights Insights data
     * @return string Trend direction
     */
    private function calculate_satisfaction_trend($insights) {
        // Similar implementation to rating trend
        return 'stable';
    }

    /**
     * Get improvement recommendation for dimension
     *
     * @param string $dimension Rating dimension
     * @param float $rating Current rating
     * @return string Recommendation
     */
    private function get_improvement_recommendation($dimension, $rating) {
        $recommendations = [
            'quality' => __('Consider reviewing prompt templates and adding more quality checks', 'wp-ai-site-generator'),
            'accuracy' => __('Implement fact-checking and validation steps in the generation process', 'wp-ai-site-generator'),
            'relevance' => __('Improve context understanding and user intent detection', 'wp-ai-site-generator'),
            'usefulness' => __('Focus on practical, actionable content generation', 'wp-ai-site-generator'),
        ];

        return $recommendations[$dimension] ?? __('Review and optimize generation parameters', 'wp-ai-site-generator');
    }

    /**
     * Generate suggestion for issue
     *
     * @param array $issue Issue data
     * @return array Suggestion
     */
    private function generate_suggestion_for_issue($issue) {
        return [
            'issue_type' => $issue['type'],
            'frequency' => $issue['count'],
            'suggestion' => $this->get_issue_suggestion($issue['type']),
            'priority' => $this->calculate_issue_priority($issue),
        ];
    }

    /**
     * Get suggestion for issue type
     *
     * @param string $issue_type Issue type
     * @return string Suggestion
     */
    private function get_issue_suggestion($issue_type) {
        $suggestions = [
            'formatting' => __('Review and update content formatting templates', 'wp-ai-site-generator'),
            'length' => __('Adjust content length parameters based on user preferences', 'wp-ai-site-generator'),
            'tone' => __('Fine-tune tone and style instructions in prompts', 'wp-ai-site-generator'),
            'technical' => __('Enhance technical accuracy validation', 'wp-ai-site-generator'),
        ];

        return $suggestions[$issue_type] ?? __('Investigate and address reported issues', 'wp-ai-site-generator');
    }

    /**
     * Calculate issue priority
     *
     * @param array $issue Issue data
     * @return string Priority level
     */
    private function calculate_issue_priority($issue) {
        if ($issue['count'] > 10 || $issue['severity'] === 'critical') {
            return 'high';
        } elseif ($issue['count'] > 5) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Notify administrators of critical issues
     *
     * @param array $issue_record Issue record
     */
    private function notify_admins_of_issue($issue_record) {
        if ($issue_record['severity'] === 'critical') {
            $admins = get_users(['role' => 'administrator']);
            $subject = __('Critical Issue Reported in AI Site Generator', 'wp-ai-site-generator');
            $message = sprintf(
                __('A critical issue has been reported:\n\nType: %s\nDescription: %s\n\nPlease review in the admin dashboard.', 'wp-ai-site-generator'),
                $issue_record['issue_type'],
                $issue_record['description']
            );

            foreach ($admins as $admin) {
                wp_mail($admin->user_email, $subject, $message);
            }
        }
    }

    /**
     * Handle AJAX feedback submission
     */
    public function handle_feedback_submission() {
        check_ajax_referer('wp_ai_site_generator_nonce', 'nonce');

        $feedback_data = $_POST['feedback_data'] ?? [];
        $result = $this->submit_feedback($feedback_data);

        wp_send_json($result);
    }

    /**
     * Handle AJAX quick feedback
     */
    public function handle_quick_feedback() {
        check_ajax_referer('wp_ai_site_generator_nonce', 'nonce');

        $generation_id = intval($_POST['generation_id'] ?? 0);
        $is_positive = $_POST['is_positive'] === 'true';
        $section_id = $_POST['section_id'] ?? null;

        $result = $this->submit_quick_feedback($generation_id, $is_positive, $section_id);

        wp_send_json($result);
    }

    /**
     * Handle AJAX issue report
     */
    public function handle_issue_report() {
        check_ajax_referer('wp_ai_site_generator_nonce', 'nonce');

        $issue_data = $_POST['issue_data'] ?? [];
        $result = $this->report_issue($issue_data);

        wp_send_json($result);
    }

    /**
     * Inject feedback prompt after generation
     *
     * @param string $content Generated content
     * @param array $generation_data Generation data
     * @return string Modified content
     */
    public function inject_feedback_prompt($content, $generation_data) {
        if (get_option('wp_ai_site_generator_prompt_feedback', true)) {
            $feedback_html = $this->render_feedback_prompt($generation_data['id']);
            $content .= $feedback_html;
        }

        return $content;
    }

    /**
     * Render feedback prompt HTML
     *
     * @param int $generation_id Generation ID
     * @return string HTML
     */
    private function render_feedback_prompt($generation_id) {
        ob_start();
        ?>
        <div class="wp-ai-feedback-prompt" data-generation-id="<?php echo esc_attr($generation_id); ?>">
            <div class="feedback-prompt-header">
                <h4><?php _e('How was this content?', 'wp-ai-site-generator'); ?></h4>
                <div class="quick-feedback-buttons">
                    <button class="feedback-thumbs-up" data-feedback="positive">
                        <span class="dashicons dashicons-thumbs-up"></span>
                    </button>
                    <button class="feedback-thumbs-down" data-feedback="negative">
                        <span class="dashicons dashicons-thumbs-down"></span>
                    </button>
                </div>
            </div>
            <button class="feedback-detailed-btn">
                <?php _e('Provide detailed feedback', 'wp-ai-site-generator'); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
}