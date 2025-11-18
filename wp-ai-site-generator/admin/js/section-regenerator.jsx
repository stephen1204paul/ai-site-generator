/**
 * Section Regenerator React Component
 *
 * @package WPAISiteGenerator
 * @since   1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	TextareaControl,
	SelectControl,
	RangeControl,
	CheckboxControl,
	Spinner,
	Notice,
	Card,
	CardBody,
	CardHeader,
	Modal,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	Popover,
	ToolbarButton,
	ToolbarGroup,
	Dropdown,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { edit, update, cog } from '@wordpress/icons';

const SectionRegenerator = ({
	sectionId,
	sectionContent,
	sectionType = 'paragraph',
	pageId,
	onUpdate,
	inline = false
}) => {
	const [isRegenerating, setIsRegenerating] = useState(false);
	const [showOptions, setShowOptions] = useState(false);
	const [showPreview, setShowPreview] = useState(false);
	const [newContent, setNewContent] = useState('');
	const [error, setError] = useState(null);
	const [variations, setVariations] = useState([]);
	const [selectedVariation, setSelectedVariation] = useState(0);

	// Regeneration options
	const [options, setOptions] = useState({
		prompt: '',
		tone: 'same',
		length: 'same',
		style: 'same',
		includeKeywords: '',
		excludeWords: '',
		temperature: 0.7,
		variations: 1,
		preserveFormatting: true,
		maintainSeoKeywords: true,
	});

	const toneOptions = [
		{ value: 'same', label: __('Keep Same', 'wp-ai-site-generator') },
		{ value: 'professional', label: __('Professional', 'wp-ai-site-generator') },
		{ value: 'casual', label: __('Casual', 'wp-ai-site-generator') },
		{ value: 'friendly', label: __('Friendly', 'wp-ai-site-generator') },
		{ value: 'formal', label: __('Formal', 'wp-ai-site-generator') },
		{ value: 'creative', label: __('Creative', 'wp-ai-site-generator') },
		{ value: 'persuasive', label: __('Persuasive', 'wp-ai-site-generator') },
		{ value: 'informative', label: __('Informative', 'wp-ai-site-generator') },
	];

	const lengthOptions = [
		{ value: 'same', label: __('Keep Same', 'wp-ai-site-generator') },
		{ value: 'shorter', label: __('Make Shorter', 'wp-ai-site-generator') },
		{ value: 'longer', label: __('Make Longer', 'wp-ai-site-generator') },
		{ value: 'much-shorter', label: __('Much Shorter', 'wp-ai-site-generator') },
		{ value: 'much-longer', label: __('Much Longer', 'wp-ai-site-generator') },
	];

	const styleOptions = [
		{ value: 'same', label: __('Keep Same', 'wp-ai-site-generator') },
		{ value: 'paragraph', label: __('Paragraph', 'wp-ai-site-generator') },
		{ value: 'bullet-points', label: __('Bullet Points', 'wp-ai-site-generator') },
		{ value: 'numbered-list', label: __('Numbered List', 'wp-ai-site-generator') },
		{ value: 'heading-paragraphs', label: __('Headings + Paragraphs', 'wp-ai-site-generator') },
		{ value: 'qa-format', label: __('Q&A Format', 'wp-ai-site-generator') },
		{ value: 'storytelling', label: __('Storytelling', 'wp-ai-site-generator') },
	];

	const regenerateSection = async () => {
		setIsRegenerating(true);
		setError(null);
		setVariations([]);

		try {
			const response = await apiFetch({
				path: '/waisg/v1/sections/regenerate',
				method: 'POST',
				data: {
					section_id: sectionId,
					page_id: pageId,
					current_content: sectionContent,
					section_type: sectionType,
					options: {
						...options,
						action: 'regenerate'
					}
				},
			});

			if (response.success) {
				if (options.variations > 1) {
					setVariations(response.data.variations);
					setSelectedVariation(0);
					setNewContent(response.data.variations[0]);
				} else {
					setNewContent(response.data.content);
				}
				setShowPreview(true);
			} else {
				throw new Error(response.message || __('Regeneration failed', 'wp-ai-site-generator'));
			}
		} catch (error) {
			setError(error.message);
		} finally {
			setIsRegenerating(false);
		}
	};

	const improveSection = async (improvementType) => {
		setIsRegenerating(true);
		setError(null);

		try {
			const response = await apiFetch({
				path: '/waisg/v1/sections/improve',
				method: 'POST',
				data: {
					section_id: sectionId,
					page_id: pageId,
					current_content: sectionContent,
					improvement_type: improvementType,
				},
			});

			if (response.success) {
				setNewContent(response.data.content);
				setShowPreview(true);
			} else {
				throw new Error(response.message || __('Improvement failed', 'wp-ai-site-generator'));
			}
		} catch (error) {
			setError(error.message);
		} finally {
			setIsRegenerating(false);
		}
	};

	const applyRegeneration = () => {
		if (onUpdate) {
			const contentToApply = variations.length > 0
				? variations[selectedVariation]
				: newContent;
			onUpdate(contentToApply);
		}
		resetState();
	};

	const resetState = () => {
		setShowPreview(false);
		setShowOptions(false);
		setNewContent('');
		setVariations([]);
		setSelectedVariation(0);
		setError(null);
	};

	const quickActions = [
		{
			label: __('Fix Grammar', 'wp-ai-site-generator'),
			icon: 'editor-spellcheck',
			action: () => improveSection('grammar'),
		},
		{
			label: __('Improve Clarity', 'wp-ai-site-generator'),
			icon: 'visibility',
			action: () => improveSection('clarity'),
		},
		{
			label: __('Enhance SEO', 'wp-ai-site-generator'),
			icon: 'search',
			action: () => improveSection('seo'),
		},
		{
			label: __('Add Details', 'wp-ai-site-generator'),
			icon: 'plus-alt2',
			action: () => improveSection('expand'),
		},
		{
			label: __('Simplify', 'wp-ai-site-generator'),
			icon: 'minus',
			action: () => improveSection('simplify'),
		},
		{
			label: __('Change Tone', 'wp-ai-site-generator'),
			icon: 'admin-customizer',
			action: () => setShowOptions(true),
		},
	];

	// Render inline toolbar for block editor integration
	if (inline) {
		return (
			<>
				<ToolbarGroup>
					<Dropdown
						renderToggle={({ isOpen, onToggle }) => (
							<ToolbarButton
								icon={update}
								label={__('AI Section Tools', 'wp-ai-site-generator')}
								onClick={onToggle}
								isActive={isOpen}
							/>
						)}
						renderContent={() => (
							<div className="waisg-inline-regenerator">
								<VStack spacing={2}>
									<h4>{__('Quick Actions', 'wp-ai-site-generator')}</h4>
									{quickActions.map((action, index) => (
										<Button
											key={index}
											variant="secondary"
											icon={action.icon}
											onClick={action.action}
											disabled={isRegenerating}
										>
											{action.label}
										</Button>
									))}
									<Button
										variant="primary"
										onClick={() => setShowOptions(true)}
										disabled={isRegenerating}
									>
										{__('Advanced Options', 'wp-ai-site-generator')}
									</Button>
								</VStack>
							</div>
						)}
					/>
				</ToolbarGroup>

				{/* Modals for inline mode */}
				{showOptions && (
					<Modal
						title={__('Regenerate Section', 'wp-ai-site-generator')}
						onRequestClose={resetState}
					>
						<RegenerationOptions
							options={options}
							setOptions={setOptions}
							onRegenerate={regenerateSection}
							isRegenerating={isRegenerating}
							onCancel={resetState}
						/>
					</Modal>
				)}

				{showPreview && (
					<Modal
						title={__('Preview Regenerated Content', 'wp-ai-site-generator')}
						onRequestClose={resetState}
						size="large"
					>
						<PreviewContent
							originalContent={sectionContent}
							newContent={newContent}
							variations={variations}
							selectedVariation={selectedVariation}
							setSelectedVariation={setSelectedVariation}
							onApply={applyRegeneration}
							onCancel={resetState}
						/>
					</Modal>
				)}
			</>
		);
	}

	// Regular component render
	return (
		<div className="waisg-section-regenerator">
			{/* Quick Actions Bar */}
			<Card size="small">
				<CardBody>
					<HStack wrap alignment="space-between">
						<HStack wrap>
							{quickActions.slice(0, 5).map((action, index) => (
								<Button
									key={index}
									variant="secondary"
									size="small"
									icon={action.icon}
									onClick={action.action}
									disabled={isRegenerating}
								>
									{action.label}
								</Button>
							))}
						</HStack>
						<Button
							variant="primary"
							size="small"
							icon={cog}
							onClick={() => setShowOptions(!showOptions)}
							disabled={isRegenerating}
						>
							{__('More Options', 'wp-ai-site-generator')}
						</Button>
					</HStack>
				</CardBody>
			</Card>

			{/* Error Notice */}
			{error && (
				<Notice status="error" onRemove={() => setError(null)}>
					{error}
				</Notice>
			)}

			{/* Advanced Options Panel */}
			{showOptions && (
				<Card>
					<CardHeader>
						<h3>{__('Regeneration Options', 'wp-ai-site-generator')}</h3>
					</CardHeader>
					<CardBody>
						<RegenerationOptions
							options={options}
							setOptions={setOptions}
							onRegenerate={regenerateSection}
							isRegenerating={isRegenerating}
							onCancel={() => setShowOptions(false)}
						/>
					</CardBody>
				</Card>
			)}

			{/* Preview Panel */}
			{showPreview && (
				<Card>
					<CardHeader>
						<h3>{__('Preview Regenerated Content', 'wp-ai-site-generator')}</h3>
					</CardHeader>
					<CardBody>
						<PreviewContent
							originalContent={sectionContent}
							newContent={newContent}
							variations={variations}
							selectedVariation={selectedVariation}
							setSelectedVariation={setSelectedVariation}
							onApply={applyRegeneration}
							onCancel={resetState}
						/>
					</CardBody>
				</Card>
			)}

			{/* Loading State */}
			{isRegenerating && (
				<div className="waisg-regenerating-overlay">
					<Spinner />
					<p>{__('Regenerating content...', 'wp-ai-site-generator')}</p>
				</div>
			)}
		</div>
	);
};

