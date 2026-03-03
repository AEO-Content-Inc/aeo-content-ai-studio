<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$plugin     = aeo_content_ai_studio();
$features   = $plugin->get_enabled_features();
$modules    = $plugin->get_available_modules();
$token      = get_option( 'aeo_site_token', '' );
$platform   = get_option( 'aeo_platform_url', 'https://www.aeocontent.ai' );
$connected  = ! empty( $token );

$module_labels = array(
    'llms-txt'      => array( 'label' => 'llms.txt',            'desc' => 'Serve a virtual /llms.txt file for AI crawlers.' ),
    'ai-txt'        => array( 'label' => 'ai.txt',              'desc' => 'Serve a virtual /ai.txt file for AI crawlers.' ),
    'robots-txt'    => array( 'label' => 'robots.txt Rules',    'desc' => 'Append AI crawler rules to robots.txt.' ),
    'schema-org'    => array( 'label' => 'Organization Schema', 'desc' => 'Inject Organization + WebSite JSON-LD on every page.' ),
    'schema-post'   => array( 'label' => 'Post Schema',         'desc' => 'Inject Article, Author, FAQ, and Speakable schema per post.' ),
    'canonical'     => array( 'label' => 'Canonical URLs',      'desc' => 'Manage canonical URL tags per post.' ),
    'semantic-html' => array( 'label' => 'Semantic HTML',       'desc' => 'Wrap content in article tags, add time elements, ensure lang attribute.' ),
    'freshness'     => array( 'label' => 'Content Freshness',   'desc' => 'Add dateModified metadata and Open Graph meta tags.' ),
    'content'       => array( 'label' => 'Content Publishing',  'desc' => 'Create and update posts with full AEO optimization via API.' ),
);
?>
<div class="wrap aeo-settings">
    <h1><?php esc_html_e( 'AEO Content AI Studio', 'aeo-content-ai-studio' ); ?></h1>

    <div class="aeo-status-bar <?php echo $connected ? 'aeo-connected' : 'aeo-disconnected'; ?>">
        <span class="aeo-status-dot"></span>
        <span>
            <?php if ( $connected ) : ?>
                <?php esc_html_e( 'Connected to AEO Content Platform', 'aeo-content-ai-studio' ); ?>
            <?php else : ?>
                <?php esc_html_e( 'Not connected - enter your Site Token below to connect', 'aeo-content-ai-studio' ); ?>
            <?php endif; ?>
        </span>
        <span class="aeo-version">v<?php echo esc_html( AEO_VERSION ); ?></span>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'aeo_settings' ); ?>

        <h2><?php esc_html_e( 'Connection', 'aeo-content-ai-studio' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aeo_site_token"><?php esc_html_e( 'Site Token', 'aeo-content-ai-studio' ); ?></label></th>
                <td>
                    <input type="password" id="aeo_site_token" name="aeo_site_token"
                           value="<?php echo esc_attr( $token ); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php esc_html_e( 'Shared secret for HMAC authentication. Get this from your AEO Content dashboard.', 'aeo-content-ai-studio' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aeo_platform_url"><?php esc_html_e( 'Platform URL', 'aeo-content-ai-studio' ); ?></label></th>
                <td>
                    <input type="url" id="aeo_platform_url" name="aeo_platform_url"
                           value="<?php echo esc_attr( $platform ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'AEO Content platform URL for heartbeat and registration.', 'aeo-content-ai-studio' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Features', 'aeo-content-ai-studio' ); ?></h2>
        <p><?php esc_html_e( 'Toggle which optimization features are active on this site.', 'aeo-content-ai-studio' ); ?></p>

        <table class="form-table aeo-features-table">
            <?php foreach ( $modules as $slug ) :
                $info    = isset( $module_labels[ $slug ] ) ? $module_labels[ $slug ] : array( 'label' => $slug, 'desc' => '' );
                $checked = in_array( $slug, $features, true );
            ?>
            <tr>
                <th scope="row"><?php echo esc_html( $info['label'] ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="aeo_enabled_features[]"
                               value="<?php echo esc_attr( $slug ); ?>"
                               <?php checked( $checked ); ?> />
                        <?php echo esc_html( $info['desc'] ); ?>
                    </label>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php submit_button( __( 'Save Settings', 'aeo-content-ai-studio' ) ); ?>
    </form>

    <?php if ( $connected ) : ?>
    <h2><?php esc_html_e( 'Quick Status', 'aeo-content-ai-studio' ); ?></h2>
    <table class="widefat fixed striped aeo-status-table">
        <tbody>
            <tr>
                <td><strong><?php esc_html_e( 'llms.txt', 'aeo-content-ai-studio' ); ?></strong></td>
                <td><?php echo get_option( 'aeo_llms_txt_content' ) ? esc_html__( 'Configured', 'aeo-content-ai-studio' ) : esc_html__( 'Not set', 'aeo-content-ai-studio' ); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'ai.txt', 'aeo-content-ai-studio' ); ?></strong></td>
                <td><?php echo get_option( 'aeo_ai_txt_content' ) ? esc_html__( 'Configured', 'aeo-content-ai-studio' ) : esc_html__( 'Not set', 'aeo-content-ai-studio' ); ?></td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'robots.txt AI Rules', 'aeo-content-ai-studio' ); ?></strong></td>
                <td>
                    <?php
                    $rules = get_option( 'aeo_robots_ai_rules', array() );
                    if ( $rules ) {
                        /* translators: %d: number of rules configured */
                        echo esc_html( sprintf( _n( '%d rule', '%d rules', count( $rules ), 'aeo-content-ai-studio' ), count( $rules ) ) );
                    } else {
                        esc_html_e( 'Not set', 'aeo-content-ai-studio' );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong><?php esc_html_e( 'Organization Schema', 'aeo-content-ai-studio' ); ?></strong></td>
                <td><?php echo get_option( 'aeo_org_schema' ) ? esc_html__( 'Configured', 'aeo-content-ai-studio' ) : esc_html__( 'Not set', 'aeo-content-ai-studio' ); ?></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>
</div>
