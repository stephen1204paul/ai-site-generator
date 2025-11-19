<?php
/**
 * Migration: Create quality metrics table
 *
 * @package WPAISiteGenerator
 * @since 1.0.0
 */

namespace WPAISiteGenerator\Database\Migrations;

/**
 * Create quality metrics table migration.
 */
class Create_Quality_Metrics_Table {

	/**
	 * Run the migration.
	 *
	 * @return bool Success status.
	 */
	public static function up() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'waisg_quality_metrics';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			generation_id bigint(20) UNSIGNED NOT NULL,
			quality_score decimal(5,2) NOT NULL DEFAULT 0.00,
			provider varchar(50) DEFAULT NULL,
			content_type varchar(50) DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			metrics_data longtext DEFAULT NULL COMMENT 'JSON encoded metrics data',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_generation_id (generation_id),
			KEY idx_provider (provider),
			KEY idx_content_type (content_type),
			KEY idx_quality_score (quality_score),
			KEY idx_created_at (created_at),
			KEY idx_user_id (user_id),
			KEY idx_provider_score (provider, quality_score),
			KEY idx_date_score (created_at, quality_score)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;

		if ( $table_exists ) {
			// Add version option
			update_option( 'waisg_quality_metrics_db_version', '1.0.0' );
			return true;
		}

		return false;
	}

	/**
	 * Reverse the migration.
	 *
	 * @return bool Success status.
	 */
	public static function down() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'waisg_quality_metrics';

		// Drop the table
		$sql = "DROP TABLE IF EXISTS {$table_name}";
		$result = $wpdb->query( $sql );

		// Remove version option
		delete_option( 'waisg_quality_metrics_db_version' );

		return false !== $result;
	}

	/**
	 * Get migration description.
	 *
	 * @return string Migration description.
	 */
	public static function description() {
		return 'Creates the quality metrics table for tracking generation quality scores and related metrics';
	}

	/**
	 * Get migration version.
	 *
	 * @return string Migration version.
	 */
	public static function version() {
		return '1.0.0';
	}
}