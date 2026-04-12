<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/modules/class-aeo-content.php';

final class AEOCASContentTest extends TestCase {

    /** @var AEOCAS_Content */
    private $content;

    protected function setUp(): void {
        $this->content = new AEOCAS_Content();

        $GLOBALS['aeocas_test_post_data'] = array();
        $GLOBALS['aeocas_test_post_meta'] = array();
        $GLOBALS['aeocas_test_insert_post_calls'] = array();
        $GLOBALS['aeocas_test_update_post_calls'] = array();
        $GLOBALS['aeocas_test_next_post_id'] = 200;
        $GLOBALS['aeocas_test_current_user_can'] = null;
        $GLOBALS['aeocas_test_wp_kses_post'] = null;
        $GLOBALS['aeocas_test_remote_head'] = null;
        $GLOBALS['aeocas_test_remote_head_calls'] = array();
        $GLOBALS['aeocas_test_media_sideload'] = null;
        $GLOBALS['aeocas_test_attachment_ids_by_url'] = array();
        $GLOBALS['aeocas_test_post_thumbnail'] = array();
        $GLOBALS['aeocas_test_filters'] = array();
    }

    public function test_create_or_update_post_preserves_existing_post_type(): void {
        $GLOBALS['aeocas_test_post_data'][55] = (object) array(
            'ID'           => 55,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Source Page',
            'post_content' => '<p>Old</p>',
            'post_excerpt' => '',
            'post_name'    => 'source-page',
        );

        $response = $this->content->create_or_update_post( array(
            'post_id' => 55,
            'title'   => 'Updated Page',
            'content' => '<p>Updated</p>',
        ) );

        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertSame( 'page', $GLOBALS['aeocas_test_update_post_calls'][0]['post_type'] );
        $this->assertSame( 'publish', $GLOBALS['aeocas_test_post_data'][55]->post_status );
    }

    public function test_create_or_update_post_rejects_unsupported_post_type(): void {
        $result = $this->content->create_or_update_post( array(
            'post_type' => 'product',
            'title'     => 'Unsupported',
            'content'   => '<p>Nope</p>',
        ) );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_unsupported_post_type', $result->get_error_code() );
    }

    public function test_create_rewrite_draft_creates_linked_draft_without_touching_source(): void {
        $GLOBALS['aeocas_test_post_data'][12] = (object) array(
            'ID'           => 12,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Live Article',
            'post_content' => '<p>Original content</p>',
            'post_excerpt' => 'Original excerpt',
            'post_name'    => 'live-article',
        );

        $response = $this->content->create_rewrite_draft( array(
            'source_post_id' => 12,
            'rewrite_id'     => 'rw_123',
            'audit_stamp'    => 'aud_456',
            'title'          => 'Live Article (Rewritten)',
            'content'        => '<p>Draft rewrite</p>',
            'excerpt'        => 'Draft excerpt',
        ) );

        $data = $response->get_data();

        $this->assertSame( 200, $data['draft_post_id'] );
        $this->assertSame( 12, $data['source_post_id'] );
        $this->assertSame( 'draft_ready', $data['rewrite_status'] );
        $this->assertSame( 'page', $GLOBALS['aeocas_test_insert_post_calls'][0]['post_type'] );
        $this->assertSame( 'draft', $GLOBALS['aeocas_test_insert_post_calls'][0]['post_status'] );
        $this->assertSame( 'publish', $GLOBALS['aeocas_test_post_data'][12]->post_status );
        $this->assertSame( 12, $GLOBALS['aeocas_test_post_meta'][200][AEOCAS_Content::REWRITE_SOURCE_POST_META] );
        $this->assertSame( 'draft_ready', $GLOBALS['aeocas_test_post_meta'][200][AEOCAS_Content::REWRITE_STATUS_META] );
        $this->assertSame( 200, $GLOBALS['aeocas_test_post_meta'][12][AEOCAS_Content::ACTIVE_REWRITE_DRAFT_META] );
    }

