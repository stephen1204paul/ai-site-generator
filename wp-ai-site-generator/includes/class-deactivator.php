<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Includes;

/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/includes
 */
class Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Cleans up scheduled events and performs necessary cleanup tasks.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Clear scheduled cron events
		self::clear_scheduled_events();

		// Clean up temporary files
		self::cleanup_temp_files();

		// Clear plugin caches
		self::clear_plugin_caches();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log deactivation
		self::log_deactivation();
	}

	/**
	 * Clear all scheduled cron events.
	 *
	 * @since    1.0.0
	 */
	private static function clear_scheduled_events() {
		// List of all plugin cron hooks
		$cron_hooks = array(
			'waisg_daily_cleanup',
			'waisg_sync_usage',
			'waisg_weekly_report',
			'waisg_cleanup_old_logs',
			'waisg_check_provider_health',
		);

		// Clear each scheduled event
		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}

			// Clear all scheduled occurrences of the hook
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Clean up temporary files.
	 *
	 * @since    1.0.0
	 */
	private static function cleanup_temp_files() {
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/wp-ai-site-generator/temp';

		if ( file_exists( $temp_dir ) && is_dir( $temp_dir ) ) {
			// Get all files in temp directory
			$files = glob( $temp_dir . '/*' );

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// Only delete files older than 1 hour
					if ( time() - filemtime( $file ) > 3600 ) {
						wp_delete_file( $file );
					}
				}
			}
		}

		// Clean up old log files (keep last 7 days)
		$logs_dir = $upload_dir['basedir'] . '/wp-ai-site-generator/logs';

		if ( file_exists( $logs_dir ) && is_dir( $logs_dir ) ) {
			$log_files = glob( $logs_dir . '/*.log' );

			foreach ( $log_files as $log_file ) {
				if ( is_file( $log_file ) ) {
					// Delete logs older than 7 days
					if ( time() - filemtime( $log_file ) > 7 * DAY_IN_SECONDS ) {
						wp_delete_file( $log_file );
					}
				}
			}
		}
	}

	/**
	 * Clear all plugin caches.
	 *
	 * @since    1.0.0
	 */
	private static function clear_plugin_caches() {
		// Clear transients
		self::clear_plugin_transients();

		// Clear object cache for plugin data
		wp_cache_delete( 'waisg_provider_status', 'waisg' );
		wp_cache_delete( 'waisg_generation_queue', 'waisg' );
		wp_cache_delete( 'waisg_templates', 'waisg' );
		wp_cache_delete( 'waisg_usage_stats', 'waisg' );

		// Clear file-based cache
		$upload_dir = wp_upload_dir();
		$cache_dir = $upload_dir['basedir'] . '/wp-ai-site-generator/cache';

		if ( file_exists( $cache_dir ) && is_dir( $cache_dir ) ) {
			$cache_files = glob( $cache_dir . '/*' );

			foreach ( $cache_files as $cache_file ) {
				if ( is_file( $cache_file ) ) {
					wp_delete_file( $cache_file );
				}
			}
		}
	}

	/**
	 * Clear plugin-specific transients.
	 *
	 * @since    1.0.0
	 */
	private static function clear_plugin_transients() {
		global $wpdb;

		// Delete all transients with our prefix
		$plugin_prefix = 'waisg_';

		// Delete transients
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $plugin_prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $plugin_prefix ) . '%'
			)
		);

		// Delete site transients for multisite
		if ( is_multisite() ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta}
					WHERE meta_key LIKE %s
					OR meta_key LIKE %s",
					$wpdb->esc_like( '_site_transient_' . $plugin_prefix ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_' . $plugin_prefix ) . '%'
				)
			);
		}
	}

	/**
	 * Log plugin deactivation.
	 *
	 * @since    1.0.0
	 */
	private static function log_deactivation() {
		// Get current usage statistics before deactivation
		$usage_stats = self::get_usage_statistics();

		$deactivation_data = array(
			'version'        => defined( 'WAISG_VERSION' ) ? WAISG_VERSION : '1.0.0',
			'deactivated_at' => current_time( 'mysql' ),
			'deactivated_by' => get_current_user_id(),
			'usage_stats'    => $usage_stats,
		);

		// Store deactivation data
		update_option( 'waisg_deactivation_data', $deactivation_data );

		// Log to file if debug mode is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WP AI Site Generator deactivated: ' . wp_json_encode( $deactivation_data ) );
		}
	}

	/**
	 * Get usage statistics for logging.
	 *
	 * @since    1.0.0
	 * @return   array    Usage statistics.
	 */
	private static function get_usage_statistics() {
		global $wpdb;

		$stats = array(
			'total_generations' => 0,
			'total_content_blocks' => 0,
			'total_api_calls' => 0,
			'active_days' => 0,
		);

		// Get total generations
		$generations_table = $wpdb->prefix . 'waisg_generations';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$generations_table'" ) == $generations_table ) {
			$stats['total_generations'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM $generations_table"
			);
		}

		// Get total content blocks
		$content_table = $wpdb->prefix . 'waisg_generated_content';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$content_table'" ) == $content_table ) {
			$stats['total_content_blocks'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM $content_table"
			);
		}

		// Get total API calls
		$usage_table = $wpdb->prefix . 'waisg_provider_usage';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$usage_table'" ) == $usage_table ) {
			$stats['total_api_calls'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM $usage_table"
			);
		}

		// Calculate active days
		$activation_data = get_option( 'waisg_activation_data' );
		if ( $activation_data && isset( $activation_data['activated_at'] ) ) {
			$activated = strtotime( $activation_data['activated_at'] );
			$now = current_time( 'timestamp' );
			$stats['active_days'] = max( 1, floor( ( $now - $activated ) / DAY_IN_SECONDS ) );
		}

		return $stats;
	}

	/**
	 * Optional: Offer to delete plugin data.
	 *
	 * This method is not called automatically. It can be triggered
	 * through a settings option if the user wants to completely
	 * remove plugin data.
	 *
	 * @since    1.0.0
	 */
	public static function uninstall() {
		// Check if user opted to delete data on uninstall
		$settings = get_option( 'waisg_settings' );
		if ( ! isset( $settings['delete_on_uninstall'] ) || ! $settings['delete_on_uninstall'] ) {
			return;
		}

		// Delete database tables
		self::delete_database_tables();

		// Delete all plugin options
		self::delete_plugin_options();

		// Delete upload directory and all files
		self::delete_upload_directory();
	}

	/**
	 * Delete plugin database tables.
	 *
	 * @since    1.0.0
	 */
	private static function delete_database_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'waisg_generations',
			$wpdb->prefix . 'waisg_generated_content',
			$wpdb->prefix . 'waisg_provider_usage',
			$wpdb->prefix . 'waisg_templates',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}

	/**
	 * Delete all plugin options.
	 *
	 * @since    1.0.0
	 */
	private static function delete_plugin_options() {
		$options = array(
			'waisg_db_version',
			'waisg_settings',
			'waisg_provider_settings',
			'waisg_generation_defaults',
			'waisg_features',
			'waisg_usage_limits',
			'waisg_activation_data',
			'waisg_deactivation_data',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Delete any additional options with our prefix
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE 'waisg_%'"
		);
	}

	/**
	 * Delete plugin upload directory.
	 *
	 * @since    1.0.0
	 */
	private static function delete_upload_directory() {
		$upload_dir = wp_upload_dir();
		$plugin_upload_dir = $upload_dir['basedir'] . '/wp-ai-site-generator';

		if ( file_exists( $plugin_upload_dir ) && is_dir( $plugin_upload_dir ) ) {
			// Recursively delete directory
			self::recursive_delete_directory( $plugin_upload_dir );
		}
	}

	/**
	 * Recursively delete a directory.
	 *
	 * @since    1.0.0
	 * @param    string    $dir    Directory path.
	 */
	private static function recursive_delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				self::recursive_delete_directory( $path );
			} else {
				wp_delete_file( $path );
			}
		}

		rmdir( $dir );
	}
}