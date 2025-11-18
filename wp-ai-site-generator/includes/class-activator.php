<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Sets up database tables, default options, and performs initial checks.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Check PHP version
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( basename( dirname( dirname( __FILE__ ) ) ) . '/wp-ai-site-generator.php' );
			wp_die(
				esc_html__( 'WP AI Site Generator requires PHP 8.0 or higher.', 'wp-ai-site-generator' ),
				esc_html__( 'Plugin Activation Error', 'wp-ai-site-generator' ),
				array( 'response' => 200, 'back_link' => true )
			);
		}

		// Check WordPress version
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			deactivate_plugins( basename( dirname( dirname( __FILE__ ) ) ) . '/wp-ai-site-generator.php' );
			wp_die(
				esc_html__( 'WP AI Site Generator requires WordPress 6.0 or higher.', 'wp-ai-site-generator' ),
				esc_html__( 'Plugin Activation Error', 'wp-ai-site-generator' ),
				array( 'response' => 200, 'back_link' => true )
			);
		}

		// Create database tables
		self::create_database_tables();

		// Set default options
		self::set_default_options();

		// Create necessary directories
		self::create_plugin_directories();

		// Schedule cron events
		self::schedule_cron_events();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log activation
		self::log_activation();
	}

	/**
	 * Create database tables using migration system.
	 *
	 * @since    1.0.0
	 */
	private static function create_database_tables() {
		// Use the migration manager to handle database setup
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'database/class-migration-manager.php';

		$migration_manager = new \WPAISiteGenerator\Database\Migration_Manager();

		// Run all migrations
		$result = $migration_manager->run();

		// Log migration results
		if ( ! $result['success'] ) {
			// Log errors if in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				foreach ( $result['errors'] as $error ) {
					error_log( 'WP AI Site Generator Migration Error: ' . wp_json_encode( $error ) );
				}
			}

			// If migrations failed, try to create the legacy tables as fallback
			self::create_legacy_tables();
		} else {
			// Log successful migrations if in debug mode
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $result['executed'] ) ) {
				error_log( 'WP AI Site Generator Migrations Executed: ' . wp_json_encode( $result['executed'] ) );
			}
		}
	}

	/**
	 * Create legacy tables as fallback.
	 *
	 * This method creates the original tables directly if migrations fail.
	 * This ensures backward compatibility and prevents activation failures.
	 *
	 * @since    1.0.0
	 */
	private static function create_legacy_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Generations table for tracking site generation history
		$generations_table = $wpdb->prefix . 'waisg_generations';
		$sql_generations = "CREATE TABLE IF NOT EXISTS $generations_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			prompt text NOT NULL,
			provider varchar(50) NOT NULL,
			model varchar(100) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			progress int(3) NOT NULL DEFAULT 0,
			result longtext DEFAULT NULL,
			error_message text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		// Generated content table for storing AI-generated blocks
		$content_table = $wpdb->prefix . 'waisg_generated_content';
		$sql_content = "CREATE TABLE IF NOT EXISTS $content_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			generation_id bigint(20) unsigned DEFAULT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			block_type varchar(100) NOT NULL,
			block_content longtext NOT NULL,
			prompt text DEFAULT NULL,
			provider varchar(50) DEFAULT NULL,
			model varchar(100) DEFAULT NULL,
			tokens_used int(11) DEFAULT NULL,
			cost decimal(10,6) DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY generation_id (generation_id),
			KEY post_id (post_id),
			KEY block_type (block_type),
			KEY created_at (created_at)
		) $charset_collate;";

		// Provider usage table for tracking API usage and costs
		$usage_table = $wpdb->prefix . 'waisg_provider_usage';
		$sql_usage = "CREATE TABLE IF NOT EXISTS $usage_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(50) NOT NULL,
			model varchar(100) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			tokens_input int(11) DEFAULT 0,
			tokens_output int(11) DEFAULT 0,
			tokens_total int(11) DEFAULT 0,
			cost decimal(10,6) DEFAULT 0.000000,
			request_type varchar(50) DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY provider (provider),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Templates table for storing reusable AI prompts and configurations
		$templates_table = $wpdb->prefix . 'waisg_templates';
		$sql_templates = "CREATE TABLE IF NOT EXISTS $templates_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			description text DEFAULT NULL,
			prompt_template longtext NOT NULL,
			configuration longtext DEFAULT NULL,
			category varchar(100) DEFAULT 'general',
			is_active tinyint(1) DEFAULT 1,
			usage_count int(11) DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY category (category),
			KEY is_active (is_active)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_generations );
		dbDelta( $sql_content );
		dbDelta( $sql_usage );
		dbDelta( $sql_templates );

		// Store database version
		update_option( 'waisg_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since    1.0.0
	 */
	private static function set_default_options() {
		// General settings
		add_option( 'waisg_settings', array(
			'enabled'                => true,
			'debug_mode'             => false,
			'log_level'              => 'error',
			'max_generation_time'    => 300, // 5 minutes
			'enable_caching'         => true,
			'cache_ttl'              => 3600, // 1 hour
			'rate_limiting'          => true,
			'requests_per_minute'    => 10,
			'default_language'       => 'en_US',
			'enable_multilingual'    => false,
			'auto_save_drafts'       => true,
			'enable_revision_history'=> true,
			'max_revisions'          => 10,
		) );

		// Provider settings
		add_option( 'waisg_provider_settings', array(
			'default_provider' => 'openai',
			'fallback_enabled' => true,
			'providers'        => array(
				'openai' => array(
					'enabled'     => false,
					'api_key'     => '',
					'model'       => 'gpt-4-turbo-preview',
					'temperature' => 0.7,
					'max_tokens'  => 4000,
				),
				'anthropic' => array(
					'enabled'     => false,
					'api_key'     => '',
					'model'       => 'claude-3-sonnet-20240229',
					'temperature' => 0.7,
					'max_tokens'  => 4000,
				),
				'google' => array(
					'enabled'     => false,
					'api_key'     => '',
					'model'       => 'gemini-pro',
					'temperature' => 0.7,
					'max_tokens'  => 4000,
				),
			),
		) );

		// Generation defaults
		add_option( 'waisg_generation_defaults', array(
			'site_structure' => array(
				'homepage'      => true,
				'about_page'    => true,
				'services_page' => true,
				'contact_page'  => true,
				'blog_page'     => false,
			),
			'content_settings' => array(
				'tone'           => 'professional',
				'style'          => 'modern',
				'word_count_min' => 300,
				'word_count_max' => 1500,
				'include_images' => true,
				'include_cta'    => true,
			),
			'seo_settings' => array(
				'generate_meta'     => true,
				'generate_schema'   => true,
				'keyword_density'   => 2,
				'internal_linking'  => true,
			),
		) );

		// Feature flags
		add_option( 'waisg_features', array(
			'conversational_ui'     => true,
			'batch_generation'      => true,
			'template_library'      => true,
			'content_optimization'  => true,
			'a_b_testing'           => false,
			'analytics_integration' => true,
			'export_import'         => true,
		) );

		// Usage limits
		add_option( 'waisg_usage_limits', array(
			'daily_generation_limit'   => 100,
			'monthly_generation_limit' => 3000,
			'max_tokens_per_request'   => 8000,
			'max_concurrent_requests'  => 3,
		) );
	}

	/**
	 * Create necessary plugin directories.
	 *
	 * @since    1.0.0
	 */
	private static function create_plugin_directories() {
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/wp-ai-site-generator';

		// Create main upload directory
		if ( ! file_exists( $plugin_upload_dir ) ) {
			wp_mkdir_p( $plugin_upload_dir );
		}

		// Create subdirectories
		$subdirs = array(
			'cache',
			'logs',
			'exports',
			'templates',
			'temp',
		);

		foreach ( $subdirs as $subdir ) {
			$dir_path = $plugin_upload_dir . '/' . $subdir;
			if ( ! file_exists( $dir_path ) ) {
				wp_mkdir_p( $dir_path );
			}

			// Add .htaccess for security (except templates)
			if ( $subdir !== 'templates' ) {
				$htaccess_content = "Order Deny,Allow\nDeny from all";
				file_put_contents( $dir_path . '/.htaccess', $htaccess_content );
			}
		}

		// Add index.php files for additional security
		$index_content = '<?php // Silence is golden';
		file_put_contents( $plugin_upload_dir . '/index.php', $index_content );

		foreach ( $subdirs as $subdir ) {
			file_put_contents( $plugin_upload_dir . '/' . $subdir . '/index.php', $index_content );
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @since    1.0.0
	 */
	private static function schedule_cron_events() {
		// Schedule daily cleanup
		if ( ! wp_next_scheduled( 'waisg_daily_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'waisg_daily_cleanup' );
		}

		// Schedule hourly usage sync
		if ( ! wp_next_scheduled( 'waisg_sync_usage' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'waisg_sync_usage' );
		}

		// Schedule weekly reports
		if ( ! wp_next_scheduled( 'waisg_weekly_report' ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', 'waisg_weekly_report' );
		}
	}

	/**
	 * Log plugin activation.
	 *
	 * @since    1.0.0
	 */
	private static function log_activation() {
		$activation_data = array(
			'version'      => defined( 'WAISG_VERSION' ) ? WAISG_VERSION : '1.0.0',
			'php_version'  => PHP_VERSION,
			'wp_version'   => get_bloginfo( 'version' ),
			'activated_at' => current_time( 'mysql' ),
			'activated_by' => get_current_user_id(),
		);

		update_option( 'waisg_activation_data', $activation_data );

		// Log to file if debug mode is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WP AI Site Generator activated: ' . wp_json_encode( $activation_data ) );
		}
	}
}