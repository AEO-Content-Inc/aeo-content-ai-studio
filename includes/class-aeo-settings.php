<?php
/**
 * Admin settings page for AEO Content AI Studio.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    public function add_menu() {
        add_options_page(
            __( 'AEO Content AI Studio', 'aeo-content-ai-studio' ),
            __( 'AEO Content', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-content-ai-studio',
            array( $this, 'render_page' )
        );

        add_options_page(
            __( 'AEO Activity Log', 'aeo-content-ai-studio' ),
            __( 'AEO Activity Log', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-activity-log',
            array( $this, 'render_activity_log' )
        );
    }

    public function register_settings() {
        register_setting( 'aeo_settings', 'aeo_site_token', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'aeo_settings', 'aeo_platform_url', array(
            'type'              => 'string',
            'default'           => 'https://www.aeocontent.ai',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        register_setting( 'aeo_settings', 'aeo_enabled_features', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_features' ),
        ) );
    }

    public function sanitize_features( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $available = aeo_content_ai_studio()->get_available_modules();
        return array_values( array_intersect( $input, $available ) );
    }

    public function enqueue_styles( $hook ) {
        $aeo_pages = array(
            'settings_page_aeo-content-ai-studio',
            'settings_page_aeo-activity-log',
        );
        if ( ! in_array( $hook, $aeo_pages, true ) ) {
            return;
        }
        wp_enqueue_style(
            'aeo-admin',
            AEO_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            AEO_VERSION
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include AEO_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_activity_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include AEO_PLUGIN_DIR . 'admin/views/activity-log-page.php';
    }
}
