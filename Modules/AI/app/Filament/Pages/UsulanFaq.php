<?php

namespace Modules\AI\Filament\Pages;

use App\Services\ModuleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\AI\Models\AiFaqDraf;
use Modules\AI\Services\Research\FaqMiner;

/**
 * FAQ-Mining — halaman tinjau & sahkan Usulan FAQ (panel TENANT, grup
 * "AI Khadim Digital"). Mengikuti pola halaman T-A011 (SumberPengetahuan /
 * EskalasiTanyaUstadz): gating modul `ai` + permission `ai.pengetahuan.kelola`.
 *
 * HUMAN-IN-THE-LOOP MUTLAK: halaman ini satu-satunya jalan usulan tambang
 * masuk RAG — admin/ustadz mengisi jawaban lalu Sahkan (→ ingest Sumber
 * Pengetahuan kategori 'faq') atau Abaikan. Badge navigasi = jumlah usulan
 * yang menunggu ditinjau.
 */
class UsulanFaq extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim';

    protected static ?string $navigationLabel = 'Usulan FAQ';

    protected static ?string $title = 'Usulan FAQ — Khadim Digital';

    protected static ?string $slug = 'usulan-faq-ai';

    protected static ?int $navigationSort = 9;

    protected string $view = 'ai::filament.pages.usulan-faq';

    /** Filter status daftar ('' = semua). */
    public string $filterStatus = '';

    /** Draf jawaban per usulan id (textarea blade). */
    public array $jawaban = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil && (Auth::user()?->can('ai.pengetahuan.kelola') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Badge navigasi: jumlah usulan menunggu tinjauan (status 'usulan'). */
    public static function getNavigationBadge(): ?string
    {
        try {
            $jumlah = AiFaqDraf::query()
                ->where('status', AiFaqDraf::STATUS_USULAN)
                ->count();

            return $jumlah > 0 ? (string) $jumlah : null;
        } catch (\Throwable) {
            return null; // tabel belum termigrasi — jangan merobohkan navigasi.
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Daftar usulan untuk blade (frekuensi terbesar dulu) sesuai filter. */
    public function daftarUsulan(): Collection
    {
        return AiFaqDraf::query()
            ->when(
                in_array($this->filterStatus, [
                    AiFaqDraf::STATUS_USULAN,
                    AiFaqDraf::STATUS_DIJAWAB,
                    AiFaqDraf::STATUS_DISAHKAN,
                    AiFaqDraf::STATUS_DIABAIKAN,
                ], true),
                fn ($q) => $q->where('status', $this->filterStatus),
            )
            ->orderByRaw("CASE WHEN status IN ('usulan', 'dijawab') THEN 0 ELSE 1 END")
            ->orderByDesc('frekuensi')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    /** Simpan jawaban tanpa mengesahkan (status → dijawab). */
    public function simpanJawaban(int $id): void
    {
        $this->pastikanBoleh();

        $teks = trim((string) ($this->jawaban[$id] ?? ''));

        $this->jalankan(function (FaqMiner $miner) use ($id, $teks): void {
            $miner->isiJawaban($id, Auth::user(), $teks);

            Notification::make()
                ->title('Jawaban tersimpan')
                ->body('Jawaban tercatat. Usulan BELUM masuk pengetahuan asisten — silakan Sahkan bila sudah final.')
                ->success()
                ->send();
        });
    }

    /**
     * Sahkan usulan → ingest ke Sumber Pengetahuan kategori 'faq'. Bila
     * textarea terisi, jawaban disimpan dulu (sekali klik untuk keduanya).
     */
    public function sahkan(int $id): void
    {
        $this->pastikanBoleh();

        $teks = trim((string) ($this->jawaban[$id] ?? ''));

        $this->jalankan(function (FaqMiner $miner) use ($id, $teks): void {
            if ($teks !== '') {
                $miner->isiJawaban($id, Auth::user(), $teks);
            }

            $miner->sahkan($id, Auth::user());

            unset($this->jawaban[$id]);

            Notification::make()
                ->title('FAQ disahkan')
                ->body('Teks FAQ sedang diproses ke Sumber Pengetahuan. Setelah siap, asisten otomatis dapat menjawabnya.')
                ->success()
                ->send();
        });
    }

    public function abaikan(int $id): void
    {
        $this->pastikanBoleh();

        $this->jalankan(function (FaqMiner $miner) use ($id): void {
            $miner->abaikan($id, Auth::user());

            unset($this->jawaban[$id]);

            Notification::make()
                ->title('Usulan diabaikan')
                ->body('Usulan ini tidak akan diusulkan ulang oleh penambang FAQ.')
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
            $aksi(app(FaqMiner::class));
        } catch (AuthorizationException|\InvalidArgumentException $e) {
            Notification::make()
                ->title('Tindakan belum terlaksana')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
