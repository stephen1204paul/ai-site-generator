<?php
/**
 * Prompt Templates for AI Generation
 *
 * Provides production-ready prompt templates for various content generation tasks.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes/prompts
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes\Prompts;

/**
 * Prompt Templates Class
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes/prompts
 */
class Prompt_Templates {

	/**
	 * System prompts for different contexts
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $system_prompts    System prompt templates.
	 */
	private $system_prompts = [
		'default' => 'You are an expert WordPress website developer and content strategist with 10+ years of experience. You specialize in creating SEO-optimized, accessible, and user-friendly websites. Your responses should be structured, professional, and tailored to the specific industry and audience. Always follow WordPress coding standards and best practices.',

		'technical' => 'You are a senior WordPress developer specializing in modern web development. You have deep expertise in Gutenberg blocks, FSE themes, performance optimization, and accessibility standards. Generate clean, semantic, and performant code following WordPress coding standards.',

		'content' => 'You are a professional content writer and SEO specialist. You create engaging, informative, and optimized content that resonates with target audiences while meeting search engine requirements. Focus on clarity, value, and user intent.',

		'design' => 'You are a UX/UI designer with expertise in WordPress theme development. You understand modern design principles, accessibility requirements, and responsive design. Create visually appealing and user-friendly layouts.',
	];

