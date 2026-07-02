<?php

namespace Modules\AI\Services\Wa;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\AI\Models\AiConversation;
use Modules\AI\Models\AiMessage;
use Modules\AI\Services\Gateway\AiGenerateRequest;
use Modules\AI\Services\Gateway\AiGenerateResult;
use Modules\AI\Services\Guardrails\PolicyEngine;
use Modules\AI\Services\Rag\RagAnswerer;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\AI\Services\Tools\ToolRegistry;
use Modules\Kesantrian\Models\Santri;

/**
 * T-A051 — orkestrasi chatbot WA wali: verifikasi → intent → tool/template
 * → free-form RAG (urutan Blueprint §7.1, dipanggil ProsesPesanWaJob saat
 * mode_uji=false; opt-out/opt-in sudah ditangani job SEBELUM kelas ini).
 *
 * Kontrak inti:
 *  - TEMPLATE-FIRST: mayoritas trafik = intent keyword → ToolRegistry →
 *    ToolResult::keTeks + footer sumber+cap waktu (DisclaimerInjector via
 *    PolicyEngine::postNonRag) — NOL panggilan LLM;
 *  - ANTI-KARANG ANGKA: nominal/jadwal HANYA dari ToolResult; teks bernada
 *    uang dipaksa ke tool oleh IntentRouter SEBELUM RAG;
 *  - PolicyEngine::pre menggerbangi SEMUA trafik wali terverifikasi (fitur
 *    wali.chatbot Wizard, jam layanan kanal wa, blacklist, level fatwa) —
 *    dipakai apa adanya, TIDAK dibangun ulang; blokir = balasan template
 *    keputusan (+ eskalasi otomatis oleh engine);
 *  - nomor tak terverifikasi: HANYA info publik (PPDB umum/jam layanan/menu)
 *    + tawaran kontak admin — tool & RAG tidak pernah disentuh;
 *  - IDEMPOTEN per wa_message_id: balasan ditandai meta.balasan_untuk; retry
 *    job yang sudah terbalas berhenti diam-diam (kiriman & metering tak dobel);
 *  - SEMUA balasan via WaBalasanService (jalur outbound resmi Notifikasi,
 *    Aturan Emas #5) — tercatat ai_message keluar TERREDAKSI;
 *  - T-A052 HANDOFF MANUSIA (PRD §8.2): wali terverifikasi minta manusia
 *    (keyword config) → konfirmasi + status handoff; jawaban free-form
 *    degraded/tanpa-rujukan beruntun (ai_conversation.gagal_beruntun)
 *    mencapai ambang → tawarkan handoff otomatis. Saat status=handoff,
 *    kelas ini TIDAK pernah dipanggil (ProsesPesanWaJob mendiamkan bot).
 */
class WaChatService
{
    public function __construct(
        private readonly WaBalasanService $balasan,
        private readonly WaliVerifier $verifier,
        private readonly IntentRouter $router,
        private readonly ToolRegistry $tools,
        private readonly PolicyEngine $policy,
        private readonly RagAnswerer $rag,
        private readonly WaHandoffService $handoff,
    ) {}

