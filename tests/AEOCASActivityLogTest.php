<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASActivityLogTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['wpdb']->inserts            = array();
        $GLOBALS['wpdb']->queries            = array();
        $GLOBALS['wpdb']->get_var_result     = 0;
        $GLOBALS['wpdb']->get_results_result = array();
        $GLOBALS['wpdb']->get_col_result     = array();
        $GLOBALS['aeocas_test_options']      = array();
        $GLOBALS['aeocas_test_filters']      = array();
        unset( $_SERVER['REMOTE_ADDR'] );
    }

    public function test_log_inserts_a_row_with_correct_data(): void {
        AEOCAS_Activity_Log::log( 'publish_post', 'success', array( 'message' => 'Published OK' ), 42 );

        $this->assertCount( 1, $GLOBALS['wpdb']->inserts );

        $row = $GLOBALS['wpdb']->inserts[0]['data'];
        $this->assertSame( 'publish_post', $row['command'] );
        $this->assertSame( 'success', $row['status'] );
        $this->assertSame( 42, $row['post_id'] );
    }

    public function test_log_defaults_to_success_status_for_invalid_status(): void {
        AEOCAS_Activity_Log::log( 'test_cmd', 'invalid_status' );

        $this->assertCount( 1, $GLOBALS['wpdb']->inserts );
        $this->assertSame( 'success', $GLOBALS['wpdb']->inserts[0]['data']['status'] );
    }

    public function test_log_stores_null_post_id_when_not_provided(): void {
        AEOCAS_Activity_Log::log( 'test_cmd', 'error', array( 'error' => 'fail' ) );

        $this->assertCount( 1, $GLOBALS['wpdb']->inserts );
        $this->assertNull( $GLOBALS['wpdb']->inserts[0]['data']['post_id'] );
    }

    public function test_log_stores_null_details_when_not_provided(): void {
        AEOCAS_Activity_Log::log( 'test_cmd' );

        $this->assertCount( 1, $GLOBALS['wpdb']->inserts );
        $this->assertNull( $GLOBALS['wpdb']->inserts[0]['data']['details'] );
    }

    public function test_log_redacts_request_and_response_bodies(): void {
        AEOCAS_Activity_Log::log(
            'heartbeat',
            'success',
            array(
                'request_body'  => array( 'payload' => str_repeat( 'a', 1200 ) ),
                'response_body' => str_repeat( 'b', 1200 ),
                'message'       => 'ok',
            )
        );

        $details = json_decode( $GLOBALS['wpdb']->inserts[0]['data']['details'], true );

        $this->assertSame( '[redacted]', $details['request_body'] );
        $this->assertSame( '[redacted]', $details['response_body'] );
        $this->assertSame( 'ok', $details['message'] );
    }

    public function test_log_does_not_store_ip_by_default(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.20';

        AEOCAS_Activity_Log::log( 'test_cmd', 'success', array( 'message' => 'ok' ) );

        $this->assertNull( $GLOBALS['wpdb']->inserts[0]['data']['ip'] );
    }

    public function test_get_logs_returns_paginated_structure(): void {
        $GLOBALS['wpdb']->get_var_result     = 5;
        $GLOBALS['wpdb']->get_results_result = array(
            array( 'id' => '1', 'created_at' => '2026-04-12', 'command' => 'test', 'status' => 'success', 'details' => null, 'post_id' => null, 'ip' => null ),
        );

        $result = AEOCAS_Activity_Log::get_logs( 1, 25 );

        $this->assertArrayHasKey( 'items', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'pages', $result );
        $this->assertSame( 5, $result['total'] );
    }

    public function test_get_logs_decodes_json_details(): void {
        $GLOBALS['wpdb']->get_var_result     = 1;
        $GLOBALS['wpdb']->get_results_result = array(
            array( 'id' => '1', 'created_at' => '2026-04-12', 'command' => 'test', 'status' => 'success', 'details' => '{"message":"ok"}', 'post_id' => null, 'ip' => null ),
        );

        $result = AEOCAS_Activity_Log::get_logs();

        $this->assertIsArray( $result['items'][0]['details'] );
        $this->assertSame( 'ok', $result['items'][0]['details']['message'] );
    }

    public function test_get_logs_applies_filters(): void {
        $GLOBALS['wpdb']->get_var_result     = 0;
        $GLOBALS['wpdb']->get_results_result = array();

        $result = AEOCAS_Activity_Log::get_logs( 1, 25, array(
            'command'   => 'publish_post',
            'status'    => 'error',
            'date_from' => '2026-01-01',
            'date_to'   => '2026-12-31',
        ) );

        $this->assertSame( 0, $result['total'] );
    }

    public function test_get_stats_returns_expected_keys(): void {
        $GLOBALS['wpdb']->get_var_result = 10;

        $stats = AEOCAS_Activity_Log::get_stats();

        $this->assertArrayHasKey( 'total', $stats );
        $this->assertArrayHasKey( 'success', $stats );
        $this->assertArrayHasKey( 'error', $stats );
        $this->assertArrayHasKey( 'success_rate', $stats );
        $this->assertArrayHasKey( 'last_action', $stats );
        $this->assertArrayHasKey( 'last_24h', $stats );
    }

    public function test_get_commands_returns_array(): void {
        $GLOBALS['wpdb']->get_col_result = array( 'publish_post', 'heartbeat' );

        $commands = AEOCAS_Activity_Log::get_commands();

        $this->assertSame( array( 'publish_post', 'heartbeat' ), $commands );
    }

    public function test_get_all_for_export_uses_get_logs(): void {
        $GLOBALS['wpdb']->get_var_result     = 2;
        $GLOBALS['wpdb']->get_results_result = array(
            array( 'id' => '1', 'created_at' => '2026-04-12', 'command' => 'a', 'status' => 'success', 'details' => null, 'post_id' => null, 'ip' => null ),
            array( 'id' => '2', 'created_at' => '2026-04-12', 'command' => 'b', 'status' => 'error', 'details' => null, 'post_id' => null, 'ip' => null ),
        );

        $items = AEOCAS_Activity_Log::get_all_for_export();

        $this->assertCount( 2, $items );
    }

    public function test_cleanup_executes_query(): void {
        AEOCAS_Activity_Log::cleanup();

        $this->assertNotEmpty( $GLOBALS['wpdb']->queries );
    }

    public function test_clear_all_executes_query(): void {
        AEOCAS_Activity_Log::clear_all();

        $this->assertNotEmpty( $GLOBALS['wpdb']->queries );
    }

    public function test_schedule_cleanup_calls_wp_schedule_event(): void {
        // wp_next_scheduled returns false, so schedule_event should be called.
        // We just verify it runs without error.
        AEOCAS_Activity_Log::schedule_cleanup();
        $this->assertTrue( true );
    }

    public function test_handle_rest_logs_returns_response(): void {
        $GLOBALS['wpdb']->get_var_result     = 0;
        $GLOBALS['wpdb']->get_results_result = array();

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'page', 1 );
        $request->set_param( 'per_page', 10 );

        $response = AEOCAS_Activity_Log::handle_rest_logs( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertArrayHasKey( 'logs', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'stats', $data );
    }

    public function test_create_table_runs_without_error(): void {
        AEOCAS_Activity_Log::create_table();

        // Verify that the db version option was set.
        $this->assertSame( '1.0', $GLOBALS['aeocas_test_options']['aeocas_activity_log_db_version'] );
    }

    public function test_handle_csv_export_with_missing_nonce_returns_early(): void {
        $_GET['aeocas_export_logs'] = '1';
        unset( $_GET['_wpnonce'] );

        // Should return early after nonce check fails.
        AEOCAS_Activity_Log::handle_csv_export();
        $this->assertTrue( true );

        unset( $_GET['aeocas_export_logs'] );
    }

    public function test_handle_csv_export_returns_early_when_no_param(): void {
        // Clear $_GET to ensure the early return is triggered.
        unset( $_GET['aeocas_export_logs'] );

        // Should return early without any side effects.
        AEOCAS_Activity_Log::handle_csv_export();
        $this->assertTrue( true );
    }

    public function test_escape_csv_cell_prefixes_formula_like_values(): void {
        $method = aeocas_make_reflection_method_accessible( new ReflectionMethod( AEOCAS_Activity_Log::class, 'escape_csv_cell' ) );

        $this->assertSame( "'=SUM(A1:A2)", $method->invoke( null, '=SUM(A1:A2)' ) );
        $this->assertSame( "' +HYPERLINK(\"https://example.com\")", $method->invoke( null, ' +HYPERLINK("https://example.com")' ) );
        $this->assertSame( 'safe-value', $method->invoke( null, 'safe-value' ) );
    }

    public function test_handle_rest_logs_with_filters(): void {
        $GLOBALS['wpdb']->get_var_result     = 0;
        $GLOBALS['wpdb']->get_results_result = array();

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'command', 'publish_post' );
        $request->set_param( 'status', 'error' );
        $request->set_param( 'date_from', '2026-01-01' );
        $request->set_param( 'date_to', '2026-12-31' );

        $response = AEOCAS_Activity_Log::handle_rest_logs( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
    }
}
