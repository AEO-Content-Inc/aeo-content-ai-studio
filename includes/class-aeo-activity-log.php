<?php
/**
 * Activity Log for AEO Content AI Studio.
 *
 * Tracks every command sent to the plugin with timestamp, status, and details.
 * Provides admin UI data, REST endpoint for remote querying, and auto-cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEO_Activity_Log {

    /** @var string Table name (without prefix). */
    const TABLE = 'aeo_activity_log';

    /**
     * Create the activity log table. Called on plugin activation.
     */
    public static function create_table() {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            command VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            details LONGTEXT,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_command (command),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'aeo_activity_log_db_version', '1.0' );
    }

    /**
     * Log a command execution.
     *
     * @param string      $command  Command name (e.g. 'set_llms_txt').
     * @param string      $status   'success' or 'error'.
     * @param mixed       $details  Arbitrary data (will be JSON-encoded).
     * @param int|null    $post_id  Related post ID, if any.
     */
    public static function log( $command, $status = 'success', $details = null, $post_id = null ) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        // Get IP from request.
        $ip = null;
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP API available.
        $wpdb->insert(
            $table,
            array(
                'command'    => sanitize_text_field( $command ),
                'status'     => in_array( $status, array( 'success', 'error' ), true ) ? $status : 'success',
                'details'    => is_null( $details ) ? null : wp_json_encode( $details ),
                'post_id'    => $post_id ? absint( $post_id ) : null,
                'ip'         => $ip,
            ),
            array( '%s', '%s', '%s', '%d', '%s' )
        );
    }

    /**
     * Get paginated log entries.
     *
     * @param int    $page     Page number (1-indexed).
     * @param int    $per_page Items per page.
     * @param array  $filters  Optional: command, status, date_from, date_to.
     * @return array { items: array, total: int, pages: int }
     */
    public static function get_logs( $page = 1, $per_page = 25, $filters = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE;

        // Hardcoded whitelist - SQL clauses are string literals only, no user input.
        $allowed_clauses = array(
            'command'   => array( 'sql' => 'command = %s',     'suffix' => '' ),
            'status'    => array( 'sql' => 'status = %s',      'suffix' => '' ),
            'date_from' => array( 'sql' => 'created_at >= %s', 'suffix' => ' 00:00:00' ),
            'date_to'   => array( 'sql' => 'created_at <= %s', 'suffix' => ' 23:59:59' ),
        );

        $where  = array( '1=1' );
        $values = array();

        foreach ( $allowed_clauses as $key => $clause ) {
            if ( ! empty( $filters[ $key ] ) ) {
                $where[]  = $clause['sql'];
                $values[] = sanitize_text_field( $filters[ $key ] ) . $clause['suffix'];
            }
        }

        $where_sql = implode( ' AND ', $where );

        // Count.
        $count_sql    = 'SELECT COUNT(*) FROM %i WHERE ' . $where_sql;
        $count_values = array_merge( array( $table ), $values );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $where_sql is concatenation of hardcoded literals from $allowed_clauses only; values passed via prepare().
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_values ) );

        // Fetch.
        $offset     = max( 0, ( $page - 1 ) * $per_page );
        $query_sql  = 'SELECT * FROM %i WHERE ' . $where_sql . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $all_values = array_merge( array( $table ), $values, array( $per_page, $offset ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $where_sql is concatenation of hardcoded literals from $allowed_clauses only; values passed via prepare().
        $items = $wpdb->get_results( $wpdb->prepare( $query_sql, $all_values ), ARRAY_A );

        // Decode details JSON.
        foreach ( $items as &$item ) {
            if ( ! empty( $item['details'] ) ) {
                $item['details'] = json_decode( $item['details'], true );
            }
        }

        return array(
            'items' => $items,
            'total' => $total,
            'pages' => ceil( $total / $per_page ),
        );
    }

    /**
     * Get summary statistics.
     *
     * @return array { total, success, error, success_rate, last_action, last_24h }
     */
    public static function get_stats() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $total   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $success = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'success' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $error   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, 'error' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $last_action = $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM %i ORDER BY created_at DESC LIMIT 1', $table ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $last_24h = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s', $table, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );

        return array(
            'total'        => $total,
            'success'      => $success,
            'error'        => $error,
            'success_rate' => $total > 0 ? round( ( $success / $total ) * 100, 1 ) : 0,
            'last_action'  => $last_action,
            'last_24h'     => $last_24h,
        );
    }

    /**
     * Get distinct command names for filter dropdown.
     *
     * @return string[]
     */
    public static function get_commands() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT command FROM %i ORDER BY command ASC', $table ) );
    }

    /**
     * Get all logs for CSV export.
     *
     * @param array $filters Same as get_logs filters.
     * @return array
     */
    public static function get_all_for_export( $filters = array() ) {
        $result = self::get_logs( 1, 999999, $filters );
        return $result['items'];
    }

    /**
     * Delete logs older than 90 days. Called via WP-Cron.
     */
    public static function cleanup() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) ) ) );
    }

    /**
     * Register the cleanup cron event.
     */
    public static function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'aeo_activity_log_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'aeo_activity_log_cleanup' );
        }
    }

    /**
     * Handle REST endpoint for remote log query.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_rest_logs( $request ) {
        $page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 25 ) );

        $filters = array();
        if ( $request->get_param( 'command' ) ) {
            $filters['command'] = $request->get_param( 'command' );
        }
        if ( $request->get_param( 'status' ) ) {
            $filters['status'] = $request->get_param( 'status' );
        }
        if ( $request->get_param( 'date_from' ) ) {
            $filters['date_from'] = $request->get_param( 'date_from' );
        }
        if ( $request->get_param( 'date_to' ) ) {
            $filters['date_to'] = $request->get_param( 'date_to' );
        }

        $logs  = self::get_logs( $page, $per_page, $filters );
        $stats = self::get_stats();

        return rest_ensure_response( array(
            'ok'    => true,
            'logs'  => $logs['items'],
            'total' => $logs['total'],
            'pages' => $logs['pages'],
            'stats' => $stats,
        ) );
    }

    /**
     * Handle CSV export from admin.
     */
    public static function handle_csv_export() {
        // Bail early if this is not a CSV export request.
        if ( empty( $_GET['aeo_export_logs'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'aeo-content-ai-studio' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'aeo_export_logs' ) ) {
            return;
        }

        $filters = array();
        if ( ! empty( $_GET['command'] ) ) {
            $filters['command'] = sanitize_text_field( wp_unslash( $_GET['command'] ) );
        }
        if ( ! empty( $_GET['status'] ) ) {
            $filters['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
        }

        $items = self::get_all_for_export( $filters );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=aeo-activity-log-' . gmdate( 'Y-m-d' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Timestamp', 'Command', 'Status', 'Post ID', 'Details', 'IP' ) );

        foreach ( $items as $item ) {
            fputcsv( $output, array(
                $item['id'],
                $item['created_at'],
                $item['command'],
                $item['status'],
                $item['post_id'] ?: '',
                is_array( $item['details'] ) ? wp_json_encode( $item['details'] ) : ( $item['details'] ?: '' ),
                $item['ip'] ?: '',
            ) );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream, not a filesystem file.
        fclose( $output );
        exit;
    }
}
