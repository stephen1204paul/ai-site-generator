<?php
/**
 * Lead Magnet Generator
 *
 * Generates lead magnets and opt-in pages using AI
 *
 * @package WP_AI_Site_Generator
 * @subpackage Generators
 */

namespace WP_AI_Site_Generator\Generators;

use WP_AI_Site_Generator\Includes\AI_Provider_Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Lead_Magnet_Generator
 */
class Lead_Magnet_Generator extends AI_Provider_Base {

    /**
     * Lead magnet types
     */
    private $magnet_types = [];

    /**
     * Opt-in page templates
     */
    private $optin_templates = [];

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->init_magnet_types();
        $this->init_optin_templates();

        add_filter('wp_ai_generator_types', [$this, 'register_generator_type']);
        add_action('wp_ajax_generate_lead_magnet', [$this, 'handle_ajax_generation']);
    }

    /**
     * Initialize lead magnet types
     */
    private function init_magnet_types() {
        $this->magnet_types = [
            'ebook' => [
                'name' => 'eBook',
                'description' => 'Comprehensive guide on a specific topic',
                'pages' => '10-30',
                'format' => 'PDF',
                'sections' => ['introduction', 'chapters', 'conclusion', 'resources'],
            ],
            'checklist' => [
                'name' => 'Checklist',
                'description' => 'Step-by-step actionable checklist',
                'pages' => '1-3',
                'format' => 'PDF',
                'sections' => ['title', 'steps', 'tips', 'notes'],
            ],
            'template' => [
                'name' => 'Template/Swipe File',
                'description' => 'Ready-to-use templates and examples',
                'pages' => '5-10',
                'format' => 'PDF/DOCX',
                'sections' => ['instructions', 'templates', 'examples', 'customization'],
            ],
            'cheatsheet' => [
                'name' => 'Cheat Sheet',
                'description' => 'Quick reference guide with key information',
                'pages' => '1-2',
                'format' => 'PDF',
                'sections' => ['shortcuts', 'formulas', 'quick_tips', 'references'],
            ],
            'toolkit' => [
                'name' => 'Resource Toolkit',
                'description' => 'Curated list of tools and resources',
                'pages' => '3-5',
                'format' => 'PDF',
                'sections' => ['categories', 'tools', 'descriptions', 'links'],
            ],
            'workbook' => [
                'name' => 'Workbook',
                'description' => 'Interactive exercises and worksheets',
                'pages' => '10-20',
                'format' => 'PDF',
                'sections' => ['lessons', 'exercises', 'worksheets', 'answers'],
            ],
            'email_course' => [
                'name' => 'Email Course',
                'description' => 'Multi-day educational email series',
                'pages' => 'N/A',
                'format' => 'Email',
                'sections' => ['lessons', 'action_steps', 'resources'],
            ],
            'video_series' => [
                'name' => 'Video Series',
                'description' => 'Educational video content series',
                'pages' => 'N/A',
                'format' => 'Video',
                'sections' => ['scripts', 'slides', 'resources'],
            ],
            'quiz' => [
                'name' => 'Assessment/Quiz',
                'description' => 'Interactive assessment with results',
                'pages' => 'N/A',
                'format' => 'Interactive',
                'sections' => ['questions', 'results', 'recommendations'],
            ],
            'calculator' => [
                'name' => 'Calculator/Tool',
                'description' => 'Interactive calculation or planning tool',
                'pages' => 'N/A',
                'format' => 'Interactive',
                'sections' => ['inputs', 'calculations', 'results', 'insights'],
            ],
        ];
    }

    /**
     * Initialize opt-in page templates
     */
    private function init_optin_templates() {
        $this->optin_templates = [
            'simple' => [
                'name' => 'Simple Opt-In',
                'sections' => ['headline', 'subheadline', 'bullet_points', 'opt_in_form'],
                'style' => 'minimalist',
            ],
            'video' => [
                'name' => 'Video Opt-In',
                'sections' => ['headline', 'video', 'benefits', 'opt_in_form', 'testimonials'],
                'style' => 'engaging',
            ],
            'long_form' => [
                'name' => 'Long Form Sales Letter',
                'sections' => ['headline', 'problem', 'solution', 'benefits', 'testimonials', 'opt_in_form', 'faq'],
                'style' => 'comprehensive',
            ],
            'quiz_funnel' => [
                'name' => 'Quiz Funnel',
                'sections' => ['headline', 'quiz_intro', 'quiz_questions', 'results_teaser', 'opt_in_form'],
                'style' => 'interactive',
            ],
            'webinar' => [
                'name' => 'Webinar Registration',
                'sections' => ['headline', 'webinar_details', 'speaker_bio', 'topics', 'opt_in_form', 'countdown'],
                'style' => 'event',
            ],
        ];
    }

    /**
     * Generate lead magnet content
     */
    public function generate_lead_magnet($config) {
        $type = $config['type'] ?? 'ebook';
        $topic = $config['topic'] ?? '';
        $audience = $config['audience'] ?? '';
        $goal = $config['goal'] ?? 'generate_leads';

        if (!isset($this->magnet_types[$type])) {
            return ['success' => false, 'error' => 'Invalid lead magnet type'];
        }

        $magnet_config = $this->magnet_types[$type];

        // Generate content for each section
        $content = [];
        foreach ($magnet_config['sections'] as $section) {
            $content[$section] = $this->generate_section_content($type, $section, $topic, $audience);
        }

        // Generate metadata
        $metadata = $this->generate_magnet_metadata($type, $topic, $audience);

        // Create opt-in page
        $optin_page = $this->generate_optin_page($metadata, $config['optin_template'] ?? 'simple');

        // Package the lead magnet
        $package = $this->package_lead_magnet($type, $content, $metadata);

        return [
            'success' => true,
            'lead_magnet' => $package,
            'optin_page' => $optin_page,
            'metadata' => $metadata,
        ];
    }

    /**
     * Generate section content
     */
    private function generate_section_content($type, $section, $topic, $audience) {
        $prompts = $this->get_section_prompts($type, $section);

        $prompt = str_replace(
            ['{{topic}}', '{{audience}}'],
            [$topic, $audience],
            $prompts['prompt']
        );

        $content = $this->generate_ai_content($prompt, $prompts['params'] ?? []);

        return $this->format_section_content($content, $section);
    }

    /**
     * Get section prompts
     */
    private function get_section_prompts($type, $section) {
        $prompts = [
            'ebook' => [
                'introduction' => [
                    'prompt' => 'Write an engaging introduction for an ebook about {{topic}} for {{audience}}. Include the problem, promise, and preview of what they\'ll learn.',
                    'params' => ['max_tokens' => 500],
                ],
                'chapters' => [
                    'prompt' => 'Create a detailed outline with 5-7 chapters for an ebook about {{topic}} for {{audience}}. For each chapter, include title, key points, and takeaways.',
                    'params' => ['max_tokens' => 1500],
                ],
                'conclusion' => [
                    'prompt' => 'Write a compelling conclusion for an ebook about {{topic}} that summarizes key points and provides clear action steps for {{audience}}.',
                    'params' => ['max_tokens' => 400],
                ],
            ],
            'checklist' => [
                'steps' => [
                    'prompt' => 'Create a comprehensive checklist with 15-25 actionable items for {{topic}} targeted at {{audience}}. Group items by category.',
                    'params' => ['max_tokens' => 800],
                ],
                'tips' => [
                    'prompt' => 'Provide 5-7 pro tips for using this {{topic}} checklist effectively for {{audience}}.',
                    'params' => ['max_tokens' => 300],
                ],
            ],
            'template' => [
                'templates' => [
                    'prompt' => 'Create 3-5 ready-to-use templates for {{topic}} that {{audience}} can customize. Include fill-in-the-blank sections.',
                    'params' => ['max_tokens' => 1200],
                ],
                'examples' => [
                    'prompt' => 'Provide 3 real-world examples of how {{audience}} can use these templates for {{topic}}.',
                    'params' => ['max_tokens' => 600],
                ],
            ],
            'cheatsheet' => [
                'shortcuts' => [
                    'prompt' => 'List the top 20 shortcuts, hacks, or quick wins for {{topic}} that {{audience}} needs to know.',
                    'params' => ['max_tokens' => 500],
                ],
                'formulas' => [
                    'prompt' => 'Provide key formulas, frameworks, or rules of thumb for {{topic}} relevant to {{audience}}.',
                    'params' => ['max_tokens' => 400],
                ],
            ],
            'workbook' => [
                'exercises' => [
                    'prompt' => 'Create 5-7 interactive exercises for {{audience}} to practice and apply {{topic}} concepts.',
                    'params' => ['max_tokens' => 1000],
                ],
                'worksheets' => [
                    'prompt' => 'Design 3-5 fillable worksheets for {{topic}} that help {{audience}} track progress and insights.',
                    'params' => ['max_tokens' => 800],
                ],
            ],
            'email_course' => [
                'lessons' => [
                    'prompt' => 'Create a 5-day email course about {{topic}} for {{audience}}. Include subject lines, main content, and action steps for each day.',
                    'params' => ['max_tokens' => 2000],
                ],
            ],
        ];

        $type_prompts = $prompts[$type] ?? $prompts['ebook'];
        return $type_prompts[$section] ?? [
            'prompt' => 'Generate content for ' . $section . ' section about {{topic}} for {{audience}}',
            'params' => ['max_tokens' => 500],
        ];
    }

    /**
     * Format section content
     */
    private function format_section_content($content, $section) {
        // Apply section-specific formatting
        $formatted = [
            'raw' => $content,
            'html' => $this->convert_to_html($content, $section),
            'markdown' => $this->convert_to_markdown($content, $section),
        ];

        return $formatted;
    }

    /**
     * Generate magnet metadata
     */
    private function generate_magnet_metadata($type, $topic, $audience) {
        $prompt = "Generate metadata for a {$type} about {$topic} for {$audience}. Include: title, description, 5 key benefits, and 3 learning outcomes.";

        $metadata_raw = $this->generate_ai_content($prompt, ['max_tokens' => 400]);

        // Parse into structured format
        return [
            'title' => $this->extract_title($metadata_raw),
            'description' => $this->extract_description($metadata_raw),
            'benefits' => $this->extract_benefits($metadata_raw),
            'outcomes' => $this->extract_outcomes($metadata_raw),
            'type' => $type,
            'topic' => $topic,
            'audience' => $audience,
            'created' => current_time('mysql'),
        ];
    }

    /**
     * Generate opt-in page
     */
    public function generate_optin_page($metadata, $template_id = 'simple') {
        $template = $this->optin_templates[$template_id] ?? $this->optin_templates['simple'];

        $page_content = [];

        foreach ($template['sections'] as $section) {
            $page_content[$section] = $this->generate_optin_section($section, $metadata);
        }

        // Generate HTML
        $html = $this->build_optin_html($page_content, $template, $metadata);

        // Create WordPress page
        $page_id = wp_insert_post([
            'post_title' => $metadata['title'] . ' - Free Download',
            'post_content' => $html,
            'post_type' => 'page',
            'post_status' => 'draft',
            'meta_input' => [
                '_lead_magnet_type' => $metadata['type'],
                '_lead_magnet_metadata' => $metadata,
                '_optin_template' => $template_id,
            ],
        ]);

        return [
            'page_id' => $page_id,
            'html' => $html,
            'sections' => $page_content,
            'edit_url' => get_edit_post_link($page_id),
        ];
    }

    /**
     * Generate opt-in page section
     */
    private function generate_optin_section($section, $metadata) {
        $sections = [
            'headline' => $this->generate_headline($metadata),
            'subheadline' => $this->generate_subheadline($metadata),
            'bullet_points' => $this->generate_bullet_points($metadata),
            'opt_in_form' => $this->generate_optin_form($metadata),
            'testimonials' => $this->generate_testimonials($metadata),
            'video' => $this->generate_video_section($metadata),
            'faq' => $this->generate_faq($metadata),
            'countdown' => $this->generate_countdown(),
        ];

        return $sections[$section] ?? '';
    }

    /**
     * Generate headline
     */
    private function generate_headline($metadata) {
        $templates = [
            'How to {{outcome}} Without {{pain_point}}',
            'The Ultimate Guide to {{topic}} for {{audience}}',
            'Free: The {{adjective}} {{type}} That Shows You {{benefit}}',
            'Discover the {{number}} Secrets to {{outcome}}',
            '{{audience}}: Get Your Free {{topic}} {{type}}',
        ];

        $template = $templates[array_rand($templates)];

        return $this->fill_template($template, [
            'outcome' => $metadata['outcomes'][0] ?? 'Success',
            'pain_point' => 'The Usual Struggle',
            'topic' => $metadata['topic'],
            'audience' => $metadata['audience'],
            'type' => ucfirst($metadata['type']),
            'benefit' => $metadata['benefits'][0] ?? 'Amazing Results',
            'adjective' => 'Essential',
            'number' => rand(3, 10),
        ]);
    }

    /**
     * Generate subheadline
     */
    private function generate_subheadline($metadata) {
        return "Get instant access to {$metadata['description']} - absolutely free!";
    }

    /**
     * Generate bullet points
     */
    private function generate_bullet_points($metadata) {
        $html = '<ul class="benefits-list">';
        foreach ($metadata['benefits'] as $benefit) {
            $html .= '<li>' . esc_html($benefit) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Generate opt-in form
     */
    private function generate_optin_form($metadata) {
        $form_html = '
        <div class="optin-form-container">
            <form class="lead-magnet-optin-form funnel-form" data-magnet-type="' . esc_attr($metadata['type']) . '">
                <input type="hidden" name="lead_magnet" value="' . esc_attr($metadata['title']) . '">
                <div class="form-group">
                    <input type="text" name="first_name" placeholder="Your First Name" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Your Best Email" required>
                </div>
                <button type="submit" class="btn-primary">Get Instant Access</button>
                <p class="privacy-notice">We respect your privacy. Unsubscribe at any time.</p>
            </form>
        </div>';

        return $form_html;
    }

    /**
     * Generate testimonials
     */
    private function generate_testimonials($metadata) {
        // Generate AI testimonials (placeholder)
        $testimonials = [
            [
                'text' => "This {$metadata['type']} completely changed how I approach {$metadata['topic']}!",
                'author' => 'Sarah M.',
                'title' => 'Happy Customer',
            ],
            [
                'text' => "Clear, actionable, and exactly what I needed. Highly recommended!",
                'author' => 'John D.',
                'title' => 'Verified User',
            ],
        ];

        $html = '<div class="testimonials">';
        foreach ($testimonials as $testimonial) {
            $html .= '
            <div class="testimonial">
                <p>"' . esc_html($testimonial['text']) . '"</p>
                <cite>- ' . esc_html($testimonial['author']) . ', ' . esc_html($testimonial['title']) . '</cite>
            </div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Build opt-in page HTML
     */
    private function build_optin_html($content, $template, $metadata) {
        $html = '
        <div class="lead-magnet-optin-page" data-template="' . esc_attr($template['name']) . '">
            <style>
                .lead-magnet-optin-page {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 40px 20px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .optin-headline {
                    font-size: 36px;
                    font-weight: bold;
                    margin-bottom: 20px;
                    color: #333;
                }
                .optin-subheadline {
                    font-size: 20px;
                    color: #666;
                    margin-bottom: 30px;
                }
                .benefits-list {
                    list-style: none;
                    padding: 0;
                    margin: 30px 0;
                }
                .benefits-list li {
                    padding: 10px 0 10px 40px;
                    position: relative;
                }
                .benefits-list li:before {
                    content: "✓";
                    position: absolute;
                    left: 10px;
                    color: #4CAF50;
                    font-weight: bold;
                }
                .optin-form-container {
                    background: #f9f9f9;
                    padding: 30px;
                    border-radius: 10px;
                    margin: 30px 0;
                }
                .lead-magnet-optin-form input {
                    width: 100%;
                    padding: 12px;
                    margin: 10px 0;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    font-size: 16px;
                }
                .lead-magnet-optin-form button {
                    width: 100%;
                    padding: 15px;
                    background: #007cba;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    font-size: 18px;
                    font-weight: bold;
                    cursor: pointer;
                    transition: background 0.3s;
                }
                .lead-magnet-optin-form button:hover {
                    background: #005a87;
                }
                .privacy-notice {
                    text-align: center;
                    color: #999;
                    font-size: 12px;
                    margin-top: 10px;
                }
                .testimonials {
                    margin: 40px 0;
                }
                .testimonial {
                    background: #f0f0f0;
                    padding: 20px;
                    border-radius: 10px;
                    margin: 20px 0;
                }
                .testimonial p {
                    font-style: italic;
                    margin-bottom: 10px;
                }
                .testimonial cite {
                    display: block;
                    text-align: right;
                    color: #666;
                }
            </style>';

        // Add sections
        foreach ($template['sections'] as $section) {
            if (isset($content[$section])) {
                $section_class = 'optin-' . str_replace('_', '-', $section);

                if ($section === 'headline') {
                    $html .= '<h1 class="' . $section_class . '">' . $content[$section] . '</h1>';
                } elseif ($section === 'subheadline') {
                    $html .= '<p class="' . $section_class . '">' . $content[$section] . '</p>';
                } else {
                    $html .= '<div class="' . $section_class . '">' . $content[$section] . '</div>';
                }
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Package lead magnet for delivery
     */
    private function package_lead_magnet($type, $content, $metadata) {
        $package = [
            'type' => $type,
            'metadata' => $metadata,
            'content' => $content,
            'files' => [],
        ];

        // Generate appropriate format based on type
        switch ($this->magnet_types[$type]['format']) {
            case 'PDF':
                $package['files']['pdf'] = $this->generate_pdf($content, $metadata);
                break;
            case 'Email':
                $package['files']['emails'] = $this->format_email_series($content);
                break;
            case 'Interactive':
                $package['files']['html'] = $this->generate_interactive_content($content, $type);
                break;
        }

        // Store in database
        $package['id'] = $this->save_lead_magnet($package);

        return $package;
    }

    /**
     * Generate PDF version
     */
    private function generate_pdf($content, $metadata) {
        // This would use a PDF library like TCPDF or mPDF
        // For now, return HTML that can be converted to PDF
        $html = '<html><head><title>' . esc_html($metadata['title']) . '</title></head><body>';

        foreach ($content as $section => $section_content) {
            $html .= '<h2>' . ucfirst(str_replace('_', ' ', $section)) . '</h2>';
            $html .= $section_content['html'] ?? $section_content;
        }

        $html .= '</body></html>';

        // Save to uploads directory
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . sanitize_file_name($metadata['title']) . '.html';
        file_put_contents($file_path, $html);

        return $upload_dir['url'] . '/' . basename($file_path);
    }

    /**
     * Format email series
     */
    private function format_email_series($content) {
        $emails = [];

        if (isset($content['lessons'])) {
            $lessons = $this->parse_lessons($content['lessons']);

            foreach ($lessons as $index => $lesson) {
                $emails[] = [
                    'day' => $index + 1,
                    'subject' => $lesson['subject'] ?? "Day " . ($index + 1) . " Lesson",
                    'content' => $lesson['content'] ?? '',
                    'action_step' => $lesson['action'] ?? '',
                ];
            }
        }

        return $emails;
    }

    /**
     * Generate interactive content
     */
    private function generate_interactive_content($content, $type) {
        // Generate HTML/JS for interactive content
        return [
            'html' => $this->build_interactive_html($content, $type),
            'scripts' => $this->get_interactive_scripts($type),
        ];
    }

    /**
     * Save lead magnet to database
     */
    private function save_lead_magnet($package) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_lead_magnets';

        $wpdb->insert($table_name, [
            'title' => $package['metadata']['title'],
            'type' => $package['type'],
            'metadata' => wp_json_encode($package['metadata']),
            'content' => wp_json_encode($package['content']),
            'files' => wp_json_encode($package['files']),
            'created_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Handle AJAX generation request
     */
    public function handle_ajax_generation() {
        check_ajax_referer('wp_ai_lead_magnet', 'nonce');

        $config = [
            'type' => sanitize_text_field($_POST['type'] ?? 'ebook'),
            'topic' => sanitize_text_field($_POST['topic'] ?? ''),
            'audience' => sanitize_text_field($_POST['audience'] ?? ''),
            'goal' => sanitize_text_field($_POST['goal'] ?? ''),
            'optin_template' => sanitize_text_field($_POST['optin_template'] ?? 'simple'),
        ];

        $result = $this->generate_lead_magnet($config);

        wp_send_json($result);
    }

    /**
     * Helper methods
     */
    private function extract_title($content) {
        // Extract title from AI-generated content
        if (preg_match('/title:\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Ultimate Guide';
    }

    private function extract_description($content) {
        if (preg_match('/description:\s*(.+)/i', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Comprehensive resource';
    }

    private function extract_benefits($content) {
        $benefits = [];
        if (preg_match_all('/benefit\s*\d*:\s*(.+)/i', $content, $matches)) {
            $benefits = array_map('trim', $matches[1]);
        }
        return $benefits ?: ['Learn key concepts', 'Practical examples', 'Actionable steps'];
    }

    private function extract_outcomes($content) {
        $outcomes = [];
        if (preg_match_all('/outcome\s*\d*:\s*(.+)/i', $content, $matches)) {
            $outcomes = array_map('trim', $matches[1]);
        }
        return $outcomes ?: ['Master the basics', 'Apply to real situations', 'See results'];
    }

    private function convert_to_html($content, $section) {
        // Convert to HTML format
        return nl2br(esc_html($content));
    }

    private function convert_to_markdown($content, $section) {
        // Convert to Markdown format
        return $content;
    }

    private function fill_template($template, $vars) {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }

    private function parse_lessons($content) {
        // Parse lesson content into structured format
        return [];
    }

    private function build_interactive_html($content, $type) {
        return '<div class="interactive-' . $type . '">Interactive content placeholder</div>';
    }

    private function get_interactive_scripts($type) {
        return [];
    }

    private function generate_video_section($metadata) {
        return '<div class="video-placeholder">Video content will be here</div>';
    }

    private function generate_faq($metadata) {
        return '<div class="faq">Frequently asked questions</div>';
    }

    private function generate_countdown() {
        return '<div class="countdown" data-expires="24hours">Limited time offer!</div>';
    }

    /**
     * Register generator type
     */
    public function register_generator_type($types) {
        $types['lead_magnet'] = [
            'name' => 'Lead Magnet',
            'class' => self::class,
        ];
        return $types;
    }
}