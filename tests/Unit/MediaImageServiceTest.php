<?php

namespace Tests\Unit;

use App\Services\MediaImageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage untuk MediaImageService (kompresi gambar). Guard diuji tanpa GD;
 * uji kompresi nyata di-skip bila ekstensi GD tak tersedia. Tak mengubah service.
 */
class MediaImageServiceTest extends TestCase
{
    public function test_lewati_mime_tak_didukung(): void
    {
        // Mime non-gambar → null lebih dulu (tak butuh GD / file).
        $this->assertNull((new MediaImageService())->compress('public', 'dok.pdf', 'application/pdf'));
        $this->assertNull((new MediaImageService())->compress('public', 'x', null));
    }

    public function test_lewati_file_hilang(): void
    {
        Storage::fake('public');

        $this->assertNull((new MediaImageService())->compress('public', 'tidak-ada.jpg', 'image/jpeg'));
    }

    public function test_resize_gambar_lebar_berlebih(): void
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            $this->markTestSkipped('Ekstensi GD tidak tersedia.');
        }

        Storage::fake('public');

        $img = imagecreatetruecolor(3000, 1000);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put('besar.png', $png);

        $hasil = (new MediaImageService())->compress('public', 'besar.png', 'image/png');

        $this->assertIsInt($hasil);

        $after = imagecreatefromstring(Storage::disk('public')->get('besar.png'));
        $this->assertNotFalse($after);
        $this->assertLessThanOrEqual(MediaImageService::MAX_WIDTH, imagesx($after));
        imagedestroy($after);
    }
}
