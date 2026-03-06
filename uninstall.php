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
    'aeo_connection_verified',
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
delete_post_meta_by_key( '_aeo_faq_schema' );
delete_post_meta_by_key( '_aeo_author_schema' );
delete_post_meta_by_key( '_aeo_speakable' );
delete_post_meta_by_key( '_aeo_canonical_url' );

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'aeo_heartbeat_event' );

// Flush rewrite rules to remove our custom rules.
flush_rewrite_rules();
