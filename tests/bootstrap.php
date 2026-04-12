<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'AEOCAS_ACCOUNT_URL' ) ) {
    define( 'AEOCAS_ACCOUNT_URL', 'https://account.aeocontent.ai' );
}

if ( ! defined( 'AEOCAS_PLATFORM_URL' ) ) {
    define( 'AEOCAS_PLATFORM_URL', 'https://www.aeocontent.ai' );
}

if ( ! defined( 'AEOCAS_STUDIO_URL' ) ) {
    define( 'AEOCAS_STUDIO_URL', 'https://studio.aeocontent.ai' );
}

if ( ! defined( 'AEOCAS_PLUGIN_FILE' ) ) {
    define( 'AEOCAS_PLUGIN_FILE', dirname( __DIR__ ) . '/aeo-content-ai-studio.php' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
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

function delete_option( $name ) {
    unset( $GLOBALS['aeocas_test_options'][ $name ] );
}

function plugin_basename( $file ) {
    return 'aeo-content-ai-studio/aeo-content-ai-studio.php';
}

$GLOBALS['aeocas_test_options'] = array();
$GLOBALS['aeocas_test_menu_page_args'] = null;
$GLOBALS['aeocas_test_transients'] = array();
$GLOBALS['aeocas_test_remote_get'] = null;
$GLOBALS['aeocas_test_remote_get_calls'] = array();

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

function get_home_url() {
    return 'https://helpsquad.com';
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

function wp_json_encode( $value ) {
    return json_encode( $value );
}

function sanitize_title( $value ) {
    $value = strtolower( (string) $value );
    $value = preg_replace( '/[^a-z0-9-]+/', '-', $value );
    return trim( $value, '-' );
}

function sanitize_key( $value ) {
    $value = strtolower( (string) $value );
    return preg_replace( '/[^a-z0-9_-]+/', '', $value );
}

function wp_strip_all_tags( $value ) {
    return trim( strip_tags( (string) $value ) );
}

function esc_url_raw( $url ) {
    return (string) $url;
}

function get_transient( $name ) {
    return array_key_exists( $name, $GLOBALS['aeocas_test_transients'] ) ? $GLOBALS['aeocas_test_transients'][ $name ] : false;
}

function set_transient( $name, $value, $expiration ) {
    $GLOBALS['aeocas_test_transients'][ $name ] = $value;
    return true;
}

function delete_transient( $name ) {
    unset( $GLOBALS['aeocas_test_transients'][ $name ] );
    return true;
}

function wp_remote_get( $url, $args = array() ) {
    $GLOBALS['aeocas_test_remote_get_calls'][] = array(
        'url'  => $url,
        'args' => $args,
    );

    if ( is_callable( $GLOBALS['aeocas_test_remote_get'] ) ) {
        return call_user_func( $GLOBALS['aeocas_test_remote_get'], $url, $args );
    }

    return new WP_Error( 'missing_stub', 'No wp_remote_get stub configured.' );
}

function wp_remote_retrieve_response_code( $response ) {
    return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
    return isset( $response['body'] ) ? (string) $response['body'] : '';
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        private $code;
        private $message;

        public function __construct( $code = '', $message = '' ) {
            $this->code    = $code;
            $this->message = $message;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

// Minimal AEOCAS_Auth stub for tests that call get_google_connect_url().
if ( ! class_exists( 'AEOCAS_Auth' ) ) {
    class AEOCAS_Auth {
        public static function generate_plugin_token() {
            return str_repeat( 'ab', 32 );
        }
    }
}

if ( ! class_exists( 'AEOCAS_Activity_Log' ) ) {
    class AEOCAS_Activity_Log {
        public static function log( $action, $level = 'info', $context = array() ) {
            return true;
        }
    }
}

require_once dirname( __DIR__ ) . '/includes/class-aeo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-aeo-audit-api.php';
