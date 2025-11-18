<?php
/**
 * Migration CLI Tool.
 *
 * Command-line utility for managing database migrations.
 * This can be used via WP-CLI or directly for testing.
 *
 * @package    WPAISiteGenerator
 * @subpackage WPAISiteGenerator/database
 * @since      1.0.0
 */

namespace WPAISiteGenerator\Database;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration CLI Class.
 *
 * Provides command-line interface for migration management.
 *
 * @since      1.0.0
 */
class Migration_CLI {

	/**
	 * Migration manager instance.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      Migration_Manager    $migration_manager    Migration manager instance.
	 */
	private $migration_manager;

	/**
	 * Constructor.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		require_once plugin_dir_path( __FILE__ ) . 'class-migration-manager.php';
		$this->migration_manager = new Migration_Manager();
	}

	/**
	 * Run pending migrations.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview migrations without executing them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp waisg migrate
	 *     wp waisg migrate --dry-run
	 *
	 * @since    1.0.0
	 * @param    array    $args       Positional arguments.
	 * @param    array    $assoc_args Associative arguments.
	 */
	public function migrate( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			$this->status( $args, $assoc_args );
			\WP_CLI::success( 'Dry run completed. No changes were made.' );
			return;
		}

		\WP_CLI::log( 'Running database migrations...' );

		$result = $this->migration_manager->run();

		if ( $result['success'] ) {
			if ( ! empty( $result['executed'] ) ) {
				\WP_CLI::success( $result['message'] );
				foreach ( $result['executed'] as $migration ) {
					\WP_CLI::log( "  ✓ Executed migration: {$migration}" );
				}
			} else {
				\WP_CLI::success( $result['message'] );
			}
		} else {
			\WP_CLI::error( $result['message'] );
			foreach ( $result['errors'] as $error ) {
				\WP_CLI::error( "  ✗ {$error['migration']}: {$error['error']}", false );
			}
		}
	}

	/**
	 * Rollback migrations.
	 *
	 * ## OPTIONS
	 *
	 * [<target>]
	 * : Target version to rollback to. If not specified, rolls back last migration.
	 *
	 * [--force]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp waisg rollback
	 *     wp waisg rollback 002
	 *     wp waisg rollback --force
	 *
	 * @since    1.0.0
	 * @param    array    $args       Positional arguments.
	 * @param    array    $assoc_args Associative arguments.
	 */
	public function rollback( $args, $assoc_args ) {
		$target = isset( $args[0] ) ? $args[0] : null;
		$force = isset( $assoc_args['force'] );

		if ( ! $force ) {
			\WP_CLI::confirm( 'Are you sure you want to rollback migrations? This may result in data loss.' );
		}

		\WP_CLI::log( 'Rolling back migrations...' );

		$result = $this->migration_manager->rollback( $target );

		if ( $result['success'] ) {
			if ( ! empty( $result['rolled_back'] ) ) {
				\WP_CLI::success( $result['message'] );
				foreach ( $result['rolled_back'] as $migration ) {
					\WP_CLI::log( "  ✓ Rolled back migration: {$migration}" );
				}
			} else {
				\WP_CLI::success( $result['message'] );
			}
		} else {
			\WP_CLI::error( $result['message'] );
			foreach ( $result['errors'] as $error ) {
				\WP_CLI::error( "  ✗ {$error['migration']}: {$error['error']}", false );
			}
		}
	}

	/**
	 * Show migration status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp waisg status
	 *
	 * @since    1.0.0
	 * @param    array    $args       Positional arguments.
	 * @param    array    $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$status = $this->migration_manager->get_status();

		\WP_CLI::log( '=== Migration Status ===' );
		\WP_CLI::log( "Current Version: {$status['current_version']}" );
		\WP_CLI::log( "Total Migrations: {$status['total_migrations']}" );
		\WP_CLI::log( "Executed Migrations: {$status['executed_migrations']}" );
		\WP_CLI::log( "Pending Migrations: {$status['pending_migrations']}" );
		\WP_CLI::log( '' );

		if ( ! empty( $status['pending'] ) ) {
			\WP_CLI::log( 'Pending Migrations:' );
			foreach ( $status['pending'] as $migration ) {
				\WP_CLI::log( "  - {$migration['version']}: {$migration['name']}" );
			}
			\WP_CLI::log( '' );
		}

		if ( ! empty( $status['executed'] ) ) {
			\WP_CLI::log( 'Executed Migrations:' );
			foreach ( $status['executed'] as $version ) {
				\WP_CLI::log( "  ✓ {$version}" );
			}
		}
	}

	/**
	 * Show migration history.
	 *
	 * ## EXAMPLES
	 *
	 *     wp waisg history
	 *
	 * @since    1.0.0
	 * @param    array    $args       Positional arguments.
	 * @param    array    $assoc_args Associative arguments.
	 */
	public function history( $args, $assoc_args ) {
		$history = $this->migration_manager->get_history();

		if ( empty( $history ) ) {
			\WP_CLI::log( 'No migration history found.' );
			return;
		}

		$items = array();
		foreach ( $history as $record ) {
			$items[] = array(
				'Version' => $record['version'],
				'Name' => $record['name'],
				'Executed At' => $record['executed_at'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'Version', 'Name', 'Executed At' ) );
	}

	/**
	 * Reset all migrations (dangerous!).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp waisg reset --force
	 *
	 * @since    1.0.0
	 * @param    array    $args       Positional arguments.
	 * @param    array    $assoc_args Associative arguments.
	 */
	public function reset( $args, $assoc_args ) {
		$force = isset( $assoc_args['force'] );

		if ( ! $force ) {
			\WP_CLI::confirm( 'WARNING: This will DELETE ALL plugin tables and data. Are you absolutely sure?' );
			\WP_CLI::confirm( 'Type "yes" to confirm you understand this action is irreversible:' );
		}

		\WP_CLI::log( 'Resetting all migrations and tables...' );

		$result = $this->migration_manager->reset();

		if ( $result ) {
			\WP_CLI::success( 'All plugin tables have been dropped. Database has been reset.' );
		} else {
			\WP_CLI::error( 'Failed to reset database.' );
		}
	}
}

// Register WP-CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'waisg migrate', array( 'WPAISiteGenerator\Database\Migration_CLI', 'migrate' ) );
	\WP_CLI::add_command( 'waisg rollback', array( 'WPAISiteGenerator\Database\Migration_CLI', 'rollback' ) );
	\WP_CLI::add_command( 'waisg status', array( 'WPAISiteGenerator\Database\Migration_CLI', 'status' ) );
	\WP_CLI::add_command( 'waisg history', array( 'WPAISiteGenerator\Database\Migration_CLI', 'history' ) );
	\WP_CLI::add_command( 'waisg reset', array( 'WPAISiteGenerator\Database\Migration_CLI', 'reset' ) );
}