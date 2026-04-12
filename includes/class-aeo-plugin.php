<?php
/**
 * Main plugin class. Singleton that loads all modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Plugin {

	private static $instance = null;

	/** @var array Loaded module instances keyed by slug. */
	private $modules = array();

	/** @var AEOCAS_Command_Runner|null */
	private $command_runner = null;

	/** @var array Map of module slug => class file and class name. */
	private $available_modules = array(
		'content' => array(
			'file'  => 'class-aeo-content.php',
			'class' => 'AEOCAS_Content',
		),
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_core();
		$this->load_modules();

		register_deactivation_hook( AEOCAS_PLUGIN_FILE, array( $this, 'on_deactivate' ) );
	}

	/**
	 * Load core (non-optional) classes.
	 */
	private function load_core() {
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-capabilities.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-auth.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-activity-log.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-command-runner.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-rest-api.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-heartbeat.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-settings.php';
		require_once AEOCAS_PLUGIN_DIR . 'includes/class-aeo-audit-api.php';

		new AEOCAS_Auth();
		new AEOCAS_Rest_Api( $this );
		new AEOCAS_Heartbeat();
		new AEOCAS_Settings();
		AEOCAS_Audit_Api::register_ajax();

		// Schedule activity log cleanup cron.
		AEOCAS_Activity_Log::schedule_cleanup();
		add_action( 'aeocas_activity_log_cleanup', array( 'AEOCAS_Activity_Log', 'cleanup' ) );

		// CSV export handler (must run early before headers sent).
		add_action( 'admin_init', array( 'AEOCAS_Activity_Log', 'handle_csv_export' ) );
	}

	/**
	 * Load enabled feature modules.
	 */
	private function load_modules() {
		$enabled = $this->get_enabled_features();

		foreach ( $enabled as $slug ) {
			if ( isset( $this->available_modules[ $slug ] ) ) {
				$info = $this->available_modules[ $slug ];
				$file = AEOCAS_PLUGIN_DIR . 'includes/modules/' . $info['file'];
				if ( file_exists( $file ) ) {
					require_once $file;
					$class                  = $info['class'];
					$this->modules[ $slug ] = new $class();
				}
			}
		}
	}

	/**
	 * Get the list of enabled feature slugs.
	 *
	 * @return string[]
	 */
	public function get_enabled_features() {
		$features = get_option( 'aeocas_enabled_features', array() );
		return is_array( $features ) ? $features : array();
	}

	/**
	 * Check if a specific feature is enabled.
	 */
	public function is_feature_enabled( $slug ) {
		return in_array( $slug, $this->get_enabled_features(), true );
	}

	/**
	 * Enable a feature module.
	 */
	public function enable_feature( $slug ) {
		if ( ! isset( $this->available_modules[ $slug ] ) ) {
			return false;
		}
		$features = $this->get_enabled_features();
		if ( ! in_array( $slug, $features, true ) ) {
			$features[] = $slug;
			update_option( 'aeocas_enabled_features', $features );
		}
		return true;
	}

	/**
	 * Disable a feature module.
	 */
	public function disable_feature( $slug ) {
		$features = $this->get_enabled_features();
		$features = array_values( array_diff( $features, array( $slug ) ) );
		update_option( 'aeocas_enabled_features', $features );
		return true;
	}

	/**
	 * Get a loaded module instance.
	 *
	 * @return object|null
	 */
	public function get_module( $slug ) {
		return isset( $this->modules[ $slug ] ) ? $this->modules[ $slug ] : null;
	}

	/**
	 * Get the shared command runner instance.
	 *
	 * @return AEOCAS_Command_Runner
	 */
	public function get_command_runner() {
		if ( null === $this->command_runner ) {
			$this->command_runner = new AEOCAS_Command_Runner( $this );
		}

		return $this->command_runner;
	}

	/**
	 * Get all available module slugs.
	 */
	public function get_available_modules() {
		return array_keys( $this->available_modules );
	}

	/**
	 * Activation hook.
	 */
	public function on_activate() {
		// Set default enabled features if first activation.
		if ( false === get_option( 'aeocas_enabled_features' ) ) {
			update_option( 'aeocas_enabled_features', array( 'content' ) );
		}

		// Create activity log table.
		AEOCAS_Activity_Log::create_table();

		// Schedule heartbeat cron.
		AEOCAS_Heartbeat::activate();

		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 */
	public function on_deactivate() {
		wp_clear_scheduled_hook( 'aeocas_heartbeat_event' );
		wp_clear_scheduled_hook( 'aeocas_activity_log_cleanup' );
		flush_rewrite_rules();
	}
}
