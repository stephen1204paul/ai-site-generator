<?php
/**
 * Sales Funnel Builder
 *
 * @package WP_AI_Site_Generator
 * @since 1.0.0
 */

namespace WP_AI_Site_Generator\Includes;

use WP_AI_Site_Generator\Generators\Page_Generator;
use WP_AI_Site_Generator\Database\Funnel_DB_Handler;

/**
 * Sales Funnel Builder Class
 */
class Sales_Funnel {

    /**
     * Funnel types
     */
    const FUNNEL_TYPES = [
        'lead_generation' => 'Lead Generation Funnel',
        'webinar' => 'Webinar Funnel',
        'product_launch' => 'Product Launch Funnel',
        'service' => 'Service Sales Funnel',
        'ecommerce' => 'E-commerce Funnel',
        'tripwire' => 'Tripwire Funnel',
        'membership' => 'Membership Funnel',
        'book' => 'Book Funnel',
        'survey' => 'Survey Funnel',
        'application' => 'Application Funnel'
    ];

    /**
     * Funnel stages
     */
    const FUNNEL_STAGES = [
        'awareness' => [
            'name' => 'Awareness',
            'description' => 'Top of funnel - Building brand awareness',
            'page_types' => ['landing', 'blog', 'video', 'social_proof']
        ],
        'interest' => [
            'name' => 'Interest',
            'description' => 'Generating interest in the product/service',
            'page_types' => ['lead_magnet', 'webinar_registration', 'quiz', 'survey']
        ],
        'decision' => [
            'name' => 'Decision',
            'description' => 'Helping prospects make a buying decision',
            'page_types' => ['sales', 'demo', 'consultation', 'comparison']
        ],
        'action' => [
            'name' => 'Action',
            'description' => 'Converting prospects to customers',
            'page_types' => ['checkout', 'order_form', 'appointment', 'signup']
        ],
        'retention' => [
            'name' => 'Retention',
            'description' => 'Keeping customers engaged',
            'page_types' => ['thank_you', 'onboarding', 'upsell', 'member_area']
        ]
    ];

    /**
     * Urgency elements
     */
    const URGENCY_ELEMENTS = [
        'countdown_timer' => 'Countdown Timer',
        'limited_seats' => 'Limited Seats Available',
        'flash_sale' => 'Flash Sale',
        'early_bird' => 'Early Bird Pricing',
        'bonus_expiry' => 'Bonus Expiry',
        'cart_close' => 'Cart Closing Soon',
        'price_increase' => 'Price Increasing Soon',
        'limited_stock' => 'Limited Stock'
    ];

    /**
     * Database handler
     *
     * @var Funnel_DB_Handler
     */
    private $db_handler;

