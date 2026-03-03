<?php
/**
 * Content publishing module.
 *
 * Creates or updates WordPress posts with full AEO optimization:
 * - Post content, title, slug, excerpt, categories
 * - Automatically sets FAQ schema from content
 * - Sets author schema, speakable selectors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Content {

    public function __construct() {
        // No hooks needed - called via REST API.
    }

    /**
     * Create or update a post with AEO optimizations.
     *
     * @param array $payload {
     *     @type int    $post_id    Optional. Update existing post.
     *     @type string $title      Post title.
     *     @type string $content    HTML content.
     *     @type string $slug       URL slug.
     *     @type string $excerpt    Post excerpt.
     *     @type string $status     Post status (publish, draft, pending).
     *     @type array  $categories Category names (will be created if missing).
     *     @type array  $tags       Tag names.
     *     @type array  $faq        Array of {question, answer} pairs.
     *     @type array  $author     Person schema override.
     *     @type array  $speakable  CSS selectors for speakable content.
     *     @type string $canonical  Canonical URL override.
     *     @type string $featured_image_url  URL to download and set as featured image.
     * }
     * @return WP_REST_Response|WP_Error
     */
    public function create_or_update_post( $payload ) {
        $post_id = isset( $payload['post_id'] ) ? intval( $payload['post_id'] ) : 0;

        $post_data = array(
            'post_type'   => 'post',
            'post_status' => isset( $payload['status'] ) ? sanitize_text_field( $payload['status'] ) : 'draft',
        );

        if ( isset( $payload['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $payload['title'] );
        }
        if ( isset( $payload['content'] ) ) {
            $post_data['post_content'] = wp_kses_post( $payload['content'] );
        }
        if ( isset( $payload['slug'] ) ) {
            $post_data['post_name'] = sanitize_title( $payload['slug'] );
        }
        if ( isset( $payload['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_textarea_field( $payload['excerpt'] );
        }

        // Handle categories.
        if ( isset( $payload['categories'] ) && is_array( $payload['categories'] ) ) {
            $cat_ids = array();
            foreach ( $payload['categories'] as $name ) {
                $term = get_term_by( 'name', $name, 'category' );
                if ( $term ) {
                    $cat_ids[] = $term->term_id;
                } else {
                    $result = wp_insert_term( $name, 'category' );
                    if ( ! is_wp_error( $result ) ) {
                        $cat_ids[] = $result['term_id'];
                    }
                }
            }
            if ( ! empty( $cat_ids ) ) {
                $post_data['post_category'] = $cat_ids;
            }
        }

        // Handle tags.
        if ( isset( $payload['tags'] ) && is_array( $payload['tags'] ) ) {
            $post_data['tags_input'] = array_map( 'sanitize_text_field', $payload['tags'] );
        }

        // Create or update.
        if ( $post_id && get_post( $post_id ) ) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post( $post_data, true );
        } else {
            $result = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $post_id = $result;

        // Set AEO post meta.
        if ( isset( $payload['faq'] ) && is_array( $payload['faq'] ) ) {
            update_post_meta( $post_id, '_aeo_faq_schema', $payload['faq'] );
        } else {
            // Auto-extract FAQ from content.
            $this->auto_extract_faq( $post_id, $post_data['post_content'] ?? '' );
        }

        if ( isset( $payload['author'] ) ) {
            update_post_meta( $post_id, '_aeo_author_schema', $payload['author'] );
        }

        if ( isset( $payload['speakable'] ) ) {
            update_post_meta( $post_id, '_aeo_speakable', $payload['speakable'] );
        }

        if ( isset( $payload['canonical'] ) ) {
            update_post_meta( $post_id, '_aeo_canonical_url', esc_url_raw( $payload['canonical'] ) );
        }

        // Download and set featured image.
        if ( ! empty( $payload['featured_image_url'] ) ) {
            $image_url = esc_url_raw( $payload['featured_image_url'], array( 'http', 'https' ) );
            if ( $image_url ) {
                $this->set_featured_image( $post_id, $image_url );
            }
        }

        return rest_ensure_response( array(
            'ok'      => true,
            'post_id' => $post_id,
            'url'     => get_permalink( $post_id ),
            'edit'    => get_edit_post_link( $post_id, 'raw' ),
        ) );
    }

    /**
     * Auto-extract FAQ pairs from HTML content.
     * Looks for Rank Math FAQ blocks or generic H2/H3 FAQ patterns.
     */
    private function auto_extract_faq( $post_id, $content ) {
        if ( empty( $content ) ) {
            return;
        }

        $pairs = array();

        // Pattern 1: Rank Math FAQ block.
        if ( false !== strpos( $content, 'rank-math-faq' ) ) {
            $pattern = '/<h3[^>]*class\s*=\s*["\']rank-math-question[^"\']*["\'][^>]*>([\s\S]*?)<\/h3>\s*<div[^>]*class\s*=\s*["\']rank-math-answer[^"\']*["\'][^>]*>([\s\S]*?)<\/div>/i';
            if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
                foreach ( $matches as $match ) {
                    $pairs[] = array(
                        'question' => wp_strip_all_tags( $match[1] ),
                        'answer'   => wp_strip_all_tags( $match[2] ),
                    );
                }
            }
        }

        // Pattern 2: Generic FAQ heading + Q&A pairs.
        if ( empty( $pairs ) ) {
            $faq_pattern = '/<h2[^>]*>[\s\S]*?(?:FAQ|Frequently\s+Asked\s+Questions)[\s\S]*?<\/h2>/i';
            if ( preg_match( $faq_pattern, $content, $faq_match, PREG_OFFSET_MATCH ) ) {
                $faq_section = substr( $content, $faq_match[0][1] );
                // Find next H2 to limit scope.
                $next_h2 = strpos( $faq_section, '<h2', strlen( $faq_match[0][0] ) );
                if ( $next_h2 ) {
                    $faq_section = substr( $faq_section, 0, $next_h2 );
                }
                // Extract H3 + next paragraph.
                $h3_pattern = '/<h3[^>]*>([\s\S]*?)<\/h3>\s*(?:<[^h][\s\S]*?(?=<h3|$))/i';
                if ( preg_match_all( $h3_pattern, $faq_section, $h3_matches, PREG_SET_ORDER ) ) {
                    foreach ( $h3_matches as $h3m ) {
                        $question = wp_strip_all_tags( $h3m[1] );
                        // Get text between this H3 and the next H3 or end.
                        $answer_start = strpos( $faq_section, $h3m[0] ) + strlen( $h3m[0] );
                        $answer_end   = strpos( $faq_section, '<h3', $answer_start );
                        $answer_html  = $answer_end
                            ? substr( $faq_section, $answer_start, $answer_end - $answer_start )
                            : substr( $faq_section, $answer_start );
                        $answer = trim( wp_strip_all_tags( $answer_html ) );

                        if ( $question && $answer ) {
                            $pairs[] = array(
                                'question' => $question,
                                'answer'   => $answer,
                            );
                        }
                    }
                }
            }
        }

        if ( ! empty( $pairs ) ) {
            update_post_meta( $post_id, '_aeo_faq_schema', $pairs );
        }
    }

    /**
     * Download an image URL and set as featured image.
     */
    private function set_featured_image( $post_id, $url ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image( $url, $post_id, '', 'id' );
        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }
}
