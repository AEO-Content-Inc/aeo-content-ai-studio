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
				__( 'Site connection is not configured. Connect your site in Settings first.', 'aeo-content-ai-studio' ),
				array( 'status' => 403 )
			);
		}

		$timestamp = $request->get_header( 'x_aeocas_timestamp' );
		$signature = $request->get_header( 'x_aeocas_signature' );

		if ( ! empty( $timestamp ) || ! empty( $signature ) ) {
			return self::verify_signed_request( $request, $plugin_token, $timestamp, $signature );
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
				__( 'Invalid site credential.', 'aeo-content-ai-studio' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Verify an HMAC-signed request using the stored plugin token as the secret.
	 *
	 * @param WP_REST_Request $request      The REST request.
	 * @param string          $plugin_token Stored plugin token.
	 * @param string|null     $timestamp    Request timestamp header.
	 * @param string|null     $signature    Request signature header.
	 * @return true|WP_Error
	 */
	private static function verify_signed_request( $request, $plugin_token, $timestamp, $signature ) {
		if ( empty( $timestamp ) || empty( $signature ) ) {
			return new WP_Error(
				'aeocas_missing_signature',
				__( 'Missing signed request headers.', 'aeo-content-ai-studio' ),
				array( 'status' => 401 )
			);
		}

		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return new WP_Error(
				'aeocas_invalid_timestamp',
				__( 'Invalid signed request timestamp.', 'aeo-content-ai-studio' ),
				array( 'status' => 401 )
			);
		}

		if ( abs( time() - $timestamp ) > ( 5 * MINUTE_IN_SECONDS ) ) {
			return new WP_Error(
				'aeocas_expired_signature',
				__( 'Signed request timestamp is outside the allowed window.', 'aeo-content-ai-studio' ),
				array( 'status' => 401 )
			);
		}

		$body = method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '';
		$hash = self::generate_request_signature( $plugin_token, $timestamp, $body );

		if ( ! hash_equals( $hash, (string) $signature ) ) {
			return new WP_Error(
				'aeocas_invalid_signature',
				__( 'Invalid request signature.', 'aeo-content-ai-studio' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Generate an HMAC signature for a request payload.
	 *
	 * @param string $plugin_token Shared secret.
	 * @param int    $timestamp    Unix timestamp.
	 * @param string $body         Raw request body.
	 * @return string
	 */
	public static function generate_request_signature( $plugin_token, $timestamp, $body ) {
		return hash_hmac( 'sha256', $timestamp . '.' . (string) $body, (string) $plugin_token );
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
