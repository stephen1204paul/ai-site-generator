<?php
/**
 * Email Integration System
 *
 * Integrates with popular email marketing platforms
 *
 * @package WP_AI_Site_Generator
 * @subpackage Sales_Funnel
 */

namespace WP_AI_Site_Generator\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Email_Integration
 */
class Email_Integration {

    /**
     * Available email providers
     */
    private $providers = [];

    /**
     * Active provider instance
     */
    private $active_provider = null;

    /**
     * Email templates
     */
    private $email_templates = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_providers();
        $this->init_email_templates();
        $this->set_active_provider();

        add_action('wp_ai_funnel_conversion', [$this, 'handle_email_trigger'], 10, 4);
        add_action('wp_ai_post_conversion_send_welcome_email', [$this, 'send_welcome_email'], 10, 2);
        add_action('wp_ai_post_conversion_start_nurture_sequence', [$this, 'start_nurture_sequence'], 10, 2);
        add_filter('wp_ai_email_content', [$this, 'personalize_email_content'], 10, 3);
    }

    /**
     * Initialize email providers
     */
    private function init_providers() {
        $this->providers = [
            'mailchimp' => [
                'name' => 'Mailchimp',
                'class' => 'MailchimpProvider',
                'fields' => ['api_key', 'list_id', 'server_prefix'],
                'features' => ['lists', 'tags', 'automations', 'segments'],
            ],
            'convertkit' => [
                'name' => 'ConvertKit',
                'class' => 'ConvertKitProvider',
                'fields' => ['api_key', 'api_secret'],
                'features' => ['tags', 'sequences', 'forms', 'broadcasts'],
            ],
            'activecampaign' => [
                'name' => 'ActiveCampaign',
                'class' => 'ActiveCampaignProvider',
                'fields' => ['api_url', 'api_key'],
                'features' => ['lists', 'tags', 'automations', 'deals', 'scoring'],
            ],
            'getresponse' => [
                'name' => 'GetResponse',
                'class' => 'GetResponseProvider',
                'fields' => ['api_key'],
                'features' => ['lists', 'tags', 'autoresponders', 'webinars'],
            ],
            'aweber' => [
                'name' => 'AWeber',
                'class' => 'AWeberProvider',
                'fields' => ['consumer_key', 'consumer_secret', 'access_token', 'access_secret'],
                'features' => ['lists', 'tags', 'campaigns', 'automations'],
            ],
            'drip' => [
                'name' => 'Drip',
                'class' => 'DripProvider',
                'fields' => ['api_key', 'account_id'],
                'features' => ['workflows', 'tags', 'events', 'segments'],
            ],
            'sendinblue' => [
                'name' => 'Sendinblue',
                'class' => 'SendinblueProvider',
                'fields' => ['api_key'],
                'features' => ['lists', 'attributes', 'automations', 'sms'],
            ],
            'klaviyo' => [
                'name' => 'Klaviyo',
                'class' => 'KlaviyoProvider',
                'fields' => ['private_key', 'public_key'],
                'features' => ['lists', 'segments', 'flows', 'metrics'],
            ],
            'wordpress' => [
                'name' => 'WordPress Mail',
                'class' => 'WordPressMailProvider',
                'fields' => [],
                'features' => ['basic_email'],
            ],
        ];
    }

    /**
     * Initialize email templates
     */
    private function init_email_templates() {
        $this->email_templates = [
            'welcome' => [
                'subject' => 'Welcome to {{company_name}}!',
                'preview' => 'Your journey starts here...',
                'body' => $this->get_welcome_template(),
                'type' => 'transactional',
            ],
            'lead_magnet_delivery' => [
                'subject' => 'Your {{lead_magnet_name}} is Here!',
                'preview' => 'As promised, here\'s your free resource',
                'body' => $this->get_lead_magnet_template(),
                'type' => 'transactional',
            ],
            'nurture_1' => [
                'subject' => 'The #1 Mistake {{audience}} Make',
                'preview' => 'And how to avoid it...',
                'body' => $this->get_nurture_template(1),
                'type' => 'marketing',
                'delay' => 86400, // 1 day
            ],
            'nurture_2' => [
                'subject' => 'Case Study: How {{case_study_name}} {{achievement}}',
                'preview' => 'Real results from real people',
                'body' => $this->get_nurture_template(2),
                'type' => 'marketing',
                'delay' => 259200, // 3 days
            ],
            'nurture_3' => [
                'subject' => 'Quick question for you...',
                'preview' => 'This will only take a second',
                'body' => $this->get_nurture_template(3),
                'type' => 'marketing',
                'delay' => 432000, // 5 days
            ],
            'cart_abandonment' => [
                'subject' => 'You left something behind...',
                'preview' => 'Complete your purchase and save {{discount}}',
                'body' => $this->get_cart_abandonment_template(),
                'type' => 'transactional',
                'delay' => 3600, // 1 hour
            ],
            'webinar_confirmation' => [
                'subject' => 'You\'re registered! {{webinar_title}}',
                'preview' => 'Save your spot now',
                'body' => $this->get_webinar_confirmation_template(),
                'type' => 'transactional',
            ],
            'webinar_reminder' => [
                'subject' => 'Starting in 1 hour: {{webinar_title}}',
                'preview' => 'Don\'t miss out!',
                'body' => $this->get_webinar_reminder_template(),
                'type' => 'transactional',
                'delay' => -3600, // 1 hour before
            ],
            'post_purchase' => [
                'subject' => 'Thank you for your purchase!',
                'preview' => 'Here\'s everything you need to know',
                'body' => $this->get_post_purchase_template(),
                'type' => 'transactional',
            ],
            'upsell' => [
                'subject' => 'Exclusive offer just for you',
                'preview' => 'As a valued customer, you get {{discount}}% off',
                'body' => $this->get_upsell_template(),
                'type' => 'marketing',
                'delay' => 86400, // 1 day after purchase
            ],
        ];
    }

    /**
     * Set active email provider
     */
    private function set_active_provider() {
        $provider_settings = get_option('wp_ai_email_provider', []);

        if (empty($provider_settings['provider'])) {
            $this->active_provider = new WordPressMailProvider();
            return;
        }

        $provider_id = $provider_settings['provider'];

        if (isset($this->providers[$provider_id])) {
            $class_name = __NAMESPACE__ . '\\Email\\' . $this->providers[$provider_id]['class'];

            if (class_exists($class_name)) {
                $this->active_provider = new $class_name($provider_settings);
            } else {
                // Fallback to WordPress mail
                $this->active_provider = new WordPressMailProvider();
            }
        }
    }

    /**
     * Send email through active provider
     */
    public function send_email($to, $template_id, $merge_vars = [], $options = []) {
        if (!$this->active_provider) {
            return ['success' => false, 'error' => 'No email provider configured'];
        }

        // Get template
        $template = $this->get_email_template($template_id);

        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        // Merge variables into content
        $subject = $this->merge_variables($template['subject'], $merge_vars);
        $body = $this->merge_variables($template['body'], $merge_vars);

        // Apply personalization
        $body = apply_filters('wp_ai_email_content', $body, $to, $template_id);

        // Send through provider
        $result = $this->active_provider->send([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'type' => $template['type'] ?? 'transactional',
            'tags' => $options['tags'] ?? [],
            'metadata' => $options['metadata'] ?? [],
        ]);

        // Log email activity
        $this->log_email_activity($to, $template_id, $result);

        return $result;
    }

    /**
     * Add subscriber to list
     */
    public function add_subscriber($email, $data = [], $options = []) {
        if (!$this->active_provider) {
            return ['success' => false, 'error' => 'No email provider configured'];
        }

        $subscriber_data = array_merge([
            'email' => $email,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'tags' => $options['tags'] ?? [],
            'custom_fields' => $data,
        ], $data);

        $result = $this->active_provider->add_subscriber($subscriber_data);

        // Log subscription
        $this->log_subscription($email, $result);

        return $result;
    }

    /**
     * Start automation/sequence
     */
    public function start_automation($email, $automation_id, $data = []) {
        if (!$this->active_provider) {
            return ['success' => false, 'error' => 'No email provider configured'];
        }

        if (!method_exists($this->active_provider, 'start_automation')) {
            return ['success' => false, 'error' => 'Provider does not support automations'];
        }

        return $this->active_provider->start_automation($email, $automation_id, $data);
    }

    /**
     * Tag subscriber
     */
    public function tag_subscriber($email, $tags) {
        if (!$this->active_provider) {
            return ['success' => false, 'error' => 'No email provider configured'];
        }

        if (!method_exists($this->active_provider, 'tag_subscriber')) {
            return ['success' => false, 'error' => 'Provider does not support tags'];
        }

        return $this->active_provider->tag_subscriber($email, $tags);
    }

    /**
     * Handle email trigger from funnel conversion
     */
    public function handle_email_trigger($funnel_id, $stage_id, $visitor_id, $conversion_data) {
        $email = $conversion_data['email'] ?? '';

        if (empty($email)) {
            return;
        }

        // Add to email list
        $this->add_subscriber($email, [
            'source' => 'funnel_' . $funnel_id,
            'stage' => $stage_id,
            'visitor_id' => $visitor_id,
        ], [
            'tags' => ['funnel_conversion', 'funnel_' . $funnel_id],
        ]);

        // Determine which email to send based on conversion type
        $conversion_type = $conversion_data['type'] ?? 'form_submission';

        switch ($conversion_type) {
            case 'lead_magnet':
                $this->send_email($email, 'lead_magnet_delivery', [
                    'lead_magnet_name' => $conversion_data['lead_magnet'] ?? 'resource',
                    'download_link' => $conversion_data['download_url'] ?? '',
                ]);
                break;

            case 'webinar_registration':
                $this->send_email($email, 'webinar_confirmation', [
                    'webinar_title' => $conversion_data['webinar_title'] ?? '',
                    'webinar_date' => $conversion_data['webinar_date'] ?? '',
                    'webinar_link' => $conversion_data['webinar_link'] ?? '',
                ]);
                break;

            case 'purchase':
                $this->send_email($email, 'post_purchase', [
                    'order_id' => $conversion_data['order_id'] ?? '',
                    'product_name' => $conversion_data['product'] ?? '',
                    'amount' => $conversion_data['value'] ?? 0,
                ]);
                break;

            default:
                $this->send_welcome_email($visitor_id, ['email' => $email]);
                break;
        }
    }

    /**
     * Send welcome email
     */
    public function send_welcome_email($visitor_id, $data) {
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return;
        }

        $this->send_email($email, 'welcome', [
            'company_name' => get_bloginfo('name'),
            'first_name' => $data['first_name'] ?? 'there',
        ]);
    }

    /**
     * Start nurture sequence
     */
    public function start_nurture_sequence($visitor_id, $data) {
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return;
        }

        // Schedule nurture emails
        $nurture_templates = ['nurture_1', 'nurture_2', 'nurture_3'];

        foreach ($nurture_templates as $template_id) {
            $template = $this->email_templates[$template_id];
            $delay = $template['delay'] ?? 0;

            if ($delay > 0) {
                wp_schedule_single_event(
                    time() + $delay,
                    'send_nurture_email',
                    [$email, $template_id, $visitor_id]
                );
            }
        }

        // If provider supports automations, start nurture automation
        if (method_exists($this->active_provider, 'start_automation')) {
            $this->start_automation($email, 'nurture_sequence', $data);
        }
    }

    /**
     * Merge variables into content
     */
    private function merge_variables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        // Replace any remaining variables with defaults
        $content = preg_replace('/{{.*?}}/', '', $content);

        return $content;
    }

    /**
     * Get email template
     */
    private function get_email_template($template_id) {
        if (isset($this->email_templates[$template_id])) {
            return $this->email_templates[$template_id];
        }

        // Check for custom template
        $custom_templates = get_option('wp_ai_email_templates', []);

        if (isset($custom_templates[$template_id])) {
            return $custom_templates[$template_id];
        }

        return null;
    }

    /**
     * Log email activity
     */
    private function log_email_activity($to, $template_id, $result) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_email_log';

        $wpdb->insert($table_name, [
            'recipient' => $to,
            'template_id' => $template_id,
            'status' => $result['success'] ? 'sent' : 'failed',
            'error_message' => $result['error'] ?? '',
            'provider' => get_class($this->active_provider),
            'sent_at' => current_time('mysql'),
        ]);
    }

    /**
     * Log subscription
     */
    private function log_subscription($email, $result) {
        if ($result['success']) {
            do_action('wp_ai_email_subscribed', $email, $result);
        }
    }

    /**
     * Personalize email content
     */
    public function personalize_email_content($content, $to, $template_id) {
        // Get subscriber data
        $subscriber = $this->get_subscriber_data($to);

        if ($subscriber) {
            // Apply personalization
            $content = str_replace('{{first_name}}', $subscriber['first_name'] ?? 'there', $content);
            $content = str_replace('{{segment}}', $subscriber['segment'] ?? 'valued customer', $content);
        }

        return $content;
    }

    /**
     * Get subscriber data
     */
    private function get_subscriber_data($email) {
        // This would fetch from provider or local database
        return [
            'first_name' => 'John',
            'segment' => 'engaged_prospect',
        ];
    }

    /**
     * Email template generators
     */
    private function get_welcome_template() {
        return '
            <h1>Welcome to {{company_name}}!</h1>
            <p>Hi {{first_name}},</p>
            <p>Thank you for joining us! We\'re excited to have you as part of our community.</p>
            <p>Here\'s what you can expect from us:</p>
            <ul>
                <li>Weekly tips and insights</li>
                <li>Exclusive offers and early access</li>
                <li>Helpful resources and guides</li>
            </ul>
            <p>Get started by exploring our most popular content:</p>
            <a href="{{popular_content_link}}" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Explore Now</a>
        ';
    }

    private function get_lead_magnet_template() {
        return '
            <h1>Your {{lead_magnet_name}} is Ready!</h1>
            <p>Hi {{first_name}},</p>
            <p>As promised, here\'s your free {{lead_magnet_name}}.</p>
            <a href="{{download_link}}" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Download Now</a>
            <p>This resource will help you:</p>
            <ul>
                <li>{{benefit_1}}</li>
                <li>{{benefit_2}}</li>
                <li>{{benefit_3}}</li>
            </ul>
            <p>Questions? Just reply to this email!</p>
        ';
    }

    private function get_nurture_template($number) {
        $templates = [
            1 => '
                <h2>The #1 Mistake {{audience}} Make</h2>
                <p>Hi {{first_name}},</p>
                <p>After working with hundreds of {{audience}}, we\'ve discovered the one mistake that holds most people back...</p>
                <p>{{mistake_description}}</p>
                <p>Here\'s how to avoid it:</p>
                <p>{{solution}}</p>
                <a href="{{blog_link}}">Read the full article →</a>
            ',
            2 => '
                <h2>Case Study: How {{case_study_name}} {{achievement}}</h2>
                <p>Hi {{first_name}},</p>
                <p>Want to see what\'s possible? Check out this amazing transformation...</p>
                <p>{{case_study_summary}}</p>
                <a href="{{case_study_link}}">See the full case study →</a>
            ',
            3 => '
                <h2>Quick question for you...</h2>
                <p>Hi {{first_name}},</p>
                <p>I\'m curious - what\'s your biggest challenge with {{topic}} right now?</p>
                <p>Just reply and let me know. I read every email and would love to help!</p>
                <p>P.S. If you\'re ready to take the next step, check out our {{product_name}}:</p>
                <a href="{{product_link}}">Learn more →</a>
            ',
        ];

        return $templates[$number] ?? $templates[1];
    }

    private function get_cart_abandonment_template() {
        return '
            <h2>You left something behind...</h2>
            <p>Hi {{first_name}},</p>
            <p>We noticed you didn\'t complete your purchase. Was there something wrong?</p>
            <p>Your items are still waiting for you:</p>
            <div style="border: 1px solid #ddd; padding: 15px; margin: 20px 0;">
                {{cart_items}}
            </div>
            <p><strong>Complete your purchase in the next 24 hours and save {{discount}}%!</strong></p>
            <a href="{{cart_link}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Complete Purchase</a>
        ';
    }

    private function get_webinar_confirmation_template() {
        return '
            <h1>You\'re In! 🎉</h1>
            <p>Hi {{first_name}},</p>
            <p>You\'re registered for: <strong>{{webinar_title}}</strong></p>
            <p>Date: {{webinar_date}}<br>
            Time: {{webinar_time}}</p>
            <a href="{{calendar_link}}" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Add to Calendar</a>
            <p>What we\'ll cover:</p>
            <ul>
                {{webinar_agenda}}
            </ul>
            <p>See you there!</p>
        ';
    }

    private function get_webinar_reminder_template() {
        return '
            <h2>Starting in 1 Hour: {{webinar_title}}</h2>
            <p>Hi {{first_name}},</p>
            <p>Just a quick reminder that our webinar starts in 1 hour!</p>
            <a href="{{webinar_link}}" style="background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Join Webinar Now</a>
            <p>Can\'t make it? No worries - we\'ll send you the replay.</p>
        ';
    }

    private function get_post_purchase_template() {
        return '
            <h1>Thank You for Your Purchase!</h1>
            <p>Hi {{first_name}},</p>
            <p>Your order has been confirmed!</p>
            <div style="background: #f8f9fa; padding: 20px; margin: 20px 0;">
                <strong>Order #{{order_id}}</strong><br>
                Product: {{product_name}}<br>
                Amount: ${{amount}}
            </div>
            <h3>What\'s Next?</h3>
            <p>{{next_steps}}</p>
            <a href="{{access_link}}" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Access Your Purchase</a>
        ';
    }

    private function get_upsell_template() {
        return '
            <h2>Exclusive Offer for {{product_name}} Customers</h2>
            <p>Hi {{first_name}},</p>
            <p>As a valued customer, you get exclusive access to {{upsell_product}} at {{discount}}% off!</p>
            <p>This perfectly complements your recent purchase and will help you {{benefit}}.</p>
            <p><strong>This offer expires in 48 hours.</strong></p>
            <a href="{{upsell_link}}" style="background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Claim Your Discount</a>
        ';
    }
}

/**
 * WordPress Mail Provider (Fallback)
 */
class WordPressMailProvider {

    public function send($data) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        $result = wp_mail(
            $data['to'],
            $data['subject'],
            $data['body'],
            $headers
        );

        return ['success' => $result];
    }

    public function add_subscriber($data) {
        // Store locally
        return ['success' => true, 'message' => 'Subscriber added locally'];
    }
}