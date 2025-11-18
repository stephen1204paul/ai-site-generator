<?php
/**
 * Migration: Create messages table.
 *
 * Creates the chat messages table for storing conversation history.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database/migrations
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database\Migrations;

/**
 * Migration class for creating messages table.
 *
 * @since      1.0.0
 */
class Migration_002 {

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
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \wpdb    $wpdb    WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'waisg_messages';
		$this->sessions_table = $wpdb->prefix . 'waisg_sessions';
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
			role enum('user', 'assistant', 'system') NOT NULL,
			content longtext NOT NULL,
			tokens_used int(11) DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id_idx (session_id),
			KEY role_idx (role),
			KEY created_at_idx (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name );
		if ( $this->wpdb->get_var( $query ) !== $this->table_name ) {
			return false;
		}

		// Add foreign key constraint for session_id
		$this->add_foreign_key_if_supported();

		// Add fulltext index for content search if supported
		$this->add_fulltext_index_if_supported();

		return true;
	}

	/**
	 * Rollback the migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function down() {
		// Drop fulltext index if exists
		$this->drop_fulltext_index_if_exists();

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
			$constraint_name = 'fk_messages_session_id';
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
				// Add foreign key constraint with CASCADE delete
				$sql = "ALTER TABLE {$this->table_name}
						ADD CONSTRAINT {$constraint_name}
						FOREIGN KEY (session_id)
						REFERENCES {$this->sessions_table}(id)
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
		$constraint_name = 'fk_messages_session_id';

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

	/**
	 * Add fulltext index for content search if supported.
	 *
	 * @since    1.0.0
	 */
	private function add_fulltext_index_if_supported() {
		// Check if table is using MyISAM or InnoDB with fulltext support
		$table_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SHOW TABLE STATUS WHERE Name = %s",
				$this->table_name
			)
		);

		if ( ! $table_status ) {
			return;
		}

		// MyISAM supports fulltext, InnoDB supports it from MySQL 5.6+
		$supports_fulltext = false;
		if ( 'MyISAM' === $table_status->Engine ) {
			$supports_fulltext = true;
		} elseif ( 'InnoDB' === $table_status->Engine ) {
			// Check MySQL version for InnoDB fulltext support
			$mysql_version = $this->wpdb->db_version();
			if ( version_compare( $mysql_version, '5.6.0', '>=' ) ) {
				$supports_fulltext = true;
			}
		}

		if ( $supports_fulltext ) {
			// Check if fulltext index already exists
			$index_name = 'ft_messages_content';
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
				// Add fulltext index
				$sql = "ALTER TABLE {$this->table_name} ADD FULLTEXT {$index_name} (content)";

				// Suppress errors as fulltext might not be supported
				$this->wpdb->suppress_errors( true );
				$this->wpdb->query( $sql );
				$this->wpdb->suppress_errors( false );
			}
		}
	}

	/**
	 * Drop fulltext index if it exists.
	 *
	 * @since    1.0.0
	 */
	private function drop_fulltext_index_if_exists() {
		$index_name = 'ft_messages_content';

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