    public function proses(AiConversation $conversation, AiMessage $masuk, string $isi): void
    {
        // Idempotensi retry job: pesan ini sudah pernah terbalas → selesai.
        if ($this->sudahTerbalas($masuk)) {
            return;
        }

        // ------------------------------------------------------------------
        // 1. Verifikasi wali (PRD §8.2 — verifikasi ringan sesi pertama)
        // ------------------------------------------------------------------
        $wali = $this->waliTerverifikasi($conversation);

        if ($wali === null) {
            $this->prosesBelumTerverifikasi($conversation, $masuk, $isi);

            return;
        }

        // ------------------------------------------------------------------
        // 1b. T-A052 — wali minta dilayani MANUSIA (keyword config): set
        //     handoff DULU (admin pasti ternotifikasi walau kirim konfirmasi
        //     gagal), lalu konfirmasi santun. Permintaan meta ini sengaja
        //     SEBELUM PolicyEngine — minta admin bukan trafik AI.
        // ------------------------------------------------------------------
        if ($this->handoff->mintaManusia($isi)) {
            $this->handoff->mulaiHandoff($conversation, WaHandoffService::PEMICU_DIMINTA_WALI);
            $this->balas($conversation, $masuk, $this->template('balasan_handoff_diminta'), ['jenis' => 'handoff_diminta']);

            return;
        }

        // ------------------------------------------------------------------
        // 2. Guardrails — PolicyEngine pre menggerbangi seluruh trafik wali
        //    terverifikasi (fitur Wizard, jam layanan, blacklist, level fatwa).
        //    Eskalasi Tanya Ustadz ditulis engine sendiri (idempoten per key).
        // ------------------------------------------------------------------
        $request = new AiGenerateRequest(
            prompt: $isi,
            agent: (string) config('ai.wa.agent_key', 'wali_chat'),
            tier: 'ekonomis',
            channel: 'wa',
            userId: (int) $wali->getKey(),
            idempotencyKey: $this->kunciIdempotensi($masuk),
        );

        $keputusan = $this->policy->pre($request, (string) tenant()?->getTenantKey());

        if (! $keputusan->boleh) {
            $this->balas($conversation, $masuk, (string) ($keputusan->responsTemplate
                ?? config('ai.guardrails.pesan.blacklist_topik')), [
                    'jenis' => 'policy_blokir',
                    'kategori' => $keputusan->kategori,
                ]);

            return;
        }

        // ------------------------------------------------------------------
        // 3. Intent rule-based (TANPA LLM) → tool/template
        // ------------------------------------------------------------------
        $intent = $this->router->route($isi);

        match (true) {
            $intent === 'ganti_anak' => $this->prosesGantiAnak($conversation, $masuk, $isi, $wali),
            $intent === 'bantuan' => $this->balas($conversation, $masuk, $this->template('balasan_menu'), ['jenis' => 'intent_menu']),
            $intent === 'ppdb' => $this->balas($conversation, $masuk, $this->teksInfoPpdb(), ['jenis' => 'intent_ppdb']),
            $intent !== null => $this->prosesIntentTool($conversation, $masuk, $isi, $wali, $intent),
            default => $this->prosesFreeForm($conversation, $masuk, $request),
        };
    }

    // ------------------------------------------------------------------
    // Jalur belum terverifikasi
    // ------------------------------------------------------------------

    private function prosesBelumTerverifikasi(AiConversation $conversation, AiMessage $masuk, string $isi): void
    {
        // Kunci 24 jam masih berlaku → tolak santun, sarankan admin.
        if ($conversation->verifikasi_terkunci_sampai !== null) {
            if ($conversation->verifikasi_terkunci_sampai->isFuture()) {
                $this->balas($conversation, $masuk, $this->template('balasan_terkunci'), ['jenis' => 'verifikasi_terkunci']);

                return;
            }

            // Kunci kedaluwarsa → reset hitungan, lanjut alur normal.
            $conversation->update([
                'verifikasi_terkunci_sampai' => null,
                'verifikasi_percobaan' => 0,
            ]);
        }

        if ($conversation->verifikasi_status === AiConversation::VERIFIKASI_MENUNGGU_TGL_LAHIR) {
            $this->prosesJawabanTanggalLahir($conversation, $masuk, $isi);

            return;
        }

        // Status 'belum': cocokkan nomor pengirim ke wali eksisting.
        $kandidat = $this->verifier->kandidat($conversation);

        if ($kandidat !== null) {
            $conversation->update([
                'verifikasi_status' => AiConversation::VERIFIKASI_MENUNGGU_TGL_LAHIR,
                'wali_user_id' => $kandidat->getKey(),
                'verifikasi_percobaan' => 0,
            ]);

            $this->balas($conversation, $masuk, $this->template('balasan_minta_tgl_lahir'), ['jenis' => 'verifikasi_minta_tgl']);

            return;
        }

        // Nomor tak dikenal → HANYA info publik (PPDB umum / jam layanan).
        $intent = $this->router->route($isi);

        match ($intent) {
            'ppdb' => $this->balas($conversation, $masuk, $this->teksInfoPpdb(), ['jenis' => 'publik_ppdb']),
            'bantuan' => $this->balas($conversation, $masuk, $this->template('balasan_menu_publik'), ['jenis' => 'publik_menu']),
            default => $this->balas($conversation, $masuk, $this->template('balasan_tidak_dikenal'), ['jenis' => 'publik_tak_dikenal']),
        };
    }

