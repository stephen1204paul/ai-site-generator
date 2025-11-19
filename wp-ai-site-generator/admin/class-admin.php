<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Admin;

use WPAISiteGenerator\Providers\Provider_Manager;
use WPAISiteGenerator\Database\DB_Handler;

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/admin
 */
class Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Only load on our plugin pages
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		// Enqueue admin styles
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/admin.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue quality dashboard styles on quality page
		if ( $this->is_page( 'quality' ) ) {
			wp_enqueue_style(
				$this->plugin_name . '-quality-dashboard',
				plugin_dir_url( __FILE__ ) . 'css/quality-dashboard.css',
				array(),
				$this->version,
				'all'
			);
		}

		// Enqueue select2 for better select boxes
		wp_enqueue_style(
			$this->plugin_name . '-select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
			array(),
			'4.1.0',
			'all'
		);

		// Enqueue color picker styles
		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Only load on our plugin pages
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		// Enqueue WordPress packages
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_script( 'wp-i18n' );
		wp_enqueue_script( 'wp-hooks' );
		wp_enqueue_script( 'wp-date' );

		// Enqueue WordPress styles
		wp_enqueue_style( 'wp-components' );

		// Enqueue admin scripts
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			$this->version,
			false
		);

		// Enqueue select2
		wp_enqueue_script(
			$this->plugin_name . '-select2',
			'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
			array( 'jquery' ),
			'4.1.0',
			false
		);

		// Enqueue React components based on current page
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		// Chat interface component
		if ( strpos( $current_page, 'chat' ) !== false || $this->is_page( 'generate' ) ) {
			wp_enqueue_script(
				$this->plugin_name . '-chat-interface',
				plugin_dir_url( __FILE__ ) . 'js/chat-interface.jsx',
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-date' ),
				$this->version,
				true
			);
		}

		// Provider config component
		if ( $this->is_page( 'providers' ) ) {
			wp_enqueue_script(
				$this->plugin_name . '-provider-config',
				plugin_dir_url( __FILE__ ) . 'js/provider-config.jsx',
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				$this->version,
				true
			);
		}

		// Generation wizard component
		if ( $this->is_page( 'generate' ) || $this->is_page( 'wizard' ) ) {
			wp_enqueue_script(
				$this->plugin_name . '-generation-wizard',
				plugin_dir_url( __FILE__ ) . 'js/generation-wizard.jsx',
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				$this->version,
				true
			);
		}

		// Section regenerator component
		wp_enqueue_script(
			$this->plugin_name . '-section-regenerator',
			plugin_dir_url( __FILE__ ) . 'js/section-regenerator.jsx',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			$this->version,
			true
		);

		// Quality dashboard component
		if ( $this->is_page( 'quality' ) ) {
			// Load Chart.js for quality dashboard
			wp_enqueue_script(
				$this->plugin_name . '-chartjs',
				'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);

			wp_enqueue_script(
				$this->plugin_name . '-quality-dashboard',
				plugin_dir_url( __FILE__ ) . 'js/quality-dashboard.jsx',
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-date', $this->plugin_name . '-chartjs' ),
				$this->version,
				true
			);
		}

		// Knowledge Base component
		if ( $this->is_page( 'knowledge-base' ) ) {
			wp_enqueue_script(
				$this->plugin_name . '-knowledge-base',
				plugin_dir_url( __FILE__ ) . 'js/knowledge-base.js',
				array( 'jquery', 'wp-api' ),
				$this->version,
				true
			);

			// Also enqueue React UI if needed
			wp_enqueue_script(
				$this->plugin_name . '-knowledge-base-ui',
				plugin_dir_url( __FILE__ ) . 'js/knowledge-base-ui.jsx',
				array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				$this->version,
				true
			);

			// Enable media uploader for file handling
			wp_enqueue_media();
		}

		// Localize script with necessary data
		wp_localize_script(
			$this->plugin_name,
			'waisg_admin',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'waisg_admin_nonce' ),
				'rest_url'         => rest_url( 'waisg/v1/' ),
				'rest_nonce'       => wp_create_nonce( 'wp_rest' ),
				'plugin_url'       => WAISG_PLUGIN_URL,
				'admin_url'        => admin_url(),
				'site_url'         => site_url(),
				'strings'          => $this->get_js_strings(),
				'settings'         => get_option( 'waisg_settings', array() ),
				'features'         => get_option( 'waisg_features', array() ),
				'provider_settings'=> get_option( 'waisg_provider_settings', array() ),
				'generation_defaults'=> get_option( 'waisg_generation_defaults', array() ),
				'current_user'     => array(
					'id'    => get_current_user_id(),
					'name'  => wp_get_current_user()->display_name,
					'email' => wp_get_current_user()->user_email,
				),
			)
		);

		// Also localize for React components
		$components = array( 'chat-interface', 'provider-config', 'generation-wizard', 'section-regenerator', 'quality-dashboard' );
		foreach ( $components as $component ) {
			if ( wp_script_is( $this->plugin_name . '-' . $component, 'enqueued' ) ) {
				wp_localize_script(
					$this->plugin_name . '-' . $component,
					'waisgAdmin',
					array(
						'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
						'nonce'     => wp_create_nonce( 'waisg_admin_nonce' ),
						'restUrl'   => rest_url( 'waisg/v1/' ),
						'restNonce' => wp_create_nonce( 'wp_rest' ),
						'pluginUrl' => WAISG_PLUGIN_URL,
						'adminUrl'  => admin_url(),
						'siteUrl'   => site_url(),
					)
				);
			}
		}

		// Add media uploader if needed
		if ( $this->is_page( 'templates' ) || $this->is_page( 'settings' ) ) {
			wp_enqueue_media();
		}

		// Add inline script to initialize React components
		$this->add_react_init_script();
	}

	/**
	 * Add inline script to initialize React components.
	 *
	 * @since    1.0.0
	 */
	private function add_react_init_script() {
		$script = "
		document.addEventListener('DOMContentLoaded', function() {
			// Initialize Chat Interface
			if (document.getElementById('waisg-chat-react-root')) {
				if (window.wp && window.wp.element && window.ChatInterface) {
					const { render } = window.wp.element;
					render(
						window.wp.element.createElement(window.ChatInterface),
						document.getElementById('waisg-chat-react-root')
					);
				}
			}

			// Initialize Provider Config
			if (document.getElementById('waisg-provider-config-root')) {
				if (window.wp && window.wp.element && window.ProviderConfig) {
					const { render } = window.wp.element;
					render(
						window.wp.element.createElement(window.ProviderConfig),
						document.getElementById('waisg-provider-config-root')
					);
				}
			}

			// Initialize Generation Wizard
			if (document.getElementById('waisg-generation-wizard-root')) {
				if (window.wp && window.wp.element && window.GenerationWizard) {
					const { render } = window.wp.element;
					render(
						window.wp.element.createElement(window.GenerationWizard),
						document.getElementById('waisg-generation-wizard-root')
					);
				}
			}

			// Initialize Section Regenerator
			if (document.querySelectorAll('.waisg-section-regenerator-root').length > 0) {
				if (window.wp && window.wp.element && window.SectionRegenerator) {
					const { render } = window.wp.element;
					document.querySelectorAll('.waisg-section-regenerator-root').forEach(function(element) {
						const sectionId = element.dataset.sectionId;
						const sectionContent = element.dataset.sectionContent;
						const pageId = element.dataset.pageId;
						render(
							window.wp.element.createElement(window.SectionRegenerator, {
								sectionId: sectionId,
								sectionContent: sectionContent,
								pageId: pageId
							}),
							element
						);
					});
				}
			}
		});
		";

		wp_add_inline_script( $this->plugin_name, $script );
	}

	/**
	 * Add plugin admin menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		// Main menu item
		add_menu_page(
			__( 'AI Site Generator', 'wp-ai-site-generator' ),
			__( 'AI Site Generator', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_dashboard_page' ),
			'dashicons-layout',
			30
		);

		// Dashboard submenu (same as main)
		add_submenu_page(
			$this->plugin_name,
			__( 'Dashboard', 'wp-ai-site-generator' ),
			__( 'Dashboard', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_dashboard_page' )
		);

		// Generate Site submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Generate Site', 'wp-ai-site-generator' ),
			__( 'Generate Site', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-generate',
			array( $this, 'display_generate_page' )
		);

		// Chat Interface submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'AI Chat Assistant', 'wp-ai-site-generator' ),
			__( 'Chat Assistant', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-chat',
			array( $this, 'display_chat_page' )
		);

		// History submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Generation History', 'wp-ai-site-generator' ),
			__( 'History', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-history',
			array( $this, 'display_history_page' )
		);

		// Templates submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Templates', 'wp-ai-site-generator' ),
			__( 'Templates', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-templates',
			array( $this, 'display_templates_page' )
		);

		// Providers submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'AI Providers', 'wp-ai-site-generator' ),
			__( 'Providers', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-providers',
			array( $this, 'display_providers_page' )
		);

		// Settings submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Settings', 'wp-ai-site-generator' ),
			__( 'Settings', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-settings',
			array( $this, 'display_settings_page' )
		);

		// Usage & Analytics submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Usage & Analytics', 'wp-ai-site-generator' ),
			__( 'Usage', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-usage',
			array( $this, 'display_usage_page' )
		);

		// Quality Dashboard submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Quality Dashboard', 'wp-ai-site-generator' ),
			__( 'Quality', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-quality',
			array( $this, 'display_quality_dashboard_page' )
		);

		// Knowledge Base submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Knowledge Base', 'wp-ai-site-generator' ),
			__( 'Knowledge Base', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-knowledge-base',
			array( $this, 'display_knowledge_base_page' )
		);

		// Help submenu
		add_submenu_page(
			$this->plugin_name,
			__( 'Help & Documentation', 'wp-ai-site-generator' ),
			__( 'Help', 'wp-ai-site-generator' ),
			'manage_options',
			$this->plugin_name . '-help',
			array( $this, 'display_help_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		// Register settings
		register_setting( 'waisg_settings_group', 'waisg_settings' );
		register_setting( 'waisg_provider_settings_group', 'waisg_provider_settings' );
		register_setting( 'waisg_generation_defaults_group', 'waisg_generation_defaults' );
		register_setting( 'waisg_features_group', 'waisg_features' );
		register_setting( 'waisg_usage_limits_group', 'waisg_usage_limits' );
	}

	/**
	 * Display dashboard page.
	 *
	 * @since    1.0.0
	 */
	public function display_dashboard_page() {
		include_once 'partials/admin-dashboard-display.php';
	}

	/**
	 * Display generate page.
	 *
	 * @since    1.0.0
	 */
	public function display_generate_page() {
		include_once 'partials/admin-generate-display.php';
	}

	/**
	 * Display chat page.
	 *
	 * @since    1.0.0
	 */
	public function display_chat_page() {
		include_once 'partials/chat-interface.php';
	}

	/**
	 * Display history page.
	 *
	 * @since    1.0.0
	 */
	public function display_history_page() {
		include_once 'partials/generation-history.php';
	}

	/**
	 * Display templates page.
	 *
	 * @since    1.0.0
	 */
	public function display_templates_page() {
		include_once 'partials/templates-library.php';
	}

	/**
	 * Display providers page.
	 *
	 * @since    1.0.0
	 */
	public function display_providers_page() {
		include_once 'partials/provider-settings.php';
	}

	/**
	 * Display settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		include_once 'partials/settings-page.php';
	}

	/**
	 * Display usage page.
	 *
	 * @since    1.0.0
	 */
	public function display_usage_page() {
		include_once 'partials/admin-usage-display.php';
	}

	/**
	 * Display quality dashboard page.
	 *
	 * @since    1.0.0
	 */
	public function display_quality_dashboard_page() {
		include_once 'partials/quality-dashboard.php';
	}

	/**
	 * Display help page.
	 *
	 * @since    1.0.0
	 */
	public function display_help_page() {
		include_once 'partials/admin-help-display.php';
	}

	/**
	 * Display knowledge base page.
	 *
	 * @since    1.0.0
	 */
	public function display_knowledge_base_page() {
		include_once 'partials/knowledge-base-page.php';
	}

	/**
	 * AJAX handler for site generation.
	 *
	 * @since    1.0.0
	 */
	public function ajax_generate_site() {
		// Verify nonce
		if ( ! check_ajax_referer( 'waisg_admin_nonce', 'nonce', false ) ) {
			wp_die( __( 'Security check failed', 'wp-ai-site-generator' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'wp-ai-site-generator' ) );
		}

		// Get and validate input
		$prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$options = isset( $_POST['options'] ) ? json_decode( wp_unslash( $_POST['options'] ), true ) : array();

		if ( empty( $prompt ) ) {
			wp_send_json_error( __( 'Prompt is required', 'wp-ai-site-generator' ) );
		}

		// Start generation process
		try {
			$provider_manager = new Provider_Manager();
			$db_handler = new DB_Handler();

			// Create generation record
			$generation_id = $db_handler->create_generation( array(
				'user_id'  => get_current_user_id(),
				'prompt'   => $prompt,
				'provider' => $provider,
				'status'   => 'processing',
			) );

			// Start async generation
			$this->start_async_generation( $generation_id, $prompt, $provider, $options );

			wp_send_json_success( array(
				'generation_id' => $generation_id,
				'message'       => __( 'Generation started successfully', 'wp-ai-site-generator' ),
			) );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for getting generation status.
	 *
	 * @since    1.0.0
	 */
	public function ajax_get_generation_status() {
		// Verify nonce
		if ( ! check_ajax_referer( 'waisg_admin_nonce', 'nonce', false ) ) {
			wp_die( __( 'Security check failed', 'wp-ai-site-generator' ) );
		}

		$generation_id = isset( $_GET['generation_id'] ) ? intval( $_GET['generation_id'] ) : 0;

		if ( ! $generation_id ) {
			wp_send_json_error( __( 'Invalid generation ID', 'wp-ai-site-generator' ) );
		}

		try {
			$db_handler = new DB_Handler();
			$generation = $db_handler->get_generation( $generation_id );

			if ( ! $generation ) {
				wp_send_json_error( __( 'Generation not found', 'wp-ai-site-generator' ) );
			}

			wp_send_json_success( $generation );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for cancelling generation.
	 *
	 * @since    1.0.0
	 */
	public function ajax_cancel_generation() {
		// Verify nonce
		if ( ! check_ajax_referer( 'waisg_admin_nonce', 'nonce', false ) ) {
			wp_die( __( 'Security check failed', 'wp-ai-site-generator' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'wp-ai-site-generator' ) );
		}

		$generation_id = isset( $_POST['generation_id'] ) ? intval( $_POST['generation_id'] ) : 0;

		if ( ! $generation_id ) {
			wp_send_json_error( __( 'Invalid generation ID', 'wp-ai-site-generator' ) );
		}

		try {
			$db_handler = new DB_Handler();
			$db_handler->update_generation( $generation_id, array(
				'status' => 'cancelled',
			) );

			wp_send_json_success( __( 'Generation cancelled successfully', 'wp-ai-site-generator' ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @since    1.0.0
	 */
	public function display_admin_notices() {
		// Only show on our plugin pages
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		// Check for missing API keys
		$provider_settings = get_option( 'waisg_provider_settings', array() );
		$has_active_provider = false;

		if ( isset( $provider_settings['providers'] ) ) {
			foreach ( $provider_settings['providers'] as $provider => $config ) {
				if ( ! empty( $config['enabled'] ) && ! empty( $config['api_key'] ) ) {
					$has_active_provider = true;
					break;
				}
			}
		}

		if ( ! $has_active_provider ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %s: Link to providers settings page */
						__( 'No AI providers configured. Please <a href="%s">configure at least one provider</a> to start generating sites.', 'wp-ai-site-generator' ),
						esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '-providers' ) )
					);
					?>
				</p>
			</div>
			<?php
		}

		// Check for pending updates
		if ( get_transient( 'waisg_update_available' ) ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<?php esc_html_e( 'A new version of WP AI Site Generator is available. Please update to get the latest features and improvements.', 'wp-ai-site-generator' ); ?>
				</p>
			</div>
			<?php
		}

		// Display any transient notices
		$notice = get_transient( 'waisg_admin_notice' );
		if ( $notice ) {
			$type = isset( $notice['type'] ) ? $notice['type'] : 'info';
			$message = isset( $notice['message'] ) ? $notice['message'] : '';
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php
			delete_transient( 'waisg_admin_notice' );
		}
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @since    1.0.0
	 * @return   bool    True if on plugin page.
	 */
	private function is_plugin_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return strpos( $page, $this->plugin_name ) === 0;
	}

	/**
	 * Check if on specific plugin page.
	 *
	 * @since    1.0.0
	 * @param    string    $page_suffix    Page suffix to check.
	 * @return   bool                      True if on specified page.
	 */
	private function is_page( $page_suffix ) {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		return $page === $this->plugin_name . '-' . $page_suffix;
	}

	/**
	 * AJAX handler for exporting settings.
	 *
	 * @since    1.0.0
	 */
	public function ajax_export_settings() {
		// Verify nonce
		if ( ! check_ajax_referer( 'waisg_admin_nonce', 'nonce', false ) ) {
			wp_die( __( 'Security check failed', 'wp-ai-site-generator' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'wp-ai-site-generator' ) );
		}

		// Gather all settings
		$settings = array(
			'general'           => get_option( 'waisg_settings', array() ),
			'features'          => get_option( 'waisg_features', array() ),
			'generation_defaults'=> get_option( 'waisg_generation_defaults', array() ),
			'usage_limits'      => get_option( 'waisg_usage_limits', array() ),
			'provider_settings' => get_option( 'waisg_provider_settings', array() ),
			'export_date'       => current_time( 'mysql' ),
			'export_version'    => $this->version,
		);

		// Remove sensitive data
		if ( isset( $settings['provider_settings']['providers'] ) ) {
			foreach ( $settings['provider_settings']['providers'] as $provider => &$config ) {
				if ( isset( $config['api_key'] ) ) {
					// Mask API keys but keep first and last 4 characters
					$key_length = strlen( $config['api_key'] );
					if ( $key_length > 8 ) {
						$config['api_key'] = substr( $config['api_key'], 0, 4 ) . str_repeat( '*', $key_length - 8 ) . substr( $config['api_key'], -4 );
					} else {
						$config['api_key'] = str_repeat( '*', $key_length );
					}
				}
			}
		}

		wp_send_json_success( $settings );
	}

	/**
	 * AJAX handler for importing settings.
	 *
	 * @since    1.0.0
	 */
	public function ajax_import_settings() {
		// Verify nonce
		if ( ! check_ajax_referer( 'waisg_admin_nonce', 'nonce', false ) ) {
			wp_die( __( 'Security check failed', 'wp-ai-site-generator' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action', 'wp-ai-site-generator' ) );
		}

		// Get and validate settings
		$settings_json = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';

		if ( empty( $settings_json ) ) {
			wp_send_json_error( __( 'No settings provided', 'wp-ai-site-generator' ) );
		}

		$settings = json_decode( $settings_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( __( 'Invalid settings format', 'wp-ai-site-generator' ) );
		}

		// Update settings (excluding provider settings with masked API keys)
		if ( isset( $settings['general'] ) ) {
			update_option( 'waisg_settings', $settings['general'] );
		}

		if ( isset( $settings['features'] ) ) {
			update_option( 'waisg_features', $settings['features'] );
		}

		if ( isset( $settings['generation_defaults'] ) ) {
			update_option( 'waisg_generation_defaults', $settings['generation_defaults'] );
		}

		if ( isset( $settings['usage_limits'] ) ) {
			update_option( 'waisg_usage_limits', $settings['usage_limits'] );
		}

		// Handle provider settings specially
		if ( isset( $settings['provider_settings'] ) ) {
			$current_provider_settings = get_option( 'waisg_provider_settings', array() );

			// Preserve existing API keys if imported ones are masked
			if ( isset( $settings['provider_settings']['providers'] ) ) {
				foreach ( $settings['provider_settings']['providers'] as $provider => &$config ) {
					if ( isset( $config['api_key'] ) && strpos( $config['api_key'], '*' ) !== false ) {
						// Keep existing API key if the imported one is masked
						if ( isset( $current_provider_settings['providers'][$provider]['api_key'] ) ) {
							$config['api_key'] = $current_provider_settings['providers'][$provider]['api_key'];
						} else {
							unset( $config['api_key'] );
						}
					}
				}
			}

			update_option( 'waisg_provider_settings', $settings['provider_settings'] );
		}

		wp_send_json_success( __( 'Settings imported successfully', 'wp-ai-site-generator' ) );
	}

	/**
	 * Get JavaScript strings for localization.
	 *
	 * @since    1.0.0
	 * @return   array    Localized strings.
	 */
	private function get_js_strings() {
		return array(
			'confirm_delete'   => __( 'Are you sure you want to delete this?', 'wp-ai-site-generator' ),
			'confirm_cancel'   => __( 'Are you sure you want to cancel the generation?', 'wp-ai-site-generator' ),
			'generating'       => __( 'Generating...', 'wp-ai-site-generator' ),
			'error'            => __( 'An error occurred', 'wp-ai-site-generator' ),
			'success'          => __( 'Success!', 'wp-ai-site-generator' ),
			'processing'       => __( 'Processing...', 'wp-ai-site-generator' ),
			'please_wait'      => __( 'Please wait...', 'wp-ai-site-generator' ),
		);
	}

	/**
	 * Start async generation process.
	 *
	 * @since    1.0.0
	 * @param    int       $generation_id    Generation ID.
	 * @param    string    $prompt           Generation prompt.
	 * @param    string    $provider         AI provider.
	 * @param    array     $options          Generation options.
	 */
	private function start_async_generation( $generation_id, $prompt, $provider, $options = array() ) {
		// Schedule immediate background task
		wp_schedule_single_event( time(), 'waisg_process_generation', array( $generation_id, $prompt, $provider, $options ) );

		// Trigger cron if not running
		spawn_cron();
	}
}