<?php

namespace Modules\AI\Services\Wa;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\AI\Models\AiConversation;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A052 — siklus hidup handoff manusia percakapan WA (PRD §8.2).
 *
 * Satu pintu transisi status percakapan chatbot:
 *  - mulaiHandoff()    : aktif/ditutup → handoff (tiga pemicu: diminta wali,
 *    gagal beruntun, ambil alih admin) + notifikasi pemegang `ai.chat.kelola`;
 *  - kembalikanKeBot() : handoff → aktif + reset gagal_beruntun;
 *  - tutup()           : → ditutup (pesan baru dari wali membukanya kembali,
 *    lihat ProsesPesanWaJob);
 *  - balasManual()     : balasan admin dari Kotak Masuk — pengiriman fisik
 *    TETAP lewat WaBalasanService (jalur outbound resmi Modules/Notifikasi,
 *    Aturan Emas #5) dan tercatat ai_message keluar TERREDAKSI bermeta
 *    {jenis: manual, oleh: user_id}.
 *
 * PRIVASI: nomor WA tidak pernah keluar utuh dari kelas ini — UI/notifikasi
 * hanya menerima nomorTersamar() (4 digit terakhir). Notifikasi memakai pola
 * LogNotifikasi database eksisting (EscalationService/BudgetGuard) — BUKAN
 * jalur WA baru.
 */
class WaHandoffService
{
    public const PEMICU_DIMINTA_WALI = 'diminta_wali';

    public const PEMICU_GAGAL_BERUNTUN = 'gagal_beruntun';

    public const PEMICU_AMBIL_ALIH = 'ambil_alih_admin';

    /** Permission penerima notifikasi & pengelola Kotak Masuk WA. */
    public const PERMISSION_KELOLA = 'ai.chat.kelola';

    public function __construct(private readonly WaBalasanService $balasan) {}

    /**
     * Apakah teks wali merupakan permintaan dilayani manusia? Pola hidup di
     * config ai.wa.handoff.pola_minta_manusia (Aturan Emas #11).
     */
    public function mintaManusia(string $teks): bool
    {
        $teks = trim($teks);

        if ($teks === '') {
            return false;
        }

        foreach ((array) config('ai.wa.handoff.pola_minta_manusia', []) as $pola) {
            // Regex config rusak diabaikan (pola IntentRouter) — deteksi
            // tidak boleh meledak karena satu pola salah ketik.
            if (@preg_match((string) $pola, $teks) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pindahkan percakapan ke status handoff + beri tahu admin/TU pemegang
     * `ai.chat.kelola`. Idempoten: percakapan yang SUDAH handoff tidak
     * dinotifikasikan ulang.
     */
    public function mulaiHandoff(AiConversation $conversation, string $pemicu, ?int $olehUserId = null): void
    {
        if ($conversation->status === AiConversation::STATUS_HANDOFF) {
            return;
        }

        $conversation->update(['status' => AiConversation::STATUS_HANDOFF]);

        $label = match ($pemicu) {
            self::PEMICU_DIMINTA_WALI => 'wali meminta dilayani manusia',
            self::PEMICU_GAGAL_BERUNTUN => 'asisten gagal menjawab beberapa kali beruntun',
            self::PEMICU_AMBIL_ALIH => 'diambil alih admin dari Kotak Masuk',
            default => $pemicu,
        };

        $this->notifikasiKePengelola(
            jenis: 'ai_wa_handoff',
            judul: 'Percakapan WA menunggu layanan admin',
            pesan: sprintf(
                'Percakapan WA (nomor %s) berpindah ke layanan manusia karena %s. '
                .'Mohon ditindaklanjuti melalui halaman Kotak Masuk WA.',
                $this->nomorTersamar($conversation),
                $label,
            ),
            conversation: $conversation,
            ekstra: ['pemicu' => $pemicu, 'oleh_user_id' => $olehUserId],
        );
    }

    /**
     * Pesan baru masuk saat status handoff: bot diam — cukup beri tahu admin
     * ada pesan menunggu (isi TIDAK ikut di payload; baca di Kotak Masuk).
     */
    public function notifikasiPesanMasuk(AiConversation $conversation): void
    {
        $this->notifikasiKePengelola(
            jenis: 'ai_wa_handoff_pesan',
            judul: 'Pesan WA baru pada percakapan handoff',
            pesan: sprintf(
                'Ada pesan baru dari nomor %s pada percakapan yang sedang dilayani manusia. '
                .'Mohon dibalas melalui halaman Kotak Masuk WA.',
                $this->nomorTersamar($conversation),
            ),
            conversation: $conversation,
        );
    }

    /** Kembalikan percakapan ke bot: status aktif + reset hitungan gagal. */
    public function kembalikanKeBot(AiConversation $conversation): void
    {
        $conversation->update([
            'status' => AiConversation::STATUS_AKTIF,
            'gagal_beruntun' => 0,
        ]);
    }

    /** Tutup percakapan (selesai dilayani). */
    public function tutup(AiConversation $conversation): void
    {
        $conversation->update(['status' => AiConversation::STATUS_DITUTUP]);
    }

    /**
     * Balasan manual admin dari Kotak Masuk — via jalur outbound resmi
     * (WaBalasanService → WaGatewayService) + tercatat ai_message keluar
     * meta {jenis: manual, oleh: user_id}.
     *
     * @return bool true bila gateway dipanggil (template aktif & WA hidup)
     */
    public function balasManual(AiConversation $conversation, User $oleh, string $teks): bool
    {
        $teks = trim($teks);

        if ($teks === '') {
            throw new InvalidArgumentException('Balasan tidak boleh kosong.');
        }

        return $this->balasan->balas($conversation, $teks, [
            'jenis' => 'manual',
            'oleh' => (int) $oleh->getKey(),
        ]);
    }

    /**
     * Nomor WA tersamar untuk UI/notifikasi: HANYA 4 digit terakhir
     * (PRD §8.2 privasi — nomor penuh tidak pernah ditampilkan).
     */
    public function nomorTersamar(AiConversation $conversation): string
    {
        try {
            $nomor = Crypt::decryptString((string) $conversation->nomor_wa_terenkripsi);
        } catch (Throwable) {
            return '••••';
        }

        // Buang suffix JID (628xx@c.us) lalu sisakan digit saja.
        $posisi = strpos($nomor, '@');
        $digit = (string) preg_replace('/\D+/', '', $posisi === false ? $nomor : substr($nomor, 0, $posisi));

        if ($digit === '') {
            return '••••';
        }

        return '••••'.substr($digit, -4);
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /**
     * Notifikasi database ke seluruh pemegang `ai.chat.kelola` (langsung
     * atau via role — scope permission() spatie, pola EskalasiTanyaUstadz).
     * Tanpa pemegang = satu baris target 'admin_tenant' supaya peristiwa
     * tidak hilang. Best-effort: kegagalan menulis tidak menggagalkan
     * transisi status (pola EscalationService).
     *
     * @param  array<string, mixed>  $ekstra
     */
    private function notifikasiKePengelola(
        string $jenis,
        string $judul,
        string $pesan,
        AiConversation $conversation,
        array $ekstra = [],
    ): void {
        try {
            $targets = User::permission(self::PERMISSION_KELOLA)
                ->pluck('id')
                ->map(fn ($id): string => 'user:'.$id)
                ->all();
        } catch (Throwable) {
            $targets = [];
        }

        if ($targets === []) {
            $targets = ['admin_tenant'];
        }

        foreach ($targets as $target) {
            try {
                LogNotifikasi::query()->create([
                    'jenis' => $jenis,
                    'channel' => 'database',
                    'target' => $target,
                    'status' => 'sent',
                    // PRIVASI: payload TANPA nomor penuh & TANPA isi pesan —
                    // hanya rujukan id percakapan + nomor tersamar.
                    'payload' => array_merge([
                        'judul' => $judul,
                        'pesan' => $pesan,
                        'conversation_id' => $conversation->getKey(),
                        'nomor_tersamar' => $this->nomorTersamar($conversation),
                        'status' => $conversation->status,
                    ], $ekstra),
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::error('WaHandoffService: GAGAL menulis notifikasi handoff.', [
                    'jenis' => $jenis,
                    'conversation_id' => $conversation->getKey(),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
