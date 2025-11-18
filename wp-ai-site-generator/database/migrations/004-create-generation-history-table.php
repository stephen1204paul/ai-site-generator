<?php
/**
 * Migration: Create generation history table.
 *
 * Creates the generation history table for tracking regenerations and changes.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database/migrations
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database\Migrations;

/**
 * Migration class for creating generation history table.
 *
 * @since      1.0.0
 */
class Migration_004 {

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
	 * Designs table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $designs_table    Designs table name with prefix.
	 */
	private $designs_table;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 * @param    \wpdb    $wpdb    WordPress database object.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
		$this->table_name = $wpdb->prefix . 'waisg_generation_history';
		$this->designs_table = $wpdb->prefix . 'waisg_designs';
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
			design_id bigint(20) UNSIGNED NOT NULL,
			section_id varchar(100) DEFAULT NULL,
			prompt text DEFAULT NULL,
			response longtext DEFAULT NULL,
			blocks_generated longtext DEFAULT NULL,
			tokens_used int(11) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY design_id_idx (design_id),
			KEY section_id_idx (section_id),
			KEY created_at_idx (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$query = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name );
		if ( $this->wpdb->get_var( $query ) !== $this->table_name ) {
			return false;
		}

		// Add foreign key constraint
		$this->add_foreign_key_if_supported();

		// Add composite indexes for performance
		$this->add_composite_indexes();

		// Add fulltext index for searching prompts if supported
		$this->add_fulltext_indexes_if_supported();

		return true;
	}

	/**
	 * Rollback the migration.
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function down() {
		// Drop fulltext indexes
		$this->drop_fulltext_indexes_if_exist();

		// Drop composite indexes
		$this->drop_composite_indexes();

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
			$constraint_name = 'fk_generation_history_design_id';
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
				// When a design is deleted, its history should also be deleted
				$sql = "ALTER TABLE {$this->table_name}
						ADD CONSTRAINT {$constraint_name}
						FOREIGN KEY (design_id)
						REFERENCES {$this->designs_table}(id)
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
		$constraint_name = 'fk_generation_history_design_id';

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

	/**
	 * Add composite indexes for better query performance.
	 *
	 * @since    1.0.0
	 */
	private function add_composite_indexes() {
		// Add composite index for finding history by design and section
		$this->add_index( 'idx_design_section', array( 'design_id', 'section_id' ) );

		// Add composite index for finding recent generations by design
		$this->add_index( 'idx_design_created', array( 'design_id', 'created_at' ) );
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
		$indexes = array( 'idx_design_section', 'idx_design_created' );

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

	/**
	 * Add fulltext indexes for search functionality if supported.
	 *
	 * @since    1.0.0
	 */
	private function add_fulltext_indexes_if_supported() {
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
			// Add fulltext index for prompt searching
			$this->add_fulltext_index( 'ft_generation_prompt', 'prompt' );
		}
	}

	/**
	 * Add a fulltext index.
	 *
	 * @since    1.0.0
	 * @param    string    $index_name    Name of the fulltext index.
	 * @param    string    $column        Column to index.
	 */
	private function add_fulltext_index( $index_name, $column ) {
		// Check if fulltext index already exists
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
			$sql = "ALTER TABLE {$this->table_name} ADD FULLTEXT {$index_name} ({$column})";

			// Suppress errors as fulltext might not be supported
			$this->wpdb->suppress_errors( true );
			$this->wpdb->query( $sql );
			$this->wpdb->suppress_errors( false );
		}
	}

	/**
	 * Drop fulltext indexes if they exist.
	 *
	 * @since    1.0.0
	 */
	private function drop_fulltext_indexes_if_exist() {
		$indexes = array( 'ft_generation_prompt' );

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