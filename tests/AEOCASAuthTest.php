<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASAuthTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['aeocas_test_options'] = array();
    }

    public function test_verify_request_returns_error_when_no_token_configured(): void {
        $request = new WP_REST_Request( 'POST' );
        $request->set_header( 'x_api_key', 'some-key' );

        $result = AEOCAS_Auth::verify_request( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_not_configured', $result->get_error_code() );
    }

    public function test_verify_request_returns_error_when_header_missing(): void {
        $GLOBALS['aeocas_test_options']['aeocas_plugin_token'] = 'valid-token-abc';

        $request = new WP_REST_Request( 'POST' );
        // No x_api_key header set.

        $result = AEOCAS_Auth::verify_request( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_missing_api_key', $result->get_error_code() );
    }

    public function test_verify_request_returns_error_when_key_invalid(): void {
        $GLOBALS['aeocas_test_options']['aeocas_plugin_token'] = 'valid-token-abc';

        $request = new WP_REST_Request( 'POST' );
        $request->set_header( 'x_api_key', 'wrong-key' );

        $result = AEOCAS_Auth::verify_request( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_invalid_api_key', $result->get_error_code() );
    }

    public function test_verify_request_returns_true_when_key_matches(): void {
        $token = 'valid-token-abc';
        $GLOBALS['aeocas_test_options']['aeocas_plugin_token'] = $token;

        $request = new WP_REST_Request( 'POST' );
        $request->set_header( 'x_api_key', $token );

        $result = AEOCAS_Auth::verify_request( $request );

        $this->assertTrue( $result );
    }

    public function test_constructor_runs_without_error(): void {
        $auth = new AEOCAS_Auth();
        $this->assertInstanceOf( AEOCAS_Auth::class, $auth );
    }

    public function test_generate_plugin_token_returns_64_char_hex(): void {
        $token = AEOCAS_Auth::generate_plugin_token();

        $this->assertSame( 64, strlen( $token ) );
        $this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $token );
    }
}
