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

register_activation_hook(__FILE__, 'waisg_activate');
register_deactivation_hook(__FILE__, 'waisg_deactivate');

/**
 * The core plugin class.
 */
require WAISG_PLUGIN_DIR . 'includes/class-ai-site-generator.php';

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function waisg_run() {
    $plugin = new WPAISiteGenerator\Includes\AI_Site_Generator();
    $plugin->run();
}

// Initialize the plugin.
waisg_run();
