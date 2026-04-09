<?php
/**
 * REST API endpoints under /wp-json/aeocas/v1/.
 *
 * All mutating endpoints require authenticated platform requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEOCAS_Rest_Api {

    /** @var AEOCAS_Plugin */
    private $plugin;

    const REST_NAMESPACE = 'aeocas/v1';

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Public health check.
        register_rest_route( self::REST_NAMESPACE, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_status' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Unified command dispatch.
        register_rest_route( self::REST_NAMESPACE, '/command', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_command' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Activity log (authenticated platform requests).
        register_rest_route( self::REST_NAMESPACE, '/logs', array(
            'methods'             => 'GET',
            'callback'            => array( 'AEOCAS_Activity_Log', 'handle_rest_logs' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Posts list (read).
        register_rest_route( self::REST_NAMESPACE, '/posts', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_get_posts' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Single post (read).
        register_rest_route( self::REST_NAMESPACE, '/posts/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_get_post' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Publish endpoint.
        register_rest_route( self::REST_NAMESPACE, '/publish', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_publish' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Categories list.
        register_rest_route( self::REST_NAMESPACE, '/categories', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_get_categories' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Tags list.
        register_rest_route( self::REST_NAMESPACE, '/tags', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_get_tags' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );
    }

    /**
     * Permission callback - verify the platform credential.
     */
    public function check_auth( $request ) {
        return AEOCAS_Auth::verify_request( $request );
    }

    // ─── Status ───────────────────────────────────────────────

    public function handle_status( $request ) {
        return rest_ensure_response( array(
            'ok'       => true,
            'version'  => AEOCAS_VERSION,
            'features' => $this->plugin->get_enabled_features(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
        ) );
    }

    // ─── Unified Command Dispatch ─────────────────────────────

    public function handle_command( $request ) {
        $command = $request->get_param( 'command' );
        $payload = $request->get_param( 'payload' );

        if ( empty( $command ) ) {
            return new WP_Error( 'aeocas_missing_command', __( 'Missing command parameter.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
        }

        $commands = array(
            'publish_post' => 'cmd_publish_post',
        );

        if ( ! isset( $commands[ $command ] ) ) {
            AEOCAS_Activity_Log::log( $command, 'error', array( 'message' => "Unknown command: {$command}" ) );
            /* translators: %s: command name */
            return new WP_Error( 'aeocas_unknown_command', sprintf( __( 'Unknown command: %s', 'aeo-content-ai-studio' ), $command ), array( 'status' => 400 ) );
        }

        $method = $commands[ $command ];
        return $this->$method( $payload );
    }

    // ─── Endpoint Handlers ──────────────────────────────────

    public function handle_publish( $request ) {
        return $this->cmd_publish_post( $request->get_json_params() );
    }

    // ─── Posts Read Endpoints ────────────────────────────────

    /**
     * GET /aeo/v1/posts — paginated list of posts.
     *
     * Params: page, per_page, status, search, post_type, orderby, order.
     */
    public function handle_get_posts( $request ) {
        $module = $this->plugin->get_module( 'content' );
        if ( ! $module ) {
            return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
        }

        $page      = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per_page  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
        $status    = sanitize_text_field( $request->get_param( 'status' ) ?: 'publish' );
        $search    = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        $post_type = sanitize_text_field( $request->get_param( 'post_type' ) ?: 'post' );
        $orderby   = sanitize_text_field( $request->get_param( 'orderby' ) ?: 'date' );

        $allowed_types = array( 'post', 'page' );
        if ( ! in_array( $post_type, $allowed_types, true ) ) {
            $post_type = 'post';
        }
        $order     = sanitize_text_field( $request->get_param( 'order' ) ?: 'DESC' );

        $allowed_statuses = array( 'publish', 'draft', 'pending', 'future', 'private', 'any' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'publish';
        }

        $allowed_orderby = array( 'date', 'modified', 'title', 'ID' );
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'date';
        }

        $order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        $args = array(
            'post_type'      => $post_type,
            'post_status'    => $status,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
        );

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        $query = new WP_Query( $args );
        $items = array();

        foreach ( $query->posts as $post ) {
            $items[] = $this->format_post_summary( $post );
        }

        AEOCAS_Activity_Log::log( 'get_posts', 'success', array(
            'message' => "Listed {$query->found_posts} posts (page {$page}).",
            'total'   => $query->found_posts,
        ) );

        return rest_ensure_response( array(
            'ok'    => true,
            'posts' => $items,
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'page'  => $page,
        ) );
    }

    /**
     * GET /aeo/v1/posts/{id} — full post content.
     */
    public function handle_get_post( $request ) {
        $module = $this->plugin->get_module( 'content' );
        if ( ! $module ) {
            return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
        }

        $post_id = (int) $request->get_param( 'id' );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error( 'aeocas_not_found', __( 'Post not found.', 'aeo-content-ai-studio' ), array( 'status' => 404 ) );
        }

        $data = $this->format_post_summary( $post );

        // Add full content.
        $data['content']   = $post->post_content;
        $data['author_id'] = (int) $post->post_author;

        // Author info.
        $author = get_userdata( $post->post_author );
        if ( $author ) {
            $data['author'] = array(
                'id'           => (int) $author->ID,
                'display_name' => $author->display_name,
                'email'        => $author->user_email,
            );
        }

        // Featured image.
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( $thumb_id ) {
            $data['featured_image'] = array(
                'id'  => (int) $thumb_id,
                'url' => wp_get_attachment_url( $thumb_id ),
                'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
            );
        }

        // AEO meta.
        $aeocas_meta = array();
        $faq = get_post_meta( $post_id, '_aeocas_faq_schema', true );
        if ( $faq ) {
            $aeocas_meta['faq'] = $faq;
        }
        $speakable = get_post_meta( $post_id, '_aeocas_speakable', true );
        if ( $speakable ) {
            $aeocas_meta['speakable'] = $speakable;
        }
        $canonical = get_post_meta( $post_id, '_aeocas_canonical_url', true );
        if ( $canonical ) {
            $aeocas_meta['canonical'] = $canonical;
        }
        $author_schema = get_post_meta( $post_id, '_aeocas_author_schema', true );
        if ( $author_schema ) {
            $aeocas_meta['author_schema'] = $author_schema;
        }
        if ( ! empty( $aeocas_meta ) ) {
            $data['aeocas_meta'] = $aeocas_meta;
        }

        AEOCAS_Activity_Log::log( 'get_post', 'success', array(
            'message' => "Post #{$post_id} retrieved.",
        ), $post_id );

        return rest_ensure_response( array(
            'ok'   => true,
            'post' => $data,
        ) );
    }

    /**
     * Format a WP_Post into a summary array (used in list and single endpoints).
     *
     * @param WP_Post $post
     * @return array
     */
    private function format_post_summary( $post ) {
        $categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
        $tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

        return array(
            'id'         => (int) $post->ID,
            'title'      => $post->post_title,
            'slug'       => $post->post_name,
            'status'     => $post->post_status,
            'date'       => $post->post_date_gmt,
            'modified'   => $post->post_modified_gmt,
            'excerpt'    => $post->post_excerpt ?: wp_trim_words( $post->post_content, 40, '...' ),
            'url'        => get_permalink( $post->ID ),
            'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
            'categories' => $categories,
            'tags'       => $tags,
        );
    }

    // ─── Taxonomy Endpoints ────────────────────────────────────

    /**
     * GET /aeo/v1/categories — all categories.
     *
     * Params: search, hide_empty, parent, orderby, order.
     */
    public function handle_get_categories( $request ) {
        $hide_empty = $request->get_param( 'hide_empty' );
        $hide_empty = ( null === $hide_empty ) ? false : rest_sanitize_boolean( $hide_empty );

        $args = array(
            'taxonomy'   => 'category',
            'hide_empty' => $hide_empty,
            'orderby'    => sanitize_text_field( $request->get_param( 'orderby' ) ?: 'name' ),
            'order'      => strtoupper( sanitize_text_field( $request->get_param( 'order' ) ?: 'ASC' ) ) === 'DESC' ? 'DESC' : 'ASC',
        );

        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        if ( ! empty( $search ) ) {
            $args['search'] = $search;
        }

        $parent = $request->get_param( 'parent' );
        if ( null !== $parent ) {
            $args['parent'] = absint( $parent );
        }

        $terms = get_terms( $args );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $items = array();
        foreach ( $terms as $term ) {
            $items[] = array(
                'id'     => (int) $term->term_id,
                'name'   => $term->name,
                'slug'   => $term->slug,
                'parent' => (int) $term->parent,
                'count'  => (int) $term->count,
            );
        }

        return rest_ensure_response( array(
            'ok'         => true,
            'categories' => $items,
            'total'      => count( $items ),
        ) );
    }

    /**
     * GET /aeo/v1/tags — all tags.
     *
     * Params: search, hide_empty, orderby, order.
     */
    public function handle_get_tags( $request ) {
        $hide_empty = $request->get_param( 'hide_empty' );
        $hide_empty = ( null === $hide_empty ) ? false : rest_sanitize_boolean( $hide_empty );

        $args = array(
            'taxonomy'   => 'post_tag',
            'hide_empty' => $hide_empty,
            'orderby'    => sanitize_text_field( $request->get_param( 'orderby' ) ?: 'name' ),
            'order'      => strtoupper( sanitize_text_field( $request->get_param( 'order' ) ?: 'ASC' ) ) === 'DESC' ? 'DESC' : 'ASC',
        );

        $search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
        if ( ! empty( $search ) ) {
            $args['search'] = $search;
        }

        $terms = get_terms( $args );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        $items = array();
        foreach ( $terms as $term ) {
            $items[] = array(
                'id'    => (int) $term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'count' => (int) $term->count,
            );
        }

        return rest_ensure_response( array(
            'ok'    => true,
            'tags'  => $items,
            'total' => count( $items ),
        ) );
    }

    // ─── Command Implementations ──────────────────────────────

    private function cmd_publish_post( $payload ) {
        $module = $this->plugin->get_module( 'content' );
        if ( ! $module ) {
            AEOCAS_Activity_Log::log( 'publish_post', 'error', array( 'message' => 'Content module is not enabled.' ) );
            return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
        }

        $title = isset( $payload['title'] ) ? $payload['title'] : 'untitled';

        try {
            $result = $module->create_or_update_post( $payload );
        } catch ( \Throwable $e ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( '[AEO] cmd_publish_post fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
            AEOCAS_Activity_Log::log( 'publish_post', 'error', array(
                'message' => 'Internal error: ' . $e->getMessage(),
            ) );
            return new WP_Error( 'aeocas_internal_error', __( 'Internal error during publish.', 'aeo-content-ai-studio' ), array( 'status' => 500 ) );
        }

        $post_id = null;
        if ( ! is_wp_error( $result ) ) {
            $data    = $result->get_data();
            $post_id = isset( $data['post_id'] ) ? $data['post_id'] : null;
        }

        AEOCAS_Activity_Log::log(
            'publish_post',
            is_wp_error( $result ) ? 'error' : 'success',
            array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : "Published: {$title}" ),
            $post_id
        );

        return $result;
    }

}
