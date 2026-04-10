<?php
/**
 * Admin settings page for AEO Content AI Studio.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEOCAS_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_post_aeocas_disconnect', array( $this, 'handle_disconnect' ) );
        add_action( 'wp_ajax_aeocas_google_connect', array( $this, 'ajax_google_connect' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( AEOCAS_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
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
            'aeocas-audit-report',
            array( $this, 'render_audit_report' ),
            AEOCAS_PLUGIN_URL . 'admin/images/icon.png',
            30
        );

        add_submenu_page(
            'aeocas-audit-report',
            __( 'AEO Audit Report', 'aeo-content-ai-studio' ),
            __( 'Audit Report', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeocas-audit-report',
            array( $this, 'render_audit_report' )
        );

        add_submenu_page(
            'aeocas-audit-report',
            __( 'AEO Content AI Studio', 'aeo-content-ai-studio' ),
            __( 'Settings', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeo-content-ai-studio',
            array( $this, 'render_page' )
        );

        add_submenu_page(
            'aeocas-audit-report',
            __( 'AEO Activity Log', 'aeo-content-ai-studio' ),
            __( 'Activity Log', 'aeo-content-ai-studio' ),
            'manage_options',
            'aeocas-activity-log',
            array( $this, 'render_activity_log' )
        );
    }

    public function register_settings() {
        register_setting( 'aeocas_connection_settings', 'aeocas_site_token', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_and_register_api_key' ),
        ) );
        register_setting( 'aeocas_settings', 'aeocas_enabled_features', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_features' ),
        ) );
    }

    /**
     * Sanitize a site credential and attempt registration with the platform.
     *
     * Generates a plugin_token (if not already present) and sends it
     * to the platform during registration. The platform uses this token
     * to authenticate its requests to the plugin's REST API.
     */
    public function sanitize_and_register_api_key( $input ) {
        $api_key = sanitize_text_field( $input );

        if ( empty( $api_key ) ) {
            delete_option( 'aeocas_connection_verified' );
            return '';
        }

        // Generate plugin token if not already present.
        $plugin_token = get_option( 'aeocas_plugin_token', '' );
        if ( empty( $plugin_token ) ) {
            $plugin_token = AEOCAS_Auth::generate_plugin_token();
        }

        $response = wp_remote_post(
            trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/plugin/register',
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
            delete_option( 'aeocas_connection_verified' );
            add_settings_error( 'aeocas_site_token', 'aeocas_register_failed',
                __( 'Could not connect to AEO Content platform. Please try again later.', 'aeo-content-ai-studio' ),
                'error'
            );
            return $api_key;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 === $status && ! empty( $body['ok'] ) ) {
            // Save plugin token only after successful registration.
            update_option( 'aeocas_plugin_token', $plugin_token, false );
            update_option( 'aeocas_connection_verified', true );
            add_settings_error( 'aeocas_site_token', 'aeocas_register_success',
                __( 'Successfully connected to AEO Content platform.', 'aeo-content-ai-studio' ),
                'success'
            );
        } else {
            delete_option( 'aeocas_connection_verified' );
            $message = ! empty( $body['error'] ) ? sanitize_text_field( $body['error'] ) : __( 'Registration failed.', 'aeo-content-ai-studio' );
            add_settings_error( 'aeocas_site_token', 'aeocas_register_failed', $message, 'error' );
        }

        return $api_key;
    }

    public function sanitize_features( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        $available = aeocas_plugin()->get_available_modules();
        return array_values( array_intersect( $input, $available ) );
    }

    /**
     * Return the best-known site URL for onboarding and platform links.
     *
     * @return string
     */
    public static function get_site_url() {
        return get_option( 'aeocas_real_site_url', site_url() );
    }

    /**
     * Build an account URL for onboarding or sign-in.
     *
     * @param string $intent start|signin
     * @return string
     */
    public static function get_connect_url( $intent = 'start' ) {
        $args = array(
            'intent'       => 'signin' === $intent ? 'signin' : 'start',
            'site_url'     => self::get_site_url(),
            'home_url'     => get_option( 'aeocas_real_home_url', home_url() ),
            'return_url'   => admin_url( 'admin.php?page=aeo-content-ai-studio' ),
            'utm_source'   => 'wordpress-plugin',
            'utm_medium'   => 'plugin',
            'utm_campaign' => 'wp-admin',
        );

        return add_query_arg( $args, trailingslashit( AEOCAS_ACCOUNT_URL ) . 'login' );
    }

    /**
     * Build an account URL for connected users who want to manage their account.
     *
     * @return string
     */
    public static function get_manage_url() {
        return add_query_arg(
            array(
                'utm_source'   => 'wordpress-plugin',
                'utm_medium'   => 'plugin',
                'utm_campaign' => 'wp-admin',
            ),
            trailingslashit( AEOCAS_ACCOUNT_URL ) . 'login'
        );
    }

    /**
     * Build the popup URL for Google-based connect flow.
     *
     * @return string
     */
    public static function get_google_connect_url() {
        // Ensure plugin_token exists before opening popup.
        $plugin_token = get_option( 'aeocas_plugin_token', '' );
        if ( empty( $plugin_token ) ) {
            $plugin_token = AEOCAS_Auth::generate_plugin_token();
            update_option( 'aeocas_plugin_token', $plugin_token, false );
        }

        return add_query_arg(
            array(
                'intent'       => 'google',
                'site_url'     => self::get_site_url(),
                'home_url'     => get_option( 'aeocas_real_home_url', home_url() ),
                'plugin_token' => $plugin_token,
                'return_url'   => admin_url( 'admin.php?page=aeo-content-ai-studio' ),
                'utm_source'   => 'wordpress-plugin',
                'utm_medium'   => 'plugin',
                'utm_campaign' => 'wp-admin',
            ),
            trailingslashit( AEOCAS_STUDIO_URL ) . 'login'
        );
    }

    /**
     * AJAX handler: store tokens received from the Google connect popup.
     */
    public function ajax_google_connect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_google_connect', 'nonce' );

        $site_token = isset( $_POST['site_token'] ) ? sanitize_text_field( wp_unslash( $_POST['site_token'] ) ) : '';

        if ( empty( $site_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing site token from platform.', 'aeo-content-ai-studio' ) ) );
        }

        update_option( 'aeocas_site_token', $site_token );
        update_option( 'aeocas_connection_verified', true );

        AEOCAS_Activity_Log::log( 'google_connect', 'success', array( 'message' => 'Site connected via Google sign-in.' ) );

        wp_send_json_success( array( 'message' => __( 'Connected successfully.', 'aeo-content-ai-studio' ) ) );
    }

    /**
     * Disconnect the current site from the platform.
     */
    public function handle_disconnect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'aeo-content-ai-studio' ) );
        }

        check_admin_referer( 'aeocas_disconnect' );

        delete_option( 'aeocas_site_token' );
        delete_option( 'aeocas_plugin_token' );
        delete_option( 'aeocas_connection_verified' );
        AEOCAS_Audit_Api::clear_cache();

        $redirect_url = add_query_arg(
            array(
                'page'          => 'aeo-content-ai-studio',
                'aeocas_notice' => 'disconnected',
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function enqueue_styles( $hook ) {
        $aeocas_pages = array(
            'toplevel_page_aeocas-audit-report',
            'aeo-content_page_aeo-content-ai-studio',
            'aeo-content_page_aeocas-activity-log',
        );
        if ( ! in_array( $hook, $aeocas_pages, true ) ) {
            return;
        }
        $asset_ver = AEOCAS_VERSION . '.' . filemtime( AEOCAS_PLUGIN_DIR . 'admin/css/admin.css' );
        wp_enqueue_style(
            'aeocas-admin',
            AEOCAS_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $asset_ver
        );

        // Google connect JS (settings page, disconnected state only).
        if ( 'aeo-content_page_aeo-content-ai-studio' === $hook ) {
            $connected = ! empty( get_option( 'aeocas_site_token', '' ) ) && get_option( 'aeocas_connection_verified', false );
            if ( ! $connected ) {
                $gc_ver = AEOCAS_VERSION . '.' . filemtime( AEOCAS_PLUGIN_DIR . 'admin/js/google-connect.js' );
                wp_enqueue_script( 'aeocas-google-connect', AEOCAS_PLUGIN_URL . 'admin/js/google-connect.js', array(), $gc_ver, true );
                wp_localize_script( 'aeocas-google-connect', 'aeocasGoogle', array(
                    'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                    'nonce'         => wp_create_nonce( 'aeocas_google_connect' ),
                    'connectUrl'    => self::get_google_connect_url(),
                    'accountOrigin' => esc_url_raw( rtrim( AEOCAS_STUDIO_URL, '/' ) ),
                    'i18n'          => array(
                        'waiting'    => __( 'Waiting for Google sign-in...', 'aeo-content-ai-studio' ),
                        'connecting' => __( 'Connecting your site...', 'aeo-content-ai-studio' ),
                        'success'    => __( 'Connected! Reloading...', 'aeo-content-ai-studio' ),
                        'error'      => __( 'Connection failed. Please try again.', 'aeo-content-ai-studio' ),
                    ),
                ) );
            }
        }

        // Audit page JS.
        if ( 'toplevel_page_aeocas-audit-report' === $hook ) {
            $js_ver = AEOCAS_VERSION . '.' . filemtime( AEOCAS_PLUGIN_DIR . 'admin/js/audit.js' );
            wp_enqueue_script(
                'aeocas-audit',
                AEOCAS_PLUGIN_URL . 'admin/js/audit.js',
                array(),
                $js_ver,
                true
            );
            wp_localize_script( 'aeocas-audit', 'aeocasAudit', array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'aeocas_audit_nonce' ),
                'favicon'  => get_site_icon_url( 48, '' ),
            ) );
        }
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include AEOCAS_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_activity_log() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include AEOCAS_PLUGIN_DIR . 'admin/views/activity-log-page.php';
    }

    public function render_audit_report() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        include AEOCAS_PLUGIN_DIR . 'admin/views/audit-page.php';
    }
}
