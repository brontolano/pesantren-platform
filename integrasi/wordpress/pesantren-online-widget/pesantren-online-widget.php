<?php
/**
 * Plugin Name: Pesantren Online Widget
 * Plugin URI:  https://pesantren.online
 * Description: Tampilkan statistik, berita terbaru, dan tombol pendaftaran PPDB dari platform Pesantren Online di situs WordPress Anda. Shortcode: [pesantren_widget] dan [pesantren_ppdb_button].
 * Version:     1.0.0
 * Author:      Pesantren Online
 * License:     GPL-2.0-or-later
 * Text Domain: pesantren-online-widget
 */
if (! defined('ABSPATH')) {
    exit;
}

const POW_OPTION = 'pesantren_online_domain';

/** Domain tenant tersanitasi (host saja, tanpa skema/path). */
function pow_domain(): string
{
    $raw = (string) get_option(POW_OPTION, '');
    $host = parse_url(trim($raw), PHP_URL_HOST) ?: trim($raw);

    return preg_replace('/[^a-z0-9.\-]/i', '', strtolower((string) $host)) ?: '';
}

// ---------------------------------------------------------------------------
// Halaman pengaturan (Settings → Pesantren Online)
// ---------------------------------------------------------------------------
add_action('admin_menu', function (): void {
    add_options_page(
        'Pesantren Online',
        'Pesantren Online',
        'manage_options',
        'pesantren-online-widget',
        'pow_render_settings_page'
    );
});

add_action('admin_init', function (): void {
    register_setting('pow_settings', POW_OPTION, [
        'type' => 'string',
        'sanitize_callback' => function ($value): string {
            $host = parse_url(trim((string) $value), PHP_URL_HOST) ?: trim((string) $value);

            return preg_replace('/[^a-z0-9.\-]/i', '', strtolower((string) $host)) ?: '';
        },
    ]);
});

function pow_render_settings_page(): void
{
    ?>
    <div class="wrap">
        <h1>Pesantren Online Widget</h1>
        <form method="post" action="options.php">
            <?php settings_fields('pow_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="pow-domain">Domain pesantren</label></th>
                    <td>
                        <input id="pow-domain" type="text" class="regular-text"
                               name="<?php echo esc_attr(POW_OPTION); ?>"
                               value="<?php echo esc_attr(get_option(POW_OPTION, '')); ?>"
                               placeholder="namapesantren.pesantren.online">
                        <p class="description">
                            Isi subdomain pesantren Anda di platform Pesantren Online,
                            misal <code>demo.pesantren.online</code>.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2>Cara pakai</h2>
        <ol>
            <li>Sisipkan shortcode <code>[pesantren_widget]</code> di halaman/sidebar untuk kartu lengkap (statistik + berita + tombol PPDB).</li>
            <li>Atau <code>[pesantren_ppdb_button teks="Daftar Sekarang"]</code> untuk tombol PPDB saja.</li>
        </ol>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Shortcode [pesantren_widget] — kartu lengkap via embed widget.js platform
// ---------------------------------------------------------------------------
add_shortcode('pesantren_widget', function (): string {
    $domain = pow_domain();
    if ($domain === '') {
        return current_user_can('manage_options')
            ? '<em>Pesantren Online Widget: isi domain dulu di Settings → Pesantren Online.</em>'
            : '';
    }

    wp_enqueue_script(
        'pesantren-online-widget',
        'https://'.$domain.'/embed/widget.js',
        [],
        null,
        ['strategy' => 'defer', 'in_footer' => true]
    );

    return '<div id="pesantren-widget"></div>';
});

// ---------------------------------------------------------------------------
// Shortcode [pesantren_ppdb_button] — tombol langsung ke formulir PPDB
// ---------------------------------------------------------------------------
add_shortcode('pesantren_ppdb_button', function ($atts): string {
    $domain = pow_domain();
    if ($domain === '') {
        return '';
    }

    $atts = shortcode_atts(['teks' => 'Daftar PPDB Online'], $atts, 'pesantren_ppdb_button');

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener" style="display:inline-block;background:#059669;color:#fff;font-weight:600;padding:10px 22px;border-radius:9999px;text-decoration:none">%s</a>',
        esc_url('https://'.$domain.'/ppdb'),
        esc_html($atts['teks'])
    );
});