	/**
	 * Template library for different content types
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $templates    Content templates.
	 */
	private $templates = [];

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->init_templates();
	}

	/**
	 * Initialize all template categories
	 *
	 * @since    1.0.0
	 */
	private function init_templates() {
		$this->templates = [
			'pages' => $this->get_page_templates(),
			'sections' => $this->get_section_templates(),
			'blocks' => $this->get_block_templates(),
			'seo' => $this->get_seo_templates(),
			'content' => $this->get_content_templates(),
		];
	}

	/**
	 * Get page generation templates
	 *
	 * @since    1.0.0
	 * @return   array    Page templates.
	 */
	private function get_page_templates() {
		return [
			'homepage' => [
				'prompt' => 'Create a complete homepage for a {industry} business named {business_name}.

Business Context:
- Target Audience: {audience}
- Unique Value Proposition: {uvp}
- Key Services/Products: {services}
- Brand Tone: {tone}
- Brand Colors: {colors}
- Location: {location}

Structure Required:
1. Hero Section:
   - Compelling headline (6-10 words)
   - Supporting subheadline (15-20 words)
   - Primary call-to-action button
   - Hero image placeholder

2. Features/Benefits Section:
   - 3-4 key value propositions
   - Icons or illustrations for each
   - Brief descriptions (2-3 sentences each)

3. About Preview:
   - Brief company introduction
   - Key differentiators
   - Link to full about page

4. Services/Products Showcase:
   - Grid or carousel layout
   - 4-6 main offerings
   - Brief descriptions and CTAs

5. Social Proof:
   - 2-3 testimonials with names and titles
   - Trust badges or certifications
   - Client logos if applicable

6. Call-to-Action Section:
   - Strong value proposition
   - Contact form or booking widget
   - Multiple contact methods

Requirements:
- Generate WordPress block markup
- Mobile-responsive design considerations
- SEO-optimized content with keyword: {primary_keyword}
- WCAG 2.1 AA accessibility compliance
- Include schema markup suggestions
- Proper heading hierarchy (single H1, multiple H2s)
- Internal linking opportunities
- Loading performance considerations

Output Format:
Return as WordPress block markup with placeholders for images. Include inline comments for customization points.',
				'variables' => ['industry', 'business_name', 'audience', 'uvp', 'services', 'tone', 'colors', 'location', 'primary_keyword'],
				'model_preference' => ['claude-3-5-sonnet', 'gpt-4'],
				'temperature' => 0.7,
			],

			'about' => [
				'prompt' => 'Generate a comprehensive About page for {business_name} in the {industry} industry.

Context:
- Years in Business: {years_in_business}
- Team Size: {team_size}
- Core Values: {values}
- Mission Statement: {mission}
- Target Audience: {audience}
- Brand Tone: {tone}

Structure Required:
1. Page Title and Introduction:
   - Engaging opening that connects with visitors
   - Clear statement of who you are and what you do

2. Company Story:
   - Origin story (why the company was founded)
   - Evolution and growth
   - Key milestones and achievements

3. Mission & Vision:
   - Clear mission statement
   - Vision for the future
   - How you serve your customers

4. Core Values:
   - 3-5 core values with explanations
   - How values guide operations

5. Team Section:
   - Introduction to leadership
   - Team expertise highlights
   - Company culture insights

6. Why Choose Us:
   - Key differentiators
   - Unique approach or methodology
   - Client success metrics

7. Certifications & Affiliations:
   - Professional certifications
   - Industry affiliations
   - Awards and recognition

Requirements:
- 700-1000 words of engaging content
- Include statistics and specific achievements
- Professional yet approachable tone
- SEO optimization for "{business_name} about us"
- WordPress block markup format
- Include testimonial placeholders
- Clear calls-to-action

Output Format:
WordPress blocks with proper hierarchy and formatting.',
				'variables' => ['business_name', 'industry', 'years_in_business', 'team_size', 'values', 'mission', 'audience', 'tone'],
				'model_preference' => ['claude-3-5-sonnet', 'gpt-4'],
				'temperature' => 0.6,
			],

			'services' => [
				'prompt' => 'Create a Services page showcasing {service_count} services for {business_name}.

Business Context:
- Industry: {industry}
- Service Categories: {service_categories}
- Target Market: {target_market}
- Pricing Model: {pricing_model}
- Competitive Advantages: {advantages}

Page Structure:
1. Page Header:
   - Compelling headline about solutions/services
   - Brief value proposition
   - Overview of service approach

2. Services Grid/List:
   For each service include:
   - Service name
   - Icon/image placeholder
   - 3-4 sentence description
   - Key benefits (3-4 bullet points)
   - Starting price or "Get Quote" CTA
   - Learn more link

3. Service Process:
   - How we work section
   - Step-by-step process (3-5 steps)
   - What clients can expect

4. Why Our Services:
   - Unique selling propositions
   - Quality guarantees
   - Professional credentials

5. Call-to-Action:
   - Consultation offer
   - Contact form or scheduler
   - Multiple contact options

Requirements:
- SEO optimization for each service
- Clear benefit-focused copy
- Include FAQ section with 5-7 questions
- WordPress block markup
- Schema markup for services
- Internal linking structure
- Trust indicators

Output Format:
WordPress blocks with service cards/sections.',
				'variables' => ['service_count', 'business_name', 'industry', 'service_categories', 'target_market', 'pricing_model', 'advantages'],
				'model_preference' => ['gpt-4', 'claude-3-5-sonnet'],
				'temperature' => 0.6,
			],

			'contact' => [
				'prompt' => 'Generate a Contact page for {business_name} located in {location}.

Business Information:
- Phone: {phone}
- Email: {email}
- Address: {address}
- Business Hours: {hours}
- Response Time: {response_time}
- Preferred Contact Methods: {contact_methods}

Page Requirements:
1. Contact Header:
   - Welcoming headline
   - Brief message encouraging contact
   - Response time commitment

2. Contact Form:
   - Name field (required)
   - Email field (required)
   - Phone field (optional)
   - Subject/Service dropdown
   - Message textarea
   - Privacy policy checkbox
   - Submit button with clear CTA

3. Contact Information:
   - Multiple contact methods
   - Business hours clearly displayed
   - Physical address with map placeholder
   - Social media links

4. FAQ Preview:
   - 3-4 common questions
   - Link to full FAQ if needed

5. Additional Sections:
   - Emergency contact if applicable
   - Department-specific contacts
   - International contact options

Requirements:
- Accessibility compliant form
- GDPR/privacy considerations
- Anti-spam measures mentioned
- Mobile-friendly layout
- Schema markup for local business
- Clear form validation messages

Output Format:
WordPress blocks including Contact Form 7 or WPForms shortcode placeholder.',
				'variables' => ['business_name', 'location', 'phone', 'email', 'address', 'hours', 'response_time', 'contact_methods'],
				'model_preference' => ['gpt-3.5-turbo', 'claude-3-haiku'],
				'temperature' => 0.3,
			],

			'landing' => [
				'prompt' => 'Create a high-converting landing page for {campaign_name} promoting {product_service}.

Campaign Details:
- Offer: {offer}
- Target Audience: {audience}
- Pain Points: {pain_points}
- Solution Benefits: {benefits}
- Urgency Factor: {urgency}
- Social Proof: {social_proof}

Landing Page Structure:
1. Above the Fold:
   - Attention-grabbing headline
   - Value proposition subheadline
   - Hero image/video placeholder
   - Primary CTA button
   - Trust indicators

2. Problem/Solution:
   - Identify pain points
   - Present solution
   - Key benefits (bullet points)

3. Features & Benefits:
   - 4-6 key features
   - Benefit-focused descriptions
   - Visual elements

4. Social Proof:
   - Testimonials (2-3)
   - Case study highlights
   - Success metrics
   - Trust badges

5. Offer Details:
   - What\'s included
   - Value breakdown
   - Pricing or special offer
   - Guarantee/risk reversal

6. FAQ Section:
   - 5-7 common objections addressed
   - Clear, concise answers

7. Final CTA:
   - Urgency/scarcity element
   - Clear next steps
   - Multiple CTA buttons

Requirements:
- Conversion-optimized copy
- A/B test suggestions
- Mobile-first design
- Fast loading considerations
- Exit-intent popup suggestion
- Thank you page outline

Output Format:
WordPress blocks with conversion elements.',
				'variables' => ['campaign_name', 'product_service', 'offer', 'audience', 'pain_points', 'benefits', 'urgency', 'social_proof'],
				'model_preference' => ['claude-3-5-sonnet', 'gpt-4'],
				'temperature' => 0.8,
			],
		];
	}

	/**
	 * Get section templates
	 *
	 * @since    1.0.0
	 * @return   array    Section templates.
	 */
	private function get_section_templates() {
		return [
			'hero' => [
				'prompt' => 'Create a hero section for a {page_type} page in the {industry} industry.

Requirements:
- Compelling headline (6-10 words) incorporating "{keyword}"
- Supporting subheadline (15-25 words) that expands on the value proposition
- Primary CTA button text and secondary CTA if appropriate
- Background image description or video concept
- Trust indicators or social proof elements
- Mobile-responsive considerations

Brand Context:
- Tone: {tone}
- Target Audience: {audience}
- Unique Value: {value_proposition}

Output as WordPress block markup with Cover or Group block.',
				'variables' => ['page_type', 'industry', 'keyword', 'tone', 'audience', 'value_proposition'],
				'temperature' => 0.7,
			],

			'features' => [
				'prompt' => 'Generate a features section showcasing {feature_count} key features for {product_service}.

For each feature provide:
- Feature name (2-4 words)
- Icon suggestion (FontAwesome or similar)
- Description (2-3 sentences focusing on benefits)
- Optional link text

Layout: {layout_preference} (grid/list/cards/alternating)

Output as WordPress columns block with proper responsive breakpoints.',
				'variables' => ['feature_count', 'product_service', 'layout_preference'],
				'temperature' => 0.6,
			],

			'testimonials' => [
				'prompt' => 'Create a testimonials section with {testimonial_count} testimonials for {business_name}.

For each testimonial include:
- Customer quote (50-100 words)
- Customer name
- Title/Position
- Company name
- Rating (if applicable)
- Industry-relevant pain point addressed
- Specific result or benefit mentioned

Style: {style} (cards/carousel/quotes/grid)
Include section heading and introduction.',
				'variables' => ['testimonial_count', 'business_name', 'style'],
				'temperature' => 0.8,
			],

			'cta' => [
				'prompt' => 'Generate a call-to-action section for {purpose}.

Include:
- Action-oriented heading
- Supporting text (2-3 sentences)
- Primary button text
- Secondary button text (if needed)
- Urgency or value element
- Visual suggestion (background color/pattern/image)

Context: {context}
Desired Action: {action}

Output as WordPress group block with buttons.',
				'variables' => ['purpose', 'context', 'action'],
				'temperature' => 0.7,
			],

			'faq' => [
				'prompt' => 'Create an FAQ section with {question_count} questions about {topic}.

Industry: {industry}
Target Audience: {audience}

For each Q&A:
- Question addressing common concern or objection
- Comprehensive answer (50-150 words)
- Include specific details, statistics, or examples
- Natural language, conversational tone

Include:
- Section heading
- Optional introduction paragraph
- Schema markup suggestion

Output as WordPress blocks (details or accordion pattern).',
				'variables' => ['question_count', 'topic', 'industry', 'audience'],
				'temperature' => 0.5,
			],

			'pricing' => [
				'prompt' => 'Design a pricing section with {plan_count} pricing plans for {product_service}.

For each plan include:
- Plan name
- Price (or "Custom Quote")
- Billing frequency
- Feature list (5-8 items)
- Highlighted features
- CTA button text
- Best for: (target user)

Include:
- Section heading
- Introduction text
- Most popular plan indicator
- Money-back guarantee if applicable

Style: {style} (cards/table/toggle monthly-annual)

Output as WordPress columns with pricing cards.',
				'variables' => ['plan_count', 'product_service', 'style'],
				'temperature' => 0.4,
			],
		];
	}

	/**
	 * Get block templates
	 *
	 * @since    1.0.0
	 * @return   array    Block templates.
	 */
	private function get_block_templates() {
		return [
			'content_block' => [
				'prompt' => 'Create a content block about {topic} for {audience}.

Requirements:
- Heading (H2 or H3)
- 2-3 paragraphs of informative content
- Include relevant keywords: {keywords}
- Optional: bullet points or numbered list
- Optional: blockquote or highlight box
- Internal link opportunities

Tone: {tone}
Word count: {word_count}

Output as WordPress group block with proper formatting.',
				'variables' => ['topic', 'audience', 'keywords', 'tone', 'word_count'],
				'temperature' => 0.6,
			],

			'team_member' => [
				'prompt' => 'Generate a team member block for {name} - {position}.

Include:
- Professional bio (75-100 words)
- Key qualifications or expertise areas
- Notable achievement or fun fact
- Contact method or social links
- Image placeholder

Company: {company}
Department: {department}

Output as WordPress media-text or columns block.',
				'variables' => ['name', 'position', 'company', 'department'],
				'temperature' => 0.5,
			],

			'statistic' => [
				'prompt' => 'Create a statistics/numbers block showcasing {stat_count} key metrics.

Business: {business_type}
Purpose: {purpose}

For each statistic:
- Large number/percentage
- Label (2-4 words)
- Supporting text (optional)
- Icon suggestion

Include visual formatting suggestions.

Output as WordPress columns block.',
				'variables' => ['stat_count', 'business_type', 'purpose'],
				'temperature' => 0.4,
			],

			'process' => [
				'prompt' => 'Design a process/steps block showing {step_count} steps for {process_name}.

For each step:
- Step number/label
- Step title (2-4 words)
- Description (2-3 sentences)
- Icon or illustration suggestion
- Connection to next step

Style: {style} (timeline/cards/numbered list/flowchart)

Output as WordPress group/columns block.',
				'variables' => ['step_count', 'process_name', 'style'],
				'temperature' => 0.5,
			],
		];
	}

	/**
	 * Get SEO templates
	 *
	 * @since    1.0.0
	 * @return   array    SEO templates.
	 */
	private function get_seo_templates() {
		return [
			'meta_description' => [
				'prompt' => 'Write an SEO meta description for a {page_type} page about {topic}.

Requirements:
- 150-160 characters
- Include primary keyword: {primary_keyword}
- Include call-to-action
- Unique value proposition
- Natural, engaging language

Context: {context}',
				'variables' => ['page_type', 'topic', 'primary_keyword', 'context'],
				'temperature' => 0.3,
			],

			'title_tag' => [
				'prompt' => 'Create an SEO title tag for {page_type} about {topic}.

Requirements:
- 50-60 characters
- Include primary keyword: {primary_keyword}
- Include brand name: {brand_name}
- Front-load important keywords
- Compelling and clickable

Page focus: {focus}',
				'variables' => ['page_type', 'topic', 'primary_keyword', 'brand_name', 'focus'],
				'temperature' => 0.3,
			],

			'schema_markup' => [
				'prompt' => 'Generate JSON-LD schema markup for a {schema_type} page.

Details:
{details}

Include all relevant properties for {schema_type} schema.
Ensure valid JSON-LD format.',
				'variables' => ['schema_type', 'details'],
				'temperature' => 0.1,
			],

			'og_tags' => [
				'prompt' => 'Create Open Graph tags for {page_type}.

Page Title: {title}
Description: {description}
URL: {url}
Image: {image_description}

Generate og:title, og:description, og:image, og:type, and Twitter card tags.',
				'variables' => ['page_type', 'title', 'description', 'url', 'image_description'],
				'temperature' => 0.2,
			],
		];
	}

	/**
	 * Get content templates
	 *
	 * @since    1.0.0
	 * @return   array    Content templates.
	 */
	private function get_content_templates() {
		return [
			'blog_post' => [
				'prompt' => 'Write a {word_count}-word blog post about {topic} for {audience}.

Title: Create an engaging, SEO-friendly title

Structure:
1. Introduction (hook, problem statement, what reader will learn)
2. Main sections with H2 headings
3. Supporting points with H3 subheadings
4. Examples, statistics, or case studies
5. Conclusion with key takeaways
6. Call-to-action

Requirements:
- Primary keyword: {primary_keyword}
- Related keywords: {related_keywords}
- Include 2-3 internal link opportunities
- Add 1-2 external link suggestions
- Natural keyword density (2-3%)
- Scannable formatting (bullets, short paragraphs)
- Include meta description

Tone: {tone}
Purpose: {purpose}

Output as WordPress blocks with proper hierarchy.',
				'variables' => ['word_count', 'topic', 'audience', 'primary_keyword', 'related_keywords', 'tone', 'purpose'],
				'temperature' => 0.7,
			],

			'product_description' => [
				'prompt' => 'Create a product description for {product_name}.

Product Details:
- Category: {category}
- Key Features: {features}
- Benefits: {benefits}
- Target Customer: {target_customer}
- Price Point: {price_point}

Include:
1. Compelling headline
2. Brief overview (2-3 sentences)
3. Key features (bullet points)
4. Benefits section
5. Use cases or applications
6. Technical specifications (if applicable)
7. What\'s included
8. Guarantee/warranty info

Tone: {tone}
Focus on: benefits over features

Output as structured WordPress content.',
				'variables' => ['product_name', 'category', 'features', 'benefits', 'target_customer', 'price_point', 'tone'],
				'temperature' => 0.6,
			],

			'case_study' => [
				'prompt' => 'Write a case study about {client_name} for {business_name}.

Project: {project_type}
Industry: {industry}
Results: {key_results}

Structure:
1. Client Overview
2. Challenge/Problem
3. Solution/Approach
4. Implementation Process
5. Results & Metrics
6. Client Testimonial
7. Key Takeaways

Word count: {word_count}

Include specific metrics and data points.

Output as WordPress blocks.',
				'variables' => ['client_name', 'business_name', 'project_type', 'industry', 'key_results', 'word_count'],
				'temperature' => 0.5,
			],
		];
	}

	/**
	 * Get a specific template
	 *
	 * @since    1.0.0
	 * @param    string    $category    Template category.
	 * @param    string    $name        Template name.
	 * @return   array|null             Template data or null if not found.
	 */
	public function get_template( $category, $name ) {
		return $this->templates[ $category ][ $name ] ?? null;
	}

	/**
	 * Get all templates in a category
	 *
	 * @since    1.0.0
	 * @param    string    $category    Template category.
	 * @return   array                  Templates in category.
	 */
	public function get_category_templates( $category ) {
		return $this->templates[ $category ] ?? [];
	}

	/**
	 * Get system prompt
	 *
	 * @since    1.0.0
	 * @param    string    $type    System prompt type.
	 * @return   string            System prompt.
	 */
	public function get_system_prompt( $type = 'default' ) {
		return $this->system_prompts[ $type ] ?? $this->system_prompts['default'];
	}

	/**
	 * Build a complete prompt from template
	 *
	 * @since    1.0.0
	 * @param    string    $category     Template category.
	 * @param    string    $name         Template name.
	 * @param    array     $variables    Variables to replace.
	 * @param    array     $context      Additional context.
	 * @return   array                   Complete prompt configuration.
	 */
	public function build_prompt( $category, $name, $variables = [], $context = [] ) {
		$template = $this->get_template( $category, $name );

		if ( ! $template ) {
			return null;
		}

		// Replace variables in prompt
		$prompt = $template['prompt'];
		foreach ( $variables as $key => $value ) {
			$prompt = str_replace( '{' . $key . '}', $value, $prompt );
		}

		// Add context if provided
		if ( ! empty( $context ) ) {
			$prompt = $this->add_context_to_prompt( $prompt, $context );
		}

		return [
			'system' => $this->get_system_prompt( $context['system_type'] ?? 'default' ),
			'prompt' => $prompt,
			'temperature' => $template['temperature'] ?? 0.7,
			'model_preference' => $template['model_preference'] ?? null,
			'max_tokens' => $template['max_tokens'] ?? 2000,
		];
	}

	/**
	 * Add context to prompt
	 *
	 * @since    1.0.0
	 * @param    string    $prompt     Base prompt.
	 * @param    array     $context    Context to add.
	 * @return   string               Prompt with context.
	 */
	private function add_context_to_prompt( $prompt, $context ) {
		$context_parts = [];

		if ( isset( $context['examples'] ) ) {
			$context_parts[] = "Examples:\n" . implode( "\n", $context['examples'] );
		}

		if ( isset( $context['constraints'] ) ) {
			$context_parts[] = "Constraints:\n" . implode( "\n", $context['constraints'] );
		}

		if ( isset( $context['style_guide'] ) ) {
			$context_parts[] = "Style Guide:\n" . $context['style_guide'];
		}

		if ( isset( $context['brand_voice'] ) ) {
			$context_parts[] = "Brand Voice:\n" . $context['brand_voice'];
		}

		if ( ! empty( $context_parts ) ) {
			$prompt .= "\n\nAdditional Context:\n" . implode( "\n\n", $context_parts );
		}

		return $prompt;
	}

	/**
	 * Get chain-of-thought prompt
	 *
	 * @since    1.0.0
	 * @param    string    $task         Task description.
	 * @param    array     $steps        Steps to follow.
	 * @return   string                  Chain-of-thought prompt.
	 */
	public function get_chain_of_thought_prompt( $task, $steps ) {
		$prompt = "Please complete the following task using a step-by-step approach:\n\n";
		$prompt .= "Task: {$task}\n\n";
		$prompt .= "Follow these steps:\n";

		foreach ( $steps as $index => $step ) {
			$prompt .= ( $index + 1 ) . ". {$step}\n";
		}

		$prompt .= "\nProvide your reasoning for each step and then the final output.";

		return $prompt;
	}

	/**
	 * Get few-shot prompt
	 *
	 * @since    1.0.0
	 * @param    string    $task         Task description.
	 * @param    array     $examples     Example inputs and outputs.
	 * @param    string    $input        New input to process.
	 * @return   string                  Few-shot prompt.
	 */
	public function get_few_shot_prompt( $task, $examples, $input ) {
		$prompt = "Task: {$task}\n\n";
		$prompt .= "Here are some examples:\n\n";

		foreach ( $examples as $index => $example ) {
			$prompt .= "Example " . ( $index + 1 ) . ":\n";
			$prompt .= "Input: " . $example['input'] . "\n";
			$prompt .= "Output: " . $example['output'] . "\n\n";
		}

		$prompt .= "Now, complete the following:\n";
		$prompt .= "Input: {$input}\n";
		$prompt .= "Output:";

		return $prompt;
	}

	/**
	 * Optimize prompt for token usage
	 *
	 * @since    1.0.0
	 * @param    string    $prompt    Original prompt.
	 * @return   string              Optimized prompt.
	 */
	public function optimize_prompt( $prompt ) {
		// Remove extra whitespace
		$prompt = preg_replace( '/\s+/', ' ', $prompt );

		// Remove unnecessary articles in lists
		$prompt = preg_replace( '/\b(the|a|an)\b\s+(?=[A-Z])/i', '', $prompt );

		// Compress common phrases
		$replacements = [
			'Please ensure that' => 'Ensure',
			'Make sure to' => 'Must',
			'It is important to' => 'Important:',
			'You should' => 'Should',
			'In order to' => 'To',
		];

		foreach ( $replacements as $long => $short ) {
			$prompt = str_replace( $long, $short, $prompt );
		}

		return trim( $prompt );
	}

	/**
	 * Get adaptive prompt based on model
	 *
	 * @since    1.0.0
	 * @param    string    $base_prompt    Base prompt.
	 * @param    string    $model          Model identifier.
	 * @return   string                   Adapted prompt.
	 */
	public function adapt_prompt_for_model( $base_prompt, $model ) {
		$adaptations = [
			'gpt-4' => [
				'prefix' => '',
				'suffix' => "\nProvide a detailed, well-structured response.",
			],
			'gpt-3.5-turbo' => [
				'prefix' => 'Be concise but complete. ',
				'suffix' => "\nFormat the response clearly.",
			],
			'claude-3-5-sonnet' => [
				'prefix' => '',
				'suffix' => "\nUse clear formatting with appropriate HTML/Markdown.",
			],
			'claude-3-haiku' => [
				'prefix' => 'Provide a focused response. ',
				'suffix' => '',
			],
		];

		$adaptation = $adaptations[ $model ] ?? ['prefix' => '', 'suffix' => ''];

		return $adaptation['prefix'] . $base_prompt . $adaptation['suffix'];
	}

	/**
	 * Validate template variables
	 *
	 * @since    1.0.0
	 * @param    string    $category     Template category.
	 * @param    string    $name         Template name.
	 * @param    array     $variables    Provided variables.
	 * @return   array                   Validation result.
	 */
	public function validate_template_variables( $category, $name, $variables ) {
		$template = $this->get_template( $category, $name );

		if ( ! $template ) {
			return [
				'valid' => false,
				'errors' => ['Template not found'],
			];
		}

		$required = $template['variables'] ?? [];
		$missing = array_diff( $required, array_keys( $variables ) );

		if ( ! empty( $missing ) ) {
			return [
				'valid' => false,
				'errors' => ['Missing required variables: ' . implode( ', ', $missing )],
			];
		}

		return [
			'valid' => true,
			'errors' => [],
		];
	}

	/**
	 * Get all available templates
	 *
	 * @since    1.0.0
	 * @return   array    All templates organized by category.
	 */
	public function get_all_templates() {
		return $this->templates;
	}

	/**
	 * Get template metadata
	 *
	 * @since    1.0.0
	 * @param    string    $category    Template category.
	 * @param    string    $name        Template name.
	 * @return   array                  Template metadata.
	 */
	public function get_template_metadata( $category, $name ) {
		$template = $this->get_template( $category, $name );

		if ( ! $template ) {
			return null;
		}

		return [
			'variables' => $template['variables'] ?? [],
			'temperature' => $template['temperature'] ?? 0.7,
			'model_preference' => $template['model_preference'] ?? null,
			'max_tokens' => $template['max_tokens'] ?? 2000,
			'description' => $this->extract_description( $template['prompt'] ),
		];
	}

	/**
	 * Extract description from prompt
	 *
	 * @since    1.0.0
	 * @param    string    $prompt    Prompt text.
	 * @return   string              Extracted description.
	 */
	private function extract_description( $prompt ) {
		// Get first line or first 100 characters
		$lines = explode( "\n", $prompt );
		$description = $lines[0];

		if ( strlen( $description ) > 100 ) {
			$description = substr( $description, 0, 97 ) . '...';
		}

		return $description;
	}
}