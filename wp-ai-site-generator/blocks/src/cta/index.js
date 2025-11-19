/**
 * CTA Block Registration
 */

import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import save from './save';
import metadata from './block.json';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save,
	example: {
		attributes: {
			title: __( 'Ready to Transform Your Business?', 'wp-ai-site-generator' ),
			description: __( 'Start your journey today with our powerful AI tools', 'wp-ai-site-generator' ),
			buttonText: __( 'Get Started Free', 'wp-ai-site-generator' ),
		},
	},
} );