<?php

namespace Modules\AI\Filament\Pages;

use App\Models\User;
use App\Services\ModuleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Services\Escalation\EscalationService;
use Modules\AI\Services\Escalation\LaporanEskalasi;

/**
 * T-A023 — inbox antrean eskalasi "Tanya Ustadz" (panel TENANT, grup
 * "AI Khadim Digital"). Mengikuti pola halaman T-A011 (PengaturanAi /
 * PersetujuanAi): gating modul `ai` + permission.
 *
 * Dua wajah dalam satu halaman:
 *  - pemegang `ai.eskalasi.kelola` (admin/asatidz): tabel antrean + aksi
 *    tugaskan (pilih pengguna / default Wizard), jawab, tutup, filter status;
 *  - pemegang `ai.setting.setujui` (kyai/pimpinan): section Laporan Bulanan
 *    (volume per kategori, rata-rata waktu jawab, contoh anonim terredaksi).
 */
class EskalasiTanyaUstadz extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Komunikasi & AI';

    protected static ?string $navigationLabel = 'Eskalasi Tanya Ustadz';

    protected static ?string $title = 'Antrean Eskalasi — Tanya Ustadz';

    protected static ?string $slug = 'eskalasi-tanya-ustadz';

    protected static ?int $navigationSort = 4;

    protected string $view = 'ai::filament.pages.eskalasi-tanya-ustadz';

    /** Filter status tabel ('' = semua). */
    public string $filterStatus = '';

    /** Pilihan penugasan per eskalasi id (select blade; '' = tujuan default). */
    public array $tugasKe = [];

    /** Draf jawaban per eskalasi id (textarea blade). */
    public array $jawaban = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        $user = Auth::user();

        return $modulTampil && (
            ($user?->can('ai.eskalasi.kelola') ?? false)
            || ($user?->can('ai.setting.setujui') ?? false)
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Pemegang izin kelola antrean (aksi tugaskan/jawab/tutup). */
    public function bolehKelola(): bool
    {
        return Auth::user()?->can('ai.eskalasi.kelola') ?? false;
    }

    /** Pemegang izin laporan pimpinan (section laporan bulanan). */
    public function bolehLihatLaporan(): bool
    {
        return Auth::user()?->can('ai.setting.setujui') ?? false;
    }

    /** Daftar eskalasi (terbaru dulu) sesuai filter status — dipakai blade. */
    public function daftarEskalasi(): Collection
    {
        return AiEscalation::query()
            ->when(
                in_array($this->filterStatus, [
                    AiEscalation::STATUS_BARU,
                    AiEscalation::STATUS_DITUGASKAN,
                    AiEscalation::STATUS_DIJAWAB,
                    AiEscalation::STATUS_DITUTUP,
                ], true),
                fn ($q) => $q->where('status', $this->filterStatus),
            )
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    /**
     * Kandidat penerima tugas: pemegang `ai.eskalasi.kelola` (langsung atau
     * via role — scope permission() spatie). [id => nama] untuk select blade.
     *
     * @return array<int, string>
     */
    public function pilihanPenugasan(): array
    {
        try {
            return User::permission('ai.eskalasi.kelola')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Nama pengguna yang dirujuk antrean (ditugaskan_ke / dijawab_oleh).
     *
     * @return array<int, string>
     */
    public function namaPengguna(): array
    {
        $ids = $this->daftarEskalasi()
            ->flatMap(fn (AiEscalation $e): array => [$e->ditugaskan_ke, $e->dijawab_oleh])
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? []
            : User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** Laporan bulan berjalan untuk section pimpinan. */
    public function laporanBulanIni(): array
    {
        return app(LaporanEskalasi::class)->bulanan(now());
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    public function tugaskan(int $id): void
    {
        $this->pastikanBolehKelola();

        $pilihan = (int) ($this->tugasKe[$id] ?? 0);

        $this->jalankan(function (EscalationService $service) use ($id, $pilihan): void {
            $eskalasi = $service->tugaskan($id, $pilihan > 0 ? $pilihan : null);

            unset($this->tugasKe[$id]);

            Notification::make()
                ->title('Eskalasi ditugaskan')
                ->body('Penerima tugas telah diberi tahu. Tenggat jawab: '
                    .($eskalasi->sla_deadline?->translatedFormat('d F Y H:i') ?? '-').'.')
                ->success()
                ->send();
        });
    }

    public function jawab(int $id): void
    {
        $this->pastikanBolehKelola();

        $teks = trim((string) ($this->jawaban[$id] ?? ''));

        $this->jalankan(function (EscalationService $service) use ($id, $teks): void {
            $service->jawab($id, Auth::user(), $teks);

            unset($this->jawaban[$id]);

            Notification::make()
                ->title('Jawaban tercatat')
                ->body('Jawaban tersimpan dan diteruskan ke kanal asal penanya. Jazakumullahu khairan.')
                ->success()
                ->send();
        });
    }

    public function tutup(int $id): void
    {
        $this->pastikanBolehKelola();

        $this->jalankan(function (EscalationService $service) use ($id): void {
            $service->tutup($id, Auth::user());

            Notification::make()
                ->title('Eskalasi ditutup')
                ->body('Antrean ini telah selesai ditindaklanjuti.')
                ->success()
                ->send();
        });
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function jalankan(callable $aksi): void
    {
        try {
            $aksi(app(EscalationService::class));
        } catch (AuthorizationException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Tindakan belum terlaksana')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function pastikanBolehKelola(): void
    {
        abort_unless(static::canAccess() && $this->bolehKelola(), 403);
    }
}
