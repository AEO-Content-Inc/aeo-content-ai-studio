<?php
/**
 * Audit API client.
 *
 * Fetches audit data from the AEO Content platform using the stored site credential.
 * Results are cached in a transient to avoid excessive API calls.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AEOCAS_Audit_Api {

    /** @var int Cache lifetime in seconds (1 hour). */
    const CACHE_TTL = HOUR_IN_SECONDS;

    /** @var int Short cache lifetime while a remote job is still running (60s). */
    const SHORT_CACHE_TTL = MINUTE_IN_SECONDS;

    /** @var int Cache lifetime for visibility snapshots (10 minutes). */
    const VISIBILITY_CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    /** @var string Transient key prefix for cached audit payloads. */
    const TRANSIENT_PREFIX = 'aeocas_audit_';

    /** @var string Transient key prefix for cached discovery payloads. */
    const DISCOVERY_TRANSIENT_PREFIX = 'aeocas_discovery_';

    /** @var string Transient key prefix for cached visibility payloads. */
    const VISIBILITY_TRANSIENT_PREFIX = 'aeocas_visibility_';

    /**
     * Build a lightweight local content index for admin-side page enrichment.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function get_local_content_index() {
        $post_ids = get_posts( array(
            'post_type'              => array( 'post', 'page' ),
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ) );

        $items = array();
        foreach ( $post_ids as $post_id ) {
            $url = get_permalink( $post_id );
            if ( empty( $url ) ) {
                continue;
            }

            $faq       = get_post_meta( $post_id, '_aeocas_faq_schema', true );
            $canonical = get_post_meta( $post_id, '_aeocas_canonical_url', true );
            $faq_count = is_array( $faq ) ? count( $faq ) : 0;
            $edit_url  = get_edit_post_link( $post_id, 'raw' );

            $items[] = array(
                'id'            => (int) $post_id,
                'title'         => get_the_title( $post_id ),
                'url'           => esc_url_raw( $url ),
                'canonical_url' => $canonical ? esc_url_raw( $canonical ) : '',
                'edit_url'      => $edit_url ? esc_url_raw( $edit_url ) : '',
                'post_type'     => get_post_type( $post_id ),
                'status'        => get_post_status( $post_id ),
                'modified_gmt'  => get_post_modified_time( 'c', true, $post_id ),
                'faq_count'     => $faq_count,
                'has_faq'       => $faq_count > 0,
            );
        }

        return $items;
    }

    /**
     * Get the audit slug for this site.
     *
     * Converts hostname to slug format: wptest.datasub.com → wptest-datasub-com
     *
     * @return string
     */
    public static function get_site_slug() {
        $home = wp_parse_url( get_home_url(), PHP_URL_HOST );
        if ( ! $home ) {
            return '';
        }
        // Remove www. prefix.
        $home = preg_replace( '/^www\./', '', $home );
        return sanitize_title( str_replace( '.', '-', $home ) );
    }

    /**
     * Handle an auth failure from the platform.
     *
     * Clears the stored site token and connection flag so the plugin reverts
     * to a disconnected state. Returns a WP_Error with a special code that
     * the JS layer can detect and redirect to the Connect tab.
     *
     * @return WP_Error
     */
    private static function handle_auth_failure() {
        delete_option( 'aeocas_site_token' );
        delete_option( 'aeocas_plugin_token' );
        delete_option( 'aeocas_connection_verified' );
        self::clear_cache();
        AEOCAS_Activity_Log::log( 'auth_failure', 'error', array( 'message' => 'API key rejected by platform — connection cleared.' ) );
        return new WP_Error( 'aeocas_auth_expired', __( 'Your site connection has expired or been revoked. Please reconnect.', 'aeo-content-ai-studio' ) );
    }

    /**
     * Extract a visibility snapshot from a payload when present.
     *
     * @param mixed $payload Raw payload.
     * @return array|null
     */
    private static function extract_visibility_payload( $payload ) {
        if ( ! is_array( $payload ) ) {
            return null;
        }

        if ( isset( $payload['engines'] ) && isset( $payload['citations_count'] ) ) {
            return $payload;
        }

        if ( ! empty( $payload['visibility'] ) && is_array( $payload['visibility'] ) ) {
            return $payload['visibility'];
        }

        if ( ! empty( $payload['data']['visibility'] ) && is_array( $payload['data']['visibility'] ) ) {
            return $payload['data']['visibility'];
        }

        return null;
    }

    /**
     * Convert internal engine keys into user-facing labels.
     *
     * @param string $engine Engine key.
     * @return string
     */
    private static function visibility_engine_label( $engine ) {
        $engine = sanitize_key( $engine );

        switch ( $engine ) {
            case 'chatgpt':
                return 'ChatGPT';
            case 'perplexity':
                return 'Perplexity';
            case 'claude':
                return 'Claude';
            case 'gemini':
                return 'Gemini';
            case 'google_aio':
            case 'google-ai-overview':
            case 'google_ai_overview':
                return 'Google AI Overview';
            default:
                return ucwords( str_replace( array( '-', '_' ), ' ', $engine ) );
        }
    }

    /**
     * Normalize the public /api/v1/visibility response into the compact
     * snapshot shape used by the WordPress plugin UI.
     *
     * @param mixed $payload Raw visibility API response.
     * @return array|null
     */
    private static function normalize_visibility_payload( $payload ) {
        if ( ! is_array( $payload ) ) {
            return null;
        }

        $reports = array();
        if ( isset( $payload['data'] ) ) {
            if ( is_array( $payload['data'] ) && isset( $payload['data'][0] ) ) {
                $reports = $payload['data'];
            } elseif ( is_array( $payload['data'] ) ) {
                $reports = array( $payload['data'] );
            }
        } elseif ( isset( $payload[0] ) && is_array( $payload[0] ) ) {
            $reports = $payload;
        } elseif ( isset( $payload['engine'] ) ) {
            $reports = array( $payload );
        }

        if ( empty( $reports ) ) {
            return null;
        }

        $total_visible       = 0;
        $total_queries       = 0;
        $engines             = array();
        $alerts              = array();
        $citations           = array();
        $competitors         = array();
        $trend_points        = array();
        $latest_synced_at    = '';
        $competitor_mentions = array();
        $seen_alerts         = array();

        foreach ( $reports as $report ) {
            if ( ! is_array( $report ) ) {
                continue;
            }

            $engine_key   = isset( $report['engine'] ) ? (string) $report['engine'] : 'unknown';
            $engine_label = self::visibility_engine_label( $engine_key );
            $visible_count = isset( $report['visibility_score'] ) ? (int) $report['visibility_score'] : 0;
            $query_total   = isset( $report['visibility_total'] ) ? (int) $report['visibility_total'] : 0;
            $engine_pct    = $query_total > 0 ? (int) round( ( $visible_count / $query_total ) * 100 ) : null;
            $domain        = isset( $report['domain'] ) ? preg_replace( '/^www\./', '', (string) $report['domain'] ) : '';
            $page_url      = $domain ? 'https://' . ltrim( $domain, '/' ) : '';
            $created_at    = isset( $report['created_at'] ) ? (string) $report['created_at'] : '';

            $engines[] = array(
                'name'          => $engine_label,
                'engine'        => sanitize_key( $engine_key ),
                'count'         => $visible_count,
                'visibility_pct'=> $engine_pct,
                'tested_queries'=> $query_total,
            );

            $total_visible += $visible_count;
            $total_queries += $query_total;

            if ( $created_at && ( empty( $latest_synced_at ) || strtotime( $created_at ) > strtotime( $latest_synced_at ) ) ) {
                $latest_synced_at = $created_at;
            }

            $key_findings = isset( $report['key_findings'] ) && is_array( $report['key_findings'] ) ? $report['key_findings'] : array();
            foreach ( $key_findings as $finding ) {
                if ( ! is_array( $finding ) ) {
                    continue;
                }

                $text = isset( $finding['text'] ) ? wp_strip_all_tags( (string) $finding['text'] ) : '';
                if ( '' === $text ) {
                    continue;
                }

                $finding_type = isset( $finding['type'] ) ? sanitize_key( (string) $finding['type'] ) : 'finding';
                $severity     = 'warning' === $finding_type ? 'warning' : 'neutral';
                $alert_key    = md5( $severity . '|' . $text );
                if ( isset( $seen_alerts[ $alert_key ] ) ) {
                    continue;
                }

                $seen_alerts[ $alert_key ] = true;
                $alerts[] = array(
                    'severity' => $severity,
                    'title'    => $text,
                    'detail'   => $engine_label . ' finding',
                    'category' => 'finding',
                    'engine'   => $engine_label,
                );
            }

            $action_plan = isset( $report['action_plan'] ) && is_array( $report['action_plan'] ) ? $report['action_plan'] : array();
            foreach ( array_slice( $action_plan, 0, 4 ) as $action_item ) {
                if ( ! is_array( $action_item ) ) {
                    continue;
                }

                $title = isset( $action_item['action'] ) ? wp_strip_all_tags( (string) $action_item['action'] ) : '';
                if ( '' === $title ) {
                    continue;
                }

                $priority = isset( $action_item['priority'] ) ? strtoupper( (string) $action_item['priority'] ) : '';
                $severity = 'warning';
                if ( 'P0' === $priority ) {
                    $severity = 'critical';
                } elseif ( ! in_array( $priority, array( 'P0', 'P1' ), true ) ) {
                    $severity = 'neutral';
                }

                $detail = '';
                if ( ! empty( $action_item['impact'] ) ) {
                    $detail = wp_strip_all_tags( (string) $action_item['impact'] );
                } elseif ( ! empty( $action_item['notes'] ) ) {
                    $detail = wp_strip_all_tags( (string) $action_item['notes'] );
                }

                $alert_key = md5( 'action|' . $severity . '|' . $title );
                if ( isset( $seen_alerts[ $alert_key ] ) ) {
                    continue;
                }

                $seen_alerts[ $alert_key ] = true;
                $alerts[] = array(
                    'severity' => $severity,
                    'title'    => $title,
                    'detail'   => $detail,
                    'category' => 'action',
                    'engine'   => $engine_label,
                );
            }

            $variants = isset( $report['query_variants'] ) && is_array( $report['query_variants'] ) ? $report['query_variants'] : array();
            foreach ( $variants as $variant ) {
                if ( ! is_array( $variant ) || empty( $variant['query'] ) ) {
                    continue;
                }

                $query          = wp_strip_all_tags( (string) $variant['query'] );
                $target_visible = ! empty( $variant['target_visible'] );
                $snippet        = ! empty( $variant['what_llm_returns'] ) ? wp_strip_all_tags( (string) $variant['what_llm_returns'] ) : '';

                if ( $target_visible ) {
                    $citations[] = array(
                        'engine'    => $engine_label,
                        'query'     => $query,
                        'page_url'  => $page_url,
                        'page_title'=> $domain ? $domain : $engine_label,
                        'cited_at'  => $created_at,
                        'snippet'   => $snippet,
                        'severity'  => 'neutral',
                    );
                }

                if ( ! empty( $variant['competitor_visibility'] ) && is_array( $variant['competitor_visibility'] ) ) {
                    foreach ( $variant['competitor_visibility'] as $competitor_domain => $is_visible ) {
                        if ( ! $is_visible ) {
                            continue;
                        }

                        $competitor_key = preg_replace( '/^www\./', '', strtolower( (string) $competitor_domain ) );
                        if ( '' === $competitor_key || $competitor_key === $domain ) {
                            continue;
                        }

                        if ( ! isset( $competitor_mentions[ $competitor_key ] ) ) {
                            $competitor_mentions[ $competitor_key ] = 0;
                        }
                        $competitor_mentions[ $competitor_key ]++;
                    }
                }
            }

            $llm_entries = isset( $report['llm_response_analysis'] ) && is_array( $report['llm_response_analysis'] ) ? $report['llm_response_analysis'] : array();
            foreach ( $llm_entries as $entry ) {
                if ( ! is_array( $entry ) || empty( $entry['domain'] ) ) {
                    continue;
                }

                $competitor_key = preg_replace( '/^www\./', '', strtolower( (string) $entry['domain'] ) );
                if ( '' === $competitor_key || $competitor_key === $domain ) {
                    continue;
                }

                if ( ! isset( $competitor_mentions[ $competitor_key ] ) ) {
                    $competitor_mentions[ $competitor_key ] = 0;
                }
                $competitor_mentions[ $competitor_key ]++;
            }

            $comparison_rows = isset( $report['competitor_comparison'] ) && is_array( $report['competitor_comparison'] ) ? $report['competitor_comparison'] : array();
            foreach ( $comparison_rows as $row ) {
                if ( empty( $row['competitors'] ) || ! is_array( $row['competitors'] ) ) {
                    continue;
                }

                foreach ( $row['competitors'] as $competitor ) {
                    if ( ! is_array( $competitor ) || empty( $competitor['name'] ) ) {
                        continue;
                    }

                    $competitor_key = strtolower( trim( (string) $competitor['name'] ) );
                    if ( '' === $competitor_key ) {
                        continue;
                    }

                    if ( ! isset( $competitor_mentions[ $competitor_key ] ) ) {
                        $competitor_mentions[ $competitor_key ] = 0;
                    }
                    $competitor_mentions[ $competitor_key ]++;
                }
            }
        }

        $timeline = isset( $payload['timeline'] ) && is_array( $payload['timeline'] ) ? $payload['timeline'] : array();
        foreach ( $timeline as $point ) {
            if ( ! is_array( $point ) || ! isset( $point['score'] ) ) {
                continue;
            }

            $trend_points[] = array(
                'date'  => isset( $point['published_at'] ) ? (string) $point['published_at'] : '',
                'score' => (int) $point['score'],
            );
        }

        $delta_7d  = null;
        $delta_30d = null;
        if ( count( $trend_points ) >= 2 ) {
            $last_index = count( $trend_points ) - 1;
            $delta_7d   = (int) $trend_points[ $last_index ]['score'] - (int) $trend_points[ $last_index - 1 ]['score'];
            $delta_30d  = (int) $trend_points[ $last_index ]['score'] - (int) $trend_points[0]['score'];
        }

        $query_deltas = isset( $payload['query_deltas'] ) && is_array( $payload['query_deltas'] ) ? $payload['query_deltas'] : array();
        foreach ( $query_deltas as $delta ) {
            if ( ! is_array( $delta ) || empty( $delta['query'] ) || empty( $delta['change'] ) ) {
                continue;
            }

            $change = sanitize_key( (string) $delta['change'] );
            if ( ! in_array( $change, array( 'gained', 'lost' ), true ) ) {
                continue;
            }

            $query = wp_strip_all_tags( (string) $delta['query'] );
            $severity = 'lost' === $change ? 'critical' : 'healthy';
            $alerts[] = array(
                'severity' => $severity,
                'title'    => ( 'lost' === $change ? 'Lost visibility for' : 'Gained visibility for' ) . ' "' . $query . '"',
                'detail'   => 'Derived from recent visibility version changes.',
                'category' => 'delta',
                'engine'   => '',
            );
        }

        arsort( $competitor_mentions );
        $top_competitor_count = ! empty( $competitor_mentions ) ? (int) reset( $competitor_mentions ) : 0;
        foreach ( array_slice( $competitor_mentions, 0, 8, true ) as $competitor_name => $mention_count ) {
            $competitors[] = array(
                'name'            => $competitor_name,
                'visibility_score'=> null,
                'delta_30d'       => null,
                'citation_share'  => $total_visible > 0 ? (int) round( ( $mention_count / $total_visible ) * 100 ) : null,
                'mention_count'   => (int) $mention_count,
                'relative_score'  => $top_competitor_count > 0 ? (int) round( ( $mention_count / $top_competitor_count ) * 100 ) : null,
            );
        }

        return array(
            'status'            => 'ready',
            'visibility_score'  => $total_queries > 0 ? (int) round( ( $total_visible / $total_queries ) * 100 ) : null,
            'delta_7d'          => $delta_7d,
            'delta_30d'         => $delta_30d,
            'citations_count'   => $total_visible,
            'engines'           => $engines,
            'top_citations'     => array_slice( $citations, 0, 20 ),
            'competitors'       => $competitors,
            'alerts'            => array_slice( $alerts, 0, 20 ),
            'trend_points_30d'  => $trend_points,
            'last_synced_at'    => $latest_synced_at,
        );
    }

    /**
     * Fetch audit data from the platform API.
     *
     * @param bool $force_refresh Skip cache and fetch fresh data.
     * @return array|WP_Error Audit data array or WP_Error on failure.
     */
    public static function get_audit( $force_refresh = false ) {
        $api_key = get_option( 'aeocas_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured. Go to Settings to connect your site.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeocas_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $transient_key = self::TRANSIENT_PREFIX . $slug;

        // Check cache.
        if ( ! $force_refresh ) {
            $cached = get_transient( $transient_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        // Fetch from platform.
        $url = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/audits/' . $slug . '?include=all';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 404 === $status ) {
            return new WP_Error( 'aeocas_no_audit', __( 'No audit found for this site. Request an audit at aeocontent.ai.', 'aeo-content-ai-studio' ) );
        }

        if ( 401 === $status || 403 === $status ) {
            return self::handle_auth_failure();
        }

        if ( 200 !== $status || empty( $body['data'] ) ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unexpected API response.', 'aeo-content-ai-studio' );
            return new WP_Error( 'aeocas_api_error', $message );
        }

        // The API returns an array when no engine param, or a single object with engine param.
        // We always use the first audit (or the single object).
        $audit = is_array( $body['data'] ) && isset( $body['data'][0] ) ? $body['data'][0] : $body['data'];

        // Cache the result.
        set_transient( $transient_key, $audit, self::CACHE_TTL );

        return $audit;
    }

    /**
     * Clear cached audit data.
     */
    public static function clear_cache() {
        $slug = self::get_site_slug();
        if ( $slug ) {
            delete_transient( self::TRANSIENT_PREFIX . $slug );
            delete_transient( self::DISCOVERY_TRANSIENT_PREFIX . $slug );
            delete_transient( self::VISIBILITY_TRANSIENT_PREFIX . $slug );
        }
    }

    /**
     * Fetch discovery data from the platform API.
     *
     * Discovery is populated the moment the remote `discovering` stage finishes,
     * well before the full audit completes. While the job is still pending or
     * early-discovering, the remote returns `discovery: null` and the plugin
     * renders the progress UI instead.
     *
     * @param bool $force_refresh Skip cache and fetch fresh data.
     * @return array|WP_Error Discovery payload wrapper or WP_Error on failure.
     */
    public static function get_discovery( $force_refresh = false ) {
        $api_key = get_option( 'aeocas_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured. Go to Settings to connect your site.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeocas_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $transient_key = self::DISCOVERY_TRANSIENT_PREFIX . $slug;

        if ( ! $force_refresh ) {
            $cached = get_transient( $transient_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $url = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/audits/' . $slug . '/discovery';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 404 === $status ) {
            return new WP_Error( 'aeocas_no_discovery', __( 'No audit job found for this site yet. Connect the site and run the first audit to populate Discovery.', 'aeo-content-ai-studio' ) );
        }

        if ( 401 === $status || 403 === $status ) {
            return self::handle_auth_failure();
        }

        if ( 200 !== $status || empty( $body['data'] ) ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unexpected API response.', 'aeo-content-ai-studio' );
            return new WP_Error( 'aeocas_api_error', $message );
        }

        $payload = $body['data'];

        // While the remote job is still running, cache briefly so the UI can keep polling.
        // Once the job has settled (completed or discovery populated with no more work expected), cache longer.
        $is_settled = ! empty( $payload['discovery'] ) && in_array( $payload['status'] ?? '', array( 'completed', 'failed' ), true );
        $ttl        = $is_settled ? self::CACHE_TTL : self::SHORT_CACHE_TTL;

        set_transient( $transient_key, $payload, $ttl );

        return $payload;
    }

    /**
     * Fetch AI visibility data from the platform API.
     *
     * @param bool $force_refresh Skip cache and fetch fresh data.
     * @return array|WP_Error
     */
    public static function get_visibility( $force_refresh = false ) {
        $api_key = get_option( 'aeocas_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured. Go to Settings to connect your site.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeocas_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $transient_key = self::VISIBILITY_TRANSIENT_PREFIX . $slug;

        if ( ! $force_refresh ) {
            $cached = get_transient( $transient_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $cached_audit = get_transient( self::TRANSIENT_PREFIX . $slug );
        $cached_visibility = self::extract_visibility_payload( $cached_audit );
        if ( $cached_visibility ) {
            set_transient( $transient_key, $cached_visibility, self::VISIBILITY_CACHE_TTL );
            return $cached_visibility;
        }

        $url = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/visibility/' . $slug . '?include=timeline';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 401 === $status || 403 === $status ) {
            return self::handle_auth_failure();
        }

        if ( 404 === $status ) {
            $audit = self::get_audit( $force_refresh );
            if ( ! is_wp_error( $audit ) ) {
                $audit_visibility = self::extract_visibility_payload( $audit );
                if ( $audit_visibility ) {
                    set_transient( $transient_key, $audit_visibility, self::VISIBILITY_CACHE_TTL );
                    return $audit_visibility;
                }
            }
            return new WP_Error( 'aeocas_no_visibility', __( 'AI visibility data is not available yet. Open the admin workspace for the latest sync status.', 'aeo-content-ai-studio' ) );
        }

        if ( 200 !== $status ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unexpected visibility response.', 'aeo-content-ai-studio' );
            return new WP_Error( 'aeocas_api_error', $message );
        }

        $payload    = $body;
        $visibility = self::extract_visibility_payload( $payload );
        if ( ! $visibility ) {
            $visibility = self::normalize_visibility_payload( $payload );
        }

        if ( empty( $visibility ) || ! is_array( $visibility ) ) {
            return new WP_Error( 'aeocas_no_visibility', __( 'AI visibility data is not available yet. Open the admin workspace for the latest sync status.', 'aeo-content-ai-studio' ) );
        }

        $visibility_status = isset( $visibility['status'] ) ? sanitize_key( $visibility['status'] ) : '';
        $ttl = in_array( $visibility_status, array( 'pending', 'refreshing', 'building', 'queued' ), true )
            ? self::SHORT_CACHE_TTL
            : self::VISIBILITY_CACHE_TTL;

        set_transient( $transient_key, $visibility, $ttl );

        return $visibility;
    }

    /**
     * AJAX handler for fetching audit data.
     */
    public static function ajax_get_audit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

        $force = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $audit = self::get_audit( $force );

        if ( is_wp_error( $audit ) ) {
            wp_send_json_error( array( 'message' => $audit->get_error_message(), 'code' => $audit->get_error_code() ) );
        }

        wp_send_json_success( $audit );
    }

    /**
     * Dispatch a Discovery + Full Site Audit via /api/v1/plugin/onboard.
     *
     * Used by both the re-audit button (blocking, waits for response) and the
     * Google-connect onboarding flow (fire-and-forget). The endpoint is
     * idempotent, fires Modal directly, and works whether or not a published
     * audit already exists.
     *
     * @param bool $blocking When true (default), waits for the platform response
     *                       and returns it. When false, fires and returns true
     *                       immediately without waiting for the response.
     * @return array|true|WP_Error Platform response (blocking), true (non-blocking), or WP_Error.
     */
    public static function dispatch_audit( $blocking = true ) {
        $api_key = get_option( 'aeocas_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured.', 'aeo-content-ai-studio' ) );
        }

        $url = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/plugin/onboard';

        $response = wp_remote_post( $url, array(
            'headers'  => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'body'     => wp_json_encode( array( 'site_url' => get_home_url() ) ),
            'timeout'  => $blocking ? 20 : 3,
            'blocking' => $blocking,
        ) );

        if ( is_wp_error( $response ) ) {
            AEOCAS_Activity_Log::log( 'audit_dispatch', 'error', array( 'message' => $response->get_error_message() ) );
            return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
        }

        self::clear_cache();

        if ( ! $blocking ) {
            AEOCAS_Activity_Log::log( 'audit_dispatch', 'success', array( 'message' => 'Audit dispatched (non-blocking).' ) );
            return true;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 401 === $status || 403 === $status ) {
            return self::handle_auth_failure();
        }

        if ( $status >= 400 ) {
            $message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( isset( $body['message'] ) ? $body['message'] : __( 'Failed to trigger audit.', 'aeo-content-ai-studio' ) );
            AEOCAS_Activity_Log::log( 'audit_dispatch', 'error', array( 'message' => $message ) );
            return new WP_Error( 'aeocas_reaudit_error', $message );
        }

        AEOCAS_Activity_Log::log( 'audit_dispatch', 'success', array( 'message' => 'Audit dispatched.', 'slug' => $body['data']['slug'] ?? null ) );
        return $body;
    }

    /** @deprecated Use dispatch_audit() directly. */
    public static function trigger_reaudit() {
        return self::dispatch_audit( true );
    }

    /** @deprecated Use dispatch_audit( false ) directly. */
    public static function trigger_onboarding() {
        return self::dispatch_audit( false );
    }

    /**
     * Get audit job status from platform.
     *
     * @return array|WP_Error Status data or error.
     */
    public static function get_audit_status() {
        $api_key = get_option( 'aeocas_site_token', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured.', 'aeo-content-ai-studio' ) );
        }

        $slug = self::get_site_slug();
        if ( empty( $slug ) ) {
            return new WP_Error( 'aeocas_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
        }

        $url = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/audits/' . $slug . '/status';

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 401 === $status || 403 === $status ) {
            return self::handle_auth_failure();
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return $body;
    }

    /**
     * AJAX handler for triggering re-audit.
     */
    public static function ajax_reaudit() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

        $result = self::dispatch_audit( true );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for fetching discovery data.
     */
    public static function ajax_get_discovery() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

        $force     = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $discovery = self::get_discovery( $force );

        if ( is_wp_error( $discovery ) ) {
            wp_send_json_error( array( 'message' => $discovery->get_error_message(), 'code' => $discovery->get_error_code() ) );
        }

        wp_send_json_success( $discovery );
    }

    /**
     * AJAX handler for fetching AI visibility data.
     */
    public static function ajax_get_visibility() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

        $force      = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $visibility = self::get_visibility( $force );

        if ( is_wp_error( $visibility ) ) {
            wp_send_json_error( array( 'message' => $visibility->get_error_message(), 'code' => $visibility->get_error_code() ) );
        }

        wp_send_json_success( $visibility );
    }

    /**
     * AJAX handler for polling audit status.
     */
    public static function ajax_audit_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

        $result = self::get_audit_status();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX handler for fetching a local content index used by admin JS.
     */
    public static function ajax_get_local_content_index() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
        }

        check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

        wp_send_json_success( array(
            'items' => self::get_local_content_index(),
        ) );
    }

    /**
     * Register AJAX hooks.
     */
    public static function register_ajax() {
        add_action( 'wp_ajax_aeocas_get_audit', array( __CLASS__, 'ajax_get_audit' ) );
        add_action( 'wp_ajax_aeocas_reaudit', array( __CLASS__, 'ajax_reaudit' ) );
        add_action( 'wp_ajax_aeocas_audit_status', array( __CLASS__, 'ajax_audit_status' ) );
        add_action( 'wp_ajax_aeocas_get_discovery', array( __CLASS__, 'ajax_get_discovery' ) );
        add_action( 'wp_ajax_aeocas_get_visibility', array( __CLASS__, 'ajax_get_visibility' ) );
        add_action( 'wp_ajax_aeocas_get_local_content_index', array( __CLASS__, 'ajax_get_local_content_index' ) );
    }
}
