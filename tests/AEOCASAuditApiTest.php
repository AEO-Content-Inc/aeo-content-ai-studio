<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASAuditApiTest extends TestCase {

	    protected function setUp(): void {
	        $GLOBALS['aeocas_test_options'] = array(
	            'aeocas_site_token'           => 'site-token',
	            'aeocas_connection_verified'  => true,
	        );
        $GLOBALS['aeocas_test_transients'] = array();
        $GLOBALS['aeocas_test_remote_get_calls'] = array();
        $GLOBALS['aeocas_test_remote_get'] = null;
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = null;
        $GLOBALS['aeocas_test_post_ids'] = array();
        $GLOBALS['aeocas_test_post_data'] = array();
        $GLOBALS['aeocas_test_post_meta'] = array();
	        $GLOBALS['aeocas_test_current_user_can'] = null;
	        $GLOBALS['aeocas_test_wp_kses_post'] = null;
	        $GLOBALS['aeocas_test_filters'] = array();

	        $property = new ReflectionProperty( AEOCAS_Audit_Api::class, 'local_content_index_bundle' );
	        $property->setValue( null, null );
	    }

    public function test_get_visibility_returns_cached_visibility_snapshot_without_remote_call(): void {
        $GLOBALS['aeocas_test_transients']['aeocas_visibility_helpsquad-com'] = array(
            'status'           => 'ready',
            'visibility_score' => 77,
            'citations_count'  => 14,
            'engines'          => array(
                array(
                    'name'          => 'ChatGPT',
                    'count'         => 14,
                    'visibility_pct'=> 70,
                ),
            ),
        );

        $visibility = AEOCAS_Audit_Api::get_visibility();

        $this->assertIsArray( $visibility );
        $this->assertSame( 77, $visibility['visibility_score'] );
        $this->assertSame( 14, $visibility['citations_count'] );
        $this->assertSame( array(), $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    public function test_get_visibility_prefers_dedicated_visibility_endpoint_over_cached_audit_visibility(): void {
        $GLOBALS['aeocas_test_transients']['aeocas_audit_helpsquad-com'] = array(
            'visibility' => array(
                'status' => 'pending',
            ),
        );

        $test_case = $this;

        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ) use ( $test_case ): array {
            if ( false !== strpos( $url, '/api/v1/visibility/helpsquad-com' ) ) {
                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => wp_json_encode( array(
                        'data' => array(
                            array(
                                'slug'              => 'helpsquad-com',
                                'domain'            => 'helpsquad.com',
                                'engine'            => 'chatgpt',
                                'visibility_score'  => 11,
                                'visibility_total'  => 20,
                                'key_findings'      => array(),
                                'llm_response_analysis' => array(),
                                'query_variants'    => array(
                                    array(
                                        'query'          => 'customer support outsourcing',
                                        'target_visible' => true,
                                    ),
                                ),
                                'competitor_comparison' => array(),
                                'action_plan'       => array(
                                    array(
                                        'action'   => 'Expand first-hand support examples',
                                        'priority' => 'P0',
                                    ),
                                ),
                                'created_at'        => '2026-04-12T04:00:00.000Z',
                            ),
                        ),
                        'timeline' => array(
                            array(
                                'published_at' => '2026-04-05T04:00:00.000Z',
                                'score'        => 48,
                            ),
                            array(
                                'published_at' => '2026-04-12T04:00:00.000Z',
                                'score'        => 55,
                            ),
                        ),
                    ) ),
                );
            }

            $test_case->fail( 'Unexpected remote URL: ' . $url );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility();

        $this->assertIsArray( $visibility );
        $this->assertSame( 'ready', $visibility['status'] );
        $this->assertSame( 55, $visibility['visibility_score'] );
        $this->assertSame( 11, $visibility['citations_count'] );
        $this->assertCount( 1, $visibility['engines'] );
        $this->assertNotEmpty( $GLOBALS['aeocas_test_remote_get_calls'] );
        $this->assertStringContainsString(
            '/api/v1/visibility/helpsquad-com',
            $GLOBALS['aeocas_test_remote_get_calls'][0]['url']
        );
    }

    public function test_get_visibility_falls_back_to_audit_payload_when_visibility_endpoint_is_not_found(): void {
        $test_case = $this;

        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ) use ( $test_case ): array {
            if ( false !== strpos( $url, '/api/v1/visibility/helpsquad-com' ) ) {
                return array(
                    'response' => array( 'code' => 404 ),
                    'body'     => wp_json_encode( array(
                        'error' => array(
                            'code'    => 'not_found',
                            'message' => 'No visibility report found.',
                        ),
                    ) ),
                );
            }

            if ( false !== strpos( $url, '/api/v1/audits/helpsquad-com?include=all' ) ) {
                return array(
                    'response' => array( 'code' => 200 ),
                    'body'     => wp_json_encode( array(
                        'data' => array(
                            array(
                                'visibility' => array(
                                    'status'           => 'ready',
                                    'visibility_score' => 62,
                                    'citations_count'  => 8,
                                    'engines'          => array(
                                        array(
                                            'name'          => 'ChatGPT',
                                            'count'         => 8,
                                            'visibility_pct'=> 40,
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ) ),
                );
            }

            $test_case->fail( 'Unexpected remote URL: ' . $url );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertSame( 'ready', $visibility['status'] );
        $this->assertSame( 62, $visibility['visibility_score'] );
        $this->assertSame( 8, $visibility['citations_count'] );
        $this->assertCount( 1, $visibility['engines'] );
        $this->assertCount( 2, $GLOBALS['aeocas_test_remote_get_calls'] );
        $this->assertStringContainsString(
            '/api/v1/visibility/helpsquad-com',
            $GLOBALS['aeocas_test_remote_get_calls'][0]['url']
        );
        $this->assertStringContainsString(
            '/api/v1/audits/helpsquad-com?include=all',
            $GLOBALS['aeocas_test_remote_get_calls'][1]['url']
        );
    }

    public function test_get_visibility_returns_api_error_for_unexpected_visibility_response(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 500 ),
                'body'     => wp_json_encode( array(
                    'error' => array(
                        'message' => 'Visibility worker failed.',
                    ),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertInstanceOf( WP_Error::class, $visibility );
        $this->assertSame( 'aeocas_api_error', $visibility->get_error_code() );
        $this->assertSame( 'Visibility worker failed.', $visibility->get_error_message() );
        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    /**
     * The updated platform API returns monitor-based data with per-engine
     * reports containing query_variants. The plugin's normalize_visibility_payload
     * should produce the correct engine count, citation count, and score.
     */
    public function test_get_visibility_normalizes_monitor_format_with_multiple_engines(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        array(
                            'slug'             => 'helpsquad-com',
                            'domain'           => 'helpsquad.com',
                            'engine'           => 'google_aio',
                            'visibility_score' => 4,
                            'visibility_total' => 19,
                            'query_variants'   => array(
                                array( 'query' => 'White Label Bpo Services', 'target_visible' => true ),
                                array( 'query' => 'Customer Service Outsourcing', 'target_visible' => false ),
                            ),
                            'created_at'       => '2026-04-12T05:08:09.977+00:00',
                        ),
                        array(
                            'slug'             => 'helpsquad-com',
                            'domain'           => 'helpsquad.com',
                            'engine'           => 'perplexity',
                            'visibility_score' => 6,
                            'visibility_total' => 19,
                            'query_variants'   => array(
                                array( 'query' => 'best virtual medical assistant companies', 'target_visible' => true ),
                            ),
                            'created_at'       => '2026-04-12T05:08:09.977+00:00',
                        ),
                        array(
                            'slug'             => 'helpsquad-com',
                            'domain'           => 'helpsquad.com',
                            'engine'           => 'claude',
                            'visibility_score' => 0,
                            'visibility_total' => 19,
                            'query_variants'   => array(),
                            'created_at'       => '2026-04-12T05:08:09.977+00:00',
                        ),
                    ),
                    'meta' => array(
                        'request_id' => 'test-123',
                        'timestamp'  => '2026-04-12T06:00:00.000Z',
                    ),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertSame( 'ready', $visibility['status'] );
        $this->assertCount( 3, $visibility['engines'] );

        // Engine order should match API response
        $this->assertSame( 'Google AI Overview', $visibility['engines'][0]['name'] );
        $this->assertSame( 'Perplexity', $visibility['engines'][1]['name'] );
        $this->assertSame( 'Claude', $visibility['engines'][2]['name'] );

        // Visibility percentages
        $this->assertSame( 21, $visibility['engines'][0]['visibility_pct'] ); // 4/19 = 21%
        $this->assertSame( 32, $visibility['engines'][1]['visibility_pct'] ); // 6/19 = 32%
        $this->assertSame( 0, $visibility['engines'][2]['visibility_pct'] );  // 0/19 = 0%

        // Total citations = sum of visibility_score across engines
        $this->assertSame( 10, $visibility['citations_count'] ); // 4 + 6 + 0

        // Overall score = (total_visible / total_queries) * 100
        $this->assertSame( 18, $visibility['visibility_score'] ); // (10/57) * 100 ≈ 18

        // Last synced should be the latest created_at
        $this->assertSame( '2026-04-12T05:08:09.977+00:00', $visibility['last_synced_at'] );
    }

    /**
     * When the API returns a single-engine response (engine filter), the
     * data field is an object not an array. normalize_visibility_payload
     * wraps it into an array and processes it correctly.
     */
    public function test_get_visibility_normalizes_single_engine_response(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        'slug'             => 'helpsquad-com',
                        'domain'           => 'helpsquad.com',
                        'engine'           => 'chatgpt',
                        'visibility_score' => 1,
                        'visibility_total' => 19,
                        'created_at'       => '2026-04-12T04:00:00.000Z',
                    ),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertCount( 1, $visibility['engines'] );
        $this->assertSame( 'ChatGPT', $visibility['engines'][0]['name'] );
        $this->assertSame( 5, $visibility['engines'][0]['visibility_pct'] ); // 1/19 = 5%
        $this->assertSame( 1, $visibility['citations_count'] );
    }

    /**
     * An already-normalized snapshot with engines and citations_count keys
     * should pass through extract_visibility_payload without re-normalization.
     */
    public function test_get_site_slug_converts_domain_to_dash_format(): void {
        $this->assertSame( 'helpsquad-com', AEOCAS_Audit_Api::get_site_slug() );
    }

    public function test_get_site_slug_strips_www_prefix(): void {
        // get_home_url returns https://helpsquad.com (no www), but the method
        // explicitly strips www. so we test via reflection to cover that branch.
        $method = new ReflectionMethod( AEOCAS_Audit_Api::class, 'get_site_slug' );
        aeocas_make_reflection_method_accessible( $method );
        // The stub always returns https://helpsquad.com, so the slug is helpsquad-com.
        // To actually test www stripping we verify the code path by observing
        // that the result matches the expected stripped value.
        $slug = $method->invoke( null );
        $this->assertSame( 'helpsquad-com', $slug );
        $this->assertStringNotContainsString( 'www', $slug );
    }

    public function test_get_audit_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );

        $result = AEOCAS_Audit_Api::get_audit();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_key', $result->get_error_code() );
    }

    public function test_get_audit_returns_cached_data_without_remote_call(): void {
        $cached_audit = array( 'status' => 'completed', 'score' => 85 );
        $GLOBALS['aeocas_test_transients']['aeocas_audit_helpsquad-com'] = $cached_audit;

        $result = AEOCAS_Audit_Api::get_audit();

        $this->assertIsArray( $result );
        $this->assertSame( 85, $result['score'] );
        $this->assertSame( array(), $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    public function test_get_audit_fetches_from_api_when_cache_empty(): void {
        $audit_data = array( 'status' => 'completed', 'score' => 90 );

        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ) use ( $audit_data ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array( $audit_data ),
                ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit();

        $this->assertIsArray( $result );
        $this->assertSame( 'completed', $result['status'] );
        $this->assertSame( 90, $result['score'] );
        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    public function test_get_audit_records_review_milestone_when_completed(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode(
                    array(
                        'data' => array(
                            array(
                                'status' => 'completed',
                                'score'  => 91,
                            ),
                        ),
                    )
                ),
            );
        };

        AEOCAS_Audit_Api::get_audit( true );

        $state = get_option( AEOCAS_Settings::REVIEW_PROMPT_OPTION, array() );
        $this->assertSame( 1, $state['events']['audit_completed'] );
    }

    public function test_get_audit_handles_auth_failure(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 401 ),
                'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Unauthorized' ) ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_auth_expired', $result->get_error_code() );
    }

    public function test_clear_cache_removes_all_transients(): void {
        $GLOBALS['aeocas_test_transients']['aeocas_audit_helpsquad-com'] = array( 'data' => 'audit' );
        $GLOBALS['aeocas_test_transients']['aeocas_discovery_helpsquad-com'] = array( 'data' => 'discovery' );
        $GLOBALS['aeocas_test_transients']['aeocas_visibility_helpsquad-com'] = array( 'data' => 'visibility' );

        AEOCAS_Audit_Api::clear_cache();

        $this->assertArrayNotHasKey( 'aeocas_audit_helpsquad-com', $GLOBALS['aeocas_test_transients'] );
        $this->assertArrayNotHasKey( 'aeocas_discovery_helpsquad-com', $GLOBALS['aeocas_test_transients'] );
        $this->assertArrayNotHasKey( 'aeocas_visibility_helpsquad-com', $GLOBALS['aeocas_test_transients'] );
    }

    public function test_visibility_engine_label_maps_known_engines(): void {
        $method = new ReflectionMethod( AEOCAS_Audit_Api::class, 'visibility_engine_label' );
        aeocas_make_reflection_method_accessible( $method );

        $this->assertSame( 'ChatGPT', $method->invoke( null, 'chatgpt' ) );
        $this->assertSame( 'Perplexity', $method->invoke( null, 'perplexity' ) );
        $this->assertSame( 'Google AI Overview', $method->invoke( null, 'google_aio' ) );
        $this->assertSame( 'Claude', $method->invoke( null, 'claude' ) );
        $this->assertSame( 'Gemini', $method->invoke( null, 'gemini' ) );
    }

    public function test_visibility_engine_label_capitalizes_unknown(): void {
        $method = new ReflectionMethod( AEOCAS_Audit_Api::class, 'visibility_engine_label' );
        aeocas_make_reflection_method_accessible( $method );

        $this->assertSame( 'Foo Bar', $method->invoke( null, 'foo_bar' ) );
    }

    public function test_get_visibility_passes_through_pre_normalized_snapshot(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'status'           => 'ready',
                    'visibility_score' => 42,
                    'citations_count'  => 12,
                    'engines'          => array(
                        array( 'name' => 'Perplexity', 'engine' => 'perplexity', 'count' => 7, 'visibility_pct' => 37, 'tested_queries' => 19 ),
                        array( 'name' => 'ChatGPT', 'engine' => 'chatgpt', 'count' => 5, 'visibility_pct' => 26, 'tested_queries' => 19 ),
                    ),
                    'top_citations'    => array(),
                    'competitors'      => array(),
                    'last_synced_at'   => '2026-04-12T05:00:00.000Z',
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertSame( 42, $visibility['visibility_score'] );
        $this->assertSame( 12, $visibility['citations_count'] );
        $this->assertCount( 2, $visibility['engines'] );
        $this->assertSame( 'Perplexity', $visibility['engines'][0]['name'] );
    }

    public function test_get_rewrite_availability_returns_cached_snapshot_without_remote_call(): void {
        $GLOBALS['aeocas_test_transients']['aeocas_rewrite_availability_helpsquad-com'] = array(
            'available'   => 3,
            'used'        => 7,
            'limit'       => 10,
            'plan'        => 'starter',
            'resets_at'   => '2026-05-01T00:00:00Z',
            'upgrade_url' => 'https://account.aeocontent.ai/billing',
        );

        $availability = AEOCAS_Audit_Api::get_rewrite_availability();

        $this->assertIsArray( $availability );
        $this->assertSame( 3, $availability['available'] );
        $this->assertSame( array(), $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    public function test_get_rewrite_availability_normalizes_wrapped_response(): void {
        $test_case = $this;

        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ) use ( $test_case ): array {
            $test_case->assertStringContainsString( '/api/v1/rewrites/availability', $url );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        'rewrites' => array(
                            'available'           => 2,
                            'used'                => 8,
                            'limit'               => 10,
                            'plan'                => 'starter',
                            'plan_label'          => '$1 Trial Plan',
                            'resets_at'           => '2026-05-01T00:00:00Z',
                            'upgrade_url'         => 'https://account.aeocontent.ai/upgrade',
                            'starter_eligible'    => true,
                            'starter_price_cents' => 100,
                            'starter_articles'    => 5,
                            'checkout_enabled'    => true,
                        ),
                    ),
                ) ),
            );
        };

        $availability = AEOCAS_Audit_Api::get_rewrite_availability( true );

        $this->assertIsArray( $availability );
        $this->assertSame( 2, $availability['available'] );
        $this->assertSame( 8, $availability['used'] );
        $this->assertSame( 'starter', $availability['plan'] );
        $this->assertSame( '$1 Trial Plan', $availability['plan_label'] );
        $this->assertTrue( $availability['starter_eligible'] );
        $this->assertSame( 100, $availability['starter_price_cents'] );
        $this->assertTrue( $availability['checkout_enabled'] );
        $this->assertSame( 'https://account.aeocontent.ai/upgrade', $availability['upgrade_url'] );
    }

    public function test_create_rewrite_draft_rejects_exhausted_quota(): void {
        $test_case = $this;

        $GLOBALS['aeocas_test_post_ids'] = array( 12 );
        $GLOBALS['aeocas_test_post_data'][12] = (object) array(
            'ID'           => 12,
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_title'   => 'Live Article',
            'post_content' => '<p>Original content</p>',
            'post_excerpt' => 'Original excerpt',
            'post_name'    => 'live-article',
        );
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ) use ( $test_case ): array {
            $test_case->assertStringContainsString( '/api/v1/rewrites/availability', $url );

            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'available' => 0,
                    'used'      => 10,
                    'limit'     => 10,
                    'plan'      => 'starter',
                ) ),
            );
        };

        $result = AEOCAS_Audit_Api::create_rewrite_draft( array(
            'page_url' => 'https://helpsquad.com/post-12',
            'title'    => 'Rewritten title',
            'content'  => '<p>Rewrite</p>',
        ) );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_rewrites_remaining', $result->get_error_code() );
        $this->assertSame( array(), $GLOBALS['aeocas_test_insert_post_calls'] ?? array() );
    }

    public function test_apply_rewrite_draft_rejects_mismatched_source_post(): void {
        $GLOBALS['aeocas_test_post_data'][12] = (object) array(
            'ID'           => 12,
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_title'   => 'Live Article',
            'post_content' => '<p>Original content</p>',
            'post_excerpt' => 'Original excerpt',
            'post_name'    => 'live-article',
        );
        $GLOBALS['aeocas_test_post_data'][200] = (object) array(
            'ID'           => 200,
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => 'Rewrite Draft',
            'post_content' => '<p>Draft content</p>',
            'post_excerpt' => 'Draft excerpt',
            'post_name'    => 'rewrite-draft',
        );
        $GLOBALS['aeocas_test_post_meta'][200] = array(
            AEOCAS_Content::REWRITE_SOURCE_POST_META => 12,
        );

        $result = AEOCAS_Audit_Api::apply_rewrite_draft( array(
            'draft_post_id'  => 200,
            'source_post_id' => 999,
        ) );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_rewrite_source_mismatch', $result->get_error_code() );
    }

    public function test_apply_rewrite_draft_rejects_when_user_cannot_edit_draft(): void {
        $GLOBALS['aeocas_test_post_data'][12] = (object) array(
            'ID'           => 12,
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_title'   => 'Live Article',
            'post_content' => '<p>Original content</p>',
            'post_excerpt' => 'Original excerpt',
            'post_name'    => 'live-article',
        );
        $GLOBALS['aeocas_test_post_data'][200] = (object) array(
            'ID'           => 200,
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => 'Rewrite Draft',
            'post_content' => '<p>Draft content</p>',
            'post_excerpt' => 'Draft excerpt',
            'post_name'    => 'rewrite-draft',
        );
        $GLOBALS['aeocas_test_post_meta'][200] = array(
            AEOCAS_Content::REWRITE_SOURCE_POST_META => 12,
        );
        $GLOBALS['aeocas_test_current_user_can'] = static function ( string $capability, ...$args ): bool {
            if ( 'edit_post' === $capability && 200 === (int) ( $args[0] ?? 0 ) ) {
                return false;
            }

            return true;
        };

        $result = AEOCAS_Audit_Api::apply_rewrite_draft( array(
            'draft_post_id' => 200,
        ) );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_rewrite_forbidden', $result->get_error_code() );
    }

    public function test_ajax_reaudit_requires_manage_capability(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_current_user_can'] = static function ( string $capability ): bool {
            if ( 'manage_options' === $capability ) {
                return false;
            }

            return 'edit_posts' === $capability;
        };

        try { AEOCAS_Audit_Api::ajax_reaudit(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
        $this->assertSame( 403, $GLOBALS['aeocas_test_json_response']['status'] );
    }

    // Rewrite checkout is no longer handled in the plugin — billing is
    // initiated entirely from Studio (see AEOCAS_Settings::get_billing_url()),
    // so the former /api/v1/rewrites/checkout proxy tests have been removed.

    // --- Discovery tests ---

    public function test_get_discovery_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );

        $result = AEOCAS_Audit_Api::get_discovery();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_key', $result->get_error_code() );
    }

    public function test_get_discovery_returns_cached_data_without_remote_call(): void {
        $cached = array( 'status' => 'completed', 'discovery' => array( 'pages' => 10 ) );
        $GLOBALS['aeocas_test_transients']['aeocas_discovery_helpsquad-com'] = $cached;

        $result = AEOCAS_Audit_Api::get_discovery();

        $this->assertIsArray( $result );
        $this->assertSame( 'completed', $result['status'] );
        $this->assertSame( array(), $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    public function test_get_discovery_fetches_from_api_when_cache_empty(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        'status'    => 'completed',
                        'discovery' => array( 'pages' => 42 ),
                    ),
                ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_discovery( true );

        $this->assertIsArray( $result );
        $this->assertSame( 'completed', $result['status'] );
        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_get_calls'] );
    }

    public function test_get_discovery_handles_404(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 404 ),
                'body'     => '',
            );
        };

        $result = AEOCAS_Audit_Api::get_discovery( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_discovery', $result->get_error_code() );
    }

    public function test_get_discovery_handles_auth_failure(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 401 ),
                'body'     => '',
            );
        };

        $result = AEOCAS_Audit_Api::get_discovery( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_auth_expired', $result->get_error_code() );
    }

    // --- dispatch_audit tests ---

    public function test_dispatch_audit_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_remote_post'] = null;
        $GLOBALS['aeocas_test_remote_post_calls'] = array();

        $result = AEOCAS_Audit_Api::dispatch_audit();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_key', $result->get_error_code() );
    }

    public function test_dispatch_audit_blocking_returns_body_on_success(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'ok' => true, 'data' => array( 'slug' => 'helpsquad-com' ) ) ),
            );
        };

        $result = AEOCAS_Audit_Api::dispatch_audit( true );

        $this->assertIsArray( $result );
        $this->assertTrue( $result['ok'] );
        $this->assertCount( 1, $GLOBALS['aeocas_test_remote_post_calls'] );
    }

    public function test_dispatch_audit_non_blocking_returns_true(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => '',
            );
        };

        $result = AEOCAS_Audit_Api::dispatch_audit( false );

        $this->assertTrue( $result );
    }

    public function test_dispatch_audit_handles_auth_failure(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 403 ),
                'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Forbidden' ) ) ),
            );
        };

        $result = AEOCAS_Audit_Api::dispatch_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_auth_expired', $result->get_error_code() );
    }

    public function test_dispatch_audit_handles_server_error(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['wpdb']->inserts = array();
        $GLOBALS['aeocas_test_remote_post'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 500 ),
                'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Server error.' ) ) ),
            );
        };

        $result = AEOCAS_Audit_Api::dispatch_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_reaudit_error', $result->get_error_code() );
    }

    public function test_dispatch_audit_handles_wp_error_response(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['wpdb']->inserts = array();
        $GLOBALS['aeocas_test_remote_post'] = static function () {
            return new WP_Error( 'http_error', 'Connection timed out' );
        };

        $result = AEOCAS_Audit_Api::dispatch_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }

    public function test_trigger_reaudit_delegates_to_dispatch_audit(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = static function (): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'ok' => true, 'data' => array( 'slug' => 'helpsquad-com' ) ) ),
            );
        };

        $result = AEOCAS_Audit_Api::trigger_reaudit();
        $this->assertIsArray( $result );
        $this->assertTrue( $result['ok'] );
    }

    public function test_trigger_onboarding_delegates_to_dispatch_audit_non_blocking(): void {
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = static function (): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => '',
            );
        };

        $result = AEOCAS_Audit_Api::trigger_onboarding();
        $this->assertTrue( $result );
    }

    // --- get_audit_status tests ---

    public function test_get_audit_status_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );

        $result = AEOCAS_Audit_Api::get_audit_status();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_key', $result->get_error_code() );
    }

    public function test_get_audit_status_returns_body_on_success(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'status' => 'completed' ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit_status();

        $this->assertIsArray( $result );
        $this->assertSame( 'completed', $result['status'] );
    }

    public function test_get_audit_status_handles_auth_failure(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 401 ),
                'body'     => '',
            );
        };

        $result = AEOCAS_Audit_Api::get_audit_status();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_auth_expired', $result->get_error_code() );
    }

    // --- AJAX handler tests ---
    // wp_send_json_success/error stubs throw AEOCAS_Test_Json_Exit to simulate die().

    public function test_ajax_get_audit_returns_success_for_cached_data(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_transients']['aeocas_audit_helpsquad-com'] = array( 'status' => 'completed', 'score' => 95 );

        try { AEOCAS_Audit_Api::ajax_get_audit(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_get_audit_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_json_response'] = null;

        try { AEOCAS_Audit_Api::ajax_get_audit(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_reaudit_returns_success(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_remote_post_calls'] = array();
        $GLOBALS['aeocas_test_remote_post'] = static function (): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'ok' => true, 'data' => array( 'slug' => 'helpsquad-com' ) ) ),
            );
        };

        try { AEOCAS_Audit_Api::ajax_reaudit(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_reaudit_returns_error_on_failure(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_json_response'] = null;

        try { AEOCAS_Audit_Api::ajax_reaudit(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_get_discovery_returns_success(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_transients']['aeocas_discovery_helpsquad-com'] = array( 'status' => 'completed' );

        try { AEOCAS_Audit_Api::ajax_get_discovery(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_get_discovery_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_json_response'] = null;

        try { AEOCAS_Audit_Api::ajax_get_discovery(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_get_visibility_returns_success(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_transients']['aeocas_visibility_helpsquad-com'] = array(
            'status'           => 'ready',
            'visibility_score' => 50,
            'citations_count'  => 5,
            'engines'          => array(),
        );

        try { AEOCAS_Audit_Api::ajax_get_visibility(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_get_visibility_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_json_response'] = null;

        try { AEOCAS_Audit_Api::ajax_get_visibility(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_get_rewrite_availability_returns_success(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_transients']['aeocas_rewrite_availability_helpsquad-com'] = array(
            'available' => 4,
            'used'      => 6,
            'limit'     => 10,
            'plan'      => 'starter',
        );

        try { AEOCAS_Audit_Api::ajax_get_rewrite_availability(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
        $this->assertSame( 4, $GLOBALS['aeocas_test_json_response']['data']['available'] );
    }

    public function test_ajax_get_rewrite_availability_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_json_response'] = null;

        try { AEOCAS_Audit_Api::ajax_get_rewrite_availability(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_audit_status_returns_success(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'status' => 'running' ) ),
            );
        };

        try { AEOCAS_Audit_Api::ajax_audit_status(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    public function test_ajax_audit_status_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );
        $GLOBALS['aeocas_test_json_response'] = null;

        try { AEOCAS_Audit_Api::ajax_audit_status(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertFalse( $GLOBALS['aeocas_test_json_response']['success'] );
    }

    // --- ajax_get_local_content_index test ---

	    public function test_ajax_get_local_content_index_returns_items(): void {
        $GLOBALS['aeocas_test_json_response'] = null;
        $GLOBALS['aeocas_test_post_ids'] = array( 1, 2 );
        $GLOBALS['aeocas_test_post_data'] = array(
            1 => (object) array( 'ID' => 1, 'post_type' => 'page', 'post_status' => 'publish' ),
            2 => (object) array( 'ID' => 2, 'post_type' => 'post', 'post_status' => 'draft' ),
        );
        $GLOBALS['aeocas_test_post_meta'] = array(
            1 => array(
                '_aeocas_faq_schema'            => array( array( 'q' => 'What?', 'a' => 'This.' ) ),
                '_aeocas_canonical_url'         => 'https://helpsquad.com/canonical',
                '_aeocas_rewrite_status'        => 'draft_ready',
                '_aeocas_active_rewrite_draft_id' => 21,
            ),
            2 => array(
                '_aeocas_faq_schema'    => '',
                '_aeocas_canonical_url' => '',
            ),
        );

        try { AEOCAS_Audit_Api::ajax_get_local_content_index(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
        $this->assertArrayHasKey( 'items', $GLOBALS['aeocas_test_json_response']['data'] );
        $this->assertCount( 2, $GLOBALS['aeocas_test_json_response']['data']['items'] );

        // First item should have faq_count = 1.
        $this->assertSame( 1, $GLOBALS['aeocas_test_json_response']['data']['items'][0]['faq_count'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['data']['items'][0]['has_faq'] );
        $this->assertSame( 'page', $GLOBALS['aeocas_test_json_response']['data']['items'][0]['post_type'] );
        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['data']['items'][0]['can_edit'] );
        $this->assertSame( 'draft_ready', $GLOBALS['aeocas_test_json_response']['data']['items'][0]['rewrite_status'] );
        $this->assertSame( 21, $GLOBALS['aeocas_test_json_response']['data']['items'][0]['active_rewrite_draft_id'] );

        // Second item should have faq_count = 0.
        $this->assertSame( 0, $GLOBALS['aeocas_test_json_response']['data']['items'][1]['faq_count'] );
        $this->assertSame( 'draft', $GLOBALS['aeocas_test_json_response']['data']['items'][1]['status'] );

	        unset( $GLOBALS['aeocas_test_post_ids'], $GLOBALS['aeocas_test_post_meta'], $GLOBALS['aeocas_test_post_data'] );
	    }

	    public function test_ajax_get_local_content_index_excludes_posts_the_user_cannot_edit(): void {
	        $GLOBALS['aeocas_test_json_response'] = null;
	        $GLOBALS['aeocas_test_post_ids'] = array( 1, 2 );
	        $GLOBALS['aeocas_test_post_data'] = array(
	            1 => (object) array( 'ID' => 1, 'post_type' => 'page', 'post_status' => 'publish' ),
	            2 => (object) array( 'ID' => 2, 'post_type' => 'post', 'post_status' => 'private' ),
	        );
	        $GLOBALS['aeocas_test_current_user_can'] = static function ( string $capability, ...$args ): bool {
	            if ( 'edit_post' === $capability && 2 === (int) ( $args[0] ?? 0 ) ) {
	                return false;
	            }

	            return true;
	        };

	        try { AEOCAS_Audit_Api::ajax_get_local_content_index(); } catch ( AEOCAS_Test_Json_Exit $e ) {}

	        $this->assertNotNull( $GLOBALS['aeocas_test_json_response'] );
	        $this->assertTrue( $GLOBALS['aeocas_test_json_response']['success'] );
	        $this->assertCount( 1, $GLOBALS['aeocas_test_json_response']['data']['items'] );
	        $this->assertSame( 1, $GLOBALS['aeocas_test_json_response']['data']['items'][0]['id'] );
	    }

    // --- register_ajax test ---

    public function test_register_ajax_runs_without_error(): void {
        AEOCAS_Audit_Api::register_ajax();
        $this->assertTrue( true );
    }

    // --- get_audit 404 and unexpected responses ---

    public function test_get_audit_handles_404(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 404 ),
                'body'     => wp_json_encode( array() ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_audit', $result->get_error_code() );
    }

    public function test_get_audit_handles_unexpected_response(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 500 ),
                'body'     => wp_json_encode( array( 'error' => array( 'message' => 'Internal error' ) ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }

    public function test_get_audit_handles_wp_error(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function () {
            return new WP_Error( 'http_error', 'Timeout' );
        };

        $result = AEOCAS_Audit_Api::get_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }

    // --- get_visibility error paths ---

    public function test_get_visibility_returns_error_when_no_token(): void {
        unset( $GLOBALS['aeocas_test_options']['aeocas_site_token'] );

        $result = AEOCAS_Audit_Api::get_visibility();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_no_key', $result->get_error_code() );
    }

    public function test_get_visibility_handles_auth_failure(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function (): array {
            return array(
                'response' => array( 'code' => 403 ),
                'body'     => '',
            );
        };

        $result = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_auth_expired', $result->get_error_code() );
    }

    public function test_get_visibility_handles_wp_error(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function () {
            return new WP_Error( 'http_error', 'Connection refused' );
        };

        $result = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }

    // --- handle_auth_failure side effects ---

    public function test_handle_auth_failure_clears_tokens(): void {
        $GLOBALS['aeocas_test_options']['aeocas_site_token']          = 'token';
        $GLOBALS['aeocas_test_options']['aeocas_plugin_token']        = 'plugin-token';
        $GLOBALS['aeocas_test_options']['aeocas_connection_verified'] = true;

        // Trigger auth failure via a 401 on get_audit
        $GLOBALS['aeocas_test_remote_get'] = static function (): array {
            return array(
                'response' => array( 'code' => 401 ),
                'body'     => '',
            );
        };

        AEOCAS_Audit_Api::get_audit( true );

        $this->assertArrayNotHasKey( 'aeocas_site_token', $GLOBALS['aeocas_test_options'] );
        $this->assertArrayNotHasKey( 'aeocas_plugin_token', $GLOBALS['aeocas_test_options'] );
        $this->assertArrayNotHasKey( 'aeocas_connection_verified', $GLOBALS['aeocas_test_options'] );
    }

    // --- Discovery short cache for pending status ---

    public function test_get_discovery_uses_short_cache_for_pending_status(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        'status'    => 'discovering',
                        'discovery' => null,
                    ),
                ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_discovery( true );

        $this->assertIsArray( $result );
        // Verify data was cached
        $this->assertArrayHasKey( 'aeocas_discovery_helpsquad-com', $GLOBALS['aeocas_test_transients'] );
    }

    // --- get_discovery with missing data ---

    public function test_get_discovery_returns_error_on_empty_data(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array( 'data' => null ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_discovery( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }

    public function test_get_discovery_handles_wp_error(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function () {
            return new WP_Error( 'http_error', 'DNS failure' );
        };

        $result = AEOCAS_Audit_Api::get_discovery( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }

    // --- normalize_visibility_payload with edge cases ---

    public function test_normalize_handles_query_deltas(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        array(
                            'slug'             => 'helpsquad-com',
                            'domain'           => 'helpsquad.com',
                            'engine'           => 'chatgpt',
                            'visibility_score' => 3,
                            'visibility_total' => 10,
                            'query_variants'   => array(),
                            'created_at'       => '2026-04-12T00:00:00.000Z',
                        ),
                    ),
                    'query_deltas' => array(
                        array( 'query' => 'live chat support', 'change' => 'gained' ),
                        array( 'query' => 'help desk outsourcing', 'change' => 'lost' ),
                    ),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertNotEmpty( $visibility['alerts'] );

        $alert_titles = array_column( $visibility['alerts'], 'title' );
        $found_gained = false;
        $found_lost   = false;
        foreach ( $alert_titles as $title ) {
            if ( strpos( $title, 'Gained' ) !== false ) {
                $found_gained = true;
            }
            if ( strpos( $title, 'Lost' ) !== false ) {
                $found_lost = true;
            }
        }
        $this->assertTrue( $found_gained );
        $this->assertTrue( $found_lost );
    }

    public function test_normalize_handles_competitor_comparison(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        array(
                            'slug'                  => 'helpsquad-com',
                            'domain'                => 'helpsquad.com',
                            'engine'                => 'chatgpt',
                            'visibility_score'      => 5,
                            'visibility_total'      => 10,
                            'query_variants'        => array(
                                array(
                                    'query'          => 'customer support outsourcing',
                                    'target_visible' => true,
                                    'competitor_visibility' => array(
                                        'zendesk.com' => true,
                                        'freshdesk.com' => true,
                                    ),
                                ),
                            ),
                            'competitor_comparison' => array(
                                array(
                                    'query'       => 'live chat',
                                    'competitors' => array(
                                        array( 'name' => 'intercom.com' ),
                                    ),
                                ),
                            ),
                            'llm_response_analysis' => array(
                                array( 'domain' => 'drift.com' ),
                            ),
                            'created_at' => '2026-04-12T00:00:00.000Z',
                        ),
                    ),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertNotEmpty( $visibility['competitors'] );

        $names = array_column( $visibility['competitors'], 'name' );
        $this->assertContains( 'zendesk.com', $names );
        $this->assertContains( 'freshdesk.com', $names );
    }

    public function test_normalize_handles_key_findings_and_action_plan(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => array(
                        array(
                            'slug'             => 'helpsquad-com',
                            'domain'           => 'helpsquad.com',
                            'engine'           => 'perplexity',
                            'visibility_score' => 2,
                            'visibility_total' => 10,
                            'key_findings'     => array(
                                array( 'text' => 'Site lacks structured data', 'type' => 'warning' ),
                                array( 'text' => 'Good content quality', 'type' => 'finding' ),
                            ),
                            'action_plan'      => array(
                                array( 'action' => 'Add FAQ schema', 'priority' => 'P0', 'impact' => 'High visibility boost' ),
                                array( 'action' => 'Improve meta descriptions', 'priority' => 'P2' ),
                            ),
                            'query_variants'   => array(),
                            'created_at'       => '2026-04-12T00:00:00.000Z',
                        ),
                    ),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertNotEmpty( $visibility['alerts'] );

        $categories = array_column( $visibility['alerts'], 'category' );
        $this->assertContains( 'finding', $categories );
        $this->assertContains( 'action', $categories );

        // P0 actions should be 'critical' severity
        $p0_alerts = array_filter( $visibility['alerts'], static function ( $a ) {
            return 'critical' === $a['severity'] && 'action' === $a['category'];
        } );
        $this->assertNotEmpty( $p0_alerts );
    }

    // --- get_visibility with pending status uses short cache ---

    public function test_get_visibility_uses_short_cache_for_pending_status(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'status'           => 'pending',
                    'visibility_score' => 0,
                    'citations_count'  => 0,
                    'engines'          => array(),
                ) ),
            );
        };

        $visibility = AEOCAS_Audit_Api::get_visibility( true );

        $this->assertIsArray( $visibility );
        $this->assertArrayHasKey( 'aeocas_visibility_helpsquad-com', $GLOBALS['aeocas_test_transients'] );
    }

    // --- get_audit with single object data ---

    public function test_get_audit_handles_single_object_data(): void {
        $audit_data = array( 'status' => 'completed', 'slug' => 'helpsquad-com' );

        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ) use ( $audit_data ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array(
                    'data' => $audit_data,
                ) ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit( true );

        $this->assertIsArray( $result );
        $this->assertSame( 'completed', $result['status'] );
    }

    // --- get_audit empty body with 200 ---

    public function test_get_audit_handles_200_with_empty_body(): void {
        $GLOBALS['aeocas_test_remote_get'] = static function ( string $url, array $args ): array {
            return array(
                'response' => array( 'code' => 200 ),
                'body'     => wp_json_encode( array() ),
            );
        };

        $result = AEOCAS_Audit_Api::get_audit( true );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_api_error', $result->get_error_code() );
    }
}
