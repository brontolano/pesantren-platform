<?php

namespace Tests\Unit;

use Modules\CMS\Support\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Inti blok "Video Kajian": ekstraksi videoId + iframe host-terkunci.
 * Pure, tanpa DB. Fokus keamanan: URL jahat/host lain WAJIB ditolak.
 */
class VideoEmbedTest extends TestCase
{
    /** @return array<string, array{0:string, 1:string}> */
    public static function youtubeUrls(): array
    {
        return [
            'watch?v='          => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch tanpa www'   => ['https://youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'youtu.be'          => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'youtu.be + query'  => ['https://youtu.be/dQw4w9WgXcQ?t=42', 'dQw4w9WgXcQ'],
            'embed'             => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'nocookie embed'    => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'shorts'            => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch + param lain'=> ['https://www.youtube.com/watch?list=PL&v=dQw4w9WgXcQ&t=1', 'dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('youtubeUrls')]
    public function test_ekstrak_youtube_id(string $url, string $expectedId): void
    {
        $this->assertSame($expectedId, VideoEmbed::youtubeId($url));
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/'.$expectedId,
            VideoEmbed::toEmbedUrl($url),
        );
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function vimeoUrls(): array
    {
        return [
            'vimeo.com/id'      => ['https://vimeo.com/123456789', '123456789'],
            'player'            => ['https://player.vimeo.com/video/123456789', '123456789'],
            'channels'          => ['https://vimeo.com/channels/staffpicks/123456789', '123456789'],
            'private hash'      => ['https://vimeo.com/123456789/abcdef123', '123456789'],
        ];
    }

    #[DataProvider('vimeoUrls')]
    public function test_ekstrak_vimeo_id(string $url, string $expectedId): void
    {
        $this->assertSame($expectedId, VideoEmbed::vimeoId($url));
        $this->assertSame(
            'https://player.vimeo.com/video/'.$expectedId,
            VideoEmbed::toEmbedUrl($url),
        );
    }

    /** @return array<string, array{0:string}> */
    public static function maliciousOrUnknownUrls(): array
    {
        return [
            'host jahat mirip yt'   => ['https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ'],
            'host jahat mirip vimeo'=> ['https://vimeo.com.evil.com/123456789'],
            'javascript scheme'     => ['javascript:alert(1)'],
            'data uri'              => ['data:text/html,<script>alert(1)</script>'],
            'situs lain'            => ['https://evil.example.com/watch?v=dQw4w9WgXcQ'],
            'dailymotion'           => ['https://www.dailymotion.com/video/x7tgad0'],
            'kosong'                => [''],
            'spasi'                 => ['   '],
            'bukan url'             => ['halo dunia'],
            'yt tanpa id'           => ['https://www.youtube.com/'],
        ];
    }

    #[DataProvider('maliciousOrUnknownUrls')]
    public function test_url_jahat_atau_tak_dikenal_ditolak(string $url): void
    {
        $this->assertNull(VideoEmbed::toEmbedUrl($url), "URL harus ditolak: {$url}");

        $html = VideoEmbed::toIframeHtml($url);

        // JANGAN pernah render iframe untuk URL tak dikenal.
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString(VideoEmbed::UNSUPPORTED_MESSAGE, $html);
    }

    public function test_iframe_valid_hanya_host_whitelist(): void
    {
        $ytHtml = VideoEmbed::toIframeHtml('https://youtu.be/dQw4w9WgXcQ');
        $this->assertStringContainsString('<iframe', $ytHtml);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $ytHtml);
        $this->assertStringContainsString('aspect-ratio:16/9', $ytHtml);
        $this->assertStringContainsString('allowfullscreen', $ytHtml);

        $vimeoHtml = VideoEmbed::toIframeHtml('https://vimeo.com/123456789');
        $this->assertStringContainsString('https://player.vimeo.com/video/123456789', $vimeoHtml);

        // Tak ada host non-whitelist yang bocor.
        $this->assertStringNotContainsString('youtube.com/embed', $ytHtml); // harus nocookie
        $this->assertStringNotContainsString('<script', $ytHtml);
    }

    public function test_id_jahat_dengan_karakter_aneh_tidak_lolos(): void
    {
        // Percobaan injeksi lewat "id" yang mengandung karakter tak sah:
        // regex 11-char [A-Za-z0-9_-] tak akan cocok → ditolak.
        $this->assertNull(VideoEmbed::toEmbedUrl('https://www.youtube.com/watch?v="><script>x'));
        $this->assertNull(VideoEmbed::toEmbedUrl('https://www.youtube.com/embed/../../etc'));
    }
}
