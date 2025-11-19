<?php
/**
 * Funnel Database Handler
 *
 * Manages database tables and operations for funnel and tracking data
 *
 * @package WP_AI_Site_Generator
 * @subpackage Database
 */

namespace WP_AI_Site_Generator\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Funnel_DB_Handler
 */
class Funnel_DB_Handler {

    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Table names
     */
    private $tables = [];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->tables = [
            'tracking' => $wpdb->prefix . 'ai_funnel_tracking',
            'sessions' => $wpdb->prefix . 'ai_funnel_tracking_sessions',
            'conversions' => $wpdb->prefix . 'ai_funnel_conversions',
            'triggers_log' => $wpdb->prefix . 'ai_marketing_triggers_log',
            'email_log' => $wpdb->prefix . 'ai_email_log',
            'lead_magnets' => $wpdb->prefix . 'ai_lead_magnets',
            'ab_tests' => $wpdb->prefix . 'ai_funnel_ab_tests',
            'analytics' => $wpdb->prefix . 'ai_funnel_analytics',
        ];

        add_action('wp_ai_activate', [$this, 'create_tables']);
        add_action('wp_ai_upgrade', [$this, 'upgrade_tables']);
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Tracking table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['tracking']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitor_id varchar(36) NOT NULL,
            session_id varchar(36) NOT NULL,
            funnel_id bigint(20) NOT NULL,
            stage_id varchar(36) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_visitor (visitor_id),
            INDEX idx_session (session_id),
            INDEX idx_funnel (funnel_id),
            INDEX idx_stage (stage_id),
            INDEX idx_event (event_type),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Sessions table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['sessions']} (
            id varchar(36) NOT NULL,
            visitor_id varchar(36) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            referrer text,
            landing_page text,
            session_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_visitor (visitor_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at),
            INDEX idx_activity (last_activity)
        ) $charset_collate;";
        dbDelta($sql);

        // Conversions table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['conversions']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitor_id varchar(36) NOT NULL,
            session_id varchar(36) NOT NULL,
            funnel_id bigint(20) NOT NULL,
            stage_id varchar(36) NOT NULL,
            conversion_type varchar(50) NOT NULL,
            conversion_value decimal(10,2) DEFAULT 0,
            order_id varchar(100) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            customer_data longtext,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_visitor (visitor_id),
            INDEX idx_funnel (funnel_id),
            INDEX idx_type (conversion_type),
            INDEX idx_email (customer_email),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Marketing triggers log
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['triggers_log']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            trigger_name varchar(100) NOT NULL,
            action varchar(100) NOT NULL,
            visitor_id varchar(36) NOT NULL,
            funnel_id bigint(20) DEFAULT NULL,
            data longtext,
            status varchar(20) DEFAULT 'pending',
            executed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_trigger (trigger_name),
            INDEX idx_action (action),
            INDEX idx_visitor (visitor_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Email log
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['email_log']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient varchar(255) NOT NULL,
            subject text,
            template_id varchar(50),
            status varchar(20) NOT NULL,
            provider varchar(50),
            message_id varchar(255),
            opened boolean DEFAULT false,
            clicked boolean DEFAULT false,
            bounced boolean DEFAULT false,
            error_message text,
            metadata longtext,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            opened_at datetime DEFAULT NULL,
            clicked_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_recipient (recipient),
            INDEX idx_template (template_id),
            INDEX idx_status (status),
            INDEX idx_provider (provider),
            INDEX idx_sent (sent_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Lead magnets
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['lead_magnets']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            topic varchar(255),
            audience varchar(255),
            metadata longtext,
            content longtext,
            files longtext,
            download_count int DEFAULT 0,
            conversion_rate decimal(5,2) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_type (type),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // A/B tests
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['ab_tests']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            funnel_id bigint(20) NOT NULL,
            stage_id varchar(36) NOT NULL,
            test_name varchar(255) NOT NULL,
            test_type varchar(50) NOT NULL,
            variants longtext,
            traffic_split longtext,
            metrics longtext,
            winner varchar(10) DEFAULT NULL,
            confidence decimal(5,2) DEFAULT NULL,
            status varchar(20) DEFAULT 'running',
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_funnel (funnel_id),
            INDEX idx_stage (stage_id),
            INDEX idx_status (status),
            INDEX idx_started (started_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Analytics aggregation
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tables['analytics']} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            funnel_id bigint(20) NOT NULL,
            stage_id varchar(36),
            date date NOT NULL,
            hour tinyint DEFAULT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value decimal(10,2) NOT NULL,
            dimensions longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_metric (funnel_id, stage_id, date, hour, metric_type),
            INDEX idx_funnel (funnel_id),
            INDEX idx_date (date),
            INDEX idx_metric (metric_type)
        ) $charset_collate;";
        dbDelta($sql);

        // Save database version
        update_option('wp_ai_funnel_db_version', self::DB_VERSION);
    }

    /**
     * Upgrade tables if needed
     */
    public function upgrade_tables() {
        $current_version = get_option('wp_ai_funnel_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->create_tables();
        }
    }

    /**
     * Insert tracking event
     */
    public function insert_tracking_event($data) {
        global $wpdb;

        $defaults = [
            'visitor_id' => '',
            'session_id' => '',
            'funnel_id' => 0,
            'stage_id' => '',
            'event_type' => '',
            'event_data' => '',
            'created_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        if (is_array($data['event_data']) || is_object($data['event_data'])) {
            $data['event_data'] = wp_json_encode($data['event_data']);
        }

        return $wpdb->insert($this->tables['tracking'], $data);
    }

    /**
     * Get visitor events
     */
    public function get_visitor_events($visitor_id, $args = []) {
        global $wpdb;

        $defaults = [
            'funnel_id' => null,
            'stage_id' => null,
            'event_type' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $query = "SELECT * FROM {$this->tables['tracking']} WHERE visitor_id = %s";
        $params = [$visitor_id];

        if ($args['funnel_id']) {
            $query .= " AND funnel_id = %d";
            $params[] = $args['funnel_id'];
        }

        if ($args['stage_id']) {
            $query .= " AND stage_id = %s";
            $params[] = $args['stage_id'];
        }

        if ($args['event_type']) {
            $query .= " AND event_type = %s";
            $params[] = $args['event_type'];
        }

        if ($args['date_from']) {
            $query .= " AND created_at >= %s";
            $params[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $query .= " AND created_at <= %s";
            $params[] = $args['date_to'];
        }

        $query .= " ORDER BY created_at " . $args['order'];
        $query .= " LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Get funnel metrics
     */
    public function get_funnel_metrics($funnel_id, $date_range = '30days') {
        global $wpdb;

        $date_condition = $this->get_date_condition($date_range);

        $metrics = [];

        // Total visitors
        $metrics['total_visitors'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT visitor_id) FROM {$this->tables['tracking']}
             WHERE funnel_id = %d {$date_condition}",
            $funnel_id
        ));

        // Total sessions
        $metrics['total_sessions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$this->tables['tracking']}
             WHERE funnel_id = %d {$date_condition}",
            $funnel_id
        ));

        // Page views
        $metrics['page_views'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['tracking']}
             WHERE funnel_id = %d AND event_type = 'page_view' {$date_condition}",
            $funnel_id
        ));

        // Conversions
        $metrics['conversions'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->tables['conversions']}
             WHERE funnel_id = %d {$date_condition}",
            $funnel_id
        ));

        // Revenue
        $metrics['revenue'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(conversion_value) FROM {$this->tables['conversions']}
             WHERE funnel_id = %d {$date_condition}",
            $funnel_id
        )) ?: 0;

        // Conversion rate
        $metrics['conversion_rate'] = $metrics['total_visitors'] > 0 ?
            ($metrics['conversions'] / $metrics['total_visitors']) * 100 : 0;

        // Average order value
        $metrics['average_order_value'] = $metrics['conversions'] > 0 ?
            $metrics['revenue'] / $metrics['conversions'] : 0;

        // Stage metrics
        $metrics['stages'] = $wpdb->get_results($wpdb->prepare(
            "SELECT
                stage_id,
                COUNT(DISTINCT visitor_id) as visitors,
                COUNT(*) as views,
                COUNT(DISTINCT CASE WHEN event_type = 'conversion' THEN visitor_id END) as conversions
             FROM {$this->tables['tracking']}
             WHERE funnel_id = %d {$date_condition}
             GROUP BY stage_id",
            $funnel_id
        ));

        return $metrics;
    }

    /**
     * Get conversion details
     */
    public function get_conversion_details($conversion_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['conversions']} WHERE id = %d",
            $conversion_id
        ));
    }

    /**
     * Record conversion
     */
    public function record_conversion($data) {
        global $wpdb;

        $defaults = [
            'visitor_id' => '',
            'session_id' => '',
            'funnel_id' => 0,
            'stage_id' => '',
            'conversion_type' => 'form_submission',
            'conversion_value' => 0,
            'order_id' => null,
            'customer_email' => null,
            'customer_data' => '',
            'metadata' => '',
            'created_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Encode JSON fields
        foreach (['customer_data', 'metadata'] as $field) {
            if (is_array($data[$field]) || is_object($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        $result = $wpdb->insert($this->tables['conversions'], $data);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get or create session
     */
    public function get_or_create_session($visitor_id, $data = []) {
        global $wpdb;

        // Check for existing active session (within 30 minutes)
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['sessions']}
             WHERE visitor_id = %s
             AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             ORDER BY created_at DESC LIMIT 1",
            $visitor_id
        ));

        if ($session) {
            // Update last activity
            $wpdb->update(
                $this->tables['sessions'],
                ['last_activity' => current_time('mysql')],
                ['id' => $session->id]
            );
            return $session->id;
        }

        // Create new session
        $session_data = array_merge([
            'id' => wp_generate_uuid4(),
            'visitor_id' => $visitor_id,
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'landing_page' => $_SERVER['REQUEST_URI'] ?? '',
            'session_data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
            'last_activity' => current_time('mysql'),
        ], $data);

        $wpdb->insert($this->tables['sessions'], $session_data);

        return $session_data['id'];
    }

    /**
     * Log marketing trigger
     */
    public function log_marketing_trigger($trigger_name, $action, $visitor_id, $data = []) {
        global $wpdb;

        return $wpdb->insert($this->tables['triggers_log'], [
            'trigger_name' => $trigger_name,
            'action' => $action,
            'visitor_id' => $visitor_id,
            'funnel_id' => $data['funnel_id'] ?? null,
            'data' => wp_json_encode($data),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Update trigger status
     */
    public function update_trigger_status($trigger_id, $status) {
        global $wpdb;

        return $wpdb->update(
            $this->tables['triggers_log'],
            [
                'status' => $status,
                'executed_at' => $status === 'completed' ? current_time('mysql') : null,
            ],
            ['id' => $trigger_id]
        );
    }

    /**
     * Log email activity
     */
    public function log_email($data) {
        global $wpdb;

        $defaults = [
            'recipient' => '',
            'subject' => '',
            'template_id' => '',
            'status' => 'sent',
            'provider' => '',
            'message_id' => '',
            'error_message' => '',
            'metadata' => '',
            'sent_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        if (is_array($data['metadata']) || is_object($data['metadata'])) {
            $data['metadata'] = wp_json_encode($data['metadata']);
        }

        return $wpdb->insert($this->tables['email_log'], $data);
    }

    /**
     * Update email status
     */
    public function update_email_status($message_id, $updates) {
        global $wpdb;

        $allowed_updates = ['opened', 'clicked', 'bounced', 'opened_at', 'clicked_at'];
        $clean_updates = array_intersect_key($updates, array_flip($allowed_updates));

        if (!empty($clean_updates)) {
            return $wpdb->update(
                $this->tables['email_log'],
                $clean_updates,
                ['message_id' => $message_id]
            );
        }

        return false;
    }

    /**
     * Save lead magnet
     */
    public function save_lead_magnet($data) {
        global $wpdb;

        $defaults = [
            'title' => '',
            'type' => '',
            'topic' => '',
            'audience' => '',
            'metadata' => '',
            'content' => '',
            'files' => '',
            'status' => 'active',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Encode JSON fields
        foreach (['metadata', 'content', 'files'] as $field) {
            if (is_array($data[$field]) || is_object($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        $result = $wpdb->insert($this->tables['lead_magnets'], $data);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get lead magnet
     */
    public function get_lead_magnet($id) {
        global $wpdb;

        $magnet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['lead_magnets']} WHERE id = %d",
            $id
        ));

        if ($magnet) {
            // Decode JSON fields
            $magnet->metadata = json_decode($magnet->metadata, true);
            $magnet->content = json_decode($magnet->content, true);
            $magnet->files = json_decode($magnet->files, true);
        }

        return $magnet;
    }

    /**
     * Increment lead magnet downloads
     */
    public function increment_lead_magnet_downloads($id) {
        global $wpdb;

        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tables['lead_magnets']}
             SET download_count = download_count + 1
             WHERE id = %d",
            $id
        ));
    }

    /**
     * Create A/B test
     */
    public function create_ab_test($data) {
        global $wpdb;

        $defaults = [
            'funnel_id' => 0,
            'stage_id' => '',
            'test_name' => '',
            'test_type' => 'split',
            'variants' => [],
            'traffic_split' => [50, 50],
            'metrics' => [],
            'status' => 'running',
            'started_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Encode JSON fields
        foreach (['variants', 'traffic_split', 'metrics'] as $field) {
            if (is_array($data[$field]) || is_object($data[$field])) {
                $data[$field] = wp_json_encode($data[$field]);
            }
        }

        $result = $wpdb->insert($this->tables['ab_tests'], $data);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update A/B test
     */
    public function update_ab_test($test_id, $updates) {
        global $wpdb;

        // Encode JSON fields if present
        foreach (['variants', 'traffic_split', 'metrics'] as $field) {
            if (isset($updates[$field]) && (is_array($updates[$field]) || is_object($updates[$field]))) {
                $updates[$field] = wp_json_encode($updates[$field]);
            }
        }

        return $wpdb->update(
            $this->tables['ab_tests'],
            $updates,
            ['id' => $test_id]
        );
    }

    /**
     * Get A/B test results
     */
    public function get_ab_test_results($test_id) {
        global $wpdb;

        $test = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tables['ab_tests']} WHERE id = %d",
            $test_id
        ));

        if ($test) {
            $test->variants = json_decode($test->variants, true);
            $test->traffic_split = json_decode($test->traffic_split, true);
            $test->metrics = json_decode($test->metrics, true);

            // Get conversion data for each variant
            foreach ($test->variants as &$variant) {
                $variant['stats'] = $this->get_variant_stats($test_id, $variant['id']);
            }
        }

        return $test;
    }

    /**
     * Get variant statistics
     */
    private function get_variant_stats($test_id, $variant_id) {
        global $wpdb;

        // This would fetch real variant stats from tracking data
        return [
            'visitors' => rand(100, 1000),
            'conversions' => rand(10, 100),
            'conversion_rate' => rand(5, 25) / 100,
            'confidence' => rand(70, 99) / 100,
        ];
    }

    /**
     * Save analytics data
     */
    public function save_analytics($data) {
        global $wpdb;

        $defaults = [
            'funnel_id' => 0,
            'stage_id' => null,
            'date' => date('Y-m-d'),
            'hour' => null,
            'metric_type' => '',
            'metric_value' => 0,
            'dimensions' => [],
            'created_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        if (is_array($data['dimensions']) || is_object($data['dimensions'])) {
            $data['dimensions'] = wp_json_encode($data['dimensions']);
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE for aggregation
        $query = $wpdb->prepare(
            "INSERT INTO {$this->tables['analytics']}
             (funnel_id, stage_id, date, hour, metric_type, metric_value, dimensions, created_at)
             VALUES (%d, %s, %s, %d, %s, %f, %s, %s)
             ON DUPLICATE KEY UPDATE
             metric_value = metric_value + VALUES(metric_value)",
            $data['funnel_id'],
            $data['stage_id'],
            $data['date'],
            $data['hour'],
            $data['metric_type'],
            $data['metric_value'],
            $data['dimensions'],
            $data['created_at']
        );

        return $wpdb->query($query);
    }

    /**
     * Get analytics data
     */
    public function get_analytics($funnel_id, $args = []) {
        global $wpdb;

        $defaults = [
            'stage_id' => null,
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'metric_types' => [],
            'group_by' => 'date',
        ];

        $args = wp_parse_args($args, $defaults);

        $query = "SELECT * FROM {$this->tables['analytics']} WHERE funnel_id = %d";
        $params = [$funnel_id];

        if ($args['stage_id']) {
            $query .= " AND stage_id = %s";
            $params[] = $args['stage_id'];
        }

        $query .= " AND date BETWEEN %s AND %s";
        $params[] = $args['date_from'];
        $params[] = $args['date_to'];

        if (!empty($args['metric_types'])) {
            $placeholders = array_fill(0, count($args['metric_types']), '%s');
            $query .= " AND metric_type IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $args['metric_types']);
        }

        $query .= " ORDER BY date ASC";

        return $wpdb->get_results($wpdb->prepare($query, $params));
    }

    /**
     * Clean old tracking data
     */
    public function clean_old_data($days = 90) {
        global $wpdb;

        $date_threshold = date('Y-m-d', strtotime("-{$days} days"));

        // Clean tracking data
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['tracking']} WHERE created_at < %s",
            $date_threshold
        ));

        // Clean sessions
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['sessions']} WHERE created_at < %s",
            $date_threshold
        ));

        // Clean old email logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tables['email_log']} WHERE sent_at < %s",
            $date_threshold
        ));

        return true;
    }

    /**
     * Helper: Get date condition for queries
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
     * Helper: Get client IP
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
     * Get table name
     */
    public function get_table($name) {
        return $this->tables[$name] ?? null;
    }
}