    private function prosesJawabanTanggalLahir(AiConversation $conversation, AiMessage $masuk, string $isi): void
    {
        $kandidat = $conversation->wali_user_id !== null
            ? User::query()->find($conversation->wali_user_id)
            : null;

        if ($kandidat === null) {
            // Akun kandidat hilang (dihapus admin) → mundur ke alur awal.
            $conversation->update([
                'verifikasi_status' => AiConversation::VERIFIKASI_BELUM,
                'wali_user_id' => null,
            ]);
            $this->balas($conversation, $masuk, $this->template('balasan_tidak_dikenal'), ['jenis' => 'publik_tak_dikenal']);

            return;
        }

        $tanggal = WaliVerifier::parseTanggal($isi);

        if ($tanggal === null) {
            // Bukan tanggal — ingatkan format; layani intent publik bila ada.
            $intent = $this->router->route($isi);

            if ($intent === 'ppdb') {
                $this->balas($conversation, $masuk, $this->teksInfoPpdb(), ['jenis' => 'publik_ppdb']);

                return;
            }

            $this->balas($conversation, $masuk, $this->template('balasan_bukan_tanggal'), ['jenis' => 'verifikasi_minta_tgl']);

            return;
        }

        $cocok = $this->verifier->cocokkanTanggalLahir($kandidat, $tanggal);

        if ($cocok === null) {
            $percobaan = (int) $conversation->verifikasi_percobaan + 1;
            $maks = (int) config('ai.wa.verifikasi.maks_percobaan', 3);

            if ($percobaan >= $maks) {
                $conversation->update([
                    'verifikasi_percobaan' => $percobaan,
                    'verifikasi_terkunci_sampai' => now()->addHours(
                        (int) config('ai.wa.verifikasi.kunci_jam', 24),
                    ),
                ]);
                $this->balas($conversation, $masuk, $this->template('balasan_terkunci'), ['jenis' => 'verifikasi_terkunci']);

                return;
            }

            $conversation->update(['verifikasi_percobaan' => $percobaan]);
            $this->balas($conversation, $masuk, $this->template('balasan_tgl_salah', [
                ':percobaan' => (string) $percobaan,
                ':maks' => (string) $maks,
            ]), ['jenis' => 'verifikasi_salah']);

            return;
        }

        // Terverifikasi — default sesi = anak pertama (is_primary dulu).
        $anakPertama = $this->verifier->anakTertaut($kandidat)->first();

        $conversation->update([
            'verifikasi_status' => AiConversation::VERIFIKASI_TERVERIFIKASI,
            'verifikasi_percobaan' => 0,
            'verifikasi_terkunci_sampai' => null,
            'santri_id' => $anakPertama?->getKey(),
        ]);

        $this->balas($conversation, $masuk, $this->template('balasan_terverifikasi', [
            ':nama' => (string) ($anakPertama?->nama ?? '-'),
        ]), ['jenis' => 'verifikasi_sukses']);
    }

    // ------------------------------------------------------------------
    // Jalur terverifikasi: intent → tool/template
    // ------------------------------------------------------------------

    private function prosesIntentTool(
        AiConversation $conversation,
        AiMessage $masuk,
        string $isi,
        User $wali,
        string $intent,
    ): void {
        $peta = (array) config("ai.wa.intent_tool_map.{$intent}");
        $tool = (string) ($peta['tool'] ?? '');

        if ($tool === '') {
            // Intent tanpa pemetaan tool → perlakukan sebagai menu bantuan.
            $this->balas($conversation, $masuk, $this->template('balasan_menu'), ['jenis' => 'intent_menu']);

            return;
        }

        $santri = $this->santriAktif($conversation, $isi, $wali);

        if ($santri === null) {
            $this->balas($conversation, $masuk, $this->template('balasan_tanpa_anak'), ['jenis' => 'intent_tool', 'intent' => $intent]);

            return;
        }

        $args = $this->susunArgs($tool, $santri);

        if ($args === null) {
            // getJadwal tanpa kelas terdaftar — jawab santun tanpa mengarang.
            $this->balas($conversation, $masuk, $this->template('balasan_kelas_belum_ada', [
                ':nama' => (string) $santri->nama,
            ]), ['jenis' => 'intent_tool', 'intent' => $intent]);

            return;
        }

        $hasil = $this->tools->call($tool, $args, $wali);

        if (! $hasil->sukses) {
            // Pesan kegagalan registry sudah santun & tanpa angka.
            $this->balas($conversation, $masuk, $hasil->keTeks(), ['jenis' => 'intent_tool', 'intent' => $intent, 'tool' => $tool, 'sukses' => false]);

            return;
        }

        // Footer sumber+cap waktu + disclaimer kategori data — via
        // PolicyEngine::postNonRag (DisclaimerInjector), tanpa LLM.
        $kategori = (string) ($peta['kategori'] ?? 'umum');

        $dirakit = $this->policy->postNonRag(
            new AiGenerateResult(
                text: $this->bukaDenganNamaAnak($hasil->keTeks(denganFooter: false), $santri),
                provider: 'template',
                model: 'template',
            ),
            $kategori,
            $hasil->capWaktu,
            $hasil->sumber,
        );

        // T-A052: jawaban data sukses memutus rentetan gagal free-form.
        $this->resetGagalBeruntun($conversation);

        $this->balas($conversation, $masuk, $dirakit->text, [
            'jenis' => 'intent_tool',
            'intent' => $intent,
            'tool' => $tool,
            'sukses' => true,
            'santri_id' => $santri->getKey(),
        ]);
    }

