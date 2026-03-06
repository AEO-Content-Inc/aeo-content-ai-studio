<?php
/**
 * API Key request authentication.
 *
 * Verifies that incoming REST requests originate from the AEO Content platform.
 * Expects header: x-api-key: <api_key>
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Auth {

    public function __construct() {
        // Nothing to hook - called statically from REST API class.
    }

    /**
     * Verify an incoming request by checking the x-api-key header.
     *
     * @param  WP_REST_Request $request The REST request.
     * @return true|WP_Error   True on success, WP_Error on failure.
     */
    public static function verify_request( $request ) {
        $stored_key = get_option( 'aeo_site_token', '' );
        if ( empty( $stored_key ) ) {
            return new WP_Error(
                'aeo_not_configured',
                'Plugin API key is not configured.',
                array( 'status' => 403 )
            );
        }

        $api_key = $request->get_header( 'x_api_key' );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'aeo_missing_api_key',
                'Missing x-api-key header.',
                array( 'status' => 401 )
            );
        }

        if ( ! hash_equals( $stored_key, $api_key ) ) {
            return new WP_Error(
                'aeo_invalid_api_key',
                'Invalid API key.',
                array( 'status' => 401 )
            );
        }

        return true;
    }
}
