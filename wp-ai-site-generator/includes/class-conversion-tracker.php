<?php
/**
 * Conversion Tracker
 *
 * Tracks visitor journey, conversions, and drop-offs throughout funnels
 *
 * @package WP_AI_Site_Generator
 * @subpackage Sales_Funnel
 */

namespace WP_AI_Site_Generator\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Conversion_Tracker
 */
class Conversion_Tracker {

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Tracking cookie name
     */
    private $cookie_name = 'wp_ai_funnel_visitor';

    /**
     * Session duration in seconds
     */
    private $session_duration = 1800; // 30 minutes

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_funnel_tracking';

        add_action('init', [$this, 'init_tracking']);
        add_action('wp_footer', [$this, 'inject_tracking_script']);
        add_action('wp_ajax_track_funnel_event', [$this, 'handle_tracking_event']);
        add_action('wp_ajax_nopriv_track_funnel_event', [$this, 'handle_tracking_event']);
        add_action('wp_ajax_track_conversion', [$this, 'handle_conversion']);
        add_action('wp_ajax_nopriv_track_conversion', [$this, 'handle_conversion']);
    }

    /**
     * Initialize tracking
     */
    public function init_tracking() {
        if (!is_admin() && !wp_doing_ajax()) {
            $this->set_visitor_cookie();
        }
    }

    /**
     * Set visitor tracking cookie
     */
    private function set_visitor_cookie() {
        if (!isset($_COOKIE[$this->cookie_name])) {
            $visitor_id = $this->generate_visitor_id();
            setcookie(
                $this->cookie_name,
                $visitor_id,
                time() + (86400 * 30), // 30 days
                '/',
                '',
                is_ssl(),
                true
            );
            $_COOKIE[$this->cookie_name] = $visitor_id;
        }
    }

    /**
     * Generate unique visitor ID
     */
    private function generate_visitor_id() {
        return wp_generate_uuid4();
    }

    /**
     * Get current visitor ID
     */
    public function get_visitor_id() {
        return $_COOKIE[$this->cookie_name] ?? $this->generate_visitor_id();
    }

    /**
     * Track page view
     */
    public function track_page_view($funnel_id, $stage_id, $page_data = []) {
        $visitor_id = $this->get_visitor_id();
        $session_id = $this->get_or_create_session($visitor_id);

        $event_data = [
            'visitor_id' => $visitor_id,
            'session_id' => $session_id,
            'funnel_id' => $funnel_id,
            'stage_id' => $stage_id,
            'event_type' => 'page_view',
            'event_data' => wp_json_encode(array_merge($page_data, [
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $this->get_client_ip(),
                'timestamp' => current_time('mysql'),
            ])),
            'created_at' => current_time('mysql'),
        ];

        $this->save_tracking_event($event_data);

        // Update stage metrics
        $this->update_stage_metrics($funnel_id, $stage_id, 'views');

        return $event_data;
    }

    /**
     * Track conversion event
     */
    public function track_conversion($funnel_id, $stage_id, $conversion_data = []) {
        $visitor_id = $this->get_visitor_id();
        $session_id = $this->get_or_create_session($visitor_id);

        $event_data = [
            'visitor_id' => $visitor_id,
            'session_id' => $session_id,
            'funnel_id' => $funnel_id,
            'stage_id' => $stage_id,
            'event_type' => 'conversion',
            'event_data' => wp_json_encode(array_merge($conversion_data, [
                'conversion_value' => $conversion_data['value'] ?? 0,
                'conversion_type' => $conversion_data['type'] ?? 'form_submission',
                'timestamp' => current_time('mysql'),
            ])),
            'created_at' => current_time('mysql'),
        ];

        $this->save_tracking_event($event_data);

        // Update stage metrics
        $this->update_stage_metrics($funnel_id, $stage_id, 'conversions');

        // Trigger conversion action
        do_action('wp_ai_funnel_conversion', $funnel_id, $stage_id, $visitor_id, $conversion_data);

        return $event_data;
    }

    /**
     * Track custom event
     */
    public function track_custom_event($funnel_id, $stage_id, $event_name, $event_data = []) {
        $visitor_id = $this->get_visitor_id();
        $session_id = $this->get_or_create_session($visitor_id);

        $tracking_data = [
            'visitor_id' => $visitor_id,
            'session_id' => $session_id,
            'funnel_id' => $funnel_id,
            'stage_id' => $stage_id,
            'event_type' => 'custom_' . $event_name,
            'event_data' => wp_json_encode($event_data),
            'created_at' => current_time('mysql'),
        ];

        $this->save_tracking_event($tracking_data);

        return $tracking_data;
    }

    /**
     * Get or create session
     */
    private function get_or_create_session($visitor_id) {
        global $wpdb;

        // Check for existing active session
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name}_sessions
             WHERE visitor_id = %s
             AND last_activity > DATE_SUB(NOW(), INTERVAL %d SECOND)
             ORDER BY created_at DESC LIMIT 1",
            $visitor_id,
            $this->session_duration
        ));

        if ($session) {
            // Update last activity
            $wpdb->update(
                $this->table_name . '_sessions',
                ['last_activity' => current_time('mysql')],
                ['id' => $session->id]
            );
            return $session->id;
        }

        // Create new session
        $session_id = wp_generate_uuid4();
        $wpdb->insert(
            $this->table_name . '_sessions',
            [
                'id' => $session_id,
                'visitor_id' => $visitor_id,
                'created_at' => current_time('mysql'),
                'last_activity' => current_time('mysql'),
                'session_data' => wp_json_encode([
                    'source' => $this->get_traffic_source(),
                    'landing_page' => $_SERVER['REQUEST_URI'] ?? '',
                    'device' => $this->get_device_type(),
                ])
            ]
        );

        return $session_id;
    }

    /**
     * Save tracking event to database
     */
    private function save_tracking_event($event_data) {
        global $wpdb;

        $wpdb->insert($this->table_name, $event_data);

        // Trigger event saved action
        do_action('wp_ai_funnel_event_tracked', $event_data);
    }

    /**
     * Update stage metrics
     */
    private function update_stage_metrics($funnel_id, $stage_id, $metric_type) {
        $stages = get_post_meta($funnel_id, '_funnel_stages', true);

        if (!$stages) {
            return;
        }

        foreach ($stages as &$stage) {
            if ($stage['id'] === $stage_id) {
                $stage['metrics'][$metric_type] = ($stage['metrics'][$metric_type] ?? 0) + 1;

                // Calculate conversion rate
                if ($stage['metrics']['views'] > 0) {
                    $stage['metrics']['conversion_rate'] =
                        ($stage['metrics']['conversions'] / $stage['metrics']['views']) * 100;
                }

                break;
            }
        }

        update_post_meta($funnel_id, '_funnel_stages', $stages);
    }

    /**
     * Get visitor journey
     */
    public function get_visitor_journey($visitor_id, $funnel_id = null) {
        global $wpdb;

        $query = "SELECT * FROM {$this->table_name} WHERE visitor_id = %s";
        $params = [$visitor_id];

        if ($funnel_id) {
            $query .= " AND funnel_id = %d";
            $params[] = $funnel_id;
        }

        $query .= " ORDER BY created_at ASC";

        $events = $wpdb->get_results($wpdb->prepare($query, $params));

        $journey = [
            'visitor_id' => $visitor_id,
            'total_events' => count($events),
            'first_seen' => $events[0]->created_at ?? null,
            'last_seen' => end($events)->created_at ?? null,
            'events' => [],
            'conversions' => [],
            'funnel_paths' => [],
        ];

        $current_funnel = null;
        $funnel_path = [];

        foreach ($events as $event) {
            $event_data = json_decode($event->event_data, true);

            $journey['events'][] = [
                'type' => $event->event_type,
                'funnel_id' => $event->funnel_id,
                'stage_id' => $event->stage_id,
                'timestamp' => $event->created_at,
                'data' => $event_data,
            ];

            if ($event->event_type === 'conversion') {
                $journey['conversions'][] = [
                    'funnel_id' => $event->funnel_id,
                    'stage_id' => $event->stage_id,
                    'value' => $event_data['conversion_value'] ?? 0,
                    'timestamp' => $event->created_at,
                ];
            }

            // Track funnel paths
            if ($event->funnel_id !== $current_funnel) {
                if (!empty($funnel_path)) {
                    $journey['funnel_paths'][] = $funnel_path;
                }
                $current_funnel = $event->funnel_id;
                $funnel_path = [
                    'funnel_id' => $event->funnel_id,
                    'stages' => [],
                ];
            }

            $funnel_path['stages'][] = $event->stage_id;
        }

        if (!empty($funnel_path)) {
            $journey['funnel_paths'][] = $funnel_path;
        }

        return $journey;
    }

    /**
     * Get funnel conversion report
     */
    public function get_conversion_report($funnel_id, $date_range = '30days') {
        global $wpdb;

        $date_condition = $this->get_date_condition($date_range);

        // Get total visitors
        $total_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_id) FROM {$this->table_name}
             WHERE funnel_id = %d AND event_type = 'page_view' {$date_condition}",
            $funnel_id
        ));

        // Get conversions by stage
        $conversions = $wpdb->get_results($wpdb->prepare(
            "SELECT stage_id, COUNT(*) as count, SUM(JSON_EXTRACT(event_data, '$.conversion_value')) as value
             FROM {$this->table_name}
             WHERE funnel_id = %d AND event_type = 'conversion' {$date_condition}
             GROUP BY stage_id",
            $funnel_id
        ));

        // Get drop-off analysis
        $stage_progression = $wpdb->get_results($wpdb->prepare(
            "SELECT stage_id, COUNT(DISTINCT visitor_id) as visitors
             FROM {$this->table_name}
             WHERE funnel_id = %d AND event_type = 'page_view' {$date_condition}
             GROUP BY stage_id
             ORDER BY created_at ASC",
            $funnel_id
        ));

        $report = [
            'funnel_id' => $funnel_id,
            'date_range' => $date_range,
            'total_visitors' => $total_visitors,
            'conversions' => $conversions,
            'stage_progression' => $stage_progression,
            'overall_conversion_rate' => 0,
            'total_value' => 0,
            'average_order_value' => 0,
        ];

        // Calculate overall metrics
        if ($conversions) {
            $total_conversions = array_sum(array_column($conversions, 'count'));
            $total_value = array_sum(array_column($conversions, 'value'));

            $report['total_conversions'] = $total_conversions;
            $report['total_value'] = $total_value;
            $report['overall_conversion_rate'] = $total_visitors > 0 ?
                ($total_conversions / $total_visitors) * 100 : 0;
            $report['average_order_value'] = $total_conversions > 0 ?
                $total_value / $total_conversions : 0;
        }

        return $report;
    }

    /**
     * Get date condition for queries
     */
    private function get_date_condition($date_range) {
        $conditions = [
            'today' => "AND DATE(created_at) = CURDATE()",
            '7days' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            '30days' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            '90days' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            'all' => "",
        ];

        return $conditions[$date_range] ?? $conditions['30days'];
    }

    /**
     * Get traffic source
     */
    private function get_traffic_source() {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        if (empty($referrer)) {
            return 'direct';
        }

        $parsed = parse_url($referrer);
        $host = $parsed['host'] ?? '';

        // Check for known sources
        $sources = [
            'google' => 'organic_search',
            'bing' => 'organic_search',
            'facebook' => 'social',
            'twitter' => 'social',
            'linkedin' => 'social',
            'instagram' => 'social',
        ];

        foreach ($sources as $domain => $source) {
            if (strpos($host, $domain) !== false) {
                return $source;
            }
        }

        // Check if same domain
        if ($host === $_SERVER['HTTP_HOST']) {
            return 'internal';
        }

        return 'referral';
    }

    /**
     * Get device type
     */
    private function get_device_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (preg_match('/mobile|android|iphone/i', $user_agent)) {
            return 'mobile';
        }

        if (preg_match('/tablet|ipad/i', $user_agent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Inject tracking script
     */
    public function inject_tracking_script() {
        if (is_admin()) {
            return;
        }

        $current_funnel = $this->get_current_funnel();

        if (!$current_funnel) {
            return;
        }

        ?>
        <script>
            (function() {
                const trackingData = {
                    funnel_id: '<?php echo esc_js($current_funnel['id']); ?>',
                    stage_id: '<?php echo esc_js($current_funnel['stage_id']); ?>',
                    visitor_id: '<?php echo esc_js($this->get_visitor_id()); ?>',
                    ajax_url: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
                    nonce: '<?php echo esc_js(wp_create_nonce('funnel_tracking')); ?>'
                };

                // Track page view
                fetch(trackingData.ajax_url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'track_funnel_event',
                        funnel_id: trackingData.funnel_id,
                        stage_id: trackingData.stage_id,
                        event_type: 'page_view',
                        nonce: trackingData.nonce
                    })
                });

                // Track time on page
                let startTime = Date.now();
                window.addEventListener('beforeunload', function() {
                    const timeOnPage = Math.round((Date.now() - startTime) / 1000);
                    navigator.sendBeacon(trackingData.ajax_url, new URLSearchParams({
                        action: 'track_funnel_event',
                        funnel_id: trackingData.funnel_id,
                        stage_id: trackingData.stage_id,
                        event_type: 'time_on_page',
                        time: timeOnPage,
                        nonce: trackingData.nonce
                    }));
                });

                // Track form submissions
                document.addEventListener('submit', function(e) {
                    if (e.target.classList.contains('funnel-form')) {
                        fetch(trackingData.ajax_url, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'track_conversion',
                                funnel_id: trackingData.funnel_id,
                                stage_id: trackingData.stage_id,
                                form_id: e.target.id,
                                nonce: trackingData.nonce
                            })
                        });
                    }
                });
            })();
        </script>
        <?php
    }

    /**
     * Get current funnel context
     */
    private function get_current_funnel() {
        // This would be determined by page template, shortcode, or URL parameter
        // Placeholder implementation
        return null;
    }

    /**
     * Handle AJAX tracking event
     */
    public function handle_tracking_event() {
        check_ajax_referer('funnel_tracking', 'nonce');

        $funnel_id = intval($_POST['funnel_id'] ?? 0);
        $stage_id = sanitize_text_field($_POST['stage_id'] ?? '');
        $event_type = sanitize_text_field($_POST['event_type'] ?? 'page_view');

        if ($funnel_id && $stage_id) {
            if ($event_type === 'page_view') {
                $this->track_page_view($funnel_id, $stage_id);
            } else {
                $this->track_custom_event($funnel_id, $stage_id, $event_type, $_POST);
            }
        }

        wp_send_json_success();
    }

    /**
     * Handle AJAX conversion
     */
    public function handle_conversion() {
        check_ajax_referer('funnel_tracking', 'nonce');

        $funnel_id = intval($_POST['funnel_id'] ?? 0);
        $stage_id = sanitize_text_field($_POST['stage_id'] ?? '');

        if ($funnel_id && $stage_id) {
            $this->track_conversion($funnel_id, $stage_id, [
                'form_id' => sanitize_text_field($_POST['form_id'] ?? ''),
                'value' => floatval($_POST['value'] ?? 0),
            ]);
        }

        wp_send_json_success();
    }
}