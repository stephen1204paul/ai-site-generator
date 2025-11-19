/**
 * Main Blocks Registration File
 *
 * @package WPAISiteGenerator
 */

import { registerBlockCollection } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

// Import all blocks
import './hero';
import './features';
import './testimonials';
import './cta';
import './team';
import './faq';
import './pricing';
import './contact';

// Register block collection
registerBlockCollection( 'waisg', {
	title: __( 'AI Site Generator', 'wp-ai-site-generator' ),
	icon: 'admin-site-alt3',
} );

// Export block configurations
export { default as HeroBlock } from './hero';
export { default as FeaturesBlock } from './features';
export { default as TestimonialsBlock } from './testimonials';
export { default as CTABlock } from './cta';
export { default as TeamBlock } from './team';
export { default as FAQBlock } from './faq';
export { default as PricingBlock } from './pricing';
export { default as ContactBlock } from './contact';