<?php
/**
 * Activity Log admin page.
 *
 * Displays a filterable, paginated table of all plugin commands with stats bar.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle clear logs action.
if ( isset( $_POST['aeo_clear_logs'] ) && check_admin_referer( 'aeo_clear_logs' ) && current_user_can( 'manage_options' ) ) {
    AEO_Activity_Log::clear_all();
    wp_safe_redirect( admin_url( 'admin.php?page=aeo-activity-log&cleared=1' ) );
    exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters, no state changes.
$current_page = max( 1, isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1 );
$per_page     = 10;

$filters = array();
if ( ! empty( $_GET['command'] ) ) {
    $filters['command'] = sanitize_text_field( wp_unslash( $_GET['command'] ) );
}
if ( ! empty( $_GET['status'] ) ) {
    $filters['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
}
if ( ! empty( $_GET['date_from'] ) ) {
    $filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
}
if ( ! empty( $_GET['date_to'] ) ) {
    $filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
}
// phpcs:enable

$logs     = AEO_Activity_Log::get_logs( $current_page, $per_page, $filters );
$stats    = AEO_Activity_Log::get_stats();
$commands = AEO_Activity_Log::get_commands();

$base_url = admin_url( 'admin.php?page=aeo-activity-log' );
?>
<div class="wrap aeo-settings">
    <h1><?php esc_html_e( 'AEO Activity Log', 'aeo-content-ai-studio' ); ?></h1>

    <?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All logs have been cleared.', 'aeo-content-ai-studio' ); ?></p></div>
    <?php endif; ?>

    <!-- Stats Bar -->
    <div class="aeo-log-stats">
        <div class="aeo-stat-card">
            <span class="aeo-stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
            <span class="aeo-stat-label"><?php esc_html_e( 'Total Actions', 'aeo-content-ai-studio' ); ?></span>
        </div>
        <div class="aeo-stat-card">
            <span class="aeo-stat-number aeo-stat-success"><?php echo esc_html( $stats['success_rate'] ); ?>%</span>
            <span class="aeo-stat-label"><?php esc_html_e( 'Success Rate', 'aeo-content-ai-studio' ); ?></span>
        </div>
        <div class="aeo-stat-card">
            <span class="aeo-stat-number"><?php echo esc_html( $stats['last_24h'] ); ?></span>
            <span class="aeo-stat-label"><?php esc_html_e( 'Last 24 Hours', 'aeo-content-ai-studio' ); ?></span>
        </div>
        <div class="aeo-stat-card">
            <span class="aeo-stat-number aeo-stat-time">
                <?php
                if ( $stats['last_action'] ) {
                    /* translators: %s: human-readable time difference */
                    echo esc_html( sprintf( __( '%s ago', 'aeo-content-ai-studio' ), human_time_diff( strtotime( $stats['last_action'] ), time() ) ) );
                } else {
                    esc_html_e( 'Never', 'aeo-content-ai-studio' );
                }
                ?>
            </span>
            <span class="aeo-stat-label"><?php esc_html_e( 'Last Action', 'aeo-content-ai-studio' ); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" class="aeo-log-filters">
        <input type="hidden" name="page" value="aeo-activity-log" />

        <select name="command">
            <option value=""><?php esc_html_e( 'All Commands', 'aeo-content-ai-studio' ); ?></option>
            <?php foreach ( $commands as $cmd ) : ?>
                <option value="<?php echo esc_attr( $cmd ); ?>" <?php selected( isset( $filters['command'] ) ? $filters['command'] : '', $cmd ); ?>>
                    <?php echo esc_html( $cmd ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value=""><?php esc_html_e( 'All Statuses', 'aeo-content-ai-studio' ); ?></option>
            <option value="success" <?php selected( isset( $filters['status'] ) ? $filters['status'] : '', 'success' ); ?>><?php esc_html_e( 'Success', 'aeo-content-ai-studio' ); ?></option>
            <option value="error" <?php selected( isset( $filters['status'] ) ? $filters['status'] : '', 'error' ); ?>><?php esc_html_e( 'Error', 'aeo-content-ai-studio' ); ?></option>
        </select>

        <input type="date" name="date_from" value="<?php echo esc_attr( isset( $filters['date_from'] ) ? $filters['date_from'] : '' ); ?>" placeholder="From" />
        <input type="date" name="date_to" value="<?php echo esc_attr( isset( $filters['date_to'] ) ? $filters['date_to'] : '' ); ?>" placeholder="To" />

        <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'aeo-content-ai-studio' ); ?>" />

        <?php if ( ! empty( $filters ) ) : ?>
            <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'aeo-content-ai-studio' ); ?></a>
        <?php endif; ?>

        <?php
        $export_args = array_merge( $filters, array(
            'aeo_export_logs' => '1',
            '_wpnonce'        => wp_create_nonce( 'aeo_export_logs' ),
        ) );
        ?>
        <div style="margin-left: auto; display: flex; gap: 8px; align-items: center;">
            <a href="<?php echo esc_url( add_query_arg( $export_args, admin_url( 'admin.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'aeo-content-ai-studio' ); ?></a>
            <?php // Close GET form, open POST form inline for Clear button. ?>
            </form><form method="post" style="display:inline; margin:0;">
                <?php wp_nonce_field( 'aeo_clear_logs' ); ?>
                <button type="submit" name="aeo_clear_logs" value="1" class="button" style="color:#a00;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete all logs?', 'aeo-content-ai-studio' ); ?>');">
                    <?php esc_html_e( 'Clear All Logs', 'aeo-content-ai-studio' ); ?>
                </button>
            </form>
        </div>

    <!-- Log Table -->
    <?php if ( empty( $logs['items'] ) ) : ?>
        <div class="aeo-log-empty">
            <p><?php esc_html_e( 'No activity recorded yet. Commands sent from the AEO Content platform will appear here.', 'aeo-content-ai-studio' ); ?></p>
        </div>
    <?php else : ?>
        <table class="widefat fixed striped aeo-log-table">
            <thead>
                <tr>
                    <th style="width: 160px;"><?php esc_html_e( 'Timestamp', 'aeo-content-ai-studio' ); ?></th>
                    <th style="width: 180px;"><?php esc_html_e( 'Command', 'aeo-content-ai-studio' ); ?></th>
                    <th style="width: 80px;"><?php esc_html_e( 'Status', 'aeo-content-ai-studio' ); ?></th>
                    <th><?php esc_html_e( 'Details', 'aeo-content-ai-studio' ); ?></th>
                    <th style="width: 80px;"><?php esc_html_e( 'Post', 'aeo-content-ai-studio' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs['items'] as $entry ) : ?>
                    <tr>
                        <td>
                            <span title="<?php echo esc_attr( $entry['created_at'] ); ?>">
                                <?php echo esc_html( date_i18n( 'M j, Y g:i:s a', strtotime( $entry['created_at'] ) ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $cmd_type_map = array(
                                'publish_post' => 'write',
                                'get_posts'    => 'read',
                                'get_post'     => 'read',
                                'heartbeat'    => 'system',
                                'reaudit'      => 'audit',
                            );
                            $cmd_type = isset( $cmd_type_map[ $entry['command'] ] ) ? $cmd_type_map[ $entry['command'] ] : 'read';
                            ?>
                            <span class="aeo-log-command aeo-cmd-<?php echo esc_attr( $cmd_type ); ?>">
                                <?php echo esc_html( $entry['command'] ); ?>
                            </span>
                        </td>
                        <td>
                            <span class="aeo-badge aeo-badge-<?php echo esc_attr( $entry['status'] ); ?>">
                                <?php echo esc_html( $entry['status'] ); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $details = $entry['details'];
                            if ( is_array( $details ) ) {
                                if ( isset( $details['message'] ) ) {
                                    echo esc_html( $details['message'] );
                                }
                                // Show extra data (request_body, response_body, etc.) in expandable block.
                                $extra = array_diff_key( $details, array( 'message' => 1 ) );
                                if ( ! empty( $extra ) ) {
                                    echo '<details style="margin-top:4px;"><summary style="cursor:pointer;font-size:12px;color:#666;">' . esc_html__( 'Show payload', 'aeo-content-ai-studio' ) . '</summary>';
                                    echo '<pre style="font-size:11px;max-height:300px;overflow:auto;background:#f5f5f5;padding:8px;margin-top:4px;">';
                                    echo esc_html( wp_json_encode( $extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
                                    echo '</pre></details>';
                                } elseif ( ! isset( $details['message'] ) ) {
                                    echo '<code class="aeo-log-details">' . esc_html( wp_json_encode( $details, JSON_UNESCAPED_SLASHES ) ) . '</code>';
                                }
                            } elseif ( $details ) {
                                echo esc_html( $details );
                            } else {
                                echo '<span class="aeo-log-muted">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $entry['post_id'] ) ) : ?>
                                <a href="<?php echo esc_url( get_edit_post_link( $entry['post_id'] ) ); ?>" target="_blank">
                                    #<?php echo esc_html( $entry['post_id'] ); ?>
                                </a>
                            <?php else : ?>
                                <span class="aeo-log-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $logs['pages'] > 1 ) :
            $total_pages     = $logs['pages'];
            $pagination_args = array_merge( $filters, array( 'page' => 'aeo-activity-log' ) );
            $neighbours      = 2;

            // Build list of page numbers to show.
            $pages_to_show = array( 1, $total_pages );
            for ( $i = max( 2, $current_page - $neighbours ); $i <= min( $total_pages - 1, $current_page + $neighbours ); $i++ ) {
                $pages_to_show[] = $i;
            }
            $pages_to_show = array_unique( $pages_to_show );
            sort( $pages_to_show );
        ?>
            <div class="aeo-log-pagination">
                <?php // Previous button.
                if ( $current_page > 1 ) :
                    $pagination_args['paged'] = $current_page - 1;
                ?>
                    <a href="<?php echo esc_url( add_query_arg( $pagination_args, admin_url( 'admin.php' ) ) ); ?>" class="button">&laquo; <?php esc_html_e( 'Previous', 'aeo-content-ai-studio' ); ?></a>
                <?php else : ?>
                    <span class="button disabled">&laquo; <?php esc_html_e( 'Previous', 'aeo-content-ai-studio' ); ?></span>
                <?php endif; ?>

                <?php
                $prev = 0;
                foreach ( $pages_to_show as $p ) :
                    // Show ellipsis between gaps.
                    if ( $p - $prev > 1 ) :
                ?>
                        <span class="aeo-log-muted" style="padding: 0 4px;">&hellip;</span>
                    <?php endif;
                    $pagination_args['paged'] = $p;
                    $class = ( $p === $current_page ) ? 'button button-primary' : 'button';
                ?>
                    <a href="<?php echo esc_url( add_query_arg( $pagination_args, admin_url( 'admin.php' ) ) ); ?>" class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $p ); ?>
                    </a>
                <?php
                    $prev = $p;
                endforeach;
                ?>

                <?php // Next button.
                if ( $current_page < $total_pages ) :
                    $pagination_args['paged'] = $current_page + 1;
                ?>
                    <a href="<?php echo esc_url( add_query_arg( $pagination_args, admin_url( 'admin.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Next', 'aeo-content-ai-studio' ); ?> &raquo;</a>
                <?php else : ?>
                    <span class="button disabled"><?php esc_html_e( 'Next', 'aeo-content-ai-studio' ); ?> &raquo;</span>
                <?php endif; ?>

                <span class="aeo-log-muted" style="margin-left: 8px;">
                    <?php
                    /* translators: %1$d: current page number, %2$d: total pages, %3$d: total entries */
                    echo esc_html( sprintf( __( 'Page %1$d of %2$d (%3$d entries)', 'aeo-content-ai-studio' ), $current_page, $total_pages, $logs['total'] ) );
                    ?>
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
