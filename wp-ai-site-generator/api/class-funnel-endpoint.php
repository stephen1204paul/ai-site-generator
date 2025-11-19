<?php
/**
 * Funnel REST API Endpoints
 *
 * Provides REST API endpoints for funnel CRUD operations and analytics
 *
 * @package WP_AI_Site_Generator
 * @subpackage API
 */

namespace WP_AI_Site_Generator\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_AI_Site_Generator\Includes\Sales_Funnel;
use WP_AI_Site_Generator\Includes\Conversion_Tracker;
use WP_AI_Site_Generator\Database\Funnel_DB_Handler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Funnel_Endpoint
 */
class Funnel_Endpoint extends WP_REST_Controller {

    /**
     * Namespace
     */
    protected $namespace = 'wp-ai-site-generator/v1';

    /**
     * Rest base
     */
    protected $rest_base = 'funnels';

    /**
     * Sales funnel instance
     */
    private $sales_funnel;

    /**
     * Conversion tracker instance
     */
    private $conversion_tracker;

    /**
     * Database handler instance
     */
    private $db_handler;

    /**
     * Constructor
     */
    public function __construct() {
        $this->sales_funnel = new Sales_Funnel();
        $this->conversion_tracker = new Conversion_Tracker();
        $this->db_handler = new Funnel_DB_Handler();
    }

