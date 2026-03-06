<?php
/**
 * Virtual /llms.txt file served via rewrite rule.
 *
 * WordPress does not support creating arbitrary root-level files via the REST API.
 * This module registers a rewrite rule so that /llms.txt is handled by WordPress
 * and served from wp_options content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Llms_Txt {

    public function __construct() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'serve_file' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?aeo_llms_txt=1', 'top' );
        // Also handle /llms-full.txt if content exists.
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?aeo_llms_full_txt=1', 'top' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'aeo_llms_txt';
        $vars[] = 'aeo_llms_full_txt';
        return $vars;
    }

    public function serve_file() {
        if ( get_query_var( 'aeo_llms_txt' ) ) {
            $content = get_option( 'aeo_llms_txt_content', '' );
            $this->output_text( $content );
        }

        if ( get_query_var( 'aeo_llms_full_txt' ) ) {
            // Full version - if separate content exists, use it; otherwise fall back to main.
            $content = get_option( 'aeo_llms_full_txt_content', '' );
            if ( empty( $content ) ) {
                $content = get_option( 'aeo_llms_txt_content', '' );
            }
            $this->output_text( $content );
        }
    }

    private function output_text( $content ) {
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
