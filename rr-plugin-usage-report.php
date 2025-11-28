<?php
/**
 * Plugin Name: Review Ranger – Plugin Usage Report
 * Description: Shows usage info for selected third-party plugins (shortcodes, Elementor widgets, etc.).
 * Version: 1.0
 * Author: Shammy
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( is_admin() ) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    add_action( 'admin_menu', function () {
        add_management_page(
            'RR Plugin Usage',
            'RR Plugin Usage',
            'manage_options',
            'rr-plugin-usage',
            'rr_render_plugin_usage_page'
        );
    } );
}

/**
 * Helper: count posts/pages that contain a given shortcode.
 */
function rr_count_posts_with_shortcode( $shortcode ) {
    global $wpdb;

    $like = '%[' . $shortcode . '%';

    $sql = $wpdb->prepare(
        "SELECT COUNT(ID) 
         FROM {$wpdb->posts} 
         WHERE post_status = 'publish'
           AND post_type IN ('post','page')
           AND post_content LIKE %s",
        $like
    );

    return (int) $wpdb->get_var( $sql );
}

/**
 * Helper: count Elementor documents that contain a given string in _elementor_data.
 */
function rr_count_elementor_usage_like( $needle ) {
    global $wpdb;

    $sql = $wpdb->prepare(
        "SELECT COUNT(post_id)
         FROM {$wpdb->postmeta}
         WHERE meta_key = '_elementor_data'
           AND meta_value LIKE %s",
        '%' . $wpdb->esc_like( $needle ) . '%'
    );

    return (int) $wpdb->get_var( $sql );
}

/**
 * Render the admin page.
 */