    private function prosesGantiAnak(AiConversation $conversation, AiMessage $masuk, string $isi, User $wali): void
    {
        $anak = $this->verifier->anakTertaut($wali);

        if ($anak->isEmpty()) {
            $this->balas($conversation, $masuk, $this->template('balasan_tanpa_anak'), ['jenis' => 'ganti_anak']);

            return;
        }

        $pilihan = $this->cariNamaAnak($isi, $anak);

        if ($pilihan === null) {
            $daftar = $anak->map(fn (Santri $s): string => '- '.$s->nama)->implode("\n");
            $this->balas($conversation, $masuk, $this->template('balasan_ganti_anak_pilihan', [
                ':daftar' => $daftar,
            ]), ['jenis' => 'ganti_anak']);

            return;
        }

        $conversation->update(['santri_id' => $pilihan->getKey()]);

        $this->balas($conversation, $masuk, $this->template('balasan_ganti_anak_sukses', [
            ':nama' => (string) $pilihan->nama,
        ]), ['jenis' => 'ganti_anak', 'santri_id' => $pilihan->getKey()]);
    }

    // ------------------------------------------------------------------
    // Jalur free-form (RAG bersitasi — satu-satunya jalur ber-LLM)
    // ------------------------------------------------------------------

    private function prosesFreeForm(AiConversation $conversation, AiMessage $masuk, AiGenerateRequest $request): void
    {
        // Kategori 'umum' — disclaimer agama/keuangan TIDAK relevan di sini;
        // pertanyaan agama sudah dialihkan PolicyEngine SEBELUM titik ini,
        // dan pertanyaan uang sudah dipaksa ke tool oleh IntentRouter.
        $hasil = $this->rag->jawab($request, ['kategori_jawaban' => 'umum']);

        // T-A052 — jawaban GAGAL = degradasi provider ATAU tanpa-rujukan/
        // gagal-sitasi (RagAnswerer mengembalikan provider 'null' untuk
        // keduanya). Tolakan kebijakan BUKAN kegagalan menjawab.
        $gagal = $hasil->degraded
            || ($hasil->provider === 'null' && ! $hasil->ditolakPolicy);

        $meta = [
            'jenis' => 'rag',
            'degraded' => $hasil->degraded,
            'ditolak_policy' => $hasil->ditolakPolicy,
        ];

        if (! $gagal) {
            $this->resetGagalBeruntun($conversation);
            $this->balas($conversation, $masuk, $hasil->text, $meta);

            return;
        }

        $beruntun = (int) $conversation->gagal_beruntun + 1;
        $conversation->update(['gagal_beruntun' => $beruntun]);

        if ($beruntun < max(1, (int) config('ai.wa.handoff.maks_gagal_beruntun', 2))) {
            $this->balas($conversation, $masuk, $hasil->text, $meta + ['gagal_beruntun' => $beruntun]);

            return;
        }

        // Ambang tercapai → tawarkan handoff otomatis + status handoff
        // (handoff DULU supaya admin pasti ternotifikasi; lihat 1b).
        $this->handoff->mulaiHandoff($conversation, WaHandoffService::PEMICU_GAGAL_BERUNTUN);

        $this->balas(
            $conversation,
            $masuk,
            $hasil->text."\n\n".$this->template('balasan_handoff_otomatis'),
            ['jenis' => 'handoff_otomatis', 'gagal_beruntun' => $beruntun] + $meta,
        );
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /** User wali percakapan TERVERIFIKASI (null = belum/akun hilang). */
    private function waliTerverifikasi(AiConversation $conversation): ?User
    {
        if ($conversation->verifikasi_status !== AiConversation::VERIFIKASI_TERVERIFIKASI
            || $conversation->wali_user_id === null) {
            return null;
        }

        $wali = User::query()->find($conversation->wali_user_id);

        if ($wali === null) {
            // Akun wali dihapus — verifikasi gugur, mundur ke alur awal.
            $conversation->update([
                'verifikasi_status' => AiConversation::VERIFIKASI_BELUM,
                'wali_user_id' => null,
                'santri_id' => null,
            ]);

            return null;
        }

        return $wali;
    }

    /**
     * Santri konteks jawaban: nama anak yang DISEBUT di pesan menang,
     * lalu anak aktif sesi (santri_id percakapan), lalu anak pertama.
     */
    private function santriAktif(AiConversation $conversation, string $isi, User $wali): ?Santri
    {
        $anak = $this->verifier->anakTertaut($wali);

        if ($anak->isEmpty()) {
            return null;
        }

        $disebut = $this->cariNamaAnak($isi, $anak);

        if ($disebut !== null) {
            return $disebut;
        }

        if ($conversation->santri_id !== null) {
            $aktif = $anak->first(fn (Santri $s): bool => (int) $s->getKey() === (int) $conversation->santri_id);

            if ($aktif !== null) {
                return $aktif;
            }
        }

        return $anak->first();
    }

    /**
     * Cari anak tertaut yang namanya disebut dalam pesan (kata-utuh,
     * case-insensitive; token nama < 3 huruf diabaikan agar tidak false
     * positive). null = tidak ada/tidak yakin.
     *
     * @param  Collection<int, Santri>  $anak
     */
    private function cariNamaAnak(string $isi, $anak): ?Santri
    {
        $teks = mb_strtolower($isi);

        foreach ($anak as $santri) {
            foreach (preg_split('/\s+/u', mb_strtolower((string) $santri->nama)) ?: [] as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }

                $regex = '/(?<![\p{L}\p{N}])'.preg_quote($token, '/').'(?![\p{L}\p{N}])/u';

                if (preg_match($regex, $teks) === 1) {
                    return $santri;
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null null = prasyarat data kurang (kelas) */
    private function susunArgs(string $tool, Santri $santri): ?array
    {
        if ($tool === 'getJadwal') {
            $kelasId = $santri->kelas_id ?? $santri->penempatanAktif?->kelas_id;

            return $kelasId === null ? null : ['kelas_id' => (int) $kelasId];
        }

        return ['santri_id' => (int) $santri->getKey()];
    }

    /** Awali jawaban tool dengan nama anak — jelas saat wali multi-anak. */
    private function bukaDenganNamaAnak(string $teks, Santri $santri): string
    {
        return 'Data ananda '.$santri->nama.":\n".$teks;
    }

    private function teksInfoPpdb(): string
    {
        $jam = '';

        try {
            $setting = app(TenantSettingService::class)->aktif();
            $jamLayanan = (array) (((array) ($setting?->payload ?? []))['jam_layanan'] ?? []);

            if (isset($jamLayanan['mulai'], $jamLayanan['selesai'])) {
                $jam = "\nJam layanan: {$jamLayanan['mulai']}–{$jamLayanan['selesai']}.";
            }
        } catch (\Throwable) {
            // Info jam layanan opsional — tanpa setting pun balasan tetap utuh.
        }

        return $this->template('balasan_info_ppdb', [':jam_layanan' => $jam]);
    }

    /** T-A052: jawaban sukses memutus rentetan gagal degraded/tanpa-rujukan. */
    private function resetGagalBeruntun(AiConversation $conversation): void
    {
        if ((int) $conversation->gagal_beruntun !== 0) {
            $conversation->update(['gagal_beruntun' => 0]);
        }
    }

    private function kunciIdempotensi(AiMessage $masuk): string
    {
        return 'wa-'.($masuk->wa_message_id ?? 'msg-'.$masuk->getKey());
    }

    private function sudahTerbalas(AiMessage $masuk): bool
    {
        if ($masuk->wa_message_id === null) {
            return false;
        }

        return AiMessage::query()
            ->where('arah', AiMessage::ARAH_KELUAR)
            ->where('meta->balasan_untuk', $masuk->wa_message_id)
            ->exists();
    }

    /** Satu pintu balasan: tandai meta.balasan_untuk untuk idempotensi retry. */
    private function balas(AiConversation $conversation, AiMessage $masuk, string $pesan, array $meta = []): void
    {
        $this->balasan->balas($conversation, $pesan, $meta + [
            'balasan_untuk' => $masuk->wa_message_id,
        ]);
    }

    /** @param  array<string, string>  $ganti */
    private function template(string $kunci, array $ganti = []): string
    {
        $teks = (string) config(
            "ai.wa.{$kunci}",
            'Mohon maaf, layanan belum dapat memproses permintaan ini. Silakan hubungi admin pesantren.',
        );

        return $ganti === [] ? $teks : strtr($teks, $ganti);
    }
}