// Regeneration Options Component
const RegenerationOptions = ({ options, setOptions, onRegenerate, isRegenerating, onCancel }) => {
	const updateOption = (key, value) => {
		setOptions({ ...options, [key]: value });
	};

	return (
		<VStack spacing={4}>
			<TextareaControl
				label={__('Custom Instructions (Optional)', 'wp-ai-site-generator')}
				value={options.prompt}
				onChange={(value) => updateOption('prompt', value)}
				placeholder={__('E.g., Make it more engaging, add statistics, focus on benefits...', 'wp-ai-site-generator')}
				rows={3}
			/>

			<HStack alignment="stretch">
				<SelectControl
					label={__('Tone', 'wp-ai-site-generator')}
					value={options.tone}
					onChange={(value) => updateOption('tone', value)}
					options={[
						{ value: 'same', label: __('Keep Same', 'wp-ai-site-generator') },
						{ value: 'professional', label: __('Professional', 'wp-ai-site-generator') },
						{ value: 'casual', label: __('Casual', 'wp-ai-site-generator') },
						{ value: 'friendly', label: __('Friendly', 'wp-ai-site-generator') },
						{ value: 'formal', label: __('Formal', 'wp-ai-site-generator') },
						{ value: 'creative', label: __('Creative', 'wp-ai-site-generator') },
						{ value: 'persuasive', label: __('Persuasive', 'wp-ai-site-generator') },
						{ value: 'informative', label: __('Informative', 'wp-ai-site-generator') },
					]}
				/>

				<SelectControl
					label={__('Length', 'wp-ai-site-generator')}
					value={options.length}
					onChange={(value) => updateOption('length', value)}
					options={[
						{ value: 'same', label: __('Keep Same', 'wp-ai-site-generator') },
						{ value: 'shorter', label: __('Shorter', 'wp-ai-site-generator') },
						{ value: 'longer', label: __('Longer', 'wp-ai-site-generator') },
						{ value: 'much-shorter', label: __('Much Shorter', 'wp-ai-site-generator') },
						{ value: 'much-longer', label: __('Much Longer', 'wp-ai-site-generator') },
					]}
				/>

				<SelectControl
					label={__('Style', 'wp-ai-site-generator')}
					value={options.style}
					onChange={(value) => updateOption('style', value)}
					options={[
						{ value: 'same', label: __('Keep Same', 'wp-ai-site-generator') },
						{ value: 'paragraph', label: __('Paragraph', 'wp-ai-site-generator') },
						{ value: 'bullet-points', label: __('Bullet Points', 'wp-ai-site-generator') },
						{ value: 'numbered-list', label: __('Numbered List', 'wp-ai-site-generator') },
						{ value: 'heading-paragraphs', label: __('With Headings', 'wp-ai-site-generator') },
						{ value: 'qa-format', label: __('Q&A Format', 'wp-ai-site-generator') },
					]}
				/>
			</HStack>

			<TextControl
				label={__('Include Keywords', 'wp-ai-site-generator')}
				value={options.includeKeywords}
				onChange={(value) => updateOption('includeKeywords', value)}
				placeholder={__('Comma-separated keywords', 'wp-ai-site-generator')}
				help={__('Keywords to include in the regenerated content', 'wp-ai-site-generator')}
			/>

			<TextControl
				label={__('Exclude Words', 'wp-ai-site-generator')}
				value={options.excludeWords}
				onChange={(value) => updateOption('excludeWords', value)}
				placeholder={__('Comma-separated words to avoid', 'wp-ai-site-generator')}
			/>

			<RangeControl
				label={__('Creativity Level', 'wp-ai-site-generator')}
				value={options.temperature}
				onChange={(value) => updateOption('temperature', value)}
				min={0}
				max={1}
				step={0.1}
			/>

			<RangeControl
				label={__('Number of Variations', 'wp-ai-site-generator')}
				value={options.variations}
				onChange={(value) => updateOption('variations', value)}
				min={1}
				max={5}
				help={__('Generate multiple versions to choose from', 'wp-ai-site-generator')}
			/>

			<CheckboxControl
				label={__('Preserve Formatting', 'wp-ai-site-generator')}
				checked={options.preserveFormatting}
				onChange={(value) => updateOption('preserveFormatting', value)}
			/>

			<CheckboxControl
				label={__('Maintain SEO Keywords', 'wp-ai-site-generator')}
				checked={options.maintainSeoKeywords}
				onChange={(value) => updateOption('maintainSeoKeywords', value)}
			/>

			<HStack>
				<Button
					variant="primary"
					onClick={onRegenerate}
					disabled={isRegenerating}
				>
					{isRegenerating ? (
						<>
							<Spinner />
							{__('Regenerating...', 'wp-ai-site-generator')}
						</>
					) : (
						__('Regenerate', 'wp-ai-site-generator')
					)}
				</Button>
				<Button
					variant="secondary"
					onClick={onCancel}
					disabled={isRegenerating}
				>
					{__('Cancel', 'wp-ai-site-generator')}
				</Button>
			</HStack>
		</VStack>
	);
};

