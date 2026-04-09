<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$aeocas_plugin     = aeocas_plugin();
$aeocas_features   = $aeocas_plugin->get_enabled_features();
$aeocas_modules    = $aeocas_plugin->get_available_modules();
$aeocas_token      = get_option( 'aeocas_site_token', '' );
$aeocas_platform   = AEOCAS_PLATFORM_URL;
$aeocas_connected  = ! empty( $aeocas_token ) && get_option( 'aeocas_connection_verified', false );
$aeocas_site_url   = AEOCAS_Settings::get_site_url();
$aeocas_connect_url = AEOCAS_Settings::get_connect_url( 'start' );
$aeocas_signin_url = AEOCAS_Settings::get_connect_url( 'signin' );
$aeocas_manage_url = AEOCAS_Settings::get_manage_url();
$aeocas_notice     = isset( $_GET['aeocas_notice'] ) ? sanitize_key( wp_unslash( $_GET['aeocas_notice'] ) ) : '';

$aeocas_module_labels = array(
    'content'       => array( 'label' => 'Content Publishing',  'desc' => 'Allow the AEO Content platform to read, create, and update posts on this site.' ),
);
?>
<div class="wrap aeo-settings">
    <h1><?php esc_html_e( 'AEO Content AI Studio', 'aeo-content-ai-studio' ); ?></h1>
    <h2 class="aeo-subtitle"><?php esc_html_e( 'AI Engine Optimization for WordPress. Connects your site to AEO Content AI Studio for AI-powered content management.', 'aeo-content-ai-studio' ); ?></h2>

    <?php if ( 'disconnected' === $aeocas_notice ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'This site has been disconnected from AEO Content.', 'aeo-content-ai-studio' ); ?></p>
        </div>
    <?php endif; ?>

    <?php settings_errors(); ?>

    <div class="aeo-status-bar <?php echo $aeocas_connected ? 'aeo-connected' : 'aeo-disconnected'; ?>">
        <span class="aeo-status-dot"></span>
        <span>
            <?php if ( $aeocas_connected ) : ?>
                <?php esc_html_e( 'Connected to AEO Content Platform', 'aeo-content-ai-studio' ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Not connected yet. Get started to create your account and connect this site.', 'aeo-content-ai-studio' ); ?>
            <?php endif; ?>
        </span>
        <span class="aeo-version">v<?php echo esc_html( AEOCAS_VERSION ); ?></span>
    </div>

    <h2><?php esc_html_e( 'Connection', 'aeo-content-ai-studio' ); ?></h2>

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

            <h2><?php esc_html_e( 'Features', 'aeo-content-ai-studio' ); ?></h2>
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
                            <input type="checkbox" name="aeocas_enabled_features[]"
                                   value="<?php echo esc_attr( $aeocas_slug ); ?>"
                                   <?php checked( $aeocas_checked ); ?> />
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
            <p class="aeo-connect-lead"><?php esc_html_e( 'Start in AEO Content to create your account, approve this WordPress site, and come back here once setup is complete.', 'aeo-content-ai-studio' ); ?></p>

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
                <a class="button button-primary button-hero" href="<?php echo esc_url( $aeocas_connect_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Started', 'aeo-content-ai-studio' ); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url( $aeocas_signin_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'I Already Have an Account', 'aeo-content-ai-studio' ); ?></a>
            </div>

            <p class="description aeo-connect-help"><?php esc_html_e( 'AEO Content opens in a new tab so signup and login stay on the secure account domain.', 'aeo-content-ai-studio' ); ?></p>
        </div>

        <details class="aeo-manual-connect">
            <summary><?php esc_html_e( 'Advanced: connect with an API key instead', 'aeo-content-ai-studio' ); ?></summary>

            <form method="post" action="options.php">
                <?php settings_fields( 'aeocas_connection_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aeocas_site_token"><?php esc_html_e( 'Site API Key', 'aeo-content-ai-studio' ); ?></label></th>
                        <td>
                            <input type="password" id="aeocas_site_token" name="aeocas_site_token"
                                   value="<?php echo esc_attr( $aeocas_token ); ?>" class="regular-text" autocomplete="off" />
                            <p class="description">
                                <?php esc_html_e( 'Use manual setup only if the AEO Content team gave you a site API key directly.', 'aeo-content-ai-studio' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Connect with API Key', 'aeo-content-ai-studio' ), 'secondary' ); ?>
            </form>
        </details>
    <?php endif; ?>

</div>
