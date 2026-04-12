<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'AEOCAS_ACCOUNT_URL' ) ) {
    define( 'AEOCAS_ACCOUNT_URL', 'https://account.aeocontent.ai' );
}

if ( ! defined( 'AEOCAS_STUDIO_URL' ) ) {
    define( 'AEOCAS_STUDIO_URL', 'https://studio.aeocontent.ai' );
}

if ( ! defined( 'AEOCAS_PLUGIN_FILE' ) ) {
    define( 'AEOCAS_PLUGIN_FILE', dirname( __DIR__ ) . '/aeo-content-ai-studio.php' );
}

function __( $text, $domain = null ) {
    return $text;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    return true;
}

function update_option( $name, $value, $autoload = null ) {
    $GLOBALS['aeocas_test_options'][ $name ] = $value;
}

function plugin_basename( $file ) {
    return 'aeo-content-ai-studio/aeo-content-ai-studio.php';
}

$GLOBALS['aeocas_test_options'] = array();
$GLOBALS['aeocas_test_menu_page_args'] = null;

function get_option( $name, $default = false ) {
    return array_key_exists( $name, $GLOBALS['aeocas_test_options'] ) ? $GLOBALS['aeocas_test_options'][ $name ] : $default;
}

function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
    $GLOBALS['aeocas_test_menu_page_args'] = array(
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $capability,
        'menu_slug'  => $menu_slug,
        'callback'   => $callback,
        'icon_url'   => $icon_url,
        'position'   => $position,
    );

    return $menu_slug;
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

// Minimal AEOCAS_Auth stub for tests that call get_google_connect_url().
if ( ! class_exists( 'AEOCAS_Auth' ) ) {
    class AEOCAS_Auth {
        public static function generate_plugin_token() {
            return str_repeat( 'ab', 32 );
        }
    }
}

require_once dirname( __DIR__ ) . '/includes/class-aeo-settings.php';
