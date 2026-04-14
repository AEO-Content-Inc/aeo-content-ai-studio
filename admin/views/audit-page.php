<?php
/**
 * Audit Report admin page template.
 *
 * Single-page plugin UI with workflow stages for Connect, Diagnose, Fix,
 * and AI Visibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Settings state used by the Connect tab (merged from the old settings page).
$aeocas_plugin      = aeocas_plugin();
$aeocas_features    = $aeocas_plugin->get_enabled_features();
$aeocas_modules     = $aeocas_plugin->get_available_modules();
$aeocas_token       = get_option( 'aeocas_site_token', '' );
$aeocas_platform    = AEOCAS_PLATFORM_URL;
$aeocas_privacy_url = 'https://www.aeocontent.ai/privacy';
$aeocas_terms_url   = 'https://www.aeocontent.ai/terms';
$aeocas_connected   = ! empty( $aeocas_token ) && get_option( 'aeocas_connection_verified', false );
$aeocas_site_url    = AEOCAS_Settings::get_site_url();
$aeocas_connect_url = AEOCAS_Settings::get_connect_url( 'start' );
$aeocas_signin_url  = AEOCAS_Settings::get_connect_url( 'signin' );
$aeocas_manage_url  = AEOCAS_Settings::get_manage_url();
$aeocas_can_manage  = AEOCAS_Capabilities::can_manage_plugin();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UI state from sanitized query args.
$aeocas_notice        = isset( $_GET['aeocas_notice'] ) ? sanitize_key( wp_unslash( $_GET['aeocas_notice'] ) ) : '';
$aeocas_requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$aeocas_connect_notice = AEOCAS_Settings::consume_connect_notice();
$aeocas_module_labels  = array(
	'content' => array(
		'label' => 'Content Publishing',
		'desc'  => 'Allow the AEO Content platform to read, create, and update posts on this site.',
	),
);

$aeocas_log_base         = admin_url( 'admin.php?page=aeocas-audit-report' );
$aeocas_wordmark_path    = AEOCAS_PLUGIN_DIR . 'admin/images/logo.png';
$aeocas_wordmark_version = file_exists( $aeocas_wordmark_path ) ? (string) filemtime( $aeocas_wordmark_path ) : AEOCAS_VERSION;
$aeocas_wordmark_url     = AEOCAS_PLUGIN_URL . 'admin/images/logo.png?ver=' . rawurlencode( $aeocas_wordmark_version );
$aeocas_admin_url        = AEOCAS_Settings::get_admin_plugin_url();
$aeocas_valid_tabs       = array( 'connect', 'discovery', 'scoreboard', 'site-audit', 'opportunities', 'rewrite', 'visibility-overview', 'visibility-citations', 'visibility-competitors', 'visibility-trends', 'visibility', 'activity' );
$aeocas_stage_by_tab     = array(
	'connect'                => 'connect',
	'discovery'              => 'connect',
	'scoreboard'             => 'diagnose',
	'site-audit'             => 'diagnose',
	'opportunities'          => 'fix',
	'rewrite'                => 'fix',
	'visibility-overview'    => 'visibility',
	'visibility-citations'   => 'visibility',
	'visibility-competitors' => 'visibility',
	'visibility-trends'      => 'visibility',
	'visibility'             => 'visibility',
	'activity'               => 'visibility',
);
$aeocas_active_tab       = in_array( $aeocas_requested_tab, $aeocas_valid_tabs, true ) ? $aeocas_requested_tab : 'connect';
$aeocas_active_tab       = in_array( $aeocas_active_tab, array( 'visibility', 'activity' ), true ) ? 'visibility-overview' : $aeocas_active_tab;
$aeocas_active_stage     = isset( $aeocas_stage_by_tab[ $aeocas_active_tab ] ) ? $aeocas_stage_by_tab[ $aeocas_active_tab ] : 'connect';
$aeocas_tab_labels       = array(
	'connect'                => __( 'Settings', 'aeo-content-ai-studio' ),
	'discovery'              => __( 'Discovery', 'aeo-content-ai-studio' ),
	'scoreboard'             => __( 'Site Audit', 'aeo-content-ai-studio' ),
	'site-audit'             => __( 'Pages Audit', 'aeo-content-ai-studio' ),
	'opportunities'          => __( 'Opportunities', 'aeo-content-ai-studio' ),
	'rewrite'                => __( 'Rewrites', 'aeo-content-ai-studio' ),
	'visibility-overview'    => __( 'AI Visibility', 'aeo-content-ai-studio' ),
	'visibility-citations'   => __( 'Citations', 'aeo-content-ai-studio' ),
	'visibility-competitors' => __( 'Competitors', 'aeo-content-ai-studio' ),
	'visibility-trends'      => __( 'Trends', 'aeo-content-ai-studio' ),
);
// Collapse the overview chrome when arriving from a sidebar submenu link.
$aeocas_direct_tab = $aeocas_connected && '' !== $aeocas_requested_tab && 'connect' !== $aeocas_requested_tab;

if ( ! $aeocas_connected ) {
	$aeocas_active_tab   = 'connect';
	$aeocas_active_stage = 'connect';
}
?>
	<style id="aeo-audit-critical-shell">
		/* Critical shell styles live inline as a cache-safe fallback so plugin
			upgrades do not leave the workflow header/rail unstyled in wp-admin. */
		#aeo-audit-wrap .aeo-header {
			display:flex;
			align-items:center;
			gap:16px;
			flex-wrap:wrap;
			margin: 6px 0 16px;
			padding: 14px 20px;
			border: 1px solid rgba(31, 42, 51, 0.12);
			border-radius: 16px;
			background:
				radial-gradient(circle at top right, rgba(15, 118, 110, 0.12), transparent 36%),
				linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(249, 245, 236, 0.96));
			box-shadow: 0 12px 28px rgba(31, 42, 51, 0.06);
		}
		#aeo-audit-wrap .aeo-header-copy { display:flex; align-items:center; gap:10px; flex-wrap:wrap; min-width:0; }
		#aeo-audit-wrap .aeo-header-copy h1 { margin:0; font-size:20px; line-height:1.2; white-space:nowrap; }
		#aeo-audit-wrap .aeo-header-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
		#aeo-audit-wrap .aeo-header-wordmark-wrap {
			display:block;
			width:140px;
			flex-shrink:0;
		}
		#aeo-audit-wrap .aeo-header-wordmark { display:block; width:100%; max-width:100%; height:auto; }
		#aeo-audit-wrap .aeo-header-trial { display:flex; align-items:center; gap:10px; margin-left:auto; flex-wrap:wrap; font-size:13px; }
		#aeo-audit-wrap .aeo-header-trial .aeo-header-trial-title { font-size:13px; font-weight:600; margin:0; }
		#aeo-audit-wrap .aeo-header-trial .aeo-header-trial-actions { display:flex; gap:8px; }
		#aeo-audit-wrap .aeo-header-trial .aeo-header-trial-kicker { margin:0; }
	#aeo-audit-wrap .aeo-header-trial-kicker {
		display:inline-flex; align-items:center; justify-content:center; width:max-content;
		min-height:24px; padding:0 8px; border-radius:999px; background:#fbf0d7; color:#8a5a12;
		font-size:10px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;
	}
	#aeo-audit-wrap .aeo-version {
		display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:0 10px;
		border-radius:999px; background:rgba(31, 42, 51, 0.06); color:#66717d; font-size:11px; font-weight:700;
	}
	#aeo-audit-wrap .aeo-rewrite-badge {
		display:inline-flex; align-items:center; justify-content:center; min-height:28px; padding:0 10px;
		border-radius:999px; background:#dff4ef; color:#0f766e; font-size:11px; font-weight:700;
	}
	#aeo-audit-wrap .aeo-rewrite-badge.is-exhausted { background:#eef2f4; color:#50575e; }
	#aeo-audit-wrap .aeo-rewrite-badge.is-loading { background:#fbf0d7; color:#8a5a12; }
	#aeo-audit-wrap .aeo-rewrite-badge[hidden] { display:none !important; }
	#aeo-audit-wrap .aeo-header-insights-grid {
		display:grid;
		grid-template-columns:repeat(2, minmax(0, 1fr));
		gap:16px;
		min-width:0;
		align-self:start;
	}
	#aeo-audit-wrap .aeo-header-priority {
		min-height:0;
		padding:18px;
		border:1px solid rgba(31, 42, 51, 0.1);
		border-radius:20px;
		background:linear-gradient(180deg, rgba(255,255,255,0.94), rgba(223,244,239,0.5));
		box-shadow:inset 0 1px 0 rgba(255,255,255,0.72);
	}
	#aeo-audit-wrap .aeo-header-priority-empty {
		display:flex;
		flex-direction:column;
		justify-content:flex-start;
		min-height:0;
		gap:8px;
		color:#66717d;
	}
	#aeo-audit-wrap .aeo-header-priority-empty p { margin:0; }
	#aeo-audit-wrap .aeo-header-priority-empty strong {
		color:#1f2a33;
		font-size:15px;
	}
	#aeo-audit-wrap .aeo-workflow-step-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; position:relative; z-index:1; }
	#aeo-audit-wrap .aeo-workflow-step-action {
		display:inline-flex; align-items:center; justify-content:center; min-height:32px; padding:0 10px;
		border-radius:999px; background:rgba(31, 42, 51, 0.06); color:#66717d; font-size:12px; font-weight:700;
		line-height:1; text-decoration:none; cursor:pointer;
	}
	#aeo-audit-wrap .aeo-workflow-step-action.is-primary { background:#0f766e; color:#fff; }
	#aeo-audit-wrap .aeo-workflow-step-action.is-primary:hover,
	#aeo-audit-wrap .aeo-workflow-step-action.is-primary:focus { background:#0b5f58; color:#fff; }
	#aeo-audit-wrap .aeo-workflow-step-action:hover,
	#aeo-audit-wrap .aeo-workflow-step-action:focus { background:rgba(31, 42, 51, 0.1); color:#1f2a33; }

	#aeo-audit-wrap .aeo-workflow-rail {
		position: relative;
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 18px;
		margin: 0 0 28px;
		padding: 6px 0 10px;
	}
	#aeo-audit-wrap .aeo-workflow-rail::before {
		content: '';
		position: absolute;
		top: 34px;
		left: 60px;
		right: 60px;
		height: 2px;
		background: linear-gradient(90deg, rgba(15, 118, 110, 0.18), rgba(15, 118, 110, 0.08));
		pointer-events: none;
	}
	#aeo-audit-wrap .aeo-workflow-step {
		position: relative;
		display: flex !important;
		flex-direction: column;
		gap: 8px;
		min-height: 70px;
		padding: 12px 14px;
		border: 1px solid rgba(31, 42, 51, 0.12);
		border-radius: 16px;
		background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(249,245,236,0.94));
		box-shadow: 0 18px 40px rgba(31, 42, 51, 0.08);
		color: #1f2a33 !important;
		text-decoration: none !important;
		overflow: visible;
	}
	#aeo-audit-wrap .aeo-workflow-step.is-active {
		transform: translateY(-3px);
		border-color: rgba(15, 118, 110, 0.45);
		box-shadow: 0 22px 50px rgba(31, 42, 51, 0.14);
	}
	#aeo-audit-wrap .aeo-workflow-step.is-attention {
		background: linear-gradient(180deg, rgba(255,250,249,0.98), rgba(253,231,226,0.96));
		border-color: rgba(196, 61, 61, 0.28);
	}
	#aeo-audit-wrap .aeo-workflow-step.is-progress {
		background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(223,244,239,0.72));
		border-color: rgba(15, 118, 110, 0.24);
	}
	#aeo-audit-wrap .aeo-workflow-step.is-healthy {
		background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(228,243,232,0.92));
		border-color: rgba(47, 133, 90, 0.28);
	}
	#aeo-audit-wrap .aeo-workflow-step-top { display:flex; align-items:center; gap:8px; min-width:0; }
	#aeo-audit-wrap .aeo-workflow-step-index {
		position:relative; z-index:1; display:inline-flex; align-items:center; justify-content:center;
		width:28px; height:28px; border-radius:999px; border:1px solid rgba(31,42,51,0.12);
		background:#fff; font-size:12px; font-weight:700; box-shadow:0 6px 12px rgba(31,42,51,0.06);
	}
	#aeo-audit-wrap .aeo-workflow-step-icon {
		display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;
		border-radius:10px; border:1px solid rgba(15,118,110,0.16); background:rgba(15,118,110,0.08); color:#0f766e;
	}
	#aeo-audit-wrap .aeo-workflow-step-icon .dashicons { width:16px; height:16px; font-size:16px; }
	#aeo-audit-wrap .aeo-workflow-step-body { display:flex; flex-direction:column; gap:4px; min-width:0; }
	#aeo-audit-wrap .aeo-workflow-step-title { display:block; font-size:14px; font-weight:700; line-height:1.2; }
	#aeo-audit-wrap .aeo-workflow-step-label { display:none; }
	#aeo-audit-wrap .aeo-workflow-step-state {
		display:inline-flex; align-items:center; justify-content:center; width:max-content; max-width:100%;
		margin-top:auto; padding:4px 8px; border-radius:999px; font-size:10px; font-weight:700;
		letter-spacing:0.03em; text-transform:uppercase; background:#eef2f4; color:#66717d;
	}
	#aeo-audit-wrap .aeo-workflow-step-state.is-attention { background:#fde7e2; color:#c43d3d; }
	#aeo-audit-wrap .aeo-workflow-step-state.is-progress { background:#dff4ef; color:#0f766e; }
	#aeo-audit-wrap .aeo-workflow-step-state.is-healthy { background:#e4f3e8; color:#2f855a; }

	#aeo-audit-wrap .aeo-workflow-badge,
	#aeo-audit-wrap .aeo-subtab-badge {
		display:none;
		align-items:center;
		justify-content:center;
		min-width:26px;
		height:26px;
		padding:0 8px;
		border-radius:999px;
		background:#c43d3d;
		color:#fff;
		font-size:12px;
		font-weight:700;
		line-height:1;
		box-shadow:0 0 0 4px #fff, 0 12px 24px rgba(196, 61, 61, 0.24);
	}
	#aeo-audit-wrap .aeo-workflow-badge.is-visible,
	#aeo-audit-wrap .aeo-subtab-badge.is-visible { display:inline-flex !important; }
	#aeo-audit-wrap .aeo-workflow-badge { position:absolute; top:14px; right:14px; z-index:2; }

	#aeo-audit-wrap .aeo-subtabs { display:flex; gap:12px; flex-wrap:wrap; }
	#aeo-audit-wrap .aeo-subtab {
		display:inline-flex !important;
		align-items:center;
		gap:10px;
		min-height:56px;
		padding:10px 16px 10px 12px;
		border:1px solid rgba(31, 42, 51, 0.12);
		border-radius:18px;
		background:linear-gradient(180deg, rgba(255,253,248,0.9), rgba(245,241,232,0.72));
		color:#66717d !important;
		font-size:14px;
		font-weight:700;
		text-decoration:none !important;
		box-sizing:border-box;
	}
	#aeo-audit-wrap .aeo-subtab.is-active {
		background:#fffdf8;
		color:#1f2a33 !important;
		border-color:rgba(15, 118, 110, 0.32);
		box-shadow:0 18px 30px rgba(31, 42, 51, 0.1);
	}
	#aeo-audit-wrap .aeo-subtab-icon {
		display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px;
		border-radius:12px; background:rgba(15,118,110,0.08); color:#0f766e; flex-shrink:0;
	}
	#aeo-audit-wrap .aeo-subtab-icon .dashicons { width:18px; height:18px; font-size:18px; }
	#aeo-audit-wrap .aeo-subtab-label { line-height:1.3; }
	#aeo-audit-wrap .aeo-subtab-badge { margin-left:auto; }

		@media (max-width: 1200px) {
			#aeo-audit-wrap .aeo-header-insights-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
			#aeo-audit-wrap .aeo-workflow-rail { display:flex; overflow-x:auto; padding-bottom:8px; }
			#aeo-audit-wrap .aeo-workflow-rail::before { display:none; }
			#aeo-audit-wrap .aeo-workflow-step { min-width:180px; flex:0 0 180px; }
		}
		@media (max-width: 782px) {
			#aeo-audit-wrap .aeo-header { padding:12px 16px; }
			#aeo-audit-wrap .aeo-header-wordmark-wrap { width:120px; }
			#aeo-audit-wrap .aeo-header-copy h1 { font-size:16px; }
			#aeo-audit-wrap .aeo-header-trial { margin-left:0; width:100%; }
			#aeo-audit-wrap .aeo-header-insights-grid { grid-template-columns:minmax(0, 1fr); }
			#aeo-audit-wrap .aeo-workflow-step-actions { width:100%; }
		}
	</style>
	<div class="wrap aeo-settings<?php echo $aeocas_direct_tab ? ' aeo-direct-tab' : ''; ?>" id="aeo-audit-wrap" data-requested-tab="<?php echo esc_attr( $aeocas_requested_tab ); ?>" data-connected="<?php echo $aeocas_connected ? '1' : '0'; ?>" data-feature-count="<?php echo esc_attr( count( $aeocas_features ) ); ?>">

		<?php if ( $aeocas_direct_tab ) : ?>
		<div class="aeo-page-title-bar">
			<h1 class="aeo-page-title"><?php echo esc_html( isset( $aeocas_tab_labels[ $aeocas_active_tab ] ) ? $aeocas_tab_labels[ $aeocas_active_tab ] : 'AEO Content AI Studio' ); ?></h1>
			<button type="button" class="aeo-overview-toggle" id="aeo-overview-toggle" aria-expanded="false">
				<span class="dashicons dashicons-arrow-down-alt2"></span>
				<?php esc_html_e( 'Show overview', 'aeo-content-ai-studio' ); ?>
			</button>
		</div>
		<?php endif; ?>

		<div class="aeo-overview-panel" id="aeo-overview-panel" <?php echo $aeocas_direct_tab ? 'hidden' : ''; ?>>
		<div class="aeo-header">
			<span class="aeo-header-wordmark-wrap">
				<img class="aeo-header-wordmark" src="<?php echo esc_url( $aeocas_wordmark_url ); ?>" alt="<?php esc_attr_e( 'AEO Content', 'aeo-content-ai-studio' ); ?>" width="360" height="89" loading="eager" decoding="async" />
			</span>
			<div class="aeo-header-copy">
				<h1><?php esc_html_e( 'AEO Content AI Studio', 'aeo-content-ai-studio' ); ?></h1>
				<div class="aeo-header-meta">
					<span class="aeo-version">v<?php echo esc_html( AEOCAS_VERSION ); ?></span>
					<?php if ( $aeocas_connected ) : ?>
						<span class="aeo-rewrite-badge" id="aeo-rewrite-availability" hidden aria-live="polite"></span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $aeocas_connected ) : ?>
			<div class="aeo-header-trial" id="aeo-header-trial-offer" aria-live="polite">
				<span class="aeo-header-trial-title"><?php esc_html_e( 'Checking trial status…', 'aeo-content-ai-studio' ); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $aeocas_connected ) : ?>
		<div class="aeo-header-insights-grid" id="aeo-insights-grid-mount">
			<div class="aeo-header-priority" id="aeo-header-rewrite-priority" aria-live="polite">
				<div class="aeo-header-priority-empty">
					<strong><?php esc_html_e( 'Loading rewrite priorities…', 'aeo-content-ai-studio' ); ?></strong>
					<p><?php esc_html_e( 'Lowest-scoring articles from the latest audit will surface here.', 'aeo-content-ai-studio' ); ?></p>
				</div>
			</div>
			<div class="aeo-header-priority" id="aeo-header-content-suggestions" aria-live="polite">
				<div class="aeo-header-priority-empty">
					<strong><?php esc_html_e( 'Loading content suggestions…', 'aeo-content-ai-studio' ); ?></strong>
					<p><?php esc_html_e( 'Discovery gaps and intent signals will surface article ideas here.', 'aeo-content-ai-studio' ); ?></p>
				</div>
			</div>
		</div>
		<?php endif; ?>
		</div><!-- /.aeo-overview-panel -->

	<!-- Loading state -->
	<div id="aeo-audit-loading" class="aeo-audit-loading" <?php echo $aeocas_connected ? '' : 'style="display: none;"'; ?>>
		<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
		<?php esc_html_e( 'Loading audit data...', 'aeo-content-ai-studio' ); ?>
	</div>

	<!-- Error state (rendered by JS when needed) -->
	<div id="aeo-audit-error"></div>

	<?php if ( is_array( $aeocas_connect_notice ) && ! empty( $aeocas_connect_notice['message'] ) ) : ?>
		<?php
		$aeocas_connect_notice_class = 'notice-info';
		if ( 'success' === $aeocas_connect_notice['type'] ) {
			$aeocas_connect_notice_class = 'notice-success';
		} elseif ( 'warning' === $aeocas_connect_notice['type'] ) {
			$aeocas_connect_notice_class = 'notice-warning';
		} elseif ( 'error' === $aeocas_connect_notice['type'] ) {
			$aeocas_connect_notice_class = 'notice-error';
		}
		?>
		<div class="notice <?php echo esc_attr( $aeocas_connect_notice_class ); ?> is-dismissible"><p><?php echo esc_html( $aeocas_connect_notice['message'] ); ?></p></div>
	<?php endif; ?>

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
	<div id="aeo-audit-content" <?php echo $aeocas_connected ? 'style="display: none;"' : ''; ?>>

		<?php if ( $aeocas_connected ) : ?>
		<nav class="aeo-workflow-rail" aria-label="<?php esc_attr_e( 'Audit workflow', 'aeo-content-ai-studio' ); ?>">
			<a href="<?php echo esc_url( $aeocas_log_base . '&tab=connect' ); ?>" class="aeo-workflow-step <?php echo 'connect' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="connect" data-default-tab="connect">
				<span class="aeo-workflow-step-top">
					<span class="aeo-workflow-step-index">1</span>
					<span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-admin-links"></span></span>
				</span>
				<span class="aeo-workflow-step-body">
					<span class="aeo-workflow-step-title"><?php esc_html_e( 'Connect', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-workflow-step-label"><?php esc_html_e( 'Connect and review discovery', 'aeo-content-ai-studio' ); ?></span>
				</span>
				<span class="aeo-workflow-step-state"></span>
				<span class="aeo-workflow-badge" aria-hidden="true"></span>
			</a>
			<a href="<?php echo esc_url( $aeocas_log_base . '&tab=scoreboard' ); ?>" class="aeo-workflow-step <?php echo 'diagnose' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="diagnose" data-default-tab="scoreboard">
				<span class="aeo-workflow-step-top">
					<span class="aeo-workflow-step-index">2</span>
					<span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-chart-bar"></span></span>
				</span>
				<span class="aeo-workflow-step-body">
					<span class="aeo-workflow-step-title"><?php esc_html_e( 'Diagnose', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-workflow-step-label"><?php esc_html_e( 'Find critical issues', 'aeo-content-ai-studio' ); ?></span>
				</span>
				<span class="aeo-workflow-step-actions">
					<span class="aeo-workflow-step-action" data-aeo-diagnose-action="refresh" role="button" tabindex="0"><?php esc_html_e( 'Refresh', 'aeo-content-ai-studio' ); ?></span>
					<?php if ( $aeocas_can_manage ) : ?>
						<span class="aeo-workflow-step-action is-primary" data-aeo-diagnose-action="reaudit" role="button" tabindex="0"><?php esc_html_e( 'Re-audit', 'aeo-content-ai-studio' ); ?></span>
					<?php endif; ?>
				</span>
				<span class="aeo-workflow-step-state"></span>
				<span class="aeo-workflow-badge" aria-hidden="true"></span>
			</a>
			<a href="<?php echo esc_url( $aeocas_log_base . '&tab=opportunities' ); ?>" class="aeo-workflow-step <?php echo 'fix' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="fix" data-default-tab="opportunities">
				<span class="aeo-workflow-step-top">
					<span class="aeo-workflow-step-index">3</span>
					<span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-admin-tools"></span></span>
				</span>
				<span class="aeo-workflow-step-body">
					<span class="aeo-workflow-step-title"><?php esc_html_e( 'Fix', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-workflow-step-label"><?php esc_html_e( 'Act on best opportunities', 'aeo-content-ai-studio' ); ?></span>
				</span>
				<span class="aeo-workflow-step-state"></span>
				<span class="aeo-workflow-badge" aria-hidden="true"></span>
			</a>
			<a href="<?php echo esc_url( $aeocas_log_base . '&tab=visibility-overview' ); ?>" class="aeo-workflow-step <?php echo 'visibility' === $aeocas_active_stage ? 'is-active' : ''; ?>" data-stage="visibility" data-default-tab="visibility-overview">
				<span class="aeo-workflow-step-top">
					<span class="aeo-workflow-step-index">4</span>
					<span class="aeo-workflow-step-icon" aria-hidden="true"><span class="dashicons dashicons-visibility"></span></span>
				</span>
				<span class="aeo-workflow-step-body">
					<span class="aeo-workflow-step-title"><?php esc_html_e( 'AI Visibility', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-workflow-step-label"><?php esc_html_e( 'Monitor mentions and trends', 'aeo-content-ai-studio' ); ?></span>
				</span>
				<span class="aeo-workflow-step-state"></span>
				<span class="aeo-workflow-badge" aria-hidden="true"></span>
			</a>
		</nav>
		<?php endif; ?>

		<section class="aeo-stage-shell <?php echo 'connect' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-connect" data-stage="connect" <?php echo 'connect' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
			<?php if ( $aeocas_connected ) : ?>
			<div class="aeo-stage-chrome">
				<div class="aeo-stage-hero" id="aeo-stage-hero-connect"></div>
				<div class="aeo-stage-summary" id="aeo-stage-summary-connect"></div>
			</div>
			<nav class="aeo-subtabs" data-subtabs-for="connect" aria-label="<?php esc_attr_e( 'Connect views', 'aeo-content-ai-studio' ); ?>">
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=connect' ); ?>" class="aeo-subtab <?php echo 'connect' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="connect">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-admin-links"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Connection', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=discovery' ); ?>" class="aeo-subtab <?php echo 'discovery' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="discovery">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Discovery', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
			</nav>
			<?php endif; ?>
			<div class="aeo-stage-body aeo-stage-body-grouped">
				<div class="aeo-tab-panel" id="tab-connect" data-tab-panel="connect" <?php echo 'connect' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>>
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
								<div class="aeo-connect-meta-item">
									<span class="aeo-connect-meta-label"><?php esc_html_e( 'Logged in as', 'aeo-content-ai-studio' ); ?></span>
									<code>
									<?php
									$aeocas_current_user = wp_get_current_user();
									echo esc_html( $aeocas_current_user->user_email );
									?>
									</code>
									<?php if ( ! $aeocas_can_manage ) : ?>
										<span class="aeo-badge aeo-badge-error" style="font-size:11px;margin-left:6px;"><?php esc_html_e( 'Not admin — some features are restricted', 'aeo-content-ai-studio' ); ?></span>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( $aeocas_can_manage ) : ?>
								<div class="aeo-connect-actions">
									<a class="button button-primary" href="<?php echo esc_url( $aeocas_manage_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Manage Account', 'aeo-content-ai-studio' ); ?></a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aeo-inline-form">
										<input type="hidden" name="action" value="aeocas_disconnect" />
										<?php wp_nonce_field( 'aeocas_disconnect' ); ?>
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Disconnect Site', 'aeo-content-ai-studio' ); ?></button>
									</form>
								</div>
								<p class="description aeo-connect-help aeo-connect-legal">
									<?php esc_html_e( 'Account policies:', 'aeo-content-ai-studio' ); ?>
									<a href="<?php echo esc_url( $aeocas_privacy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy Policy', 'aeo-content-ai-studio' ); ?></a>
									<span aria-hidden="true">•</span>
									<a href="<?php echo esc_url( $aeocas_terms_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Terms', 'aeo-content-ai-studio' ); ?></a>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Only a site administrator can manage billing, disconnect the site, or change plugin features.', 'aeo-content-ai-studio' ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( $aeocas_can_manage ) : ?>
							<form method="post" action="options.php">
								<?php settings_fields( 'aeocas_settings' ); ?>
								<h3><?php esc_html_e( 'Features', 'aeo-content-ai-studio' ); ?></h3>
								<p><?php esc_html_e( 'Toggle which optimization features are active on this site.', 'aeo-content-ai-studio' ); ?></p>
								<table class="form-table aeo-features-table">
									<?php
									foreach ( $aeocas_modules as $aeocas_slug ) :
										$aeocas_info    = isset( $aeocas_module_labels[ $aeocas_slug ] ) ? $aeocas_module_labels[ $aeocas_slug ] : array(
											'label' => $aeocas_slug,
											'desc'  => '',
										);
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
						<?php endif; ?>
					<?php else : ?>
						<div class="aeo-connect-card aeo-connect-card-disconnected">
							<p class="aeo-connect-lead">
								<?php
								echo $aeocas_can_manage
									? esc_html__( 'Connect your site to AEO Content in one click with your Google account.', 'aeo-content-ai-studio' )
									: esc_html__( 'A site administrator must connect this site to AEO Content before audits and billing actions can run.', 'aeo-content-ai-studio' );
								?>
							</p>

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

							<?php if ( $aeocas_can_manage ) : ?>
								<div class="aeo-connect-actions" id="aeo-google-connect-wrap">
									<button type="button" class="button button-hero aeo-google-btn" id="aeo-google-btn">
										<svg class="aeo-google-icon" width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/><path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.26c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/><path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/><path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/></svg>
										<?php esc_html_e( 'Continue with Google', 'aeo-content-ai-studio' ); ?>
									</button>
									<a href="#" class="aeo-google-switch-link" id="aeo-google-switch-account"><?php esc_html_e( 'Use a different Google account', 'aeo-content-ai-studio' ); ?></a>
									<span id="aeo-google-status" class="aeo-google-status" style="display: none;"></span>
								</div>

								<div class="aeo-connect-divider"><span><?php esc_html_e( 'or', 'aeo-content-ai-studio' ); ?></span></div>

								<div class="aeo-connect-actions aeo-connect-actions-alt">
									<a class="button" href="<?php echo esc_url( $aeocas_connect_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create Account Manually', 'aeo-content-ai-studio' ); ?></a>
									<a class="button" href="<?php echo esc_url( $aeocas_signin_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'I Already Have an Account', 'aeo-content-ai-studio' ); ?></a>
								</div>

								<p class="description aeo-connect-help"><?php esc_html_e( 'A secure popup will open for Google sign-in. Your account is created automatically.', 'aeo-content-ai-studio' ); ?></p>
								<p class="description aeo-connect-help"><?php esc_html_e( 'If Google keeps picking the wrong session, use a different Google account to force the account chooser.', 'aeo-content-ai-studio' ); ?></p>
								<p class="description aeo-connect-help aeo-connect-legal">
									<?php esc_html_e( 'By continuing, you agree to the', 'aeo-content-ai-studio' ); ?>
									<a href="<?php echo esc_url( $aeocas_terms_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Terms', 'aeo-content-ai-studio' ); ?></a>
									<?php esc_html_e( 'and', 'aeo-content-ai-studio' ); ?>
									<a href="<?php echo esc_url( $aeocas_privacy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy Policy', 'aeo-content-ai-studio' ); ?></a>.
								</p>
							<?php else : ?>
								<p class="description aeo-connect-help"><?php esc_html_e( 'Ask a site administrator to complete the connection flow or provide a verified site token.', 'aeo-content-ai-studio' ); ?></p>
							<?php endif; ?>
						</div>

						<?php if ( $aeocas_can_manage ) : ?>
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
					<?php endif; ?>

					<!-- Audit Overview (rendered by JS when audit data arrives) -->
					<div id="aeo-connect-audit-section" style="display:none;"></div>
				</div>
				<div class="aeo-tab-panel" id="tab-discovery" data-tab-panel="discovery" <?php echo 'discovery' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
			</div>
		</section>

		<section class="aeo-stage-shell <?php echo 'diagnose' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-diagnose" data-stage="diagnose" <?php echo 'diagnose' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
			<div class="aeo-stage-chrome">
				<div class="aeo-stage-hero" id="aeo-stage-hero-diagnose"></div>
				<div class="aeo-stage-summary" id="aeo-stage-summary-diagnose"></div>
			</div>
			<nav class="aeo-subtabs" data-subtabs-for="diagnose" aria-label="<?php esc_attr_e( 'Diagnose views', 'aeo-content-ai-studio' ); ?>">
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=scoreboard' ); ?>" class="aeo-subtab <?php echo 'scoreboard' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="scoreboard">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-forms"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Site Audit', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=site-audit' ); ?>" class="aeo-subtab <?php echo 'site-audit' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="site-audit">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-admin-page"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Pages Audit', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
			</nav>
			<div class="aeo-stage-body aeo-stage-body-grouped">
				<div class="aeo-tab-panel" id="tab-scoreboard" data-tab-panel="scoreboard" <?php echo 'scoreboard' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
				<div class="aeo-tab-panel" id="tab-site-audit" data-tab-panel="site-audit" <?php echo 'site-audit' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
			</div>
		</section>

		<section class="aeo-stage-shell <?php echo 'fix' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-fix" data-stage="fix" <?php echo 'fix' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
			<div class="aeo-stage-chrome">
				<div class="aeo-stage-hero" id="aeo-stage-hero-fix"></div>
				<div class="aeo-stage-summary" id="aeo-stage-summary-fix"></div>
			</div>
			<nav class="aeo-subtabs" data-subtabs-for="fix" aria-label="<?php esc_attr_e( 'Fix views', 'aeo-content-ai-studio' ); ?>">
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=opportunities' ); ?>" class="aeo-subtab <?php echo 'opportunities' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="opportunities">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-star-filled"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Opportunities', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=rewrite' ); ?>" class="aeo-subtab <?php echo 'rewrite' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="rewrite">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-edit"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Rewrite Candidates', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
			</nav>
			<div class="aeo-stage-body aeo-stage-body-grouped">
				<div class="aeo-tab-panel" id="tab-opportunities" data-tab-panel="opportunities" <?php echo 'opportunities' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
				<div class="aeo-tab-panel" id="tab-rewrite" data-tab-panel="rewrite" <?php echo 'rewrite' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
			</div>
		</section>

		<section class="aeo-stage-shell <?php echo 'visibility' === $aeocas_active_stage ? 'is-active' : ''; ?>" id="stage-visibility" data-stage="visibility" data-admin-url="<?php echo esc_url( $aeocas_admin_url ); ?>" <?php echo 'visibility' === $aeocas_active_stage ? '' : 'style="display: none;"'; ?>>
			<div class="aeo-stage-chrome">
				<div class="aeo-stage-hero" id="aeo-stage-hero-visibility"></div>
				<div class="aeo-stage-summary" id="aeo-stage-summary-visibility"></div>
			</div>
			<nav class="aeo-subtabs" data-subtabs-for="visibility" aria-label="<?php esc_attr_e( 'AI Visibility views', 'aeo-content-ai-studio' ); ?>">
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=visibility-overview' ); ?>" class="aeo-subtab <?php echo 'visibility-overview' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="visibility-overview">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-chart-area"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Overview', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=visibility-citations' ); ?>" class="aeo-subtab <?php echo 'visibility-citations' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="visibility-citations">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-format-quote"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Citations', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=visibility-competitors' ); ?>" class="aeo-subtab <?php echo 'visibility-competitors' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="visibility-competitors">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-groups"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Competitors', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
				<a href="<?php echo esc_url( $aeocas_log_base . '&tab=visibility-trends' ); ?>" class="aeo-subtab <?php echo 'visibility-trends' === $aeocas_active_tab ? 'is-active' : ''; ?>" data-tab="visibility-trends">
					<span class="aeo-subtab-icon" aria-hidden="true"><span class="dashicons dashicons-chart-line"></span></span>
					<span class="aeo-subtab-label"><?php esc_html_e( 'Trends', 'aeo-content-ai-studio' ); ?></span>
					<span class="aeo-subtab-badge" aria-hidden="true"></span>
				</a>
			</nav>
			<div class="aeo-stage-body aeo-stage-body-grouped">
				<div class="aeo-tab-panel" id="tab-visibility-overview" data-tab-panel="visibility-overview" <?php echo 'visibility-overview' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
				<div class="aeo-tab-panel" id="tab-visibility-citations" data-tab-panel="visibility-citations" <?php echo 'visibility-citations' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
				<div class="aeo-tab-panel" id="tab-visibility-competitors" data-tab-panel="visibility-competitors" <?php echo 'visibility-competitors' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
				<div class="aeo-tab-panel" id="tab-visibility-trends" data-tab-panel="visibility-trends" <?php echo 'visibility-trends' === $aeocas_active_tab ? '' : 'style="display: none;"'; ?>></div>
			</div>
		</section>

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
<?php if ( $aeocas_direct_tab ) : ?>
<script>
(function(){
	var btn = document.getElementById('aeo-overview-toggle');
	var panel = document.getElementById('aeo-overview-panel');
	if (!btn || !panel) return;
	btn.addEventListener('click', function(){
		var open = panel.hidden;
		panel.hidden = !open;
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		btn.querySelector('.dashicons').className = 'dashicons dashicons-arrow-' + (open ? 'up' : 'down') + '-alt2';
		var label = btn.childNodes;
		for (var i = 0; i < label.length; i++) {
			if (label[i].nodeType === 3 && label[i].textContent.trim()) {
				label[i].textContent = open ? ' <?php echo esc_js( __( 'Hide overview', 'aeo-content-ai-studio' ) ); ?>' : ' <?php echo esc_js( __( 'Show overview', 'aeo-content-ai-studio' ) ); ?>';
				break;
			}
		}
	});
})();
</script>
<?php endif; ?>
