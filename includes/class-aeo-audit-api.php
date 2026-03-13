<?php
/**
 * Audit API client.
 *
 * Fetches audit data from the AEO Content platform using the stored API key.
 * Results are cached in a transient to avoid excessive API calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Audit_Api {

    /** @var int Cache lifetime in seconds (1 hour). */
    const CACHE_TTL = HOUR_IN_SECONDS;

    /** @var string Transient key prefix. */
    const TRANSIENT_PREFIX = 'aeo_audit_';

    /**
     * Get the audit slug for this site.
     *
     * Converts hostname to slug format: wptest.datasub.com → wptest-datasub-com
     *
     * @return string
     */
    public static function get_site_slug() {
        $home = wp_parse_url( get_home_url(), PHP_URL_HOST );
        if ( ! $home ) {
            return '';
        }
        // Remove www. prefix.
        $home = preg_replace( '/^www\./', '', $home );
        return sanitize_title( str_replace( '.', '-', $home ) );
    }

    /**
     * Fetch audit data from the platform API.
     *
     * @param bool $force_refresh Skip cache and fetch fresh data.
     * @return array|WP_Error Audit data array or WP_Error on failure.
     */
    public static function get_audit( $force_refresh = false ) {
        $api_key = get_option( 'aeo_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeo_no_key', __( 'API key is not configured. Go to Settings to enter your API key.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeo_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $transient_key = self::TRANSIENT_PREFIX . $slug;

        // Check cache.
        if ( ! $force_refresh ) {
            $cached = get_transient( $transient_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Fetch from platform.
        $url = trailingslashit( AEO_PLATFORM_URL ) . 'api/v1/audits/' . $slug . '?include=fix_prompts';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeo_api_error', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 404 === $status ) {
            return new WP_Error( 'aeo_no_audit', __( 'No audit found for this site. Request an audit at aeocontent.ai.', 'aeo-content-ai-studio' ) );
        }

        if ( 401 === $status || 403 === $status ) {
            return new WP_Error( 'aeo_auth_error', __( 'API key is invalid or does not have read permission.', 'aeo-content-ai-studio' ) );
        }

        if ( 200 !== $status || empty( $body['data'] ) ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unexpected API response.', 'aeo-content-ai-studio' );
            return new WP_Error( 'aeo_api_error', $message );
        }

        // The API returns an array when no engine param, or a single object with engine param.
        // We always use the first audit (or the single object).
        $audit = is_array( $body['data'] ) && isset( $body['data'][0] ) ? $body['data'][0] : $body['data'];

        // Cache the result.
        set_transient( $transient_key, $audit, self::CACHE_TTL );

        return $audit;
    }

    /**
     * Clear cached audit data.
     */
    public static function clear_cache() {
        $slug = self::get_site_slug();
        if ( $slug ) {
            delete_transient( self::TRANSIENT_PREFIX . $slug );
        }
    }

    /**
     * AJAX handler for fetching audit data.
     */
    public static function ajax_get_audit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeo_audit_nonce', 'nonce' );

        $force = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $audit = self::get_audit( $force );

        if ( is_wp_error( $audit ) ) {
            wp_send_json_error( array( 'message' => $audit->get_error_message() ) );
        }

        wp_send_json_success( $audit );
    }

    /**
     * Trigger a re-audit on the platform.
     *
     * @return array|WP_Error Response data or error.
     */
    public static function trigger_reaudit() {
        $api_key = get_option( 'aeo_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeo_no_key', __( 'API key is not configured.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeo_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $url = trailingslashit( AEO_PLATFORM_URL ) . 'api/v1/audits/' . $slug . '/reaudit';

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => '{}',
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeo_api_error', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( isset( $body['message'] ) ? $body['message'] : __( 'Failed to trigger re-audit.', 'aeo-content-ai-studio' ) );
            return new WP_Error( 'aeo_reaudit_error', $message );
        }

        // Clear cached audit so next load fetches fresh data.
        self::clear_cache();

        AEO_Activity_Log::log( 'reaudit', 'success', array( 'message' => 'Re-audit triggered.', 'slug' => $slug ) );

        return $body;
    }

    /**
     * Get audit job status from platform.
     *
     * @return array|WP_Error Status data or error.
     */
    public static function get_audit_status() {
        $api_key = get_option( 'aeo_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeo_no_key', __( 'API key is not configured.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeo_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $url = trailingslashit( AEO_PLATFORM_URL ) . 'api/v1/audits/' . $slug . '/status';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeo_api_error', $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body;
    }

    /**
     * AJAX handler for triggering re-audit.
     */
    public static function ajax_reaudit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeo_audit_nonce', 'nonce' );

        $result = self::trigger_reaudit();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for polling audit status.
     */
    public static function ajax_audit_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeo_audit_nonce', 'nonce' );

        $result = self::get_audit_status();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * Register AJAX hooks.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_aeo_get_audit', array( __CLASS__, 'ajax_get_audit' ) );
        add_action( 'wp_ajax_aeo_reaudit', array( __CLASS__, 'ajax_reaudit' ) );
        add_action( 'wp_ajax_aeo_audit_status', array( __CLASS__, 'ajax_audit_status' ) );
    }
}