function rr_render_plugin_usage_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Map plugin main file to label and heuristic.
    $plugins = [
        'advanced-custom-fields/acf.php' => [
            'label' => 'Advanced Custom Fields',
            'type'  => 'core',
        ],
        'easy-table-of-contents/easy-table-of-contents.php' => [
            'label'      => 'Easy Table of Contents',
            'type'       => 'shortcode',
            'shortcode'  => 'ez-toc',
        ],
        'easy-table-of-contents-pro/easy-table-of-contents-pro.php' => [
            'label' => 'Easy Table of Contents PRO',
            'type'  => 'addon',
        ],
        'essential-addons-for-elementor-lite/essential_adons_elementor.php' => [
            'label'     => 'Essential Addons for Elementor',
            'type'      => 'elementor',
            'needle'    => 'eael-',   // string used in Elementor JSON
        ],
        'essential-addons-elementor/essential-addons-elementor.php' => [
            'label'  => 'Essential Addons for Elementor – Pro',
            'type'   => 'addon',
            'needle' => 'eael-',
        ],
        'extendify/extendify.php' => [
            'label' => 'Extendify WordPress Onboarding and AI Assistant',
            'type'  => 'helper',
        ],
        'imagify/imagify.php' => [
            'label' => 'Imagify',
            'type'  => 'performance',
        ],
        'minimal-coming-soon-maintenance-mode/minimal-coming-soon-maintenance-mode.php' => [
            'label' => 'Minimal Coming Soon & Maintenance Mode',
            'type'  => 'helper',
        ],
        'seo-by-rank-math/rank-math.php' => [
            'label' => 'Rank Math SEO',
            'type'  => 'seo',
        ],
        'seo-by-rank-math-pro/rank-math-pro.php' => [
            'label' => 'Rank Math SEO PRO',
            'type'  => 'seo-addon',
        ],
        'tracking-code-manager/tracking-code-manager.php' => [
            'label' => 'Tracking Code Manager',
            'type'  => 'tracking',
        ],
        'wordpress-importer/wordpress-importer.php' => [
            'label' => 'WordPress Importer',
            'type'  => 'one-off',
        ],
        'wp-all-import/plugin.php' => [
            'label' => 'WP All Import',
            'type'  => 'import',
        ],
        'wp-rocket/wp-rocket.php' => [
            'label' => 'WP Rocket',
            'type'  => 'performance',
        ],
        'wpforms-lite/wpforms.php' => [
            'label'     => 'WPForms Lite',
            'type'      => 'shortcode',
            'shortcode' => 'wpforms',
            'needle'    => 'wpforms', // Elementor JSON
        ],
    ];

    ?>
    <div class="wrap">
        <h1>Review Ranger – Plugin Usage Report</h1>
        <p>This report shows whether selected plugins are active and where they appear to be used.
           Use this as a guide before deactivating anything.</p>

        <table class="widefat striped">
            <thead>
            <tr>
                <th>Plugin</th>
                <th>Status</th>
                <th>Detected Usage</th>
                <th>Recommendation</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $plugins as $file => $meta ) :

                $active = is_plugin_active( $file );
                $usage  = '';
                $note   = '';

                switch ( $meta['type'] ) {
                    case 'shortcode':
                        $count = rr_count_posts_with_shortcode( $meta['shortcode'] );
                        $usage = sprintf(
                            'Found %d published posts/pages with [%s] shortcode.',
                            $count,
                            esc_html( $meta['shortcode'] )
                        );
                        if ( $meta['label'] === 'WPForms Lite' ) {
                            $elem_count = rr_count_elementor_usage_like( $meta['needle'] );
                            if ( $elem_count > 0 ) {
                                $usage .= ' Also detected in ' . $elem_count . ' Elementor layouts.';
                            }
                            $note = 'If usage count is 0, it is likely safe to deactivate. Otherwise forms will disappear.';
                        } else {
                            $note = 'If usage count is 0, table of contents blocks are probably not used and plugin is a good candidate to disable.';
                        }
                        break;

                    case 'elementor':
                        $elem = rr_count_elementor_usage_like( $meta['needle'] );
                        $usage = sprintf(
                            'Detected %d Elementor layouts using Essential Addons widgets (pattern "%s").',
                            $elem,
                            esc_html( $meta['needle'] )
                        );
                        $note  = 'If count is 0, plugin might be unused. If >0, disabling will break those sections.';
                        break;

                    case 'addon':
                        $usage = 'Pro/add-on plugin. Depends on whether its extra features are configured.';
                        if ( strpos( strtolower( $meta['label'] ), 'rank math' ) !== false ) {
                            $note = 'Deactivating PRO will keep core SEO but remove PRO features (advanced schema, analytics, etc.).';
                        } elseif ( strpos( strtolower( $meta['label'] ), 'essential addons' ) !== false ) {
                            $note = 'If free Essential Addons widgets are in use but PRO widgets are not, Pro can usually be disabled.';
                        } else {
                            $note = 'Check plugin settings to confirm if any PRO-only features are used before disabling.';
                        }
                        break;

                    case 'core':
                        $usage = 'Core data / fields plugin (used by custom templates).';
                        $note  = 'Do NOT disable. Templates that rely on ACF fields will break.';
                        break;

                    case 'seo':
                        $usage = 'Global SEO engine (titles, meta, schema, sitemaps).';
                        $note  = 'Do NOT disable on a production SEO site.';
                        break;

                    case 'seo-addon':
                        $usage = 'Adds extra SEO features on top of Rank Math free.';
                        $note  = 'Not strictly required for basic SEO, but recommended to keep for full functionality.';
                        break;

                    case 'performance':
                        $usage = 'Site-wide performance/optimisation plugin.';
                        if ( $meta['label'] === 'Imagify' ) {
                            $note = 'Safe to deactivate; existing optimised images remain. New uploads will no longer be compressed.';
                        } else {
                            $note = 'Deactivating will usually slow the site and change how assets are cached/minified.';
                        }
                        break;

                    case 'tracking':
                        $usage = 'Likely injecting Analytics / Pixel / GTM codes site-wide.';
                        $note  = 'If deactivated without migrating codes elsewhere, tracking data will stop.';
                        break;

                    case 'one-off':
                        $usage = 'Used only for importing/exporting demo content.';
                        $note  = 'Safe to deactivate on a live site when not actively importing.';
                        break;

                    case 'import':
                        $usage = 'Bulk import tool for posts/listings.';
                        $note  = 'Existing content stays. Only ongoing or scheduled imports require it to stay active.';
                        break;

                    case 'helper':
                        $usage = 'Editor/onboarding / maintenance helper.';
                        if ( $meta['label'] === 'Minimal Coming Soon & Maintenance Mode' ) {
                            $note = 'Safe to deactivate as long as you do not rely on its maintenance mode right now.';
                        } else {
                            $note = 'Safe to deactivate in production; mostly affects backend UX.';
                        }
                        break;

                    default:
                        $usage = 'No automatic heuristic defined.';
                        $note  = 'Review manually.';
                        break;
                }

                ?>
                <tr>
                    <td><strong><?php echo esc_html( $meta['label'] ); ?></strong><br><code><?php echo esc_html( $file ); ?></code></td>
                    <td>
                        <?php if ( $active ) : ?>
                            <span style="color: #008000; font-weight: 600;">Active</span>
                        <?php else : ?>
                            <span style="color: #a00; font-weight: 600;">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $usage ); ?></td>
                    <td><?php echo esc_html( $note ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 1em; font-style: italic;">
            Note: this tool can’t guarantee a plugin is 100% safe to disable – it simply surfaces where it appears to be used.
            Always test changes on a staging site or during low-traffic hours.
        </p>
    </div>
    <?php
}
