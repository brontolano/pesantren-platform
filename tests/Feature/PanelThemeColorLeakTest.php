<?php

namespace Tests\Feature;

use App\Http\Middleware\ApplyPanelTheme;
use Filament\Support\Colors\Color;
use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regresi JEBAKAN CACHE WARNA LINTAS-REQUEST (multi-tenant, worker
 * long-running): ColorManager Filament v5 di-bind scoped + meng-cache hasil
 * getColors() di $cachedColors. Bila cache sudah terbentuk dari request tenant
 * sebelumnya (kernel dipanggil berulang dalam satu proses — pola harness
 * audit), FilamentColor::register() berikutnya TIDAK berpengaruh → warna
 * tenant lama bocor. ApplyPanelTheme kini membuang instance scoped + resolved
 * facade sebelum register agar tiap request mulai dari ColorManager segar.
 */
class PanelThemeColorLeakTest extends TestCase
{
    public function test_register_setelah_cache_terbentuk_tidak_membawa_warna_request_sebelumnya(): void
    {
        // "Request 1": tenant lain me-register merah lalu cache terbentuk
        // (getColors() dipanggil saat render).
        FilamentColor::register(['primary' => Color::hex('#dc2626')]);
        $merah = FilamentColor::getColor('primary');
        $this->assertNotNull($merah);

        // Tanpa fix: instance scoped yang sama bertahan → cache lama menang.
        // "Request 2": ApplyPanelTheme berjalan (tanpa tenant → baseline
        // Emerald deterministik) dan HARUS mengganti palet primer.
        (new ApplyPanelTheme)->handle(Request::create('http://tenant2.localhost/admin'), fn ($r) => response('ok'));

        $sesudah = FilamentColor::getColor('primary');

        $this->assertNotNull($sesudah);
        $this->assertNotEquals($merah, $sesudah, 'Warna primer request sebelumnya bocor: cache ColorManager tidak di-invalidate oleh ApplyPanelTheme.');

        // Palet primer = baseline Emerald (bukan sisa merah): bandingkan
        // dengan hasil ColorManager SEGAR yang me-register Emerald murni.
        $segar = new ColorManager;
        $segar->register(['primary' => Color::Emerald]);
        $this->assertSame($segar->getColor('primary'), $sesudah);
    }

    public function test_dua_kali_middleware_dalam_satu_proses_tetap_deterministik(): void
    {
        $mw = new ApplyPanelTheme;

        $mw->handle(Request::create('http://tenant-a.localhost/admin'), fn ($r) => response('ok'));
        $pertama = FilamentColor::getColor('primary');

        // Simulasi request kedua di worker sama: register palet nyasar di
        // antara dua request (mis. plugin), middleware harus menormalkan lagi.
        FilamentColor::register(['primary' => Color::hex('#7c3aed')]);
        FilamentColor::getColors(); // paksa cache terbentuk

        $mw->handle(Request::create('http://tenant-b.localhost/admin'), fn ($r) => response('ok'));
        $kedua = FilamentColor::getColor('primary');

        $this->assertSame($pertama, $kedua, 'Dua request tanpa tenant harus menghasilkan palet baseline identik (deterministik).');
    }
}
