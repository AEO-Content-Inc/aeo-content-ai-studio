<?php
/**
 * Virtual /ai.txt file served via rewrite rule.
 *
 * Same pattern as llms.txt - registers a rewrite rule so /ai.txt is handled
 * by WordPress and served from wp_options content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Ai_Txt {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'serve_file' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^ai\.txt$', 'index.php?aeo_ai_txt=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'aeo_ai_txt';
        return $vars;
    }

    public function serve_file() {
        if ( ! get_query_var( 'aeo_ai_txt' ) ) {
            return;
        }

        $content = get_option( 'aeo_ai_txt_content', '' );

        if ( empty( $content ) ) {
            status_header( 404 );
            echo 'Not configured.';
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'X-Robots-Tag: noindex' );
        echo esc_html( $content );
        exit;
    }
}