    public function test_apply_rewrite_draft_updates_source_post_without_demoting_it(): void {
        $GLOBALS['aeocas_test_post_data'][12] = (object) array(
            'ID'           => 12,
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Live Article',
            'post_content' => '<p>Original content</p>',
            'post_excerpt' => 'Original excerpt',
            'post_name'    => 'live-article',
        );
        $GLOBALS['aeocas_test_post_data'][200] = (object) array(
            'ID'           => 200,
            'post_type'    => 'page',
            'post_status'  => 'draft',
            'post_title'   => 'Reviewed Rewrite',
            'post_content' => '<p>Approved rewrite</p>',
            'post_excerpt' => 'Approved excerpt',
            'post_name'    => 'live-article-rewrite-review',
        );
        $GLOBALS['aeocas_test_post_meta'][12] = array(
            AEOCAS_Content::ACTIVE_REWRITE_DRAFT_META => 200,
        );
        $GLOBALS['aeocas_test_post_meta'][200] = array(
            AEOCAS_Content::REWRITE_SOURCE_POST_META => 12,
            AEOCAS_Content::REWRITE_ID_META          => 'rw_123',
            AEOCAS_Content::REWRITE_AUDIT_STAMP_META => 'aud_456',
            AEOCAS_Content::REWRITE_STATUS_META      => 'draft_ready',
        );

        $response = $this->content->apply_rewrite_draft( array(
            'draft_post_id' => 200,
        ) );

        $data = $response->get_data();

        $this->assertSame( 12, $data['source_post_id'] );
        $this->assertSame( 200, $data['draft_post_id'] );
        $this->assertSame( 'applied', $data['rewrite_status'] );
        $this->assertSame( 12, $GLOBALS['aeocas_test_update_post_calls'][0]['ID'] );
        $this->assertSame( 'page', $GLOBALS['aeocas_test_update_post_calls'][0]['post_type'] );
        $this->assertSame( 'Reviewed Rewrite', $GLOBALS['aeocas_test_post_data'][12]->post_title );
        $this->assertSame( '<p>Approved rewrite</p>', $GLOBALS['aeocas_test_post_data'][12]->post_content );
        $this->assertSame( 'publish', $GLOBALS['aeocas_test_post_data'][12]->post_status );
        $this->assertArrayNotHasKey( AEOCAS_Content::ACTIVE_REWRITE_DRAFT_META, $GLOBALS['aeocas_test_post_meta'][12] );
        $this->assertSame( 12, $GLOBALS['aeocas_test_post_meta'][200][AEOCAS_Content::REWRITE_APPLIED_TO_POST_META] );
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
            'post_title'   => 'Rewrite Review',
            'post_content' => '<p>Reviewed rewrite</p>',
            'post_excerpt' => 'Reviewed excerpt',
            'post_name'    => 'rewrite-review',
        );
        $GLOBALS['aeocas_test_post_meta'][200] = array(
            AEOCAS_Content::REWRITE_SOURCE_POST_META => 12,
        );

        $result = $this->content->apply_rewrite_draft( array(
            'draft_post_id'  => 200,
            'source_post_id' => 999,
        ) );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'aeocas_rewrite_source_mismatch', $result->get_error_code() );
        $this->assertSame( array(), $GLOBALS['aeocas_test_update_post_calls'] );
    }

    public function test_create_or_update_post_preserves_gutenberg_block_comments(): void {
        $GLOBALS['aeocas_test_post_data'][55] = (object) array(
            'ID'           => 55,
            'post_type'    => 'post',
            'post_status'  => 'publish',
            'post_title'   => 'Source Post',
            'post_content' => '<p>Old</p>',
            'post_excerpt' => '',
            'post_name'    => 'source-post',
        );
        $GLOBALS['aeocas_test_wp_kses_post'] = static function ( string $content ): string {
            return (string) preg_replace( '/<!--[\s\S]*?-->/', '', $content );
        };

        $this->content->create_or_update_post( array(
            'post_id' => 55,
            'content' => '<!-- wp:paragraph --><p>Block text</p><!-- /wp:paragraph -->',
        ) );

        $this->assertSame(
            '<!-- wp:paragraph --><p>Block text</p><!-- /wp:paragraph -->',
            $GLOBALS['aeocas_test_post_data'][55]->post_content
        );
    }

    public function test_create_or_update_post_skips_private_featured_image_hosts(): void {
        $response = $this->content->create_or_update_post( array(
            'title'              => 'Remote image test',
            'content'            => '<p>Body</p>',
            'featured_image_url' => 'http://127.0.0.1/private.jpg',
        ) );

        $data = $response->get_data();

        $this->assertTrue( $data['ok'] );
        $this->assertArrayNotHasKey( $data['post_id'], $GLOBALS['aeocas_test_post_thumbnail'] );
        $this->assertSame( array(), $GLOBALS['aeocas_test_remote_head_calls'] );
    }
}
