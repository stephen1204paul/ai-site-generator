<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package WP_AI_Site_Generator
 */

// Load Composer autoloader.
require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';

// Define test constants.
define( 'WAISG_TESTS_DIR', __DIR__ );
define( 'WAISG_PLUGIN_DIR', dirname( __DIR__ ) );

// Load WordPress test environment if available.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php\n";
	echo "Please set WP_TESTS_DIR environment variable or install WordPress test library\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require WAISG_PLUGIN_DIR . '/wp-ai-site-generator.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
