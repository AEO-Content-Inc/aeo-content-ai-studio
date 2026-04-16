<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AEOCASCommandRunnerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['aeocas_test_options'] = array();
		$GLOBALS['wpdb']->inserts       = array();
	}

	public function test_run_publish_post_records_review_milestone_on_success(): void {
		$plugin = new class {
			public function get_module( $slug ) {
				return new class {
					public function create_or_update_post( $payload ) {
						return rest_ensure_response(
							array(
								'ok'      => true,
								'post_id' => 321,
							)
						);
					}
				};
			}
		};

		$runner = new AEOCAS_Command_Runner( $plugin );
		$result = $runner->run(
			'publish_post',
			array(
				'title' => 'Test Draft',
			)
		);

		$this->assertSame( 321, $result->get_data()['post_id'] );

		$state = get_option( AEOCAS_Settings::REVIEW_PROMPT_OPTION, array() );
		$this->assertSame( 1, $state['events']['publish_success'] );
	}

	public function test_run_publish_post_does_not_record_review_milestone_on_error(): void {
		$plugin = new class {
			public function get_module( $slug ) {
				return new class {
					public function create_or_update_post( $payload ) {
						return new WP_Error( 'publish_failed', 'Nope' );
					}
				};
			}
		};

		$runner = new AEOCAS_Command_Runner( $plugin );
		$runner->run(
			'publish_post',
			array(
				'title' => 'Broken Draft',
			)
		);

		$state = get_option( AEOCAS_Settings::REVIEW_PROMPT_OPTION, array() );
		$this->assertArrayNotHasKey( 'events', $state );
	}
}
