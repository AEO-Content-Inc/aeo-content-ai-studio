<?php
/**
 * Activity Log admin page.
 *
 * Displays a filterable, paginated table of all plugin commands with stats bar.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters, no state changes.
$current_page = max( 1, isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1 );
$per_page     = 25;

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

$base_url = admin_url( 'options-general.php?page=aeo-activity-log' );
?>
<div class="wrap aeo-settings">
    <h1><?php esc_html_e( 'AEO Activity Log', 'aeo-content-ai-studio' ); ?></h1>

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
        <a href="<?php echo esc_url( add_query_arg( $export_args, admin_url( 'admin.php' ) ) ); ?>" class="button" style="margin-left: auto;"><?php esc_html_e( 'Export CSV', 'aeo-content-ai-studio' ); ?></a>
    </form>

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
                                <?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $entry['created_at'] ) ) ); ?>
                            </span>
                        </td>
                        <td>
                            <code class="aeo-log-command"><?php echo esc_html( $entry['command'] ); ?></code>
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
                                // Show message if present, otherwise compact JSON.
                                if ( isset( $details['message'] ) ) {
                                    echo esc_html( $details['message'] );
                                } else {
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
        <?php if ( $logs['pages'] > 1 ) : ?>
            <div class="aeo-log-pagination">
                <?php
                $pagination_args = array_merge( $filters, array( 'page' => 'aeo-activity-log' ) );
                for ( $i = 1; $i <= $logs['pages']; $i++ ) :
                    $pagination_args['paged'] = $i;
                    $class = ( $i === $current_page ) ? 'button button-primary' : 'button';
                ?>
                    <a href="<?php echo esc_url( add_query_arg( $pagination_args, admin_url( 'options-general.php' ) ) ); ?>" class="<?php echo esc_attr( $class ); ?>">
                        <?php echo esc_html( $i ); ?>
                    </a>
                <?php endfor; ?>
                <span class="aeo-log-muted" style="margin-left: 8px;">
                    Page <?php echo esc_html( $current_page ); ?> of <?php echo esc_html( $logs['pages'] ); ?> (<?php echo esc_html( $logs['total'] ); ?> entries)
                </span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
