<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WP_AI_Site_Generator
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Get plugin options.
$delete_data = get_option( 'waisg_delete_data_on_uninstall', false );

if ( $delete_data ) {
	// Delete plugin database tables.
	$tables = array(
		$wpdb->prefix . 'waisg_sessions',
		$wpdb->prefix . 'waisg_messages',
		$wpdb->prefix . 'waisg_designs',
		$wpdb->prefix . 'waisg_generation_history',
		$wpdb->prefix . 'waisg_migrations',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Delete all plugin options.
	delete_option( 'waisg_version' );
	delete_option( 'waisg_db_version' );
	delete_option( 'waisg_general_settings' );
	delete_option( 'waisg_provider_settings' );
	delete_option( 'waisg_generation_settings' );
	delete_option( 'waisg_advanced_settings' );
	delete_option( 'waisg_feature_settings' );
	delete_option( 'waisg_usage_settings' );
	delete_option( 'waisg_delete_data_on_uninstall' );

	// Delete transients.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_waisg_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_waisg_%'" );

	// Clear any scheduled cron jobs.
	wp_clear_scheduled_hook( 'waisg_cleanup_old_sessions' );
	wp_clear_scheduled_hook( 'waisg_cleanup_cache' );
}