    /**
     * Register routes
     */
    public function register_routes() {
        // List funnels
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_funnels'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args' => $this->get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_funnel'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => $this->get_create_params(),
            ],
        ]);

        // Single funnel
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_funnel'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_funnel'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
                'args' => $this->get_update_params(),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_funnel'],
                'permission_callback' => [$this, 'delete_item_permissions_check'],
            ],
        ]);

        // Funnel stages
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<funnel_id>[\d]+)/stages', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_stages'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add_stage'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
            ],
        ]);

        // Single stage
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<funnel_id>[\d]+)/stages/(?P<stage_id>[a-z0-9\-]+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_stage'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_stage'],
                'permission_callback' => [$this, 'update_item_permissions_check'],
            ],
        ]);

        // Analytics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/analytics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'get_item_permissions_check'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'date_range' => [
                    'default' => '30days',
                    'enum' => ['today', '7days', '30days', '90days', 'all'],
                ],
            ],
        ]);

        // Conversion tracking
        register_rest_route($this->namespace, '/' . $this->rest_base . '/track', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'track_event'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'funnel_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'stage_id' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'event_type' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'event_data' => [
                    'type' => 'object',
                    'default' => [],
                ],
            ],
        ]);

        // Templates
        register_rest_route($this->namespace, '/' . $this->rest_base . '/templates', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_templates'],
            'permission_callback' => [$this, 'get_items_permissions_check'],
        ]);

        // Clone funnel
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/clone', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'clone_funnel'],
            'permission_callback' => [$this, 'create_item_permissions_check'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'name' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);

        // Activate/Deactivate
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_status'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'status' => [
                    'required' => true,
                    'enum' => ['active', 'paused', 'draft'],
                ],
            ],
        ]);

        // Lead magnets
        register_rest_route($this->namespace, '/lead-magnets', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_lead_magnets'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_lead_magnet'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
                'args' => [
                    'type' => [
                        'required' => true,
                        'enum' => ['ebook', 'checklist', 'template', 'cheatsheet', 'toolkit', 'workbook', 'email_course', 'video_series', 'quiz', 'calculator'],
                    ],
                    'topic' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'audience' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                ],
            ],
        ]);

        // A/B tests
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<funnel_id>[\d]+)/ab-tests', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_ab_tests'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_ab_test'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
            ],
        ]);

        // Email campaigns
        register_rest_route($this->namespace, '/email-campaigns', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_email_campaigns'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_email_campaign'],
                'permission_callback' => [$this, 'create_item_permissions_check'],
            ],
        ]);
    }

    /**
     * Get funnels
     */
    public function get_funnels($request) {
        $args = [
            'post_type' => 'wp_ai_funnel',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => $request->get_param('page') ?: 1,
            'post_status' => 'any',
        ];

        // Filter by status
        if ($status = $request->get_param('status')) {
            $args['meta_query'] = [
                [
                    'key' => '_funnel_status',
                    'value' => $status,
                ],
            ];
        }

        $query = new \WP_Query($args);

        $funnels = [];

        foreach ($query->posts as $post) {
            $funnels[] = $this->prepare_funnel_response($post);
        }

        return rest_ensure_response([
            'funnels' => $funnels,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ]);
    }

    /**
     * Get single funnel
     */
    public function get_funnel($request) {
        $funnel_id = $request->get_param('id');
        $post = get_post($funnel_id);

        if (!$post || $post->post_type !== 'wp_ai_funnel') {
            return new WP_Error('funnel_not_found', 'Funnel not found', ['status' => 404]);
        }

        $response = $this->prepare_funnel_response($post, true);

        return rest_ensure_response($response);
    }

    /**
     * Create funnel
     */
    public function create_funnel($request) {
        $params = $request->get_params();

        $result = $this->sales_funnel->create_funnel([
            'name' => $params['name'],
            'template' => $params['template'] ?? 'lead_generation',
            'stages' => $params['stages'] ?? [],
            'settings' => $params['settings'] ?? [],
        ]);

        if ($result['success']) {
            $post = get_post($result['funnel_id']);
            $response = $this->prepare_funnel_response($post, true);

            return rest_ensure_response($response);
        }

        return new WP_Error('funnel_creation_failed', $result['error'] ?? 'Failed to create funnel', ['status' => 400]);
    }

    /**
     * Update funnel
     */
    public function update_funnel($request) {
        $funnel_id = $request->get_param('id');
        $post = get_post($funnel_id);

        if (!$post || $post->post_type !== 'wp_ai_funnel') {
            return new WP_Error('funnel_not_found', 'Funnel not found', ['status' => 404]);
        }

        $params = $request->get_params();

        // Update post
        if (isset($params['name'])) {
            wp_update_post([
                'ID' => $funnel_id,
                'post_title' => $params['name'],
            ]);
        }

        // Update meta
        $meta_fields = ['stages', 'settings', 'status'];
        foreach ($meta_fields as $field) {
            if (isset($params[$field])) {
                update_post_meta($funnel_id, '_funnel_' . $field, $params[$field]);
            }
        }

        $response = $this->prepare_funnel_response(get_post($funnel_id), true);

        return rest_ensure_response($response);
    }

    /**
     * Delete funnel
     */
    public function delete_funnel($request) {
        $funnel_id = $request->get_param('id');
        $post = get_post($funnel_id);

        if (!$post || $post->post_type !== 'wp_ai_funnel') {
            return new WP_Error('funnel_not_found', 'Funnel not found', ['status' => 404]);
        }

        wp_delete_post($funnel_id, true);

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Get funnel stages
     */
    public function get_stages($request) {
        $funnel_id = $request->get_param('funnel_id');
        $stages = get_post_meta($funnel_id, '_funnel_stages', true);

        if (!$stages) {
            return rest_ensure_response([]);
        }

        return rest_ensure_response($stages);
    }

    /**
     * Add stage to funnel
     */
    public function add_stage($request) {
        $funnel_id = $request->get_param('funnel_id');
        $stage_data = $request->get_params();

        unset($stage_data['funnel_id']);

        $stages = get_post_meta($funnel_id, '_funnel_stages', true) ?: [];

        $stage_data['id'] = wp_generate_uuid4();
        $stage_data['created_at'] = current_time('mysql');

        $stages[] = $stage_data;

        update_post_meta($funnel_id, '_funnel_stages', $stages);

        return rest_ensure_response($stage_data);
    }

    /**
     * Update stage
     */
    public function update_stage($request) {
        $funnel_id = $request->get_param('funnel_id');
        $stage_id = $request->get_param('stage_id');

        $result = $this->sales_funnel->update_stage($funnel_id, $stage_id, $request->get_params());

        if ($result['success']) {
            return rest_ensure_response($result['stage']);
        }

        return new WP_Error('stage_update_failed', $result['error'], ['status' => 400]);
    }

    /**
     * Delete stage
     */
    public function delete_stage($request) {
        $funnel_id = $request->get_param('funnel_id');
        $stage_id = $request->get_param('stage_id');

        $stages = get_post_meta($funnel_id, '_funnel_stages', true);

        if (!$stages) {
            return new WP_Error('stages_not_found', 'No stages found', ['status' => 404]);
        }

        $stages = array_filter($stages, function($stage) use ($stage_id) {
            return $stage['id'] !== $stage_id;
        });

        update_post_meta($funnel_id, '_funnel_stages', array_values($stages));

        return rest_ensure_response(['success' => true]);
    }

    /**
     * Get analytics
     */
    public function get_analytics($request) {
        $funnel_id = $request->get_param('id');
        $date_range = $request->get_param('date_range');

        $analytics = $this->sales_funnel->get_funnel_analytics($funnel_id, $date_range);

        if (!$analytics) {
            return new WP_Error('analytics_not_found', 'Analytics not available', ['status' => 404]);
        }

        // Get additional metrics from database
        $db_metrics = $this->db_handler->get_funnel_metrics($funnel_id, $date_range);

        $analytics['metrics'] = $db_metrics;

        return rest_ensure_response($analytics);
    }

    /**
     * Track event
     */
    public function track_event($request) {
        $funnel_id = $request->get_param('funnel_id');
        $stage_id = $request->get_param('stage_id');
        $event_type = $request->get_param('event_type');
        $event_data = $request->get_param('event_data');

        if ($event_type === 'conversion') {
            $result = $this->conversion_tracker->track_conversion($funnel_id, $stage_id, $event_data);
        } elseif ($event_type === 'page_view') {
            $result = $this->conversion_tracker->track_page_view($funnel_id, $stage_id, $event_data);
        } else {
            $result = $this->conversion_tracker->track_custom_event($funnel_id, $stage_id, $event_type, $event_data);
        }

        return rest_ensure_response($result);
    }

    /**
     * Get templates
     */
    public function get_templates($request) {
        $templates = $this->sales_funnel->get_funnel_templates();

        return rest_ensure_response($templates);
    }

    /**
     * Clone funnel
     */
    public function clone_funnel($request) {
        $funnel_id = $request->get_param('id');
        $new_name = $request->get_param('name');

        $result = $this->sales_funnel->clone_funnel($funnel_id, $new_name);

        if ($result['success']) {
            $post = get_post($result['funnel_id']);
            $response = $this->prepare_funnel_response($post, true);

            return rest_ensure_response($response);
        }

        return new WP_Error('clone_failed', $result['error'], ['status' => 400]);
    }

    /**
     * Update funnel status
     */
    public function update_status($request) {
        $funnel_id = $request->get_param('id');
        $status = $request->get_param('status');

        update_post_meta($funnel_id, '_funnel_status', $status);

        if ($status === 'active') {
            $this->sales_funnel->activate_funnel($funnel_id);
        } else {
            $this->sales_funnel->deactivate_funnel($funnel_id);
        }

        return rest_ensure_response(['success' => true, 'status' => $status]);
    }

    /**
     * Get lead magnets
     */
    public function get_lead_magnets($request) {
        global $wpdb;

        $table_name = $this->db_handler->get_table('lead_magnets');
        $magnets = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");

        foreach ($magnets as &$magnet) {
            $magnet->metadata = json_decode($magnet->metadata);
            $magnet->files = json_decode($magnet->files);
        }

        return rest_ensure_response($magnets);
    }

    /**
     * Generate lead magnet
     */
    public function generate_lead_magnet($request) {
        $generator = new \WP_AI_Site_Generator\Generators\Lead_Magnet_Generator();

        $result = $generator->generate_lead_magnet([
            'type' => $request->get_param('type'),
            'topic' => $request->get_param('topic'),
            'audience' => $request->get_param('audience'),
            'goal' => $request->get_param('goal') ?? 'generate_leads',
            'optin_template' => $request->get_param('optin_template') ?? 'simple',
        ]);

        if ($result['success']) {
            return rest_ensure_response($result);
        }

        return new WP_Error('generation_failed', $result['error'] ?? 'Failed to generate lead magnet', ['status' => 400]);
    }

    /**
     * Get A/B tests
     */
    public function get_ab_tests($request) {
        $funnel_id = $request->get_param('funnel_id');

        global $wpdb;
        $table_name = $this->db_handler->get_table('ab_tests');

        $tests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE funnel_id = %d ORDER BY started_at DESC",
            $funnel_id
        ));

        foreach ($tests as &$test) {
            $test->variants = json_decode($test->variants);
            $test->traffic_split = json_decode($test->traffic_split);
            $test->metrics = json_decode($test->metrics);
        }

        return rest_ensure_response($tests);
    }

    /**
     * Create A/B test
     */
    public function create_ab_test($request) {
        $funnel_id = $request->get_param('funnel_id');

        $test_id = $this->db_handler->create_ab_test([
            'funnel_id' => $funnel_id,
            'stage_id' => $request->get_param('stage_id'),
            'test_name' => $request->get_param('test_name'),
            'test_type' => $request->get_param('test_type') ?? 'split',
            'variants' => $request->get_param('variants'),
            'traffic_split' => $request->get_param('traffic_split') ?? [50, 50],
        ]);

        if ($test_id) {
            return rest_ensure_response(['success' => true, 'test_id' => $test_id]);
        }

        return new WP_Error('test_creation_failed', 'Failed to create A/B test', ['status' => 400]);
    }

    /**
     * Get email campaigns
     */
    public function get_email_campaigns($request) {
        global $wpdb;

        $table_name = $this->db_handler->get_table('email_log');

        $campaigns = $wpdb->get_results(
            "SELECT template_id, COUNT(*) as sent_count,
                    SUM(opened) as opened_count,
                    SUM(clicked) as clicked_count,
                    MIN(sent_at) as first_sent,
                    MAX(sent_at) as last_sent
             FROM {$table_name}
             GROUP BY template_id
             ORDER BY last_sent DESC"
        );

        return rest_ensure_response($campaigns);
    }

    /**
     * Create email campaign
     */
    public function create_email_campaign($request) {
        $email_integration = new \WP_AI_Site_Generator\Includes\Email_Integration();

        $recipients = $request->get_param('recipients');
        $template = $request->get_param('template');
        $subject = $request->get_param('subject');
        $content = $request->get_param('content');

        $results = [];

        foreach ($recipients as $recipient) {
            $result = $email_integration->send_email($recipient, $template, [
                'subject' => $subject,
                'content' => $content,
            ]);

            $results[] = [
                'recipient' => $recipient,
                'success' => $result['success'],
            ];
        }

        return rest_ensure_response([
            'campaign_id' => wp_generate_uuid4(),
            'results' => $results,
        ]);
    }

    /**
     * Prepare funnel response
     */
    private function prepare_funnel_response($post, $full = false) {
        $response = [
            'id' => $post->ID,
            'name' => $post->post_title,
            'status' => get_post_meta($post->ID, '_funnel_status', true) ?: 'draft',
            'created' => $post->post_date,
            'modified' => $post->post_modified,
        ];

        if ($full) {
            $response['template'] = get_post_meta($post->ID, '_funnel_template', true);
            $response['stages'] = get_post_meta($post->ID, '_funnel_stages', true) ?: [];
            $response['settings'] = get_post_meta($post->ID, '_funnel_settings', true) ?: [];

            // Add basic analytics
            $metrics = $this->db_handler->get_funnel_metrics($post->ID, '30days');
            $response['metrics'] = [
                'total_visitors' => $metrics['total_visitors'] ?? 0,
                'conversions' => $metrics['conversions'] ?? 0,
                'conversion_rate' => $metrics['conversion_rate'] ?? 0,
                'revenue' => $metrics['revenue'] ?? 0,
            ];
        }

        return $response;
    }

    /**
     * Permission callbacks
     */
    public function get_items_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function get_item_permissions_check($request) {
        return current_user_can('edit_posts');
    }

    public function create_item_permissions_check($request) {
        return current_user_can('publish_posts');
    }

    public function update_item_permissions_check($request) {
        $funnel_id = $request->get_param('id') ?: $request->get_param('funnel_id');
        return current_user_can('edit_post', $funnel_id);
    }

    public function delete_item_permissions_check($request) {
        $funnel_id = $request->get_param('id');
        return current_user_can('delete_post', $funnel_id);
    }

    /**
     * Get collection params
     */
    private function get_collection_params() {
        return [
            'page' => [
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'per_page' => [
                'default' => 10,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                }
            ],
            'status' => [
                'enum' => ['active', 'paused', 'draft'],
            ],
        ];
    }

    /**
     * Get create params
     */
    private function get_create_params() {
        return [
            'name' => [
                'required' => true,
                'type' => 'string',
            ],
            'template' => [
                'type' => 'string',
                'enum' => ['lead_generation', 'webinar', 'product_launch', 'tripwire'],
                'default' => 'lead_generation',
            ],
            'stages' => [
                'type' => 'array',
                'default' => [],
            ],
            'settings' => [
                'type' => 'object',
                'default' => [],
            ],
        ];
    }

    /**
     * Get update params
     */
    private function get_update_params() {
        return [
            'name' => [
                'type' => 'string',
            ],
            'stages' => [
                'type' => 'array',
            ],
            'settings' => [
                'type' => 'object',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'paused', 'draft'],
            ],
        ];
    }
}