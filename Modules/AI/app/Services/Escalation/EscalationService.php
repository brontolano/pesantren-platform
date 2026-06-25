<?php

namespace Modules\AI\Services\Escalation;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\AI\Models\AiEscalation;
use Modules\AI\Services\Privacy\PiiRedactor;
use Modules\AI\Services\Research\ResearchTelemetry;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Notifikasi\Models\LogNotifikasi;
use Throwable;

/**
 * T-A023 — siklus hidup antrean eskalasi "Tanya Ustadz" (PRD §7.6).
 *
 * Baris ai_escalation lahir dari PolicyEngine (T-A020, blacklist/level fatwa)
 * atau RagAnswerer/CitationEnforcer (T-A022, gagal sitasi). Kelas ini yang
 * menggerakkan statusnya: baru → ditugaskan (SLA) → dijawab → ditutup.
 *
 * SELURUH method berjalan dalam KONTEKS TENANT (tabel ai_escalation +
 * log_notifikasi tenant). Notifikasi memakai pola log notifikasi database
 * eksisting (LogNotifikasi, channel 'database') — BUKAN jalur WA baru
 * (Aturan Emas #5). Pengiriman jawaban balik ke kanal WA = stub T-A050
 * (lihat kirimJawabanKeKanal).
 */
class EscalationService
{
    /** T-A090 — kategori eskalasi yang dibuat manual oleh pengguna dari UI. */
    public const KATEGORI_MANUAL = 'manual';

    /**
     * Tugaskan eskalasi ke seorang pengguna. $userId null = ambil tujuan
     * default dari setting aktif `eskalasi_tujuan.penerima` (entri pertama
     * yang merujuk user yang benar-benar ada).
     *
     * Boleh dipanggil ulang (penugasan ulang): tenggat SLA dihitung ulang dan
     * penanda pengingat di-reset (tenggat baru = pengingat baru).
     */
    public function tugaskan(int $id, ?int $userId = null): AiEscalation
    {
        return DB::transaction(function () use ($id, $userId): AiEscalation {
            /** @var AiEscalation $eskalasi */
            $eskalasi = AiEscalation::query()->lockForUpdate()->findOrFail($id);

            if (! in_array($eskalasi->status, [AiEscalation::STATUS_BARU, AiEscalation::STATUS_DITUGASKAN], true)) {
                throw new InvalidArgumentException(
                    "Eskalasi #{$id} berstatus '{$eskalasi->status}' — hanya baru/ditugaskan yang dapat ditugaskan."
                );
            }

            $tujuan = $userId !== null
                ? $this->pastikanUserAda($userId)
                : $this->tujuanDefault();

            $eskalasi->update([
                'status' => AiEscalation::STATUS_DITUGASKAN,
                'ditugaskan_ke' => $tujuan,
                'sla_deadline' => now()->addHours($this->slaJam()),
                'diingatkan_pada' => null,
            ]);

            $this->catatNotifikasi(
                jenis: 'ai_eskalasi_tugas',
                target: 'user:'.$tujuan,
                judul: 'Pertanyaan Tanya Ustadz menanti jawaban',
                pesan: sprintf(
                    'Sebuah pertanyaan kategori "%s" (kanal %s) ditugaskan kepada Anda. '
                    .'Mohon dijawab sebelum %s. Jazakumullahu khairan.',
                    $eskalasi->kategori,
                    $eskalasi->kanal,
                    $eskalasi->refresh()->sla_deadline?->translatedFormat('d F Y H:i'),
                ),
                eskalasi: $eskalasi,
            );

            return $eskalasi->refresh();
        });
    }

