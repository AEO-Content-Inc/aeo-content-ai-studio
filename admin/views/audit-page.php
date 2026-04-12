<?php
/**
 * Audit Report admin page template.
 *
 * Single-page plugin UI with tabs for Discovery, Site Audit, Connect,
 * Scoreboard, Opportunities, Rewrite Candidates, and Activity Log.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Settings state used by the Connect tab (merged from the old settings page).
$aeocas_plugin        = aeocas_plugin();
$aeocas_features      = $aeocas_plugin->get_enabled_features();
$aeocas_modules       = $aeocas_plugin->get_available_modules();
$aeocas_token         = get_option( 'aeocas_site_token', '' );
$aeocas_platform      = AEOCAS_PLATFORM_URL;
$aeocas_connected     = ! empty( $aeocas_token ) && get_option( 'aeocas_connection_verified', false );
$aeocas_site_url      = AEOCAS_Settings::get_site_url();
$aeocas_connect_url   = AEOCAS_Settings::get_connect_url( 'start' );
$aeocas_signin_url    = AEOCAS_Settings::get_connect_url( 'signin' );
$aeocas_manage_url    = AEOCAS_Settings::get_manage_url();
$aeocas_notice        = isset( $_GET['aeocas_notice'] ) ? sanitize_key( wp_unslash( $_GET['aeocas_notice'] ) ) : '';
$aeocas_requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
$aeocas_module_labels = array(
    'content' => array( 'label' => 'Content Publishing', 'desc' => 'Allow the AEO Content platform to read, create, and update posts on this site.' ),
);

// Activity log state used by the Activity Log tab.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
$aeocas_log_page     = max( 1, isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1 );
$aeocas_log_per_page = 10;
$aeocas_log_filters  = array();
foreach ( array( 'command', 'status', 'date_from', 'date_to' ) as $aeocas_log_f ) {
    if ( ! empty( $_GET[ $aeocas_log_f ] ) ) {
        $aeocas_log_filters[ $aeocas_log_f ] = sanitize_text_field( wp_unslash( $_GET[ $aeocas_log_f ] ) );
    }
}
// phpcs:enable
$aeocas_logs     = AEOCAS_Activity_Log::get_logs( $aeocas_log_page, $aeocas_log_per_page, $aeocas_log_filters );
$aeocas_stats    = AEOCAS_Activity_Log::get_stats();
$aeocas_commands = AEOCAS_Activity_Log::get_commands();
$aeocas_log_base = admin_url( 'admin.php?page=aeocas-audit-report' );
$aeocas_logo_url = AEOCAS_PLUGIN_URL . 'admin/images/icon.svg';
$aeocas_valid_tabs = array( 'connect', 'discovery', 'scoreboard', 'site-audit', 'opportunities', 'rewrite', 'activity' );
$aeocas_stage_by_tab = array(
    'connect'       => 'connect',
    'discovery'     => 'discovery',
    'scoreboard'    => 'diagnose',
    'site-audit'    => 'diagnose',
    'opportunities' => 'fix',
    'rewrite'       => 'fix',
    'activity'      => 'track',
);
$aeocas_active_tab = in_array( $aeocas_requested_tab, $aeocas_valid_tabs, true ) ? $aeocas_requested_tab : 'connect';
$aeocas_active_stage = isset( $aeocas_stage_by_tab[ $aeocas_active_tab ] ) ? $aeocas_stage_by_tab[ $aeocas_active_tab ] : 'connect';
$aeocas_log_last_action_label = $aeocas_stats['last_action']
    ? sprintf(
        /* translators: %s: human-readable time diff */
        __( '%s ago', 'aeo-content-ai-studio' ),
        human_time_diff( strtotime( $aeocas_stats['last_action'] ), time() )
    )
    : __( 'Never', 'aeo-content-ai-studio' );
