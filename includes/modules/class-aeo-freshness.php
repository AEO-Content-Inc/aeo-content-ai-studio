<?php
/**
 * Content freshness signals.
 *
 * - Adds Open Graph article:published_time and article:modified_time
 * - Adds OG tags (type, title, url, description, image) UNLESS Yoast is active
 *
 * Yoast SEO generates its own OG tags. When detected, we only output
 * article:published_time and article:modified_time (which Yoast sometimes omits).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Freshness {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_freshness_meta' ), 3 );
    }

    /**
     * Check if an SEO plugin (Yoast or Rank Math) handles OG tags.
     */
    private function has_seo_plugin() {
        // Yoast SEO.
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' ) ) {
            return true;
        }
        // Rank Math SEO.
        if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
            return true;
        }
        return false;
    }

    public function output_freshness_meta() {
        if ( ! is_singular( array( 'post', 'page' ) ) ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        $published = get_the_date( 'c', $post );
        $modified  = get_the_modified_date( 'c', $post );

        // Always output article times (Yoast doesn't always include these).
        echo '<meta property="article:published_time" content="' . esc_attr( $published ) . '" />' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr( $modified ) . '" />' . "\n";

        // Skip all other OG tags if an SEO plugin handles them.
        if ( $this->has_seo_plugin() ) {
            return;
        }

        // OG type.
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( get_the_title( $post ) ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_permalink( $post ) ) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";

        // OG description from excerpt.
        $excerpt = get_the_excerpt( $post );
        if ( $excerpt ) {
            echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $excerpt ) ) . '" />' . "\n";
        }

        // OG image from featured image.
        $thumb_id = get_post_thumbnail_id( $post->ID );
        if ( $thumb_id ) {
            $image = wp_get_attachment_image_src( $thumb_id, 'full' );
            if ( $image ) {
                echo '<meta property="og:image" content="' . esc_url( $image[0] ) . '" />' . "\n";
                echo '<meta property="og:image:width" content="' . esc_attr( $image[1] ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $image[2] ) . '" />' . "\n";
            }
        }

        // Author.
        $user = get_userdata( $post->post_author );
        if ( $user ) {
            echo '<meta property="article:author" content="' . esc_attr( $user->display_name ) . '" />' . "\n";
        }
    }
}
