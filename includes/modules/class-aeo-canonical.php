<?php
/**
 * Canonical URL management.
 *
 * Allows the platform to override canonical URLs per post via post meta.
 * Removes the default WordPress canonical tag and outputs our own.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Canonical {

    public function __construct() {
        // Remove default WP canonical.
        remove_action( 'wp_head', 'rel_canonical' );
        add_action( 'wp_head', array( $this, 'output_canonical' ), 1 );
    }

    public function output_canonical() {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        // Check for AEO override.
        $canonical = get_post_meta( $post->ID, '_aeo_canonical_url', true );

        // Fall back to default permalink.
        if ( empty( $canonical ) ) {
            $canonical = get_permalink( $post->ID );
        }

        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
        }
    }
}
