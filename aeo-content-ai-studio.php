<?php
/**
 * Plugin Name: AEO Content AI Studio
 * Description: AI visibility audits and content publishing for WordPress. Track citations, prioritize fixes, and publish optimized drafts from AEO Content AI Studio.
 * Version: 1.2.7
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

define( 'AEOCAS_VERSION', '1.2.7' );
define( 'AEOCAS_PLUGIN_FILE', __FILE__ );
define( 'AEOCAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AEOCAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AEOCAS_PLATFORM_URL', 'https://www.aeocontent.ai' );
define( 'AEOCAS_ADMIN_URL', 'https://admin.aeocontent.ai' );
define( 'AEOCAS_ACCOUNT_URL', 'https://account.aeocontent.ai' );
define( 'AEOCAS_STUDIO_URL', 'https://studio.aeocontent.ai' );

require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-plugin.php';

/**
 * Returns the main plugin instance.
 */
function aeocas_plugin() {
	return AEOCAS_Plugin::get_instance();
}

// Register activation hook.
register_activation_hook(
	__FILE__,
	function () {
		aeocas_plugin()->on_activate();
	}
);

// Boot the plugin.
aeocas_plugin();
