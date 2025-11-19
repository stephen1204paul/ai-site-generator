/**
 * Hero Block Save Component
 *
 * @package WPAISiteGenerator
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Hero Block Save Component
 *
 * @param {Object} props Block props
 * @return {JSX.Element} Save component
 */
export default function save( { attributes } ) {
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
	} = attributes;

	const blockProps = useBlockProps.save( {
		className: `waisg-hero align-${ contentAlignment }`,
		style: {
			minHeight: minHeight,
			backgroundImage: backgroundImage?.url ? `url(${ backgroundImage.url })` : undefined,
		},
	} );

	return (
		<div { ...blockProps }>
			{ backgroundImage?.url && (
				<div
					className="waisg-hero__overlay"
					style={ { opacity: overlayOpacity } }
					aria-hidden="true"
				/>
			) }
			<div className="waisg-hero__content">
				<div className="waisg-hero__text-wrapper">
					{ subtitle && (
						<RichText.Content
							tagName="p"
							className="waisg-hero__subtitle"
							value={ subtitle }
						/>
					) }
					<RichText.Content
						tagName="h1"
						className="waisg-hero__title"
						value={ title }
					/>
					{ description && (
						<RichText.Content
							tagName="p"
							className="waisg-hero__description"
							value={ description }
						/>
					) }
				</div>
				<div className="waisg-hero__buttons">
					{ primaryButtonText && (
						<div className="wp-block-button is-style-fill">
							<a
								className="wp-block-button__link"
								href={ primaryButtonUrl }
								rel="noopener"
							>
								{ primaryButtonText }
							</a>
						</div>
					) }
					{ secondaryButtonText && (
						<div className="wp-block-button is-style-outline">
							<a
								className="wp-block-button__link"
								href={ secondaryButtonUrl }
								rel="noopener"
							>
								{ secondaryButtonText }
							</a>
						</div>
					) }
				</div>
			</div>
		</div>
	);
}