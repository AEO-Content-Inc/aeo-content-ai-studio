<?php
/**
 * REST API endpoints under /wp-json/aeo/v1/.
 *
 * All mutating endpoints require HMAC authentication from the AEO Content platform.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Rest_Api {

    /** @var AEO_Plugin */
    private $plugin;

    const REST_NAMESPACE = 'aeo/v1';

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Public health check.
        register_rest_route( self::REST_NAMESPACE, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_status' ),
            'permission_callback' => '__return_true',
        ) );

        // Unified command dispatch.
        register_rest_route( self::REST_NAMESPACE, '/command', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_command' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Activity log (HMAC auth).
        register_rest_route( self::REST_NAMESPACE, '/logs', array(
            'methods'             => 'GET',
            'callback'            => array( 'AEO_Activity_Log', 'handle_rest_logs' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        // Direct endpoints.
        $endpoints = array(
            '/llms-txt'              => 'handle_llms_txt',
            '/ai-txt'               => 'handle_ai_txt',
            '/robots-txt'           => 'handle_robots_txt',
            '/schema/organization'  => 'handle_schema_organization',
            '/schema/post/(?P<id>\d+)' => 'handle_schema_post',
            '/publish'              => 'handle_publish',
            '/bulk/faq-schema'      => 'handle_bulk_faq_schema',
            '/features'             => 'handle_features',
        );

        foreach ( $endpoints as $route => $callback ) {
            register_rest_route( self::REST_NAMESPACE, $route, array(
                'methods'             => 'POST',
                'callback'            => array( $this, $callback ),
                'permission_callback' => array( $this, 'check_auth' ),
            ) );
        }
    }

    /**
     * Permission callback - verify HMAC.
     */
    public function check_auth( $request ) {
        return AEO_Auth::verify_request( $request );
    }

    // ─── Status ───────────────────────────────────────────────

    public function handle_status( $request ) {
        return rest_ensure_response( array(
            'ok'       => true,
            'version'  => AEO_VERSION,
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
            return new WP_Error( 'aeo_missing_command', 'Missing command parameter.', array( 'status' => 400 ) );
        }

        $commands = array(
            'set_llms_txt'         => 'cmd_set_llms_txt',
            'set_ai_txt'           => 'cmd_set_ai_txt',
            'set_robots_rules'     => 'cmd_set_robots_rules',
            'set_org_schema'       => 'cmd_set_org_schema',
            'set_website_schema'   => 'cmd_set_website_schema',
            'set_post_schema'      => 'cmd_set_post_schema',
            'set_post_faq'         => 'cmd_set_post_faq',
            'set_post_speakable'   => 'cmd_set_post_speakable',
            'set_author_defaults'  => 'cmd_set_author_defaults',
            'set_canonical'        => 'cmd_set_canonical',
            'publish_post'         => 'cmd_publish_post',
            'enable_feature'       => 'cmd_enable_feature',
            'disable_feature'      => 'cmd_disable_feature',
            'bulk_faq_schema'      => 'cmd_bulk_faq_schema',
            'bulk_speakable'       => 'cmd_bulk_speakable',
            'query_post_meta'      => 'cmd_query_post_meta',
        );

        if ( ! isset( $commands[ $command ] ) ) {
            AEO_Activity_Log::log( $command, 'error', array( 'message' => "Unknown command: {$command}" ) );
            return new WP_Error( 'aeo_unknown_command', "Unknown command: {$command}", array( 'status' => 400 ) );
        }

        $method = $commands[ $command ];
        return $this->$method( $payload );
    }

    // ─── Direct Endpoint Handlers ─────────────────────────────

    public function handle_llms_txt( $request ) {
        return $this->cmd_set_llms_txt( $request->get_json_params() );
    }

    public function handle_ai_txt( $request ) {
        return $this->cmd_set_ai_txt( $request->get_json_params() );
    }

    public function handle_robots_txt( $request ) {
        return $this->cmd_set_robots_rules( $request->get_json_params() );
    }

    public function handle_schema_organization( $request ) {
        return $this->cmd_set_org_schema( $request->get_json_params() );
    }

    public function handle_schema_post( $request ) {
        $payload = $request->get_json_params();
        $payload['post_id'] = intval( $request->get_param( 'id' ) );
        return $this->cmd_set_post_schema( $payload );
    }

    public function handle_publish( $request ) {
        return $this->cmd_publish_post( $request->get_json_params() );
    }

    public function handle_bulk_faq_schema( $request ) {
        return $this->cmd_bulk_faq_schema( $request->get_json_params() );
    }

    public function handle_features( $request ) {
        $action  = $request->get_param( 'action' );
        $feature = $request->get_param( 'feature' );

        if ( 'enable' === $action ) {
            return $this->cmd_enable_feature( array( 'feature' => $feature ) );
        }
        return $this->cmd_disable_feature( array( 'feature' => $feature ) );
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Recursively sanitize a schema array for safe storage.
     *
     * @param mixed $data Input data.
     * @return mixed Sanitized data.
     */
    private function sanitize_schema( $data ) {
        if ( is_array( $data ) ) {
            $clean = array();
            foreach ( $data as $key => $value ) {
                $clean[ sanitize_text_field( $key ) ] = $this->sanitize_schema( $value );
            }
            return $clean;
        }
        if ( is_string( $data ) ) {
            // Preserve URLs.
            if ( filter_var( $data, FILTER_VALIDATE_URL ) ) {
                return esc_url_raw( $data );
            }
            return sanitize_text_field( $data );
        }
        if ( is_bool( $data ) || is_int( $data ) || is_float( $data ) ) {
            return $data;
        }
        return '';
    }

    // ─── Command Implementations ──────────────────────────────

    private function cmd_set_llms_txt( $payload ) {
        $content = isset( $payload['content'] ) ? sanitize_textarea_field( $payload['content'] ) : '';
        update_option( 'aeo_llms_txt_content', $content );
        AEO_Activity_Log::log( 'set_llms_txt', 'success', array( 'message' => 'llms.txt updated.', 'length' => strlen( $content ) ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => 'llms.txt updated.' ) );
    }

    private function cmd_set_ai_txt( $payload ) {
        $content = isset( $payload['content'] ) ? sanitize_textarea_field( $payload['content'] ) : '';
        update_option( 'aeo_ai_txt_content', $content );
        AEO_Activity_Log::log( 'set_ai_txt', 'success', array( 'message' => 'ai.txt updated.', 'length' => strlen( $content ) ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => 'ai.txt updated.' ) );
    }

    private function cmd_set_robots_rules( $payload ) {
        $rules = isset( $payload['rules'] ) ? $payload['rules'] : array();
        if ( ! is_array( $rules ) ) {
            AEO_Activity_Log::log( 'set_robots_rules', 'error', array( 'message' => 'Rules must be an array.' ) );
            return new WP_Error( 'aeo_invalid_rules', 'Rules must be an array.', array( 'status' => 400 ) );
        }
        // Sanitize each rule line.
        $rules = array_map( 'sanitize_text_field', $rules );
        update_option( 'aeo_robots_ai_rules', $rules );
        AEO_Activity_Log::log( 'set_robots_rules', 'success', array( 'message' => 'robots.txt rules updated.', 'count' => count( $rules ) ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => 'robots.txt rules updated.' ) );
    }

    private function cmd_set_org_schema( $payload ) {
        $schema = isset( $payload['schema'] ) ? $payload['schema'] : $payload;
        $schema = $this->sanitize_schema( $schema );
        update_option( 'aeo_org_schema', $schema );
        $name = isset( $schema['name'] ) ? $schema['name'] : 'unknown';
        AEO_Activity_Log::log( 'set_org_schema', 'success', array( 'message' => "Organization schema updated for {$name}." ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => 'Organization schema updated.' ) );
    }

    private function cmd_set_website_schema( $payload ) {
        $schema = isset( $payload['schema'] ) ? $payload['schema'] : $payload;
        $schema = $this->sanitize_schema( $schema );
        update_option( 'aeo_website_schema', $schema );
        AEO_Activity_Log::log( 'set_website_schema', 'success', array( 'message' => 'WebSite schema updated.' ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => 'WebSite schema updated.' ) );
    }

    private function cmd_set_post_schema( $payload ) {
        $post_id = isset( $payload['post_id'] ) ? intval( $payload['post_id'] ) : 0;
        if ( ! $post_id || ! get_post( $post_id ) ) {
            AEO_Activity_Log::log( 'set_post_schema', 'error', array( 'message' => 'Post not found.', 'post_id' => $post_id ) );
            return new WP_Error( 'aeo_invalid_post', 'Post not found.', array( 'status' => 404 ) );
        }
        $updated = array();
        if ( isset( $payload['author'] ) ) {
            update_post_meta( $post_id, '_aeo_author_schema', $this->sanitize_schema( $payload['author'] ) );
            $updated[] = 'author';
        }
        if ( isset( $payload['faq'] ) ) {
            update_post_meta( $post_id, '_aeo_faq_schema', $this->sanitize_schema( $payload['faq'] ) );
            $updated[] = 'faq';
        }
        if ( isset( $payload['speakable'] ) ) {
            update_post_meta( $post_id, '_aeo_speakable', $this->sanitize_schema( $payload['speakable'] ) );
            $updated[] = 'speakable';
        }
        AEO_Activity_Log::log( 'set_post_schema', 'success', array( 'message' => 'Post schema updated.', 'fields' => $updated ), $post_id );
        return rest_ensure_response( array( 'ok' => true, 'post_id' => $post_id, 'message' => 'Post schema updated.' ) );
    }

    private function cmd_set_post_faq( $payload ) {
        $post_id = isset( $payload['post_id'] ) ? intval( $payload['post_id'] ) : 0;
        $pairs   = isset( $payload['pairs'] ) ? $payload['pairs'] : array();
        if ( ! $post_id || ! get_post( $post_id ) ) {
            AEO_Activity_Log::log( 'set_post_faq', 'error', array( 'message' => 'Post not found.', 'post_id' => $post_id ) );
            return new WP_Error( 'aeo_invalid_post', 'Post not found.', array( 'status' => 404 ) );
        }
        update_post_meta( $post_id, '_aeo_faq_schema', $pairs );
        AEO_Activity_Log::log( 'set_post_faq', 'success', array( 'message' => 'FAQ schema set.', 'pairs' => count( $pairs ) ), $post_id );
        return rest_ensure_response( array( 'ok' => true, 'post_id' => $post_id, 'count' => count( $pairs ) ) );
    }

    private function cmd_set_post_speakable( $payload ) {
        $post_id  = isset( $payload['post_id'] ) ? intval( $payload['post_id'] ) : 0;
        $selectors = isset( $payload['selectors'] ) ? $payload['selectors'] : array();
        if ( ! $post_id || ! get_post( $post_id ) ) {
            AEO_Activity_Log::log( 'set_post_speakable', 'error', array( 'message' => 'Post not found.', 'post_id' => $post_id ) );
            return new WP_Error( 'aeo_invalid_post', 'Post not found.', array( 'status' => 404 ) );
        }
        update_post_meta( $post_id, '_aeo_speakable', $selectors );
        AEO_Activity_Log::log( 'set_post_speakable', 'success', array( 'message' => 'Speakable selectors set.' ), $post_id );
        return rest_ensure_response( array( 'ok' => true, 'post_id' => $post_id ) );
    }

    private function cmd_set_author_defaults( $payload ) {
        update_option( 'aeo_author_defaults', $this->sanitize_schema( $payload ) );
        $name = isset( $payload['name'] ) ? $payload['name'] : 'unknown';
        AEO_Activity_Log::log( 'set_author_defaults', 'success', array( 'message' => "Author defaults set to {$name}." ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => 'Author defaults updated.' ) );
    }

    private function cmd_set_canonical( $payload ) {
        $post_id = isset( $payload['post_id'] ) ? intval( $payload['post_id'] ) : 0;
        $url     = isset( $payload['url'] ) ? esc_url_raw( $payload['url'] ) : '';
        if ( ! $post_id || ! get_post( $post_id ) ) {
            AEO_Activity_Log::log( 'set_canonical', 'error', array( 'message' => 'Post not found.', 'post_id' => $post_id ) );
            return new WP_Error( 'aeo_invalid_post', 'Post not found.', array( 'status' => 404 ) );
        }
        update_post_meta( $post_id, '_aeo_canonical_url', $url );
        AEO_Activity_Log::log( 'set_canonical', 'success', array( 'message' => 'Canonical URL set.', 'url' => $url ), $post_id );
        return rest_ensure_response( array( 'ok' => true, 'post_id' => $post_id ) );
    }

    private function cmd_publish_post( $payload ) {
        $module = $this->plugin->get_module( 'content' );
        if ( ! $module ) {
            AEO_Activity_Log::log( 'publish_post', 'error', array( 'message' => 'Content module is not enabled.' ) );
            return new WP_Error( 'aeo_module_disabled', 'Content module is not enabled.', array( 'status' => 400 ) );
        }
        $result = $module->create_or_update_post( $payload );
        $title  = isset( $payload['title'] ) ? $payload['title'] : 'untitled';
        $post_id = null;
        if ( ! is_wp_error( $result ) ) {
            $data = $result->get_data();
            $post_id = isset( $data['post_id'] ) ? $data['post_id'] : null;
        }
        AEO_Activity_Log::log(
            'publish_post',
            is_wp_error( $result ) ? 'error' : 'success',
            array( 'message' => is_wp_error( $result ) ? $result->get_error_message() : "Published: {$title}" ),
            $post_id
        );
        return $result;
    }

    private function cmd_enable_feature( $payload ) {
        $feature = isset( $payload['feature'] ) ? sanitize_text_field( $payload['feature'] ) : '';
        if ( $this->plugin->enable_feature( $feature ) ) {
            AEO_Activity_Log::log( 'enable_feature', 'success', array( 'message' => "Feature '{$feature}' enabled.", 'feature' => $feature ) );
            return rest_ensure_response( array( 'ok' => true, 'message' => "Feature '{$feature}' enabled." ) );
        }
        AEO_Activity_Log::log( 'enable_feature', 'error', array( 'message' => "Unknown feature: {$feature}", 'feature' => $feature ) );
        return new WP_Error( 'aeo_unknown_feature', "Unknown feature: {$feature}", array( 'status' => 400 ) );
    }

    private function cmd_disable_feature( $payload ) {
        $feature = isset( $payload['feature'] ) ? sanitize_text_field( $payload['feature'] ) : '';
        $this->plugin->disable_feature( $feature );
        AEO_Activity_Log::log( 'disable_feature', 'success', array( 'message' => "Feature '{$feature}' disabled.", 'feature' => $feature ) );
        return rest_ensure_response( array( 'ok' => true, 'message' => "Feature '{$feature}' disabled." ) );
    }

    private function cmd_bulk_faq_schema( $payload ) {
        $posts = isset( $payload['posts'] ) ? $payload['posts'] : array();
        if ( empty( $posts ) || ! is_array( $posts ) ) {
            AEO_Activity_Log::log( 'bulk_faq_schema', 'error', array( 'message' => 'Posts array is required.' ) );
            return new WP_Error( 'aeo_invalid_payload', 'Posts array is required.', array( 'status' => 400 ) );
        }

        $results  = array();
        $ok_count = 0;
        foreach ( $posts as $item ) {
            $post_id = isset( $item['post_id'] ) ? intval( $item['post_id'] ) : 0;
            $pairs   = isset( $item['pairs'] ) ? $item['pairs'] : array();

            if ( ! $post_id || ! get_post( $post_id ) ) {
                $results[] = array( 'post_id' => $post_id, 'ok' => false, 'error' => 'Post not found.' );
                continue;
            }

            update_post_meta( $post_id, '_aeo_faq_schema', $pairs );
            $results[] = array( 'post_id' => $post_id, 'ok' => true, 'count' => count( $pairs ) );
            $ok_count++;
        }

        AEO_Activity_Log::log( 'bulk_faq_schema', 'success', array(
            'message'  => "Bulk FAQ: {$ok_count}/" . count( $posts ) . ' posts updated.',
            'total'    => count( $posts ),
            'success'  => $ok_count,
        ) );
        return rest_ensure_response( array( 'ok' => true, 'results' => $results ) );
    }

    // ─── New Commands (v1.1.0) ────────────────────────────────

    /**
     * Bulk set speakable CSS selectors on posts matching criteria.
     */
    private function cmd_bulk_speakable( $payload ) {
        $selector  = isset( $payload['selector'] ) ? sanitize_text_field( $payload['selector'] ) : '.entry-content';
        $post_type = isset( $payload['post_type'] ) ? sanitize_text_field( $payload['post_type'] ) : 'post';
        $limit     = isset( $payload['limit'] ) ? min( intval( $payload['limit'] ), 1000 ) : 100;

        $posts = get_posts( array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
        ) );

        if ( empty( $posts ) ) {
            AEO_Activity_Log::log( 'bulk_speakable', 'success', array( 'message' => 'No posts found.', 'post_type' => $post_type ) );
            return rest_ensure_response( array( 'ok' => true, 'updated' => 0, 'message' => 'No posts found.' ) );
        }

        $updated = 0;
        $selectors = array( $selector );

        foreach ( $posts as $post_id ) {
            update_post_meta( $post_id, '_aeo_speakable', $selectors );
            $updated++;
        }

        AEO_Activity_Log::log( 'bulk_speakable', 'success', array(
            'message'   => "Speakable set on {$updated} posts.",
            'selector'  => $selector,
            'post_type' => $post_type,
            'updated'   => $updated,
        ) );

        return rest_ensure_response( array(
            'ok'       => true,
            'updated'  => $updated,
            'selector' => $selector,
            'message'  => "Speakable set on {$updated} {$post_type} posts.",
        ) );
    }

    /**
     * Query AEO post meta for diagnostics.
     */
    private function cmd_query_post_meta( $payload ) {
        $meta_key  = isset( $payload['meta_key'] ) ? sanitize_text_field( $payload['meta_key'] ) : '';
        $post_type = isset( $payload['post_type'] ) ? sanitize_text_field( $payload['post_type'] ) : 'post';
        $limit     = isset( $payload['limit'] ) ? min( intval( $payload['limit'] ), 200 ) : 50;

        if ( empty( $meta_key ) ) {
            return new WP_Error( 'aeo_missing_meta_key', 'meta_key is required.', array( 'status' => 400 ) );
        }

        // Only allow querying AEO-prefixed meta keys.
        if ( strpos( $meta_key, '_aeo_' ) !== 0 && strpos( $meta_key, 'aeo_' ) !== 0 ) {
            return new WP_Error( 'aeo_invalid_meta_key', 'Only AEO meta keys can be queried.', array( 'status' => 400 ) );
        }

        $posts = get_posts( array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_query'     => array(
                array(
                    'key'     => $meta_key,
                    'compare' => 'EXISTS',
                ),
            ),
            'fields'         => 'ids',
        ) );

        $results = array();
        foreach ( $posts as $post_id ) {
            $value = get_post_meta( $post_id, $meta_key, true );
            $results[] = array(
                'post_id'    => $post_id,
                'title'      => get_the_title( $post_id ),
                'meta_value' => $value,
            );
        }

        AEO_Activity_Log::log( 'query_post_meta', 'success', array(
            'message'  => "Queried {$meta_key}: " . count( $results ) . ' posts found.',
            'meta_key' => $meta_key,
            'count'    => count( $results ),
        ) );

        return rest_ensure_response( array(
            'ok'      => true,
            'results' => $results,
            'count'   => count( $results ),
        ) );
    }
}
