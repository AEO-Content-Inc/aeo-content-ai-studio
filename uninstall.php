<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from wp_options, post meta, transients, and cron.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
$aeocas_options = array(
    'aeocas_site_token',
    'aeocas_plugin_token',
    'aeocas_connection_verified',
    'aeocas_enabled_features',
    'aeocas_activity_log_db_version',
    'aeocas_real_site_url',
    'aeocas_real_home_url',
);

foreach ( $aeocas_options as $aeocas_option ) {
    delete_option( $aeocas_option );
}

// Drop activity log table.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'aeocas_activity_log' ) );

// Remove per-post meta.
delete_post_meta_by_key( '_aeocas_faq_schema' );
delete_post_meta_by_key( '_aeocas_canonical_url' );
delete_post_meta_by_key( '_aeocas_speakable' );
delete_post_meta_by_key( '_aeocas_author_schema' );

// Remove audit transients.
$aeocas_like_transient = $wpdb->esc_like( '_transient_aeocas_audit_' ) . '%';
$aeocas_like_timeout   = $wpdb->esc_like( '_transient_timeout_aeocas_audit_' ) . '%';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $aeocas_like_transient,
        $aeocas_like_timeout
    )
);

// Clear all scheduled cron events.
wp_clear_scheduled_hook( 'aeocas_heartbeat_event' );
wp_clear_scheduled_hook( 'aeocas_activity_log_cleanup' );

// Flush rewrite rules to remove our custom rules.
flush_rewrite_rules();
