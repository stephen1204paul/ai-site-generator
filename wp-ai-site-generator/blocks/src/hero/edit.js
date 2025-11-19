/**
 * Hero Block Editor Component
 *
 * @package WPAISiteGenerator
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	BlockControls,
	AlignmentToolbar,
	URLInput,
} from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	Button,
	TextControl,
	RangeControl,
	ToggleControl,
	SelectControl,
	Toolbar,
	ToolbarButton,
	ToolbarGroup,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Hero Block Edit Component
 *
 * @param {Object} props Block props
 * @return {JSX.Element} Edit component
 */
export default function Edit( { attributes, setAttributes, isSelected } ) {
	const {
		title,
		subtitle,
		description,
		primaryButtonText,
		primaryButtonUrl,
		secondaryButtonText,
		secondaryButtonUrl,
		backgroundImage,
		overlayOpacity,
		contentAlignment,
		minHeight,
		aiPrompt,
		aiGenerated,
	} = attributes;

	const [ isGenerating, setIsGenerating ] = useState( false );
	const [ generationError, setGenerationError ] = useState( null );

	const blockProps = useBlockProps( {
		className: `waisg-hero align-${ contentAlignment }`,
		style: {
			minHeight: minHeight,
			backgroundImage: backgroundImage?.url ? `url(${ backgroundImage.url })` : undefined,
		},
	} );

	/**
	 * Generate content using AI
	 */
	const generateContent = async () => {
		if ( ! aiPrompt ) {
			setGenerationError( __( 'Please enter a prompt for AI generation', 'wp-ai-site-generator' ) );
			return;
		}

		setIsGenerating( true );
		setGenerationError( null );

		try {
			const response = await apiFetch( {
				path: addQueryArgs( '/waisg/v1/generate', {
					type: 'hero',
					prompt: aiPrompt,
				} ),
				method: 'POST',
				data: {
					prompt: aiPrompt,
					blockType: 'hero',
				},
			} );

			if ( response.success && response.data ) {
				setAttributes( {
					title: response.data.title || title,
					subtitle: response.data.subtitle || subtitle,
					description: response.data.description || description,
					primaryButtonText: response.data.primaryButtonText || primaryButtonText,
					secondaryButtonText: response.data.secondaryButtonText || secondaryButtonText,
					aiGenerated: true,
				} );
			}
		} catch ( error ) {
			setGenerationError( error.message || __( 'Failed to generate content', 'wp-ai-site-generator' ) );
		} finally {
			setIsGenerating( false );
		}
	};

	/**
	 * Regenerate specific field
	 */
	const regenerateField = async ( field ) => {
		setIsGenerating( true );
		try {
			const response = await apiFetch( {
				path: '/waisg/v1/generate',
				method: 'POST',
				data: {
					prompt: `Generate a new ${ field } for: ${ aiPrompt || title }`,
					blockType: 'hero',
					field: field,
				},
			} );

			if ( response.success && response.data ) {
				setAttributes( {
					[ field ]: response.data[ field ],
					aiGenerated: true,
				} );
			}
		} catch ( error ) {
			console.error( 'Regeneration failed:', error );
		} finally {
			setIsGenerating( false );
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'AI Generation', 'wp-ai-site-generator' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'AI Prompt', 'wp-ai-site-generator' ) }
						value={ aiPrompt }
						onChange={ ( value ) => setAttributes( { aiPrompt: value } ) }
						help={ __( 'Describe the hero section you want to generate', 'wp-ai-site-generator' ) }
					/>
					<Button
						isPrimary
						onClick={ generateContent }
						disabled={ isGenerating || ! aiPrompt }
						className="waisg-generate-button"
					>
						{ isGenerating ? (
							<>
								<Spinner />
								{ __( 'Generating...', 'wp-ai-site-generator' ) }
							</>
						) : (
							__( 'Generate Content', 'wp-ai-site-generator' )
						) }
					</Button>
					{ generationError && (
						<div className="waisg-error-message">
							{ generationError }
						</div>
					) }
					{ aiGenerated && (
						<div className="waisg-success-message">
							{ __( 'Content generated successfully!', 'wp-ai-site-generator' ) }
						</div>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Hero Settings', 'wp-ai-site-generator' ) }>
					<SelectControl
						label={ __( 'Content Alignment', 'wp-ai-site-generator' ) }
						value={ contentAlignment }
						options={ [
							{ label: __( 'Left', 'wp-ai-site-generator' ), value: 'left' },
							{ label: __( 'Center', 'wp-ai-site-generator' ), value: 'center' },
							{ label: __( 'Right', 'wp-ai-site-generator' ), value: 'right' },
						] }
						onChange={ ( value ) => setAttributes( { contentAlignment: value } ) }
					/>
					<TextControl
						label={ __( 'Minimum Height', 'wp-ai-site-generator' ) }
						value={ minHeight }
						onChange={ ( value ) => setAttributes( { minHeight: value } ) }
						help={ __( 'e.g., 500px, 100vh', 'wp-ai-site-generator' ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Background', 'wp-ai-site-generator' ) }>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( {
									backgroundImage: {
										id: media.id,
										url: media.url,
										alt: media.alt,
									},
								} )
							}
							allowedTypes={ [ 'image' ] }
							value={ backgroundImage?.id }
							render={ ( { open } ) => (
								<div>
									{ backgroundImage?.url ? (
										<div>
											<img src={ backgroundImage.url } alt={ backgroundImage.alt } style={ { maxWidth: '100%' } } />
											<Button
												isSecondary
												onClick={ open }
												style={ { marginTop: '10px' } }
											>
												{ __( 'Replace Image', 'wp-ai-site-generator' ) }
											</Button>
											<Button
												isDestructive
												onClick={ () => setAttributes( { backgroundImage: {} } ) }
												style={ { marginTop: '10px', marginLeft: '10px' } }
											>
												{ __( 'Remove', 'wp-ai-site-generator' ) }
											</Button>
										</div>
									) : (
										<Button isPrimary onClick={ open }>
											{ __( 'Select Background Image', 'wp-ai-site-generator' ) }
										</Button>
									) }
								</div>
							) }
						/>
					</MediaUploadCheck>
					{ backgroundImage?.url && (
						<RangeControl
							label={ __( 'Overlay Opacity', 'wp-ai-site-generator' ) }
							value={ overlayOpacity }
							onChange={ ( value ) => setAttributes( { overlayOpacity: value } ) }
							min={ 0 }
							max={ 1 }
							step={ 0.1 }
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Button Settings', 'wp-ai-site-generator' ) }>
					<TextControl
						label={ __( 'Primary Button URL', 'wp-ai-site-generator' ) }
						value={ primaryButtonUrl }
						onChange={ ( value ) => setAttributes( { primaryButtonUrl: value } ) }
					/>
					<TextControl
						label={ __( 'Secondary Button URL', 'wp-ai-site-generator' ) }
						value={ secondaryButtonUrl }
						onChange={ ( value ) => setAttributes( { secondaryButtonUrl: value } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon="update"
						label={ __( 'Regenerate All', 'wp-ai-site-generator' ) }
						onClick={ generateContent }
						disabled={ isGenerating || ! aiPrompt }
					/>
				</ToolbarGroup>
				<AlignmentToolbar
					value={ contentAlignment }
					onChange={ ( value ) => setAttributes( { contentAlignment: value } ) }
				/>
			</BlockControls>

			<div { ...blockProps }>
				{ backgroundImage?.url && (
					<div
						className="waisg-hero__overlay"
						style={ { opacity: overlayOpacity } }
					/>
				) }
				<div className="waisg-hero__content">
					<div className="waisg-hero__text-wrapper">
						{ subtitle && (
							<RichText
								tagName="p"
								className="waisg-hero__subtitle"
								value={ subtitle }
								onChange={ ( value ) => setAttributes( { subtitle: value } ) }
								placeholder={ __( 'Add subtitle...', 'wp-ai-site-generator' ) }
							/>
						) }
						<RichText
							tagName="h1"
							className="waisg-hero__title"
							value={ title }
							onChange={ ( value ) => setAttributes( { title: value } ) }
							placeholder={ __( 'Add title...', 'wp-ai-site-generator' ) }
						/>
						{ aiGenerated && (
							<Button
								isSmall
								isSecondary
								icon="update"
								onClick={ () => regenerateField( 'title' ) }
								disabled={ isGenerating }
								className="waisg-regenerate-field"
							>
								{ __( 'Regenerate', 'wp-ai-site-generator' ) }
							</Button>
						) }
						<RichText
							tagName="p"
							className="waisg-hero__description"
							value={ description }
							onChange={ ( value ) => setAttributes( { description: value } ) }
							placeholder={ __( 'Add description...', 'wp-ai-site-generator' ) }
						/>
						{ aiGenerated && (
							<Button
								isSmall
								isSecondary
								icon="update"
								onClick={ () => regenerateField( 'description' ) }
								disabled={ isGenerating }
								className="waisg-regenerate-field"
							>
								{ __( 'Regenerate', 'wp-ai-site-generator' ) }
							</Button>
						) }
					</div>
					<div className="waisg-hero__buttons">
						{ primaryButtonText && (
							<div className="wp-block-button is-style-fill">
								<RichText
									tagName="a"
									className="wp-block-button__link"
									value={ primaryButtonText }
									onChange={ ( value ) => setAttributes( { primaryButtonText: value } ) }
									placeholder={ __( 'Primary button...', 'wp-ai-site-generator' ) }
								/>
							</div>
						) }
						{ secondaryButtonText && (
							<div className="wp-block-button is-style-outline">
								<RichText
									tagName="a"
									className="wp-block-button__link"
									value={ secondaryButtonText }
									onChange={ ( value ) => setAttributes( { secondaryButtonText: value } ) }
									placeholder={ __( 'Secondary button...', 'wp-ai-site-generator' ) }
								/>
							</div>
						) }
					</div>
				</div>
			</div>
		</>
	);
}