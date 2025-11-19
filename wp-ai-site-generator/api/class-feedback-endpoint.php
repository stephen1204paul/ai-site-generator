<?php
/**
 * Feedback REST API Endpoints
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_AI_Site_Generator\Includes\User_Feedback;
use WP_AI_Site_Generator\Includes\Feedback_Learner;
use WP_AI_Site_Generator\Database\Feedback_DB_Handler;

/**
 * Feedback REST API Endpoint Class
 */
class Feedback_Endpoint extends WP_REST_Controller {

    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace = 'wp-ai-site-generator/v1';

    /**
     * Rest base
     *
     * @var string
     */
    protected $rest_base = 'feedback';

    /**
     * User feedback handler
     *
     * @var User_Feedback
     */
    private $feedback_handler;

    /**
     * Feedback learner
     *
     * @var Feedback_Learner
     */
    private $feedback_learner;

    /**
     * Database handler
     *
     * @var Feedback_DB_Handler
     */
    private $db_handler;

    /**
     * Constructor
     */
    public function __construct() {
        $this->feedback_handler = new User_Feedback();
        $this->feedback_learner = new Feedback_Learner();
        $this->db_handler = new Feedback_DB_Handler();
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // Submit feedback
        register_rest_route($this->namespace, '/' . $this->rest_base . '/submit', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submit_feedback'],
                'permission_callback' => [$this, 'submit_feedback_permissions_check'],
                'args' => $this->get_submit_feedback_args(),
            ],
        ]);

        // Quick feedback (thumbs up/down)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/quick', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submit_quick_feedback'],
                'permission_callback' => [$this, 'submit_feedback_permissions_check'],
                'args' => $this->get_quick_feedback_args(),
            ],
        ]);

        // Report issue
        register_rest_route($this->namespace, '/' . $this->rest_base . '/report-issue', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'report_issue'],
                'permission_callback' => [$this, 'submit_feedback_permissions_check'],
                'args' => $this->get_report_issue_args(),
            ],
        ]);

        // Submit feature suggestion
        register_rest_route($this->namespace, '/' . $this->rest_base . '/suggest-feature', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'suggest_feature'],
                'permission_callback' => [$this, 'submit_feedback_permissions_check'],
                'args' => $this->get_suggest_feature_args(),
            ],
        ]);

        // Get feedback statistics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_feedback_stats'],
                'permission_callback' => [$this, 'get_stats_permissions_check'],
                'args' => $this->get_stats_args(),
            ],
        ]);

        // Get feedback insights
        register_rest_route($this->namespace, '/' . $this->rest_base . '/insights', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_insights'],
                'permission_callback' => [$this, 'get_insights_permissions_check'],
                'args' => $this->get_insights_args(),
            ],
        ]);

        // Get feedback trends
        register_rest_route($this->namespace, '/' . $this->rest_base . '/trends', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_trends'],
                'permission_callback' => [$this, 'get_insights_permissions_check'],
                'args' => $this->get_trends_args(),
            ],
        ]);

        // Get improvement suggestions
        register_rest_route($this->namespace, '/' . $this->rest_base . '/suggestions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_suggestions'],
                'permission_callback' => [$this, 'get_insights_permissions_check'],
                'args' => $this->get_suggestions_args(),
            ],
        ]);

        // Get A/B test results
        register_rest_route($this->namespace, '/' . $this->rest_base . '/ab-tests', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_ab_tests'],
                'permission_callback' => [$this, 'get_insights_permissions_check'],
            ],
        ]);

        // Get specific generation feedback
        register_rest_route($this->namespace, '/' . $this->rest_base . '/generation/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_generation_feedback'],
                'permission_callback' => [$this, 'get_stats_permissions_check'],
                'args' => [
                    'id' => [
                        'description' => __('Generation ID', 'wp-ai-site-generator'),
                        'type' => 'integer',
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
        ]);

        // Export feedback data
        register_rest_route($this->namespace, '/' . $this->rest_base . '/export', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'export_feedback'],
                'permission_callback' => [$this, 'export_permissions_check'],
                'args' => $this->get_export_args(),
            ],
        ]);
    }

    /**
     * Submit feedback
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function submit_feedback($request) {
        $feedback_data = [
            'generation_id' => $request->get_param('generation_id'),
            'overall_rating' => $request->get_param('overall_rating'),
            'quality_rating' => $request->get_param('quality_rating'),
            'accuracy_rating' => $request->get_param('accuracy_rating'),
            'relevance_rating' => $request->get_param('relevance_rating'),
            'usefulness_rating' => $request->get_param('usefulness_rating'),
            'text_feedback' => $request->get_param('text_feedback'),
            'categories' => $request->get_param('categories'),
            'section_id' => $request->get_param('section_id'),
            'is_anonymous' => $request->get_param('is_anonymous'),
            'consent_given' => $request->get_param('consent_given'),
            'context' => $request->get_param('context'),
        ];

        $result = $this->feedback_handler->submit_feedback($feedback_data);

        if ($result['success']) {
            return new WP_REST_Response($result, 201);
        }

        return new WP_REST_Response($result, 400);
    }

    /**
     * Submit quick feedback
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function submit_quick_feedback($request) {
        $generation_id = $request->get_param('generation_id');
        $is_positive = $request->get_param('is_positive');
        $section_id = $request->get_param('section_id');

        $result = $this->feedback_handler->submit_quick_feedback($generation_id, $is_positive, $section_id);

        if ($result['success']) {
            return new WP_REST_Response($result, 201);
        }

        return new WP_REST_Response($result, 400);
    }

    /**
     * Report an issue
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function report_issue($request) {
        $issue_data = [
            'generation_id' => $request->get_param('generation_id'),
            'issue_type' => $request->get_param('issue_type'),
            'severity' => $request->get_param('severity'),
            'description' => $request->get_param('description'),
            'affected_section' => $request->get_param('affected_section'),
            'steps_to_reproduce' => $request->get_param('steps_to_reproduce'),
            'expected_behavior' => $request->get_param('expected_behavior'),
            'actual_behavior' => $request->get_param('actual_behavior'),
            'screenshot' => $request->get_param('screenshot'),
        ];

        $result = $this->feedback_handler->report_issue($issue_data);

        if ($result['success']) {
            return new WP_REST_Response($result, 201);
        }

        return new WP_REST_Response($result, 400);
    }

    /**
     * Suggest a feature
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function suggest_feature($request) {
        $suggestion_data = [
            'title' => $request->get_param('title'),
            'description' => $request->get_param('description'),
            'category' => $request->get_param('category'),
            'priority' => $request->get_param('priority'),
            'use_case' => $request->get_param('use_case'),
        ];

        $result = $this->feedback_handler->submit_feature_suggestion($suggestion_data);

        if ($result['success']) {
            return new WP_REST_Response($result, 201);
        }

        return new WP_REST_Response($result, 400);
    }

    /**
     * Get feedback statistics
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_feedback_stats($request) {
        $generation_id = $request->get_param('generation_id');
        $period = $request->get_param('period');

        if ($generation_id) {
            $stats = $this->feedback_handler->get_generation_feedback_stats($generation_id);
        } else {
            $filters = [
                'period' => $period ?: '30days',
                'type' => $request->get_param('type'),
            ];
            $stats = $this->feedback_handler->get_feedback_insights($filters);
        }

        return new WP_REST_Response($stats, 200);
    }

    /**
     * Get AI-generated insights
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_insights($request) {
        $period = $request->get_param('period') ?: '30days';
        $type = $request->get_param('type') ?: 'all';

        $insights = $this->db_handler->get_aggregated_insights($period, $type);
        $analyzed_insights = $this->feedback_learner->generate_insights(['insights' => $insights]);

        return new WP_REST_Response($analyzed_insights, 200);
    }

    /**
     * Get trend data
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_trends($request) {
        global $wpdb;

        $period = $request->get_param('period') ?: '30days';
        $metric = $request->get_param('metric') ?: 'rating';
        $date_from = $this->calculate_date_from($period);

        $trends = [];

        switch ($metric) {
            case 'rating':
                $trends = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            DATE(f.created_at) as date,
                            AVG(r.rating) as value
                        FROM {$wpdb->prefix}ai_feedback f
                        JOIN {$wpdb->prefix}ai_feedback_ratings r ON f.id = r.feedback_id
                        WHERE f.created_at >= %s AND r.dimension = 'overall'
                        GROUP BY DATE(f.created_at)
                        ORDER BY date ASC",
                        $date_from
                    ),
                    ARRAY_A
                );
                break;

            case 'volume':
                $trends = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            DATE(created_at) as date,
                            COUNT(*) as value
                        FROM {$wpdb->prefix}ai_feedback
                        WHERE created_at >= %s
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC",
                        $date_from
                    ),
                    ARRAY_A
                );
                break;

            case 'issues':
                $trends = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            DATE(created_at) as date,
                            COUNT(*) as value
                        FROM {$wpdb->prefix}ai_issues
                        WHERE created_at >= %s
                        GROUP BY DATE(created_at)
                        ORDER BY date ASC",
                        $date_from
                    ),
                    ARRAY_A
                );
                break;

            case 'satisfaction':
                $trends = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT
                            DATE(timestamp) as date,
                            SUM(CASE WHEN value > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as value
                        FROM {$wpdb->prefix}ai_quick_feedback
                        WHERE timestamp >= %s
                        GROUP BY DATE(timestamp)
                        ORDER BY date ASC",
                        $date_from
                    ),
                    ARRAY_A
                );
                break;
        }

        // Add trend analysis
        $trend_analysis = $this->analyze_trend($trends);

        return new WP_REST_Response([
            'data' => $trends,
            'analysis' => $trend_analysis,
        ], 200);
    }

    /**
     * Get improvement suggestions
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_suggestions($request) {
        $context = $request->get_param('context') ?: 'general';
        $limit = $request->get_param('limit') ?: 10;

        // Get recent feedback
        $recent_feedback = $this->get_recent_feedback_data();

        // Analyze patterns
        $patterns = $this->feedback_learner->analyze_feedback_patterns($recent_feedback);

        // Generate suggestions
        $suggestions = [];

        // Common issues
        $common_issues = $this->feedback_learner->identify_common_issues($recent_feedback);
        foreach ($common_issues as $issue) {
            $suggestions[] = [
                'type' => 'issue_fix',
                'priority' => $issue['severity'],
                'suggestion' => $issue['recommendation'],
                'affected_area' => $issue['issue'],
                'frequency' => $issue['frequency'],
            ];
        }

        // Top-rated prompts
        $top_prompts = $this->feedback_learner->get_top_rated_prompts();
        if (!empty($top_prompts)) {
            $suggestions[] = [
                'type' => 'best_practice',
                'priority' => 'medium',
                'suggestion' => __('Use patterns from top-rated prompts', 'wp-ai-site-generator'),
                'details' => array_slice($top_prompts, 0, 3),
            ];
        }

        // Prompt improvements
        if ($context === 'prompt') {
            $prompt_template = $request->get_param('prompt_template');
            if ($prompt_template) {
                $prompt_suggestions = $this->feedback_learner->suggest_prompt_improvements(
                    $prompt_template,
                    $recent_feedback
                );
                $suggestions = array_merge($suggestions, $prompt_suggestions);
            }
        }

        // Sort by priority
        usort($suggestions, function($a, $b) {
            $priority_order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return ($priority_order[$a['priority']] ?? 4) - ($priority_order[$b['priority']] ?? 4);
        });

        // Limit results
        $suggestions = array_slice($suggestions, 0, $limit);

        return new WP_REST_Response($suggestions, 200);
    }

    /**
     * Get A/B test results
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_ab_tests($request) {
        global $wpdb;

        $status = $request->get_param('status') ?: 'all';

        $where = '';
        if ($status !== 'all') {
            $where = $wpdb->prepare("WHERE status = %s", $status);
        }

        $tests = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ai_ab_tests $where ORDER BY created_at DESC",
            ARRAY_A
        );

        // Enhance with aggregated results
        foreach ($tests as &$test) {
            $test['aggregated_results'] = $this->feedback_learner->aggregate_ab_test_results($test['id']);
        }

        return new WP_REST_Response($tests, 200);
    }

    /**
     * Get generation-specific feedback
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function get_generation_feedback($request) {
        $generation_id = $request->get_param('id');

        $stats = $this->feedback_handler->get_generation_feedback_stats($generation_id);

        // Get individual feedback records
        global $wpdb;
        $feedback_records = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT f.*, GROUP_CONCAT(CONCAT(r.dimension, ':', r.rating)) as ratings_str
                FROM {$wpdb->prefix}ai_feedback f
                LEFT JOIN {$wpdb->prefix}ai_feedback_ratings r ON f.id = r.feedback_id
                WHERE f.generation_id = %d
                GROUP BY f.id
                ORDER BY f.created_at DESC",
                $generation_id
            ),
            ARRAY_A
        );

        // Parse ratings
        foreach ($feedback_records as &$record) {
            $record['ratings'] = [];
            if ($record['ratings_str']) {
                foreach (explode(',', $record['ratings_str']) as $rating_pair) {
                    list($dimension, $rating) = explode(':', $rating_pair);
                    $record['ratings'][$dimension] = floatval($rating);
                }
            }
            unset($record['ratings_str']);
        }

        return new WP_REST_Response([
            'statistics' => $stats,
            'feedback_records' => $feedback_records,
        ], 200);
    }

    /**
     * Export feedback data
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function export_feedback($request) {
        $format = $request->get_param('format') ?: 'csv';
        $period = $request->get_param('period') ?: '30days';
        $type = $request->get_param('type') ?: 'all';

        $data = $this->gather_export_data($period, $type);

        switch ($format) {
            case 'csv':
                $export = $this->export_as_csv($data);
                break;
            case 'json':
                $export = $this->export_as_json($data);
                break;
            case 'xlsx':
                $export = $this->export_as_xlsx($data);
                break;
            default:
                return new WP_REST_Response([
                    'error' => __('Invalid export format', 'wp-ai-site-generator'),
                ], 400);
        }

        return new WP_REST_Response([
            'download_url' => $export['url'],
            'filename' => $export['filename'],
            'size' => $export['size'],
        ], 200);
    }

    /**
     * Permission check for submitting feedback
     *
     * @return bool
     */
    public function submit_feedback_permissions_check() {
        // Allow logged-in users and optionally anonymous users
        if (is_user_logged_in()) {
            return true;
        }

        // Check if anonymous feedback is allowed
        return get_option('wp_ai_site_generator_allow_anonymous_feedback', true);
    }

    /**
     * Permission check for viewing stats
     *
     * @return bool
     */
    public function get_stats_permissions_check() {
        return current_user_can('edit_posts');
    }

    /**
     * Permission check for viewing insights
     *
     * @return bool
     */
    public function get_insights_permissions_check() {
        return current_user_can('manage_options');
    }

    /**
     * Permission check for exporting data
     *
     * @return bool
     */
    public function export_permissions_check() {
        return current_user_can('manage_options');
    }

    /**
     * Get submit feedback arguments
     *
     * @return array Arguments
     */
    private function get_submit_feedback_args() {
        return [
            'generation_id' => [
                'description' => __('Generation ID', 'wp-ai-site-generator'),
                'type' => 'integer',
                'required' => true,
            ],
            'overall_rating' => [
                'description' => __('Overall rating (1-5)', 'wp-ai-site-generator'),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ],
            'quality_rating' => [
                'description' => __('Quality rating (1-5)', 'wp-ai-site-generator'),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ],
            'accuracy_rating' => [
                'description' => __('Accuracy rating (1-5)', 'wp-ai-site-generator'),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ],
            'relevance_rating' => [
                'description' => __('Relevance rating (1-5)', 'wp-ai-site-generator'),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ],
            'usefulness_rating' => [
                'description' => __('Usefulness rating (1-5)', 'wp-ai-site-generator'),
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5,
            ],
            'text_feedback' => [
                'description' => __('Text feedback', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'categories' => [
                'description' => __('Feedback categories', 'wp-ai-site-generator'),
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
            'section_id' => [
                'description' => __('Section ID', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'is_anonymous' => [
                'description' => __('Submit anonymously', 'wp-ai-site-generator'),
                'type' => 'boolean',
                'default' => false,
            ],
            'consent_given' => [
                'description' => __('User consent for data collection', 'wp-ai-site-generator'),
                'type' => 'boolean',
            ],
            'context' => [
                'description' => __('Additional context', 'wp-ai-site-generator'),
                'type' => 'object',
            ],
        ];
    }

    /**
     * Get quick feedback arguments
     *
     * @return array Arguments
     */
    private function get_quick_feedback_args() {
        return [
            'generation_id' => [
                'description' => __('Generation ID', 'wp-ai-site-generator'),
                'type' => 'integer',
                'required' => true,
            ],
            'is_positive' => [
                'description' => __('Positive feedback', 'wp-ai-site-generator'),
                'type' => 'boolean',
                'required' => true,
            ],
            'section_id' => [
                'description' => __('Section ID', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get report issue arguments
     *
     * @return array Arguments
     */
    private function get_report_issue_args() {
        return [
            'generation_id' => [
                'description' => __('Generation ID', 'wp-ai-site-generator'),
                'type' => 'integer',
                'required' => true,
            ],
            'issue_type' => [
                'description' => __('Issue type', 'wp-ai-site-generator'),
                'type' => 'string',
                'required' => true,
                'enum' => ['bug', 'error', 'quality', 'formatting', 'content', 'performance', 'other'],
            ],
            'severity' => [
                'description' => __('Issue severity', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['low', 'medium', 'high', 'critical'],
                'default' => 'medium',
            ],
            'description' => [
                'description' => __('Issue description', 'wp-ai-site-generator'),
                'type' => 'string',
                'required' => true,
            ],
            'affected_section' => [
                'description' => __('Affected section', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'steps_to_reproduce' => [
                'description' => __('Steps to reproduce', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'expected_behavior' => [
                'description' => __('Expected behavior', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'actual_behavior' => [
                'description' => __('Actual behavior', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'screenshot' => [
                'description' => __('Screenshot URL', 'wp-ai-site-generator'),
                'type' => 'string',
                'format' => 'uri',
            ],
        ];
    }

    /**
     * Get feature suggestion arguments
     *
     * @return array Arguments
     */
    private function get_suggest_feature_args() {
        return [
            'title' => [
                'description' => __('Suggestion title', 'wp-ai-site-generator'),
                'type' => 'string',
                'required' => true,
            ],
            'description' => [
                'description' => __('Suggestion description', 'wp-ai-site-generator'),
                'type' => 'string',
                'required' => true,
            ],
            'category' => [
                'description' => __('Suggestion category', 'wp-ai-site-generator'),
                'type' => 'string',
                'default' => 'general',
            ],
            'priority' => [
                'description' => __('Priority', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['low', 'medium', 'high'],
                'default' => 'medium',
            ],
            'use_case' => [
                'description' => __('Use case description', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get stats arguments
     *
     * @return array Arguments
     */
    private function get_stats_args() {
        return [
            'generation_id' => [
                'description' => __('Generation ID', 'wp-ai-site-generator'),
                'type' => 'integer',
            ],
            'period' => [
                'description' => __('Time period', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['7days', '30days', '90days', '1year'],
                'default' => '30days',
            ],
            'type' => [
                'description' => __('Generation type filter', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get insights arguments
     *
     * @return array Arguments
     */
    private function get_insights_args() {
        return [
            'period' => [
                'description' => __('Time period', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['7days', '30days', '90days', '1year'],
                'default' => '30days',
            ],
            'type' => [
                'description' => __('Content type filter', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
        ];
    }

    /**
     * Get trends arguments
     *
     * @return array Arguments
     */
    private function get_trends_args() {
        return [
            'period' => [
                'description' => __('Time period', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['7days', '30days', '90days', '1year'],
                'default' => '30days',
            ],
            'metric' => [
                'description' => __('Metric to track', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['rating', 'volume', 'issues', 'satisfaction'],
                'default' => 'rating',
            ],
        ];
    }

    /**
     * Get suggestions arguments
     *
     * @return array Arguments
     */
    private function get_suggestions_args() {
        return [
            'context' => [
                'description' => __('Context for suggestions', 'wp-ai-site-generator'),
                'type' => 'string',
                'default' => 'general',
            ],
            'prompt_template' => [
                'description' => __('Prompt template to analyze', 'wp-ai-site-generator'),
                'type' => 'string',
            ],
            'limit' => [
                'description' => __('Maximum number of suggestions', 'wp-ai-site-generator'),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 50,
            ],
        ];
    }

    /**
     * Get export arguments
     *
     * @return array Arguments
     */
    private function get_export_args() {
        return [
            'format' => [
                'description' => __('Export format', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['csv', 'json', 'xlsx'],
                'default' => 'csv',
            ],
            'period' => [
                'description' => __('Time period', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['7days', '30days', '90days', '1year', 'all'],
                'default' => '30days',
            ],
            'type' => [
                'description' => __('Data type to export', 'wp-ai-site-generator'),
                'type' => 'string',
                'enum' => ['all', 'feedback', 'issues', 'suggestions', 'stats'],
                'default' => 'all',
            ],
        ];
    }

    /**
     * Calculate date from period
     *
     * @param string $period Period string
     * @return string Date
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
     * Analyze trend data
     *
     * @param array $trends Trend data
     * @return array Analysis
     */
    private function analyze_trend($trends) {
        if (empty($trends)) {
            return ['direction' => 'stable', 'change' => 0];
        }

        $values = array_column($trends, 'value');
        $recent = array_slice($values, -7);
        $previous = array_slice($values, -14, 7);

        if (empty($previous)) {
            return ['direction' => 'insufficient_data', 'change' => 0];
        }

        $recent_avg = array_sum($recent) / count($recent);
        $previous_avg = array_sum($previous) / count($previous);
        $change = (($recent_avg - $previous_avg) / $previous_avg) * 100;

        if ($change > 5) {
            $direction = 'improving';
        } elseif ($change < -5) {
            $direction = 'declining';
        } else {
            $direction = 'stable';
        }

        return [
            'direction' => $direction,
            'change' => round($change, 2),
            'recent_average' => round($recent_avg, 2),
            'previous_average' => round($previous_avg, 2),
        ];
    }

    /**
     * Get recent feedback data
     *
     * @return array Feedback data
     */
    private function get_recent_feedback_data() {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT f.*, GROUP_CONCAT(CONCAT(r.dimension, ':', r.rating)) as ratings_str
            FROM {$wpdb->prefix}ai_feedback f
            LEFT JOIN {$wpdb->prefix}ai_feedback_ratings r ON f.id = r.feedback_id
            WHERE f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY f.id",
            ARRAY_A
        );
    }

    /**
     * Gather data for export
     *
     * @param string $period Period
     * @param string $type Data type
     * @return array Export data
     */
    private function gather_export_data($period, $type) {
        global $wpdb;

        $date_from = $period === 'all' ? '1970-01-01' : $this->calculate_date_from($period);
        $data = [];

        if ($type === 'all' || $type === 'feedback') {
            $data['feedback'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_feedback WHERE created_at >= %s",
                    $date_from
                ),
                ARRAY_A
            );
        }

        if ($type === 'all' || $type === 'issues') {
            $data['issues'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_issues WHERE created_at >= %s",
                    $date_from
                ),
                ARRAY_A
            );
        }

        if ($type === 'all' || $type === 'suggestions') {
            $data['suggestions'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ai_suggestions WHERE created_at >= %s",
                    $date_from
                ),
                ARRAY_A
            );
        }

        if ($type === 'all' || $type === 'stats') {
            $data['stats'] = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}ai_feedback_stats",
                ARRAY_A
            );
        }

        return $data;
    }

    /**
     * Export data as CSV
     *
     * @param array $data Data to export
     * @return array Export info
     */
    private function export_as_csv($data) {
        $upload_dir = wp_upload_dir();
        $filename = 'feedback-export-' . date('Y-m-d-His') . '.csv';
        $filepath = $upload_dir['basedir'] . '/ai-exports/' . $filename;

        // Create directory if needed
        wp_mkdir_p(dirname($filepath));

        $handle = fopen($filepath, 'w');

        foreach ($data as $type => $records) {
            if (empty($records)) continue;

            // Write section header
            fputcsv($handle, [strtoupper($type)]);

            // Write column headers
            if (!empty($records[0])) {
                fputcsv($handle, array_keys($records[0]));
            }

            // Write data rows
            foreach ($records as $record) {
                fputcsv($handle, $record);
            }

            // Empty line between sections
            fputcsv($handle, []);
        }

        fclose($handle);

        return [
            'url' => $upload_dir['baseurl'] . '/ai-exports/' . $filename,
            'filename' => $filename,
            'size' => filesize($filepath),
        ];
    }

    /**
     * Export data as JSON
     *
     * @param array $data Data to export
     * @return array Export info
     */
    private function export_as_json($data) {
        $upload_dir = wp_upload_dir();
        $filename = 'feedback-export-' . date('Y-m-d-His') . '.json';
        $filepath = $upload_dir['basedir'] . '/ai-exports/' . $filename;

        wp_mkdir_p(dirname($filepath));

        file_put_contents($filepath, wp_json_encode($data, JSON_PRETTY_PRINT));

        return [
            'url' => $upload_dir['baseurl'] . '/ai-exports/' . $filename,
            'filename' => $filename,
            'size' => filesize($filepath),
        ];
    }

    /**
     * Export data as XLSX
     *
     * @param array $data Data to export
     * @return array Export info
     */
    private function export_as_xlsx($data) {
        // This would require a library like PhpSpreadsheet
        // For now, fallback to CSV
        return $this->export_as_csv($data);
    }
}