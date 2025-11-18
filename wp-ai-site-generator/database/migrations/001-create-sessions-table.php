<?php
/**
 * Migration: Create sessions table.
 *
 * Creates the chat sessions table for tracking user conversations.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database/migrations
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database\Migrations;

/**
 * Migration class for creating sessions table.
 *
 * @since      1.0.0
 */
class Migration_001 {

	/**
	 * WordPress database object.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      \wpdb    $wpdb    WordPress database object.
	 */
	private $wpdb;

	/**
	 * Table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $table_name    Table name with prefix.
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \wpdb    $wpdb    WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'waisg_sessions';
	}

	/**
	 * Run the migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function up() {
		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			session_name varchar(255) DEFAULT NULL,
			provider varchar(50) NOT NULL,
			model varchar(100) NOT NULL,
			status enum('active', 'completed', 'archived') NOT NULL DEFAULT 'active',
			metadata longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id_idx (user_id),
			KEY status_idx (status),
			KEY created_at_idx (created_at),
			KEY updated_at_idx (updated_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name );
		if ( $this->wpdb->get_var( $query ) !== $this->table_name ) {
			return false;
		}

		// Add foreign key constraint for user_id if wp_users table exists
		// Note: WordPress doesn't use foreign keys by default, but we'll add them for data integrity
		// Only if the storage engine supports it (InnoDB)
		$this->add_foreign_key_if_supported();

		return true;
	}

	/**
	 * Rollback the migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function down() {
		// Drop foreign key if exists
		$this->drop_foreign_key_if_exists();

		// Drop the table
		$sql = "DROP TABLE IF EXISTS {$this->table_name}";
		$result = $this->wpdb->query( $sql );

		// Verify table was dropped
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name );
		if ( $this->wpdb->get_var( $query ) === $this->table_name ) {
			return false;
		}

		return true;
	}

	/**
	 * Add foreign key constraint if supported.
	 *
	 * @since    1.0.0
	 */
	private function add_foreign_key_if_supported() {
		// Check if table is using InnoDB
		$table_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SHOW TABLE STATUS WHERE Name = %s",
				$this->table_name
			)
		);

		if ( $table_status && 'InnoDB' === $table_status->Engine ) {
			// Check if foreign key already exists
			$constraint_name = 'fk_sessions_user_id';
			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT CONSTRAINT_NAME
					FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
					WHERE TABLE_SCHEMA = DATABASE()
					AND TABLE_NAME = %s
					AND CONSTRAINT_NAME = %s",
					$this->table_name,
					$constraint_name
				)
			);

			if ( ! $existing ) {
				// Add foreign key constraint
				$users_table = $this->wpdb->prefix . 'users';
				$sql = "ALTER TABLE {$this->table_name}
						ADD CONSTRAINT {$constraint_name}
						FOREIGN KEY (user_id)
						REFERENCES {$users_table}(ID)
						ON DELETE CASCADE";

				// Suppress errors as foreign keys might not be supported
				$this->wpdb->suppress_errors( true );
				$this->wpdb->query( $sql );
				$this->wpdb->suppress_errors( false );
			}
		}
	}

	/**
	 * Drop foreign key constraint if it exists.
	 *
	 * @since    1.0.0
	 */
	private function drop_foreign_key_if_exists() {
		$constraint_name = 'fk_sessions_user_id';

		// Check if foreign key exists
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT CONSTRAINT_NAME
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = %s
				AND CONSTRAINT_NAME = %s",
				$this->table_name,
				$constraint_name
			)
		);

		if ( $existing ) {
			$sql = "ALTER TABLE {$this->table_name} DROP FOREIGN KEY {$constraint_name}";

			// Suppress errors as foreign keys might not be supported
			$this->wpdb->suppress_errors( true );
			$this->wpdb->query( $sql );
			$this->wpdb->suppress_errors( false );
		}
	}
}