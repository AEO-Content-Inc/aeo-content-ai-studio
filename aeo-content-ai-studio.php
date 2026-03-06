<?php
/**
 * Plugin Name: AEO Content AI Studio
 * Plugin URI: https://www.aeocontent.ai
 * Description: AI Engine Optimization for WordPress. Manages llms.txt, ai.txt, robots.txt rules, structured data, and semantic HTML to maximize AI visibility.
 * Version: 1.1.0
 * Author: AEO Content, Inc.
 * Author URI: https://www.aeocontent.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aeo-content-ai-studio
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AEO_VERSION', '1.1.0' );
define( 'AEO_PLUGIN_FILE', __FILE__ );
define( 'AEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AEO_PLATFORM_URL', 'https://www.aeocontent.ai' );

require_once AEO_PLUGIN_DIR . 'includes/class-aeo-plugin.php';

/**
 * Returns the main plugin instance.
 */
function aeo_content_ai_studio() {
    return AEO_Plugin::get_instance();
}

// Boot the plugin.
aeo_content_ai_studio();
