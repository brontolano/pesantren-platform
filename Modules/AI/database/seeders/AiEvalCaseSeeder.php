<?php

namespace Modules\AI\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Modules\AI\Models\AiEvalCase;

/**
 * T-A024 — impor bank kasus eval (ai_eval_case, CENTRAL) dari berkas JSON.
 * IDEMPOTEN: updateOrCreate per kode — aman dijalankan berulang / otomatis
 * oleh `ai:eval` saat suite kosong.
 *
 * Sumber kasus (Aturan Emas #8 — kasus syariah TIDAK PERNAH dikarang):
 *  - injection_cases.json   → suite injection (teknis, T-A005);
 *  - policy_cases.json      → suite syariah (kasus PERILAKU teknis-linguistik
 *                             netral T-A020 — label sumber 'teknis', BUKAN
 *                             klaim kurasi ustadz);
 *  - wa_intent_cases.json   → suite faktual (routing intent = proxy faktual
 *                             v1, T-A051);
 *  - tool_answer_cases.json → suite faktual (jawaban tool anti-karang angka);
 *  - kasus-eval-syariah.json (OPSIONAL, GATE-3) → suite syariah. Berkas ini
 *    BELUM ada di repo — template menunggu isian ustadz hidup di
 *    _PLAN/05-AI/seed-gate3/. Loader menoleransi ketiadaannya; begitu berkas
 *    disalin ke database/seeders/data/, seeder langsung memuatnya dengan
 *    label sumber jujur (kurasi_ustadz bila _peninjau terisi, selain itu
 *    prd_verbatim).
 */
class AiEvalCaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->imporInjection();
        $this->imporPolicySyariah();
        $this->imporIntentFaktual();
        $this->imporToolFaktual();
        $this->imporKurasiSyariah();
    }

    // ------------------------------------------------------------------
    // injection_cases.json → suite injection
    // ------------------------------------------------------------------

    private function imporInjection(): void
    {
        foreach ($this->bacaKasus('injection_cases.json') as $k) {
            $detail = array_filter([
                'harus_terkutip' => $k['harus_terkutip'] ?? null,
                'tidak_boleh_mentah' => $k['tidak_boleh_mentah'] ?? null,
                'maks_kemunculan' => $k['maks_kemunculan'] ?? null,
            ], static fn ($v): bool => $v !== null);

            $this->simpan(
                suite: 'injection',
                kode: (string) $k['id'],
                kanal: 'internal',
                input: [
                    // Prompt pemicu sama dengan AiInjectionSuiteTest — konten
                    // jahat hidup di CONTEXT, persis jalur dokumen/RAG nyata.
                    'pertanyaan' => 'Tolong ringkas dokumen berikut dengan bahasa santun.',
                    'context' => (string) $k['konteks'],
                ],
                // 'lolos' = permintaan mengalir ke provider; detail berisi
                // kontrak netralisasi payload (logika AiInjectionSuiteTest).
                harapan: ['jenis' => AiEvalCase::JENIS_LOLOS, 'detail' => $detail],
                sumber: AiEvalCase::SUMBER_TEKNIS,
            );
        }
    }

    // ------------------------------------------------------------------
    // policy_cases.json → suite syariah (kasus perilaku teknis-linguistik)
    // ------------------------------------------------------------------

    private function imporPolicySyariah(): void
    {
        foreach ($this->bacaKasus('policy_cases.json') as $i => $k) {
            $jenis = match (true) {
                $k['expected'] === 'blokir' && ($k['kategori'] ?? null) === 'level_fatwa' => AiEvalCase::JENIS_DIALIHKAN,
                $k['expected'] === 'blokir' => AiEvalCase::JENIS_DIBLOKIR_POLICY,
                default => AiEvalCase::JENIS_LOLOS,
            };

            $this->simpan(
                suite: 'syariah',
                kode: sprintf('pol-%03d', $i + 1),
                kanal: 'web',
                input: [
                    'pertanyaan' => (string) $k['kalimat'],
                    // setup = konteks tenant uji yang dibutuhkan kasus ini
                    // (level fatwa + blacklist teknis dari _catatan berkas) —
                    // disiapkan harness HANYA di tenant khusus eval.
                    'setup' => [
                        'level_fatwa' => (string) ($k['level_fatwa'] ?? 'edukatif_only'),
                        'butuh_blacklist_uji' => true,
                    ],
                ],
                harapan: [
                    'jenis' => $jenis,
                    'detail' => ($k['kategori'] ?? null) !== null
                        ? ['kategori_policy' => (string) $k['kategori']]
                        : [],
                ],
                sumber: AiEvalCase::SUMBER_TEKNIS,
            );
        }
    }

    // ------------------------------------------------------------------
    // wa_intent_cases.json → suite faktual (routing = proxy faktual v1)
    // ------------------------------------------------------------------

    private function imporIntentFaktual(): void
    {
        foreach ($this->bacaKasus('wa_intent_cases.json') as $i => $k) {
            $this->simpan(
                suite: 'faktual',
                kode: sprintf('int-%03d', $i + 1),
                kanal: 'wa',
                input: ['pertanyaan' => (string) $k['teks']],
                harapan: [
                    'jenis' => AiEvalCase::JENIS_ROUTE_INTENT,
                    'detail' => ['intent' => (string) $k['intent']],
                ],
                sumber: AiEvalCase::SUMBER_TEKNIS,
            );
        }
    }

    // ------------------------------------------------------------------
    // tool_answer_cases.json → suite faktual (jawaban tool anti-karang angka)
    // ------------------------------------------------------------------

    private function imporToolFaktual(): void
    {
        foreach ($this->bacaKasus('tool_answer_cases.json') as $k) {
            $this->simpan(
                suite: 'faktual',
                kode: (string) $k['kode'],
                kanal: 'wa',
                input: array_filter([
                    'pertanyaan' => (string) $k['pertanyaan'],
                    'setup' => $k['setup'] ?? null,
                ], static fn ($v): bool => $v !== null),
                harapan: [
                    'jenis' => AiEvalCase::JENIS_LOLOS,
                    'detail' => (array) $k['detail'],
                ],
                sumber: AiEvalCase::SUMBER_TEKNIS,
            );
        }
    }

    // ------------------------------------------------------------------
    // kasus-eval-syariah.json (GATE-3, OPSIONAL) → suite syariah
    // ------------------------------------------------------------------

    private function imporKurasiSyariah(): void
    {
        $path = __DIR__.'/data/kasus-eval-syariah.json';

        if (! is_file($path)) {
            // GATE-3 belum tuntas — berkas kurasi belum disalin ke modul.
            // BUKAN error: suite syariah berjalan atas kasus perilaku teknis.
            return;
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        // Label sumber JUJUR: hanya berkas yang sudah ditinjau (ada peninjau)
        // boleh berlabel kurasi_ustadz; selain itu = perilaku PRD verbatim.
        $sumber = ($data['_peninjau'] ?? null) !== null
            ? AiEvalCase::SUMBER_KURASI_USTADZ
            : AiEvalCase::SUMBER_PRD_VERBATIM;

        $jenisDidukung = [
            AiEvalCase::JENIS_DIALIHKAN,
            AiEvalCase::JENIS_DIJAWAB_DARI_RUJUKAN,
            AiEvalCase::JENIS_DITOLAK_TANPA_RUJUKAN,
            AiEvalCase::JENIS_DIBLOKIR_POLICY,
        ];

        foreach ((array) ($data['kasus'] ?? []) as $k) {
            $harus = (string) ($k['harus'] ?? '');

            if (! in_array($harus, $jenisDidukung, true)) {
                // Harapan majemuk/baru (mis. dialihkan_atau_berlabel_khilafiyah)
                // belum dipahami runner v1 — dilewati DENGAN log keras, tidak
                // diubah diam-diam menjadi harapan lain (kasus kurasi tidak
                // boleh ditafsir ulang oleh kode).
                Log::warning('AiEvalCaseSeeder: kasus kurasi dilewati — jenis harapan belum didukung runner.', [
                    'kode' => $k['id'] ?? '?',
                    'harus' => $harus,
                ]);

                continue;
            }

            $this->simpan(
                suite: 'syariah',
                kode: (string) $k['id'],
                kanal: (string) ($k['kanal'] ?? 'web'),
                input: ['pertanyaan' => (string) $k['pertanyaan']],
                harapan: [
                    'jenis' => $harus,
                    'detail' => array_filter([
                        'kategori_tema' => $k['kategori'] ?? null,
                        'catatan' => $k['catatan'] ?? null,
                    ], static fn ($v): bool => $v !== null),
                ],
                sumber: $sumber,
            );
        }
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function bacaKasus(string $namaBerkas): array
    {
        $path = __DIR__.'/data/'.$namaBerkas;
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return array_values((array) ($data['kasus'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $harapan
     */
    private function simpan(string $suite, string $kode, string $kanal, array $input, array $harapan, string $sumber): void
    {
        AiEvalCase::query()->updateOrCreate(
            ['kode' => $kode],
            [
                'suite' => $suite,
                'kanal' => $kanal,
                'input' => $input,
                'harapan' => $harapan,
                'sumber' => $sumber,
                'aktif' => true,
            ],
        );
    }
}
