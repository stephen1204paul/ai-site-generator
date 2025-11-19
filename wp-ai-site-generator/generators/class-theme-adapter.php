<?php
/**
 * Theme Adapter Class.
 *
 * Adapts generated blocks for different WordPress themes.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/generators
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Generators;

/**
 * Theme Adapter Class.
 *
 * @since      1.0.0
 */
class Theme_Adapter {

	/**
	 * Current theme.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_theme    Current theme slug.
	 */
	private $current_theme;

	/**
	 * Theme configurations.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $theme_configs    Theme-specific configurations.
	 */
	private $theme_configs = array();

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->current_theme = get_template();
		$this->initialize_theme_configs();
	}

	/**
	 * Initialize theme configurations.
	 *
	 * @since    1.0.0
	 */
	private function initialize_theme_configs() {
		$this->theme_configs = array(
			'astra' => array(
				'name'           => 'Astra',
				'container_class' => 'ast-container',
				'button_class'    => 'ast-button',
				'colors'          => array(
					'primary'   => 'var(--ast-global-color-0)',
					'secondary' => 'var(--ast-global-color-1)',
					'text'      => 'var(--ast-global-color-3)',
					'accent'    => 'var(--ast-global-color-2)',
				),
				'typography' => array(
					'heading' => 'var(--ast-heading-font-family)',
					'body'    => 'var(--ast-body-font-family)',
				),
				'spacing' => array(
					'section' => '60px',
					'content' => 'var(--ast-container-default-paddings)',
				),
				'breakpoints' => array(
					'mobile'  => '544px',
					'tablet'  => '768px',
					'desktop' => '1024px',
				),
			),
			'kadence' => array(
				'name'           => 'Kadence',
				'container_class' => 'kb-row-layout-wrap',
				'button_class'    => 'kt-button',
				'colors'          => array(
					'primary'   => 'var(--global-palette1)',
					'secondary' => 'var(--global-palette2)',
					'text'      => 'var(--global-palette3)',
					'accent'    => 'var(--global-palette4)',
				),
				'typography' => array(
					'heading' => 'var(--global-heading-font-family)',
					'body'    => 'var(--global-body-font-family)',
				),
				'spacing' => array(
					'section' => 'var(--global-kb-spacing-xxl)',
					'content' => 'var(--global-kb-spacing-lg)',
				),
				'breakpoints' => array(
					'mobile'  => '576px',
					'tablet'  => '768px',
					'desktop' => '1024px',
				),
			),
			'twentytwentyfive' => array(
				'name'           => 'Twenty Twenty-Five',
				'container_class' => 'wp-block-group alignfull',
				'button_class'    => 'wp-block-button__link',
				'colors'          => array(
					'primary'   => 'var(--wp--preset--color--primary)',
					'secondary' => 'var(--wp--preset--color--secondary)',
					'text'      => 'var(--wp--preset--color--foreground)',
					'accent'    => 'var(--wp--preset--color--accent)',
				),
				'typography' => array(
					'heading' => 'var(--wp--preset--font-family--heading)',
					'body'    => 'var(--wp--preset--font-family--body)',
				),
				'spacing' => array(
					'section' => 'var(--wp--preset--spacing--80)',
					'content' => 'var(--wp--preset--spacing--50)',
				),
				'breakpoints' => array(
					'mobile'  => '600px',
					'tablet'  => '782px',
					'desktop' => '1024px',
				),
			),
			'ollie' => array(
				'name'           => 'Ollie',
				'container_class' => 'wp-block-group alignfull',
				'button_class'    => 'wp-element-button',
				'colors'          => array(
					'primary'   => 'var(--wp--preset--color--primary)',
					'secondary' => 'var(--wp--preset--color--secondary)',
					'text'      => 'var(--wp--preset--color--base)',
					'accent'    => 'var(--wp--preset--color--tertiary)',
				),
				'typography' => array(
					'heading' => 'var(--wp--preset--font-family--primary)',
					'body'    => 'var(--wp--preset--font-family--secondary)',
				),
				'spacing' => array(
					'section' => 'var(--wp--preset--spacing--xx-large)',
					'content' => 'var(--wp--preset--spacing--large)',
				),
				'breakpoints' => array(
					'mobile'  => '600px',
					'tablet'  => '782px',
					'desktop' => '1240px',
				),
			),
			'generatepress' => array(
				'name'           => 'GeneratePress',
				'container_class' => 'grid-container',
				'button_class'    => 'button',
				'colors'          => array(
					'primary'   => 'var(--accent)',
					'secondary' => 'var(--contrast)',
					'text'      => 'var(--base-3)',
					'accent'    => 'var(--accent)',
				),
				'typography' => array(
					'heading' => 'var(--font-heading)',
					'body'    => 'var(--font-body)',
				),
				'spacing' => array(
					'section' => '60px',
					'content' => '40px',
				),
				'breakpoints' => array(
					'mobile'  => '768px',
					'tablet'  => '1024px',
					'desktop' => '1200px',
				),
			),
			'blocksy' => array(
				'name'           => 'Blocksy',
				'container_class' => 'ct-container',
				'button_class'    => 'ct-button',
				'colors'          => array(
					'primary'   => 'var(--paletteColor1)',
					'secondary' => 'var(--paletteColor2)',
					'text'      => 'var(--paletteColor3)',
					'accent'    => 'var(--paletteColor5)',
				),
				'typography' => array(
					'heading' => 'var(--headingsFontFamily)',
					'body'    => 'var(--rootFontFamily)',
				),
				'spacing' => array(
					'section' => 'var(--section-vertical-spacing)',
					'content' => '40px',
				),
				'breakpoints' => array(
					'mobile'  => '690px',
					'tablet'  => '999px',
					'desktop' => '1200px',
				),
			),
		);

		// Add default configuration
		$this->theme_configs['default'] = array(
			'name'           => 'Default',
			'container_class' => 'wp-block-group__inner-container',
			'button_class'    => 'wp-block-button__link',
			'colors'          => array(
				'primary'   => '#007cba',
				'secondary' => '#005a87',
				'text'      => '#1e1e1e',
				'accent'    => '#d63638',
			),
			'typography' => array(
				'heading' => 'inherit',
				'body'    => 'inherit',
			),
			'spacing' => array(
				'section' => '60px',
				'content' => '30px',
			),
			'breakpoints' => array(
				'mobile'  => '600px',
				'tablet'  => '782px',
				'desktop' => '1024px',
			),
		);
	}

	/**
	 * Adapt pattern for current theme.
	 *
	 * @since    1.0.0
	 * @param    array    $blocks    Block structure.
	 * @return   array               Adapted block structure.
	 */
	public function adapt_pattern( $blocks ) {
		$config = $this->get_theme_config();

		foreach ( $blocks as &$block ) {
			$block = $this->adapt_block( $block, $config );
		}

		return $blocks;
	}

	/**
	 * Adapt individual block for theme.
	 *
	 * @since    1.0.0
	 * @param    array    $block     Block data.
	 * @param    array    $config    Theme configuration.
	 * @return   array               Adapted block.
	 */
	private function adapt_block( $block, $config ) {
		// Apply theme-specific classes
		$block = $this->apply_theme_classes( $block, $config );

		// Apply theme colors
		$block = $this->apply_theme_colors( $block, $config );

		// Apply theme typography
		$block = $this->apply_theme_typography( $block, $config );

		// Apply theme spacing
		$block = $this->apply_theme_spacing( $block, $config );

		// Handle inner blocks recursively
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as &$inner_block ) {
				$inner_block = $this->adapt_block( $inner_block, $config );
			}
		}

		return $block;
	}

	/**
	 * Apply theme-specific classes to block.
	 *
	 * @since    1.0.0
	 * @param    array    $block     Block data.
	 * @param    array    $config    Theme configuration.
	 * @return   array               Block with theme classes.
	 */
	private function apply_theme_classes( $block, $config ) {
		if ( ! isset( $block['attrs'] ) ) {
			$block['attrs'] = array();
		}

		$existing_class = $block['attrs']['className'] ?? '';
		$theme_classes = array();

		// Add container class for group blocks
		if ( $block['blockName'] === 'core/group' || strpos( $block['blockName'], 'waisg/' ) === 0 ) {
			$theme_classes[] = $config['container_class'];
		}

		// Add button class for button blocks
		if ( $block['blockName'] === 'core/button' || $block['blockName'] === 'waisg/cta' ) {
			$theme_classes[] = $config['button_class'];
		}

		// Theme-specific adjustments
		switch ( $this->current_theme ) {
			case 'astra':
				if ( $block['blockName'] === 'waisg/hero' ) {
					$theme_classes[] = 'ast-full-width-layout';
				}
				break;

			case 'kadence':
				if ( strpos( $block['blockName'], 'waisg/' ) === 0 ) {
					$theme_classes[] = 'kb-section-wrap';
				}
				break;

			case 'twentytwentyfive':
			case 'ollie':
				if ( $block['blockName'] === 'core/group' ) {
					$theme_classes[] = 'has-global-padding is-layout-constrained';
				}
				break;

			case 'generatepress':
				if ( $block['blockName'] === 'waisg/hero' ) {
					$theme_classes[] = 'generate-full-width';
				}
				break;

			case 'blocksy':
				if ( strpos( $block['blockName'], 'waisg/' ) === 0 ) {
					$theme_classes[] = 'ct-section';
				}
				break;
		}

		// Merge classes
		if ( ! empty( $theme_classes ) ) {
			$all_classes = array_filter( array_merge(
				explode( ' ', $existing_class ),
				$theme_classes
			) );
			$block['attrs']['className'] = implode( ' ', array_unique( $all_classes ) );
		}

		return $block;
	}

	/**
	 * Apply theme colors to block.
	 *
	 * @since    1.0.0
	 * @param    array    $block     Block data.
	 * @param    array    $config    Theme configuration.
	 * @return   array               Block with theme colors.
	 */
	private function apply_theme_colors( $block, $config ) {
		if ( ! isset( $block['attrs']['style'] ) ) {
			$block['attrs']['style'] = array();
		}

		// Check if block supports color
		$supports_color = in_array( $block['blockName'], array(
			'core/group',
			'core/columns',
			'core/button',
			'waisg/hero',
			'waisg/features',
			'waisg/cta',
			'waisg/pricing',
		), true );

		if ( $supports_color && ! empty( $config['colors'] ) ) {
			// Apply primary color for buttons
			if ( $block['blockName'] === 'core/button' ) {
				$block['attrs']['style']['color'] = array(
					'background' => $config['colors']['primary'],
					'text'       => '#ffffff',
				);
			}

			// Apply text color
			if ( isset( $block['attrs']['textColor'] ) ) {
				$block['attrs']['style']['color']['text'] = $config['colors']['text'];
			}
		}

		return $block;
	}

	/**
	 * Apply theme typography to block.
	 *
	 * @since    1.0.0
	 * @param    array    $block     Block data.
	 * @param    array    $config    Theme configuration.
	 * @return   array               Block with theme typography.
	 */
	private function apply_theme_typography( $block, $config ) {
		$heading_blocks = array( 'core/heading', 'waisg/hero' );
		$text_blocks = array( 'core/paragraph', 'core/list' );

		if ( ! isset( $block['attrs']['style'] ) ) {
			$block['attrs']['style'] = array();
		}

		// Apply heading font family
		if ( in_array( $block['blockName'], $heading_blocks, true ) ) {
			$block['attrs']['style']['typography'] = array(
				'fontFamily' => $config['typography']['heading'],
			);
		}

		// Apply body font family
		if ( in_array( $block['blockName'], $text_blocks, true ) ) {
			$block['attrs']['style']['typography'] = array(
				'fontFamily' => $config['typography']['body'],
			);
		}

		return $block;
	}

	/**
	 * Apply theme spacing to block.
	 *
	 * @since    1.0.0
	 * @param    array    $block     Block data.
	 * @param    array    $config    Theme configuration.
	 * @return   array               Block with theme spacing.
	 */
	private function apply_theme_spacing( $block, $config ) {
		$section_blocks = array( 'core/group', 'waisg/hero', 'waisg/features', 'waisg/pricing' );

		if ( ! isset( $block['attrs']['style'] ) ) {
			$block['attrs']['style'] = array();
		}

		// Apply section spacing
		if ( in_array( $block['blockName'], $section_blocks, true ) ) {
			$block['attrs']['style']['spacing'] = array(
				'padding' => array(
					'top'    => $config['spacing']['section'],
					'bottom' => $config['spacing']['section'],
					'left'   => $config['spacing']['content'],
					'right'  => $config['spacing']['content'],
				),
			);
		}

		return $block;
	}

	/**
	 * Get theme configuration.
	 *
	 * @since    1.0.0
	 * @return   array    Theme configuration.
	 */
	public function get_theme_config() {
		// Check for child theme
		$child_theme = get_stylesheet();
		if ( $child_theme !== $this->current_theme && isset( $this->theme_configs[ $child_theme ] ) ) {
			return $this->theme_configs[ $child_theme ];
		}

		// Return parent theme config or default
		return $this->theme_configs[ $this->current_theme ] ?? $this->theme_configs['default'];
	}

	/**
	 * Get theme color palette.
	 *
	 * @since    1.0.0
	 * @return   array    Color palette.
	 */
	public function get_theme_colors() {
		$config = $this->get_theme_config();
		return $config['colors'] ?? array();
	}

	/**
	 * Get theme typography settings.
	 *
	 * @since    1.0.0
	 * @return   array    Typography settings.
	 */
	public function get_theme_typography() {
		$config = $this->get_theme_config();
		return $config['typography'] ?? array();
	}

	/**
	 * Detect active theme.
	 *
	 * @since    1.0.0
	 * @return   string    Theme identifier.
	 */
	public function detect_theme() {
		return $this->current_theme;
	}

	/**
	 * Check if theme is block-based.
	 *
	 * @since    1.0.0
	 * @return   bool    True if block theme.
	 */
	public function is_block_theme() {
		return wp_is_block_theme();
	}

	/**
	 * Get theme breakpoints.
	 *
	 * @since    1.0.0
	 * @return   array    Breakpoint values.
	 */
	public function get_breakpoints() {
		$config = $this->get_theme_config();
		return $config['breakpoints'] ?? array(
			'mobile'  => '600px',
			'tablet'  => '782px',
			'desktop' => '1024px',
		);
	}

	/**
	 * Generate responsive CSS for theme.
	 *
	 * @since    1.0.0
	 * @param    array    $styles    Styles to make responsive.
	 * @return   string              CSS string.
	 */
	public function generate_responsive_css( $styles ) {
		$css = '';
		$breakpoints = $this->get_breakpoints();

		// Desktop styles
		if ( isset( $styles['desktop'] ) ) {
			$css .= $this->compile_css( $styles['desktop'] );
		}

		// Tablet styles
		if ( isset( $styles['tablet'] ) && isset( $breakpoints['tablet'] ) ) {
			$css .= '@media (max-width: ' . $breakpoints['tablet'] . ') {';
			$css .= $this->compile_css( $styles['tablet'] );
			$css .= '}';
		}

		// Mobile styles
		if ( isset( $styles['mobile'] ) && isset( $breakpoints['mobile'] ) ) {
			$css .= '@media (max-width: ' . $breakpoints['mobile'] . ') {';
			$css .= $this->compile_css( $styles['mobile'] );
			$css .= '}';
		}

		return $css;
	}

	/**
	 * Compile CSS from array.
	 *
	 * @since    1.0.0
	 * @param    array    $styles    Style declarations.
	 * @return   string              CSS string.
	 */
	private function compile_css( $styles ) {
		$css = '';

		foreach ( $styles as $selector => $properties ) {
			$css .= $selector . '{';
			foreach ( $properties as $property => $value ) {
				$css .= $property . ':' . $value . ';';
			}
			$css .= '}';
		}

		return $css;
	}

	/**
	 * Check theme support for feature.
	 *
	 * @since    1.0.0
	 * @param    string    $feature    Feature to check.
	 * @return   bool                  True if supported.
	 */
	public function supports( $feature ) {
		$theme_supports = array(
			'astra' => array(
				'custom-colors',
				'custom-fonts',
				'responsive-embeds',
				'wide-alignment',
			),
			'kadence' => array(
				'custom-colors',
				'custom-fonts',
				'responsive-embeds',
				'wide-alignment',
				'custom-spacing',
			),
			'twentytwentyfive' => array(
				'custom-colors',
				'custom-fonts',
				'responsive-embeds',
				'wide-alignment',
				'block-templates',
			),
			'ollie' => array(
				'custom-colors',
				'custom-fonts',
				'responsive-embeds',
				'wide-alignment',
				'block-templates',
			),
		);

		$current_supports = $theme_supports[ $this->current_theme ] ?? array();
		return in_array( $feature, $current_supports, true );
	}
}