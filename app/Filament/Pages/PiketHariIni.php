<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
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
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Piket Hari Ini';

    protected static ?string $title = 'Piket Hari Ini';

    protected static string|\UnitEnum|null $navigationGroup = 'Sarana & Logistik';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'sarpras/piket-hari-ini';

    protected string $view = 'filament.pages.piket-hari-ini';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('pembiayaan') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('pembiayaan') && auth()->check();
    }

    /** @return array<int, array{area: AreaPiket, ceklis: Collection}> */
    #[Computed]
    public function ringkasan(): array
    {
        return app(SarprasService::class)->piketHariIni();
    }
}
