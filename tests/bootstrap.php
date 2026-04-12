<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
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

if ( ! defined( 'AEOCAS_PLUGIN_DIR' ) ) {
    define( 'AEOCAS_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
    define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'AEOCAS_VERSION' ) ) {
    define( 'AEOCAS_VERSION', '1.2.2' );
}

$GLOBALS['wp_version'] = '6.9';

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
$GLOBALS['aeocas_test_remote_post'] = null;
$GLOBALS['aeocas_test_remote_post_calls'] = array();
$GLOBALS['aeocas_test_json_response'] = null;
$GLOBALS['aeocas_test_settings_errors'] = array();
$GLOBALS['aeocas_test_redirect'] = null;
$GLOBALS['aeocas_test_posts'] = array();
$GLOBALS['aeocas_test_post_data'] = array();
$GLOBALS['aeocas_test_post_meta'] = array();

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

function wp_remote_post( $url, $args = array() ) {
    $GLOBALS['aeocas_test_remote_post_calls'][] = array(
        'url'  => $url,
        'args' => $args,
    );

    if ( is_callable( $GLOBALS['aeocas_test_remote_post'] ) ) {
        return call_user_func( $GLOBALS['aeocas_test_remote_post'], $url, $args );
    }

    return new WP_Error( 'missing_stub', 'No wp_remote_post stub configured.' );
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

function current_user_can( $capability, ...$args ) {
    return true;
}

function wp_get_current_user() {
    return (object) array( 'user_email' => 'test@example.com', 'display_name' => 'Test User' );
}

function wp_doing_cron() {
    return false;
}

function is_admin() {
    return true;
}

function wp_next_scheduled( $hook ) {
    return false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
    return true;
}

function wp_clear_scheduled_hook( $hook ) {
    // no-op
}

function flush_rewrite_rules() {
    // no-op
}

function register_deactivation_hook( $file, $callback ) {
    // no-op
}

function register_rest_route( $namespace, $route, $args ) {
    // no-op
}

function check_ajax_referer( $action, $query_arg ) {
    return true;
}

/**
 * Exception thrown by wp_send_json_* stubs to simulate exit().
 */
class AEOCAS_Test_Json_Exit extends RuntimeException {}

function wp_send_json_success( $data ) {
    $GLOBALS['aeocas_test_json_response'] = array( 'success' => true, 'data' => $data );
    throw new AEOCAS_Test_Json_Exit();
}

function wp_send_json_error( $data, $status = 400 ) {
    $GLOBALS['aeocas_test_json_response'] = array( 'success' => false, 'data' => $data, 'status' => $status );
    throw new AEOCAS_Test_Json_Exit();
}

function wp_unslash( $value ) {
    return $value;
}

function sanitize_text_field( $str ) {
    return trim( strip_tags( $str ) );
}

function sanitize_textarea_field( $str ) {
    return trim( strip_tags( $str ) );
}

function wp_kses_post( $content ) {
    return (string) $content;
}

function wp_nonce_field( $action, $name ) {
    // no-op
}

function settings_errors() {
    // no-op
}

function submit_button( $text ) {
    // no-op
}

function esc_html( $text ) {
    return $text;
}

function esc_attr( $text ) {
    return $text;
}

function esc_html_e( $text ) {
    echo $text;
}

function esc_attr_e( $text ) {
    echo $text;
}

function esc_html__( $text, $domain = '' ) {
    return $text;
}

function esc_attr__( $text, $domain = '' ) {
    return $text;
}

function esc_url( $url ) {
    return $url;
}

function wp_die( $message ) {
    throw new RuntimeException( $message );
}

if ( ! function_exists( 'hash_equals' ) ) {
    function hash_equals( $known, $user ) {
        return $known === $user;
    }
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $method;
        private $headers = array();
        private $params  = array();
        private $body    = '';

        public function __construct( $method = 'GET' ) {
            $this->method = $method;
        }

        public function get_header( $name ) {
            $name = strtolower( $name );
            return isset( $this->headers[ $name ] ) ? $this->headers[ $name ] : null;
        }

        public function get_param( $key ) {
            return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
        }

        public function set_body( $body ) {
            $this->body = $body;
        }

        public function set_header( $name, $value ) {
            $this->headers[ strtolower( $name ) ] = $value;
        }

        public function set_param( $key, $value ) {
            $this->params[ $key ] = $value;
        }

        public function get_json_params() {
            if ( ! empty( $this->body ) ) {
                return json_decode( $this->body, true ) ?: array();
            }
            return array();
        }
    }
}

function register_setting( $option_group, $option_name, $args = array() ) {
    // no-op
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
    $GLOBALS['aeocas_test_settings_errors'][] = array(
        'setting' => $setting,
        'code'    => $code,
        'message' => $message,
        'type'    => $type,
    );
}

