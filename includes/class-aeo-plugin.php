<?php
/**
 * Main plugin class. Singleton that loads all modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Plugin {

    private static $instance = null;

    /** @var array Loaded module instances keyed by slug. */
    private $modules = array();

    /** @var array Map of module slug => class file and class name. */
    private $available_modules = array(
        'llms-txt'      => array( 'file' => 'class-aeo-llms-txt.php',      'class' => 'AEO_Llms_Txt' ),
        'ai-txt'        => array( 'file' => 'class-aeo-ai-txt.php',        'class' => 'AEO_Ai_Txt' ),
        'robots-txt'    => array( 'file' => 'class-aeo-robots-txt.php',    'class' => 'AEO_Robots_Txt' ),
        'schema-org'    => array( 'file' => 'class-aeo-schema-org.php',    'class' => 'AEO_Schema_Org' ),
        'schema-post'   => array( 'file' => 'class-aeo-schema-post.php',   'class' => 'AEO_Schema_Post' ),
        'canonical'     => array( 'file' => 'class-aeo-canonical.php',     'class' => 'AEO_Canonical' ),
        'semantic-html' => array( 'file' => 'class-aeo-semantic-html.php', 'class' => 'AEO_Semantic_Html' ),
        'freshness'     => array( 'file' => 'class-aeo-freshness.php',     'class' => 'AEO_Freshness' ),
        'content'       => array( 'file' => 'class-aeo-content.php',       'class' => 'AEO_Content' ),
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

        add_action( 'activated_plugin', array( $this, 'on_activate' ) );
        register_deactivation_hook( AEO_PLUGIN_FILE, array( $this, 'on_deactivate' ) );
    }

    /**
     * Load core (non-optional) classes.
     */
    private function load_core() {
        require_once AEO_PLUGIN_DIR . 'includes/class-aeo-auth.php';
        require_once AEO_PLUGIN_DIR . 'includes/class-aeo-activity-log.php';
        require_once AEO_PLUGIN_DIR . 'includes/class-aeo-rest-api.php';
        require_once AEO_PLUGIN_DIR . 'includes/class-aeo-heartbeat.php';
        require_once AEO_PLUGIN_DIR . 'includes/class-aeo-settings.php';

        new AEO_Auth();
        new AEO_Rest_Api( $this );
        new AEO_Heartbeat();
        new AEO_Settings();

        // Schedule activity log cleanup cron.
        AEO_Activity_Log::schedule_cleanup();
        add_action( 'aeo_activity_log_cleanup', array( 'AEO_Activity_Log', 'cleanup' ) );

        // CSV export handler (must run early before headers sent).
        add_action( 'admin_init', array( 'AEO_Activity_Log', 'handle_csv_export' ) );
    }

    /**
     * Load enabled feature modules.
     */
    private function load_modules() {
        $enabled = $this->get_enabled_features();

        foreach ( $enabled as $slug ) {
            if ( isset( $this->available_modules[ $slug ] ) ) {
                $info = $this->available_modules[ $slug ];
                $file = AEO_PLUGIN_DIR . 'includes/modules/' . $info['file'];
                if ( file_exists( $file ) ) {
                    require_once $file;
                    $class = $info['class'];
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
        $features = get_option( 'aeo_enabled_features', array() );
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
            update_option( 'aeo_enabled_features', $features );
        }
        return true;
    }

    /**
     * Disable a feature module.
     */
    public function disable_feature( $slug ) {
        $features = $this->get_enabled_features();
        $features = array_values( array_diff( $features, array( $slug ) ) );
        update_option( 'aeo_enabled_features', $features );
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
     * Get all available module slugs.
     */
    public function get_available_modules() {
        return array_keys( $this->available_modules );
    }

    /**
     * Activation hook.
     */
    public function on_activate( $plugin ) {
        if ( plugin_basename( AEO_PLUGIN_FILE ) !== $plugin ) {
            return;
        }
        // Set default enabled features if first activation.
        if ( false === get_option( 'aeo_enabled_features' ) ) {
            update_option( 'aeo_enabled_features', array(
                'llms-txt',
                'ai-txt',
                'robots-txt',
                'schema-org',
                'schema-post',
                'canonical',
                'semantic-html',
                'freshness',
                'content',
            ) );
        }

        // Create activity log table.
        AEO_Activity_Log::create_table();

        // Schedule heartbeat cron.
        AEO_Heartbeat::activate();

        flush_rewrite_rules();
    }

    /**
     * Deactivation hook.
     */
    public function on_deactivate() {
        wp_clear_scheduled_hook( 'aeo_heartbeat_event' );
        wp_clear_scheduled_hook( 'aeo_activity_log_cleanup' );
        flush_rewrite_rules();
    }
}