$aeocas_activity_error_count = isset( $aeocas_stats['error'] ) ? (int) $aeocas_stats['error'] : 0;
?>
<style id="aeo-audit-critical-shell">
    /* Critical shell styles live inline as a cache-safe fallback so plugin
       upgrades do not leave the workflow header/rail unstyled in wp-admin. */
    #aeo-audit-wrap .aeo-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 22px;
        margin: 6px 0 24px;
        padding: 24px 28px;
        border: 1px solid rgba(31, 42, 51, 0.12);
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(15, 118, 110, 0.12), transparent 36%),
            linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(249, 245, 236, 0.96));
        box-shadow: 0 18px 40px rgba(31, 42, 51, 0.08);
    }
    #aeo-audit-wrap .aeo-header-brand { display:flex; align-items:flex-start; gap:16px; min-width:0; }
    #aeo-audit-wrap .aeo-header-logo-wrap {
        display:inline-flex; align-items:center; justify-content:center; width:76px; height:76px; flex-shrink:0;
        border-radius:22px; border:1px solid rgba(15, 118, 110, 0.18);
        background:linear-gradient(180deg, rgba(15, 118, 110, 0.12), rgba(255,255,255,0.98));
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.85);
    }
    #aeo-audit-wrap .aeo-header-logo { display:block; width:52px; height:52px; }
    #aeo-audit-wrap .aeo-header-copy { display:flex; flex-direction:column; gap:8px; min-width:0; }
    #aeo-audit-wrap .aeo-header-kicker {
        display:inline-flex; align-items:center; width:max-content; padding:4px 10px;
        border-radius:999px; background:rgba(15, 118, 110, 0.08); color:#0f766e;
        font-size:11px; font-weight:800; letter-spacing:0.08em; text-transform:uppercase;
    }
    #aeo-audit-wrap .aeo-settings h1,
    #aeo-audit-wrap h1 {
        margin:0;
        font-size:32px;
        line-height:1.05;
    }
    #aeo-audit-wrap .aeo-subtitle { margin:0; font-size:15px; line-height:1.6; max-width:70ch; }
    #aeo-audit-wrap .aeo-header-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; margin-left:auto; }
    #aeo-audit-wrap .aeo-header-actions .button { min-height:40px; padding-inline:16px; }
    #aeo-audit-wrap .aeo-version {
        display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:0 12px;
        border-radius:999px; background:rgba(31, 42, 51, 0.06); color:#66717d; font-size:12px; font-weight:700;
    }

    #aeo-audit-wrap .aeo-workflow-rail {
        position: relative;
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 18px;
        margin: 0 0 28px;
        padding: 6px 0 10px;
    }
    #aeo-audit-wrap .aeo-workflow-rail::before {
        content: '';
        position: absolute;
        top: 52px;
        left: 84px;
        right: 84px;
        height: 3px;
        background: linear-gradient(90deg, rgba(15, 118, 110, 0.18), rgba(15, 118, 110, 0.08));
        pointer-events: none;
    }
    #aeo-audit-wrap .aeo-workflow-step {
        position: relative;
        display: flex !important;
        flex-direction: column;
        gap: 16px;
        min-height: 154px;
        padding: 20px 20px 18px;
        border: 1px solid rgba(31, 42, 51, 0.12);
        border-radius: 24px;
        background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(249,245,236,0.94));
        box-shadow: 0 18px 40px rgba(31, 42, 51, 0.08);
        color: #1f2a33 !important;
        text-decoration: none !important;
        overflow: visible;
    }
    #aeo-audit-wrap .aeo-workflow-step.is-active {
        transform: translateY(-3px);
        border-color: rgba(15, 118, 110, 0.45);
        box-shadow: 0 22px 50px rgba(31, 42, 51, 0.14);
    }
    #aeo-audit-wrap .aeo-workflow-step.is-attention {
        background: linear-gradient(180deg, rgba(255,250,249,0.98), rgba(253,231,226,0.96));
        border-color: rgba(196, 61, 61, 0.28);
    }
    #aeo-audit-wrap .aeo-workflow-step.is-progress {
        background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(223,244,239,0.72));
        border-color: rgba(15, 118, 110, 0.24);
    }
    #aeo-audit-wrap .aeo-workflow-step.is-healthy {
        background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(228,243,232,0.92));
        border-color: rgba(47, 133, 90, 0.28);
    }
    #aeo-audit-wrap .aeo-workflow-step-top { display:flex; align-items:center; gap:12px; min-width:0; padding-right:24px; }
    #aeo-audit-wrap .aeo-workflow-step-index {
        position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center;
        width:40px; height:40px; border-radius:999px; border:1px solid rgba(31,42,51,0.12);
        background:#fff; font-size:15px; font-weight:700; box-shadow:0 10px 20px rgba(31,42,51,0.08);
    }
    #aeo-audit-wrap .aeo-workflow-step-icon {
        display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px;
        border-radius:14px; border:1px solid rgba(15,118,110,0.16); background:rgba(15,118,110,0.08); color:#0f766e;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.82);
    }
    #aeo-audit-wrap .aeo-workflow-step-icon .dashicons { width:20px; height:20px; font-size:20px; }
    #aeo-audit-wrap .aeo-workflow-step-body { display:flex; flex-direction:column; gap:8px; min-width:0; padding-right:26px; }
    #aeo-audit-wrap .aeo-workflow-step-title { display:block; font-size:18px; font-weight:700; line-height:1.2; }
    #aeo-audit-wrap .aeo-workflow-step-label { display:block; color:#66717d; font-size:14px; line-height:1.55; }
    #aeo-audit-wrap .aeo-workflow-step-state {
        display:inline-flex; align-items:center; justify-content:center; width:max-content; max-width:100%;
        margin-top:auto; padding:6px 10px; border-radius:999px; font-size:11px; font-weight:700;
        letter-spacing:0.03em; text-transform:uppercase; background:#eef2f4; color:#66717d;
    }
    #aeo-audit-wrap .aeo-workflow-step-state.is-attention { background:#fde7e2; color:#c43d3d; }
    #aeo-audit-wrap .aeo-workflow-step-state.is-progress { background:#dff4ef; color:#0f766e; }
    #aeo-audit-wrap .aeo-workflow-step-state.is-healthy { background:#e4f3e8; color:#2f855a; }

    #aeo-audit-wrap .aeo-workflow-badge,
    #aeo-audit-wrap .aeo-subtab-badge {
        display:none;
        align-items:center;
        justify-content:center;
        min-width:26px;
        height:26px;
        padding:0 8px;
        border-radius:999px;
        background:#c43d3d;
        color:#fff;
        font-size:12px;
        font-weight:700;
        line-height:1;
        box-shadow:0 0 0 4px #fff, 0 12px 24px rgba(196, 61, 61, 0.24);
    }
    #aeo-audit-wrap .aeo-workflow-badge.is-visible,
    #aeo-audit-wrap .aeo-subtab-badge.is-visible { display:inline-flex !important; }
    #aeo-audit-wrap .aeo-workflow-badge { position:absolute; top:14px; right:14px; z-index:2; }

    #aeo-audit-wrap .aeo-subtabs { display:flex; gap:12px; flex-wrap:wrap; }
    #aeo-audit-wrap .aeo-subtab {
        display:inline-flex !important;
        align-items:center;
        gap:10px;
        min-height:56px;
        padding:10px 16px 10px 12px;
        border:1px solid rgba(31, 42, 51, 0.12);
        border-radius:18px;
        background:linear-gradient(180deg, rgba(255,253,248,0.9), rgba(245,241,232,0.72));
        color:#66717d !important;
        font-size:14px;
        font-weight:700;
        text-decoration:none !important;
        box-sizing:border-box;
    }
    #aeo-audit-wrap .aeo-subtab.is-active {
        background:#fffdf8;
        color:#1f2a33 !important;
        border-color:rgba(15, 118, 110, 0.32);
        box-shadow:0 18px 30px rgba(31, 42, 51, 0.1);
    }
    #aeo-audit-wrap .aeo-subtab-icon {
        display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px;
        border-radius:12px; background:rgba(15,118,110,0.08); color:#0f766e; flex-shrink:0;
    }
    #aeo-audit-wrap .aeo-subtab-icon .dashicons { width:18px; height:18px; font-size:18px; }
    #aeo-audit-wrap .aeo-subtab-label { line-height:1.3; }
    #aeo-audit-wrap .aeo-subtab-badge { margin-left:auto; }

    @media (max-width: 1200px) {
        #aeo-audit-wrap .aeo-workflow-rail { display:flex; overflow-x:auto; padding-bottom:8px; }
        #aeo-audit-wrap .aeo-workflow-rail::before { display:none; }
        #aeo-audit-wrap .aeo-workflow-step { min-width:270px; flex:0 0 270px; }
    }
    @media (max-width: 782px) {
        #aeo-audit-wrap .aeo-header { flex-direction:column; padding:18px 20px; }
        #aeo-audit-wrap .aeo-header-actions { width:100%; justify-content:flex-start; margin-left:0; }
        #aeo-audit-wrap .aeo-header-logo-wrap { width:64px; height:64px; }
        #aeo-audit-wrap .aeo-header-logo { width:46px; height:46px; }
        #aeo-audit-wrap .aeo-settings h1,
        #aeo-audit-wrap h1 { font-size:28px; }
    }
