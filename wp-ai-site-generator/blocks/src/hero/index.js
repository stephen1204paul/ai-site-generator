/**
 * Hero Block Registration
 *
 * @package WPAISiteGenerator
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import save from './save';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

/**
 * Register the Hero block
 */
registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save,
	example: {
		attributes: {
			title: __( 'Welcome to Your Future', 'wp-ai-site-generator' ),
			subtitle: __( 'Innovation Starts Here', 'wp-ai-site-generator' ),
			description: __( 'Transform your ideas into reality with our cutting-edge AI technology', 'wp-ai-site-generator' ),
			primaryButtonText: __( 'Get Started', 'wp-ai-site-generator' ),
			secondaryButtonText: __( 'Watch Demo', 'wp-ai-site-generator' ),
			contentAlignment: 'center',
		},
	},
} );