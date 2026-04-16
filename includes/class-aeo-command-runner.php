<?php
/**
 * Executes authenticated platform commands.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Command_Runner {

	/** @var AEOCAS_Plugin */
	private $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Execute a supported command.
	 *
	 * @param string $command Command slug.
	 * @param array  $payload Command payload.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run( $command, $payload = array() ) {
		$command = sanitize_key( (string) $command );
		$payload = is_array( $payload ) ? $payload : array();

		switch ( $command ) {
			case 'publish_post':
				return $this->run_publish_post( $payload );
		}

		AEOCAS_Activity_Log::log( $command, 'error', array( 'message' => "Unknown command: {$command}" ) );
		/* translators: %s: command name */
		return new WP_Error( 'aeocas_unknown_command', sprintf( __( 'Unknown command: %s', 'aeo-content-ai-studio' ), $command ), array( 'status' => 400 ) );
	}

	/**
	 * Publish or update a post via the content module.
	 *
	 * @param array $payload Publish payload.
	 * @return WP_REST_Response|WP_Error
	 */
	private function run_publish_post( $payload ) {
		$module = $this->plugin->get_module( 'content' );
		if ( ! $module ) {
			AEOCAS_Activity_Log::log( 'publish_post', 'error', array( 'message' => 'Content module is not enabled.' ) );
			return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
		}

		$title = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : 'untitled';

		try {
			$result = $module->create_or_update_post( $payload );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[AEO] publish_post fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			AEOCAS_Activity_Log::log(
				'publish_post',
				'error',
				array(
					'message' => 'Internal error during publish.',
					'error'   => $e->getMessage(),
				)
			);
			return new WP_Error( 'aeocas_internal_error', __( 'Internal error during publish.', 'aeo-content-ai-studio' ), array( 'status' => 500 ) );
		}

		$post_id = null;
		if ( ! is_wp_error( $result ) ) {
			$data    = $result->get_data();
			$post_id = isset( $data['post_id'] ) ? absint( $data['post_id'] ) : null;
		}

		AEOCAS_Activity_Log::log(
			'publish_post',
			is_wp_error( $result ) ? 'error' : 'success',
			array(
				'message' => is_wp_error( $result ) ? $result->get_error_message() : "Published: {$title}",
			),
			$post_id
		);

		if ( ! is_wp_error( $result ) && class_exists( 'AEOCAS_Settings' ) ) {
			AEOCAS_Settings::record_review_milestone( 'publish_success' );
		}

		return $result;
	}
}
