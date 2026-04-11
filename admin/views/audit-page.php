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
?>
<div class="wrap aeo-settings" id="aeo-audit-wrap" data-requested-tab="<?php echo esc_attr( $aeocas_requested_tab ); ?>">
    <h1><?php esc_html_e( 'AEO Content AI Studio', 'aeo-content-ai-studio' ); ?></h1>
    <p class="aeo-subtitle">
        <?php esc_html_e( 'AI Engine Optimization for WordPress. Powered by', 'aeo-content-ai-studio' ); ?>
        <a href="<?php echo esc_url( 'https://www.aeocontent.ai' ); ?>" target="_blank" rel="noopener">aeocontent.ai</a>
        <a href="#" id="aeo-refresh-audit" class="button button-small" style="margin-left: 12px;">
            <?php esc_html_e( 'Refresh', 'aeo-content-ai-studio' ); ?>
        </a>
        <a href="#" id="aeo-reaudit-btn" class="button button-primary button-small" style="margin-left: 4px;">
            <?php esc_html_e( 'Re-audit', 'aeo-content-ai-studio' ); ?>
        </a>
        <span class="aeo-version" style="margin-left:auto;color:#646970;font-size:11px;">v<?php echo esc_html( AEOCAS_VERSION ); ?></span>
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
            <a href="#" class="nav-tab nav-tab-active" data-tab="discovery">
                <?php esc_html_e( 'Discovery', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="site-audit">
                <?php esc_html_e( 'Site Audit', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="connect">
                <?php esc_html_e( 'Connect', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="scoreboard">
                <?php esc_html_e( 'Scoreboard', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="opportunities">
                <?php esc_html_e( 'Opportunities', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="rewrite">
                <?php esc_html_e( 'Rewrite Candidates', 'aeo-content-ai-studio' ); ?>
            </a>
            <a href="#" class="nav-tab" data-tab="activity">
                <?php esc_html_e( 'Activity Log', 'aeo-content-ai-studio' ); ?>
            </a>
        </nav>

        <div class="aeo-tab-panel" id="tab-discovery"></div>
        <div class="aeo-tab-panel" id="tab-site-audit" style="display: none;"></div>

        <div class="aeo-tab-panel" id="tab-connect" style="display: none;">
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

        <div class="aeo-tab-panel" id="tab-scoreboard" style="display: none;"></div>
        <div class="aeo-tab-panel" id="tab-opportunities" style="display: none;"></div>
        <div class="aeo-tab-panel" id="tab-rewrite" style="display: none;"></div>

        <div class="aeo-tab-panel" id="tab-activity" style="display: none;">
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
