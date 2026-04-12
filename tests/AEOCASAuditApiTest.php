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
}
