<?php

namespace Modules\CMS\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Modules\CMS\Filament\RichContent\VideoKajianBlock;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Render konten artikel RichEditor → HTML aman untuk halaman publik,
 * TERMASUK blok "Video Kajian" (iframe embed).
 *
 * Kenapa perlu sanitizer khusus? `RichContentRenderer::toHtml()` bawaan
 * Filament memakai Str::sanitizeHtml() yang MEMBUANG <iframe>. Kita butuh
 * iframe untuk video, tapi HANYA dari 2 host tepercaya. Maka:
 *   1. Proses custom block via toUnsafeHtml() (menghasilkan iframe host-terkunci
 *      dari VideoEmbed — sudah aman by construction).
 *   2. Lewatkan ke HtmlSanitizer Symfony dengan whitelist elemen aman +
 *      <iframe> dari youtube-nocookie.com & player.vimeo.com SAJA.
 * Hasilnya: XSS tetap dicegah, video tetap tampil.
 */
final class ArticleContentRenderer
{
    /** Host embed video yang diizinkan (tanpa host lain apa pun). */
    private const ALLOWED_IFRAME_HOSTS = [
        'www.youtube-nocookie.com',
        'youtube-nocookie.com',
        'player.vimeo.com',
    ];

    /**
     * @param  string|array<string,mixed>|null  $content  Konten RichEditor (HTML string atau dokumen).
     */
    public static function render(string|array|null $content): string
    {
        if (blank($content)) {
            return '';
        }

        // toUnsafeHtml(): custom block → iframe (host terkunci). Belum disanitasi,
        // jadi WAJIB dilewatkan ke sanitizer kita di bawah sebelum dirender.
        $rawHtml = RichContentRenderer::make($content)
            ->customBlocks([VideoKajianBlock::class])
            ->toUnsafeHtml();

        return self::sanitizer()->sanitize($rawHtml);
    }

    /**
     * Sanitizer: elemen aman standar + <iframe> dari host video whitelist saja.
     */
    private static function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowElement('iframe', [
                'src', 'title', 'width', 'height', 'allow',
                'allowfullscreen', 'loading', 'referrerpolicy', 'style', 'class',
            ])
            ->allowAttribute('class', allowedElements: '*')
            ->allowAttribute('style', allowedElements: '*')
            ->forceAttribute('iframe', 'referrerpolicy', 'strict-origin-when-cross-origin')
            // Kunci host iframe → hanya embed YouTube-nocookie & Vimeo.
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https'])
            // Kunci host <iframe>/media → hanya embed video whitelist.
            ->allowMediaHosts(self::ALLOWED_IFRAME_HOSTS)
            ->withMaxInputLength(500000);

        return new HtmlSanitizer($config);
    }
}
