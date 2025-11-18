<?php
/**
 * Migration: Create designs table.
 *
 * Creates the generated designs table for storing AI-generated layouts.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database/migrations
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database\Migrations;

/**
 * Migration class for creating designs table.
 *
 * @since      1.0.0
 */
class Migration_003 {

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
	 * Sessions table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $sessions_table    Sessions table name with prefix.
	 */
	private $sessions_table;

	/**
	 * Posts table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $posts_table    Posts table name with prefix.
	 */
	private $posts_table;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \wpdb    $wpdb    WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'waisg_designs';
		$this->sessions_table = $wpdb->prefix . 'waisg_sessions';
		$this->posts_table = $wpdb->prefix . 'posts';
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
			session_id bigint(20) UNSIGNED NOT NULL,
			page_id bigint(20) UNSIGNED DEFAULT NULL,
			design_type varchar(50) DEFAULT NULL,
			design_data longtext NOT NULL,
			blocks_data longtext DEFAULT NULL,
			version int(11) DEFAULT 1,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id_idx (session_id),
			KEY page_id_idx (page_id),
			KEY design_type_idx (design_type),
			KEY is_active_idx (is_active),
			KEY version_idx (version),
			KEY created_at_idx (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name );
		if ( $this->wpdb->get_var( $query ) !== $this->table_name ) {
			return false;
		}

		// Add foreign key constraints
		$this->add_foreign_keys_if_supported();

		// Add composite index for version tracking
		$this->add_composite_indexes();

		return true;
	}

	/**
	 * Rollback the migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function down() {
		// Drop composite indexes
		$this->drop_composite_indexes();

		// Drop foreign keys if exist
		$this->drop_foreign_keys_if_exist();

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
	 * Add foreign key constraints if supported.
	 *
	 * @since    1.0.0
	 */
	private function add_foreign_keys_if_supported() {
		// Check if table is using InnoDB
		$table_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SHOW TABLE STATUS WHERE Name = %s",
				$this->table_name
			)
		);

		if ( ! $table_status || 'InnoDB' !== $table_status->Engine ) {
			return;
		}

		// Add foreign key for session_id
		$this->add_foreign_key( 'fk_designs_session_id', 'session_id', $this->sessions_table, 'id', 'CASCADE' );

		// Add foreign key for page_id (SET NULL on delete since page might be deleted)
		$this->add_foreign_key( 'fk_designs_page_id', 'page_id', $this->posts_table, 'ID', 'SET NULL' );
	}

	/**
	 * Add a single foreign key constraint.
	 *
	 * @since    1.0.0
	 * @param    string    $constraint_name    Name of the constraint.
	 * @param    string    $column            Column name.
	 * @param    string    $ref_table         Referenced table.
	 * @param    string    $ref_column        Referenced column.
	 * @param    string    $on_delete         ON DELETE action.
	 */
	private function add_foreign_key( $constraint_name, $column, $ref_table, $ref_column, $on_delete ) {
		// Check if foreign key already exists
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
			$sql = "ALTER TABLE {$this->table_name}
					ADD CONSTRAINT {$constraint_name}
					FOREIGN KEY ({$column})
					REFERENCES {$ref_table}({$ref_column})
					ON DELETE {$on_delete}";

			// Suppress errors as foreign keys might not be supported
			$this->wpdb->suppress_errors( true );
			$this->wpdb->query( $sql );
			$this->wpdb->suppress_errors( false );
		}
	}

	/**
	 * Drop foreign key constraints if they exist.
	 *
	 * @since    1.0.0
	 */
	private function drop_foreign_keys_if_exist() {
		$constraints = array( 'fk_designs_session_id', 'fk_designs_page_id' );

		foreach ( $constraints as $constraint_name ) {
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

				// Suppress errors
				$this->wpdb->suppress_errors( true );
				$this->wpdb->query( $sql );
				$this->wpdb->suppress_errors( false );
			}
		}
	}

	/**
	 * Add composite indexes for better query performance.
	 *
	 * @since    1.0.0
	 */
	private function add_composite_indexes() {
		// Add composite index for finding active designs by session and page
		$this->add_index( 'idx_session_page_active', array( 'session_id', 'page_id', 'is_active' ) );

		// Add composite index for version tracking
		$this->add_index( 'idx_session_version', array( 'session_id', 'version' ) );
	}

	/**
	 * Add an index to the table.
	 *
	 * @since    1.0.0
	 * @param    string    $index_name    Name of the index.
	 * @param    array     $columns       Array of column names.
	 */
	private function add_index( $index_name, $columns ) {
		// Check if index already exists
		$existing = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT INDEX_NAME
				FROM INFORMATION_SCHEMA.STATISTICS
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = %s
				AND INDEX_NAME = %s",
				$this->table_name,
				$index_name
			)
		);

		if ( ! $existing ) {
			$columns_str = implode( ', ', $columns );
			$sql = "ALTER TABLE {$this->table_name} ADD INDEX {$index_name} ({$columns_str})";

			// Suppress errors
			$this->wpdb->suppress_errors( true );
			$this->wpdb->query( $sql );
			$this->wpdb->suppress_errors( false );
		}
	}

	/**
	 * Drop composite indexes.
	 *
	 * @since    1.0.0
	 */
	private function drop_composite_indexes() {
		$indexes = array( 'idx_session_page_active', 'idx_session_version' );

		foreach ( $indexes as $index_name ) {
			// Check if index exists
			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT INDEX_NAME
					FROM INFORMATION_SCHEMA.STATISTICS
					WHERE TABLE_SCHEMA = DATABASE()
					AND TABLE_NAME = %s
					AND INDEX_NAME = %s",
					$this->table_name,
					$index_name
				)
			);

			if ( $existing ) {
				$sql = "ALTER TABLE {$this->table_name} DROP INDEX {$index_name}";

				// Suppress errors
				$this->wpdb->suppress_errors( true );
				$this->wpdb->query( $sql );
				$this->wpdb->suppress_errors( false );
			}
		}
	}
}