function check_admin_referer( $action, $query_arg = '_wpnonce' ) {
    return true;
}

function wp_safe_redirect( $location, $status = 302 ) {
    $GLOBALS['aeocas_test_redirect'] = array( 'location' => $location, 'status' => $status );
}

function wp_verify_nonce( $nonce, $action ) {
    return true;
}

function wp_create_nonce( $action ) {
    return 'test-nonce';
}

function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
    // no-op
}

function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
    // no-op
}

function wp_localize_script( $handle, $object_name, $l10n ) {
    // no-op
}

function get_site_icon_url( $size = 512, $url = '' ) {
    return $url;
}

function rest_ensure_response( $response ) {
    if ( $response instanceof WP_Error ) {
        return $response;
    }
    return new class( $response ) {
        private $data;
        public function __construct( $data ) { $this->data = $data; }
        public function get_data() { return $this->data; }
    };
}

function rest_sanitize_boolean( $value ) {
    return (bool) $value;
}

function get_site_url() {
    return get_option( 'aeocas_real_site_url', site_url() );
}

function absint( $maybeint ) {
    return abs( (int) $maybeint );
}

function wp_trim_words( $text, $num_words = 55, $more = '&hellip;' ) {
    $words = explode( ' ', $text );
    return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}

if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts        = array();
        public $found_posts  = 0;
        public $max_num_pages = 0;

        public function __construct( $args = array() ) {
            if ( isset( $GLOBALS['aeocas_test_wp_query_result'] ) ) {
                $result             = $GLOBALS['aeocas_test_wp_query_result'];
                $this->posts        = $result['posts'] ?? array();
                $this->found_posts  = $result['found_posts'] ?? count( $this->posts );
                $this->max_num_pages = $result['max_num_pages'] ?? 1;
            }
        }
    }
}

function get_posts( $args = array() ) {
    if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
        return isset( $GLOBALS['aeocas_test_post_ids'] ) ? $GLOBALS['aeocas_test_post_ids'] : array();
    }
    return isset( $GLOBALS['aeocas_test_posts'] ) ? $GLOBALS['aeocas_test_posts'] : array();
}

function get_post( $post_id ) {
    return isset( $GLOBALS['aeocas_test_post_data'][ $post_id ] ) ? $GLOBALS['aeocas_test_post_data'][ $post_id ] : null;
}

function get_permalink( $post_id ) {
    return 'https://helpsquad.com/post-' . $post_id;
}