    /**
     * T-A090 — eskalasi MANUAL dari tombol "Tanya Ustadz" di UI (komponen
     * <x-ai::tombol-tanya-ustadz>). Berbeda dari jalur PolicyEngine/RagAnswerer
     * (eskalasi otomatis karena blokir/gagal sitasi): di sini PENGGUNA sendiri
     * yang memilih bertanya kepada manusia.
     *
     * Pagar (pola PolicyEngine [M1][M2]):
     *  - pertanyaan diredaksi PII PAKSA + dipangkas sebelum disimpan;
     *  - IDEMPOTEN per (user, hash pertanyaan ternormalisasi) — klik ganda /
     *    kirim ulang form tidak menggandakan antrean ustadz;
     *  - baris BARU menambah event riset kategori saja (T-A013, no-op senyap
     *    tanpa opt-in).
     */
    public function buatManual(User $penanya, string $pertanyaan, string $kanal = 'web'): AiEscalation
    {
        $pertanyaan = trim($pertanyaan);

        if ($pertanyaan === '') {
            throw new InvalidArgumentException('Pertanyaan tidak boleh kosong.');
        }

        $maks = (int) config('ai.guardrails.eskalasi_ringkas_max', 300);
        $terredaksi = app(PiiRedactor::class)
            ->redactPaksa($pertanyaan)->text;

        // Normalisasi sebelum hash: huruf kecil + spasi rapat — variasi
        // ketik ulang yang sama secara substansi tetap satu antrean.
        $normal = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $pertanyaan)));
        $kunci = sprintf('manual:%d:%s', $penanya->getKey(), hash('sha256', $normal));

        $eskalasi = AiEscalation::query()->firstOrCreate(
            ['idempotency_key' => $kunci],
            [
                'conversation_ref' => $kunci,
                'kanal' => $kanal,
                'pertanyaan_ringkas' => mb_substr($terredaksi, 0, $maks),
                'kategori' => self::KATEGORI_MANUAL,
                'status' => AiEscalation::STATUS_BARU,
            ],
        );

        if ($eskalasi->wasRecentlyCreated && tenancy()->initialized) {
            app(ResearchTelemetry::class)
                ->rekamEskalasiKategori((string) tenant()->getKey(), self::KATEGORI_MANUAL);
        }

        return $eskalasi;
    }

    /**
     * Catat jawaban manusia: status dijawab + identitas/waktu penjawab, lalu
     * teruskan jawaban ke kanal asal (lihat kirimJawabanKeKanal).
     */
    public function jawab(int $id, User $penjawab, string $teks): AiEscalation
    {
        $this->pastikanBoleh($penjawab, 'ai.eskalasi.kelola');

        $teks = trim($teks);

        if ($teks === '') {
            throw new InvalidArgumentException('Jawaban tidak boleh kosong.');
        }

        $eskalasi = DB::transaction(function () use ($id, $penjawab, $teks): AiEscalation {
            /** @var AiEscalation $eskalasi */
            $eskalasi = AiEscalation::query()->lockForUpdate()->findOrFail($id);

            if (! in_array($eskalasi->status, [AiEscalation::STATUS_BARU, AiEscalation::STATUS_DITUGASKAN], true)) {
                throw new InvalidArgumentException(
                    "Eskalasi #{$id} berstatus '{$eskalasi->status}' — sudah dijawab/ditutup."
                );
            }

            $eskalasi->update([
                'status' => AiEscalation::STATUS_DIJAWAB,
                'jawaban' => $teks,
                'dijawab_oleh' => $penjawab->getKey(),
                'dijawab_pada' => now(),
            ]);

            return $eskalasi->refresh();
        });

        $this->kirimJawabanKeKanal($eskalasi);

        $this->catatNotifikasi(
            jenis: 'ai_eskalasi_dijawab',
            target: 'admin_tenant',
            judul: 'Eskalasi Tanya Ustadz telah dijawab',
            pesan: sprintf(
                'Pertanyaan kategori "%s" (kanal %s) telah dijawab oleh %s.',
                $eskalasi->kategori,
                $eskalasi->kanal,
                $penjawab->name,
            ),
            eskalasi: $eskalasi,
        );

        return $eskalasi;
    }

    /** Tutup eskalasi (selesai ditindaklanjuti). */
    public function tutup(int $id, User $penutup): AiEscalation
    {
        $this->pastikanBoleh($penutup, 'ai.eskalasi.kelola');

        return DB::transaction(function () use ($id): AiEscalation {
            /** @var AiEscalation $eskalasi */
            $eskalasi = AiEscalation::query()->lockForUpdate()->findOrFail($id);

            if ($eskalasi->status === AiEscalation::STATUS_DITUTUP) {
                throw new InvalidArgumentException("Eskalasi #{$id} sudah ditutup.");
            }

            $eskalasi->update([
                'status' => AiEscalation::STATUS_DITUTUP,
                'ditutup_pada' => now(),
            ]);

            return $eskalasi->refresh();
        });
    }

    /**
     * Tugaskan otomatis seluruh eskalasi 'baru' yang menggantung lebih lama
     * dari config ai.eskalasi.auto_assign_menit ke tujuan default. Dipanggil
     * scheduled ai:eskalasi-pengingat. Tanpa tujuan default valid (setting
     * eskalasi_tujuan.penerima kosong) = tidak ada yang ditugaskan (0).
     */
    public function autoAssignBaru(): int
    {
        $menit = (int) config('ai.eskalasi.auto_assign_menit', 30);

        $ids = AiEscalation::query()
            ->where('status', AiEscalation::STATUS_BARU)
            ->where('created_at', '<=', now()->subMinutes($menit))
            ->orderBy('id')
            ->pluck('id');

        $jumlah = 0;

        foreach ($ids as $id) {
            try {
                $this->tugaskan((int) $id);
                $jumlah++;
            } catch (InvalidArgumentException $e) {
                // Tanpa tujuan default — kondisi sama untuk seluruh antrean,
                // hentikan dini. Bukan error sistem: admin belum mengisi
                // eskalasi_tujuan.penerima di Wizard.
                Log::info('EscalationService: auto-assign dilewati — tujuan eskalasi default belum disetel.', [
                    'eskalasi_id' => $id,
                    'message' => $e->getMessage(),
                ]);

                break;
            }
        }

        return $jumlah;
    }

    /**
     * Pengingat SLA: eskalasi 'ditugaskan' yang melewati sla_deadline dan
     * BELUM pernah diingatkan → notifikasi pengingat ke penerima tugas +
     * tandai diingatkan_pada (sekali per penugasan). Dipanggil scheduled.
     */
    public function kirimPengingatSla(): int
    {
        $jumlah = 0;

        AiEscalation::query()
            ->where('status', AiEscalation::STATUS_DITUGASKAN)
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->whereNull('diingatkan_pada')
            ->orderBy('id')
            ->get()
            ->each(function (AiEscalation $eskalasi) use (&$jumlah): void {
                $this->catatNotifikasi(
                    jenis: 'ai_eskalasi_pengingat',
                    target: 'user:'.$eskalasi->ditugaskan_ke,
                    judul: 'Pengingat: pertanyaan Tanya Ustadz melewati tenggat',
                    pesan: sprintf(
                        'Pertanyaan kategori "%s" (kanal %s) telah melewati tenggat jawab %s. '
                        .'Mohon berkenan menjawabnya. Jazakumullahu khairan.',
                        $eskalasi->kategori,
                        $eskalasi->kanal,
                        $eskalasi->sla_deadline?->translatedFormat('d F Y H:i'),
                    ),
                    eskalasi: $eskalasi,
                );

                $eskalasi->update(['diingatkan_pada' => now()]);
                $jumlah++;
            });

        return $jumlah;
    }

    /**
     * Teruskan jawaban manusia ke kanal asal penanya (PRD §7.6).
     *
     * ADAPTASI SADAR T-A023: kanal WA INBOUND belum ada (T-A050) dan
     * ai_escalation TIDAK menyimpan nomor penanya (hanya conversation_ref
     * terredaksi) — jalur WA keluar eksisting (WaNotificationService) terikat
     * mesin broadcast + template ber-approval Meta, bukan balasan satuan.
     * Maka v1: jawaban DIANGGAP terkirim ke "kanal panel" lewat kolom
     * `jawaban` (status dijawab), dan dicatat sebagai LogNotifikasi:
     *  - kanal 'wa'  → status 'pending' — penanda antrean kirim-balik yang
     *    akan dieksekusi T-A050 (TANPA membangun jalur WA baru di sini,
     *    Aturan Emas #5);
     *  - kanal lain  → status 'sent' (jawaban tersedia di panel/kanal asal).
     *
     * TODO T-A050: ganti cabang 'wa' dengan pengiriman nyata via kanal WA
     * inbound/outbound resmi platform begitu tersedia (pakai conversation_ref
     * untuk merutekan ke percakapan asal).
     */
    public function kirimJawabanKeKanal(AiEscalation $eskalasi): void
    {
        $viaWa = $eskalasi->kanal === 'wa';

        $this->catatNotifikasi(
            jenis: 'ai_eskalasi_jawaban_kanal',
            target: 'kanal:'.$eskalasi->kanal,
            judul: 'Jawaban Tanya Ustadz untuk penanya',
            pesan: $viaWa
                ? 'Jawaban tercatat dan menunggu kanal WA inbound (T-A050) untuk diteruskan ke penanya.'
                : 'Jawaban tersedia bagi penanya melalui kanal asal.',
            eskalasi: $eskalasi,
            status: $viaWa ? 'pending' : 'sent',
            payloadEkstra: [
                'conversation_ref' => $eskalasi->conversation_ref,
                'jawaban' => $eskalasi->jawaban,
            ],
        );
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /**
     * Tujuan penugasan default: entri PERTAMA yang valid (user-nya ada) dari
     * setting aktif `eskalasi_tujuan.penerima` (diisi admin lewat Wizard).
     */
    private function tujuanDefault(): int
    {
        $payload = (array) app(TenantSettingService::class)->aktif()?->payload;
        $penerima = (array) (($payload['eskalasi_tujuan'] ?? [])['penerima'] ?? []);

        foreach ($penerima as $kandidat) {
            $kandidat = (int) $kandidat;

            if ($kandidat > 0 && User::query()->whereKey($kandidat)->exists()) {
                return $kandidat;
            }
        }

        throw new InvalidArgumentException(
            'Tujuan eskalasi default belum disetel — mohon isi penerima "Tanya Ustadz" pada Wizard pengaturan AI.'
        );
    }

    /** SLA jam dari setting aktif; fallback config (default 24 jam). */
    private function slaJam(): int
    {
        $payload = (array) app(TenantSettingService::class)->aktif()?->payload;
        $slaJam = (int) (($payload['eskalasi_tujuan'] ?? [])['sla_jam'] ?? 0);

        return $slaJam > 0 ? $slaJam : (int) config('ai.eskalasi.sla_jam_default', 24);
    }

    private function pastikanUserAda(int $userId): int
    {
        if (! User::query()->whereKey($userId)->exists()) {
            throw new InvalidArgumentException("Pengguna #{$userId} tidak ditemukan.");
        }

        return $userId;
    }

    private function pastikanBoleh(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException(
                "Mohon maaf, Anda tidak memiliki izin '{$permission}' untuk tindakan ini."
            );
        }
    }

    /**
     * Notifikasi database (pola BudgetGuard/AiAnomaliCommand). Best-effort:
     * kegagalan menulis notifikasi TIDAK menggagalkan mutasi antrean —
     * antrean durable lebih penting daripada lonceng pemberitahuan.
     *
     * @param  array<string, mixed>  $payloadEkstra
     */
    private function catatNotifikasi(
        string $jenis,
        string $target,
        string $judul,
        string $pesan,
        AiEscalation $eskalasi,
        string $status = 'sent',
        array $payloadEkstra = [],
    ): void {
        try {
            LogNotifikasi::query()->create([
                'jenis' => $jenis,
                'channel' => 'database',
                'target' => $target,
                'status' => $status,
                'payload' => array_merge([
                    'judul' => $judul,
                    'pesan' => $pesan,
                    'eskalasi_id' => $eskalasi->getKey(),
                    'kategori' => $eskalasi->kategori,
                    'kanal' => $eskalasi->kanal,
                ], $payloadEkstra),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('EscalationService: GAGAL menulis notifikasi eskalasi.', [
                'jenis' => $jenis,
                'eskalasi_id' => $eskalasi->getKey(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
