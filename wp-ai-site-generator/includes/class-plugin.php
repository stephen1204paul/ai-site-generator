<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks,
 * public-facing site hooks, and handles plugin initialization.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

use WPAISiteGenerator\Admin\Admin;
use WPAISiteGenerator\API\REST_Controller;
use WPAISiteGenerator\Database\DB_Handler;
use WPAISiteGenerator\Providers\Provider_Manager;
use WPAISiteGenerator\Generators\Block_Generator;

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The admin instance.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Admin    $admin    The admin instance.
	 */
	protected $admin;

	/**
	 * The REST API controller.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      REST_Controller    $rest_controller    The REST API controller.
	 */
	protected $rest_controller;

	/**
	 * The database handler.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      DB_Handler    $db_handler    The database handler.
	 */
	protected $db_handler;

	/**
	 * The provider manager.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Provider_Manager    $provider_manager    The provider manager.
	 */
	protected $provider_manager;

	/**
	 * The block generator.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Block_Generator    $block_generator    The block generator.
	 */
	protected $block_generator;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->version     = defined( 'WAISG_VERSION' ) ? WAISG_VERSION : '1.0.0';
		$this->plugin_name = 'wp-ai-site-generator';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_api_hooks();
		$this->initialize_components();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		/**
		 * The class responsible for orchestrating the actions and filters of the core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-loader.php';

		/**
		 * The class responsible for defining internationalization functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-admin.php';

		/**
		 * The class responsible for handling REST API endpoints.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'api/class-rest-controller.php';

		/**
		 * The class responsible for database operations.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-db-handler.php';

		/**
		 * Classes responsible for AI provider management.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'providers/interface-ai-provider.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'providers/class-base-provider.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'providers/class-provider-manager.php';

		/**
		 * The class responsible for block generation.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'generators/class-block-generator.php';

		$this->loader = new Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new I18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$this->admin = new Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $this->admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );

		// Add AJAX handlers for admin operations
		$this->loader->add_action( 'wp_ajax_waisg_generate_site', $this->admin, 'ajax_generate_site' );
		$this->loader->add_action( 'wp_ajax_waisg_get_generation_status', $this->admin, 'ajax_get_generation_status' );
		$this->loader->add_action( 'wp_ajax_waisg_cancel_generation', $this->admin, 'ajax_cancel_generation' );

		// Add admin notices
		$this->loader->add_action( 'admin_notices', $this->admin, 'display_admin_notices' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		// Register block types for the frontend
		$this->loader->add_action( 'init', $this, 'register_block_types' );

		// Add support for AI-generated content shortcodes
		$this->loader->add_action( 'init', $this, 'register_shortcodes' );

		// Enqueue block editor assets
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_assets' );
	}

	/**
	 * Register all of the hooks related to the REST API.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_api_hooks() {
		$this->rest_controller = new REST_Controller( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'rest_api_init', $this->rest_controller, 'register_routes' );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function initialize_components() {
		// Initialize database handler
		$this->db_handler = new DB_Handler();

		// Initialize provider manager
		$this->provider_manager = new Provider_Manager();

		// Initialize block generator
		$this->block_generator = new Block_Generator( $this->provider_manager );

		// Set up cron jobs for background processing
		$this->setup_cron_jobs();
	}

	/**
	 * Setup cron jobs for background processing.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function setup_cron_jobs() {
		// Schedule cleanup of old generation logs
		if ( ! wp_next_scheduled( 'waisg_cleanup_old_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'waisg_cleanup_old_logs' );
		}

		$this->loader->add_action( 'waisg_cleanup_old_logs', $this->db_handler, 'cleanup_old_logs' );

		// Schedule provider health checks
		if ( ! wp_next_scheduled( 'waisg_check_provider_health' ) ) {
			wp_schedule_event( time(), 'hourly', 'waisg_check_provider_health' );
		}

		$this->loader->add_action( 'waisg_check_provider_health', $this->provider_manager, 'check_providers_health' );
	}

	/**
	 * Register custom block types.
	 *
	 * @since    1.0.0
	 */
	public function register_block_types() {
		// Register dynamic blocks generated by AI
		register_block_type(
			'waisg/ai-content',
			array(
				'render_callback' => array( $this, 'render_ai_content_block' ),
				'attributes'      => array(
					'content'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'prompt'      => array(
						'type'    => 'string',
						'default' => '',
					),
					'generatedAt' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Render AI content block.
	 *
	 * @since    1.0.0
	 * @param    array $attributes    Block attributes.
	 * @return   string               Rendered block content.
	 */
	public function render_ai_content_block( $attributes ) {
		$content = isset( $attributes['content'] ) ? $attributes['content'] : '';
		return wp_kses_post( $content );
	}

	/**
	 * Register shortcodes.
	 *
	 * @since    1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'waisg_content', array( $this, 'render_ai_content_shortcode' ) );
	}

	/**
	 * Render AI content shortcode.
	 *
	 * @since    1.0.0
	 * @param    array $atts    Shortcode attributes.
	 * @return   string         Rendered shortcode content.
	 */
	public function render_ai_content_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => '',
				'prompt' => '',
			),
			$atts,
			'waisg_content'
		);

		// Retrieve and render AI-generated content
		if ( ! empty( $atts['id'] ) ) {
			$content = $this->db_handler->get_generated_content( $atts['id'] );
			return wp_kses_post( $content );
		}

		return '';
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_block_editor_assets() {
		$asset_file = include plugin_dir_path( dirname( __FILE__ ) ) . 'build/index.asset.php';

		wp_enqueue_script(
			'waisg-block-editor',
			plugins_url( 'build/index.js', dirname( __FILE__ ) ),
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_localize_script(
			'waisg-block-editor',
			'waisg',
			array(
				'api_url'   => rest_url( 'waisg/v1' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'providers' => $this->provider_manager->get_available_providers(),
			)
		);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Get the admin instance.
	 *
	 * @since     1.0.0
	 * @return    Admin    The admin instance.
	 */
	public function get_admin() {
		return $this->admin;
	}

	/**
	 * Get the REST controller instance.
	 *
	 * @since     1.0.0
	 * @return    REST_Controller    The REST controller instance.
	 */
	public function get_rest_controller() {
		return $this->rest_controller;
	}

	/**
	 * Get the database handler instance.
	 *
	 * @since     1.0.0
	 * @return    DB_Handler    The database handler instance.
	 */
	public function get_db_handler() {
		return $this->db_handler;
	}

	/**
	 * Get the provider manager instance.
	 *
	 * @since     1.0.0
	 * @return    Provider_Manager    The provider manager instance.
	 */
	public function get_provider_manager() {
		return $this->provider_manager;
	}

	/**
	 * Get the block generator instance.
	 *
	 * @since     1.0.0
	 * @return    Block_Generator    The block generator instance.
	 */
	public function get_block_generator() {
		return $this->block_generator;
	}
}