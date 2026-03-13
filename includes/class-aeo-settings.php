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
        add_filter( 'plugin_action_links_' . plugin_basename( AEO_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
    }

    /**
     * Add Settings link to the plugins list page.
     *
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=aeo-content-ai-studio' ) ) . '">' . esc_html__( 'Settings', 'aeo-content-ai-studio' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function add_menu() {
        add_menu_page(
            __( 'AEO Content AI Studio', 'aeo-content-ai-studio' ),
            __( 'AEO Content', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-audit-report',
            array( $this, 'render_audit_report' ),
            AEO_PLUGIN_URL . 'admin/images/icon.png',
            30
        );

        add_submenu_page(
            'aeo-audit-report',
            __( 'AEO Audit Report', 'aeo-content-ai-studio' ),
            __( 'Audit Report', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-audit-report',
            array( $this, 'render_audit_report' )
        );

        add_submenu_page(
            'aeo-audit-report',
            __( 'AEO Content AI Studio', 'aeo-content-ai-studio' ),
            __( 'Settings', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-content-ai-studio',
            array( $this, 'render_page' )
        );

        add_submenu_page(
            'aeo-audit-report',
            __( 'AEO Activity Log', 'aeo-content-ai-studio' ),
            __( 'Activity Log', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-activity-log',
            array( $this, 'render_activity_log' )
        );
    }

    public function register_settings() {
        register_setting( 'aeo_settings', 'aeo_site_token', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_and_register_api_key' ),
        ) );
        register_setting( 'aeo_settings', 'aeo_enabled_features', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_features' ),
        ) );
    }

    /**
     * Sanitize API key and attempt registration with the platform.
     *
     * Generates a plugin_token (if not already present) and sends it
     * to the platform during registration. The platform uses this token
     * to authenticate its requests to the plugin's REST API.
     */
    public function sanitize_and_register_api_key( $input ) {
        $api_key = sanitize_text_field( $input );

        if ( empty( $api_key ) ) {
            delete_option( 'aeo_connection_verified' );
            return '';
        }

        // Generate plugin token if not already present.
        $plugin_token = get_option( 'aeo_plugin_token', '' );
        if ( empty( $plugin_token ) ) {
            $plugin_token = AEO_Auth::generate_plugin_token();
        }

        $response = wp_remote_post(
            trailingslashit( AEO_PLATFORM_URL ) . 'api/v1/plugin/register',
            array(
                'body'    => wp_json_encode( array(
                    'site_url'     => get_site_url(),
                    'plugin_token' => $plugin_token,
                ) ),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key'   => $api_key,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            delete_option( 'aeo_connection_verified' );
            add_settings_error( 'aeo_site_token', 'aeo_register_failed',
                __( 'Could not connect to AEO Content platform. Please try again later.', 'aeo-content-ai-studio' ),
                'error'
            );
            return $api_key;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $status && ! empty( $body['ok'] ) ) {
            // Save plugin token only after successful registration.
            update_option( 'aeo_plugin_token', $plugin_token, false );
            update_option( 'aeo_connection_verified', true );
            add_settings_error( 'aeo_site_token', 'aeo_register_success',
                __( 'Successfully connected to AEO Content platform.', 'aeo-content-ai-studio' ),
                'success'
            );
        } else {
            delete_option( 'aeo_connection_verified' );
            $message = ! empty( $body['error'] ) ? sanitize_text_field( $body['error'] ) : __( 'Registration failed.', 'aeo-content-ai-studio' );
            add_settings_error( 'aeo_site_token', 'aeo_register_failed', $message, 'error' );
        }

        return $api_key;
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
            'toplevel_page_aeo-audit-report',
            'aeo-content_page_aeo-content-ai-studio',
            'aeo-content_page_aeo-activity-log',
        );
        if ( ! in_array( $hook, $aeo_pages, true ) ) {
            return;
        }
        $asset_ver = AEO_VERSION . '.' . filemtime( AEO_PLUGIN_DIR . 'admin/css/admin.css' );
        wp_enqueue_style(
            'aeo-admin',
            AEO_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $asset_ver
        );

        // Audit page JS.
        if ( 'toplevel_page_aeo-audit-report' === $hook ) {
            $js_ver = AEO_VERSION . '.' . filemtime( AEO_PLUGIN_DIR . 'admin/js/audit.js' );
            wp_enqueue_script(
                'aeo-audit',
                AEO_PLUGIN_URL . 'admin/js/audit.js',
                array(),
                $js_ver,
                true
            );
            wp_localize_script( 'aeo-audit', 'aeoAudit', array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'aeo_audit_nonce' ),
                'favicon'  => get_site_icon_url( 48, '' ),
            ) );
        }
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

    public function render_audit_report() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include AEO_PLUGIN_DIR . 'admin/views/audit-page.php';
    }
}
