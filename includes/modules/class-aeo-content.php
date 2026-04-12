<?php
/**
 * Content publishing module.
 *
 * Creates or updates WordPress posts with full AEO optimization:
 * - Post content, title, slug, excerpt, categories
 * - Automatically sets FAQ schema from content
 * - Sets author schema, speakable selectors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Content {

	const REWRITE_SOURCE_POST_META     = '_aeocas_rewrite_source_post_id';
	const REWRITE_ID_META              = '_aeocas_rewrite_id';
	const REWRITE_AUDIT_STAMP_META     = '_aeocas_rewrite_audit_stamp';
	const REWRITE_STATUS_META          = '_aeocas_rewrite_status';
	const ACTIVE_REWRITE_DRAFT_META    = '_aeocas_active_rewrite_draft_id';
	const REWRITE_APPLIED_TO_POST_META = '_aeocas_rewrite_applied_to_post_id';
	const REMOTE_MEDIA_MAX_BYTES       = 10485760;

	/** @var array<string, array<string, mixed>> */
	private $sideloaded_media_cache = array();

	public function __construct() {
		// No hooks needed - called via REST API.
	}

	/**
	 * Get the allowlisted post types this plugin may write to.
	 *
	 * @return string[]
	 */
	public static function get_allowed_post_types() {
		$types = apply_filters( 'aeocas_allowed_post_types', array( 'post', 'page' ) );
		if ( ! is_array( $types ) ) {
			return array( 'post', 'page' );
		}

		$types = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $types )
				)
			)
		);

		return ! empty( $types ) ? $types : array( 'post', 'page' );
	}

	/**
	 * Create or update a post with AEO optimizations.
	 *
	 * @param array $payload {
	 *     @type int    $post_id    Optional. Update existing post.
	 *     @type string $title      Post title.
	 *     @type string $content    HTML content.
	 *     @type string $slug       URL slug.
	 *     @type string $excerpt    Post excerpt.
	 *     @type string $status     Post status (publish, draft, pending).
	 *     @type array  $categories Category names (will be created if missing).
	 *     @type array  $tags       Tag names.
	 *     @type array  $faq        Array of {question, answer} pairs.
	 *     @type array  $author     Person schema override.
	 *     @type array  $speakable  CSS selectors for speakable content.
	 *     @type string $canonical  Canonical URL override.
	 *     @type string $featured_image_url  URL to download and set as featured image.
	 * }
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_or_update_post( $payload ) {
		$post_id       = isset( $payload['post_id'] ) ? intval( $payload['post_id'] ) : 0;
		$existing_post = $post_id ? get_post( $post_id ) : null;
		$post_type     = $this->resolve_post_type( $payload, $existing_post );

		if ( is_wp_error( $post_type ) ) {
			return $post_type;
		}

		$post_data = array(
			'post_type' => $post_type,
		);

		// Only set status if explicitly provided; on update, preserve existing status.
		if ( isset( $payload['status'] ) && in_array( $payload['status'], array( 'publish', 'draft', 'pending' ), true ) ) {
			$post_data['post_status'] = $payload['status'];
		} elseif ( ! $post_id || ! $existing_post ) {
			// New post defaults to draft.
			$post_data['post_status'] = 'draft';
		}

		if ( isset( $payload['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $payload['title'] );
		}
		if ( isset( $payload['content'] ) ) {
			$post_data['post_content'] = $this->sanitize_post_content( (string) $payload['content'] );
			// Download external images to Media Library before saving.
			try {
				$post_data['post_content'] = $this->download_content_images( $post_data['post_content'] );
			} catch ( \Throwable $e ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[AEO] download_content_images failed: ' . $e->getMessage() );
			}
		}
		if ( isset( $payload['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $payload['slug'] );
		}
		if ( isset( $payload['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $payload['excerpt'] );
		}

		// Handle categories.
		if ( isset( $payload['categories'] ) && is_array( $payload['categories'] ) ) {
			$cat_ids = array();
			foreach ( $payload['categories'] as $name ) {
				$term = get_term_by( 'name', $name, 'category' );
				if ( $term ) {
					$cat_ids[] = $term->term_id;
				} else {
					$result = wp_insert_term( $name, 'category' );
					if ( ! is_wp_error( $result ) ) {
						$cat_ids[] = $result['term_id'];
					}
				}
			}
			if ( ! empty( $cat_ids ) ) {
				$post_data['post_category'] = $cat_ids;
			}
		}

		// Handle tags.
		if ( isset( $payload['tags'] ) && is_array( $payload['tags'] ) ) {
			$post_data['tags_input'] = array_map( 'sanitize_text_field', $payload['tags'] );
		}

		// Create or update.
		if ( $post_id && $existing_post ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = $result;

		// Set AEO post meta.
		if ( isset( $payload['faq'] ) && is_array( $payload['faq'] ) ) {
			update_post_meta( $post_id, '_aeocas_faq_schema', $this->sanitize_schema( $payload['faq'] ) );
		} elseif ( ! empty( $post_data['post_content'] ) ) {
			// Auto-extract FAQ from content only when content was provided.
			try {
				$this->auto_extract_faq( $post_id, $post_data['post_content'] );
			} catch ( \Throwable $e ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[AEO] auto_extract_faq failed: ' . $e->getMessage() );
			}
		}

		if ( isset( $payload['author'] ) ) {
			update_post_meta( $post_id, '_aeocas_author_schema', $this->sanitize_schema( $payload['author'] ) );
		}

		if ( isset( $payload['speakable'] ) ) {
			update_post_meta( $post_id, '_aeocas_speakable', array_map( 'sanitize_text_field', (array) $payload['speakable'] ) );
		}

		if ( isset( $payload['canonical'] ) ) {
			update_post_meta( $post_id, '_aeocas_canonical_url', esc_url_raw( $payload['canonical'] ) );
		}

		// Download and set featured image.
		if ( ! empty( $payload['featured_image_url'] ) ) {
			$image_url = esc_url_raw( $payload['featured_image_url'], array( 'http', 'https' ) );
			if ( $image_url ) {
				try {
					$this->set_featured_image( $post_id, $image_url );
				} catch ( \Throwable $e ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[AEO] set_featured_image failed: ' . $e->getMessage() );
				}
			}
		}

		return rest_ensure_response(
			array(
				'ok'      => true,
				'post_id' => $post_id,
				'url'     => get_permalink( $post_id ),
				'edit'    => get_edit_post_link( $post_id, 'raw' ),
			)
		);
	}

	/**
	 * Create a linked rewrite-review draft for an existing source post.
	 *
	 * @param array $payload Rewrite payload with source_post_id and optimized fields.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_rewrite_draft( $payload ) {
		$source_post_id = isset( $payload['source_post_id'] ) ? absint( $payload['source_post_id'] ) : 0;
		$source_post    = $source_post_id ? get_post( $source_post_id ) : null;

		if ( ! $source_post ) {
			return new WP_Error( 'aeocas_rewrite_source_missing', __( 'Source post not found for rewrite draft.', 'aeo-content-ai-studio' ) );
		}

		$draft_post_type = $this->resolve_post_type( $payload, $source_post );
		if ( is_wp_error( $draft_post_type ) ) {
			return $draft_post_type;
		}

		$draft_payload = array(
			'post_type' => $draft_post_type,
			'status'    => 'draft',
			'title'     => isset( $payload['title'] ) ? $payload['title'] : $this->build_rewrite_draft_title( $source_post ),
			'content'   => isset( $payload['content'] ) ? $payload['content'] : ( isset( $source_post->post_content ) ? $source_post->post_content : '' ),
			'excerpt'   => array_key_exists( 'excerpt', $payload ) ? $payload['excerpt'] : ( isset( $source_post->post_excerpt ) ? $source_post->post_excerpt : '' ),
			'slug'      => isset( $payload['slug'] ) ? $payload['slug'] : sanitize_title( ( isset( $source_post->post_name ) ? $source_post->post_name : $source_post_id ) . '-rewrite-review' ),
		);

		foreach ( array( 'categories', 'tags', 'faq', 'author', 'speakable', 'canonical', 'featured_image_url' ) as $key ) {
			if ( isset( $payload[ $key ] ) ) {
				$draft_payload[ $key ] = $payload[ $key ];
			}
		}

		$result = $this->create_or_update_post( $draft_payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data     = $result->get_data();
		$draft_id = isset( $data['post_id'] ) ? absint( $data['post_id'] ) : 0;
		if ( ! $draft_id ) {
			return new WP_Error( 'aeocas_rewrite_draft_failed', __( 'Rewrite draft could not be created.', 'aeo-content-ai-studio' ) );
		}

		$rewrite_id  = isset( $payload['rewrite_id'] ) ? sanitize_text_field( $payload['rewrite_id'] ) : '';
		$audit_stamp = isset( $payload['audit_stamp'] ) ? sanitize_text_field( $payload['audit_stamp'] ) : '';

		$this->store_rewrite_meta(
			$draft_id,
			array(
				self::REWRITE_SOURCE_POST_META => $source_post_id,
				self::REWRITE_ID_META          => $rewrite_id,
				self::REWRITE_AUDIT_STAMP_META => $audit_stamp,
				self::REWRITE_STATUS_META      => 'draft_ready',
			)
		);

		$this->store_rewrite_meta(
			$source_post_id,
			array(
				self::REWRITE_ID_META           => $rewrite_id,
				self::REWRITE_AUDIT_STAMP_META  => $audit_stamp,
				self::REWRITE_STATUS_META       => 'draft_ready',
				self::ACTIVE_REWRITE_DRAFT_META => $draft_id,
			)
		);

		return $this->append_response_data(
			$result,
			array(
				'draft_post_id'   => $draft_id,
				'source_post_id'  => $source_post_id,
				'rewrite_id'      => $rewrite_id,
				'rewrite_status'  => 'draft_ready',
				'source_edit_url' => get_edit_post_link( $source_post_id, 'raw' ),
			)
		);
	}

	/**
	 * Apply a reviewed rewrite draft back onto the original source post.
	 *
	 * @param array $payload Rewrite-apply payload containing draft_post_id.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_rewrite_draft( $payload ) {
		$draft_post_id = isset( $payload['draft_post_id'] ) ? absint( $payload['draft_post_id'] ) : 0;
		$draft_post    = $draft_post_id ? get_post( $draft_post_id ) : null;

		if ( ! $draft_post ) {
			return new WP_Error( 'aeocas_rewrite_draft_missing', __( 'Rewrite draft not found.', 'aeo-content-ai-studio' ) );
		}

		$linked_source_post_id = absint( get_post_meta( $draft_post_id, self::REWRITE_SOURCE_POST_META, true ) );
		if ( ! $linked_source_post_id ) {
			return new WP_Error( 'aeocas_rewrite_source_missing', __( 'Rewrite draft is not linked to a source post.', 'aeo-content-ai-studio' ) );
		}

		if ( ! empty( $payload['source_post_id'] ) && absint( $payload['source_post_id'] ) !== $linked_source_post_id ) {
			return new WP_Error( 'aeocas_rewrite_source_mismatch', __( 'Rewrite draft does not belong to the requested source post.', 'aeo-content-ai-studio' ) );
		}

		$source_post_id = $linked_source_post_id;
		$source_post    = $source_post_id ? get_post( $source_post_id ) : null;

		if ( ! $source_post ) {
			return new WP_Error( 'aeocas_rewrite_source_missing', __( 'Source post not found for rewrite apply.', 'aeo-content-ai-studio' ) );
		}

		$apply_post_type = $this->resolve_post_type( $payload, $source_post );
		if ( is_wp_error( $apply_post_type ) ) {
			return $apply_post_type;
		}

		$apply_payload = array(
			'post_id'   => $source_post_id,
			'post_type' => $apply_post_type,
			'title'     => isset( $payload['title'] ) ? $payload['title'] : ( isset( $draft_post->post_title ) ? $draft_post->post_title : '' ),
			'content'   => isset( $payload['content'] ) ? $payload['content'] : ( isset( $draft_post->post_content ) ? $draft_post->post_content : '' ),
			'excerpt'   => array_key_exists( 'excerpt', $payload ) ? $payload['excerpt'] : ( isset( $draft_post->post_excerpt ) ? $draft_post->post_excerpt : '' ),
		);

		foreach ( array( 'status', 'categories', 'tags', 'faq', 'author', 'speakable', 'canonical', 'featured_image_url' ) as $key ) {
			if ( isset( $payload[ $key ] ) ) {
				$apply_payload[ $key ] = $payload[ $key ];
			}
		}

		$result = $this->create_or_update_post( $apply_payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$rewrite_id  = isset( $payload['rewrite_id'] ) ? sanitize_text_field( $payload['rewrite_id'] ) : sanitize_text_field( (string) get_post_meta( $draft_post_id, self::REWRITE_ID_META, true ) );
		$audit_stamp = isset( $payload['audit_stamp'] ) ? sanitize_text_field( $payload['audit_stamp'] ) : sanitize_text_field( (string) get_post_meta( $draft_post_id, self::REWRITE_AUDIT_STAMP_META, true ) );

		$this->store_rewrite_meta(
			$draft_post_id,
			array(
				self::REWRITE_STATUS_META          => 'applied',
				self::REWRITE_APPLIED_TO_POST_META => $source_post_id,
			)
		);

		$this->store_rewrite_meta(
			$source_post_id,
			array(
				self::REWRITE_ID_META          => $rewrite_id,
				self::REWRITE_AUDIT_STAMP_META => $audit_stamp,
				self::REWRITE_STATUS_META      => 'applied',
			)
		);
		delete_post_meta( $source_post_id, self::ACTIVE_REWRITE_DRAFT_META );

		return $this->append_response_data(
			$result,
			array(
				'draft_post_id'  => $draft_post_id,
				'source_post_id' => $source_post_id,
				'rewrite_id'     => $rewrite_id,
				'rewrite_status' => 'applied',
			)
		);
	}

	/**
	 * Recursively sanitize a schema array for safe storage.
	 *
	 * @param mixed $data Input data.
	 * @return mixed Sanitized data.
	 */
	private function sanitize_schema( $data ) {
		if ( is_array( $data ) ) {
			$clean = array();
			foreach ( $data as $key => $value ) {
				$clean[ sanitize_text_field( $key ) ] = $this->sanitize_schema( $value );
			}
			return $clean;
		}
		if ( is_string( $data ) ) {
			if ( filter_var( $data, FILTER_VALIDATE_URL ) ) {
				return esc_url_raw( $data );
			}
			return sanitize_text_field( $data );
		}
		if ( is_bool( $data ) || is_int( $data ) || is_float( $data ) ) {
			return $data;
		}
		return '';
	}

	/**
	 * Resolve the post type for creates/updates while preserving existing types.
	 *
	 * @param array       $payload       Incoming payload.
	 * @param object|null $existing_post Existing WP post object when updating.
	 * @return string
	 */
	private function resolve_post_type( $payload, $existing_post = null ) {
		$allowed_post_types = self::get_allowed_post_types();

		if ( ! empty( $payload['post_type'] ) ) {
			$post_type = sanitize_key( $payload['post_type'] );
		} elseif ( $existing_post && ! empty( $existing_post->post_type ) ) {
			$post_type = sanitize_key( $existing_post->post_type );
		} else {
			$post_type = 'post';
		}

		if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
			return new WP_Error(
				'aeocas_unsupported_post_type',
				__( 'This post type is not enabled for AEO Content writes.', 'aeo-content-ai-studio' )
			);
		}

		return $post_type;
	}

	/**
	 * Build a stable review-draft title from the source post.
	 *
	 * @param object $source_post Source post object.
	 * @return string
	 */
	private function build_rewrite_draft_title( $source_post ) {
		$base = isset( $source_post->post_title ) && '' !== $source_post->post_title ? $source_post->post_title : __( 'Rewrite Review', 'aeo-content-ai-studio' );
		return $base . ' (Rewrite Review)';
	}

	/**
	 * Append extra data onto a REST response payload.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param array            $extra    Extra fields.
	 * @return WP_REST_Response
	 */
	private function append_response_data( $response, $extra ) {
		$data = $response->get_data();
		return rest_ensure_response( array_merge( $data, $extra ) );
	}

	/**
	 * Persist non-empty rewrite metadata on a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Key/value metadata pairs.
	 * @return void
	 */
	private function store_rewrite_meta( $post_id, $meta ) {
		foreach ( $meta as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Sanitize post content while preserving Gutenberg block comments.
	 *
	 * WordPress block markup relies on HTML comments like `<!-- wp:paragraph -->`.
	 * `wp_kses_post()` strips those comments, so we temporarily protect them,
	 * sanitize the remaining HTML, and then restore the original block tokens.
	 *
	 * @param string $content Raw content.
	 * @return string
	 */
	private function sanitize_post_content( $content ) {
		if ( '' === $content ) {
			return '';
		}

		$protected = array();
		$content   = preg_replace_callback(
			'/<!--\s*\/?wp:[\s\S]*?-->/',
			static function ( $matches ) use ( &$protected ) {
				$token               = '__AEOCAS_BLOCK_COMMENT_' . count( $protected ) . '__';
				$protected[ $token ] = $matches[0];
				return $token;
			},
			$content
		);

		$sanitized = wp_kses_post( $content );

		if ( empty( $protected ) ) {
			return $sanitized;
		}

		return strtr( $sanitized, $protected );
	}

	/**
	 * Auto-extract FAQ pairs from HTML content.
	 * Looks for Rank Math FAQ blocks or generic H2/H3 FAQ patterns.
	 */
	private function auto_extract_faq( $post_id, $content ) {
		if ( empty( $content ) ) {
			return;
		}

		$pairs = array();

		// Pattern 1: Rank Math FAQ block.
		if ( false !== strpos( $content, 'rank-math-faq' ) ) {
			$pattern = '/<h3[^>]*class\s*=\s*["\']rank-math-question[^"\']*["\'][^>]*>([\s\S]*?)<\/h3>\s*<div[^>]*class\s*=\s*["\']rank-math-answer[^"\']*["\'][^>]*>([\s\S]*?)<\/div>/i';
			if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$pairs[] = array(
						'question' => wp_strip_all_tags( $match[1] ),
						'answer'   => wp_strip_all_tags( $match[2] ),
					);
				}
			}
		}

		// Pattern 2: Generic FAQ heading + Q&A pairs.
		if ( empty( $pairs ) ) {
			$faq_pattern = '/<h2[^>]*>[\s\S]*?(?:FAQ|Frequently\s+Asked\s+Questions)[\s\S]*?<\/h2>/i';
			if ( preg_match( $faq_pattern, $content, $faq_match, PREG_OFFSET_CAPTURE ) ) {
				$faq_section = substr( $content, $faq_match[0][1] );
				// Find next H2 to limit scope.
				$next_h2 = strpos( $faq_section, '<h2', strlen( $faq_match[0][0] ) );
				if ( $next_h2 ) {
					$faq_section = substr( $faq_section, 0, $next_h2 );
				}
				// Extract H3 + next paragraph.
				$h3_pattern = '/<h3[^>]*>([\s\S]*?)<\/h3>\s*(?:<[^h][\s\S]*?(?=<h3|$))/i';
				if ( preg_match_all( $h3_pattern, $faq_section, $h3_matches, PREG_SET_ORDER ) ) {
					foreach ( $h3_matches as $h3m ) {
						$question = wp_strip_all_tags( $h3m[1] );
						// Get text between this H3 and the next H3 or end.
						$answer_start = strpos( $faq_section, $h3m[0] ) + strlen( $h3m[0] );
						$answer_end   = strpos( $faq_section, '<h3', $answer_start );
						$answer_html  = $answer_end
							? substr( $faq_section, $answer_start, $answer_end - $answer_start )
							: substr( $faq_section, $answer_start );
						$answer       = trim( wp_strip_all_tags( $answer_html ) );

						if ( $question && $answer ) {
							$pairs[] = array(
								'question' => $question,
								'answer'   => $answer,
							);
						}
					}
				}
			}
		}

		if ( ! empty( $pairs ) ) {
			update_post_meta( $post_id, '_aeocas_faq_schema', $pairs );
		}
	}

	/**
	 * Download external images in HTML content to the Media Library.
	 *
	 * Finds all <img> tags with external src URLs, downloads each image,
	 * and replaces the src with the local WordPress URL.
	 *
	 * @param string $content HTML content.
	 * @return string Content with localized image URLs.
	 */
	private function download_content_images( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

		// Find all img tags with src attribute.
		if ( ! preg_match_all( '/<img\s[^>]*src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $content, $matches, PREG_SET_ORDER ) ) {
			return $content;
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		foreach ( $matches as $match ) {
			$original_url = $match[1];

			// Only process http/https URLs.
			if ( 0 !== strpos( $original_url, 'http://' ) && 0 !== strpos( $original_url, 'https://' ) ) {
				continue;
			}

			// Skip images already on this WordPress site.
			$img_host = wp_parse_url( $original_url, PHP_URL_HOST );
			if ( $img_host === $site_host ) {
				continue;
			}

			// Download to Media Library (without attaching to a post yet).
			$local_url = $this->sideload_remote_image( $original_url, 0, 'src' );
			if ( is_wp_error( $local_url ) ) {
				continue;
			}

			// Replace the external URL with the local one.
			$content = str_replace( $original_url, $local_url, $content );
		}

		return $content;
	}

	/**
	 * Download an image URL and set as featured image.
	 */
	private function set_featured_image( $post_id, $url ) {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$img_host  = wp_parse_url( $url, PHP_URL_HOST );

		if ( $img_host && $site_host && strtolower( (string) $img_host ) === strtolower( (string) $site_host ) ) {
			$attachment_id = attachment_url_to_postid( $url );
		} else {
			$attachment_id = $this->sideload_remote_image( $url, $post_id, 'id' );
		}

		if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Validate and sideload a remote image, reusing prior downloads when possible.
	 *
	 * @param string $url     Remote image URL.
	 * @param int    $post_id Attachment parent.
	 * @param string $return_type media_sideload_image return type.
	 * @return string|int|WP_Error
	 */
	private function sideload_remote_image( $url, $post_id, $return_type ) {
		$url = esc_url_raw( $url );
		if ( isset( $this->sideloaded_media_cache[ $url ][ $return_type ] ) ) {
			return $this->sideloaded_media_cache[ $url ][ $return_type ];
		}

		$validation = $this->validate_remote_image_url( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$existing_attachment_id = $this->find_existing_sideloaded_attachment_id( $url );
		if ( $existing_attachment_id ) {
			$result = 'id' === $return_type ? $existing_attachment_id : wp_get_attachment_url( $existing_attachment_id );
			$this->cache_sideloaded_media_result( $url, $existing_attachment_id, $result );
			return $result;
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$result = media_sideload_image( $url, $post_id, '', $return_type );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$attachment_id = 'id' === $return_type ? absint( $result ) : attachment_url_to_postid( (string) $result );
		if ( $attachment_id ) {
			update_post_meta( $attachment_id, '_aeocas_source_image_url', $url );
		}

		$this->cache_sideloaded_media_result( $url, $attachment_id, $result );

		return $result;
	}

	/**
	 * Cache a sideloaded-media result for the current request.
	 *
	 * @param string    $url           Source image URL.
	 * @param int       $attachment_id Attachment ID.
	 * @param string|int $result       Result returned to caller.
	 * @return void
	 */
	private function cache_sideloaded_media_result( $url, $attachment_id, $result ) {
		$src = is_int( $result ) ? '' : (string) $result;
		if ( '' === $src && $attachment_id ) {
			$src = (string) wp_get_attachment_url( $attachment_id );
		}

		$this->sideloaded_media_cache[ $url ] = array(
			'id'  => $attachment_id,
			'src' => $src,
		);
	}

	/**
	 * Find an already-downloaded attachment for a remote source URL.
	 *
	 * @param string $url Source URL.
	 * @return int
	 */
	private function find_existing_sideloaded_attachment_id( $url ) {
		$cached_id = isset( $this->sideloaded_media_cache[ $url ]['id'] ) ? absint( $this->sideloaded_media_cache[ $url ]['id'] ) : 0;
		if ( $cached_id ) {
			return $cached_id;
		}

		$local_attachment_id = attachment_url_to_postid( $url );
		if ( $local_attachment_id ) {
			return absint( $local_attachment_id );
		}

		$attachment_ids = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_aeocas_source_image_url',
				'meta_value'             => $url,
			)
		);

		return ! empty( $attachment_ids[0] ) ? absint( $attachment_ids[0] ) : 0;
	}

	/**
	 * Validate a remote image URL before sideloading it.
	 *
	 * @param string $url Remote image URL.
	 * @return true|WP_Error
	 */
	private function validate_remote_image_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'aeocas_invalid_media_url', __( 'Remote image URL is invalid.', 'aeo-content-ai-studio' ) );
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'aeocas_invalid_media_scheme', __( 'Remote image URL must use HTTP or HTTPS.', 'aeo-content-ai-studio' ) );
		}

		$host = strtolower( (string) $parts['host'] );
		if ( $this->is_remote_media_host_blocked( $host ) ) {
			return new WP_Error( 'aeocas_blocked_media_host', __( 'Remote image host is not allowed.', 'aeo-content-ai-studio' ) );
		}

		$response = function_exists( 'wp_safe_remote_head' )
			? wp_safe_remote_head(
				$url,
				array(
					'timeout'            => 10,
					'redirection'        => 3,
					'reject_unsafe_urls' => true,
				)
			)
			: wp_remote_get(
				$url,
				array(
					'method'      => 'HEAD',
					'timeout'     => 10,
					'redirection' => 3,
				)
			);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 400 ) {
			return new WP_Error( 'aeocas_invalid_media_response', __( 'Remote image could not be validated.', 'aeo-content-ai-studio' ) );
		}

		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		if ( '' !== $content_type && 0 !== stripos( $content_type, 'image/' ) ) {
			return new WP_Error( 'aeocas_invalid_media_type', __( 'Remote URL did not return an image.', 'aeo-content-ai-studio' ) );
		}

		$content_length = wp_remote_retrieve_header( $response, 'content-length' );
		if ( is_numeric( $content_length ) && (int) $content_length > self::REMOTE_MEDIA_MAX_BYTES ) {
			return new WP_Error( 'aeocas_media_too_large', __( 'Remote image exceeds the allowed size limit.', 'aeo-content-ai-studio' ) );
		}

		return true;
	}

	/**
	 * Determine whether a remote media host should be blocked.
	 *
	 * @param string $host Remote host.
	 * @return bool
	 */
	private function is_remote_media_host_blocked( $host ) {
		$allowed_hosts = apply_filters( 'aeocas_allowed_remote_media_hosts', array() );
		if ( is_array( $allowed_hosts ) && ! empty( $allowed_hosts ) ) {
			$normalized_allowed_hosts = array_map(
				static function ( $allowed_host ) {
					return strtolower( sanitize_text_field( (string) $allowed_host ) );
				},
				$allowed_hosts
			);

			if ( ! in_array( $host, $normalized_allowed_hosts, true ) ) {
				return true;
			}
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		if ( function_exists( 'gethostbynamel' ) ) {
			$resolved_ips = gethostbynamel( $host );
			if ( is_array( $resolved_ips ) ) {
				foreach ( $resolved_ips as $resolved_ip ) {
					if ( ! filter_var( $resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
