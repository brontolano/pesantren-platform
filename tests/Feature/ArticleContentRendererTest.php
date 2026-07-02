<?php

namespace Tests\Feature;

use Modules\CMS\Support\ArticleContentRenderer;
use Tests\TestCase;

/**
 * Sanitasi render publik: iframe video HANYA dari host whitelist boleh lolos,
 * iframe/skrip host lain WAJIB dibuang. Tak butuh DB.
 */
class ArticleContentRendererTest extends TestCase
{
    public function test_kosong_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', ArticleContentRenderer::render(null));
        $this->assertSame('', ArticleContentRenderer::render(''));
    }

    /**
     * Dokumen TipTap seperti yang DISIMPAN oleh blok Video Kajian
     * (node customBlock dgn config url).
     *
     * @return array<string, mixed>
     */
    private function videoDoc(string $url): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'customBlock',
                'attrs' => ['id' => 'video_kajian', 'config' => ['url' => $url]],
            ]],
        ];
    }

    public function test_blok_video_youtube_jadi_iframe_nocookie(): void
    {
        $html = ArticleContentRenderer::render($this->videoDoc('https://youtu.be/dQw4w9WgXcQ'));

        $this->assertStringContainsString('www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('<iframe', $html);
    }

    public function test_blok_video_vimeo_jadi_iframe_player(): void
    {
        $html = ArticleContentRenderer::render($this->videoDoc('https://vimeo.com/123456789'));

        $this->assertStringContainsString('player.vimeo.com/video/123456789', $html);
        $this->assertStringContainsString('<iframe', $html);
    }

    public function test_blok_video_url_jahat_tak_render_iframe(): void
    {
        $html = ArticleContentRenderer::render($this->videoDoc('https://evil.example.com/pwn'));

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('evil.example.com', $html);
        $this->assertStringContainsString('URL video tidak didukung', $html);
    }

    public function test_iframe_host_lain_di_html_mentah_dibuang(): void
    {
        // Bahkan bila iframe host lain entah bagaimana masuk ke HTML mentah,
        // sanitizer membuangnya (whitelist host media).
        $html = ArticleContentRenderer::render(
            '<iframe src="https://evil.example.com/pwn"></iframe>'
        );

        $this->assertStringNotContainsString('evil.example.com', $html);
    }

    public function test_script_selalu_dibuang(): void
    {
        $html = ArticleContentRenderer::render(
            '<p>Aman</p><script>alert(document.cookie)</script>'
        );

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringContainsString('Aman', $html);
    }

    public function test_onerror_dan_atribut_event_dibuang(): void
    {
        $html = ArticleContentRenderer::render(
            '<img src="x" onerror="alert(1)">'
        );

        $this->assertStringNotContainsString('onerror', $html);
    }
}
