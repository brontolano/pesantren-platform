<?php

namespace Modules\CMS\Filament\RichContent;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Modules\CMS\Support\VideoEmbed;

/**
 * Blok RichEditor "Video Kajian" — sisipkan embed YouTube/Vimeo yang aman.
 *
 * NATIVE Filament v5 (tanpa plugin/composer). Modal 1 field URL. Saat render,
 * URL diekstrak videoId-nya lalu dibangun iframe responsif 16:9 pada host
 * whitelist (youtube-nocookie.com / player.vimeo.com). URL tak dikenal →
 * pesan "URL video tidak didukung" (anti-XSS, tak render iframe sembarang).
 *
 * @see VideoEmbed inti ekstraksi + iframe (teruji unit)
 */
class VideoKajianBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'video_kajian';
    }

    public static function getLabel(): string
    {
        return 'Video Kajian';
    }

    /**
     * Konfigurasi modal: 1 field URL video.
     */
    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Tempel tautan video YouTube atau Vimeo. Hanya kedua platform ini yang didukung.')
            ->schema([
                TextInput::make('url')
                    ->label('URL Video (YouTube / Vimeo)')
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->url()
                    ->required()
                    ->helperText('Contoh: https://youtu.be/dQw4w9WgXcQ atau https://vimeo.com/123456789'),
            ]);
    }

    /**
     * Render final di konten tersimpan / halaman publik.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): string
    {
        return VideoEmbed::toIframeHtml($config['url'] ?? null);
    }

    /**
     * Pratinjau di dalam editor.
     *
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        return VideoEmbed::toIframeHtml($config['url'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        $url = trim((string) ($config['url'] ?? ''));

        if (VideoEmbed::toEmbedUrl($url) === null) {
            return 'Video Kajian (URL tidak didukung)';
        }

        return 'Video Kajian';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function shouldApplyProseStylingToPreview(array $config): bool
    {
        return false;
    }
}
