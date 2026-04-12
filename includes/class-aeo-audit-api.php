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

	/** @var int Cache lifetime for rewrite availability snapshots (2 minutes). */
	const REWRITE_AVAILABILITY_CACHE_TTL = 2 * MINUTE_IN_SECONDS;

	/** @var string Transient key prefix for cached audit payloads. */
	const TRANSIENT_PREFIX = 'aeocas_audit_';

	/** @var string Transient key prefix for cached discovery payloads. */
	const DISCOVERY_TRANSIENT_PREFIX = 'aeocas_discovery_';

	/** @var string Transient key prefix for cached visibility payloads. */
	const VISIBILITY_TRANSIENT_PREFIX = 'aeocas_visibility_';

	/** @var string Transient key prefix for cached rewrite availability payloads. */
	const REWRITE_AVAILABILITY_TRANSIENT_PREFIX = 'aeocas_rewrite_availability_';

	/**
	 * Build a lightweight local content index for admin-side page enrichment.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_local_content_index() {
		$post_ids = get_posts(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => -1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $post_ids as $post_id ) {
			$url = get_permalink( $post_id );
			if ( empty( $url ) ) {
				continue;
			}

			$faq                   = get_post_meta( $post_id, '_aeocas_faq_schema', true );
			$canonical             = get_post_meta( $post_id, '_aeocas_canonical_url', true );
			$rewrite_status        = get_post_meta( $post_id, '_aeocas_rewrite_status', true );
			$rewrite_id            = get_post_meta( $post_id, '_aeocas_rewrite_id', true );
			$source_post_id        = get_post_meta( $post_id, '_aeocas_rewrite_source_post_id', true );
			$active_draft          = get_post_meta( $post_id, '_aeocas_active_rewrite_draft_id', true );
			$active_draft_edit_url = $active_draft ? get_edit_post_link( $active_draft, 'raw' ) : '';
			$faq_count             = is_array( $faq ) ? count( $faq ) : 0;
			$edit_url              = get_edit_post_link( $post_id, 'raw' );
			$can_edit              = current_user_can( 'edit_post', $post_id );

			$items[] = array(
				'id'                            => (int) $post_id,
				'title'                         => get_the_title( $post_id ),
				'url'                           => esc_url_raw( $url ),
				'canonical_url'                 => $canonical ? esc_url_raw( $canonical ) : '',
				'edit_url'                      => $edit_url ? esc_url_raw( $edit_url ) : '',
				'post_type'                     => get_post_type( $post_id ),
				'status'                        => get_post_status( $post_id ),
				'modified_gmt'                  => get_post_modified_time( 'c', true, $post_id ),
				'faq_count'                     => $faq_count,
				'has_faq'                       => $faq_count > 0,
				'can_edit'                      => (bool) $can_edit,
				'rewrite_status'                => is_string( $rewrite_status ) ? sanitize_text_field( $rewrite_status ) : '',
				'rewrite_id'                    => is_string( $rewrite_id ) ? sanitize_text_field( $rewrite_id ) : '',
				'rewrite_source_post_id'        => absint( $source_post_id ),
				'active_rewrite_draft_id'       => absint( $active_draft ),
				'active_rewrite_draft_edit_url' => $active_draft_edit_url ? esc_url_raw( $active_draft_edit_url ) : '',
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
	 * Normalize a URL into a stable lookup key.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private static function normalize_url_key( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}

		$key = preg_replace( '#/+$#', '', trim( $url ) );
		$key = preg_replace( '#^https?://#i', '', $key );
		$key = preg_replace( '#^www\.#i', '', $key );

		return strtolower( $key );
	}

	/**
	 * Find a local content item mapped to a given page URL.
	 *
	 * @param string $page_url Page URL.
	 * @return array|null
	 */
	private static function get_local_content_item_for_url( $page_url ) {
		$target_key = self::normalize_url_key( $page_url );
		if ( '' === $target_key ) {
			return null;
		}

		foreach ( self::get_local_content_index() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$item_key = self::normalize_url_key( $item['url'] ?? '' );
			if ( $item_key && $item_key === $target_key ) {
				return $item;
			}

			$canonical_key = self::normalize_url_key( $item['canonical_url'] ?? '' );
			if ( $canonical_key && $canonical_key === $target_key ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Resolve the content module used for rewrite draft operations.
	 *
	 * @return AEOCAS_Content|null
	 */
	private static function get_content_module() {
		if ( ! class_exists( 'AEOCAS_Content' ) ) {
			$file = AEOCAS_PLUGIN_DIR . 'includes/modules/class-aeo-content.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		return class_exists( 'AEOCAS_Content' ) ? new AEOCAS_Content() : null;
	}

	/**
	 * Find the latest audit page record matching a URL.
	 *
	 * @param string $page_url Page URL.
	 * @return array|null
	 */
	private static function find_audit_page_for_url( $page_url ) {
		$audit = self::get_audit();
		if ( is_wp_error( $audit ) || empty( $audit['pages_reviewed'] ) || ! is_array( $audit['pages_reviewed'] ) ) {
			return null;
		}

		$target_key = self::normalize_url_key( $page_url );
		foreach ( $audit['pages_reviewed'] as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}

			if ( self::normalize_url_key( $page['url'] ?? '' ) === $target_key ) {
				return $page;
			}
		}

		return null;
	}

	/**
	 * Extract the best available score from an audit page record.
	 *
	 * @param array $page Audit page record.
	 * @return int
	 */
	private static function get_audit_page_score( $page ) {
		if ( isset( $page['pageRankScore'] ) && is_numeric( $page['pageRankScore'] ) ) {
			return (int) round( $page['pageRankScore'] );
		}

		if ( isset( $page['pageRank']['score'] ) && is_numeric( $page['pageRank']['score'] ) ) {
			return (int) round( $page['pageRank']['score'] );
		}

		if ( isset( $page['aeoScore'] ) && is_numeric( $page['aeoScore'] ) ) {
			return (int) round( $page['aeoScore'] );
		}

		return 0;
	}

	/**
	 * Resolve the weakest pillar from a page audit record.
	 *
	 * @param array $page Audit page record.
	 * @return array|null
	 */
	private static function get_weakest_page_pillar( $page ) {
		$pillars = array();
		if ( ! empty( $page['pageRankPillars'] ) && is_array( $page['pageRankPillars'] ) ) {
			$pillars = $page['pageRankPillars'];
		} elseif ( ! empty( $page['pillarScores'] ) && is_array( $page['pillarScores'] ) ) {
			$pillars = $page['pillarScores'];
		}

		if ( empty( $pillars ) ) {
			return null;
		}

		$weakest = null;
		foreach ( $pillars as $key => $pillar ) {
			if ( ! is_array( $pillar ) ) {
				continue;
			}

			$score = isset( $pillar['score'] ) && is_numeric( $pillar['score'] ) ? (float) $pillar['score'] : null;
			if ( null === $score ) {
				continue;
			}

			if ( null === $weakest || $score < $weakest['score'] ) {
				$weakest = array(
					'key'   => sanitize_key( is_string( $key ) ? $key : '' ),
					'label' => isset( $pillar['label'] ) ? sanitize_text_field( (string) $pillar['label'] ) : sanitize_text_field( str_replace( array( '-', '_' ), ' ', (string) $key ) ),
					'score' => $score,
				);
			}
		}

		return $weakest;
	}

	/**
	 * Build the source payload used for rewrite preview requests.
	 *
	 * @param array $local_item Local content item from the index.
	 * @return array
	 */
	private static function build_rewrite_source_payload( $local_item ) {
		$post_id = isset( $local_item['id'] ) ? absint( $local_item['id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return array();
		}

		return array(
			'title'        => isset( $post->post_title ) ? (string) $post->post_title : '',
			'content_html' => isset( $post->post_content ) ? (string) $post->post_content : '',
			'excerpt'      => isset( $post->post_excerpt ) ? (string) $post->post_excerpt : '',
			'post_type'    => get_post_type( $post_id ),
			'status'       => get_post_status( $post_id ),
			'categories'   => wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ),
			'tags'         => wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ),
		);
	}

	/**
	 * Normalize rewrite availability payloads from multiple API response shapes.
	 *
	 * @param mixed $payload Raw API response.
	 * @return array|null
	 */
	private static function normalize_rewrite_availability( $payload ) {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$candidate = null;
		if ( ! empty( $payload['data']['rewrites'] ) && is_array( $payload['data']['rewrites'] ) ) {
			$candidate = $payload['data']['rewrites'];
		} elseif ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) && ( isset( $payload['data']['available'] ) || isset( $payload['data']['limit'] ) ) ) {
			$candidate = $payload['data'];
		} elseif ( ! empty( $payload['rewrites'] ) && is_array( $payload['rewrites'] ) ) {
			$candidate = $payload['rewrites'];
		} elseif ( isset( $payload['available'] ) || isset( $payload['limit'] ) || isset( $payload['plan'] ) ) {
			$candidate = $payload;
		}

		if ( ! $candidate ) {
			return null;
		}

		return array(
			'available'           => isset( $candidate['available'] ) ? max( 0, (int) $candidate['available'] ) : 0,
			'used'                => isset( $candidate['used'] ) ? max( 0, (int) $candidate['used'] ) : 0,
			'limit'               => isset( $candidate['limit'] ) ? max( 0, (int) $candidate['limit'] ) : 0,
			'plan'                => isset( $candidate['plan'] ) ? sanitize_text_field( (string) $candidate['plan'] ) : '',
			'plan_label'          => isset( $candidate['plan_label'] ) ? sanitize_text_field( (string) $candidate['plan_label'] ) : '',
			'resets_at'           => isset( $candidate['resets_at'] ) ? (string) $candidate['resets_at'] : '',
			'upgrade_url'         => isset( $candidate['upgrade_url'] ) ? esc_url_raw( $candidate['upgrade_url'] ) : '',
			'starter_eligible'    => ! empty( $candidate['starter_eligible'] ),
			'starter_price_cents' => isset( $candidate['starter_price_cents'] ) ? max( 0, (int) $candidate['starter_price_cents'] ) : 0,
			'starter_articles'    => isset( $candidate['starter_articles'] ) ? max( 0, (int) $candidate['starter_articles'] ) : 0,
			'checkout_enabled'    => ! empty( $candidate['checkout_enabled'] ),
		);
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
	 * Normalize any visibility-bearing payload into the plugin snapshot shape.
	 *
	 * The dedicated visibility endpoint is the source of truth. Audit payloads
	 * may still embed a partial or stale `visibility` block, so this helper is
	 * used only as a fallback after the dedicated request has been attempted.
	 *
	 * @param mixed $payload Raw payload or embedded visibility block.
	 * @return array|null
	 */
	private static function resolve_visibility_payload( $payload ) {
		$visibility = self::extract_visibility_payload( $payload );
		if ( ! $visibility ) {
			$visibility = self::normalize_visibility_payload( $payload );
		}

		return ( ! empty( $visibility ) && is_array( $visibility ) ) ? $visibility : null;
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

			$engine_key    = isset( $report['engine'] ) ? (string) $report['engine'] : 'unknown';
			$engine_label  = self::visibility_engine_label( $engine_key );
			$visible_count = isset( $report['visibility_score'] ) ? (int) $report['visibility_score'] : 0;
			$query_total   = isset( $report['visibility_total'] ) ? (int) $report['visibility_total'] : 0;
			$engine_pct    = $query_total > 0 ? (int) round( ( $visible_count / $query_total ) * 100 ) : null;
			$domain        = isset( $report['domain'] ) ? preg_replace( '/^www\./', '', (string) $report['domain'] ) : '';
			$page_url      = $domain ? 'https://' . ltrim( $domain, '/' ) : '';
			$created_at    = isset( $report['created_at'] ) ? (string) $report['created_at'] : '';

			$engines[] = array(
				'name'           => $engine_label,
				'engine'         => sanitize_key( $engine_key ),
				'count'          => $visible_count,
				'visibility_pct' => $engine_pct,
				'tested_queries' => $query_total,
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
				$alerts[]                  = array(
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
				$alerts[]                  = array(
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
						'engine'     => $engine_label,
						'query'      => $query,
						'page_url'   => $page_url,
						'page_title' => $domain ? $domain : $engine_label,
						'cited_at'   => $created_at,
						'snippet'    => $snippet,
						'severity'   => 'neutral',
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
						++$competitor_mentions[ $competitor_key ];
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
				++$competitor_mentions[ $competitor_key ];
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
					++$competitor_mentions[ $competitor_key ];
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

			$query    = wp_strip_all_tags( (string) $delta['query'] );
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
				'name'             => $competitor_name,
				'visibility_score' => null,
				'delta_30d'        => null,
				'citation_share'   => $total_visible > 0 ? (int) round( ( $mention_count / $total_visible ) * 100 ) : null,
				'mention_count'    => (int) $mention_count,
				'relative_score'   => $top_competitor_count > 0 ? (int) round( ( $mention_count / $top_competitor_count ) * 100 ) : null,
			);
		}

		return array(
			'status'           => 'ready',
			'visibility_score' => $total_queries > 0 ? (int) round( ( $total_visible / $total_queries ) * 100 ) : null,
			'delta_7d'         => $delta_7d,
			'delta_30d'        => $delta_30d,
			'citations_count'  => $total_visible,
			'engines'          => $engines,
			'top_citations'    => array_slice( $citations, 0, 20 ),
			'competitors'      => $competitors,
			'alerts'           => array_slice( $alerts, 0, 20 ),
			'trend_points_30d' => $trend_points,
			'last_synced_at'   => $latest_synced_at,
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

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
				'timeout' => 20,
			)
		);

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
			delete_transient( self::REWRITE_AVAILABILITY_TRANSIENT_PREFIX . $slug );
		}
	}

	/**
	 * Fetch rewrite availability from the platform API.
	 *
	 * @param bool $force_refresh Skip cache and fetch fresh data.
	 * @return array|WP_Error
	 */
	public static function get_rewrite_availability( $force_refresh = false ) {
		$api_key = get_option( 'aeocas_site_token', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured. Go to Settings to connect your site.', 'aeo-content-ai-studio' ) );
		}

		$slug = self::get_site_slug();
		if ( empty( $slug ) ) {
			return new WP_Error( 'aeocas_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
		}

		$transient_key = self::REWRITE_AVAILABILITY_TRANSIENT_PREFIX . $slug;
		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$url = add_query_arg(
			array(
				'site_slug' => $slug,
				'site_url'  => get_home_url(),
			),
			trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/rewrites/availability'
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			return self::handle_auth_failure();
		}

		if ( 200 !== $status ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( isset( $body['message'] ) ? $body['message'] : __( 'Unexpected rewrite availability response.', 'aeo-content-ai-studio' ) );
			return new WP_Error( 'aeocas_api_error', $message );
		}

		$availability = self::normalize_rewrite_availability( $body );
		if ( ! $availability ) {
			return new WP_Error( 'aeocas_no_rewrite_availability', __( 'Rewrite availability is not available yet.', 'aeo-content-ai-studio' ) );
		}

		set_transient( $transient_key, $availability, self::REWRITE_AVAILABILITY_CACHE_TTL );

		return $availability;
	}

	/**
	 * Update the cached rewrite availability snapshot after a committed rewrite.
	 *
	 * @param array|null $availability Availability snapshot.
	 * @return array|null
	 */
	private static function consume_rewrite_availability_snapshot( $availability = null ) {
		$slug = self::get_site_slug();
		if ( empty( $slug ) ) {
			return is_array( $availability ) ? $availability : null;
		}

		if ( ! is_array( $availability ) ) {
			$availability = get_transient( self::REWRITE_AVAILABILITY_TRANSIENT_PREFIX . $slug );
		}

		if ( ! is_array( $availability ) ) {
			return null;
		}

		$availability['available'] = max( 0, (int) ( $availability['available'] ?? 0 ) - 1 );
		$availability['used']      = max( 0, (int) ( $availability['used'] ?? 0 ) + 1 );

		set_transient( self::REWRITE_AVAILABILITY_TRANSIENT_PREFIX . $slug, $availability, self::REWRITE_AVAILABILITY_CACHE_TTL );

		return $availability;
	}

	/**
	 * Create a starter checkout session for rewrite tokens.
	 *
	 * @return array|WP_Error
	 */
	public static function get_rewrite_checkout_url() {
		$api_key = get_option( 'aeocas_site_token', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured. Go to Settings to connect your site.', 'aeo-content-ai-studio' ) );
		}

		$slug = self::get_site_slug();
		if ( empty( $slug ) ) {
			return new WP_Error( 'aeocas_no_slug', __( 'Could not determine site slug.', 'aeo-content-ai-studio' ) );
		}

		$response = wp_remote_post(
			trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/rewrites/checkout',
			array(
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
				'timeout'     => 15,
				'body'        => wp_json_encode(
					array(
						'site_slug'  => $slug,
						'site_url'   => get_home_url(),
						'return_url' => admin_url( 'admin.php?page=aeocas-audit-report&tab=rewrite' ),
					)
				),
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			return self::handle_auth_failure();
		}

		if ( 200 !== $status ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( isset( $body['message'] ) ? $body['message'] : __( 'Unable to create rewrite checkout session.', 'aeo-content-ai-studio' ) );
			return new WP_Error( 'aeocas_checkout_error', $message );
		}

		$checkout_url = ( isset( $body['url'] ) && is_string( $body['url'] ) ) ? esc_url_raw( $body['url'] ) : '';
		if ( empty( $checkout_url ) ) {
			return new WP_Error( 'aeocas_checkout_missing_url', __( 'The platform did not return a checkout URL.', 'aeo-content-ai-studio' ) );
		}

		return array(
			'url' => $checkout_url,
		);
	}

	/**
	 * Normalize a rewrite preview response into the plugin shape.
	 *
	 * @param mixed $payload Raw preview response.
	 * @return array|null
	 */
	private static function normalize_rewrite_preview( $payload ) {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$candidate = null;
		if ( ! empty( $payload['data'] ) && is_array( $payload['data'] ) ) {
			$candidate = $payload['data'];
		} else {
			$candidate = $payload;
		}

		if ( ! is_array( $candidate ) ) {
			return null;
		}

		$current   = array();
		$optimized = array();

		if ( ! empty( $candidate['current'] ) && is_array( $candidate['current'] ) ) {
			$current = $candidate['current'];
		} elseif ( ! empty( $candidate['source'] ) && is_array( $candidate['source'] ) ) {
			$current = $candidate['source'];
		}

		if ( ! empty( $candidate['optimized'] ) && is_array( $candidate['optimized'] ) ) {
			$optimized = $candidate['optimized'];
		} elseif ( ! empty( $candidate['rewrite'] ) && is_array( $candidate['rewrite'] ) ) {
			$optimized = $candidate['rewrite'];
		} elseif ( ! empty( $candidate['result'] ) && is_array( $candidate['result'] ) ) {
			$optimized = $candidate['result'];
		}

		$preview = array(
			'rewrite_id'      => isset( $candidate['rewrite_id'] ) ? sanitize_text_field( (string) $candidate['rewrite_id'] ) : '',
			'page_url'        => isset( $candidate['page_url'] ) ? esc_url_raw( $candidate['page_url'] ) : '',
			'post_id'         => isset( $candidate['post_id'] ) ? absint( $candidate['post_id'] ) : 0,
			'audit_stamp'     => isset( $candidate['audit_stamp'] ) ? (string) $candidate['audit_stamp'] : ( isset( $candidate['audit_id'] ) ? (string) $candidate['audit_id'] : ( isset( $candidate['audit_updated_at'] ) ? (string) $candidate['audit_updated_at'] : '' ) ),
			'predicted_score' => isset( $candidate['predicted_score'] ) ? (int) round( $candidate['predicted_score'] ) : ( isset( $candidate['predicted']['score'] ) ? (int) round( $candidate['predicted']['score'] ) : 0 ),
			'predicted_delta' => isset( $candidate['predicted_delta'] ) ? (int) round( $candidate['predicted_delta'] ) : ( isset( $candidate['predicted']['delta'] ) ? (int) round( $candidate['predicted']['delta'] ) : 0 ),
			'weakest_pillar'  => isset( $candidate['weakest_pillar'] ) ? sanitize_text_field( (string) $candidate['weakest_pillar'] ) : '',
			'issues'          => isset( $candidate['issues'] ) && is_array( $candidate['issues'] ) ? array_values( $candidate['issues'] ) : array(),
			'top_fixes'       => isset( $candidate['top_fixes'] ) && is_array( $candidate['top_fixes'] ) ? array_values( $candidate['top_fixes'] ) : array(),
			'changes'         => isset( $candidate['changes'] ) && is_array( $candidate['changes'] ) ? array_values( $candidate['changes'] ) : array(),
			'reasons'         => isset( $candidate['reasons'] ) && is_array( $candidate['reasons'] ) ? array_values( $candidate['reasons'] ) : array(),
			'current'         => array(
				'title'        => isset( $current['title'] ) ? (string) $current['title'] : '',
				'excerpt'      => isset( $current['excerpt'] ) ? (string) $current['excerpt'] : '',
				'content_html' => isset( $current['content_html'] ) ? (string) $current['content_html'] : ( isset( $current['content'] ) ? (string) $current['content'] : '' ),
			),
			'optimized'       => array(
				'title'        => isset( $optimized['title'] ) ? (string) $optimized['title'] : '',
				'excerpt'      => isset( $optimized['excerpt'] ) ? (string) $optimized['excerpt'] : '',
				'content_html' => isset( $optimized['content_html'] ) ? (string) $optimized['content_html'] : ( isset( $optimized['content'] ) ? (string) $optimized['content'] : '' ),
			),
		);

		$availability = self::normalize_rewrite_availability( $candidate );
		if ( ! $availability ) {
			$availability = self::normalize_rewrite_availability( $payload );
		}

		if ( $availability ) {
			$preview['availability'] = $availability;
		}

		return $preview;
	}

	/**
	 * Request a rewrite preview from the platform.
	 *
	 * @param string $page_url Page URL.
	 * @return array|WP_Error
	 */
	public static function preview_rewrite( $page_url ) {
		$api_key = get_option( 'aeocas_site_token', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'aeocas_no_key', __( 'Site connection is not configured.', 'aeo-content-ai-studio' ) );
		}

		$page_url = esc_url_raw( $page_url );
		if ( empty( $page_url ) ) {
			return new WP_Error( 'aeocas_missing_page_url', __( 'Missing page URL for rewrite preview.', 'aeo-content-ai-studio' ) );
		}

		$slug       = self::get_site_slug();
		$local_item = self::get_local_content_item_for_url( $page_url );
		$page       = self::find_audit_page_for_url( $page_url );

		if ( ! $local_item || empty( $local_item['id'] ) ) {
			return new WP_Error( 'aeocas_rewrite_unavailable', __( 'This page is not mapped to an editable WordPress post.', 'aeo-content-ai-studio' ) );
		}

		$post_id = absint( $local_item['id'] );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'aeocas_rewrite_forbidden', __( 'You do not have permission to rewrite this post.', 'aeo-content-ai-studio' ) );
		}

		if ( ! $page ) {
			return new WP_Error( 'aeocas_rewrite_missing_audit_page', __( 'This page is not present in the latest audit snapshot.', 'aeo-content-ai-studio' ) );
		}

		$weakest = self::get_weakest_page_pillar( $page );
		$source  = self::build_rewrite_source_payload( $local_item );

		$request_body = array(
			'site_slug'      => $slug,
			'site_url'       => get_home_url(),
			'page_url'       => $page_url,
			'post_id'        => $post_id,
			'score'          => self::get_audit_page_score( $page ),
			'weakest_pillar' => $weakest ? $weakest['label'] : '',
			'issues'         => isset( $page['issues'] ) && is_array( $page['issues'] )
				? array_values(
					array_filter(
						array_map(
							static function ( $issue ) {
								if ( ! is_array( $issue ) ) {
									return '';
								}

								if ( ! empty( $issue['label'] ) ) {
									return (string) $issue['label'];
								}

								return ! empty( $issue['check'] ) ? (string) $issue['check'] : '';
							},
							$page['issues']
						)
					)
				)
				: array(),
			'top_fixes'      => isset( $page['topFixes'] ) && is_array( $page['topFixes'] ) ? array_values( $page['topFixes'] ) : array(),
			'source'         => $source,
			'audit_page'     => array(
				'title'      => isset( $page['title'] ) ? (string) $page['title'] : '',
				'category'   => isset( $page['category'] ) ? (string) $page['category'] : '',
				'word_count' => isset( $page['wordCount'] ) ? (int) $page['wordCount'] : 0,
			),
		);

		$url      = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/rewrites/preview';
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			AEOCAS_Activity_Log::log(
				'rewrite_preview',
				'error',
				array(
					'message'  => $response->get_error_message(),
					'page_url' => $page_url,
				),
				$post_id
			);
			return new WP_Error( 'aeocas_api_error', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			return self::handle_auth_failure();
		}

		if ( $status >= 400 ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( isset( $body['message'] ) ? $body['message'] : __( 'Failed to preview rewrite.', 'aeo-content-ai-studio' ) );
			AEOCAS_Activity_Log::log(
				'rewrite_preview',
				'error',
				array(
					'message'  => $message,
					'page_url' => $page_url,
				),
				$post_id
			);
			return new WP_Error( 'aeocas_rewrite_preview_error', $message );
		}

		$preview = self::normalize_rewrite_preview( $body );
		if ( ! $preview ) {
			return new WP_Error( 'aeocas_rewrite_preview_error', __( 'Unexpected rewrite preview response.', 'aeo-content-ai-studio' ) );
		}

		if ( empty( $preview['page_url'] ) ) {
			$preview['page_url'] = $page_url;
		}
		if ( empty( $preview['post_id'] ) ) {
			$preview['post_id'] = $post_id;
		}
		if ( empty( $preview['current']['title'] ) ) {
			$preview['current'] = array_merge( $preview['current'], $source );
		}
		if ( ! empty( $preview['availability'] ) && is_array( $preview['availability'] ) ) {
			set_transient( self::REWRITE_AVAILABILITY_TRANSIENT_PREFIX . $slug, $preview['availability'], self::REWRITE_AVAILABILITY_CACHE_TTL );
		}

		AEOCAS_Activity_Log::log(
			'rewrite_preview',
			'success',
			array(
				'message'  => 'Rewrite preview generated.',
				'page_url' => $page_url,
			),
			$post_id
		);

		return $preview;
	}

	/**
	 * Create a linked rewrite-review draft from optimized content.
	 *
	 * @param array $payload Draft-creation payload.
	 * @return array|WP_Error
	 */
	public static function create_rewrite_draft( $payload ) {
		$page_url = isset( $payload['page_url'] ) ? esc_url_raw( $payload['page_url'] ) : '';
		if ( empty( $page_url ) ) {
			return new WP_Error( 'aeocas_missing_page_url', __( 'Missing page URL for rewrite draft.', 'aeo-content-ai-studio' ) );
		}

		$local_item = self::get_local_content_item_for_url( $page_url );
		if ( ! $local_item || empty( $local_item['id'] ) ) {
			return new WP_Error( 'aeocas_rewrite_unavailable', __( 'This page is not mapped to an editable WordPress post.', 'aeo-content-ai-studio' ) );
		}

		$source_post_id = absint( $local_item['id'] );
		if ( ! current_user_can( 'edit_post', $source_post_id ) ) {
			return new WP_Error( 'aeocas_rewrite_forbidden', __( 'You do not have permission to create a rewrite draft for this post.', 'aeo-content-ai-studio' ) );
		}

		$availability = self::get_rewrite_availability( true );
		if ( is_wp_error( $availability ) ) {
			return $availability;
		}

		if ( (int) ( $availability['available'] ?? 0 ) < 1 ) {
			return new WP_Error( 'aeocas_no_rewrites_remaining', __( 'No rewrites remaining on this plan. Upgrade to continue.', 'aeo-content-ai-studio' ) );
		}

		$content_module = self::get_content_module();
		if ( ! $content_module ) {
			return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ) );
		}

		$draft_payload = array(
			'source_post_id' => $source_post_id,
			'post_type'      => isset( $local_item['post_type'] ) ? $local_item['post_type'] : 'post',
			'rewrite_id'     => isset( $payload['rewrite_id'] ) ? sanitize_text_field( (string) $payload['rewrite_id'] ) : '',
			'audit_stamp'    => isset( $payload['audit_stamp'] ) ? sanitize_text_field( (string) $payload['audit_stamp'] ) : '',
			'title'          => isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : '',
			'content'        => isset( $payload['content'] ) ? (string) $payload['content'] : '',
			'excerpt'        => isset( $payload['excerpt'] ) ? (string) $payload['excerpt'] : '',
		);

		$result = $content_module->create_rewrite_draft( $draft_payload );
		if ( is_wp_error( $result ) ) {
			AEOCAS_Activity_Log::log(
				'rewrite_draft',
				'error',
				array(
					'message'  => $result->get_error_message(),
					'page_url' => $page_url,
				),
				$source_post_id
			);
			return $result;
		}

		$data                                  = $result->get_data();
		$data['page_url']                      = $page_url;
		$data['active_rewrite_draft_edit_url'] = $data['edit'] ?? '';
		$data['availability']                  = self::consume_rewrite_availability_snapshot( $availability );
		AEOCAS_Activity_Log::log(
			'rewrite_draft',
			'success',
			array(
				'message'  => 'Rewrite draft created.',
				'page_url' => $page_url,
			),
			$source_post_id
		);

		return $data;
	}

	/**
	 * Apply an approved rewrite-review draft back onto the source post.
	 *
	 * @param array $payload Draft-apply payload.
	 * @return array|WP_Error
	 */
	public static function apply_rewrite_draft( $payload ) {
		$draft_post_id = isset( $payload['draft_post_id'] ) ? absint( $payload['draft_post_id'] ) : 0;
		if ( ! $draft_post_id ) {
			return new WP_Error( 'aeocas_missing_draft_post', __( 'Missing rewrite draft post ID.', 'aeo-content-ai-studio' ) );
		}

		$content_module = self::get_content_module();
		if ( ! $content_module ) {
			return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ) );
		}

		$draft_post = get_post( $draft_post_id );
		if ( ! $draft_post ) {
			return new WP_Error( 'aeocas_rewrite_draft_missing', __( 'Rewrite draft not found.', 'aeo-content-ai-studio' ) );
		}

		if ( ! current_user_can( 'edit_post', $draft_post_id ) ) {
			return new WP_Error( 'aeocas_rewrite_forbidden', __( 'You do not have permission to apply this rewrite draft.', 'aeo-content-ai-studio' ) );
		}

		$linked_source_post_id = absint( get_post_meta( $draft_post_id, AEOCAS_Content::REWRITE_SOURCE_POST_META, true ) );
		if ( ! $linked_source_post_id ) {
			return new WP_Error( 'aeocas_rewrite_source_missing', __( 'Rewrite draft is not linked to a source post.', 'aeo-content-ai-studio' ) );
		}

		if ( ! empty( $payload['source_post_id'] ) && absint( $payload['source_post_id'] ) !== $linked_source_post_id ) {
			return new WP_Error( 'aeocas_rewrite_source_mismatch', __( 'Rewrite draft does not belong to the requested source post.', 'aeo-content-ai-studio' ) );
		}

		if ( ! current_user_can( 'edit_post', $linked_source_post_id ) ) {
			return new WP_Error( 'aeocas_rewrite_forbidden', __( 'You do not have permission to update the source post for this rewrite.', 'aeo-content-ai-studio' ) );
		}

		$payload['source_post_id'] = $linked_source_post_id;

		$result = $content_module->apply_rewrite_draft( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result->get_data();
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

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
				'timeout' => 20,
			)
		);

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

		$url = trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/visibility/' . $slug . '?include=timeline';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
				'timeout' => 20,
			)
		);

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
				$audit_visibility = self::resolve_visibility_payload( $audit );
				if ( $audit_visibility ) {
					$audit_visibility_status = isset( $audit_visibility['status'] ) ? sanitize_key( $audit_visibility['status'] ) : '';
					$audit_ttl               = in_array( $audit_visibility_status, array( 'pending', 'refreshing', 'building', 'queued' ), true )
						? self::SHORT_CACHE_TTL
						: self::VISIBILITY_CACHE_TTL;

					set_transient( $transient_key, $audit_visibility, $audit_ttl );
					return $audit_visibility;
				}
			}
			return new WP_Error( 'aeocas_no_visibility', __( 'AI visibility data is not available yet. Open the admin workspace for the latest sync status.', 'aeo-content-ai-studio' ) );
		}

		if ( 200 !== $status ) {
			$message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unexpected visibility response.', 'aeo-content-ai-studio' );
			return new WP_Error( 'aeocas_api_error', $message );
		}

		$visibility = self::resolve_visibility_payload( $body );

		if ( ! $visibility ) {
			return new WP_Error( 'aeocas_no_visibility', __( 'AI visibility data is not available yet. Open the admin workspace for the latest sync status.', 'aeo-content-ai-studio' ) );
		}

		$visibility_status = isset( $visibility['status'] ) ? sanitize_key( $visibility['status'] ) : '';
		$ttl               = in_array( $visibility_status, array( 'pending', 'refreshing', 'building', 'queued' ), true )
			? self::SHORT_CACHE_TTL
			: self::VISIBILITY_CACHE_TTL;

		set_transient( $transient_key, $visibility, $ttl );

		return $visibility;
	}

	/**
	 * AJAX handler for fetching audit data.
	 */
	public static function ajax_get_audit() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$force = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$audit = self::get_audit( $force );

		if ( is_wp_error( $audit ) ) {
			wp_send_json_error(
				array(
					'message' => $audit->get_error_message(),
					'code'    => $audit->get_error_code(),
				)
			);
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

		$response = wp_remote_post(
			$url,
			array(
				'headers'  => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'     => wp_json_encode( array( 'site_url' => get_home_url() ) ),
				'timeout'  => $blocking ? 20 : 3,
				'blocking' => $blocking,
			)
		);

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

		AEOCAS_Activity_Log::log(
			'audit_dispatch',
			'success',
			array(
				'message' => 'Audit dispatched.',
				'slug'    => $body['data']['slug'] ?? null,
			)
		);
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

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
				'timeout' => 15,
			)
		);

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
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$result = self::dispatch_audit( true );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for fetching discovery data.
	 */
	public static function ajax_get_discovery() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$force     = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$discovery = self::get_discovery( $force );

		if ( is_wp_error( $discovery ) ) {
			wp_send_json_error(
				array(
					'message' => $discovery->get_error_message(),
					'code'    => $discovery->get_error_code(),
				)
			);
		}

		wp_send_json_success( $discovery );
	}

	/**
	 * AJAX handler for fetching AI visibility data.
	 */
	public static function ajax_get_visibility() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$force      = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$visibility = self::get_visibility( $force );

		if ( is_wp_error( $visibility ) ) {
			wp_send_json_error(
				array(
					'message' => $visibility->get_error_message(),
					'code'    => $visibility->get_error_code(),
				)
			);
		}

		wp_send_json_success( $visibility );
	}

	/**
	 * AJAX handler for fetching rewrite availability.
	 */
	public static function ajax_get_rewrite_availability() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$force        = ! empty( $_POST['refresh'] ) && sanitize_text_field( wp_unslash( $_POST['refresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$availability = self::get_rewrite_availability( $force );

		if ( is_wp_error( $availability ) ) {
			wp_send_json_error(
				array(
					'message' => $availability->get_error_message(),
					'code'    => $availability->get_error_code(),
				)
			);
		}

		wp_send_json_success( $availability );
	}

	/**
	 * AJAX handler for creating a rewrite checkout session.
	 */
	public static function ajax_get_rewrite_checkout_url() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$checkout = self::get_rewrite_checkout_url();

		if ( is_wp_error( $checkout ) ) {
			wp_send_json_error(
				array(
					'message' => $checkout->get_error_message(),
					'code'    => $checkout->get_error_code(),
				)
			);
		}

		wp_send_json_success( $checkout );
	}

	/**
	 * AJAX handler for generating a rewrite preview.
	 */
	public static function ajax_preview_rewrite() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$preview  = self::preview_rewrite( $page_url );

		if ( is_wp_error( $preview ) ) {
			wp_send_json_error(
				array(
					'message' => $preview->get_error_message(),
					'code'    => $preview->get_error_code(),
				)
			);
		}

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX handler for creating a rewrite-review draft.
	 */
	public static function ajax_create_rewrite_draft() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$payload = array(
			'page_url'    => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'rewrite_id'  => isset( $_POST['rewrite_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rewrite_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'audit_stamp' => isset( $_POST['audit_stamp'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_stamp'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'content'     => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'excerpt'     => isset( $_POST['excerpt'] ) ? wp_unslash( $_POST['excerpt'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		);

		$result = self::create_rewrite_draft( $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for applying a rewrite-review draft back to the live post.
	 */
	public static function ajax_apply_rewrite_draft() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		$payload = array(
			'draft_post_id'  => isset( $_POST['draft_post_id'] ) ? absint( wp_unslash( $_POST['draft_post_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'source_post_id' => isset( $_POST['source_post_id'] ) ? absint( wp_unslash( $_POST['source_post_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'rewrite_id'     => isset( $_POST['rewrite_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rewrite_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
			'audit_stamp'    => isset( $_POST['audit_stamp'] ) ? sanitize_text_field( wp_unslash( $_POST['audit_stamp'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		);

		$result = self::apply_rewrite_draft( $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for polling audit status.
	 */
	public static function ajax_audit_status() {
		if ( ! current_user_can( 'edit_posts' ) ) {
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
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_audit_nonce', 'nonce' );

		wp_send_json_success(
			array(
				'items' => self::get_local_content_index(),
			)
		);
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
		add_action( 'wp_ajax_aeocas_get_rewrite_availability', array( __CLASS__, 'ajax_get_rewrite_availability' ) );
		add_action( 'wp_ajax_aeocas_get_rewrite_checkout_url', array( __CLASS__, 'ajax_get_rewrite_checkout_url' ) );
		add_action( 'wp_ajax_aeocas_preview_rewrite', array( __CLASS__, 'ajax_preview_rewrite' ) );
		add_action( 'wp_ajax_aeocas_create_rewrite_draft', array( __CLASS__, 'ajax_create_rewrite_draft' ) );
		add_action( 'wp_ajax_aeocas_apply_rewrite_draft', array( __CLASS__, 'ajax_apply_rewrite_draft' ) );
		add_action( 'wp_ajax_aeocas_get_local_content_index', array( __CLASS__, 'ajax_get_local_content_index' ) );
	}
}
