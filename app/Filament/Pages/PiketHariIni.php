<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Modules\Pembiayaan\Models\AreaPiket;
use Modules\Pembiayaan\Services\SarprasService;

/**
 * WP-33 — Halaman ringkas piket hari ini: status terakhir tiap area
 * (bersih/kotor/rusak) + siapa yang scan. Data masuk dari PWA Guru
 * via scan QR area (keyboard-wedge).
 */
class PiketHariIni extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Piket Hari Ini';

    protected static ?string $title = 'Piket Hari Ini';

    protected static string|\UnitEnum|null $navigationGroup = 'Sarpras & Umum';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'sarpras/piket-hari-ini';

    protected string $view = 'filament.pages.piket-hari-ini';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /** @return array<int, array{area: AreaPiket, ceklis: Collection}> */
    #[Computed]
    public function ringkasan(): array
    {
        return app(SarprasService::class)->piketHariIni();
    }
}
