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

$aeocas_module_labels = array(
    'content'       => array( 'label' => 'Content Publishing',  'desc' => 'Allow the AEO Content platform to read, create, and update posts on this site.' ),
);
?>
<div class="wrap aeo-settings">
    <h1><?php esc_html_e( 'AEO Content AI Studio', 'aeo-content-ai-studio' ); ?></h1>
    <h2 class="aeo-subtitle"><?php esc_html_e( 'AI Engine Optimization for WordPress. Connects your site to AEO Content AI Studio for AI-powered content management.', 'aeo-content-ai-studio' ); ?></h2>

    <div class="aeo-status-bar <?php echo $aeocas_connected ? 'aeo-connected' : 'aeo-disconnected'; ?>">
        <span class="aeo-status-dot"></span>
        <span>
            <?php if ( $aeocas_connected ) : ?>
                <?php esc_html_e( 'Connected to AEO Content Platform', 'aeo-content-ai-studio' ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Not connected - enter your Site API Key below to connect', 'aeo-content-ai-studio' ); ?>
            <?php endif; ?>
        </span>
        <span class="aeo-version">v<?php echo esc_html( AEOCAS_VERSION ); ?></span>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'aeocas_settings' ); ?>

        <h2><?php esc_html_e( 'Connection', 'aeo-content-ai-studio' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aeocas_site_token"><?php esc_html_e( 'Site API Key', 'aeo-content-ai-studio' ); ?></label></th>
                <td>
                    <input type="password" id="aeocas_site_token" name="aeocas_site_token"
                           value="<?php echo esc_attr( $aeocas_token ); ?>" class="regular-text" autocomplete="off" />
                    <p class="description">
                        <?php
                        echo wp_kses(
                            sprintf(
                                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                                __( 'Your API key for authenticating with the AEO Content platform. Get this from your %1$sAEO Content dashboard%2$s.', 'aeo-content-ai-studio' ),
                                '<a href="https://account.aeocontent.ai/login" target="_blank">',
                                '</a>'
                            ),
                            array( 'a' => array( 'href' => array(), 'target' => array() ) )
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Platform URL', 'aeo-content-ai-studio' ); ?></th>
                <td>
                    <code><?php echo esc_html( $aeocas_platform ); ?></code>
                    <p class="description"><?php esc_html_e( 'AEO Content platform URL for heartbeat and registration.', 'aeo-content-ai-studio' ); ?></p>
                </td>
            </tr>
        </table>

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

</div>