</style>
<div class="wrap aeo-settings" id="aeo-audit-wrap" data-requested-tab="<?php echo esc_attr( $aeocas_requested_tab ); ?>" data-connected="<?php echo $aeocas_connected ? '1' : '0'; ?>" data-feature-count="<?php echo esc_attr( count( $aeocas_features ) ); ?>">
    <div class="aeo-header">
        <div class="aeo-header-brand">
            <span class="aeo-header-logo-wrap">
                <img class="aeo-header-logo" src="<?php echo esc_url( $aeocas_logo_url ); ?>" alt="" width="52" height="52" />
            </span>
            <div class="aeo-header-copy">
                <span class="aeo-header-kicker"><?php esc_html_e( 'AEO Content Plugin', 'aeo-content-ai-studio' ); ?></span>
                <h1><?php esc_html_e( 'AEO Content AI Studio', 'aeo-content-ai-studio' ); ?></h1>
                <p class="aeo-subtitle">
                    <?php esc_html_e( 'AI Engine Optimization for WordPress. Powered by', 'aeo-content-ai-studio' ); ?>
                    <a href="<?php echo esc_url( 'https://www.aeocontent.ai' ); ?>" target="_blank" rel="noopener">aeocontent.ai</a>
                </p>
            </div>
        </div>
        <div class="aeo-header-actions">
            <a href="#" id="aeo-refresh-audit" class="button button-secondary">
                <?php esc_html_e( 'Refresh', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" id="aeo-reaudit-btn" class="button button-primary">
                <?php esc_html_e( 'Re-audit', 'aeo-content-ai-studio' ); ?>
            </a>
            <span class="aeo-version">v<?php echo esc_html( AEOCAS_VERSION ); ?></span>
        </div>
    </div>

    <!-- Loading state -->
    <div id="aeo-audit-loading" class="aeo-audit-loading">
        <span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
        <?php esc_html_e( 'Loading audit data...', 'aeo-content-ai-studio' ); ?>
    </div>

    <!-- Error state (rendered by JS when needed) -->
    <div id="aeo-audit-error"></div>

    <!-- Re-audit progress -->
    <div id="aeo-reaudit-progress" style="display: none;">
        <div class="aeo-reaudit-bar">
            <span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
            <span id="aeo-reaudit-stage"><?php esc_html_e( 'Queued...', 'aeo-content-ai-studio' ); ?></span>
        </div>
        <div class="aeo-reaudit-track">
            <div class="aeo-reaudit-fill" id="aeo-reaudit-fill" style="width: 0%;"></div>
        </div>
    </div>

    <!-- Audit content -->
    <div id="aeo-audit-content" style="display: none;">

        <nav class="aeo-workflow-rail" aria-label="<?php esc_attr_e( 'Audit workflow', 'aeo-content-ai-studio' ); ?>">
            <a href="<?php echo esc_url( $aeocas_log_base . '&tab=connect' ); ?>" class="aeo-workflow-step <?php echo 'connect' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="connect" data-default-tab="connect">
                <span class="aeo-workflow-step-top">
                    <span class="aeo-workflow-step-index">1</span>
                    <span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-admin-links"></span></span>
                </span>
                <span class="aeo-workflow-step-body">
                    <span class="aeo-workflow-step-title"><?php esc_html_e( 'Connect', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-workflow-step-label"><?php esc_html_e( 'Link your site', 'aeo-content-ai-studio' ); ?></span>
                </span>
                <span class="aeo-workflow-step-state"></span>
                <span class="aeo-workflow-badge" aria-hidden="true"></span>
            </a>
            <a href="<?php echo esc_url( $aeocas_log_base . '&tab=discovery' ); ?>" class="aeo-workflow-step <?php echo 'discovery' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="discovery" data-default-tab="discovery">
                <span class="aeo-workflow-step-top">
                    <span class="aeo-workflow-step-index">2</span>
                    <span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></span>
                </span>
                <span class="aeo-workflow-step-body">
                    <span class="aeo-workflow-step-title"><?php esc_html_e( 'Discover', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-workflow-step-label"><?php esc_html_e( 'See what was found', 'aeo-content-ai-studio' ); ?></span>
                </span>
                <span class="aeo-workflow-step-state"></span>
                <span class="aeo-workflow-badge" aria-hidden="true"></span>
            </a>
            <a href="<?php echo esc_url( $aeocas_log_base . '&tab=scoreboard' ); ?>" class="aeo-workflow-step <?php echo 'diagnose' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="diagnose" data-default-tab="scoreboard">
                <span class="aeo-workflow-step-top">
                    <span class="aeo-workflow-step-index">3</span>
                    <span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-chart-bar"></span></span>
                </span>
                <span class="aeo-workflow-step-body">
                    <span class="aeo-workflow-step-title"><?php esc_html_e( 'Diagnose', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-workflow-step-label"><?php esc_html_e( 'Find critical issues', 'aeo-content-ai-studio' ); ?></span>
                </span>
                <span class="aeo-workflow-step-state"></span>
                <span class="aeo-workflow-badge" aria-hidden="true"></span>
            </a>
            <a href="<?php echo esc_url( $aeocas_log_base . '&tab=opportunities' ); ?>" class="aeo-workflow-step <?php echo 'fix' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="fix" data-default-tab="opportunities">
                <span class="aeo-workflow-step-top">
                    <span class="aeo-workflow-step-index">4</span>
                    <span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-admin-tools"></span></span>
                </span>
                <span class="aeo-workflow-step-body">
                    <span class="aeo-workflow-step-title"><?php esc_html_e( 'Fix', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-workflow-step-label"><?php esc_html_e( 'Act on best opportunities', 'aeo-content-ai-studio' ); ?></span>
                </span>
                <span class="aeo-workflow-step-state"></span>
                <span class="aeo-workflow-badge" aria-hidden="true"></span>
            </a>
            <a href="<?php echo esc_url( $aeocas_log_base . '&tab=activity' ); ?>" class="aeo-workflow-step <?php echo 'track' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="track" data-default-tab="activity">
                <span class="aeo-workflow-step-top">
                    <span class="aeo-workflow-step-index">5</span>
                    <span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-chart-line"></span></span>
                </span>
                <span class="aeo-workflow-step-body">
                    <span class="aeo-workflow-step-title"><?php esc_html_e( 'Track', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-workflow-step-label"><?php esc_html_e( 'Monitor progress', 'aeo-content-ai-studio' ); ?></span>
                </span>
                <span class="aeo-workflow-step-state"></span>
                <span class="aeo-workflow-badge" aria-hidden="true"></span>
            </a>
        </nav>

        <section class="aeo-stage-shell <?php echo 'connect' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-connect" data-stage="connect" <?php echo 'connect' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
            <div class="aeo-stage-chrome">
                <div class="aeo-stage-hero" id="aeo-stage-hero-connect"></div>
                <div class="aeo-stage-summary" id="aeo-stage-summary-connect"></div>
            </div>
            <div class="aeo-stage-body">
                <div class="aeo-tab-panel" id="tab-connect" data-tab-panel="connect">
                    <?php if ( 'disconnected' === $aeocas_notice ) : ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'This site has been disconnected from AEO Content.', 'aeo-content-ai-studio' ); ?></p></div>
                    <?php endif; ?>

                    <?php settings_errors(); ?>

                    <div class="aeo-status-bar <?php echo $aeocas_connected ? 'aeo-connected' : 'aeo-disconnected'; ?>">
                        <span class="aeo-status-dot"></span>
                        <span>
                            <?php if ( $aeocas_connected ) : ?>
                                <?php esc_html_e( 'Connected to AEO Content Platform', 'aeo-content-ai-studio' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Not connected yet. Click Continue with Google to create your account and connect this site.', 'aeo-content-ai-studio' ); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <h3><?php esc_html_e( 'Connection', 'aeo-content-ai-studio' ); ?></h3>

                    <?php if ( $aeocas_connected ) : ?>
                        <div class="aeo-connect-card">
                            <p class="aeo-connect-lead"><?php esc_html_e( 'Your site is connected. Manage your account in AEO Content or disconnect this site here if you need to reset the connection.', 'aeo-content-ai-studio' ); ?></p>

                            <div class="aeo-connect-meta">
                                <div class="aeo-connect-meta-item">
                                    <span class="aeo-connect-meta-label"><?php esc_html_e( 'Site URL', 'aeo-content-ai-studio' ); ?></span>
                                    <code><?php echo esc_html( $aeocas_site_url ); ?></code>
                                </div>
                                <div class="aeo-connect-meta-item">
                                    <span class="aeo-connect-meta-label"><?php esc_html_e( 'Platform URL', 'aeo-content-ai-studio' ); ?></span>
                                    <code><?php echo esc_html( $aeocas_platform ); ?></code>
                                </div>
                            </div>

                            <div class="aeo-connect-actions">
                                <a class="button button-primary" href="<?php echo esc_url( $aeocas_manage_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Manage Account', 'aeo-content-ai-studio' ); ?></a>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aeo-inline-form">
                                    <input type="hidden" name="action" value="aeocas_disconnect" />
                                    <?php wp_nonce_field( 'aeocas_disconnect' ); ?>
                                    <button type="submit" class="button button-secondary"><?php esc_html_e( 'Disconnect Site', 'aeo-content-ai-studio' ); ?></button>
                                </form>
                            </div>
                        </div>

                        <form method="post" action="options.php">
                            <?php settings_fields( 'aeocas_settings' ); ?>
                            <h3><?php esc_html_e( 'Features', 'aeo-content-ai-studio' ); ?></h3>
                            <p><?php esc_html_e( 'Toggle which optimization features are active on this site.', 'aeo-content-ai-studio' ); ?></p>
                            <table class="form-table aeo-features-table">
                                <?php foreach ( $aeocas_modules as $aeocas_slug ) :
                                    $aeocas_info    = isset( $aeocas_module_labels[ $aeocas_slug ] ) ? $aeocas_module_labels[ $aeocas_slug ] : array( 'label' => $aeocas_slug, 'desc' => '' );
                                    $aeocas_checked = in_array( $aeocas_slug, $aeocas_features, true );
                                ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html( $aeocas_info['label'] ); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="aeocas_enabled_features[]" value="<?php echo esc_attr( $aeocas_slug ); ?>" <?php checked( $aeocas_checked ); ?> />
                                            <?php echo esc_html( $aeocas_info['desc'] ); ?>
                                        </label>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            <?php submit_button( __( 'Save Settings', 'aeo-content-ai-studio' ) ); ?>
                        </form>
                    <?php else : ?>
                        <div class="aeo-connect-card aeo-connect-card-disconnected">
                            <p class="aeo-connect-lead"><?php esc_html_e( 'Connect your site to AEO Content in one click with your Google account.', 'aeo-content-ai-studio' ); ?></p>

                            <div class="aeo-connect-meta">
                                <div class="aeo-connect-meta-item">
                                    <span class="aeo-connect-meta-label"><?php esc_html_e( 'Site URL', 'aeo-content-ai-studio' ); ?></span>
                                    <code><?php echo esc_html( $aeocas_site_url ); ?></code>
                                </div>
                                <div class="aeo-connect-meta-item">
                                    <span class="aeo-connect-meta-label"><?php esc_html_e( 'Platform URL', 'aeo-content-ai-studio' ); ?></span>
                                    <code><?php echo esc_html( $aeocas_platform ); ?></code>
                                </div>
                            </div>

                            <div class="aeo-connect-actions" id="aeo-google-connect-wrap">
                                <button type="button" class="button button-hero aeo-google-btn" id="aeo-google-btn">
                                    <svg class="aeo-google-icon" width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/><path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.26c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/><path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/><path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/></svg>
                                    <?php esc_html_e( 'Continue with Google', 'aeo-content-ai-studio' ); ?>
                                </button>
                                <span id="aeo-google-status" class="aeo-google-status" style="display: none;"></span>
                            </div>

                            <div class="aeo-connect-divider"><span><?php esc_html_e( 'or', 'aeo-content-ai-studio' ); ?></span></div>

                            <div class="aeo-connect-actions aeo-connect-actions-alt">
                                <a class="button" href="<?php echo esc_url( $aeocas_connect_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create Account Manually', 'aeo-content-ai-studio' ); ?></a>
                                <a class="button" href="<?php echo esc_url( $aeocas_signin_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'I Already Have an Account', 'aeo-content-ai-studio' ); ?></a>
                            </div>

                            <p class="description aeo-connect-help"><?php esc_html_e( 'A secure popup will open for Google sign-in. Your account is created automatically.', 'aeo-content-ai-studio' ); ?></p>
                        </div>

                        <details class="aeo-manual-connect">
                            <summary><?php esc_html_e( 'Advanced: connect with an API key instead', 'aeo-content-ai-studio' ); ?></summary>
                            <form method="post" action="options.php">
                                <?php settings_fields( 'aeocas_connection_settings' ); ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="aeocas_site_token"><?php esc_html_e( 'Site API Key', 'aeo-content-ai-studio' ); ?></label></th>
                                        <td>
                                            <input type="password" id="aeocas_site_token" name="aeocas_site_token" value="<?php echo esc_attr( $aeocas_token ); ?>" class="regular-text" autocomplete="off" />
                                            <p class="description"><?php esc_html_e( 'Use manual setup only if the AEO Content team gave you a site API key directly.', 'aeo-content-ai-studio' ); ?></p>
                                        </td>
                                    </tr>
                                </table>
                                <?php submit_button( __( 'Connect with API Key', 'aeo-content-ai-studio' ), 'secondary' ); ?>
                            </form>
                        </details>
                    <?php endif; ?>

                    <!-- Audit Overview (rendered by JS when audit data arrives) -->
                    <div id="aeo-connect-audit-section" style="display:none;"></div>
                </div>
            </div>
        </section>

        <section class="aeo-stage-shell <?php echo 'discovery' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-discovery" data-stage="discovery" <?php echo 'discovery' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
            <div class="aeo-stage-chrome">
                <div class="aeo-stage-hero" id="aeo-stage-hero-discovery"></div>
                <div class="aeo-stage-summary" id="aeo-stage-summary-discovery"></div>
            </div>
            <div class="aeo-stage-body">
                <div class="aeo-tab-panel" id="tab-discovery" data-tab-panel="discovery"></div>
            </div>
        </section>

        <section class="aeo-stage-shell <?php echo 'diagnose' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-diagnose" data-stage="diagnose" <?php echo 'diagnose' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
            <div class="aeo-stage-chrome">
                <div class="aeo-stage-hero" id="aeo-stage-hero-diagnose"></div>
                <div class="aeo-stage-summary" id="aeo-stage-summary-diagnose"></div>
            </div>
            <nav class="aeo-subtabs" data-subtabs-for="diagnose" aria-label="<?php esc_attr_e( 'Diagnose views', 'aeo-content-ai-studio' ); ?>">
                <a href="<?php echo esc_url( $aeocas_log_base . '&tab=scoreboard' ); ?>" class="aeo-subtab <?php echo 'scoreboard' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="scoreboard">
                    <span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-forms"></span></span>
                    <span class="aeo-subtab-label"><?php esc_html_e( 'Site Audit', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-subtab-badge" aria-hidden="true"></span>
                </a>
                <a href="<?php echo esc_url( $aeocas_log_base . '&tab=site-audit' ); ?>" class="aeo-subtab <?php echo 'site-audit' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="site-audit">
                    <span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-admin-page"></span></span>
                    <span class="aeo-subtab-label"><?php esc_html_e( 'Pages Audit', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-subtab-badge" aria-hidden="true"></span>
                </a>
            </nav>
            <div class="aeo-stage-body aeo-stage-body-grouped">
                <div class="aeo-tab-panel" id="tab-scoreboard" data-tab-panel="scoreboard" <?php echo 'scoreboard' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
                <div class="aeo-tab-panel" id="tab-site-audit" data-tab-panel="site-audit" <?php echo 'site-audit' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
            </div>
        </section>

        <section class="aeo-stage-shell <?php echo 'fix' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-fix" data-stage="fix" <?php echo 'fix' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
            <div class="aeo-stage-chrome">
                <div class="aeo-stage-hero" id="aeo-stage-hero-fix"></div>
                <div class="aeo-stage-summary" id="aeo-stage-summary-fix"></div>
            </div>
            <nav class="aeo-subtabs" data-subtabs-for="fix" aria-label="<?php esc_attr_e( 'Fix views', 'aeo-content-ai-studio' ); ?>">
                <a href="<?php echo esc_url( $aeocas_log_base . '&tab=opportunities' ); ?>" class="aeo-subtab <?php echo 'opportunities' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="opportunities">
                    <span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-star-filled"></span></span>
                    <span class="aeo-subtab-label"><?php esc_html_e( 'Opportunities', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-subtab-badge" aria-hidden="true"></span>
                </a>
                <a href="<?php echo esc_url( $aeocas_log_base . '&tab=rewrite' ); ?>" class="aeo-subtab <?php echo 'rewrite' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="rewrite">
                    <span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-edit"></span></span>
                    <span class="aeo-subtab-label"><?php esc_html_e( 'Rewrite Candidates', 'aeo-content-ai-studio' ); ?></span>
                    <span class="aeo-subtab-badge" aria-hidden="true"></span>
                </a>
            </nav>
            <div class="aeo-stage-body aeo-stage-body-grouped">
                <div class="aeo-tab-panel" id="tab-opportunities" data-tab-panel="opportunities" <?php echo 'opportunities' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
                <div class="aeo-tab-panel" id="tab-rewrite" data-tab-panel="rewrite" <?php echo 'rewrite' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
            </div>
        </section>

        <section class="aeo-stage-shell <?php echo 'track' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-track" data-stage="track" data-activity-total="<?php echo esc_attr( $aeocas_stats['total'] ); ?>" data-activity-success-rate="<?php echo esc_attr( $aeocas_stats['success_rate'] ); ?>" data-activity-last24h="<?php echo esc_attr( $aeocas_stats['last_24h'] ); ?>" data-activity-last-action-label="<?php echo esc_attr( $aeocas_log_last_action_label ); ?>" data-activity-error-count="<?php echo esc_attr( $aeocas_activity_error_count ); ?>" <?php echo 'track' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
            <div class="aeo-stage-chrome">
                <div class="aeo-stage-hero" id="aeo-stage-hero-track"></div>
                <div class="aeo-stage-summary" id="aeo-stage-summary-track"></div>
            </div>
            <div class="aeo-stage-body">
                <div class="aeo-tab-panel" id="tab-activity" data-tab-panel="activity">
            <div class="aeo-log-stats" style="margin-top:20px;">
                <div class="aeo-stat-card">
                    <span class="aeo-stat-number"><?php echo esc_html( $aeocas_stats['total'] ); ?></span>
                    <span class="aeo-stat-label"><?php esc_html_e( 'Total Actions', 'aeo-content-ai-studio' ); ?></span>
                </div>
                <div class="aeo-stat-card">
                    <span class="aeo-stat-number aeo-stat-success"><?php echo esc_html( $aeocas_stats['success_rate'] ); ?>%</span>
                    <span class="aeo-stat-label"><?php esc_html_e( 'Success Rate', 'aeo-content-ai-studio' ); ?></span>
                </div>
                <div class="aeo-stat-card">
                    <span class="aeo-stat-number"><?php echo esc_html( $aeocas_stats['last_24h'] ); ?></span>
                    <span class="aeo-stat-label"><?php esc_html_e( 'Last 24 Hours', 'aeo-content-ai-studio' ); ?></span>
                </div>
                <div class="aeo-stat-card">
                    <span class="aeo-stat-number aeo-stat-time">
                        <?php
                        if ( $aeocas_stats['last_action'] ) {
                            /* translators: %s: human-readable time diff */
                            echo esc_html( sprintf( __( '%s ago', 'aeo-content-ai-studio' ), human_time_diff( strtotime( $aeocas_stats['last_action'] ), time() ) ) );
                        } else {
                            esc_html_e( 'Never', 'aeo-content-ai-studio' );
                        }
                        ?>
                    </span>
                    <span class="aeo-stat-label"><?php esc_html_e( 'Last Action', 'aeo-content-ai-studio' ); ?></span>
                </div>
            </div>

            <form method="get" class="aeo-log-filters">
                <input type="hidden" name="page" value="aeocas-audit-report" />
                <input type="hidden" name="tab" value="activity" />
                <select name="command">
                    <option value=""><?php esc_html_e( 'All Commands', 'aeo-content-ai-studio' ); ?></option>
                    <?php foreach ( $aeocas_commands as $aeocas_cmd ) : ?>
                        <option value="<?php echo esc_attr( $aeocas_cmd ); ?>" <?php selected( isset( $aeocas_log_filters['command'] ) ? $aeocas_log_filters['command'] : '', $aeocas_cmd ); ?>><?php echo esc_html( $aeocas_cmd ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'aeo-content-ai-studio' ); ?></option>
                    <option value="success" <?php selected( isset( $aeocas_log_filters['status'] ) ? $aeocas_log_filters['status'] : '', 'success' ); ?>><?php esc_html_e( 'Success', 'aeo-content-ai-studio' ); ?></option>
                    <option value="error" <?php selected( isset( $aeocas_log_filters['status'] ) ? $aeocas_log_filters['status'] : '', 'error' ); ?>><?php esc_html_e( 'Error', 'aeo-content-ai-studio' ); ?></option>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr( isset( $aeocas_log_filters['date_from'] ) ? $aeocas_log_filters['date_from'] : '' ); ?>" />
                <input type="date" name="date_to" value="<?php echo esc_attr( isset( $aeocas_log_filters['date_to'] ) ? $aeocas_log_filters['date_to'] : '' ); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'aeo-content-ai-studio' ); ?>" />
                <?php if ( ! empty( $aeocas_log_filters ) ) : ?>
                    <a href="<?php echo esc_url( $aeocas_log_base . '&tab=activity' ); ?>" class="button"><?php esc_html_e( 'Clear', 'aeo-content-ai-studio' ); ?></a>
                <?php endif; ?>
            </form>

            <?php if ( empty( $aeocas_logs['items'] ) ) : ?>
                <div class="aeo-log-empty"><p><?php esc_html_e( 'No activity recorded yet. Commands sent from the AEO Content platform will appear here.', 'aeo-content-ai-studio' ); ?></p></div>
            <?php else : ?>
                <table class="widefat fixed striped aeo-log-table">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e( 'Timestamp', 'aeo-content-ai-studio' ); ?></th>
                            <th style="width:180px;"><?php esc_html_e( 'Command', 'aeo-content-ai-studio' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Status', 'aeo-content-ai-studio' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'aeo-content-ai-studio' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Post', 'aeo-content-ai-studio' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $aeocas_logs['items'] as $aeocas_entry ) : ?>
                            <tr>
                                <td><span title="<?php echo esc_attr( $aeocas_entry['created_at'] ); ?>"><?php echo esc_html( date_i18n( 'M j, Y g:i:s a', strtotime( $aeocas_entry['created_at'] ) ) ); ?></span></td>
                                <td><span class="aeo-log-command"><?php echo esc_html( $aeocas_entry['command'] ); ?></span></td>
                                <td><span class="aeo-badge aeo-badge-<?php echo esc_attr( $aeocas_entry['status'] ); ?>"><?php echo esc_html( $aeocas_entry['status'] ); ?></span></td>
                                <td>
                                    <?php
                                    $aeocas_details = $aeocas_entry['details'];
                                    if ( is_array( $aeocas_details ) && isset( $aeocas_details['message'] ) ) {
                                        echo esc_html( $aeocas_details['message'] );
                                    } elseif ( is_array( $aeocas_details ) ) {
                                        echo '<code class="aeo-log-details">' . esc_html( wp_json_encode( $aeocas_details, JSON_UNESCAPED_SLASHES ) ) . '</code>';
                                    } elseif ( $aeocas_details ) {
                                        echo esc_html( $aeocas_details );
                                    } else {
                                        echo '<span class="aeo-log-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $aeocas_entry['post_id'] ) ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $aeocas_entry['post_id'] ) ); ?>" target="_blank">#<?php echo esc_html( $aeocas_entry['post_id'] ); ?></a>
                                    <?php else : ?>
                                        <span class="aeo-log-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( $aeocas_logs['pages'] > 1 ) : ?>
                    <div class="aeo-log-pagination">
                        <span class="aeo-log-muted">
                            <?php
                            /* translators: %1$d: current page, %2$d: total pages, %3$d: total entries */
                            echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d entries)', 'aeo-content-ai-studio' ), $aeocas_log_page, $aeocas_logs['pages'], $aeocas_logs['total'] ) );
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
                </div>
            </div>
        </section>

    </div>

    <!-- AEO score breakdown modal -->
    <div id="aeo-score-modal" class="aeo-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="aeo-score-modal-title">
        <div class="aeo-modal-backdrop"></div>
        <div class="aeo-modal-dialog">
            <header class="aeo-modal-header">
                <h2 id="aeo-score-modal-title"><?php esc_html_e( 'AEO Page Rank — Score Breakdown', 'aeo-content-ai-studio' ); ?></h2>
                <button type="button" class="aeo-modal-close" aria-label="Close">&times;</button>
            </header>
            <div class="aeo-modal-body" id="aeo-score-modal-body"></div>
        </div>
    </div>
</div>
