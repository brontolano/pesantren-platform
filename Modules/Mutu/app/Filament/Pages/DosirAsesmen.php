<?php

namespace Modules\Mutu\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Support\UiMode;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Modules\Keuangan\Models\Tagihan;
use Modules\Ledger\Services\LaporanService;
use Modules\Mutu\Models\DokumenMutu;

/** L4 Dosir Asesmen (checklist mutu + profil keuangan) & L7 Operasional ringkas. */
class DosirAsesmen extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'L4 — Dosir Asesmen & L7';

    protected static ?string $title = 'L4 Dosir Asesmen & L7 Operasional';

    protected static string|\UnitEnum|null $navigationGroup = 'Mutu & Laporan';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'laporan/dosir-asesmen';

    protected string $view = 'filament.pages.dosir-asesmen';

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && static::moduleVisible('ledger')
            && (Auth::user()?->canAny(['view_laporan_pimpinan', 'view_ledger', 'manage_mutu']) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    #[Computed]
    public function checklist(): array
    {
        // L4 — kelengkapan dokumen mutu (SYAMIL).
        return DokumenMutu::orderBy('jenis')->get()
            ->map(fn (DokumenMutu $d) => [
                'judul' => $d->judul, 'jenis' => $d->jenis,
                'regulasi' => $d->regulasi_acuan, 'lengkap' => $d->status_kelengkapan === 'lengkap',
            ])->all();
    }

    #[Computed]
    public function operasional(): array
    {
        // L7 — ringkas operasional & profil keuangan (L6).
        $lr = app(LaporanService::class)->labaRugi();
        $tunggakan = Tagihan::belumLunas()->get()->sum(fn (Tagihan $t) => $t->sisa_tagihan);
        $danaTerikat = app(LaporanService::class)->laporanDanaTerikat();

        return [
            'pendapatan' => $lr['total_pendapatan'],
            'beban' => $lr['total_beban'],
            'surplus' => $lr['surplus_defisit'],
            'tunggakan' => round((float) $tunggakan, 2),
            'dana_terikat' => $danaTerikat,
        ];
    }
}
