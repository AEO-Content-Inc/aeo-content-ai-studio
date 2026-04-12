<?php
/**
 * Heartbeat module. Pings AEO Content platform every 6 hours.
 *
 * Reports plugin version, active features, WP version.
 * Receives any pending commands that failed push delivery.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Heartbeat {

	const CRON_HOOK     = 'aeocas_heartbeat_event';
	const INTERVAL_NAME = 'aeocas_six_hours';

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'send_heartbeat' ) );

		// Capture the real site URL during admin requests (HTTP_HOST is correct here).
		if ( is_admin() && ! wp_doing_cron() ) {
			$current = site_url();
			if ( $current && ! filter_var( wp_parse_url( $current, PHP_URL_HOST ), FILTER_VALIDATE_IP ) ) {
				update_option( 'aeocas_real_site_url', $current, false );
				update_option( 'aeocas_real_home_url', home_url(), false );
			}
		}
	}

	/**
	 * Schedule the heartbeat cron event. Called from activation hook.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::INTERVAL_NAME, self::CRON_HOOK );
		}
	}

	/**
	 * Add a 6-hour interval to WP-Cron.
	 */
	public function add_cron_schedule( $schedules ) {
		$schedules[ self::INTERVAL_NAME ] = array(
			'interval' => 3 * HOUR_IN_SECONDS,
			'display'  => 'Every 3 hours',
		);
		return $schedules;
	}

	/**
	 * Send heartbeat to platform.
	 */
	public function send_heartbeat() {
		$platform_url = AEOCAS_PLATFORM_URL;
		$api_key      = get_option( 'aeocas_site_token', '' );

		if ( empty( $api_key ) || empty( $platform_url ) ) {
			return;
		}

		global $wp_version;

		$body = wp_json_encode(
			array(
				'site_url'  => get_option( 'aeocas_real_site_url', site_url() ),
				'home_url'  => get_option( 'aeocas_real_home_url', home_url() ),
				'version'   => AEOCAS_VERSION,
				'wp'        => $wp_version,
				'php'       => PHP_VERSION,
				'features'  => aeocas_plugin()->get_enabled_features(),
				'timestamp' => time(),
			)
		);

		$response = wp_remote_post(
			trailingslashit( $platform_url ) . 'api/v1/plugin/heartbeat',
			array(
				'body'    => $body,
				'headers' => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $api_key,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			AEOCAS_Activity_Log::log( 'heartbeat', 'error', array( 'message' => 'Could not reach platform: ' . $response->get_error_message() ) );
			return;
		}

		$status        = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$request_data  = json_decode( $body, true );

		if ( 200 !== $status ) {
			AEOCAS_Activity_Log::log(
				'heartbeat',
				'error',
				array(
					'message'     => "Platform returned {$status}.",
					'status_code' => $status,
					'features'    => isset( $request_data['features'] ) && is_array( $request_data['features'] ) ? array_values( $request_data['features'] ) : array(),
				)
			);
			return;
		}

		// Process any pending commands returned by platform.
		$result = json_decode( $response_body, true );
		if ( ! empty( $result['commands'] ) && is_array( $result['commands'] ) ) {
			$count = count( $result['commands'] );
			AEOCAS_Activity_Log::log(
				'heartbeat',
				'success',
				array(
					'message'       => "Heartbeat sent. {$count} pending commands executed.",
					'command_count' => $count,
					'features'      => isset( $request_data['features'] ) && is_array( $request_data['features'] ) ? array_values( $request_data['features'] ) : array(),
				)
			);
			$this->process_pending_commands( $result['commands'] );
		} else {
			AEOCAS_Activity_Log::log(
				'heartbeat',
				'success',
				array(
					'message'  => 'Heartbeat sent successfully.',
					'features' => isset( $request_data['features'] ) && is_array( $request_data['features'] ) ? array_values( $request_data['features'] ) : array(),
				)
			);
		}
	}

	/**
	 * Execute pending commands received from platform.
	 */
	private function process_pending_commands( $commands ) {
		$plugin         = aeocas_plugin();
		$command_runner = method_exists( $plugin, 'get_command_runner' )
			? $plugin->get_command_runner()
			: new AEOCAS_Command_Runner( $plugin );

		foreach ( $commands as $cmd ) {
			if ( empty( $cmd['command'] ) ) {
				continue;
			}

			$command_runner->run(
				$cmd['command'],
				isset( $cmd['payload'] ) && is_array( $cmd['payload'] ) ? $cmd['payload'] : array()
			);
		}
	}
}
