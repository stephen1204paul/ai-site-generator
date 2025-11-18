<?php
/**
 * Database Migration Manager.
 *
 * Manages database migrations for the plugin.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database;

/**
 * Migration Manager Class.
 *
 * Handles database schema migrations with version tracking,
 * rollback capability, and safe execution.
 *
 * @since      1.0.0
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 */
class Migration_Manager {

	/**
	 * WordPress database object.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      \wpdb    $wpdb    WordPress database object.
	 */
	private $wpdb;

	/**
	 * Migrations table name.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $migrations_table    Name of the migrations tracking table.
	 */
	private $migrations_table;

	/**
	 * Migrations directory path.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $migrations_dir    Path to migrations directory.
	 */
	private $migrations_dir;

	/**
	 * Current database version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_version    Current database version.
	 */
	private $current_version;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->migrations_table = $wpdb->prefix . 'waisg_migrations';
		$this->migrations_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'database/migrations/';
		$this->current_version = get_option( 'waisg_db_version', '0.0.0' );
	}

	/**
	 * Run all pending migrations.
	 *
	 * @since    1.0.0
	 * @return   array    Results of migration run.
	 */
	public function run() {
		$results = array(
			'success' => true,
			'executed' => array(),
			'errors' => array(),
			'message' => '',
		);

		// Ensure migrations table exists
		$this->ensure_migrations_table();

		// Get all migration files
		$migrations = $this->get_migration_files();

		if ( empty( $migrations ) ) {
			$results['message'] = 'No migrations found.';
			return $results;
		}

		// Get executed migrations
		$executed = $this->get_executed_migrations();

		// Run pending migrations
		foreach ( $migrations as $migration ) {
			if ( in_array( $migration['version'], $executed, true ) ) {
				continue;
			}

			$result = $this->run_migration( $migration );

			if ( $result['success'] ) {
				$results['executed'][] = $migration['version'];
				$this->record_migration( $migration['version'], $migration['name'] );
			} else {
				$results['success'] = false;
				$results['errors'][] = array(
					'migration' => $migration['version'],
					'error' => $result['error'],
				);
				// Stop on first error to prevent inconsistent state
				break;
			}
		}

		// Update database version
		if ( $results['success'] && ! empty( $results['executed'] ) ) {
			$latest_version = end( $results['executed'] );
			update_option( 'waisg_db_version', $latest_version );
			$results['message'] = sprintf(
				'Successfully executed %d migration(s). Database version: %s',
				count( $results['executed'] ),
				$latest_version
			);
		} elseif ( ! empty( $results['errors'] ) ) {
			$results['message'] = 'Migration failed. Database may be in inconsistent state.';
		} else {
			$results['message'] = 'Database is up to date.';
		}

		return $results;
	}

	/**
	 * Rollback migrations to a specific version.
	 *
	 * @since    1.0.0
	 * @param    string    $target_version    Target version to rollback to.
	 * @return   array                        Results of rollback.
	 */
	public function rollback( $target_version = null ) {
		$results = array(
			'success' => true,
			'rolled_back' => array(),
			'errors' => array(),
			'message' => '',
		);

		// Get executed migrations in reverse order
		$executed = $this->get_executed_migrations( 'DESC' );

		if ( empty( $executed ) ) {
			$results['message'] = 'No migrations to rollback.';
			return $results;
		}

		// Determine target version
		if ( null === $target_version ) {
			// Rollback last migration
			$target_version = count( $executed ) > 1 ? $executed[1] : '000';
		}

		// Get migration files
		$migrations = $this->get_migration_files();
		$migrations_map = array();
		foreach ( $migrations as $migration ) {
			$migrations_map[ $migration['version'] ] = $migration;
		}

		// Rollback migrations
		foreach ( $executed as $version ) {
			if ( $version <= $target_version ) {
				break;
			}

			if ( ! isset( $migrations_map[ $version ] ) ) {
				$results['errors'][] = array(
					'migration' => $version,
					'error' => 'Migration file not found.',
				);
				continue;
			}

			$migration = $migrations_map[ $version ];
			$result = $this->rollback_migration( $migration );

			if ( $result['success'] ) {
				$results['rolled_back'][] = $version;
				$this->remove_migration_record( $version );
			} else {
				$results['success'] = false;
				$results['errors'][] = array(
					'migration' => $version,
					'error' => $result['error'],
				);
				// Stop on first error
				break;
			}
		}

		// Update database version
		if ( $results['success'] && ! empty( $results['rolled_back'] ) ) {
			update_option( 'waisg_db_version', $target_version );
			$results['message'] = sprintf(
				'Successfully rolled back %d migration(s). Database version: %s',
				count( $results['rolled_back'] ),
				$target_version
			);
		} elseif ( ! empty( $results['errors'] ) ) {
			$results['message'] = 'Rollback failed. Database may be in inconsistent state.';
		} else {
			$results['message'] = 'Nothing to rollback.';
		}

		return $results;
	}

	/**
	 * Check if migrations are needed.
	 *
	 * @since    1.0.0
	 * @return   bool    True if migrations are pending, false otherwise.
	 */
	public function needs_migration() {
		$migrations = $this->get_migration_files();
		$executed = $this->get_executed_migrations();

		foreach ( $migrations as $migration ) {
			if ( ! in_array( $migration['version'], $executed, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get migration status.
	 *
	 * @since    1.0.0
	 * @return   array    Migration status information.
	 */
	public function get_status() {
		$this->ensure_migrations_table();

		$migrations = $this->get_migration_files();
		$executed = $this->get_executed_migrations();
		$pending = array();

		foreach ( $migrations as $migration ) {
			if ( ! in_array( $migration['version'], $executed, true ) ) {
				$pending[] = $migration;
			}
		}

		return array(
			'current_version' => $this->current_version,
			'total_migrations' => count( $migrations ),
			'executed_migrations' => count( $executed ),
			'pending_migrations' => count( $pending ),
			'executed' => $executed,
			'pending' => $pending,
			'migrations_table_exists' => $this->migrations_table_exists(),
		);
	}

	/**
	 * Ensure migrations tracking table exists.
	 *
	 * @since    1.0.0
	 */
	private function ensure_migrations_table() {
		if ( $this->migrations_table_exists() ) {
			return;
		}

		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$this->migrations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			version varchar(20) NOT NULL,
			name varchar(255) NOT NULL,
			executed_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY version (version),
			KEY executed_at (executed_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if migrations table exists.
	 *
	 * @since    1.0.0
	 * @return   bool    True if table exists, false otherwise.
	 */
	private function migrations_table_exists() {
		$query = $this->wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$this->migrations_table
		);
		return $this->wpdb->get_var( $query ) === $this->migrations_table;
	}

	/**
	 * Get all migration files.
	 *
	 * @since    1.0.0
	 * @return   array    Array of migration information.
	 */
	private function get_migration_files() {
		$migrations = array();

		if ( ! is_dir( $this->migrations_dir ) ) {
			return $migrations;
		}

		$files = glob( $this->migrations_dir . '*.php' );

		if ( empty( $files ) ) {
			return $migrations;
		}

		foreach ( $files as $file ) {
			$filename = basename( $file );

			// Parse migration filename (format: XXX-migration-name.php)
			if ( preg_match( '/^(\d{3})-(.+)\.php$/', $filename, $matches ) ) {
				$migrations[] = array(
					'version' => $matches[1],
					'name' => str_replace( '-', '_', $matches[2] ),
					'filename' => $filename,
					'filepath' => $file,
				);
			}
		}

		// Sort by version
		usort( $migrations, function( $a, $b ) {
			return strcmp( $a['version'], $b['version'] );
		} );

		return $migrations;
	}

	/**
	 * Get executed migrations.
	 *
	 * @since    1.0.0
	 * @param    string    $order    Order of results (ASC or DESC).
	 * @return   array               Array of executed migration versions.
	 */
	private function get_executed_migrations( $order = 'ASC' ) {
		if ( ! $this->migrations_table_exists() ) {
			return array();
		}

		$order = in_array( strtoupper( $order ), array( 'ASC', 'DESC' ), true ) ? $order : 'ASC';

		$query = "SELECT version FROM {$this->migrations_table} ORDER BY version {$order}";
		$results = $this->wpdb->get_col( $query );

		return $results ? $results : array();
	}

	/**
	 * Run a single migration.
	 *
	 * @since    1.0.0
	 * @param    array    $migration    Migration information.
	 * @return   array                  Result of migration execution.
	 */
	private function run_migration( $migration ) {
		$result = array(
			'success' => false,
			'error' => null,
		);

		if ( ! file_exists( $migration['filepath'] ) ) {
			$result['error'] = 'Migration file not found: ' . $migration['filename'];
			return $result;
		}

		// Include migration file
		require_once $migration['filepath'];

		// Build class name
		$class_name = 'WPAISiteGenerator\\Database\\Migrations\\Migration_' . $migration['version'];

		if ( ! class_exists( $class_name ) ) {
			$result['error'] = 'Migration class not found: ' . $class_name;
			return $result;
		}

		try {
			// Instantiate migration
			$migration_instance = new $class_name( $this->wpdb );

			// Run up method
			if ( ! method_exists( $migration_instance, 'up' ) ) {
				$result['error'] = 'Migration class missing up() method: ' . $class_name;
				return $result;
			}

			// Start transaction if supported
			$this->wpdb->query( 'START TRANSACTION' );

			$migration_result = $migration_instance->up();

			if ( false === $migration_result ) {
				$this->wpdb->query( 'ROLLBACK' );
				$result['error'] = 'Migration failed: ' . $this->wpdb->last_error;
				return $result;
			}

			// Commit transaction
			$this->wpdb->query( 'COMMIT' );

			$result['success'] = true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			$result['error'] = 'Migration exception: ' . $e->getMessage();
		}

		return $result;
	}

	/**
	 * Rollback a single migration.
	 *
	 * @since    1.0.0
	 * @param    array    $migration    Migration information.
	 * @return   array                  Result of rollback execution.
	 */
	private function rollback_migration( $migration ) {
		$result = array(
			'success' => false,
			'error' => null,
		);

		if ( ! file_exists( $migration['filepath'] ) ) {
			$result['error'] = 'Migration file not found: ' . $migration['filename'];
			return $result;
		}

		// Include migration file
		require_once $migration['filepath'];

		// Build class name
		$class_name = 'WPAISiteGenerator\\Database\\Migrations\\Migration_' . $migration['version'];

		if ( ! class_exists( $class_name ) ) {
			$result['error'] = 'Migration class not found: ' . $class_name;
			return $result;
		}

		try {
			// Instantiate migration
			$migration_instance = new $class_name( $this->wpdb );

			// Check if down method exists
			if ( ! method_exists( $migration_instance, 'down' ) ) {
				$result['error'] = 'Migration class missing down() method: ' . $class_name;
				return $result;
			}

			// Start transaction if supported
			$this->wpdb->query( 'START TRANSACTION' );

			$migration_result = $migration_instance->down();

			if ( false === $migration_result ) {
				$this->wpdb->query( 'ROLLBACK' );
				$result['error'] = 'Rollback failed: ' . $this->wpdb->last_error;
				return $result;
			}

			// Commit transaction
			$this->wpdb->query( 'COMMIT' );

			$result['success'] = true;

		} catch ( \Exception $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			$result['error'] = 'Rollback exception: ' . $e->getMessage();
		}

		return $result;
	}

	/**
	 * Record a successful migration.
	 *
	 * @since    1.0.0
	 * @param    string    $version    Migration version.
	 * @param    string    $name       Migration name.
	 * @return   bool                  True on success, false on failure.
	 */
	private function record_migration( $version, $name ) {
		$result = $this->wpdb->insert(
			$this->migrations_table,
			array(
				'version' => $version,
				'name' => $name,
				'executed_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Remove a migration record.
	 *
	 * @since    1.0.0
	 * @param    string    $version    Migration version.
	 * @return   bool                  True on success, false on failure.
	 */
	private function remove_migration_record( $version ) {
		$result = $this->wpdb->delete(
			$this->migrations_table,
			array( 'version' => $version ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Reset all migrations (dangerous operation).
	 *
	 * @since    1.0.0
	 * @return   bool    True on success, false on failure.
	 */
	public function reset() {
		// Drop all plugin tables
		$tables = array(
			$this->wpdb->prefix . 'waisg_sessions',
			$this->wpdb->prefix . 'waisg_messages',
			$this->wpdb->prefix . 'waisg_designs',
			$this->wpdb->prefix . 'waisg_generation_history',
			$this->wpdb->prefix . 'waisg_generations',
			$this->wpdb->prefix . 'waisg_generated_content',
			$this->wpdb->prefix . 'waisg_provider_usage',
			$this->wpdb->prefix . 'waisg_templates',
			$this->migrations_table,
		);

		foreach ( $tables as $table ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Reset database version
		delete_option( 'waisg_db_version' );

		return true;
	}

	/**
	 * Get migration history.
	 *
	 * @since    1.0.0
	 * @return   array    Array of migration history records.
	 */
	public function get_history() {
		if ( ! $this->migrations_table_exists() ) {
			return array();
		}

		$query = "SELECT * FROM {$this->migrations_table} ORDER BY executed_at DESC";
		$results = $this->wpdb->get_results( $query, ARRAY_A );

		return $results ? $results : array();
	}
}