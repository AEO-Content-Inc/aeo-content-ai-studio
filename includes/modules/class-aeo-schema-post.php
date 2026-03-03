<?php
/**
 * Per-post schema: Article, Person (author), FAQPage, SpeakableSpecification.
 *
 * Reads post meta set via the REST API:
 * - _aeo_author_schema  (Person override)
 * - _aeo_faq_schema     (array of {question, answer} pairs)
 * - _aeo_speakable      (CSS selectors for speakable content)
 *
 * Yoast SEO compatibility:
 * - Article schema: skipped if Yoast active (it generates its own Article graph)
 * - FAQPage schema: always output (Yoast doesn't generate FAQPage)
 * - SpeakableSpecification: always output (Yoast doesn't generate Speakable)
 * - Person author: only output standalone if _aeo_author_schema meta explicitly set
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Schema_Post {

    public function __construct() {
        add_action( 'wp_head', array( $this, 'output_post_schema' ), 2 );
    }

    /**
     * Check if an SEO plugin (Yoast or Rank Math) generates Article schema.
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

    public function output_post_schema() {
        if ( ! is_singular( array( 'post', 'page' ) ) ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        // Article schema: skip if an SEO plugin generates its own Article graph.
        if ( ! $this->has_seo_plugin() ) {
            $this->output_article_schema( $post );
        } else {
            // SEO plugin handles Article - still output standalone Speakable if set.
            $this->output_speakable_schema( $post );
        }

        // FAQPage: always output (Yoast doesn't generate this).
        $this->output_faq_schema( $post );
    }

    /**
     * Output Article + Author + Speakable JSON-LD.
     */
    private function output_article_schema( $post ) {
        $author_data = get_post_meta( $post->ID, '_aeo_author_schema', true );
        $speakable   = get_post_meta( $post->ID, '_aeo_speakable', true );
        $defaults    = get_option( 'aeo_author_defaults', null );

        // Build author Person.
        $author = array( '@type' => 'Person' );
        if ( ! empty( $author_data ) && is_array( $author_data ) ) {
            $author = array_merge( $author, $author_data );
        } elseif ( ! empty( $defaults ) && is_array( $defaults ) ) {
            $author = array_merge( $author, $defaults );
        } else {
            // Fall back to WP user.
            $user = get_userdata( $post->post_author );
            if ( $user ) {
                $author['name'] = $user->display_name;
                if ( $user->user_url ) {
                    $author['url'] = $user->user_url;
                }
            }
        }

        // Determine article type.
        $article_type = 'Article';
        $categories = get_the_category( $post->ID );
        if ( ! empty( $categories ) ) {
            foreach ( $categories as $cat ) {
                $name = strtolower( $cat->name );
                if ( in_array( $name, array( 'news', 'press', 'press releases' ), true ) ) {
                    $article_type = 'NewsArticle';
                    break;
                }
            }
        }

        $schema = array(
            '@context'      => 'https://schema.org',
            '@type'         => $article_type,
            'headline'      => get_the_title( $post ),
            'url'           => get_permalink( $post ),
            'datePublished' => get_the_date( 'c', $post ),
            'dateModified'  => get_the_modified_date( 'c', $post ),
            'author'        => $author,
            'publisher'     => $this->get_publisher(),
        );

        // Featured image.
        $thumb_id = get_post_thumbnail_id( $post->ID );
        if ( $thumb_id ) {
            $image = wp_get_attachment_image_src( $thumb_id, 'full' );
            if ( $image ) {
                $schema['image'] = array(
                    '@type'  => 'ImageObject',
                    'url'    => $image[0],
                    'width'  => $image[1],
                    'height' => $image[2],
                );
            }
        }

        // Description from excerpt or meta description.
        $excerpt = get_the_excerpt( $post );
        if ( $excerpt ) {
            $schema['description'] = wp_strip_all_tags( $excerpt );
        }

        // Word count.
        $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
        if ( $word_count > 0 ) {
            $schema['wordCount'] = $word_count;
        }

        // Speakable.
        if ( ! empty( $speakable ) && is_array( $speakable ) ) {
            $schema['speakable'] = array(
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => $speakable,
            );
        }

        $this->print_jsonld( $schema );
    }

    /**
     * Output standalone SpeakableSpecification JSON-LD (when Yoast handles Article).
     */
    private function output_speakable_schema( $post ) {
        $speakable = get_post_meta( $post->ID, '_aeo_speakable', true );

        if ( empty( $speakable ) || ! is_array( $speakable ) ) {
            return;
        }

        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'WebPage',
            'url'         => get_permalink( $post ),
            'speakable'   => array(
                '@type'       => 'SpeakableSpecification',
                'cssSelector' => $speakable,
            ),
        );

        $this->print_jsonld( $schema );
    }

    /**
     * Output FAQPage JSON-LD if FAQ data exists.
     */
    private function output_faq_schema( $post ) {
        $pairs = get_post_meta( $post->ID, '_aeo_faq_schema', true );

        if ( empty( $pairs ) || ! is_array( $pairs ) ) {
            return;
        }

        $main_entity = array();
        foreach ( $pairs as $pair ) {
            if ( empty( $pair['question'] ) || empty( $pair['answer'] ) ) {
                continue;
            }
            $main_entity[] = array(
                '@type' => 'Question',
                'name'  => wp_strip_all_tags( $pair['question'] ),
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $pair['answer'] ),
                ),
            );
        }

        if ( empty( $main_entity ) ) {
            return;
        }

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $main_entity,
        );

        $this->print_jsonld( $schema );
    }

    /**
     * Get publisher data from Organization schema or site info.
     */
    private function get_publisher() {
        $org = get_option( 'aeo_org_schema', null );
        if ( ! empty( $org ) && is_array( $org ) ) {
            return array(
                '@type' => 'Organization',
                'name'  => isset( $org['name'] ) ? $org['name'] : get_bloginfo( 'name' ),
                'url'   => isset( $org['url'] ) ? $org['url'] : get_home_url(),
            );
        }

        return array(
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => get_home_url(),
        );
    }

    private function print_jsonld( $data ) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}
