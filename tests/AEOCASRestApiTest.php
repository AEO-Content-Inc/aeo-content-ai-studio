<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASRestApiTest extends TestCase {

    /** @var AEOCAS_Rest_Api */
    private $rest_api;

    protected function setUp(): void {
        $GLOBALS['aeocas_test_options'] = array(
            'aeocas_plugin_token' => 'valid-token',
        );
        $GLOBALS['wpdb']->inserts = array();

        $plugin = aeocas_plugin();
        $this->rest_api = new AEOCAS_Rest_Api( $plugin );
    }

    public function test_check_auth_delegates_to_auth_class(): void {
        $request = new WP_REST_Request( 'GET' );
        $request->set_header( 'x_api_key', 'valid-token' );

        $result = $this->rest_api->check_auth( $request );
        $this->assertTrue( $result );
    }

    public function test_check_auth_returns_error_for_missing_key(): void {
        $request = new WP_REST_Request( 'GET' );

        $result = $this->rest_api->check_auth( $request );
        $this->assertInstanceOf( WP_Error::class, $result );
    }

    public function test_handle_status_returns_plugin_info(): void {
        $request = new WP_REST_Request( 'GET' );

        $response = $this->rest_api->handle_status( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( AEOCAS_VERSION, $data['version'] );
        $this->assertIsArray( $data['features'] );
    }

    public function test_handle_command_returns_error_for_missing_command(): void {
        $request = new WP_REST_Request( 'POST' );
        // No command param set.

        $result = $this->rest_api->handle_command( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_missing_command', $result->get_error_code() );
    }

    public function test_handle_command_returns_error_for_unknown_command(): void {
        $request = new WP_REST_Request( 'POST' );
        $request->set_param( 'command', 'nonexistent_command' );

        $result = $this->rest_api->handle_command( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_unknown_command', $result->get_error_code() );
    }

    public function test_handle_command_publish_post_returns_module_disabled(): void {
        $request = new WP_REST_Request( 'POST' );
        $request->set_param( 'command', 'publish_post' );
        $request->set_param( 'payload', array( 'title' => 'Test' ) );

        $result = $this->rest_api->handle_command( $request );

        // Since aeocas_plugin()->get_module('content') returns null,
        // this should return a module disabled error.
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_module_disabled', $result->get_error_code() );
    }

    public function test_handle_get_posts_returns_module_disabled_error(): void {
        $request = new WP_REST_Request( 'GET' );

        $result = $this->rest_api->handle_get_posts( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_module_disabled', $result->get_error_code() );
    }

    public function test_handle_get_post_returns_module_disabled_error(): void {
        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', 1 );

        $result = $this->rest_api->handle_get_post( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_module_disabled', $result->get_error_code() );
    }

    public function test_handle_get_categories_returns_empty_list(): void {
        $request = new WP_REST_Request( 'GET' );

        $response = $this->rest_api->handle_get_categories( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( array(), $data['categories'] );
        $this->assertSame( 0, $data['total'] );
    }

    public function test_handle_get_tags_returns_empty_list(): void {
        $request = new WP_REST_Request( 'GET' );

        $response = $this->rest_api->handle_get_tags( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( array(), $data['tags'] );
        $this->assertSame( 0, $data['total'] );
    }

    public function test_register_routes_runs_without_error(): void {
        // register_rest_route is a no-op stub, but we verify the method
        // executes without throwing.
        $this->rest_api->register_routes();
        $this->assertTrue( true );
    }

    public function test_handle_get_posts_with_module_and_results(): void {
        // Create a RestApi with a plugin that has a content module.
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) {
                if ( 'content' === $slug ) {
                    return new class {};
                }
                return null;
            }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $post          = new stdClass();
        $post->ID      = 1;
        $post->post_title   = 'Test Post';
        $post->post_name    = 'test-post';
        $post->post_status  = 'publish';
        $post->post_date_gmt    = '2026-04-12 00:00:00';
        $post->post_modified_gmt = '2026-04-12 00:00:00';
        $post->post_excerpt      = 'Excerpt';
        $post->post_content      = 'Content body text';
        $post->post_author       = 1;

        $GLOBALS['aeocas_test_wp_query_result'] = array(
            'posts'         => array( $post ),
            'found_posts'   => 1,
            'max_num_pages' => 1,
        );

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'page', 1 );
        $request->set_param( 'per_page', 10 );
        $request->set_param( 'status', 'publish' );
        $request->set_param( 'post_type', 'post' );
        $request->set_param( 'orderby', 'date' );
        $request->set_param( 'order', 'DESC' );

        $response = $api->handle_get_posts( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 1, $data['total'] );
        $this->assertCount( 1, $data['posts'] );
        $this->assertSame( 'Test Post', $data['posts'][0]['title'] );

        unset( $GLOBALS['aeocas_test_wp_query_result'] );
    }

    public function test_handle_get_posts_with_search(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) { return 'content' === $slug ? new class {} : null; }
        };

        $api = new AEOCAS_Rest_Api( $plugin );
        $GLOBALS['aeocas_test_wp_query_result'] = array( 'posts' => array(), 'found_posts' => 0, 'max_num_pages' => 0 );

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'search', 'test query' );
        $request->set_param( 'post_type', 'page' );
        $request->set_param( 'status', 'draft' );
        $request->set_param( 'orderby', 'title' );
        $request->set_param( 'order', 'ASC' );

        $response = $api->handle_get_posts( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 0, $data['total'] );

        unset( $GLOBALS['aeocas_test_wp_query_result'] );
    }

    public function test_handle_get_posts_sanitizes_invalid_params(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) { return 'content' === $slug ? new class {} : null; }
        };

        $api = new AEOCAS_Rest_Api( $plugin );
        $GLOBALS['aeocas_test_wp_query_result'] = array( 'posts' => array(), 'found_posts' => 0, 'max_num_pages' => 0 );

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'post_type', 'custom_type' ); // should fall back to 'post'
        $request->set_param( 'status', 'invalid_status' ); // should fall back to 'publish'
        $request->set_param( 'orderby', 'invalid_order' ); // should fall back to 'date'

        $response = $api->handle_get_posts( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );

        unset( $GLOBALS['aeocas_test_wp_query_result'] );
    }

    public function test_handle_get_post_returns_full_post_data(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) { return 'content' === $slug ? new class {} : null; }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $post          = new stdClass();
        $post->ID      = 42;
        $post->post_title   = 'Single Post';
        $post->post_name    = 'single-post';
        $post->post_status  = 'publish';
        $post->post_date_gmt    = '2026-04-12 00:00:00';
        $post->post_modified_gmt = '2026-04-12 00:00:00';
        $post->post_excerpt      = '';
        $post->post_content      = 'Full post content here.';
        $post->post_author       = 1;

        $GLOBALS['aeocas_test_post_data'][42] = $post;

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', 42 );

        $response = $api->handle_get_post( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 'Single Post', $data['post']['title'] );
        $this->assertSame( 'Full post content here.', $data['post']['content'] );

        unset( $GLOBALS['aeocas_test_post_data'][42] );
    }

    public function test_handle_get_post_includes_content_and_meta(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) { return 'content' === $slug ? new class {} : null; }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $post              = new stdClass();
        $post->ID          = 55;
        $post->post_title  = 'Meta Post';
        $post->post_name   = 'meta-post';
        $post->post_status = 'publish';
        $post->post_date_gmt     = '2026-04-12 00:00:00';
        $post->post_modified_gmt = '2026-04-12 00:00:00';
        $post->post_excerpt      = 'An excerpt';
        $post->post_content      = 'Post content with body.';
        $post->post_author       = 1;

        $GLOBALS['aeocas_test_post_data'][55] = $post;
        $GLOBALS['aeocas_test_post_meta'][55] = array(
            '_aeocas_faq_schema'      => array( array( 'q' => 'Why?', 'a' => 'Because.' ) ),
            '_aeocas_canonical_url'   => 'https://helpsquad.com/canonical',
            '_aeocas_speakable'       => 'speakable-data',
            '_aeocas_author_schema'   => 'author-schema',
        );

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', 55 );

        $response = $api->handle_get_post( $request );
        $data     = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 'Post content with body.', $data['post']['content'] );
        $this->assertArrayHasKey( 'aeocas_meta', $data['post'] );
        $this->assertArrayHasKey( 'faq', $data['post']['aeocas_meta'] );
        $this->assertArrayHasKey( 'canonical', $data['post']['aeocas_meta'] );
        $this->assertArrayHasKey( 'speakable', $data['post']['aeocas_meta'] );
        $this->assertArrayHasKey( 'author_schema', $data['post']['aeocas_meta'] );

        unset( $GLOBALS['aeocas_test_post_data'][55], $GLOBALS['aeocas_test_post_meta'][55] );
    }

    public function test_handle_get_post_includes_author_and_thumbnail(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) { return 'content' === $slug ? new class {} : null; }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $post              = new stdClass();
        $post->ID          = 60;
        $post->post_title  = 'Author Post';
        $post->post_name   = 'author-post';
        $post->post_status = 'publish';
        $post->post_date_gmt     = '2026-04-12 00:00:00';
        $post->post_modified_gmt = '2026-04-12 00:00:00';
        $post->post_excerpt      = 'Excerpt text';
        $post->post_content      = 'Content body.';
        $post->post_author       = 5;

        $GLOBALS['aeocas_test_post_data'][60] = $post;

        // Set up author data.
        $author = new stdClass();
        $author->ID           = 5;
        $author->display_name = 'John Doe';
        $author->user_email   = 'john@example.com';
        $GLOBALS['aeocas_test_userdata'] = array( 5 => $author );

        // Set up thumbnail.
        $GLOBALS['aeocas_test_post_thumbnail'] = array( 60 => 100 );
        $GLOBALS['aeocas_test_post_meta'][100] = array(
            '_wp_attachment_image_alt' => 'Alt text for image',
        );

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', 60 );

        $response = $api->handle_get_post( $request );
        $data     = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertArrayHasKey( 'author', $data['post'] );
        $this->assertSame( 'John Doe', $data['post']['author']['display_name'] );
        $this->assertSame( 'john@example.com', $data['post']['author']['email'] );
        $this->assertArrayHasKey( 'featured_image', $data['post'] );
        $this->assertSame( 100, $data['post']['featured_image']['id'] );
        $this->assertStringContainsString( 'img-100', $data['post']['featured_image']['url'] );

        unset(
            $GLOBALS['aeocas_test_post_data'][60],
            $GLOBALS['aeocas_test_userdata'],
            $GLOBALS['aeocas_test_post_thumbnail'],
            $GLOBALS['aeocas_test_post_meta'][100]
        );
    }

    public function test_handle_get_categories_returns_term_data(): void {
        $term = new stdClass();
        $term->term_id = 1;
        $term->name    = 'Technology';
        $term->slug    = 'technology';
        $term->parent  = 0;
        $term->count   = 5;

        $GLOBALS['aeocas_test_terms'] = array( $term );

        $request = new WP_REST_Request( 'GET' );
        $response = $this->rest_api->handle_get_categories( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 1, $data['total'] );
        $this->assertSame( 'Technology', $data['categories'][0]['name'] );

        unset( $GLOBALS['aeocas_test_terms'] );
    }

    public function test_handle_get_tags_returns_term_data(): void {
        $term = new stdClass();
        $term->term_id = 10;
        $term->name    = 'JavaScript';
        $term->slug    = 'javascript';
        $term->count   = 12;

        $GLOBALS['aeocas_test_terms'] = array( $term );

        $request = new WP_REST_Request( 'GET' );
        $response = $this->rest_api->handle_get_tags( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 1, $data['total'] );
        $this->assertSame( 'JavaScript', $data['tags'][0]['name'] );

        unset( $GLOBALS['aeocas_test_terms'] );
    }

    public function test_handle_get_post_returns_not_found(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) { return 'content' === $slug ? new class {} : null; }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'id', 999 ); // no such post

        $result = $api->handle_get_post( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_not_found', $result->get_error_code() );
    }

    public function test_handle_get_categories_with_search_and_parent(): void {
        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'search', 'tech' );
        $request->set_param( 'parent', 5 );
        $request->set_param( 'hide_empty', true );
        $request->set_param( 'orderby', 'count' );
        $request->set_param( 'order', 'DESC' );

        $response = $this->rest_api->handle_get_categories( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
    }

    public function test_handle_get_tags_with_search(): void {
        $request = new WP_REST_Request( 'GET' );
        $request->set_param( 'search', 'javascript' );
        $request->set_param( 'hide_empty', true );
        $request->set_param( 'orderby', 'count' );
        $request->set_param( 'order', 'DESC' );

        $response = $this->rest_api->handle_get_tags( $request );
        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
    }

    public function test_handle_get_categories_returns_error_from_get_terms(): void {
        $GLOBALS['aeocas_test_terms'] = new WP_Error( 'term_error', 'Invalid taxonomy' );

        $request = new WP_REST_Request( 'GET' );
        $response = $this->rest_api->handle_get_categories( $request );

        $this->assertInstanceOf( WP_Error::class, $response );

        unset( $GLOBALS['aeocas_test_terms'] );
    }

    public function test_handle_get_tags_returns_error_from_get_terms(): void {
        $GLOBALS['aeocas_test_terms'] = new WP_Error( 'term_error', 'Invalid taxonomy' );

        $request = new WP_REST_Request( 'GET' );
        $response = $this->rest_api->handle_get_tags( $request );

        $this->assertInstanceOf( WP_Error::class, $response );

        unset( $GLOBALS['aeocas_test_terms'] );
    }

    public function test_cmd_publish_post_with_module_returning_error(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) {
                if ( 'content' === $slug ) {
                    return new class {
                        public function create_or_update_post( $payload ) {
                            return new WP_Error( 'publish_failed', 'Missing content' );
                        }
                    };
                }
                return null;
            }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $request = new WP_REST_Request( 'POST' );
        $request->set_param( 'command', 'publish_post' );
        $request->set_param( 'payload', array( 'title' => 'Test Post' ) );

        $result = $api->handle_command( $request );

        // The command dispatches to cmd_publish_post which calls module->create_or_update_post.
        // On WP_Error, it logs the error and returns.
        $this->assertInstanceOf( WP_Error::class, $result );
    }

    public function test_cmd_publish_post_with_module_returning_success(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) {
                if ( 'content' === $slug ) {
                    return new class {
                        public function create_or_update_post( $payload ) {
                            return rest_ensure_response( array( 'ok' => true, 'post_id' => 42 ) );
                        }
                    };
                }
                return null;
            }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $request = new WP_REST_Request( 'POST' );
        $request->set_param( 'command', 'publish_post' );
        $request->set_param( 'payload', array( 'title' => 'New Post' ) );

        $result = $api->handle_command( $request );
        $data = $result->get_data();

        $this->assertTrue( $data['ok'] );
    }

    public function test_cmd_publish_post_handles_exception(): void {
        $plugin = new class {
            public function get_enabled_features() { return array( 'content' ); }
            public function get_available_modules() { return array( 'content' ); }
            public function get_module( $slug ) {
                if ( 'content' === $slug ) {
                    return new class {
                        public function create_or_update_post( $payload ) {
                            throw new RuntimeException( 'Unexpected error' );
                        }
                    };
                }
                return null;
            }
        };

        $api = new AEOCAS_Rest_Api( $plugin );

        $request = new WP_REST_Request( 'POST' );
        $request->set_param( 'command', 'publish_post' );
        $request->set_param( 'payload', array( 'title' => 'Broken Post' ) );

        $result = $api->handle_command( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_internal_error', $result->get_error_code() );
    }

    public function test_handle_publish_returns_module_disabled(): void {
        $request = new WP_REST_Request( 'POST' );
        $request->set_body( wp_json_encode( array( 'title' => 'Test' ) ) );

        $result = $this->rest_api->handle_publish( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_module_disabled', $result->get_error_code() );
    }
}
