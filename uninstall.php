<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from wp_options, post meta, transients, and cron.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
$options = array(
    'aeo_site_token',
    'aeo_plugin_token',
    'aeo_connection_verified',
    'aeo_enabled_features',
    'aeo_activity_log_db_version',
    'aeo_real_site_url',
    'aeo_real_home_url',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Drop activity log table.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'aeo_activity_log' ) );

// Remove per-post meta.
delete_post_meta_by_key( '_aeo_faq_schema' );
delete_post_meta_by_key( '_aeo_canonical_url' );
delete_post_meta_by_key( '_aeo_speakable' );
delete_post_meta_by_key( '_aeo_author_schema' );

// Remove audit transients.
$like_transient = $wpdb->esc_like( '_transient_aeo_audit_' ) . '%';
$like_timeout   = $wpdb->esc_like( '_transient_timeout_aeo_audit_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $like_transient,
        $like_timeout
    )
);

// Clear all scheduled cron events.
wp_clear_scheduled_hook( 'aeo_heartbeat_event' );
wp_clear_scheduled_hook( 'aeo_activity_log_cleanup' );

// Flush rewrite rules to remove our custom rules.
flush_rewrite_rules();
