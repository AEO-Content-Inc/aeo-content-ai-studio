<?php
/**
 * Semantic HTML improvements.
 *
 * - Wraps post content in <article> tags
 * - Ensures <time> elements on dates
 * - Ensures lang attribute on <html>
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Semantic_Html {

    public function __construct() {
        add_filter( 'the_content', array( $this, 'wrap_in_article' ), 999 );
        add_filter( 'language_attributes', array( $this, 'ensure_lang' ), 10, 2 );
    }

    /**
     * Wrap singular post content in <article> with semantic attributes.
     */
    public function wrap_in_article( $content ) {
        // Only wrap on singular views (not in feeds, REST, or archives).
        if ( ! is_singular() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return $content;
        }

        global $post;
        if ( ! $post ) {
            return $content;
        }

        // Don't double-wrap if content already has an article tag.
        if ( false !== strpos( $content, '<article' ) ) {
            return $content;
        }

        $datetime_published = get_the_date( 'c', $post );
        $datetime_modified  = get_the_modified_date( 'c', $post );
        $date_display       = get_the_date( '', $post );
        $modified_display   = get_the_modified_date( '', $post );

        // Build time elements.
        $time_html = '<div class="aeo-article-meta">';
        $time_html .= '<time datetime="' . esc_attr( $datetime_published ) . '" itemprop="datePublished">';
        $time_html .= 'Published ' . esc_html( $date_display );
        $time_html .= '</time>';

        if ( $datetime_modified !== $datetime_published ) {
            $time_html .= ' <time datetime="' . esc_attr( $datetime_modified ) . '" itemprop="dateModified">';
            $time_html .= '(Updated ' . esc_html( $modified_display ) . ')';
            $time_html .= '</time>';
        }
        $time_html .= '</div>';

        $content = '<article itemscope itemtype="https://schema.org/Article">'
            . $time_html
            . $content
            . '</article>';

        return $content;
    }

    /**
     * Ensure lang attribute is present on <html>.
     */
    public function ensure_lang( $output, $doctype ) {
        if ( 'html' !== $doctype ) {
            return $output;
        }

        // If lang is already set, leave it alone.
        if ( preg_match( '/\blang\s*=/', $output ) ) {
            return $output;
        }

        $locale = get_bloginfo( 'language' );
        if ( empty( $locale ) ) {
            $locale = 'en-US';
        }

        return $output . ' lang="' . esc_attr( $locale ) . '"';
    }
}
