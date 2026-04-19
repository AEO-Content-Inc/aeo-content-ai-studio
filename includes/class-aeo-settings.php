<?php
/**
 * Admin settings page for AEO Content AI Studio.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Settings {

	const CONNECT_NOTICE_TRANSIENT = 'aeocas_connect_notice';
	const STUDIO_CONNECT_ACTION    = 'aeocas_complete_studio_connect';
	const REVIEW_PROMPT_OPTION     = 'aeocas_review_prompt_state';
	const REVIEW_PROMPT_ACTION     = 'aeocas_review_prompt_action';
	const SUPPORT_FORUM_URL        = 'https://wordpress.org/support/plugin/aeo-content-ai-studio/';
	const DOCS_URL                 = 'https://www.aeocontent.ai/knowledge/';
	const REVIEW_URL               = 'https://wordpress.org/support/plugin/aeo-content-ai-studio/reviews/#new-post';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_post_' . self::STUDIO_CONNECT_ACTION, array( $this, 'handle_studio_connect' ) );
		add_action( 'admin_post_' . self::REVIEW_PROMPT_ACTION, array( $this, 'handle_review_prompt_action' ) );
		add_action( 'admin_post_aeocas_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_notices', array( $this, 'render_review_prompt' ) );
		add_action( 'wp_ajax_aeocas_google_connect', array( $this, 'ajax_google_connect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AEOCAS_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu_tab' ) );
	}

	/**
	 * Add Settings link to the plugins list page.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=aeocas-audit-report&tab=connect' ) ) . '">' . esc_html__( 'Settings', 'aeo-content-ai-studio' ) . '</a>';
		$support_link  = '<a href="' . esc_url( self::get_support_forum_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Support', 'aeo-content-ai-studio' ) . '</a>';
		array_unshift( $links, $support_link );
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add documentation links to the plugin row meta on the plugins list page.
	 *
	 * @param array  $links Existing plugin row meta links.
	 * @param string $file  Plugin file basename.
	 * @return array
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( plugin_basename( AEOCAS_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( self::get_docs_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Docs', 'aeo-content-ai-studio' ) . '</a>';
		$links[] = '<a href="' . esc_url( self::get_support_forum_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Support Forum', 'aeo-content-ai-studio' ) . '</a>';

		return $links;
	}

	public function add_menu() {
		$cap      = AEOCAS_Capabilities::view_reports_capability();
		$icon     = self::get_menu_icon_data_uri();
		$base     = 'aeocas-audit-report';
		$base_url = 'admin.php?page=' . $base;

		add_menu_page(
			__( 'AEO Content AI Studio', 'aeo-content-ai-studio' ),
			__( 'AEO Content', 'aeo-content-ai-studio' ),
			$cap,
			$base,
			array( $this, 'render_audit_report' ),
			$icon,
			30
		);

		// Rename the auto-generated first submenu entry from "AEO Content"
		// to "Overview" so it serves as the dashboard landing page.
		add_submenu_page(
			$base,
			__( 'Overview - AEO Content', 'aeo-content-ai-studio' ),
			__( 'Overview', 'aeo-content-ai-studio' ),
			$cap,
			$base,
			array( $this, 'render_audit_report' )
		);

		// Additional submenu items link directly to tab query args.
		global $submenu;

		$tabs = array(
			array( 'Site Audit', 'scoreboard' ),
			array( 'Pages Audit', 'site-audit' ),
			array( 'Opportunities', 'opportunities' ),
			array( 'Rewrites', 'rewrite' ),
			array( 'AI Visibility', 'visibility-overview' ),
		);

		foreach ( $tabs as $item ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress submenu registration uses the global $submenu array.
			$submenu[ $base ][] = array(
				$item[0],
				$cap,
				esc_url( admin_url( $base_url . '&tab=' . $item[1] ) ),
			);
		}
	}

	/**
	 * Highlight the correct submenu item based on the active tab.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @return string|null
	 */
	public function highlight_submenu_tab( $submenu_file ) {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_aeocas-audit-report' !== $screen->id ) {
			return $submenu_file;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only menu highlight.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( '' === $tab ) {
			return $submenu_file;
		}

		return esc_url( admin_url( 'admin.php?page=aeocas-audit-report&tab=' . $tab ) );
	}

	/**
	 * Return the site favicon as an inline admin menu icon.
	 *
	 * @return string
	 */
	private static function get_menu_icon_data_uri() {
		$svg_path = AEOCAS_PLUGIN_DIR . 'admin/images/icon.svg';
		$svg      = '';

		if ( file_exists( $svg_path ) ) {
			$svg = file_get_contents( $svg_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		if ( ! is_string( $svg ) || '' === trim( $svg ) ) {
			$svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
  <rect width="32" height="32" rx="8" fill="#121313"/>
  <circle cx="16" cy="15.93" r="1.8" fill="#A03EE6"/>
  <circle cx="16" cy="15.93" r="4.49" stroke="#D9D9D9" stroke-width="1" opacity="0.4"/>
  <circle cx="16" cy="15.93" r="7.71" stroke="#FFFFFF" stroke-width="1" opacity="0.6"/>
  <circle cx="16" cy="15.93" r="11.23" stroke="#FFFFFF" stroke-width="1" opacity="0.8"/>
  <circle cx="22.08" cy="10.81" r="1.5" fill="#3EE6B5" stroke="#121313" stroke-width="0.5"/>
  <circle cx="19.21" cy="26.50" r="1.5" fill="#3EE6B5" stroke="#121313" stroke-width="0.5"/>
  <circle cx="22.47" cy="20.04" r="1.5" fill="#3EE6B5" stroke="#121313" stroke-width="0.5"/>
  <circle cx="14.80" cy="8.22" r="1.5" fill="#3EE6B5" stroke="#121313" stroke-width="0.5"/>
  <circle cx="12.00" cy="13.14" r="1.5" fill="#3EE6B5" stroke="#121313" stroke-width="0.5"/>
</svg>
SVG;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Benign SVG data URI for the admin menu icon.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public function register_settings() {
		register_setting(
			'aeocas_connection_settings',
			'aeocas_site_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_and_register_api_key' ),
			)
		);
		register_setting(
			'aeocas_settings',
			'aeocas_enabled_features',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_features' ),
			)
		);
	}

	/**
	 * Sanitize a site credential and attempt registration with the platform.
	 *
	 * Generates a plugin_token (if not already present) and sends it
	 * to the platform during registration. The platform uses this token
	 * to authenticate its requests to the plugin's REST API.
	 */
	public function sanitize_and_register_api_key( $input ) {
		$api_key = sanitize_text_field( $input );

		if ( empty( $api_key ) ) {
			delete_option( 'aeocas_connection_verified' );
			return '';
		}

		$result = $this->register_site_token_with_platform( $api_key );

		if ( ! is_wp_error( $result ) ) {
			update_option( 'aeocas_site_token', $api_key );
			add_settings_error(
				'aeocas_site_token',
				'aeocas_register_success',
				__( 'Successfully connected to AEO Content platform.', 'aeo-content-ai-studio' ),
				'success'
			);
		} else {
			delete_option( 'aeocas_connection_verified' );
			add_settings_error( 'aeocas_site_token', 'aeocas_register_failed', $result->get_error_message(), 'error' );
		}

		return $api_key;
	}

	/**
	 * Register or verify a site token with the platform before persisting it.
	 *
	 * @param string $site_token Site token to verify.
	 * @return array|WP_Error
	 */
	private function register_site_token_with_platform( $site_token ) {
		$site_token = sanitize_text_field( (string) $site_token );
		if ( '' === $site_token ) {
			return new WP_Error( 'aeocas_missing_site_token', __( 'Missing site token from platform.', 'aeo-content-ai-studio' ) );
		}

		$plugin_token = get_option( 'aeocas_plugin_token', '' );
		if ( empty( $plugin_token ) ) {
			$plugin_token = AEOCAS_Auth::generate_plugin_token();
		}

		$response = wp_remote_post(
			trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/plugin/register',
			array(
				'body'    => wp_json_encode(
					array(
						'site_url'     => get_site_url(),
						'plugin_token' => $plugin_token,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
					'x-api-key'    => $site_token,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aeocas_register_failed', __( 'Could not connect to AEO Content platform. Please try again later.', 'aeo-content-ai-studio' ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || empty( $body['ok'] ) ) {
			$message = ! empty( $body['error'] ) ? sanitize_text_field( $body['error'] ) : __( 'Registration failed.', 'aeo-content-ai-studio' );
			return new WP_Error( 'aeocas_register_failed', $message );
		}

		update_option( 'aeocas_plugin_token', $plugin_token, false );
		update_option( 'aeocas_connection_verified', true );

		return is_array( $body ) ? $body : array( 'ok' => true );
	}

	public function sanitize_features( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$available = aeocas_plugin()->get_available_modules();
		return array_values( array_intersect( $input, $available ) );
	}

	/**
	 * Return the best-known site URL for onboarding and platform links.
	 *
	 * @return string
	 */
	public static function get_site_url() {
		return get_option( 'aeocas_real_site_url', site_url() );
	}

	/**
	 * Build an account URL for onboarding or sign-in.
	 *
	 * @param string $intent start|signin
	 * @return string
	 */
	public static function get_connect_url( $intent = 'start' ) {
		$args = array(
			'intent'       => 'signin' === $intent ? 'signin' : 'start',
			'site_url'     => self::get_site_url(),
			'home_url'     => get_option( 'aeocas_real_home_url', home_url() ),
			'return_url'   => admin_url( 'admin.php?page=aeocas-audit-report&tab=connect' ),
			'utm_source'   => 'wordpress-plugin',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'wp-admin',
		);

		return add_query_arg( $args, trailingslashit( AEOCAS_ACCOUNT_URL ) . 'login' );
	}

	/**
	 * Return the public documentation URL for onboarding and troubleshooting.
	 *
	 * @return string
	 */
	public static function get_docs_url() {
		return self::DOCS_URL;
	}

	/**
	 * Return the public WordPress.org support forum URL.
	 *
	 * @return string
	 */
	public static function get_support_forum_url() {
		return self::SUPPORT_FORUM_URL;
	}

	/**
	 * Return the public WordPress.org review URL.
	 *
	 * @return string
	 */
	public static function get_review_url() {
		return self::REVIEW_URL;
	}

	/**
	 * Build an account URL for connected users who want to manage their account.
	 *
	 * @return string
	 */
	public static function get_manage_url() {
		return add_query_arg(
			array(
				'utm_source'   => 'wordpress-plugin',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'wp-admin',
			),
			trailingslashit( AEOCAS_ACCOUNT_URL ) . 'login'
		);
	}

	/**
	 * Build the Studio rewrite URL for the connected domain.
	 *
	 * @return string
	 */
	public static function get_rewrite_base_url() {
		$domain = wp_parse_url( self::get_site_url(), PHP_URL_HOST );
		if ( ! is_string( $domain ) || '' === $domain ) {
			return trailingslashit( AEOCAS_STUDIO_URL ) . 'pricing';
		}

		return trailingslashit( AEOCAS_STUDIO_URL ) . rawurlencode( strtolower( $domain ) ) . '/create';
	}

	/**
	 * Build the Studio billing URL for the connected domain.
	 *
	 * Studio's /{domain}/billing page redirects unauthenticated visitors to
	 * /login, so the URL is routed through /login?next=... to preserve the
	 * destination across the auth redirect.
	 *
	 * @return string
	 */
	public static function get_billing_url() {
		$domain = wp_parse_url( self::get_site_url(), PHP_URL_HOST );
		$utm    = array(
			'utm_source'   => 'wordpress-plugin',
			'utm_medium'   => 'plugin',
			'utm_campaign' => 'billing',
		);

		if ( ! is_string( $domain ) || '' === $domain ) {
			return add_query_arg( $utm, trailingslashit( AEOCAS_STUDIO_URL ) . 'pricing' );
		}

		$target = add_query_arg(
			$utm,
			trailingslashit( AEOCAS_STUDIO_URL ) . rawurlencode( strtolower( $domain ) ) . '/billing'
		);

		return add_query_arg(
			array( 'next' => $target ),
			trailingslashit( AEOCAS_STUDIO_URL ) . 'login'
		);
	}

	/**
	 * Build the admin URL for the wp-plugin console in AEO admin.
	 *
	 * @return string
	 */
	public static function get_admin_plugin_url() {
		return add_query_arg(
			array(
				'site_url'     => self::get_site_url(),
				'return_url'   => admin_url( 'admin.php?page=aeocas-audit-report&tab=visibility-overview' ),
				'utm_source'   => 'wordpress-plugin',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'wp-admin',
			),
			trailingslashit( AEOCAS_ADMIN_URL ) . 'wp-plugin'
		);
	}

	/**
	 * Build the popup URL for Google-based connect flow.
	 *
	 * Routes through Studio's `/login?intent=google` rather than `/wp-connect`
	 * directly. The login page auto-triggers `supabase.auth.signInWithOAuth`
	 * and, because `intent=google` is set, forwards `prompt=select_account`
	 * so Google always shows the account chooser (with "Use another account"
	 * at the bottom) instead of silently reusing the default signed-in
	 * Google session. After OAuth completes, the login page redirects the
	 * popup to `/wp-connect?...` which exchanges the session for a
	 * `site_token` and posts it back to this window.
	 *
	 * @return string
	 */
	public static function get_google_connect_url() {
		// Ensure plugin_token exists before opening popup.
		$plugin_token = self::get_or_create_plugin_token();

		$args = array(
			'intent'       => 'google',
			'site_url'     => self::get_site_url(),
			'home_url'     => get_option( 'aeocas_real_home_url', home_url() ),
			'plugin_token' => $plugin_token,
			'return_url'   => admin_url( 'admin.php?page=aeocas-audit-report&tab=connect' ),
		);

		return add_query_arg( $args, trailingslashit( AEOCAS_STUDIO_URL ) . 'login' );
	}

	private static function get_or_create_plugin_token() {
		$plugin_token = get_option( 'aeocas_plugin_token', '' );
		if ( empty( $plugin_token ) ) {
			$plugin_token = AEOCAS_Auth::generate_plugin_token();
			update_option( 'aeocas_plugin_token', $plugin_token, false );
		}

		return $plugin_token;
	}

	public static function set_connect_notice( $type, $message ) {
		$allowed_type = in_array( $type, array( 'success', 'warning', 'error', 'info' ), true ) ? $type : 'info';
		$notice       = array(
			'type'    => $allowed_type,
			'message' => sanitize_text_field( (string) $message ),
		);

		set_transient( self::CONNECT_NOTICE_TRANSIENT, $notice, 10 * MINUTE_IN_SECONDS );
	}

	public static function consume_connect_notice() {
		$notice = get_transient( self::CONNECT_NOTICE_TRANSIENT );
		if ( ! is_array( $notice ) ) {
			return null;
		}

		delete_transient( self::CONNECT_NOTICE_TRANSIENT );
		return $notice;
	}

	private static function get_plugin_admin_url( $tab = 'connect', $extra_args = array() ) {
		$args = array_merge(
			array(
				'page' => 'aeocas-audit-report',
				'tab'  => sanitize_key( $tab ),
			),
			is_array( $extra_args ) ? $extra_args : array()
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Return the normalized review prompt state.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_review_prompt_state() {
		$state = get_option( self::REVIEW_PROMPT_OPTION, array() );
		$state = is_array( $state ) ? $state : array();

		$events = isset( $state['events'] ) && is_array( $state['events'] ) ? $state['events'] : array();
		$events = array(
			'connected'       => isset( $events['connected'] ) ? max( 0, (int) $events['connected'] ) : 0,
			'audit_completed' => isset( $events['audit_completed'] ) ? max( 0, (int) $events['audit_completed'] ) : 0,
			'publish_success' => isset( $events['publish_success'] ) ? max( 0, (int) $events['publish_success'] ) : 0,
		);

		return array(
			'events'    => $events,
			'dismissed' => ! empty( $state['dismissed'] ),
			'reviewed'  => ! empty( $state['reviewed'] ),
		);
	}

	/**
	 * Persist review prompt state.
	 *
	 * @param array<string,mixed> $state Prompt state.
	 * @return void
	 */
	private static function save_review_prompt_state( $state ) {
		update_option( self::REVIEW_PROMPT_OPTION, $state, false );
	}

	/**
	 * Record a milestone used to qualify the review prompt.
	 *
	 * @param string $event Milestone key.
	 * @return void
	 */
	public static function record_review_milestone( $event ) {
		$event = sanitize_key( (string) $event );
		if ( ! in_array( $event, array( 'connected', 'audit_completed', 'publish_success' ), true ) ) {
			return;
		}

		$state                     = self::get_review_prompt_state();
		$state['events'][ $event ] = min( 99, (int) $state['events'][ $event ] + 1 );

		self::save_review_prompt_state( $state );
	}

	/**
	 * Determine whether the review prompt should render for this request.
	 *
	 * @return bool
	 */
	private static function should_show_review_prompt() {
		if ( ! AEOCAS_Capabilities::can_manage_plugin() ) {
			return false;
		}

		$state = self::get_review_prompt_state();
		if ( ! empty( $state['dismissed'] ) || ! empty( $state['reviewed'] ) ) {
			return false;
		}

		$has_connection = ! empty( $state['events']['connected'] );
		$has_outcome    = ! empty( $state['events']['audit_completed'] ) || ! empty( $state['events']['publish_success'] );

		return $has_connection && $has_outcome;
	}

	/**
	 * Build the redirect target used after review prompt actions.
	 *
	 * @return string
	 */
	private static function get_review_prompt_redirect_url() {
		$referer = '';
		if ( function_exists( 'wp_get_referer' ) ) {
			$referer = wp_get_referer();
		} elseif ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		if ( is_string( $referer ) && '' !== $referer ) {
			return $referer;
		}

		return self::get_plugin_admin_url( 'connect' );
	}

	public static function get_requested_studio_connect_token() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query inspection for rendering the confirmation form.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'aeocas-audit-report' !== $page ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query inspection for rendering the confirmation form.
		return isset( $_GET['aeo_connect'] ) ? sanitize_text_field( wp_unslash( $_GET['aeo_connect'] ) ) : '';
	}

	/**
	 * Render a lightweight review prompt after meaningful success milestones.
	 *
	 * @return void
	 */
	public function render_review_prompt() {
		if ( ! self::should_show_review_prompt() ) {
			return;
		}

		$review_url = self::get_review_url();
		$not_now    = add_query_arg(
			array(
				'action'               => self::REVIEW_PROMPT_ACTION,
				'aeocas_review_action' => 'dismiss',
				'_wpnonce'             => wp_create_nonce( self::REVIEW_PROMPT_ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
		$reviewed   = add_query_arg(
			array(
				'action'               => self::REVIEW_PROMPT_ACTION,
				'aeocas_review_action' => 'reviewed',
				'_wpnonce'             => wp_create_nonce( self::REVIEW_PROMPT_ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'AEO Content AI Studio is live and delivering results on this site.', 'aeo-content-ai-studio' ); ?></strong></p>
			<p><?php esc_html_e( 'If the plugin has been useful for audits or publishing, please consider leaving a quick WordPress.org review.', 'aeo-content-ai-studio' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a Review', 'aeo-content-ai-studio' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( $reviewed ); ?>"><?php esc_html_e( 'Already Reviewed', 'aeo-content-ai-studio' ); ?></a>
				<a class="button-link" href="<?php echo esc_url( $not_now ); ?>"><?php esc_html_e( 'Not Now', 'aeo-content-ai-studio' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Persist review prompt dismissal or completion state.
	 *
	 * @return void
	 */
	public function handle_review_prompt_action() {
		if ( ! AEOCAS_Capabilities::can_manage_plugin() ) {
			wp_die( esc_html__( 'Unauthorized.', 'aeo-content-ai-studio' ) );
		}

		check_admin_referer( self::REVIEW_PROMPT_ACTION );

		$decision = isset( $_REQUEST['aeocas_review_action'] )
			? sanitize_key( wp_unslash( $_REQUEST['aeocas_review_action'] ) )
			: 'dismiss';

		$state              = self::get_review_prompt_state();
		$state['dismissed'] = true;
		if ( 'reviewed' === $decision ) {
			$state['reviewed'] = true;
		}

		self::save_review_prompt_state( $state );
		wp_safe_redirect( self::get_review_prompt_redirect_url() );
	}

	public function handle_studio_connect() {
		if ( ! AEOCAS_Capabilities::can_manage_plugin() ) {
			wp_die( esc_html__( 'Unauthorized.', 'aeo-content-ai-studio' ) );
		}

		check_admin_referer( self::STUDIO_CONNECT_ACTION );

		$connect_token = isset( $_POST['connect_token'] )
			? sanitize_text_field( wp_unslash( $_POST['connect_token'] ) )
			: '';

		if ( '' === $connect_token ) {
			self::set_connect_notice( 'error', __( 'Missing Studio connection token.', 'aeo-content-ai-studio' ) );
			wp_safe_redirect( self::get_plugin_admin_url( 'connect' ) );
			return;
		}

		$plugin_token = self::get_or_create_plugin_token();
		$result       = $this->exchange_connect_token_with_platform( $connect_token, $plugin_token );

		if ( is_wp_error( $result ) ) {
			self::set_connect_notice( 'error', $result->get_error_message() );
			wp_safe_redirect(
				self::get_plugin_admin_url(
					'connect',
					array(
						'aeo_connect' => $connect_token,
					)
				)
			);
			return;
		}

		$site_token = isset( $result['site_token'] ) ? sanitize_text_field( (string) $result['site_token'] ) : '';
		if ( '' === $site_token ) {
			self::set_connect_notice( 'error', __( 'AEO Content did not return a site token for this connection.', 'aeo-content-ai-studio' ) );
			wp_safe_redirect( self::get_plugin_admin_url( 'connect' ) );
			return;
		}

		$returned_plugin_token = isset( $result['plugin_token'] ) ? sanitize_text_field( (string) $result['plugin_token'] ) : '';
		if ( '' !== $returned_plugin_token ) {
			$plugin_token = $returned_plugin_token;
		}

		update_option( 'aeocas_real_site_url', site_url(), false );
		update_option( 'aeocas_real_home_url', home_url(), false );
		update_option( 'aeocas_site_token', $site_token );
		update_option( 'aeocas_plugin_token', $plugin_token, false );
		update_option( 'aeocas_connection_verified', true );
		self::record_review_milestone( 'connected' );

		AEOCAS_Activity_Log::log( 'studio_connect', 'success', array( 'message' => 'Site connected from Studio.' ) );

		$onboard_result = AEOCAS_Audit_Api::trigger_onboarding();
		if ( is_wp_error( $onboard_result ) ) {
			self::set_connect_notice(
				'warning',
				sprintf(
					/* translators: %s: onboarding error message. */
					__( 'Connected successfully, but Discovery kickoff failed: %s', 'aeo-content-ai-studio' ),
					$onboard_result->get_error_message()
				)
			);
		} else {
			update_option( 'aeocas_onboarding_pending', 1 );
			self::set_connect_notice( 'success', __( 'Connected successfully from Studio.', 'aeo-content-ai-studio' ) );
		}

		wp_safe_redirect( self::get_plugin_admin_url( 'discovery' ) );
	}

	private function exchange_connect_token_with_platform( $connect_token, $plugin_token ) {
		$response = wp_remote_post(
			trailingslashit( AEOCAS_PLATFORM_URL ) . 'api/v1/plugin/connect/exchange',
			array(
				'body'    => wp_json_encode(
					array(
						'connect_token'  => sanitize_text_field( (string) $connect_token ),
						'site_url'       => site_url(),
						'home_url'       => home_url(),
						'plugin_token'   => sanitize_text_field( (string) $plugin_token ),
						'version'        => AEOCAS_VERSION,
						'plugin_version' => AEOCAS_VERSION,
						'wp'             => isset( $GLOBALS['wp_version'] ) ? sanitize_text_field( (string) $GLOBALS['wp_version'] ) : '',
						'wp_version'     => isset( $GLOBALS['wp_version'] ) ? sanitize_text_field( (string) $GLOBALS['wp_version'] ) : '',
						'php'            => PHP_VERSION,
						'php_version'    => PHP_VERSION,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'aeocas_connect_exchange_failed', __( 'Could not reach AEO Content to finish this connection.', 'aeo-content-ai-studio' ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();

		if ( 200 !== $status || empty( $body['ok'] ) ) {
			$message = '';
			if ( ! empty( $body['error'] ) && is_string( $body['error'] ) ) {
				$message = sanitize_text_field( $body['error'] );
			} elseif ( ! empty( $body['message'] ) && is_string( $body['message'] ) ) {
				$message = sanitize_text_field( $body['message'] );
			}

			if ( '' === $message ) {
				$message = __( 'AEO Content rejected this WordPress connection request.', 'aeo-content-ai-studio' );
			}

			return new WP_Error( 'aeocas_connect_exchange_failed', $message );
		}

		return $body;
	}

	/**
	 * AJAX handler: store tokens received from the Google connect popup.
	 */
	public function ajax_google_connect() {
		if ( ! AEOCAS_Capabilities::can_manage_plugin() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'aeo-content-ai-studio' ) ), 403 );
		}

		check_ajax_referer( 'aeocas_google_connect', 'nonce' );

		$site_token = isset( $_POST['site_token'] ) ? sanitize_text_field( wp_unslash( $_POST['site_token'] ) ) : '';

		if ( empty( $site_token ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing site token from platform.', 'aeo-content-ai-studio' ) ) );
		}

		update_option( 'aeocas_site_token', $site_token );
		update_option( 'aeocas_connection_verified', true );
		self::record_review_milestone( 'connected' );

		AEOCAS_Activity_Log::log( 'google_connect', 'success', array( 'message' => 'Site connected via Google sign-in.' ) );

		// Fire-and-forget the onboarding audit dispatch. The HTTP call is
		// non-blocking (timeout 3s, blocking=false) so this returns in ~3s
		// even if the platform is slow. The Discovery tab on the audit page
		// polls the status endpoint directly and will pick up whatever state
		// the job is in, so we don't need the synchronous response.
		$onboard_result  = AEOCAS_Audit_Api::trigger_onboarding();
		$onboard_warning = null;
		if ( is_wp_error( $onboard_result ) ) {
			$onboard_warning = $onboard_result->get_error_message();
		} else {
			update_option( 'aeocas_onboarding_pending', 1 );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Connected successfully.', 'aeo-content-ai-studio' ),
				'redirect_url' => admin_url( 'admin.php?page=aeocas-audit-report&tab=discovery' ),
				'warning'      => $onboard_warning,
			)
		);
	}

	/**
	 * Disconnect the current site from the platform.
	 */
	public function handle_disconnect() {
		if ( ! AEOCAS_Capabilities::can_manage_plugin() ) {
			wp_die( esc_html__( 'Unauthorized', 'aeo-content-ai-studio' ) );
		}

		check_admin_referer( 'aeocas_disconnect' );

		delete_option( 'aeocas_site_token' );
		delete_option( 'aeocas_plugin_token' );
		delete_option( 'aeocas_connection_verified' );
		AEOCAS_Audit_Api::clear_cache();

		$redirect_url = add_query_arg(
			array(
				'page'          => 'aeocas-audit-report',
				'tab'           => 'connect',
				'aeocas_notice' => 'disconnected',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function enqueue_styles( $hook ) {
		if ( 'toplevel_page_aeocas-audit-report' !== $hook ) {
			return;
		}

		$asset_ver = AEOCAS_VERSION . '.' . filemtime( AEOCAS_PLUGIN_DIR . 'admin/css/admin.css' );
		wp_enqueue_style(
			'aeocas-admin',
			AEOCAS_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			$asset_ver
		);

		// Google connect JS — needed on the audit page Connect tab when the
		// site isn't connected yet.
		$connected = ! empty( get_option( 'aeocas_site_token', '' ) ) && get_option( 'aeocas_connection_verified', false );
		if ( ! $connected ) {
			$gc_ver = AEOCAS_VERSION . '.' . filemtime( AEOCAS_PLUGIN_DIR . 'admin/js/google-connect.js' );
			wp_enqueue_script( 'aeocas-google-connect', AEOCAS_PLUGIN_URL . 'admin/js/google-connect.js', array(), $gc_ver, true );
			wp_localize_script(
				'aeocas-google-connect',
				'aeocasGoogle',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'aeocas_google_connect' ),
					'connectUrl'    => self::get_google_connect_url(),
					'accountOrigin' => esc_url_raw( rtrim( AEOCAS_STUDIO_URL, '/' ) ),
					'i18n'          => array(
						'waiting'    => __( 'Opening Google account chooser...', 'aeo-content-ai-studio' ),
						'connecting' => __( 'Connecting your site...', 'aeo-content-ai-studio' ),
						'success'    => __( 'Connected! Reloading...', 'aeo-content-ai-studio' ),
						'error'      => __( 'Connection failed. Please try again.', 'aeo-content-ai-studio' ),
					),
				)
			);
		}

		// Only boot the heavy audit app once the site is connected.
		if ( $connected ) {
			$js_ver = AEOCAS_VERSION . '.' . filemtime( AEOCAS_PLUGIN_DIR . 'admin/js/audit.js' );
			wp_enqueue_script(
				'aeocas-audit',
				AEOCAS_PLUGIN_URL . 'admin/js/audit.js',
				array(),
				$js_ver,
				true
			);
			wp_localize_script(
				'aeocas-audit',
				'aeocasAudit',
				array(
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'aeocas_audit_nonce' ),
					'favicon'        => get_site_icon_url( 48, '' ),
					'adminPluginUrl' => self::get_admin_plugin_url(),
					'manageUrl'      => self::get_manage_url(),
					'rewriteBaseUrl' => self::get_rewrite_base_url(),
					'billingUrl'     => self::get_billing_url(),
					'canManage'      => AEOCAS_Capabilities::can_manage_plugin(),
				)
			);
		}
	}

	// The old render_page() (Settings) and render_activity_log() methods were
	// removed when the plugin consolidated to a single workflow screen. Their
	// content now lives inside admin/views/audit-page.php under the Connect
	// and AI Visibility stages.

	public function render_audit_report() {
		if ( ! AEOCAS_Capabilities::can_view_reports() ) {
			return;
		}
		include AEOCAS_PLUGIN_DIR . 'admin/views/audit-page.php';
	}
}
