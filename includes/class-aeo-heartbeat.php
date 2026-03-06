<?php
/**
 * Heartbeat module. Pings AEO Content platform every 6 hours.
 *
 * Reports plugin version, active features, WP version.
 * Receives any pending commands that failed push delivery.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Heartbeat {

    const CRON_HOOK     = 'aeo_heartbeat_event';
    const INTERVAL_NAME = 'aeo_six_hours';

    public function __construct() {
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
        add_action( self::CRON_HOOK, array( $this, 'send_heartbeat' ) );
    }

    /**
     * Schedule the heartbeat cron event. Called from activation hook.
     */
    public static function activate() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), self::INTERVAL_NAME, self::CRON_HOOK );
        }
    }

    /**
     * Add a 6-hour interval to WP-Cron.
     */
    public function add_cron_schedule( $schedules ) {
        $schedules[ self::INTERVAL_NAME ] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Every 6 hours',
        );
        return $schedules;
    }

    /**
     * Send heartbeat to platform.
     */
    public function send_heartbeat() {
        $platform_url = AEO_PLATFORM_URL;
        $api_key      = get_option( 'aeo_site_token', '' );

        if ( empty( $api_key ) || empty( $platform_url ) ) {
            return;
        }

        global $wp_version;

        $body = wp_json_encode( array(
            'site_url'  => get_site_url(),
            'home_url'  => get_home_url(),
            'version'   => AEO_VERSION,
            'wp'        => $wp_version,
            'php'       => PHP_VERSION,
            'features'  => aeo_content_ai_studio()->get_enabled_features(),
            'timestamp' => time(),
        ) );

        $response = wp_remote_post(
            trailingslashit( $platform_url ) . 'api/v1/plugin/heartbeat',
            array(
                'body'    => $body,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key'   => $api_key,
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            return;
        }

        // Process any pending commands returned by platform.
        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $result['commands'] ) && is_array( $result['commands'] ) ) {
            $this->process_pending_commands( $result['commands'] );
        }
    }

    /**
     * Execute pending commands received from platform.
     */
    private function process_pending_commands( $commands ) {
        $rest_api = new AEO_Rest_Api( aeo_content_ai_studio() );

        foreach ( $commands as $cmd ) {
            if ( empty( $cmd['command'] ) ) {
                continue;
            }

            // Build a synthetic WP_REST_Request for the command dispatch.
            $request = new WP_REST_Request( 'POST' );
            $request->set_body( wp_json_encode( $cmd ) );
            $request->set_header( 'Content-Type', 'application/json' );
            $request->set_param( 'command', $cmd['command'] );
            $request->set_param( 'payload', isset( $cmd['payload'] ) ? $cmd['payload'] : array() );

            $rest_api->handle_command( $request );
        }
    }
}
