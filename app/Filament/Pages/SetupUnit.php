<?php

namespace App\Filament\Pages;

use App\Support\UnitSetupRegistry;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pusat Setup — onboarding bertahap per unit (panel TENANT).
 *
 * Satu halaman, dua tampilan (sederhana, tanpa duplikasi):
 *  - tanpa ?unit  → HUB: kartu tiap unit + progres.
 *  - dengan ?unit → langkah bertahap unit itu: badge selesai/belum + tombol ke
 *    halaman asli (setting/import yang sudah ada) + sorot langkah berikutnya.
 *
 * Semua status dibaca dari UnitSetupRegistry (sumber tunggal) — tak ada cek
 * status yang diulang di sini. Melengkapi widget OnboardingChecklist (nudge
 * dashboard), bukan menggantikannya; keduanya membaca registri yang sama.
 */
class SetupUnit extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Pusat Setup';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = -11;

    protected static ?string $title = 'Pusat Setup Pesantren';

    protected string $view = 'filament.pages.setup-unit';

    public ?string $unit = null;

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        foreach (UnitSetupRegistry::units() as $def) {
            if ($user->can($def['permission'])) {
                return true;
            }
        }

        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $unit = request()->query('unit');
        $units = UnitSetupRegistry::units();
        $bolehUnit = is_string($unit) && isset($units[$unit])
            && (auth()->user()?->can($units[$unit]['permission']) ?? false);

        // Diminta unit valid tapi izin kurang → beri tahu (jangan jatuh DIAM ke hub).
        // Mis. admin pesantren memasang fitur Keuangan/HR di Pusat Modul tapi belum
        // punya izin unitnya → tahu harus minta super admin, bukan bingung.
        if (is_string($unit) && isset($units[$unit]) && ! $bolehUnit) {
            Notification::make()
                ->title('Belum bisa menyiapkan fitur ini')
                ->body('Anda belum punya izin untuk mengisi data "'.$units[$unit]['label'].'". Minta super admin pesantren untuk menyiapkannya atau memberi izin.')
                ->warning()
                ->send();
        }

        $this->unit = $bolehUnit ? $unit : null;
    }

    protected function getViewData(): array
    {
        return $this->unit === null ? $this->dataHub() : $this->dataUnit($this->unit);
    }

    /** Data tampilan hub: semua unit + progres + tautan. */
    private function dataHub(): array
    {
        $user = auth()->user();
        $kartu = [];

        foreach (UnitSetupRegistry::units() as $kode => $def) {
            if ($user !== null && ! $user->can($def['permission'])) {
                continue; // hanya unit yang boleh diakses
            }
            $kartu[] = [
                'kode' => $kode,
                'label' => $def['label'],
                'icon' => $def['icon'],
                'progress' => UnitSetupRegistry::progress($kode),
                'url' => static::getUrl(['unit' => $kode]),
            ];
        }

        return [
            'mode' => 'hub',
            'kartu' => $kartu,
            // Indikator fondasi (Setup Pesantren) — pintu UTAMA sebelum isi data.
            'setup' => SetupPesantren::progress(),
            'setupUrl' => SetupPesantren::getUrl(),
        ];
    }

    /** Data tampilan satu unit: langkah + sorot langkah berikutnya. */
    private function dataUnit(string $kode): array
    {
        $def = UnitSetupRegistry::units()[$kode];
        $steps = $def['steps'];

        $nextKey = null;
        foreach ($steps as $s) {
            if (! $s['done']) {
                $nextKey = $s['key'];
                break;
            }
        }

        // Datang dari Pusat Modul (?from=toko) → tampilkan tombol kembali ke sana.
        $tokoUrl = request()->query('from') === 'toko' && PusatModul::canAccess()
            ? PusatModul::getUrl()
            : null;

        return [
            'mode' => 'unit',
            'label' => $def['label'],
            'icon' => $def['icon'],
            'steps' => $steps,
            'progress' => UnitSetupRegistry::progress($kode),
            'nextKey' => $nextKey,
            'hubUrl' => static::getUrl(),
            'tokoUrl' => $tokoUrl,
        ];
    }
}
