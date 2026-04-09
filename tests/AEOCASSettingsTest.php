<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASSettingsTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['aeocas_test_options'] = array(
            'aeocas_real_site_url' => 'https://captured.example',
            'aeocas_real_home_url' => 'https://home-captured.example',
        );
    }

    public function test_get_site_url_prefers_captured_site_url(): void {
        $this->assertSame( 'https://captured.example', AEOCAS_Settings::get_site_url() );
    }

    public function test_get_connect_url_builds_start_intent_url(): void {
        $url = AEOCAS_Settings::get_connect_url( 'start' );
        $parts = wp_parse_url( $url );

        parse_str( $parts['query'], $query );

        $this->assertSame( '/login', $parts['path'] );
        $this->assertSame( 'start', $query['intent'] );
        $this->assertSame( 'https://captured.example', $query['site_url'] );
        $this->assertSame( 'https://home-captured.example', $query['home_url'] );
        $this->assertSame( 'https://site.example/wp-admin/admin.php?page=aeo-content-ai-studio', $query['return_url'] );
        $this->assertSame( 'wordpress-plugin', $query['utm_source'] );
        $this->assertSame( 'plugin', $query['utm_medium'] );
        $this->assertSame( 'wp-admin', $query['utm_campaign'] );
    }

    public function test_get_connect_url_builds_signin_intent_url(): void {
        $url = AEOCAS_Settings::get_connect_url( 'signin' );
        $parts = wp_parse_url( $url );

        parse_str( $parts['query'], $query );

        $this->assertSame( 'signin', $query['intent'] );
    }

    public function test_get_connect_url_defaults_unknown_intent_to_start(): void {
        $url = AEOCAS_Settings::get_connect_url( 'unknown' );
        $parts = wp_parse_url( $url );

        parse_str( $parts['query'], $query );

        $this->assertSame( 'start', $query['intent'] );
    }

    public function test_get_manage_url_omits_intent_and_site_context(): void {
        $url = AEOCAS_Settings::get_manage_url();
        $parts = wp_parse_url( $url );

        parse_str( $parts['query'], $query );

        $this->assertSame( '/login', $parts['path'] );
        $this->assertArrayNotHasKey( 'intent', $query );
        $this->assertArrayNotHasKey( 'site_url', $query );
        $this->assertSame( 'wordpress-plugin', $query['utm_source'] );
        $this->assertSame( 'plugin', $query['utm_medium'] );
        $this->assertSame( 'wp-admin', $query['utm_campaign'] );
    }
}
