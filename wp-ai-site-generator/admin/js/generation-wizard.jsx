/**
 * Generation Wizard React Component
 *
 * @package WPAISiteGenerator
 * @since   1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	Button,
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
	RangeControl,
	RadioControl,
	Spinner,
	Notice,
	Card,
	CardBody,
	CardHeader,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalStepper as Stepper,
	__experimentalStep as Step,
	__experimentalStepLabel as StepLabel,
	ProgressBar,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { chevronLeft, chevronRight, check, close } from '@wordpress/icons';

const GenerationWizard = ({ initialData = {} }) => {
	const [currentStep, setCurrentStep] = useState(0);
	const [isGenerating, setIsGenerating] = useState(false);
	const [generationProgress, setGenerationProgress] = useState(0);
	const [generationStatus, setGenerationStatus] = useState('');
	const [generationId, setGenerationId] = useState(null);
	const [error, setError] = useState(null);
	const [showPreview, setShowPreview] = useState(false);
	const [generatedContent, setGeneratedContent] = useState(null);

	// Form data
	const [formData, setFormData] = useState({
		// Step 1: Basic Information
		siteName: initialData.siteName || '',
		siteDescription: initialData.siteDescription || '',
		industry: initialData.industry || '',
		targetAudience: initialData.targetAudience || '',
		primaryGoal: initialData.primaryGoal || 'informational',

		// Step 2: Content Settings
		language: initialData.language || 'en',
		tone: initialData.tone || 'professional',
		contentLength: initialData.contentLength || 'medium',
		includeImages: initialData.includeImages !== false,
		includeSeo: initialData.includeSeo !== false,

		// Step 3: Structure
		pages: initialData.pages || [
			{ title: 'Home', description: 'Main landing page' },
			{ title: 'About', description: 'About us page' },
			{ title: 'Services', description: 'Our services' },
			{ title: 'Contact', description: 'Contact information' },
		],
		template: initialData.template || '',

		// Step 4: Advanced Settings
		provider: initialData.provider || 'auto',
		model: initialData.model || 'default',
		creativity: initialData.creativity || 0.7,
		includeContactForm: initialData.includeContactForm !== false,
		includeNavMenu: initialData.includeNavMenu !== false,
		customCSS: initialData.customCSS || '',

		// Step 5: Review & Generate
		autoPublish: false,
		generateDrafts: true,
	});

	const steps = [
		{
			label: __('Basic Information', 'wp-ai-site-generator'),
			description: __('Tell us about your website', 'wp-ai-site-generator'),
		},
		{
			label: __('Content Settings', 'wp-ai-site-generator'),
			description: __('Configure content preferences', 'wp-ai-site-generator'),
		},
		{
			label: __('Site Structure', 'wp-ai-site-generator'),
			description: __('Define pages and navigation', 'wp-ai-site-generator'),
		},
		{
			label: __('Advanced Settings', 'wp-ai-site-generator'),
			description: __('Fine-tune generation options', 'wp-ai-site-generator'),
		},
		{
			label: __('Review & Generate', 'wp-ai-site-generator'),
			description: __('Review and start generation', 'wp-ai-site-generator'),
		},
	];

	const industries = [
		{ value: '', label: __('Select Industry', 'wp-ai-site-generator') },
		{ value: 'technology', label: __('Technology', 'wp-ai-site-generator') },
		{ value: 'healthcare', label: __('Healthcare', 'wp-ai-site-generator') },
		{ value: 'finance', label: __('Finance', 'wp-ai-site-generator') },
		{ value: 'education', label: __('Education', 'wp-ai-site-generator') },
		{ value: 'retail', label: __('Retail', 'wp-ai-site-generator') },
		{ value: 'hospitality', label: __('Hospitality', 'wp-ai-site-generator') },
		{ value: 'legal', label: __('Legal', 'wp-ai-site-generator') },
		{ value: 'realestate', label: __('Real Estate', 'wp-ai-site-generator') },
		{ value: 'nonprofit', label: __('Non-Profit', 'wp-ai-site-generator') },
		{ value: 'creative', label: __('Creative/Arts', 'wp-ai-site-generator') },
		{ value: 'other', label: __('Other', 'wp-ai-site-generator') },
	];

	const updateFormData = (field, value) => {
		setFormData({ ...formData, [field]: value });
	};

	const addPage = () => {
		const newPages = [...formData.pages, { title: '', description: '' }];
		updateFormData('pages', newPages);
	};

	const removePage = (index) => {
		const newPages = formData.pages.filter((_, i) => i !== index);
		updateFormData('pages', newPages);
	};

	const updatePage = (index, field, value) => {
		const newPages = [...formData.pages];
		newPages[index][field] = value;
		updateFormData('pages', newPages);
	};

	const validateStep = (stepIndex) => {
		switch (stepIndex) {
			case 0:
				if (!formData.siteName || !formData.siteDescription) {
					setError(__('Please provide site name and description', 'wp-ai-site-generator'));
					return false;
				}
				break;
			case 2:
				if (formData.pages.length === 0) {
					setError(__('At least one page is required', 'wp-ai-site-generator'));
					return false;
				}
				const invalidPages = formData.pages.filter(p => !p.title);
				if (invalidPages.length > 0) {
					setError(__('All pages must have titles', 'wp-ai-site-generator'));
					return false;
				}
				break;
		}
		setError(null);
		return true;
	};

	const handleNext = () => {
		if (validateStep(currentStep)) {
			setCurrentStep(currentStep + 1);
		}
	};

	const handleBack = () => {
		setCurrentStep(currentStep - 1);
		setError(null);
	};

	const startGeneration = async () => {
		setIsGenerating(true);
		setGenerationProgress(0);
		setGenerationStatus(__('Initializing generation...', 'wp-ai-site-generator'));
		setError(null);

		try {
			// Create generation prompt from form data
			const prompt = createGenerationPrompt();

			// Start generation
			const response = await apiFetch({
				path: '/waisg/v1/generations',
				method: 'POST',
				data: {
					prompt,
					settings: {
						provider: formData.provider,
						model: formData.model,
						temperature: formData.creativity,
					},
					options: {
						language: formData.language,
						tone: formData.tone,
						content_length: formData.contentLength,
						include_images: formData.includeImages,
						include_seo: formData.includeSeo,
						auto_publish: formData.autoPublish,
						pages: formData.pages,
					},
				},
			});

			setGenerationId(response.generation_id);
			pollGenerationStatus(response.generation_id);

		} catch (error) {
			setError(error.message || __('Failed to start generation', 'wp-ai-site-generator'));
			setIsGenerating(false);
		}
	};

	const createGenerationPrompt = () => {
		let prompt = `Create a ${formData.primaryGoal} website for "${formData.siteName}". `;
		prompt += `${formData.siteDescription}. `;

		if (formData.industry) {
			prompt += `Industry: ${formData.industry}. `;
		}
		if (formData.targetAudience) {
			prompt += `Target audience: ${formData.targetAudience}. `;
		}

		prompt += `The website should have the following pages: `;
		prompt += formData.pages.map(p => `${p.title} (${p.description})`).join(', ');

		return prompt;
	};

	const pollGenerationStatus = useCallback(async (genId) => {
		const pollInterval = setInterval(async () => {
			try {
				const response = await apiFetch({
					path: `/waisg/v1/generations/${genId}`,
				});

				setGenerationProgress(response.progress || 0);
				setGenerationStatus(response.status_message || __('Processing...', 'wp-ai-site-generator'));

				if (response.status === 'completed') {
					clearInterval(pollInterval);
					setIsGenerating(false);
					setGeneratedContent(response.result);
					setShowPreview(true);
				} else if (response.status === 'failed') {
					clearInterval(pollInterval);
					setIsGenerating(false);
					setError(response.error_message || __('Generation failed', 'wp-ai-site-generator'));
				}
			} catch (error) {
				clearInterval(pollInterval);
				setIsGenerating(false);
				setError(error.message);
			}
		}, 2000); // Poll every 2 seconds
	}, []);

	const cancelGeneration = async () => {
		if (!generationId) return;

		try {
			await apiFetch({
				path: `/waisg/v1/generations/${generationId}/cancel`,
				method: 'POST',
			});
			setIsGenerating(false);
			setGenerationStatus(__('Generation cancelled', 'wp-ai-site-generator'));
		} catch (error) {
			console.error('Failed to cancel generation:', error);
		}
	};

	const renderStepContent = () => {
		switch (currentStep) {
			case 0: // Basic Information
				return (
					<VStack spacing={4}>
						<TextControl
							label={__('Site Name', 'wp-ai-site-generator')}
							value={formData.siteName}
							onChange={(value) => updateFormData('siteName', value)}
							placeholder={__('My Awesome Website', 'wp-ai-site-generator')}
							required
						/>

						<TextareaControl
							label={__('Site Description', 'wp-ai-site-generator')}
							value={formData.siteDescription}
							onChange={(value) => updateFormData('siteDescription', value)}
							placeholder={__('Describe what your website is about...', 'wp-ai-site-generator')}
							rows={4}
							required
						/>

						<SelectControl
							label={__('Industry', 'wp-ai-site-generator')}
							value={formData.industry}
							onChange={(value) => updateFormData('industry', value)}
							options={industries}
						/>

						<TextControl
							label={__('Target Audience', 'wp-ai-site-generator')}
							value={formData.targetAudience}
							onChange={(value) => updateFormData('targetAudience', value)}
							placeholder={__('Small business owners, students, etc.', 'wp-ai-site-generator')}
						/>

						<RadioControl
							label={__('Primary Goal', 'wp-ai-site-generator')}
							selected={formData.primaryGoal}
							onChange={(value) => updateFormData('primaryGoal', value)}
							options={[
								{ label: __('Informational', 'wp-ai-site-generator'), value: 'informational' },
								{ label: __('E-commerce', 'wp-ai-site-generator'), value: 'ecommerce' },
								{ label: __('Portfolio', 'wp-ai-site-generator'), value: 'portfolio' },
								{ label: __('Blog', 'wp-ai-site-generator'), value: 'blog' },
								{ label: __('Corporate', 'wp-ai-site-generator'), value: 'corporate' },
							]}
						/>
					</VStack>
				);

			case 1: // Content Settings
				return (
					<VStack spacing={4}>
						<SelectControl
							label={__('Language', 'wp-ai-site-generator')}
							value={formData.language}
							onChange={(value) => updateFormData('language', value)}
							options={[
								{ value: 'en', label: 'English' },
								{ value: 'es', label: 'Español' },
								{ value: 'fr', label: 'Français' },
								{ value: 'de', label: 'Deutsch' },
								{ value: 'it', label: 'Italiano' },
								{ value: 'pt', label: 'Português' },
								{ value: 'nl', label: 'Nederlands' },
								{ value: 'ru', label: 'Русский' },
								{ value: 'ja', label: '日本語' },
								{ value: 'zh', label: '中文' },
							]}
						/>

						<SelectControl
							label={__('Content Tone', 'wp-ai-site-generator')}
							value={formData.tone}
							onChange={(value) => updateFormData('tone', value)}
							options={[
								{ value: 'professional', label: __('Professional', 'wp-ai-site-generator') },
								{ value: 'casual', label: __('Casual', 'wp-ai-site-generator') },
								{ value: 'friendly', label: __('Friendly', 'wp-ai-site-generator') },
								{ value: 'formal', label: __('Formal', 'wp-ai-site-generator') },
								{ value: 'creative', label: __('Creative', 'wp-ai-site-generator') },
							]}
						/>

						<SelectControl
							label={__('Content Length', 'wp-ai-site-generator')}
							value={formData.contentLength}
							onChange={(value) => updateFormData('contentLength', value)}
							options={[
								{ value: 'short', label: __('Short (300-500 words)', 'wp-ai-site-generator') },
								{ value: 'medium', label: __('Medium (500-1000 words)', 'wp-ai-site-generator') },
								{ value: 'long', label: __('Long (1000-2000 words)', 'wp-ai-site-generator') },
								{ value: 'extra-long', label: __('Extra Long (2000+ words)', 'wp-ai-site-generator') },
							]}
						/>

						<CheckboxControl
							label={__('Include Images', 'wp-ai-site-generator')}
							checked={formData.includeImages}
							onChange={(value) => updateFormData('includeImages', value)}
							help={__('Generate and include relevant images', 'wp-ai-site-generator')}
						/>

						<CheckboxControl
							label={__('Include SEO Metadata', 'wp-ai-site-generator')}
							checked={formData.includeSeo}
							onChange={(value) => updateFormData('includeSeo', value)}
							help={__('Generate meta titles, descriptions, and keywords', 'wp-ai-site-generator')}
						/>
					</VStack>
				);

			case 2: // Site Structure
				return (
					<VStack spacing={4}>
						<div className="waisg-pages-editor">
							<h3>{__('Pages', 'wp-ai-site-generator')}</h3>
							{formData.pages.map((page, index) => (
								<Card key={index} size="small">
									<CardBody>
										<HStack>
											<TextControl
												label={__('Page Title', 'wp-ai-site-generator')}
												value={page.title}
												onChange={(value) => updatePage(index, 'title', value)}
												placeholder={__('Page title', 'wp-ai-site-generator')}
											/>
											<TextareaControl
												label={__('Description', 'wp-ai-site-generator')}
												value={page.description}
												onChange={(value) => updatePage(index, 'description', value)}
												placeholder={__('Brief page description', 'wp-ai-site-generator')}
												rows={2}
											/>
											{formData.pages.length > 1 && (
												<Button
													isDestructive
													variant="tertiary"
													onClick={() => removePage(index)}
													icon="trash"
													label={__('Remove page', 'wp-ai-site-generator')}
												/>
											)}
										</HStack>
									</CardBody>
								</Card>
							))}
							<Button
								variant="secondary"
								onClick={addPage}
								icon="plus-alt2"
							>
								{__('Add Page', 'wp-ai-site-generator')}
							</Button>
						</div>

						<SelectControl
							label={__('Template', 'wp-ai-site-generator')}
							value={formData.template}
							onChange={(value) => updateFormData('template', value)}
							options={[
								{ value: '', label: __('No template', 'wp-ai-site-generator') },
								{ value: 'business', label: __('Business', 'wp-ai-site-generator') },
								{ value: 'portfolio', label: __('Portfolio', 'wp-ai-site-generator') },
								{ value: 'blog', label: __('Blog', 'wp-ai-site-generator') },
								{ value: 'ecommerce', label: __('E-commerce', 'wp-ai-site-generator') },
							]}
							help={__('Optional: Use a pre-defined template', 'wp-ai-site-generator')}
						/>
					</VStack>
				);

			case 3: // Advanced Settings
				return (
					<VStack spacing={4}>
						<SelectControl
							label={__('AI Provider', 'wp-ai-site-generator')}
							value={formData.provider}
							onChange={(value) => updateFormData('provider', value)}
							options={[
								{ value: 'auto', label: __('Auto-select', 'wp-ai-site-generator') },
								{ value: 'openai', label: 'OpenAI' },
								{ value: 'anthropic', label: 'Anthropic' },
								{ value: 'google', label: 'Google' },
								{ value: 'custom', label: __('Custom', 'wp-ai-site-generator') },
							]}
						/>

						<SelectControl
							label={__('Model', 'wp-ai-site-generator')}
							value={formData.model}
							onChange={(value) => updateFormData('model', value)}
							options={[
								{ value: 'default', label: __('Default', 'wp-ai-site-generator') },
								{ value: 'gpt-4', label: 'GPT-4' },
								{ value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' },
								{ value: 'claude-3', label: 'Claude 3' },
								{ value: 'gemini-pro', label: 'Gemini Pro' },
							]}
						/>

						<RangeControl
							label={__('Creativity Level', 'wp-ai-site-generator')}
							value={formData.creativity}
							onChange={(value) => updateFormData('creativity', value)}
							min={0}
							max={1}
							step={0.1}
							help={__('Higher values make content more creative but less predictable', 'wp-ai-site-generator')}
						/>

						<CheckboxControl
							label={__('Include Contact Form', 'wp-ai-site-generator')}
							checked={formData.includeContactForm}
							onChange={(value) => updateFormData('includeContactForm', value)}
						/>

						<CheckboxControl
							label={__('Create Navigation Menu', 'wp-ai-site-generator')}
							checked={formData.includeNavMenu}
							onChange={(value) => updateFormData('includeNavMenu', value)}
						/>

						<TextareaControl
							label={__('Custom CSS', 'wp-ai-site-generator')}
							value={formData.customCSS}
							onChange={(value) => updateFormData('customCSS', value)}
							placeholder={__('Add custom CSS styles (optional)', 'wp-ai-site-generator')}
							rows={4}
						/>
					</VStack>
				);

			case 4: // Review & Generate
				return (
					<VStack spacing={4}>
						<Card>
							<CardHeader>
								<h3>{__('Generation Summary', 'wp-ai-site-generator')}</h3>
							</CardHeader>
							<CardBody>
								<dl className="waisg-summary-list">
									<dt>{__('Site Name:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.siteName}</dd>

									<dt>{__('Description:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.siteDescription}</dd>

									<dt>{__('Industry:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.industry || __('Not specified', 'wp-ai-site-generator')}</dd>

									<dt>{__('Language:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.language}</dd>

									<dt>{__('Tone:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.tone}</dd>

									<dt>{__('Number of Pages:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.pages.length}</dd>

									<dt>{__('Pages:', 'wp-ai-site-generator')}</dt>
									<dd>{formData.pages.map(p => p.title).join(', ')}</dd>

									<dt>{__('Features:', 'wp-ai-site-generator')}</dt>
									<dd>
										{[
											formData.includeImages && __('Images', 'wp-ai-site-generator'),
											formData.includeSeo && __('SEO', 'wp-ai-site-generator'),
											formData.includeContactForm && __('Contact Form', 'wp-ai-site-generator'),
											formData.includeNavMenu && __('Navigation Menu', 'wp-ai-site-generator'),
										].filter(Boolean).join(', ')}
									</dd>
								</dl>
							</CardBody>
						</Card>

						<CheckboxControl
							label={__('Auto-publish generated content', 'wp-ai-site-generator')}
							checked={formData.autoPublish}
							onChange={(value) => updateFormData('autoPublish', value)}
							help={__('Publish pages immediately or save as drafts', 'wp-ai-site-generator')}
						/>
					</VStack>
				);

			default:
				return null;
		}
	};

	return (
		<div className="waisg-generation-wizard">
			{/* Stepper */}
			<div className="waisg-wizard-stepper">
				{steps.map((step, index) => (
					<div
						key={index}
						className={`waisg-wizard-step ${
							index === currentStep ? 'is-active' : ''
						} ${index < currentStep ? 'is-completed' : ''}`}
					>
						<div className="waisg-wizard-step-indicator">
							{index < currentStep ? (
								<Icon icon={check} />
							) : (
								<span>{index + 1}</span>
							)}
						</div>
						<div className="waisg-wizard-step-content">
							<div className="waisg-wizard-step-label">{step.label}</div>
							<div className="waisg-wizard-step-description">{step.description}</div>
						</div>
					</div>
				))}
			</div>

			{/* Step Content */}
			<Card>
				<CardHeader>
					<h2>{steps[currentStep].label}</h2>
				</CardHeader>
				<CardBody>
					{error && (
						<Notice status="error" onRemove={() => setError(null)}>
							{error}
						</Notice>
					)}

					{renderStepContent()}
				</CardBody>
			</Card>

			{/* Navigation */}
			<HStack alignment="space-between" className="waisg-wizard-navigation">
				<Button
					variant="secondary"
					onClick={handleBack}
					disabled={currentStep === 0}
					icon={chevronLeft}
				>
					{__('Back', 'wp-ai-site-generator')}
				</Button>

				{currentStep < steps.length - 1 ? (
					<Button
						variant="primary"
						onClick={handleNext}
						icon={chevronRight}
						iconPosition="right"
					>
						{__('Next', 'wp-ai-site-generator')}
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={startGeneration}
						disabled={isGenerating}
					>
						{isGenerating ? (
							<>
								<Spinner />
								{__('Generating...', 'wp-ai-site-generator')}
							</>
						) : (
							__('Start Generation', 'wp-ai-site-generator')
						)}
					</Button>
				)}
			</HStack>

			{/* Generation Progress Modal */}
			{isGenerating && (
				<Modal
					title={__('Generating Your Website', 'wp-ai-site-generator')}
					onRequestClose={cancelGeneration}
					shouldCloseOnClickOutside={false}
				>
					<VStack spacing={4}>
						<ProgressBar value={generationProgress} />
						<p>{generationStatus}</p>
						<Button
							variant="secondary"
							onClick={cancelGeneration}
						>
							{__('Cancel Generation', 'wp-ai-site-generator')}
						</Button>
					</VStack>
				</Modal>
			)}

			{/* Preview Modal */}
			{showPreview && generatedContent && (
				<Modal
					title={__('Generation Complete!', 'wp-ai-site-generator')}
					onRequestClose={() => setShowPreview(false)}
					size="large"
				>
					<VStack spacing={4}>
						<Notice status="success" isDismissible={false}>
							{__('Your website has been generated successfully!', 'wp-ai-site-generator')}
						</Notice>

						<div className="waisg-generation-results">
							<h3>{__('Generated Pages:', 'wp-ai-site-generator')}</h3>
							<ul>
								{generatedContent.pages?.map((page, index) => (
									<li key={index}>
										<a href={page.url} target="_blank" rel="noopener noreferrer">
											{page.title}
										</a>
									</li>
								))}
							</ul>
						</div>

						<HStack>
							<Button
								variant="primary"
								href={generatedContent.preview_url}
								target="_blank"
							>
								{__('View Website', 'wp-ai-site-generator')}
							</Button>
							<Button
								variant="secondary"
								onClick={() => window.location.href = generatedContent.edit_url}
							>
								{__('Edit Pages', 'wp-ai-site-generator')}
							</Button>
						</HStack>
					</VStack>
				</Modal>
			)}
		</div>
	);
};

export default GenerationWizard;