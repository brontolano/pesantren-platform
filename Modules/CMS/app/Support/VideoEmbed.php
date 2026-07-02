<?php

namespace Modules\CMS\Support;

/**
 * Ekstraksi videoId + pembuatan iframe embed AMAN untuk blok "Video Kajian".
 *
 * KEAMANAN (anti-XSS): hanya 2 host embed yang di-whitelist —
 *   - https://www.youtube-nocookie.com/embed/{id}
 *   - https://player.vimeo.com/video/{id}
 * URL apa pun yang tidak cocok pola YouTube/Vimeo yang dikenal → NULL.
 * Kelas ini JANGAN pernah merender iframe dari host lain: videoId selalu
 * di-sanitasi (alnum/_/- saja), dan URL final dibangun dari template tetap,
 * bukan dari input mentah pengguna. Jadi tak ada celah menyisipkan
 * atribut/host/skema berbahaya.
 *
 * Pure & tanpa DB → mudah diuji unit.
 */
final class VideoEmbed
{
    /** Pesan yang tampil bila URL tidak didukung. */
    public const UNSUPPORTED_MESSAGE = 'URL video tidak didukung';

    /**
     * Ubah URL YouTube/Vimeo apa pun → URL embed pada host whitelist.
     * Return NULL bila tidak dikenal (JANGAN dirender sebagai iframe).
     */
    public static function toEmbedUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if ($id = self::youtubeId($url)) {
            return 'https://www.youtube-nocookie.com/embed/'.$id;
        }

        if ($id = self::vimeoId($url)) {
            return 'https://player.vimeo.com/video/'.$id;
        }

        return null;
    }

    /**
     * Ekstrak videoId YouTube dari berbagai format:
     *   youtube.com/watch?v=ID, youtu.be/ID, youtube-nocookie.com/embed/ID,
     *   youtube.com/embed/ID, youtube.com/shorts/ID, youtube.com/live/ID.
     * ID YouTube = 11 karakter [A-Za-z0-9_-].
     */
    public static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Hanya terima host YouTube yang sah (cegah host jahat spt
        // "youtube.com.evil.com" — kita anchor pada host, bukan sekadar "contains").
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        $youtubeHosts = ['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'];

        if (! in_array($host, $youtubeHosts, true)) {
            return null;
        }

        $patterns = [
            '~[?&]v=([A-Za-z0-9_-]{11})~',          // watch?v=ID
            '~youtu\.be/([A-Za-z0-9_-]{11})~',       // youtu.be/ID
            '~/embed/([A-Za-z0-9_-]{11})~',          // /embed/ID (termasuk nocookie)
            '~/shorts/([A-Za-z0-9_-]{11})~',         // /shorts/ID
            '~/live/([A-Za-z0-9_-]{11})~',           // /live/ID
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Ekstrak videoId Vimeo dari:
     *   vimeo.com/12345678, player.vimeo.com/video/12345678,
     *   vimeo.com/channels/foo/12345678, vimeo.com/12345678/hashprivate.
     * ID Vimeo = numerik.
     */
    public static function vimeoId(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        if (! in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }

        // Ambil gugus angka terakhir yang bermakna id video.
        if (preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)~', $url, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * HTML iframe responsif 16:9 pada host whitelist, atau kotak pesan
     * "URL video tidak didukung" bila URL tak dikenal.
     *
     * HTML yang dihasilkan sudah aman (host & atribut terkunci) sehingga
     * boleh dirender mentah di sisi publik.
     */
    public static function toIframeHtml(?string $url): string
    {
        $embedUrl = self::toEmbedUrl($url);

        if ($embedUrl === null) {
            return '<div class="video-kajian video-kajian--unsupported" style="padding:0.75rem 1rem;'
                .'border:1px dashed #cbd5e1;border-radius:0.5rem;color:#64748b;font-size:0.875rem;">'
                .e(self::UNSUPPORTED_MESSAGE)
                .'</div>';
        }

        // src aman (dibangun dari template + id ter-sanitasi); tetap di-escape
        // untuk keamanan berlapis.
        $src = e($embedUrl);

        return '<div class="video-kajian" style="position:relative;width:100%;aspect-ratio:16/9;'
            .'margin:1rem 0;overflow:hidden;border-radius:0.5rem;background:#000;">'
            .'<iframe src="'.$src.'" '
            .'style="position:absolute;inset:0;width:100%;height:100%;border:0;" '
            .'title="Video Kajian" loading="lazy" '
            .'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            .'allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>'
            .'</div>';
    }
}
