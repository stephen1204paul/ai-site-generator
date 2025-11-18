<?php
/**
 * Plugin Name: WP AI Site Generator
 * Plugin URI: https://github.com/yourusername/wp-ai-site-generator
 * Description: Generate complete WordPress websites using AI with conversational interface
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-ai-site-generator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package WPAISiteGenerator
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 */
define('WAISG_VERSION', '1.0.0');

/**
 * Plugin directory path.
 */
define('WAISG_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('WAISG_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin basename.
 */
define('WAISG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Load composer autoloader if available.
 */
if (file_exists(WAISG_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WAISG_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The code that runs during plugin activation.
 */
function waisg_activate() {
    require_once WAISG_PLUGIN_DIR . 'includes/class-activator.php';
    WPAISiteGenerator\Includes\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function waisg_deactivate() {
    require_once WAISG_PLUGIN_DIR . 'includes/class-deactivator.php';
    WPAISiteGenerator\Includes\Deactivator::deactivate();
}

/**
 * The code that runs during plugin uninstall.
 */
function waisg_uninstall() {
    require_once WAISG_PLUGIN_DIR . 'includes/class-deactivator.php';
    WPAISiteGenerator\Includes\Deactivator::uninstall();
}

register_activation_hook(__FILE__, 'waisg_activate');
register_deactivation_hook(__FILE__, 'waisg_deactivate');

/**
 * The core plugin class.
 */
require WAISG_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function waisg_run() {
    $plugin = new WPAISiteGenerator\Includes\Plugin();
    $plugin->run();
}

// Initialize the plugin.
waisg_run();

/**
 * Add custom cron schedules.
 *
 * @since 1.0.0
 * @param array $schedules Existing cron schedules.
 * @return array Modified cron schedules.
 */
function waisg_add_cron_schedules($schedules) {
    $schedules['weekly'] = array(
        'interval' => WEEK_IN_SECONDS,
        'display'  => __('Weekly', 'wp-ai-site-generator'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'waisg_add_cron_schedules');

/**
 * Process generation in background.
 *
 * @since 1.0.0
 * @param int    $generation_id Generation ID.
 * @param string $prompt        Generation prompt.
 * @param string $provider      AI provider.
 * @param array  $options       Generation options.
 */
function waisg_process_generation($generation_id, $prompt, $provider, $options = array()) {
    try {
        $db_handler = new WPAISiteGenerator\Database\DB_Handler();
        $provider_manager = new WPAISiteGenerator\Providers\Provider_Manager();

        // Update status to processing
        $db_handler->update_generation($generation_id, array(
            'status'   => 'processing',
            'progress' => 10,
        ));

        // Generate site structure
        $site_structure = $provider_manager->generate_site_structure($prompt, $options, $provider);

        $db_handler->update_generation($generation_id, array(
            'progress' => 40,
        ));

        // Generate pages based on structure
        $generated_pages = array();
        foreach ($site_structure['pages'] ?? array() as $page_info) {
            $page_content = $provider_manager->generate_page_content(
                $page_info['prompt'] ?? $prompt,
                $page_info['type'] ?? 'page',
                $options,
                $provider
            );

            // Create WordPress page
            $page_id = wp_insert_post(array(
                'post_title'   => $page_info['title'] ?? '',
                'post_content' => $page_content['content'] ?? '',
                'post_type'    => 'page',
                'post_status'  => 'draft',
                'meta_input'   => array(
                    'waisg_generation_id' => $generation_id,
                ),
            ));

            $generated_pages[] = $page_id;
        }

        $db_handler->update_generation($generation_id, array(
            'progress' => 90,
        ));

        // Mark as completed
        $db_handler->update_generation($generation_id, array(
            'status'   => 'completed',
            'progress' => 100,
            'result'   => array(
                'site_structure' => $site_structure,
                'pages'          => $generated_pages,
            ),
        ));

    } catch (Exception $e) {
        $db_handler->update_generation($generation_id, array(
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ));
    }
}
add_action('waisg_process_generation', 'waisg_process_generation', 10, 4);
