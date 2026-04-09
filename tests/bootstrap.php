<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'AEOCAS_ACCOUNT_URL' ) ) {
    define( 'AEOCAS_ACCOUNT_URL', 'https://account.aeocontent.ai' );
}

$GLOBALS['aeocas_test_options'] = array();

function get_option( $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS['aeocas_test_options'] ) ? $GLOBALS['aeocas_test_options'][ $name ] : $default;
}

function site_url() {
    return 'https://site.example';
}

function home_url() {
    return 'https://home.example';
}

function admin_url( $path = '' ) {
    return 'https://site.example/wp-admin/' . ltrim( $path, '/' );
}

function trailingslashit( $value ) {
    return rtrim( $value, '/' ) . '/';
}

function add_query_arg( $args, $url = '' ) {
    $query = http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );

    if ( '' === $url ) {
        return '?' . $query;
    }

    return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
}

function wp_parse_url( $url, $component = -1 ) {
    return parse_url( $url, $component );
}

require_once dirname( __DIR__ ) . '/includes/class-aeo-settings.php';
