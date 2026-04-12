<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASSettingsTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['aeocas_test_options'] = array(
            'aeocas_real_site_url' => 'https://captured.example',
            'aeocas_real_home_url' => 'https://home-captured.example',
        );
        $GLOBALS['aeocas_test_menu_page_args'] = null;
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

    public function test_get_google_connect_url_uses_studio_domain(): void {
        $url = AEOCAS_Settings::get_google_connect_url();
        $parts = wp_parse_url( $url );

        $this->assertSame( 'studio.aeocontent.ai', $parts['host'] );
        $this->assertSame( '/wp-connect', $parts['path'] );
    }

    public function test_get_google_connect_url_includes_site_params(): void {
        $url = AEOCAS_Settings::get_google_connect_url();
        $parts = wp_parse_url( $url );

        parse_str( $parts['query'], $query );

        $this->assertSame( 'https://captured.example', $query['site_url'] );
        $this->assertSame( 'https://home-captured.example', $query['home_url'] );
        $this->assertStringContainsString( 'admin.php', $query['return_url'] );
    }

    public function test_get_google_connect_url_includes_plugin_token(): void {
        $url = AEOCAS_Settings::get_google_connect_url();
        $parts = wp_parse_url( $url );

        parse_str( $parts['query'], $query );

        $this->assertArrayHasKey( 'plugin_token', $query );
        $this->assertNotEmpty( $query['plugin_token'] );
    }

    public function test_get_google_connect_url_generates_token_when_missing(): void {
        // Ensure no token exists.
        unset( $GLOBALS['aeocas_test_options']['aeocas_plugin_token'] );

        $url = AEOCAS_Settings::get_google_connect_url();

        // Token should now be stored.
        $this->assertNotEmpty( $GLOBALS['aeocas_test_options']['aeocas_plugin_token'] ?? '' );
    }

    public function test_add_menu_uses_inline_svg_favicon_for_admin_sidebar(): void {
        $settings = new AEOCAS_Settings();

        $settings->add_menu();

        $this->assertIsArray( $GLOBALS['aeocas_test_menu_page_args'] );
        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $GLOBALS['aeocas_test_menu_page_args']['icon_url']
        );
    }

    public function test_menu_icon_svg_matches_the_site_favicon_palette(): void {
        $method = new ReflectionMethod( AEOCAS_Settings::class, 'get_menu_icon_data_uri' );
        $method->setAccessible( true );

        $icon = $method->invoke( null );
        $svg  = base64_decode( substr( $icon, strlen( 'data:image/svg+xml;base64,' ) ), true );

        $this->assertIsString( $svg );
        $this->assertStringContainsString( '<svg', $svg );
        $this->assertStringContainsString( '#121313', $svg );
        $this->assertStringContainsString( '#A03EE6', $svg );
        $this->assertStringContainsString( '<rect', $svg );
    }
}