    /**
     * Page generator
     *
     * @var Page_Generator
     */
    private $page_generator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db_handler = new Funnel_DB_Handler();
        $this->page_generator = new Page_Generator();
    }

    /**
     * Create a complete sales funnel
     *
     * @param array $config Funnel configuration
     * @return array Funnel details with generated pages
     */
    public function create_funnel( $config ) {
        $defaults = [
            'name' => 'Sales Funnel',
            'type' => 'lead_generation',
            'industry' => 'general',
            'target_audience' => '',
            'product_name' => '',
            'product_description' => '',
            'price_point' => '',
            'urgency_level' => 'medium',
            'stages' => ['awareness', 'interest', 'decision', 'action', 'retention'],
            'include_upsells' => true,
            'include_downsells' => true,
            'include_order_bumps' => true,
            'include_exit_intent' => true,
            'email_integration' => '',
            'tracking_enabled' => true,
            'ab_testing_enabled' => false
        ];

        $config = wp_parse_args( $config, $defaults );

        // Generate funnel ID
        $funnel_id = wp_generate_uuid4();

        // Create funnel pages based on type
        $pages = $this->generate_funnel_pages( $config );

        // Set up funnel flow
        $flow = $this->create_funnel_flow( $pages, $config );

        // Add urgency elements
        if ( $config['urgency_level'] !== 'none' ) {
            $pages = $this->add_urgency_elements( $pages, $config['urgency_level'] );
        }

        // Add exit intent popups
        if ( $config['include_exit_intent'] ) {
            $pages = $this->add_exit_intent_popups( $pages, $config );
        }

        // Save funnel to database
        $funnel_data = [
            'id' => $funnel_id,
            'name' => $config['name'],
            'type' => $config['type'],
            'config' => $config,
            'pages' => $pages,
            'flow' => $flow,
            'created_at' => current_time( 'mysql' ),
            'status' => 'active'
        ];

        $this->db_handler->save_funnel( $funnel_data );

        // Set up tracking
        if ( $config['tracking_enabled'] ) {
            $this->setup_funnel_tracking( $funnel_id );
        }

        // Set up A/B testing if enabled
        if ( $config['ab_testing_enabled'] ) {
            $this->setup_ab_testing( $funnel_id, $pages );
        }

        return [
            'success' => true,
            'funnel_id' => $funnel_id,
            'data' => $funnel_data,
            'message' => sprintf(
                'Successfully created %s funnel with %d pages',
                $config['name'],
                count( $pages )
            )
        ];
    }

    /**
     * Generate funnel pages based on type
     *
     * @param array $config Funnel configuration
     * @return array Generated pages
     */
    private function generate_funnel_pages( $config ) {
        $pages = [];
        $funnel_templates = $this->get_funnel_templates( $config['type'] );

        foreach ( $funnel_templates as $template ) {
            $page_config = [
                'type' => $template['type'],
                'title' => $this->generate_page_title( $template, $config ),
                'content' => $this->generate_page_content( $template, $config ),
                'meta' => [
                    'funnel_stage' => $template['stage'],
                    'page_role' => $template['role'],
                    'conversion_goal' => $template['goal']
                ]
            ];

            // Generate the page using AI
            $generated_page = $this->page_generator->generate( $page_config );

            // Add funnel-specific elements
            $generated_page = $this->enhance_page_for_funnel( $generated_page, $template, $config );

            $pages[] = $generated_page;
        }

        // Add upsell pages if enabled
        if ( $config['include_upsells'] ) {
            $pages = array_merge( $pages, $this->generate_upsell_pages( $config ) );
        }

        // Add downsell pages if enabled
        if ( $config['include_downsells'] ) {
            $pages = array_merge( $pages, $this->generate_downsell_pages( $config ) );
        }

        return $pages;
    }

    /**
     * Get funnel templates based on type
     *
     * @param string $type Funnel type
     * @return array Templates
     */
    private function get_funnel_templates( $type ) {
        $templates = [
            'lead_generation' => [
                ['type' => 'landing', 'stage' => 'awareness', 'role' => 'entry', 'goal' => 'capture_attention'],
                ['type' => 'lead_capture', 'stage' => 'interest', 'role' => 'optin', 'goal' => 'collect_email'],
                ['type' => 'thank_you', 'stage' => 'interest', 'role' => 'confirmation', 'goal' => 'deliver_value'],
                ['type' => 'sales', 'stage' => 'decision', 'role' => 'offer', 'goal' => 'present_offer'],
                ['type' => 'checkout', 'stage' => 'action', 'role' => 'conversion', 'goal' => 'complete_sale'],
                ['type' => 'success', 'stage' => 'retention', 'role' => 'fulfillment', 'goal' => 'onboard_customer']
            ],
            'webinar' => [
                ['type' => 'webinar_landing', 'stage' => 'awareness', 'role' => 'entry', 'goal' => 'generate_interest'],
                ['type' => 'registration', 'stage' => 'interest', 'role' => 'optin', 'goal' => 'register_attendee'],
                ['type' => 'confirmation', 'stage' => 'interest', 'role' => 'confirmation', 'goal' => 'confirm_registration'],
                ['type' => 'webinar_room', 'stage' => 'decision', 'role' => 'presentation', 'goal' => 'deliver_webinar'],
                ['type' => 'offer', 'stage' => 'action', 'role' => 'pitch', 'goal' => 'present_offer'],
                ['type' => 'order', 'stage' => 'action', 'role' => 'conversion', 'goal' => 'close_sale'],
                ['type' => 'replay', 'stage' => 'retention', 'role' => 'followup', 'goal' => 're_engage']
            ],
            'product_launch' => [
                ['type' => 'coming_soon', 'stage' => 'awareness', 'role' => 'teaser', 'goal' => 'build_anticipation'],
                ['type' => 'waitlist', 'stage' => 'interest', 'role' => 'optin', 'goal' => 'build_list'],
                ['type' => 'prelaunch', 'stage' => 'interest', 'role' => 'warmup', 'goal' => 'educate'],
                ['type' => 'launch', 'stage' => 'decision', 'role' => 'announcement', 'goal' => 'create_urgency'],
                ['type' => 'sales', 'stage' => 'decision', 'role' => 'offer', 'goal' => 'convert'],
                ['type' => 'checkout', 'stage' => 'action', 'role' => 'transaction', 'goal' => 'process_order'],
                ['type' => 'welcome', 'stage' => 'retention', 'role' => 'onboarding', 'goal' => 'activate_customer']
            ],
            'service' => [
                ['type' => 'service_landing', 'stage' => 'awareness', 'role' => 'introduction', 'goal' => 'explain_service'],
                ['type' => 'case_studies', 'stage' => 'interest', 'role' => 'proof', 'goal' => 'build_trust'],
                ['type' => 'consultation', 'stage' => 'interest', 'role' => 'qualification', 'goal' => 'qualify_lead'],
                ['type' => 'proposal', 'stage' => 'decision', 'role' => 'offer', 'goal' => 'present_solution'],
                ['type' => 'booking', 'stage' => 'action', 'role' => 'commitment', 'goal' => 'schedule_service'],
                ['type' => 'contract', 'stage' => 'action', 'role' => 'agreement', 'goal' => 'finalize_terms'],
                ['type' => 'client_portal', 'stage' => 'retention', 'role' => 'service', 'goal' => 'deliver_value']
            ],
            'ecommerce' => [
                ['type' => 'store_home', 'stage' => 'awareness', 'role' => 'catalog', 'goal' => 'showcase_products'],
                ['type' => 'product', 'stage' => 'interest', 'role' => 'detail', 'goal' => 'inform_buyer'],
                ['type' => 'cart', 'stage' => 'decision', 'role' => 'review', 'goal' => 'confirm_selection'],
                ['type' => 'checkout', 'stage' => 'action', 'role' => 'purchase', 'goal' => 'complete_transaction'],
                ['type' => 'order_confirm', 'stage' => 'action', 'role' => 'confirmation', 'goal' => 'confirm_order'],
                ['type' => 'account', 'stage' => 'retention', 'role' => 'loyalty', 'goal' => 'encourage_repeat']
            ]
        ];

        return isset( $templates[$type] ) ? $templates[$type] : $templates['lead_generation'];
    }

    /**
     * Generate page title based on template and config
     *
     * @param array $template Page template
     * @param array $config Funnel config
     * @return string Generated title
     */
    private function generate_page_title( $template, $config ) {
        $titles = [
            'landing' => "Discover How {product} Can Transform Your {industry}",
            'lead_capture' => "Get Your Free {product} Guide",
            'thank_you' => "Your {product} Is On The Way!",
            'sales' => "Exclusive Offer: {product} - Limited Time",
            'checkout' => "Complete Your {product} Order",
            'success' => "Welcome to {product}!",
            'webinar_landing' => "Free Webinar: Master {product} in {industry}",
            'registration' => "Register for Your Free {product} Training",
            'confirmation' => "You're Registered! Check Your Email",
            'offer' => "Special Webinar Offer - {product}",
            'upsell' => "Wait! Upgrade Your {product} Order",
            'downsell' => "Special Discount on {product}"
        ];

        $title = isset( $titles[$template['type']] ) ? $titles[$template['type']] : "Welcome to {product}";

        $title = str_replace( '{product}', $config['product_name'], $title );
        $title = str_replace( '{industry}', $config['industry'], $title );

        return $title;
    }

    /**
     * Generate page content based on template and config
     *
     * @param array $template Page template
     * @param array $config Funnel config
     * @return array Content configuration
     */
    private function generate_page_content( $template, $config ) {
        return [
            'headline' => $this->generate_headline( $template, $config ),
            'subheadline' => $this->generate_subheadline( $template, $config ),
            'body_copy' => $this->generate_body_copy( $template, $config ),
            'call_to_action' => $this->generate_cta( $template, $config ),
            'testimonials' => $this->generate_testimonials( $config ),
            'features' => $this->generate_features( $config ),
            'benefits' => $this->generate_benefits( $config ),
            'guarantee' => $this->generate_guarantee( $config ),
            'faq' => $this->generate_faq( $config )
        ];
    }

    /**
     * Generate headline
     */
    private function generate_headline( $template, $config ) {
        $headlines = [
            'awareness' => [
                "The Secret to {benefit} Without {pain_point}",
                "How {target_audience} Are Getting {benefit} in {timeframe}",
                "Finally, A {product_category} That Actually {unique_value}"
            ],
            'interest' => [
                "Get The {product} {target_audience} Are Raving About",
                "Join {number}+ {target_audience} Who've Discovered {benefit}",
                "The {product} That's Changing How {target_audience} {achievement}"
            ],
            'decision' => [
                "Ready to {achievement}? {product} Makes It Simple",
                "Why {number}+ {target_audience} Choose {product}",
                "The Proven System for {benefit} - Guaranteed"
            ],
            'action' => [
                "Yes! I Want {benefit} Now",
                "Claim Your {product} Before {urgency}",
                "Start {achievement} Today with {product}"
            ]
        ];

        $stage_headlines = $headlines[$template['stage']] ?? $headlines['awareness'];
        $headline = $stage_headlines[array_rand( $stage_headlines )];

        // Replace placeholders
        $headline = $this->replace_content_placeholders( $headline, $config );

        return $headline;
    }

    /**
     * Generate subheadline
     */
    private function generate_subheadline( $template, $config ) {
        return "Discover the proven system that helps " . $config['target_audience'] .
               " achieve " . $this->extract_benefit( $config ) .
               " without " . $this->extract_pain_point( $config );
    }

    /**
     * Generate body copy
     */
    private function generate_body_copy( $template, $config ) {
        $copy_structure = [
            'opening' => $this->generate_opening_copy( $template, $config ),
            'problem' => $this->generate_problem_copy( $config ),
            'solution' => $this->generate_solution_copy( $config ),
            'proof' => $this->generate_proof_copy( $config ),
            'offer' => $this->generate_offer_copy( $template, $config ),
            'close' => $this->generate_closing_copy( $template, $config )
        ];

        return $copy_structure;
    }

    /**
     * Create funnel flow
     */
    private function create_funnel_flow( $pages, $config ) {
        $flow = [
            'entry_points' => [],
            'primary_path' => [],
            'alternative_paths' => [],
            'exit_points' => [],
            'conversion_points' => []
        ];

        // Map page relationships
        foreach ( $pages as $index => $page ) {
            if ( $index === 0 ) {
                $flow['entry_points'][] = $page['id'];
            }

            if ( isset( $pages[$index + 1] ) ) {
                $flow['primary_path'][] = [
                    'from' => $page['id'],
                    'to' => $pages[$index + 1]['id'],
                    'condition' => 'default'
                ];
            }

            // Mark conversion points
            if ( in_array( $page['meta']['page_role'], ['conversion', 'transaction', 'purchase'] ) ) {
                $flow['conversion_points'][] = $page['id'];
            }

            // Mark exit points
            if ( in_array( $page['meta']['page_role'], ['fulfillment', 'onboarding', 'loyalty'] ) ) {
                $flow['exit_points'][] = $page['id'];
            }
        }

        return $flow;
    }

    /**
     * Add urgency elements to pages
     */
    private function add_urgency_elements( $pages, $urgency_level ) {
        $urgency_configs = [
            'low' => ['countdown' => false, 'scarcity' => true, 'social_proof' => true],
            'medium' => ['countdown' => true, 'scarcity' => true, 'social_proof' => true, 'flash_warning' => false],
            'high' => ['countdown' => true, 'scarcity' => true, 'social_proof' => true, 'flash_warning' => true, 'stock_counter' => true]
        ];

        $config = $urgency_configs[$urgency_level] ?? $urgency_configs['medium'];

        foreach ( $pages as &$page ) {
            if ( in_array( $page['meta']['funnel_stage'], ['decision', 'action'] ) ) {
                $page['urgency_elements'] = [];

                if ( $config['countdown'] ) {
                    $page['urgency_elements'][] = [
                        'type' => 'countdown_timer',
                        'config' => [
                            'duration' => '24:00:00',
                            'action' => 'hide_offer',
                            'message' => 'Offer expires in'
                        ]
                    ];
                }

                if ( $config['scarcity'] ) {
                    $page['urgency_elements'][] = [
                        'type' => 'limited_availability',
                        'config' => [
                            'total' => 100,
                            'remaining' => rand( 3, 15 ),
                            'message' => 'Only {remaining} spots left!'
                        ]
                    ];
                }

                if ( isset( $config['stock_counter'] ) && $config['stock_counter'] ) {
                    $page['urgency_elements'][] = [
                        'type' => 'stock_counter',
                        'config' => [
                            'animate' => true,
                            'update_frequency' => 30,
                            'low_stock_threshold' => 10
                        ]
                    ];
                }
            }
        }

        return $pages;
    }

    /**
     * Add exit intent popups
     */
    private function add_exit_intent_popups( $pages, $config ) {
        foreach ( $pages as &$page ) {
            if ( in_array( $page['meta']['funnel_stage'], ['interest', 'decision', 'action'] ) ) {
                $page['exit_intent'] = [
                    'enabled' => true,
                    'trigger' => 'mouse_leave',
                    'delay' => 0,
                    'frequency' => 'once_per_session',
                    'content' => $this->generate_exit_intent_content( $page, $config )
                ];
            }
        }

        return $pages;
    }

    /**
     * Generate exit intent content
     */
    private function generate_exit_intent_content( $page, $config ) {
        $templates = [
            'interest' => [
                'headline' => "Wait! Don't Miss Out",
                'subheadline' => "Get 10% off just for staying",
                'cta' => "Claim My Discount",
                'offer_type' => 'discount'
            ],
            'decision' => [
                'headline' => "Before You Go...",
                'subheadline' => "Get our exclusive bonus guide FREE",
                'cta' => "Yes, Send Me The Guide",
                'offer_type' => 'lead_magnet'
            ],
            'action' => [
                'headline' => "Special One-Time Offer",
                'subheadline' => "Save 25% if you complete your order now",
                'cta' => "Activate Discount",
                'offer_type' => 'urgency'
            ]
        ];

        $stage = $page['meta']['funnel_stage'];
        return $templates[$stage] ?? $templates['interest'];
    }

    /**
     * Generate upsell pages
     */
    private function generate_upsell_pages( $config ) {
        $upsells = [];

        // Primary upsell
        $upsells[] = [
            'id' => wp_generate_uuid4(),
            'type' => 'upsell',
            'title' => "Upgrade to " . $config['product_name'] . " Pro",
            'meta' => [
                'funnel_stage' => 'action',
                'page_role' => 'upsell',
                'conversion_goal' => 'increase_order_value',
                'offer_type' => 'upgrade',
                'discount' => '30%'
            ],
            'content' => $this->generate_upsell_content( 'upgrade', $config )
        ];

        // Additional upsell
        $upsells[] = [
            'id' => wp_generate_uuid4(),
            'type' => 'upsell',
            'title' => "Add Priority Support",
            'meta' => [
                'funnel_stage' => 'action',
                'page_role' => 'upsell',
                'conversion_goal' => 'add_services',
                'offer_type' => 'addon',
                'discount' => '50%'
            ],
            'content' => $this->generate_upsell_content( 'addon', $config )
        ];

        return $upsells;
    }

    /**
     * Generate downsell pages
     */
    private function generate_downsell_pages( $config ) {
        $downsells = [];

        $downsells[] = [
            'id' => wp_generate_uuid4(),
            'type' => 'downsell',
            'title' => "Get " . $config['product_name'] . " Lite Instead",
            'meta' => [
                'funnel_stage' => 'action',
                'page_role' => 'downsell',
                'conversion_goal' => 'recover_sale',
                'offer_type' => 'reduced',
                'discount' => '40%'
            ],
            'content' => $this->generate_downsell_content( $config )
        ];

        return $downsells;
    }

    /**
     * Setup funnel tracking
     */
    private function setup_funnel_tracking( $funnel_id ) {
        // Initialize conversion tracking
        $tracking_config = [
            'funnel_id' => $funnel_id,
            'track_visitors' => true,
            'track_conversions' => true,
            'track_revenue' => true,
            'track_behavior' => true,
            'cookie_duration' => 30, // days
            'attribution_window' => 7, // days
            'events' => [
                'page_view',
                'button_click',
                'form_submission',
                'video_play',
                'exit_intent_shown',
                'purchase_complete'
            ]
        ];

        $this->db_handler->setup_tracking( $tracking_config );
    }

    /**
     * Setup A/B testing
     */
    private function setup_ab_testing( $funnel_id, $pages ) {
        foreach ( $pages as $page ) {
            if ( in_array( $page['meta']['funnel_stage'], ['decision', 'action'] ) ) {
                // Create variant
                $variant = $page;
                $variant['id'] = wp_generate_uuid4();
                $variant['is_variant'] = true;
                $variant['parent_id'] = $page['id'];
                $variant['variant_name'] = 'B';

                // Modify variant content
                $variant['content']['headline'] = $this->generate_variant_headline( $page['content']['headline'] );
                $variant['content']['call_to_action']['text'] = $this->generate_variant_cta( $page['content']['call_to_action']['text'] );

                // Save variant
                $this->db_handler->save_ab_test_variant( $funnel_id, $variant );
            }
        }
    }

    /**
     * Helper functions for content generation
     */
    private function replace_content_placeholders( $text, $config ) {
        $replacements = [
            '{product}' => $config['product_name'],
            '{product_category}' => $this->extract_product_category( $config ),
            '{target_audience}' => $config['target_audience'],
            '{benefit}' => $this->extract_benefit( $config ),
            '{pain_point}' => $this->extract_pain_point( $config ),
            '{achievement}' => $this->extract_achievement( $config ),
            '{timeframe}' => $this->extract_timeframe( $config ),
            '{unique_value}' => $this->extract_unique_value( $config ),
            '{number}' => $this->generate_social_proof_number(),
            '{urgency}' => $this->generate_urgency_text()
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
    }

    private function extract_benefit( $config ) {
        // AI would analyze config to extract primary benefit
        return "achieving breakthrough results";
    }

    private function extract_pain_point( $config ) {
        // AI would analyze config to extract main pain point
        return "the usual struggles and frustration";
    }

    private function extract_achievement( $config ) {
        // AI would analyze config to extract desired achievement
        return "transform your business";
    }

    private function extract_timeframe( $config ) {
        return "just 30 days";
    }

    private function extract_unique_value( $config ) {
        return "delivers real results";
    }

    private function extract_product_category( $config ) {
        return "solution";
    }

    private function generate_social_proof_number() {
        return number_format( rand( 1000, 50000 ) );
    }

    private function generate_urgency_text() {
        $urgency = ['midnight tonight', 'this offer expires', 'spots fill up', 'price increases'];
        return $urgency[array_rand( $urgency )];
    }

    private function generate_variant_headline( $original ) {
        // Simple variation for A/B testing
        return "NEW: " . $original;
    }

    private function generate_variant_cta( $original ) {
        // Simple CTA variation
        $variations = [
            'Get Started' => 'Start Now',
            'Buy Now' => 'Get Instant Access',
            'Sign Up' => 'Join Now',
            'Learn More' => 'Discover How'
        ];

        return $variations[$original] ?? $original . " →";
    }

    // Additional helper methods for content generation
    private function generate_opening_copy( $template, $config ) {
        return "If you're a " . $config['target_audience'] . " looking to " .
               $this->extract_achievement( $config ) . ", you've come to the right place.";
    }

    private function generate_problem_copy( $config ) {
        return "We understand the challenges you face. " .
               "That's why we created " . $config['product_name'] . ".";
    }

    private function generate_solution_copy( $config ) {
        return $config['product_name'] . " is the comprehensive solution that helps you " .
               $this->extract_benefit( $config ) . " without " . $this->extract_pain_point( $config ) . ".";
    }

    private function generate_proof_copy( $config ) {
        return "Join thousands of satisfied customers who have already transformed their results.";
    }

    private function generate_offer_copy( $template, $config ) {
        return "For a limited time, get " . $config['product_name'] .
               " at our special introductory price.";
    }

    private function generate_closing_copy( $template, $config ) {
        return "Don't wait - this opportunity won't last forever. Take action today!";
    }

    private function generate_cta( $template, $config ) {
        $ctas = [
            'awareness' => 'Learn More',
            'interest' => 'Get Started',
            'decision' => 'See Pricing',
            'action' => 'Buy Now',
            'retention' => 'Access Dashboard'
        ];

        return [
            'text' => $ctas[$template['stage']] ?? 'Get Started',
            'action' => 'next_page',
            'style' => 'primary'
        ];
    }

    private function generate_testimonials( $config ) {
        return [
            [
                'text' => 'This completely transformed how we do business.',
                'author' => 'Sarah J.',
                'role' => 'CEO',
                'rating' => 5
            ]
        ];
    }

    private function generate_features( $config ) {
        return [
            'Easy to use interface',
            'Comprehensive training included',
            '24/7 support',
            'Money-back guarantee'
        ];
    }

    private function generate_benefits( $config ) {
        return [
            'Save time and money',
            'Increase productivity',
            'Get better results',
            'Scale your business'
        ];
    }

    private function generate_guarantee( $config ) {
        return [
            'type' => '30_day',
            'text' => '30-Day Money Back Guarantee - No Questions Asked'
        ];
    }

    private function generate_faq( $config ) {
        return [
            [
                'question' => 'How does it work?',
                'answer' => 'Our simple 3-step process gets you results fast.'
            ],
            [
                'question' => 'Is there a guarantee?',
                'answer' => 'Yes! We offer a 30-day money back guarantee.'
            ]
        ];
    }

    private function enhance_page_for_funnel( $page, $template, $config ) {
        // Add funnel-specific enhancements
        $page['funnel_elements'] = [
            'progress_bar' => $this->calculate_progress( $template ),
            'trust_badges' => $this->get_trust_badges( $config ),
            'social_proof' => $this->get_social_proof_elements( $config )
        ];

        return $page;
    }

    private function calculate_progress( $template ) {
        $progress_map = [
            'awareness' => 20,
            'interest' => 40,
            'decision' => 60,
            'action' => 80,
            'retention' => 100
        ];

        return $progress_map[$template['stage']] ?? 0;
    }

    private function get_trust_badges( $config ) {
        return [
            'ssl_secure',
            'money_back_guarantee',
            'trusted_by_thousands',
            'industry_certified'
        ];
    }

    private function get_social_proof_elements( $config ) {
        return [
            'visitor_counter' => rand( 50, 500 ),
            'recent_purchases' => $this->generate_recent_purchases(),
            'reviews_count' => rand( 100, 5000 ),
            'average_rating' => 4.8
        ];
    }

    private function generate_recent_purchases() {
        $names = ['John', 'Sarah', 'Mike', 'Emma', 'David', 'Lisa'];
        $locations = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'];
        $purchases = [];

        for ( $i = 0; $i < 5; $i++ ) {
            $purchases[] = [
                'name' => $names[array_rand( $names )],
                'location' => $locations[array_rand( $locations )],
                'time_ago' => rand( 1, 60 ) . ' minutes ago'
            ];
        }

        return $purchases;
    }

    private function generate_upsell_content( $type, $config ) {
        return [
            'headline' => "Wait! Upgrade Your Order",
            'subheadline' => "One-time offer available only now",
            'benefits' => [
                'Get premium features',
                'Priority support',
                'Advanced training'
            ],
            'original_price' => '$297',
            'offer_price' => '$97',
            'cta' => 'Yes, Upgrade My Order!'
        ];
    }

    private function generate_downsell_content( $config ) {
        return [
            'headline' => "How About A Smaller Package?",
            'subheadline' => "Get started with our lite version",
            'benefits' => [
                'Core features included',
                'Basic support',
                'Starter training'
            ],
            'price' => '$47',
            'cta' => 'Yes, I Want The Lite Version'
        ];
    }
}