function get_post_meta( $post_id, $key, $single = false ) {
    return isset( $GLOBALS['aeocas_test_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['aeocas_test_post_meta'][ $post_id ][ $key ] : '';
}

function update_post_meta( $post_id, $key, $value ) {
    if ( ! isset( $GLOBALS['aeocas_test_post_meta'][ $post_id ] ) ) {
        $GLOBALS['aeocas_test_post_meta'][ $post_id ] = array();
    }

    $GLOBALS['aeocas_test_post_meta'][ $post_id ][ $key ] = $value;
    return true;
}

function delete_post_meta( $post_id, $key ) {
    unset( $GLOBALS['aeocas_test_post_meta'][ $post_id ][ $key ] );
    return true;
}

function get_edit_post_link( $post_id, $context = 'display' ) {
    return 'https://site.example/wp-admin/post.php?post=' . $post_id . '&action=edit';
}

function get_the_title( $post_id ) {
    return 'Test Post ' . $post_id;
}

function get_post_type( $post_id ) {
    if ( isset( $GLOBALS['aeocas_test_post_data'][ $post_id ]->post_type ) ) {
        return $GLOBALS['aeocas_test_post_data'][ $post_id ]->post_type;
    }

    return 'post';
}

function get_post_status( $post_id ) {
    if ( isset( $GLOBALS['aeocas_test_post_data'][ $post_id ]->post_status ) ) {
        return $GLOBALS['aeocas_test_post_data'][ $post_id ]->post_status;
    }

    return 'publish';
}

function get_post_modified_time( $format, $gmt = false, $post_id = 0 ) {
    return '2026-04-12T00:00:00+00:00';
}

function get_post_thumbnail_id( $post_id ) {
    return isset( $GLOBALS['aeocas_test_post_thumbnail'][ $post_id ] ) ? $GLOBALS['aeocas_test_post_thumbnail'][ $post_id ] : 0;
}

function wp_get_attachment_url( $attachment_id ) {
    return 'https://site.example/wp-content/uploads/img-' . $attachment_id . '.jpg';
}

function get_userdata( $user_id ) {
    if ( isset( $GLOBALS['aeocas_test_userdata'][ $user_id ] ) ) {
        return $GLOBALS['aeocas_test_userdata'][ $user_id ];
    }
    return null;
}

function wp_get_post_categories( $post_id, $args = array() ) {
    return array();
}

function wp_get_post_tags( $post_id, $args = array() ) {
    return array();
}

function get_term_by( $field, $value, $taxonomy ) {
    return null;
}

function wp_insert_term( $term, $taxonomy ) {
    return array( 'term_id' => 1 );
}

function wp_insert_post( $postarr = array(), $wp_error = false ) {
    $post_id = isset( $GLOBALS['aeocas_test_next_post_id'] ) ? (int) $GLOBALS['aeocas_test_next_post_id'] : 100;
    $GLOBALS['aeocas_test_next_post_id'] = $post_id + 1;

    $post = (object) array_merge(
        array(
            'ID'           => $post_id,
            'post_type'    => $postarr['post_type'] ?? 'post',
            'post_status'  => $postarr['post_status'] ?? 'draft',
            'post_title'   => $postarr['post_title'] ?? '',
            'post_content' => $postarr['post_content'] ?? '',
            'post_excerpt' => $postarr['post_excerpt'] ?? '',
            'post_name'    => $postarr['post_name'] ?? '',
            'post_author'  => $postarr['post_author'] ?? 1,
        ),
        $postarr
    );

    $GLOBALS['aeocas_test_post_data'][ $post_id ] = $post;
    $GLOBALS['aeocas_test_insert_post_calls'][]   = $postarr;

    return $post_id;
}

function wp_update_post( $postarr = array(), $wp_error = false ) {
    $post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
    if ( ! $post_id ) {
        return $wp_error ? new WP_Error( 'missing_id', 'Missing post ID.' ) : 0;
    }

    $existing = isset( $GLOBALS['aeocas_test_post_data'][ $post_id ] ) ? (array) $GLOBALS['aeocas_test_post_data'][ $post_id ] : array();
    $post     = (object) array_merge( $existing, $postarr );

    $GLOBALS['aeocas_test_post_data'][ $post_id ] = $post;
    $GLOBALS['aeocas_test_update_post_calls'][]   = $postarr;

    return $post_id;
}

function get_terms( $args ) {
    if ( isset( $GLOBALS['aeocas_test_terms'] ) ) {
        return $GLOBALS['aeocas_test_terms'];
    }
    return array();
}

if ( ! defined( 'AEOCAS_PLUGIN_URL' ) ) {
    define( 'AEOCAS_PLUGIN_URL', 'https://site.example/wp-content/plugins/aeo-content-ai-studio/' );
}

if ( ! defined( 'AEOCAS_ADMIN_URL' ) ) {
    define( 'AEOCAS_ADMIN_URL', 'https://admin.aeocontent.ai' );
}

function aeocas_plugin() {
    return new class {
        public function get_enabled_features() {
            return array( 'content' );
        }

        public function get_available_modules() {
            return array( 'content' );
        }

        public function get_module( $slug ) {
            return null;
        }
    };
}

// AEOCAS_Activity_Log is loaded from the real class file (see require_once below).
// Tests can inspect $GLOBALS['wpdb']->inserts to verify log calls.

// Stub $wpdb for Activity Log tests.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';

        /** @var array Captured insert calls. */
        public $inserts = array();

        /** @var mixed Value to return from get_var(). */
        public $get_var_result = 0;

        /** @var array Value to return from get_results(). */
        public $get_results_result = array();

        /** @var array Value to return from get_col(). */
        public $get_col_result = array();

        /** @var array Captured query calls. */
        public $queries = array();

        public function get_charset_collate() {
            return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci';
        }

        public function insert( $table, $data, $format = null ) {
            $this->inserts[] = array( 'table' => $table, 'data' => $data );
            return 1;
        }

        public function prepare( $query, ...$args ) {
            // Very simple: replace %i, %s, %d placeholders sequentially.
            return $query;
        }

        public function get_var( $query = null ) {
            return $this->get_var_result;
        }

        public function get_results( $query = null, $output = 'OBJECT' ) {
            return $this->get_results_result;
        }

        public function get_col( $query = null ) {
            return $this->get_col_result;
        }

        public function query( $query ) {
            $this->queries[] = $query;
            return true;
        }
    };
}

function current_time( $type, $gmt = false ) {
    return '2026-04-12 00:00:00';
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

// Stub for dbDelta (used by create_table).
if ( ! function_exists( 'dbDelta' ) ) {
    function dbDelta( $queries = '', $execute = true ) {
        return array();
    }
}

require_once dirname( __DIR__ ) . '/includes/class-aeo-activity-log.php';

require_once dirname( __DIR__ ) . '/includes/class-aeo-auth.php';
require_once dirname( __DIR__ ) . '/includes/class-aeo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-aeo-audit-api.php';
require_once dirname( __DIR__ ) . '/includes/class-aeo-heartbeat.php';
require_once dirname( __DIR__ ) . '/includes/class-aeo-rest-api.php';
