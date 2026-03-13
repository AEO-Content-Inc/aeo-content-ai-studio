<?php
/**
 * Audit Report admin page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap aeo-settings" id="aeo-audit-wrap">
    <h1><?php esc_html_e( 'Audit Report', 'aeo-content-ai-studio' ); ?></h1>
    <p class="aeo-subtitle">
        <?php esc_html_e( 'AEO audit results for your site. Powered by', 'aeo-content-ai-studio' ); ?>
        <a href="<?php echo esc_url( 'https://www.aeocontent.ai' ); ?>" target="_blank" rel="noopener">aeocontent.ai</a>
        <a href="#" id="aeo-refresh-audit" class="button button-small" style="margin-left: 12px;">
            <?php esc_html_e( 'Refresh', 'aeo-content-ai-studio' ); ?>
        </a>
        <a href="#" id="aeo-reaudit-btn" class="button button-primary button-small" style="margin-left: 4px;">
            <?php esc_html_e( 'Re-audit', 'aeo-content-ai-studio' ); ?>
        </a>
    </p>

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

        <nav class="nav-tab-wrapper aeo-audit-tabs">
            <a href="#" class="nav-tab nav-tab-active" data-tab="overview">
                <?php esc_html_e( 'Overview', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="scoreboard">
                <?php esc_html_e( 'Scoreboard', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="opportunities">
                <?php esc_html_e( 'Opportunities', 'aeo-content-ai-studio' ); ?>
            </a>
        </nav>

        <div class="aeo-tab-panel" id="tab-overview"></div>
        <div class="aeo-tab-panel" id="tab-scoreboard" style="display: none;"></div>
        <div class="aeo-tab-panel" id="tab-opportunities" style="display: none;"></div>

    </div>
</div>
