<?php

namespace Modules\AI\Filament\Pages;

use App\Models\User;
use App\Services\ModuleService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Wa\WaHandoffService;

/**
 * T-A052 — Kotak Masuk percakapan WA chatbot (panel TENANT, grup
 * "AI Khadim Digital"). Pola halaman EskalasiTanyaUstadz: gating modul `ai`
 * + permission `ai.chat.kelola` (admin/TU — PRD §8.2 kotak masuk terpadu).
 *
 * Isi: daftar percakapan (status, NOMOR TERSAMAR 4 digit terakhir, nama wali
 * bila terverifikasi, pesan terakhir, badge handoff) + detail thread riwayat
 * ai_message TERREDAKSI. Aksi: Ambil Alih (→handoff, bot diam), Balas
 * (jalur outbound resmi via WaHandoffService→WaBalasanService), Kembalikan
 * ke Bot (→aktif + reset gagal_beruntun), Tutup.
 *
 * PRIVASI: nomor WA penuh TIDAK PERNAH menyentuh halaman ini — hanya
 * WaHandoffService::nomorTersamar; isi pesan memang sudah terredaksi
 * sejak disimpan (PiiRedactor::redactPaksa di webhook/WaBalasanService).
 */
class KotakMasukWa extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Khadim Digital';

    protected static ?string $navigationLabel = 'Kotak Masuk WA';

    protected static ?string $title = 'Kotak Masuk — Chatbot WhatsApp';

    protected static ?string $slug = 'kotak-masuk-wa';

    protected static ?int $navigationSort = 8;

    protected string $view = 'ai::filament.pages.kotak-masuk-wa';

    /** Filter status daftar percakapan ('' = semua). */
    public string $filterStatus = '';

    /** Percakapan yang sedang dibuka thread-nya (null = belum memilih). */
    public ?int $terpilih = null;

    /** Draf balasan manual per percakapan id (textarea blade). */
    public array $balasTeks = [];

    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'tenant') {
            return false;
        }

        $modulTampil = app(ModuleService::class)
            ->isVisibleForTenant('ai', tenancy()->initialized ? tenant() : null);

        return $modulTampil
            && (Auth::user()?->can(WaHandoffService::PERMISSION_KELOLA) ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    // ------------------------------------------------------------------
    // Data untuk blade
    // ------------------------------------------------------------------

    /** Daftar percakapan WA (paling baru aktif dulu) sesuai filter status. */
    public function daftarPercakapan(): Collection
    {
        return AiConversation::query()
            ->where('kanal', 'wa')
            ->when(
                in_array($this->filterStatus, [
                    AiConversation::STATUS_AKTIF,
                    AiConversation::STATUS_HANDOFF,
                    AiConversation::STATUS_DITUTUP,
                ], true),
                fn ($q) => $q->where('status', $this->filterStatus),
            )
            ->orderByDesc('terakhir_aktif')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * Pesan terakhir per percakapan (satu query max(id), tanpa N+1).
     *
     * @return array<int, AiMessage> conversation_id => pesan terakhir
     */
    public function pesanTerakhir(Collection $percakapan): array
    {
        $ids = $percakapan->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return AiMessage::query()
            ->whereIn('id', AiMessage::query()
                ->selectRaw('max(id)')
                ->whereIn('conversation_id', $ids)
                ->groupBy('conversation_id'))
            ->get()
            ->keyBy('conversation_id')
            ->all();
    }

    /**
     * Nama wali percakapan TERVERIFIKASI saja (identitas belum terverifikasi
     * tidak boleh diklaim di UI).
     *
     * @return array<int, string> user_id => nama
     */
    public function namaWali(Collection $percakapan): array
    {
        $ids = $percakapan
            ->filter(fn (AiConversation $c): bool => $c->verifikasi_status === AiConversation::VERIFIKASI_TERVERIFIKASI)
            ->pluck('wali_user_id')
            ->filter()
            ->unique()
            ->values();

        return $ids->isEmpty()
            ? []
            : User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** Nomor tersamar 4 digit terakhir — satu-satunya wujud nomor di UI. */
    public function nomorTersamar(AiConversation $conversation): string
    {
        return app(WaHandoffService::class)->nomorTersamar($conversation);
    }

    /** Percakapan yang dibuka detailnya (null = tidak ada/sudah terhapus). */
    public function percakapanTerpilih(): ?AiConversation
    {
        return $this->terpilih === null
            ? null
            : AiConversation::query()->where('kanal', 'wa')->find($this->terpilih);
    }

    /** Riwayat thread percakapan terpilih (terredaksi), 100 pesan terakhir. */
    public function pesanThread(): Collection
    {
        $conversation = $this->percakapanTerpilih();

        if ($conversation === null) {
            return collect();
        }

        return AiMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();
    }

    /** Ada percakapan handoff menunggu? → blade memasang poll ringan. */
    public function adaHandoff(): bool
    {
        return AiConversation::query()
            ->where('kanal', 'wa')
            ->where('status', AiConversation::STATUS_HANDOFF)
            ->exists();
    }

    // ------------------------------------------------------------------
    // Aksi
    // ------------------------------------------------------------------

    public function pilih(int $id): void
    {
        $this->pastikanBoleh();

        $this->terpilih = $this->terpilih === $id ? null : $id;
    }

    /** Admin mengambil alih percakapan: bot diam, admin yang melayani. */
    public function ambilAlih(int $id): void
    {
        $this->pastikanBoleh();

        $conversation = AiConversation::query()->where('kanal', 'wa')->findOrFail($id);

        app(WaHandoffService::class)->mulaiHandoff(
            $conversation,
            WaHandoffService::PEMICU_AMBIL_ALIH,
            (int) Auth::id(),
        );

        Notification::make()
            ->title('Percakapan diambil alih')
            ->body('Asisten dihentikan untuk percakapan ini. Silakan balas langsung dari kotak masuk.')
            ->success()
            ->send();
    }

    /** Balasan manual — kirim via jalur outbound resmi + tercatat. */
    public function kirimBalasan(int $id): void
    {
        $this->pastikanBoleh();

        $conversation = AiConversation::query()->where('kanal', 'wa')->findOrFail($id);
        $teks = trim((string) ($this->balasTeks[$id] ?? ''));

        try {
            $terkirim = app(WaHandoffService::class)
                ->balasManual($conversation, Auth::user(), $teks);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Balasan belum terkirim')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        unset($this->balasTeks[$id]);

        if ($terkirim) {
            Notification::make()
                ->title('Balasan terkirim')
                ->body('Pesan telah dikirim melalui jalur WhatsApp resmi dan tercatat pada riwayat percakapan.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Balasan tercatat, pengiriman tidak aktif')
            ->body('Template balasan WA atau kanal WhatsApp sedang dinonaktifkan di Pengaturan Notifikasi.')
            ->warning()
            ->send();
    }

    /** Kembalikan percakapan ke asisten: status aktif + reset gagal beruntun. */
    public function kembalikanKeBot(int $id): void
    {
        $this->pastikanBoleh();

        $conversation = AiConversation::query()->where('kanal', 'wa')->findOrFail($id);

        app(WaHandoffService::class)->kembalikanKeBot($conversation);

        Notification::make()
            ->title('Percakapan dikembalikan ke asisten')
            ->body('Pesan berikutnya dari nomor ini akan kembali dijawab otomatis oleh asisten.')
            ->success()
            ->send();
    }

    public function tutup(int $id): void
    {
        $this->pastikanBoleh();

        $conversation = AiConversation::query()->where('kanal', 'wa')->findOrFail($id);

        app(WaHandoffService::class)->tutup($conversation);

        Notification::make()
            ->title('Percakapan ditutup')
            ->body('Percakapan ditandai selesai. Pesan baru dari nomor ini akan membukanya kembali.')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    private function pastikanBoleh(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