// Preview Content Component
const PreviewContent = ({
	originalContent,
	newContent,
	variations,
	selectedVariation,
	setSelectedVariation,
	onApply,
	onCancel
}) => {
	const [showComparison, setShowComparison] = useState(false);
	const currentContent = variations.length > 0 ? variations[selectedVariation] : newContent;

	return (
		<VStack spacing={4}>
			{variations.length > 1 && (
				<SelectControl
					label={__('Select Variation', 'wp-ai-site-generator')}
					value={selectedVariation}
					onChange={(value) => setSelectedVariation(parseInt(value))}
					options={variations.map((_, index) => ({
						value: index,
						label: sprintf(__('Variation %d', 'wp-ai-site-generator'), index + 1),
					}))}
				/>
			)}

			<CheckboxControl
				label={__('Show Comparison', 'wp-ai-site-generator')}
				checked={showComparison}
				onChange={setShowComparison}
			/>

			{showComparison ? (
				<div className="waisg-content-comparison">
					<div className="waisg-comparison-column">
						<h4>{__('Original Content', 'wp-ai-site-generator')}</h4>
						<div className="waisg-content-box">
							{originalContent}
						</div>
					</div>
					<div className="waisg-comparison-column">
						<h4>{__('Regenerated Content', 'wp-ai-site-generator')}</h4>
						<div className="waisg-content-box">
							{currentContent}
						</div>
					</div>
				</div>
			) : (
				<div className="waisg-preview-content">
					<div className="waisg-content-box">
						{currentContent}
					</div>
				</div>
			)}

			<HStack>
				<Button
					variant="primary"
					onClick={onApply}
				>
					{__('Apply Changes', 'wp-ai-site-generator')}
				</Button>
				<Button
					variant="secondary"
					onClick={onCancel}
				>
					{__('Cancel', 'wp-ai-site-generator')}
				</Button>
			</HStack>
		</VStack>
	);
};

export default SectionRegenerator;