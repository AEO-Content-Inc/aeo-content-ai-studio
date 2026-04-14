<?php
/**
 * REST API endpoints under /wp-json/aeo/v1/ with /wp-json/aeocas/v1/ aliases.
 *
 * All mutating endpoints require authenticated platform requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Rest_Api {

	/** @var AEOCAS_Plugin */
	private $plugin;

	const REST_NAMESPACE        = 'aeo/v1';
	const LEGACY_REST_NAMESPACE = 'aeocas/v1';

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public static function get_rest_namespaces() {
		return array(
			self::REST_NAMESPACE,
			self::LEGACY_REST_NAMESPACE,
		);
	}

	private function register_route( $route, $args ) {
		foreach ( self::get_rest_namespaces() as $namespace ) {
			register_rest_route( $namespace, $route, $args );
		}
	}

	public function register_routes() {
		// Public health check.
		$this->register_route(
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_status' ),
				'permission_callback' => array( $this, 'allow_public_request' ),
				'args'                => array(),
			)
		);

		// Unified command dispatch.
		$this->register_route(
			'/command',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_command' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'command' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
					'payload' => array(
						'required' => false,
						'default'  => array(),
					),
				),
			)
		);

		// Activity log (authenticated platform requests).
		$this->register_route(
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'AEOCAS_Activity_Log', 'handle_rest_logs' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'page'      => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'required'          => false,
						'default'           => 25,
						'sanitize_callback' => 'absint',
					),
					'command'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_from' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Posts list (read).
		$this->register_route(
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_posts' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'page'      => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'  => array(
						'required'          => false,
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'status'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'search'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_type' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
					'orderby'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order'     => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Single post (read).
		$this->register_route(
			'/posts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_post' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Publish endpoint.
		$this->register_route(
			'/publish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_publish' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(),
			)
		);

		// Categories list.
		$this->register_route(
			'/categories',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_categories' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'search'     => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'hide_empty' => array(
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'parent'     => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
					'orderby'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order'      => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Tags list.
		$this->register_route(
			'/tags',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_get_tags' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'search'     => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'hide_empty' => array(
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'orderby'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order'      => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission callback - verify the platform credential.
	 */
	public function check_auth( $request ) {
		return AEOCAS_Auth::verify_request( $request );
	}

	public function allow_public_request() {
		return true;
	}

	// ─── Status ───────────────────────────────────────────────

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- REST callback signature includes the request argument for consistency.
	public function handle_status( $request ) {
		$connected = ! empty( get_option( 'aeocas_site_token', '' ) ) && get_option( 'aeocas_connection_verified', false );

		return rest_ensure_response(
			array(
				'ok'             => true,
				'version'        => AEOCAS_VERSION,
				'plugin_version' => AEOCAS_VERSION,
				'connected'      => (bool) $connected,
				'namespace'      => self::REST_NAMESPACE,
				'namespaces'     => self::get_rest_namespaces(),
				'features'       => $this->plugin->get_enabled_features(),
				'site_url'       => get_site_url(),
				'home_url'       => get_home_url(),
			)
		);
	}

	// ─── Unified Command Dispatch ─────────────────────────────

	public function handle_command( $request ) {
		$json_params = $request->get_json_params();
		$command     = $request->get_param( 'command' );
		$payload     = $request->get_param( 'payload' );

		if ( empty( $command ) && ! empty( $json_params['command'] ) ) {
			$command = $json_params['command'];
		}

		if ( null === $payload && isset( $json_params['payload'] ) ) {
			$payload = $json_params['payload'];
		}

		if ( empty( $command ) ) {
			return new WP_Error( 'aeocas_missing_command', __( 'Missing command parameter.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
		}

		return $this->get_command_runner()->run( $command, $payload );
	}

	// ─── Endpoint Handlers ──────────────────────────────────

	public function handle_publish( $request ) {
		return $this->get_command_runner()->run( 'publish_post', $request->get_json_params() );
	}

	// ─── Posts Read Endpoints ────────────────────────────────

	/**
	 * GET /aeo/v1/posts — paginated list of posts.
	 *
	 * Params: page, per_page, status, search, post_type, orderby, order.
	 */
	public function handle_get_posts( $request ) {
		$module = $this->plugin->get_module( 'content' );
		if ( ! $module ) {
			return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
		}

		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$status    = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$search    = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$post_type = sanitize_text_field( (string) $request->get_param( 'post_type' ) );
		$orderby   = sanitize_text_field( (string) $request->get_param( 'orderby' ) );

		if ( '' === $status ) {
			$status = 'publish';
		}

		if ( '' === $post_type ) {
			$post_type = 'post';
		}

		if ( '' === $orderby ) {
			$orderby = 'date';
		}

		$allowed_types = $this->get_allowed_post_types();
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			$post_type = 'post';
		}
		$order = sanitize_text_field( (string) $request->get_param( 'order' ) );
		if ( '' === $order ) {
			$order = 'DESC';
		}

		$allowed_statuses = array( 'publish', 'draft', 'pending', 'future', 'private', 'any' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'publish';
		}

		$allowed_orderby = array( 'date', 'modified', 'title', 'ID' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = $this->format_post_summary( $post );
		}

		AEOCAS_Activity_Log::log(
			'get_posts',
			'success',
			array(
				'message' => "Listed {$query->found_posts} posts (page {$page}).",
				'total'   => $query->found_posts,
			)
		);

		return rest_ensure_response(
			array(
				'ok'    => true,
				'posts' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'page'  => $page,
			)
		);
	}

	/**
	 * GET /aeo/v1/posts/{id} — full post content.
	 */
	public function handle_get_post( $request ) {
		$module = $this->plugin->get_module( 'content' );
		if ( ! $module ) {
			return new WP_Error( 'aeocas_module_disabled', __( 'Content module is not enabled.', 'aeo-content-ai-studio' ), array( 'status' => 400 ) );
		}

		$post_id = (int) $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'aeocas_not_found', __( 'Post not found.', 'aeo-content-ai-studio' ), array( 'status' => 404 ) );
		}

		$data = $this->format_post_summary( $post );

		// Add full content.
		$data['content']   = $post->post_content;
		$data['author_id'] = (int) $post->post_author;

		// Author info.
		$author = get_userdata( $post->post_author );
		if ( $author ) {
			$data['author'] = array(
				'id'           => (int) $author->ID,
				'display_name' => $author->display_name,
				'email'        => $author->user_email,
			);
		}

		// Featured image.
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$data['featured_image'] = array(
				'id'  => (int) $thumb_id,
				'url' => wp_get_attachment_url( $thumb_id ),
				'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			);
		}

		// AEO meta.
		$aeocas_meta = array();
		$faq         = get_post_meta( $post_id, '_aeocas_faq_schema', true );
		if ( $faq ) {
			$aeocas_meta['faq'] = $faq;
		}
		$speakable = get_post_meta( $post_id, '_aeocas_speakable', true );
		if ( $speakable ) {
			$aeocas_meta['speakable'] = $speakable;
		}
		$canonical = get_post_meta( $post_id, '_aeocas_canonical_url', true );
		if ( $canonical ) {
			$aeocas_meta['canonical'] = $canonical;
		}
		$author_schema = get_post_meta( $post_id, '_aeocas_author_schema', true );
		if ( $author_schema ) {
			$aeocas_meta['author_schema'] = $author_schema;
		}
		if ( ! empty( $aeocas_meta ) ) {
			$data['aeocas_meta'] = $aeocas_meta;
		}

		AEOCAS_Activity_Log::log(
			'get_post',
			'success',
			array(
				'message' => "Post #{$post_id} retrieved.",
			),
			$post_id
		);

		return rest_ensure_response(
			array(
				'ok'   => true,
				'post' => $data,
			)
		);
	}

	/**
	 * Format a WP_Post into a summary array (used in list and single endpoints).
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	private function format_post_summary( $post ) {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		return array(
			'id'         => (int) $post->ID,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'status'     => $post->post_status,
			'date'       => $post->post_date_gmt,
			'modified'   => $post->post_modified_gmt,
			'excerpt'    => $post->post_excerpt ? $post->post_excerpt : wp_trim_words( $post->post_content, 40, '...' ),
			'url'        => get_permalink( $post->ID ),
			'edit_url'   => get_edit_post_link( $post->ID, 'raw' ),
			'categories' => $categories,
			'tags'       => $tags,
		);
	}

	// ─── Taxonomy Endpoints ────────────────────────────────────

	/**
	 * GET /aeo/v1/categories — all categories.
	 *
	 * Params: search, hide_empty, parent, orderby, order.
	 */
	public function handle_get_categories( $request ) {
		$hide_empty = $request->get_param( 'hide_empty' );
		$hide_empty = ( null === $hide_empty ) ? false : rest_sanitize_boolean( $hide_empty );

		$args = array(
			'taxonomy'   => 'category',
			'hide_empty' => $hide_empty,
			'orderby'    => sanitize_text_field( (string) $request->get_param( 'orderby' ) ),
			'order'      => 'ASC',
		);

		if ( '' === $args['orderby'] ) {
			$args['orderby'] = 'name';
		}

		$order = strtoupper( sanitize_text_field( (string) $request->get_param( 'order' ) ) );
		if ( 'DESC' === $order ) {
			$args['order'] = 'DESC';
		}

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$parent = $request->get_param( 'parent' );
		if ( null !== $parent ) {
			$args['parent'] = absint( $parent );
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
				'count'  => (int) $term->count,
			);
		}

		return rest_ensure_response(
			array(
				'ok'         => true,
				'categories' => $items,
				'total'      => count( $items ),
			)
		);
	}

	/**
	 * GET /aeo/v1/tags — all tags.
	 *
	 * Params: search, hide_empty, orderby, order.
	 */
	public function handle_get_tags( $request ) {
		$hide_empty = $request->get_param( 'hide_empty' );
		$hide_empty = ( null === $hide_empty ) ? false : rest_sanitize_boolean( $hide_empty );

		$args = array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => $hide_empty,
			'orderby'    => sanitize_text_field( (string) $request->get_param( 'orderby' ) ),
			'order'      => 'ASC',
		);

		if ( '' === $args['orderby'] ) {
			$args['orderby'] = 'name';
		}

		$order = strtoupper( sanitize_text_field( (string) $request->get_param( 'order' ) ) );
		if ( 'DESC' === $order ) {
			$args['order'] = 'DESC';
		}

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$items = array();
		foreach ( $terms as $term ) {
			$items[] = array(
				'id'    => (int) $term->term_id,
				'name'  => $term->name,
				'slug'  => $term->slug,
				'count' => (int) $term->count,
			);
		}

		return rest_ensure_response(
			array(
				'ok'    => true,
				'tags'  => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * Get the shared command runner.
	 *
	 * @return AEOCAS_Command_Runner
	 */
	private function get_command_runner() {
		if ( method_exists( $this->plugin, 'get_command_runner' ) ) {
			return $this->plugin->get_command_runner();
		}

		return new AEOCAS_Command_Runner( $this->plugin );
	}

	/**
	 * Get the allowlisted content post types, loading the content module class if needed.
	 *
	 * @return string[]
	 */
	private function get_allowed_post_types() {
		if ( ! class_exists( 'AEOCAS_Content' ) ) {
			$file = AEOCAS_PLUGIN_DIR . 'includes/modules/class-aeo-content.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		return class_exists( 'AEOCAS_Content' ) ? AEOCAS_Content::get_allowed_post_types() : array( 'post', 'page' );
	}
}
