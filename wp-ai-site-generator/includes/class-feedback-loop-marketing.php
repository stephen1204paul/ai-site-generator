<?php
/**
 * Feedback Loop Marketing System
 *
 * Manages customer journey mapping, behavior triggers, and segmentation
 *
 * @package WP_AI_Site_Generator
 * @subpackage Sales_Funnel
 */

namespace WP_AI_Site_Generator\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Feedback_Loop_Marketing
 */
class Feedback_Loop_Marketing {

    /**
     * Customer segments
     */
    private $segments = [];

    /**
     * Behavior triggers
     */
    private $triggers = [];

    /**
     * Journey stages
     */
    private $journey_stages = [];

    /**
     * Personalization rules
     */
    private $personalization_rules = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_segments();
        $this->init_triggers();
        $this->init_journey_stages();

        add_action('init', [$this, 'register_hooks']);
        add_action('wp_ai_funnel_conversion', [$this, 'handle_conversion_feedback'], 10, 4);
        add_action('wp_ai_funnel_event_tracked', [$this, 'process_behavior_trigger']);
        add_filter('wp_ai_funnel_content', [$this, 'personalize_content'], 10, 3);
    }

    /**
     * Initialize customer segments
     */
    private function init_segments() {
        $this->segments = [
            'new_visitor' => [
                'name' => 'New Visitor',
                'criteria' => ['visits' => 1, 'conversions' => 0],
                'messaging' => 'welcome',
                'offers' => ['lead_magnet', 'newsletter'],
            ],
            'engaged_prospect' => [
                'name' => 'Engaged Prospect',
                'criteria' => ['visits' => '3+', 'page_views' => '5+', 'conversions' => 0],
                'messaging' => 'nurture',
                'offers' => ['tripwire', 'webinar'],
            ],
            'first_time_buyer' => [
                'name' => 'First Time Buyer',
                'criteria' => ['purchases' => 1],
                'messaging' => 'onboarding',
                'offers' => ['upsell', 'cross_sell'],
            ],
            'repeat_customer' => [
                'name' => 'Repeat Customer',
                'criteria' => ['purchases' => '2+'],
                'messaging' => 'loyalty',
                'offers' => ['vip_access', 'referral_program'],
            ],
            'high_value_customer' => [
                'name' => 'High Value Customer',
                'criteria' => ['lifetime_value' => '500+'],
                'messaging' => 'vip',
                'offers' => ['exclusive_deals', 'personal_consultation'],
            ],
            'at_risk' => [
                'name' => 'At Risk',
                'criteria' => ['last_activity' => '30days+', 'purchases' => '1+'],
                'messaging' => 'win_back',
                'offers' => ['reactivation_discount', 'survey'],
            ],
            'churned' => [
                'name' => 'Churned',
                'criteria' => ['last_activity' => '90days+'],
                'messaging' => 'reengagement',
                'offers' => ['comeback_offer', 'new_features'],
            ],
        ];
    }

    /**
     * Initialize behavior triggers
     */
    private function init_triggers() {
        $this->triggers = [
            'cart_abandonment' => [
                'event' => 'cart_abandoned',
                'delay' => 3600, // 1 hour
                'action' => 'send_cart_recovery_email',
                'conditions' => ['cart_value' => '>0'],
            ],
            'form_abandonment' => [
                'event' => 'form_started_not_completed',
                'delay' => 86400, // 24 hours
                'action' => 'send_form_completion_reminder',
                'conditions' => ['form_progress' => '>25%'],
            ],
            'high_engagement' => [
                'event' => 'multiple_page_views',
                'threshold' => 5,
                'timeframe' => 1800, // 30 minutes
                'action' => 'show_special_offer',
            ],
            'price_drop_interest' => [
                'event' => 'viewed_product_multiple_times',
                'threshold' => 3,
                'action' => 'notify_price_drop',
            ],
            'webinar_no_show' => [
                'event' => 'webinar_registered_not_attended',
                'delay' => 3600, // 1 hour after webinar
                'action' => 'send_replay_link',
            ],
            'download_complete' => [
                'event' => 'lead_magnet_downloaded',
                'delay' => 86400, // 24 hours
                'action' => 'start_nurture_sequence',
            ],
            'milestone_reached' => [
                'event' => 'customer_milestone',
                'types' => ['first_purchase', '10th_purchase', 'anniversary'],
                'action' => 'send_celebration_reward',
            ],
        ];
    }

    /**
     * Initialize customer journey stages
     */
    private function init_journey_stages() {
        $this->journey_stages = [
            'awareness' => [
                'description' => 'Customer becomes aware of product/service',
                'touchpoints' => ['blog', 'social_media', 'ads', 'organic_search'],
                'goals' => ['brand_recognition', 'initial_interest'],
                'metrics' => ['impressions', 'reach', 'clicks'],
                'content_types' => ['educational', 'problem_aware'],
            ],
            'consideration' => [
                'description' => 'Customer evaluates solutions',
                'touchpoints' => ['website', 'comparison_pages', 'reviews', 'demos'],
                'goals' => ['engagement', 'trust_building'],
                'metrics' => ['page_views', 'time_on_site', 'return_visits'],
                'content_types' => ['comparison', 'case_studies', 'testimonials'],
            ],
            'decision' => [
                'description' => 'Customer ready to purchase',
                'touchpoints' => ['sales_page', 'pricing', 'checkout', 'support'],
                'goals' => ['conversion', 'reduce_friction'],
                'metrics' => ['conversion_rate', 'cart_abandonment', 'support_tickets'],
                'content_types' => ['offers', 'guarantees', 'urgency'],
            ],
            'onboarding' => [
                'description' => 'New customer getting started',
                'touchpoints' => ['welcome_email', 'tutorials', 'support', 'community'],
                'goals' => ['activation', 'early_success'],
                'metrics' => ['activation_rate', 'feature_adoption', 'support_usage'],
                'content_types' => ['guides', 'tips', 'best_practices'],
            ],
            'retention' => [
                'description' => 'Keeping customers engaged',
                'touchpoints' => ['product', 'email', 'support', 'updates'],
                'goals' => ['satisfaction', 'usage_growth'],
                'metrics' => ['retention_rate', 'usage_frequency', 'nps_score'],
                'content_types' => ['feature_updates', 'success_stories', 'rewards'],
            ],
            'advocacy' => [
                'description' => 'Customers become promoters',
                'touchpoints' => ['referral_program', 'reviews', 'community', 'social'],
                'goals' => ['referrals', 'testimonials'],
                'metrics' => ['referral_rate', 'review_count', 'social_shares'],
                'content_types' => ['referral_incentives', 'exclusive_content', 'recognition'],
            ],
        ];
    }

    /**
     * Register hooks
     */
    public function register_hooks() {
        // Schedule cron for trigger processing
        if (!wp_next_scheduled('process_marketing_triggers')) {
            wp_schedule_event(time(), 'hourly', 'process_marketing_triggers');
        }

        add_action('process_marketing_triggers', [$this, 'process_scheduled_triggers']);
    }

    /**
     * Get customer segment
     */
    public function get_customer_segment($customer_data) {
        $segments = [];

        foreach ($this->segments as $segment_id => $segment) {
            if ($this->matches_segment_criteria($customer_data, $segment['criteria'])) {
                $segments[] = $segment_id;
            }
        }

        // Return highest priority segment
        return $this->prioritize_segments($segments);
    }

    /**
     * Check if customer matches segment criteria
     */
    private function matches_segment_criteria($customer_data, $criteria) {
        foreach ($criteria as $key => $value) {
            if (!$this->evaluate_criterion($customer_data, $key, $value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Evaluate single criterion
     */
    private function evaluate_criterion($data, $key, $expected) {
        $actual = $data[$key] ?? 0;

        // Handle different operators
        if (strpos($expected, '+') !== false) {
            $min = intval($expected);
            return $actual >= $min;
        }

        if (strpos($expected, '-') !== false) {
            $parts = explode('-', $expected);
            $min = intval($parts[0]);
            $max = intval($parts[1]);
            return $actual >= $min && $actual <= $max;
        }

        if (strpos($expected, 'days') !== false) {
            $days = intval($expected);
            $date = $data[$key] ?? '';
            if ($date) {
                $diff = (time() - strtotime($date)) / 86400;
                return $diff >= $days;
            }
        }

        return $actual == $expected;
    }

    /**
     * Prioritize segments
     */
    private function prioritize_segments($segments) {
        $priority = [
            'churned' => 1,
            'at_risk' => 2,
            'high_value_customer' => 3,
            'repeat_customer' => 4,
            'first_time_buyer' => 5,
            'engaged_prospect' => 6,
            'new_visitor' => 7,
        ];

        usort($segments, function($a, $b) use ($priority) {
            return ($priority[$a] ?? 999) - ($priority[$b] ?? 999);
        });

        return $segments[0] ?? 'new_visitor';
    }

    /**
     * Map customer journey
     */
    public function map_customer_journey($visitor_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ai_funnel_tracking';

        // Get all events for visitor
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE visitor_id = %s
             ORDER BY created_at ASC",
            $visitor_id
        ));

        $journey = [
            'visitor_id' => $visitor_id,
            'current_stage' => $this->determine_journey_stage($events),
            'touchpoints' => [],
            'progression' => [],
            'velocity' => $this->calculate_journey_velocity($events),
            'next_best_action' => null,
        ];

        // Map touchpoints
        foreach ($events as $event) {
            $touchpoint = [
                'timestamp' => $event->created_at,
                'type' => $event->event_type,
                'stage' => $this->event_to_stage($event),
                'data' => json_decode($event->event_data, true),
            ];

            $journey['touchpoints'][] = $touchpoint;
        }

        // Track stage progression
        $journey['progression'] = $this->track_stage_progression($journey['touchpoints']);

        // Determine next best action
        $journey['next_best_action'] = $this->determine_next_action($journey);

        return $journey;
    }

    /**
     * Determine current journey stage
     */
    private function determine_journey_stage($events) {
        if (empty($events)) {
            return 'awareness';
        }

        $latest_event = end($events);
        $event_type = $latest_event->event_type;

        // Map event types to journey stages
        $stage_mapping = [
            'first_visit' => 'awareness',
            'content_view' => 'consideration',
            'demo_request' => 'consideration',
            'pricing_view' => 'decision',
            'cart_add' => 'decision',
            'purchase' => 'onboarding',
            'feature_use' => 'retention',
            'referral' => 'advocacy',
        ];

        return $stage_mapping[$event_type] ?? 'awareness';
    }

    /**
     * Calculate journey velocity
     */
    private function calculate_journey_velocity($events) {
        if (count($events) < 2) {
            return 'slow';
        }

        $first = reset($events);
        $last = end($events);

        $time_diff = strtotime($last->created_at) - strtotime($first->created_at);
        $event_count = count($events);

        $avg_time_between = $time_diff / ($event_count - 1);

        if ($avg_time_between < 3600) { // Less than 1 hour
            return 'fast';
        } elseif ($avg_time_between < 86400) { // Less than 1 day
            return 'medium';
        }

        return 'slow';
    }

    /**
     * Track stage progression
     */
    private function track_stage_progression($touchpoints) {
        $progression = [];
        $current_stage = null;

        foreach ($touchpoints as $touchpoint) {
            if ($touchpoint['stage'] !== $current_stage) {
                $progression[] = [
                    'stage' => $touchpoint['stage'],
                    'entered_at' => $touchpoint['timestamp'],
                ];
                $current_stage = $touchpoint['stage'];
            }
        }

        return $progression;
    }

    /**
     * Determine next best action
     */
    private function determine_next_action($journey) {
        $current_stage = $journey['current_stage'];
        $velocity = $journey['velocity'];

        $actions = [
            'awareness' => [
                'fast' => 'show_demo_cta',
                'medium' => 'offer_content_upgrade',
                'slow' => 'nurture_email_series',
            ],
            'consideration' => [
                'fast' => 'limited_time_offer',
                'medium' => 'case_study_email',
                'slow' => 'personal_consultation',
            ],
            'decision' => [
                'fast' => 'checkout_incentive',
                'medium' => 'overcome_objections',
                'slow' => 'abandoned_cart_recovery',
            ],
            'onboarding' => [
                'fast' => 'advanced_features',
                'medium' => 'success_checklist',
                'slow' => 'personal_onboarding',
            ],
            'retention' => [
                'fast' => 'upsell_opportunity',
                'medium' => 'feature_announcement',
                'slow' => 'check_in_call',
            ],
            'advocacy' => [
                'fast' => 'referral_request',
                'medium' => 'case_study_participation',
                'slow' => 'vip_program',
            ],
        ];

        return $actions[$current_stage][$velocity] ?? 'default_action';
    }

    /**
     * Process behavior trigger
     */
    public function process_behavior_trigger($event_data) {
        foreach ($this->triggers as $trigger_id => $trigger) {
            if ($this->should_fire_trigger($event_data, $trigger)) {
                $this->schedule_trigger_action($trigger_id, $trigger, $event_data);
            }
        }
    }

    /**
     * Check if trigger should fire
     */
    private function should_fire_trigger($event_data, $trigger) {
        // Check event type
        if ($event_data['event_type'] !== $trigger['event']) {
            return false;
        }

        // Check conditions
        if (isset($trigger['conditions'])) {
            foreach ($trigger['conditions'] as $key => $condition) {
                if (!$this->evaluate_condition($event_data, $key, $condition)) {
                    return false;
                }
            }
        }

        // Check threshold
        if (isset($trigger['threshold'])) {
            // Count recent events
            $count = $this->count_recent_events(
                $event_data['visitor_id'],
                $trigger['event'],
                $trigger['timeframe'] ?? 3600
            );

            if ($count < $trigger['threshold']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate condition
     */
    private function evaluate_condition($data, $key, $expected) {
        $actual = $data['event_data'][$key] ?? $data[$key] ?? null;

        if (strpos($expected, '>') === 0) {
            return $actual > floatval(substr($expected, 1));
        }

        if (strpos($expected, '<') === 0) {
            return $actual < floatval(substr($expected, 1));
        }

        return $actual == $expected;
    }

    /**
     * Count recent events
     */
    private function count_recent_events($visitor_id, $event_type, $timeframe) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_funnel_tracking';

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE visitor_id = %s
             AND event_type = %s
             AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $visitor_id,
            $event_type,
            $timeframe
        ));
    }

    /**
     * Schedule trigger action
     */
    private function schedule_trigger_action($trigger_id, $trigger, $event_data) {
        $delay = $trigger['delay'] ?? 0;

        if ($delay > 0) {
            wp_schedule_single_event(
                time() + $delay,
                'execute_marketing_trigger',
                [$trigger_id, $trigger['action'], $event_data]
            );
        } else {
            $this->execute_trigger_action($trigger['action'], $event_data);
        }
    }

    /**
     * Execute trigger action
     */
    private function execute_trigger_action($action, $data) {
        do_action('wp_ai_marketing_trigger_' . $action, $data);

        // Log trigger execution
        $this->log_trigger_execution($action, $data);
    }

    /**
     * Log trigger execution
     */
    private function log_trigger_execution($action, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_marketing_triggers_log';

        $wpdb->insert($table_name, [
            'action' => $action,
            'visitor_id' => $data['visitor_id'] ?? '',
            'data' => wp_json_encode($data),
            'executed_at' => current_time('mysql'),
        ]);
    }

    /**
     * Handle conversion feedback
     */
    public function handle_conversion_feedback($funnel_id, $stage_id, $visitor_id, $conversion_data) {
        // Get customer data
        $customer_data = $this->get_customer_data($visitor_id);

        // Update segment
        $new_segment = $this->get_customer_segment($customer_data);
        $this->update_customer_segment($visitor_id, $new_segment);

        // Trigger post-conversion actions
        $this->trigger_post_conversion_actions($visitor_id, $conversion_data);

        // Update personalization rules
        $this->update_personalization_rules($visitor_id, $conversion_data);
    }

    /**
     * Get customer data
     */
    private function get_customer_data($visitor_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_funnel_tracking';

        // Get aggregated customer data
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT
             COUNT(DISTINCT session_id) as visits,
             COUNT(*) as page_views,
             COUNT(CASE WHEN event_type = 'conversion' THEN 1 END) as conversions,
             COUNT(DISTINCT CASE WHEN event_type = 'purchase' THEN funnel_id END) as purchases,
             SUM(CASE WHEN event_type = 'purchase' THEN JSON_EXTRACT(event_data, '$.value') ELSE 0 END) as lifetime_value,
             MAX(created_at) as last_activity,
             MIN(created_at) as first_activity
             FROM {$table_name}
             WHERE visitor_id = %s",
            $visitor_id
        ));

        return (array) $data;
    }

    /**
     * Update customer segment
     */
    private function update_customer_segment($visitor_id, $segment) {
        update_user_meta($visitor_id, 'marketing_segment', $segment);

        // Trigger segment change action
        do_action('wp_ai_customer_segment_changed', $visitor_id, $segment);
    }

    /**
     * Trigger post-conversion actions
     */
    private function trigger_post_conversion_actions($visitor_id, $conversion_data) {
        $actions = [
            'form_submission' => ['send_welcome_email', 'start_nurture_sequence'],
            'purchase' => ['send_receipt', 'create_account', 'start_onboarding'],
            'webinar_registration' => ['send_confirmation', 'add_to_reminder_list'],
            'download' => ['deliver_content', 'tag_interest'],
        ];

        $conversion_type = $conversion_data['type'] ?? 'form_submission';

        if (isset($actions[$conversion_type])) {
            foreach ($actions[$conversion_type] as $action) {
                do_action('wp_ai_post_conversion_' . $action, $visitor_id, $conversion_data);
            }
        }
    }

    /**
     * Update personalization rules
     */
    private function update_personalization_rules($visitor_id, $conversion_data) {
        $current_rules = get_user_meta($visitor_id, 'personalization_rules', true) ?: [];

        $new_rules = [
            'interests' => $this->extract_interests($conversion_data),
            'preferences' => $this->extract_preferences($conversion_data),
            'behavior_patterns' => $this->extract_patterns($visitor_id),
        ];

        $updated_rules = array_merge_recursive($current_rules, $new_rules);

        update_user_meta($visitor_id, 'personalization_rules', $updated_rules);
    }

    /**
     * Extract interests from conversion data
     */
    private function extract_interests($data) {
        $interests = [];

        if (isset($data['product_category'])) {
            $interests[] = $data['product_category'];
        }

        if (isset($data['tags'])) {
            $interests = array_merge($interests, $data['tags']);
        }

        return array_unique($interests);
    }

    /**
     * Extract preferences
     */
    private function extract_preferences($data) {
        return [
            'communication' => $data['email_frequency'] ?? 'default',
            'content_type' => $data['preferred_content'] ?? 'mixed',
            'purchase_timing' => $data['purchase_time'] ?? 'immediate',
        ];
    }

    /**
     * Extract behavior patterns
     */
    private function extract_patterns($visitor_id) {
        // Analyze visitor behavior to identify patterns
        return [
            'active_time' => $this->get_most_active_time($visitor_id),
            'device_preference' => $this->get_device_preference($visitor_id),
            'content_consumption' => $this->get_content_pattern($visitor_id),
        ];
    }

    /**
     * Personalize content based on visitor data
     */
    public function personalize_content($content, $visitor_id, $context) {
        $segment = get_user_meta($visitor_id, 'marketing_segment', true);
        $rules = get_user_meta($visitor_id, 'personalization_rules', true);

        // Apply segment-based personalization
        if ($segment && isset($this->segments[$segment])) {
            $content = $this->apply_segment_messaging($content, $this->segments[$segment]);
        }

        // Apply rule-based personalization
        if ($rules) {
            $content = $this->apply_personalization_rules($content, $rules, $context);
        }

        return $content;
    }

    /**
     * Apply segment messaging
     */
    private function apply_segment_messaging($content, $segment) {
        $messaging_templates = [
            'welcome' => [
                'headline' => 'Welcome! Discover What Makes Us Different',
                'cta' => 'Start Your Journey',
            ],
            'nurture' => [
                'headline' => 'You\'re So Close to Achieving {goal}',
                'cta' => 'Take the Next Step',
            ],
            'loyalty' => [
                'headline' => 'Welcome Back, Valued Customer!',
                'cta' => 'Explore What\'s New',
            ],
            'win_back' => [
                'headline' => 'We\'ve Missed You! Here\'s Something Special',
                'cta' => 'Claim Your Offer',
            ],
        ];

        $messaging = $segment['messaging'];

        if (isset($messaging_templates[$messaging])) {
            foreach ($messaging_templates[$messaging] as $key => $value) {
                $content = str_replace("{{{$key}}}", $value, $content);
            }
        }

        return $content;
    }

    /**
     * Apply personalization rules
     */
    private function apply_personalization_rules($content, $rules, $context) {
        // Apply interest-based personalization
        if (isset($rules['interests']) && !empty($rules['interests'])) {
            $content = $this->personalize_by_interests($content, $rules['interests']);
        }

        // Apply behavior-based personalization
        if (isset($rules['behavior_patterns'])) {
            $content = $this->personalize_by_behavior($content, $rules['behavior_patterns']);
        }

        return $content;
    }

    /**
     * Helper methods for pattern analysis
     */
    private function get_most_active_time($visitor_id) {
        // Analyze timestamps to find most active time
        return 'evening'; // Placeholder
    }

    private function get_device_preference($visitor_id) {
        // Analyze device usage
        return 'mobile'; // Placeholder
    }

    private function get_content_pattern($visitor_id) {
        // Analyze content consumption
        return 'video_preferred'; // Placeholder
    }

    private function personalize_by_interests($content, $interests) {
        // Personalize based on interests
        return $content;
    }

    private function personalize_by_behavior($content, $patterns) {
        // Personalize based on behavior
        return $content;
    }

    /**
     * Process scheduled triggers
     */
    public function process_scheduled_triggers() {
        // Process any scheduled trigger actions
        do_action('wp_ai_process_scheduled_marketing_triggers');
    }

    /**
     * Event to stage mapping
     */
    private function event_to_stage($event) {
        $mapping = [
            'page_view' => 'awareness',
            'content_engagement' => 'consideration',
            'form_start' => 'decision',
            'conversion' => 'onboarding',
            'feature_use' => 'retention',
            'share' => 'advocacy',
        ];

        return $mapping[$event->event_type] ?? 'awareness';
    }
}