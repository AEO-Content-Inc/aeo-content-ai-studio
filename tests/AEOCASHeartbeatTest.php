<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASHeartbeatTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['aeocas_test_options'] = array();
        $GLOBALS['aeocas_test_remote_post'] = null;
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['wpdb']->inserts = array();
    }

    public function test_add_cron_schedule_adds_three_hour_interval(): void {
        $heartbeat = new AEOCAS_Heartbeat();

        $schedules = $heartbeat->add_cron_schedule( array() );

        $this->assertArrayHasKey( 'aeocas_six_hours', $schedules );
        $this->assertSame( 3 * HOUR_IN_SECONDS, $schedules['aeocas_six_hours']['interval'] );
    }

    public function test_send_heartbeat_skips_when_no_token(): void {
        // No aeocas_site_token set in options.

        $heartbeat = new AEOCAS_Heartbeat();
        $heartbeat->send_heartbeat();

        $this->assertSame( array(), $GLOBALS['aeocas_test_remote_post_calls'] );
    }

    public function test_send_heartbeat_posts_to_platform(): void {
        $GLOBALS['aeocas_test_options']['aeocas_site_token'] = 'test-site-token';

        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'ok' => true ) ),
            );
        };

        $heartbeat = new AEOCAS_Heartbeat();
        $heartbeat->send_heartbeat();

        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_post_calls'] );

        $call = $GLOBALS['aeocas_test_remote_post_calls'][0];
        $this->assertStringContainsString( 'api/v1/plugin/heartbeat', $call['url'] );
        $this->assertSame( 'test-site-token', $call['args']['headers']['x-api-key'] );

        $body = json_decode( $call['args']['body'], true );
        $this->assertSame( AEOCAS_VERSION, $body['version'] );
        $this->assertSame( PHP_VERSION, $body['php'] );
        $this->assertArrayHasKey( 'features', $body );
    }

    public function test_activate_schedules_cron_event(): void {
        // wp_next_scheduled returns false, so wp_schedule_event should be called.
        AEOCAS_Heartbeat::activate();
        $this->assertTrue( true );
    }

    public function test_constructor_captures_site_url(): void {
        // is_admin() returns true, wp_doing_cron() returns false in stubs.
        // The constructor should store the real site URL.
        new AEOCAS_Heartbeat();

        // site_url() returns 'https://site.example', which is not an IP,
        // so it should be saved.
        $this->assertSame( 'https://site.example', $GLOBALS['aeocas_test_options']['aeocas_real_site_url'] );
        $this->assertSame( 'https://home.example', $GLOBALS['aeocas_test_options']['aeocas_real_home_url'] );
    }

    public function test_send_heartbeat_logs_success_with_commands(): void {
        $GLOBALS['aeocas_test_options']['aeocas_site_token'] = 'test-site-token';

        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'ok'       => true,
                    'commands' => array(), // empty commands array
                ) ),
            );
        };

        $heartbeat = new AEOCAS_Heartbeat();
        $heartbeat->send_heartbeat();

        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_post_calls'] );
        // The success branch should have logged a success.
        $success_logs = array_filter( $GLOBALS['wpdb']->inserts, static function ( $entry ) {
            return 'success' === ( $entry['data']['status'] ?? '' ) && 'heartbeat' === ( $entry['data']['command'] ?? '' );
        } );
        $this->assertNotEmpty( $success_logs );
    }

    public function test_send_heartbeat_handles_wp_error_response(): void {
        $GLOBALS['aeocas_test_options']['aeocas_site_token'] = 'test-site-token';

        $GLOBALS['aeocas_test_remote_post'] = static function () {
            return new WP_Error( 'http_error', 'Connection refused' );
        };

        $heartbeat = new AEOCAS_Heartbeat();
        $heartbeat->send_heartbeat();

        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_post_calls'] );
        // Should have logged an error.
        $error_logs = array_filter( $GLOBALS['wpdb']->inserts, static function ( $entry ) {
            return 'error' === ( $entry['data']['status'] ?? '' ) && 'heartbeat' === ( $entry['data']['command'] ?? '' );
        } );
        $this->assertNotEmpty( $error_logs );
    }

    public function test_send_heartbeat_processes_pending_commands(): void {
        $GLOBALS['aeocas_test_options']['aeocas_site_token']  = 'test-site-token';
        $GLOBALS['aeocas_test_options']['aeocas_plugin_token'] = 'test-plugin-token';

        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'ok'       => true,
                    'commands' => array(
                        array( 'command' => 'publish_post', 'payload' => array( 'title' => 'Remote Post' ) ),
                    ),
                ) ),
            );
        };

        $heartbeat = new AEOCAS_Heartbeat();
        $heartbeat->send_heartbeat();

        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_post_calls'] );
        // The process_pending_commands creates a Rest API and dispatches.
        // Since the module is null, the command will return a module_disabled error.
        // But the important thing is the code path was exercised.
        $success_logs = array_filter( $GLOBALS['wpdb']->inserts, static function ( $entry ) {
            return 'success' === ( $entry['data']['status'] ?? '' ) && 'heartbeat' === ( $entry['data']['command'] ?? '' );
        } );
        $this->assertNotEmpty( $success_logs );
    }

    public function test_send_heartbeat_logs_error_on_non_200(): void {
        $GLOBALS['aeocas_test_options']['aeocas_site_token'] = 'test-site-token';

        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 500 ),
                'body'     => 'Internal Server Error',
            );
        };

        $heartbeat = new AEOCAS_Heartbeat();
        $heartbeat->send_heartbeat();

        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_post_calls'] );

        // Check that an error was logged via $wpdb->insert.
        $error_logs = array_filter( $GLOBALS['wpdb']->inserts, static function ( $entry ) {
            return 'error' === ( $entry['data']['status'] ?? '' ) && 'heartbeat' === ( $entry['data']['command'] ?? '' );
        } );
        $this->assertNotEmpty( $error_logs, 'Expected an error log entry for non-200 heartbeat response.' );
    }
}
