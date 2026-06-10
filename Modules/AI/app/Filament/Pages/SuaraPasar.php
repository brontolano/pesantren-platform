<?php

namespace Modules\AI\Filament\Pages;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\AI\Models\AiResearchEvent;

/**
 * T-A013 — dashboard internal "Suara Pasar" (panel PLATFORM 'admin',
 * super_admin only): discovery permanen Product Owner dari telemetri riset
 * BER-OPT-IN (PRD §6.4) — distribusi preset & level fatwa, fitur aktif,
 * top kategori eskalasi, kepuasan (rating), cakupan opt-in.
 *
 * PAGAR PRIVASI:
 *  - Data = agregat LINTAS-TENANT murni; halaman ini TIDAK PERNAH
 *    menampilkan nama/identitas tenant per baris data.
 *  - KETERBATASAN K-ANONYMITY: jumlah tenant platform masih kecil, sehingga
 *    agregat dengan n kecil secara teori masih bisa ditebak asalnya oleh
 *    pembaca internal. Diterima untuk dashboard internal super_admin
 *    (bukan publikasi); JANGAN mengekspor data halaman ini keluar tim
 *    tanpa ambang n minimum.
 */
class SuaraPasar extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim Digital';

    protected static ?string $navigationLabel = 'Suara Pasar';

    protected static ?string $title = 'Suara Pasar — Telemetri Riset AI (Opt-in)';

    protected static ?string $slug = 'suara-pasar';

    protected static ?int $navigationSort = 1;

    protected string $view = 'ai::filament.pages.suara-pasar';

    /** Bulan terpilih, format 'Y-m'. */
    public string $periode = '';

    /** Internal platform — HANYA panel central 'admin' + super_admin. */
    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin'
            && (auth()->user()?->hasRole('super_admin') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->periode = now()->format('Y-m');
    }

    /**
     * Pilihan 12 bulan terakhir untuk select periode.
     *
     * @return array<string, string> ['Y-m' => label readable]
     */
    public function pilihanPeriode(): array
    {
        $pilihan = [];

        for ($i = 0; $i < 12; $i++) {
            $bulan = now()->startOfMonth()->subMonths($i);
            $pilihan[$bulan->format('Y-m')] = $bulan->translatedFormat('F Y');
        }

        return $pilihan;
    }

    /**
     * Seluruh agregat dashboard untuk bulan terpilih — dihitung di PHP
     * (skala data: puluhan tenant × event bulanan; kejelasan > optimasi SQL).
     *
     * @return array{
     *     periode_label: string,
     *     tenant_optin: int,
     *     tenant_total: int,
     *     distribusi_preset: array<string, int>,
     *     distribusi_level: array<string, int>,
     *     fitur_aktif: array<string, int>,
     *     top_kategori_eskalasi: array<string, int>,
     *     rata_rata_rating: float|null,
     *     jumlah_rating: int,
     * }
     */
    public function ringkasan(): array
    {
        $bulan = $this->bulanTerpilih();
        $tanggalPeriode = $bulan->toDateString();

        // Potret konfigurasi TERAKHIR per tenant s.d. bulan terpilih
        // (snapshot hanya lahir saat versi aktif berganti — konfigurasi
        // tenant yang tidak berubah tetap terwakili snapshot terdahulunya).
        $snapshotTerakhir = AiResearchEvent::query()
            ->where('jenis', AiResearchEvent::JENIS_SNAPSHOT_KONFIGURASI)
            ->where('periode', '<=', $tanggalPeriode)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique('tenant_id')
            ->values();

        $eskalasi = AiResearchEvent::query()
            ->where('jenis', AiResearchEvent::JENIS_ESKALASI_KATEGORI)
            ->where('periode', $tanggalPeriode)
            ->get();

        $rating = AiResearchEvent::query()
            ->where('jenis', AiResearchEvent::JENIS_RATING_JAWABAN)
            ->where('periode', $tanggalPeriode)
            ->get()
            ->map(fn (AiResearchEvent $e): int => (int) ($e->payload['rating'] ?? 0))
            ->filter(fn (int $r): bool => $r >= 1 && $r <= 5);

        return [
            'periode_label' => $bulan->translatedFormat('F Y'),
            // Opt-in = tenant yang masih punya event (cabut izin menghapus
            // seluruh barisnya) — bukan daftar nama, hanya jumlah.
            'tenant_optin' => (int) AiResearchEvent::query()->distinct()->count('tenant_id'),
            'tenant_total' => (int) Tenant::query()->count(),
            'distribusi_preset' => $this->hitungDistribusi($snapshotTerakhir, 'preset_manhaj'),
            'distribusi_level' => $this->hitungDistribusi($snapshotTerakhir, 'level_fatwa'),
            'fitur_aktif' => $this->hitungFiturAktif($snapshotTerakhir),
            'top_kategori_eskalasi' => $eskalasi
                ->countBy(fn (AiResearchEvent $e): string => (string) ($e->payload['kategori'] ?? '(tanpa kategori)'))
                ->sortDesc()
                ->take(5)
                ->all(),
            'rata_rata_rating' => $rating->isEmpty() ? null : round((float) $rating->avg(), 2),
            'jumlah_rating' => $rating->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function bulanTerpilih(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->periode)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    /**
     * Jumlah tenant per nilai sebuah kunci payload snapshot.
     *
     * @param  Collection<int, AiResearchEvent>  $snapshots
     * @return array<string, int>
     */
    private function hitungDistribusi(Collection $snapshots, string $kunci): array
    {
        return $snapshots
            ->countBy(fn (AiResearchEvent $e): string => (string) ($e->payload[$kunci] ?? '(tidak diketahui)'))
            ->sortDesc()
            ->all();
    }

    /**
     * Jumlah tenant per fitur aktif (kunci "peran.fitur").
     *
     * @param  Collection<int, AiResearchEvent>  $snapshots
     * @return array<string, int>
     */
    private function hitungFiturAktif(Collection $snapshots): array
    {
        return $snapshots
            ->flatMap(fn (AiResearchEvent $e): array => (array) ($e->payload['fitur_aktif'] ?? []))
            ->countBy()
            ->sortDesc()
            ->all();
    }
}
