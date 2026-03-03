<?php
/**
 * Fired when the plugin is uninstalled.
 * Cleans up all plugin data from wp_options and post meta.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
$options = array(
    'aeo_site_token',
    'aeo_platform_url',
    'aeo_enabled_features',
    'aeo_llms_txt_content',
    'aeo_ai_txt_content',
    'aeo_robots_ai_rules',
    'aeo_org_schema',
    'aeo_website_schema',
    'aeo_author_defaults',
    'aeo_canonical_overrides',
    'aeo_semantic_html_enabled',
    'aeo_freshness_enabled',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove per-post meta.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s, %s)",
        '_aeo_faq_schema',
        '_aeo_author_schema',
        '_aeo_speakable',
        '_aeo_canonical_url'
    )
);

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'aeo_heartbeat_event' );

// Flush rewrite rules to remove our custom rules.
flush_rewrite_rules();
