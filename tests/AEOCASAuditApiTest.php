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
}
