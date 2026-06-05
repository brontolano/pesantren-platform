<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Kompresi/optimasi gambar memakai GD native (tanpa dependency eksternal).
 * Resize bila lebar > MAX_WIDTH + re-encode dgn kualitas wajar. Hemat kuota.
 * Aman: no-op bila ekstensi GD tidak ada atau bukan gambar yang didukung.
 */
class MediaImageService
{
    public const MAX_WIDTH = 1920;

    public const QUALITY = 82;

    private const SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Kompres file gambar di disk (in-place).
     *
     * @return int|null ukuran byte baru, atau null bila dilewati/gagal (file lama dipertahankan).
     */
    public function compress(string $disk, string $path, ?string $mime): ?int
    {
        if (! function_exists('imagecreatefromstring') || ! in_array($mime, self::SUPPORTED, true)) {
            return null;
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        $raw = $storage->get($path);
        $img = @imagecreatefromstring((string) $raw);
        if ($img === false) {
            return null;
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $resized = false;

        if ($width > self::MAX_WIDTH) {
            $newHeight = (int) round($height * self::MAX_WIDTH / $width);
            $scaled = imagescale($img, self::MAX_WIDTH, $newHeight);
            if ($scaled !== false) {
                imagedestroy($img);
                $img = $scaled;
                $resized = true;
            }
        }

        // Pertahankan transparansi untuk PNG/WebP.
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        $ok = match ($mime) {
            'image/png' => imagepng($img, null, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($img, null, self::QUALITY) : false,
            default => imagejpeg($img, null, self::QUALITY),
        };
        $data = ob_get_clean();
        imagedestroy($img);

        if (! $ok || ! is_string($data) || $data === '') {
            return null;
        }

        // Tulis ulang hanya bila lebih kecil atau sudah di-resize.
        if ($resized || strlen($data) < strlen((string) $raw)) {
            $storage->put($path, $data);

            return strlen($data);
        }

        return strlen((string) $raw);
    }
}
