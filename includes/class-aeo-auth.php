<?php
/**
 * Plugin Token request authentication.
 *
 * Verifies that incoming REST requests originate from the AEO Content platform.
 * The plugin generates a unique plugin_token during registration and shares it
 * with the platform. The platform sends this token in the x-api-key header.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEOCAS_Auth {

    public function __construct() {
        // Nothing to hook - called statically from REST API class.
    }

    /**
     * Verify an incoming request by checking the x-api-key header
     * against the stored plugin token.
     *
     * @param  WP_REST_Request $request The REST request.
     * @return true|WP_Error   True on success, WP_Error on failure.
     */
    public static function verify_request( $request ) {
        $plugin_token = get_option( 'aeocas_plugin_token', '' );
        if ( empty( $plugin_token ) ) {
            return new WP_Error(
                'aeocas_not_configured',
                __( 'Plugin token is not configured. Please save your API Key in Settings to register with the platform.', 'aeo-content-ai-studio' ),
                array( 'status' => 403 )
            );
        }

        $api_key = $request->get_header( 'x_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'aeocas_missing_api_key',
                __( 'Missing x-api-key header.', 'aeo-content-ai-studio' ),
                array( 'status' => 401 )
            );
        }

        if ( ! hash_equals( $plugin_token, $api_key ) ) {
            return new WP_Error(
                'aeocas_invalid_api_key',
                __( 'Invalid API key.', 'aeo-content-ai-studio' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Generate a cryptographically secure plugin token.
     *
     * @return string 64-character hex string (32 random bytes).
     */
    public static function generate_plugin_token() {
        return bin2hex( random_bytes( 32 ) );
    }
}
