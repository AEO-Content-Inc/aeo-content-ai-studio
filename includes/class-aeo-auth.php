<?php
/**
 * HMAC-SHA256 request authentication.
 *
 * Verifies that incoming REST requests originate from the AEO Content platform.
 * Signature format: X-AEO-Signature: ts=<unix>,sig=<hex>
 * Computed as: HMAC-SHA256( timestamp + "." + raw_body, shared_secret )
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Auth {

    /** Maximum age (seconds) for a request before it's considered stale. */
    const MAX_AGE = 300; // 5 minutes

    public function __construct() {
        // Nothing to hook - called statically from REST API class.
    }

    /**
     * Verify an incoming request.
     *
     * @param  WP_REST_Request $request The REST request.
     * @return true|WP_Error   True on success, WP_Error on failure.
     */
    public static function verify_request( $request ) {
        $secret = get_option( 'aeo_site_token', '' );
        if ( empty( $secret ) ) {
            return new WP_Error(
                'aeo_not_configured',
                'Plugin site token is not configured.',
                array( 'status' => 403 )
            );
        }

        $header = $request->get_header( 'X-AEO-Signature' );
        if ( empty( $header ) ) {
            return new WP_Error(
                'aeo_missing_signature',
                'Missing X-AEO-Signature header.',
                array( 'status' => 401 )
            );
        }

        // Parse "ts=<unix>,sig=<hex>".
        $parts = array();
        foreach ( explode( ',', $header ) as $pair ) {
            $kv = explode( '=', $pair, 2 );
            if ( 2 === count( $kv ) ) {
                $parts[ trim( $kv[0] ) ] = trim( $kv[1] );
            }
        }

        if ( empty( $parts['ts'] ) || empty( $parts['sig'] ) ) {
            return new WP_Error(
                'aeo_invalid_signature',
                'Malformed X-AEO-Signature header.',
                array( 'status' => 401 )
            );
        }

        $timestamp = intval( $parts['ts'] );
        $signature = $parts['sig'];

        // Reject stale requests.
        if ( abs( time() - $timestamp ) > self::MAX_AGE ) {
            return new WP_Error(
                'aeo_stale_request',
                'Request timestamp is too old or too far in the future.',
                array( 'status' => 401 )
            );
        }

        // Compute expected signature.
        $body    = $request->get_body();
        $payload = $timestamp . '.' . $body;
        $expected = hash_hmac( 'sha256', $payload, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new WP_Error(
                'aeo_bad_signature',
                'Invalid request signature.',
                array( 'status' => 401 )
            );
        }

        return true;
    }

    /**
     * Generate an HMAC signature for an outgoing request (used by heartbeat).
     *
     * @param  string $body   JSON body.
     * @param  string $secret Shared secret.
     * @return string Signature header value: "ts=<unix>,sig=<hex>".
     */
    public static function sign_request( $body, $secret ) {
        $timestamp = time();
        $payload   = $timestamp . '.' . $body;
        $signature = hash_hmac( 'sha256', $payload, $secret );
        return 'ts=' . $timestamp . ',sig=' . $signature;
    }
}
