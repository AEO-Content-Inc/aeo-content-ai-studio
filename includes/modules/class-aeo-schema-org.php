<?php
/**
 * Site-wide Organization, WebSite, and BreadcrumbList schema.
 *
 * Injects JSON-LD into <head> on every page:
 * - Organization schema (skipped if Yoast active - Yoast generates its own)
 * - WebSite schema with SearchAction (skipped if Yoast active)
 * - BreadcrumbList schema (skipped if Yoast breadcrumbs enabled)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Schema_Org {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_schema' ), 1 );
    }

    /**
     * Check if an SEO plugin (Yoast or Rank Math) generates site-wide schema.
     */
    private function has_seo_plugin() {
        // Yoast SEO.
        if ( defined( 'WPSEO_VERSION' ) ) {
            return true;
        }
        // Rank Math SEO.
        if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
            return true;
        }
        return false;
    }

    /**
     * Check if an SEO plugin handles breadcrumbs.
     */
    private function has_seo_breadcrumbs() {
        // Yoast breadcrumbs.
        if ( defined( 'WPSEO_VERSION' ) ) {
            $titles = get_option( 'wpseo_titles', array() );
            if ( ! empty( $titles['breadcrumbs-enable'] ) && 'true' === $titles['breadcrumbs-enable'] ) {
                return true;
            }
        }
        // Rank Math breadcrumbs (enabled by default in its schema graph).
        if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
            return true;
        }
        return false;
    }

    public function output_schema() {
        // Skip Organization and WebSite if an SEO plugin generates them.
        if ( ! $this->has_seo_plugin() ) {
            $this->output_organization();
            $this->output_website();
        }

        if ( is_singular() ) {
            // Only output breadcrumbs if no SEO plugin handles them.
            if ( ! $this->has_seo_breadcrumbs() ) {
                $this->output_breadcrumbs();
            }
        }
    }

    /**
     * Output Organization JSON-LD.
     */
    private function output_organization() {
        $data = get_option( 'aeo_org_schema', null );
        if ( empty( $data ) ) {
            return;
        }

        // Ensure required fields.
        $schema = array_merge( array(
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
        ), (array) $data );

        $this->print_jsonld( $schema );
    }

    /**
     * Output WebSite JSON-LD with optional SearchAction.
     */
    private function output_website() {
        $data = get_option( 'aeo_website_schema', null );

        // Auto-generate if not explicitly set.
        if ( empty( $data ) ) {
            $data = array(
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => get_bloginfo( 'name' ),
                'url'      => get_home_url(),
            );

            // Add SearchAction if site has search.
            $data['potentialAction'] = array(
                '@type'       => 'SearchAction',
                'target'      => get_home_url() . '/?s={search_term_string}',
                'query-input' => 'required name=search_term_string',
            );
        }

        $schema = array_merge( array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
        ), (array) $data );

        $this->print_jsonld( $schema );
    }

    /**
     * Output BreadcrumbList JSON-LD for singular posts/pages.
     */
    private function output_breadcrumbs() {
        global $post;
        if ( ! $post ) {
            return;
        }

        $items = array();
        $position = 1;

        // Home.
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => get_bloginfo( 'name' ),
            'item'     => get_home_url(),
        );

        // Category (for posts).
        if ( 'post' === $post->post_type ) {
            $categories = get_the_category( $post->ID );
            if ( ! empty( $categories ) ) {
                $cat = $categories[0];
                $items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => $cat->name,
                    'item'     => get_category_link( $cat->term_id ),
                );
            }
        }

        // Parent pages (for hierarchical pages).
        if ( 'page' === $post->post_type && $post->post_parent ) {
            $ancestors = array_reverse( get_post_ancestors( $post->ID ) );
            foreach ( $ancestors as $ancestor_id ) {
                $items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => get_the_title( $ancestor_id ),
                    'item'     => get_permalink( $ancestor_id ),
                );
            }
        }

        // Current page (no item URL per Google spec).
        $items[] = array(
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => get_the_title( $post->ID ),
        );

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        );

        $this->print_jsonld( $schema );
    }

    /**
     * Print a JSON-LD script tag.
     */
    private function print_jsonld( $data ) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}
