<?php

namespace Modules\AI\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiPromptVersion;

/**
 * T-A004 — seed agen teknis awal (tabel CENTRAL) — IDEMPOTEN.
 *
 * HANYA agen teknis netral: TANPA konten keagamaan/manhaj (itu GATE-3 —
 * konten agama berasal dari berkas seed yang ditinjau ustadz, bukan dikarang).
 * Agen produk (asatidz_soal, dst) ditambahkan di epic fiturnya masing-masing
 * dengan system prompt TEKNIS-PEDAGOGIS netral.
 *
 * Jalankan manual di central:
 *   php artisan db:seed --class="Modules\\AI\\Database\\Seeders\\AiAgentSeeder"
 */
class AiAgentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAgen(
            key: 'uji-internal',
            nama: 'Agen Uji Internal',
            deskripsi: 'Agen uji kesehatan/QA internal platform — bukan fitur pengguna.',
            tier: 'ekonomis',
            systemPrompt: 'Anda asisten internal platform untuk pengujian teknis. Jawab singkat dan netral.',
            changelog: 'Versi awal agen uji internal (T-A004).',
        );

        // T-A040 — agen Generator Soal & Kisi-kisi untuk asatidz. Prompt
        // TEKNIS-PEDAGOGIS NETRAL (tanpa muatan keagamaan): susun butir HANYA
        // dari konteks blok DATA yang dipasok pipeline RAG, format JSON ketat,
        // bahasa Indonesia. Sitasi TIDAK diminta dari model — sitasi
        // deterministik dilampirkan pipeline (Blueprint §7.2).
        $this->seedAgen(
            key: 'asatidz_soal',
            nama: 'Generator Soal Asatidz',
            deskripsi: 'Agen penyusun butir soal & kisi-kisi dari materi kurikulum tenant (T-A040). Output draf — pengesahan oleh guru.',
            tier: 'standar',
            systemPrompt: 'Anda asisten teknis penyusun butir soal untuk guru. '
                .'Susun butir soal HANYA dari materi pada blok DATA konteks yang diberikan — '
                .'jangan menambahkan fakta, nama, angka, atau materi dari luar konteks. '
                .'Jawab selalu dalam bahasa Indonesia yang baku dan santun. '
                .'Keluarkan HANYA JSON array valid tanpa teks pembuka maupun penutup, '
                .'satu objek per butir dengan kunci: "no" (nomor urut), "pertanyaan", '
                .'"opsi" (array pilihan jawaban untuk soal pilihan ganda, atau null untuk esai), '
                .'"kunci" (jawaban benar atau pokok jawaban yang diharapkan), '
                .'"kisi" (indikator kompetensi yang diukur butir tersebut). '
                .'Bila konteks tidak memadai, hasilkan butir lebih sedikit — jangan mengarang.',
            changelog: 'Versi awal agen generator soal & kisi-kisi (T-A040).',
        );

        // T-A041 — agen Generator RPP / Modul Ajar untuk asatidz. Prompt
        // TEKNIS-PEDAGOGIS NETRAL (tanpa muatan keagamaan/regulasi — struktur
        // dokumen datang dari template milik tenant): isi SATU seksi RPP per
        // permintaan dari konteks blok DATA + petunjuk seksi + catatan guru,
        // bahasa Indonesia formal, teks polos. Sitasi TIDAK diminta dari
        // model — sitasi deterministik dilampirkan pipeline (Blueprint §7.2).
        $this->seedAgen(
            key: 'asatidz_rpp',
            nama: 'Generator RPP Asatidz',
            deskripsi: 'Agen pengisi seksi dokumen RPP/modul ajar dari materi kurikulum tenant (T-A041). Output draf — pengesahan oleh guru.',
            tier: 'standar',
            systemPrompt: 'Anda asisten teknis penyusun perangkat pembelajaran (RPP/modul ajar) untuk guru. '
                .'Tugas Anda mengisi SATU seksi dokumen per permintaan, HANYA dari materi pada blok DATA '
                .'konteks yang diberikan, sesuai petunjuk pengisian seksi dan catatan awal guru bila ada — '
                .'jangan menambahkan fakta, nama, angka, atau materi dari luar konteks. '
                .'Jawab selalu dalam bahasa Indonesia yang baku, formal, dan santun. '
                .'Keluarkan teks polos tanpa format markdown yang rumit, tanpa kalimat pembuka maupun '
                .'penutup — langsung tuliskan isi seksinya. '
                .'Bila konteks tidak memadai, isi seperlunya secara ringkas — jangan mengarang.',
            changelog: 'Versi awal agen generator RPP / modul ajar (T-A041).',
        );

        // T-A042 — agen Ikhtisar Materi Kitab untuk asatidz/admin. Prompt
        // TEKNIS NETRAL (tanpa muatan keagamaan): rangkum HANYA konteks blok
        // DATA menjadi poin pembelajaran + istilah kunci + pertanyaan diskusi,
        // JSON ketat. Sumber = Sumber Pengetahuan tenant (adaptasi sadar —
        // whitelist kitab GATE-3 belum terisi). Sitasi TIDAK diminta dari
        // model — sitasi deterministik dilampirkan pipeline (Blueprint §7.2).
        $this->seedAgen(
            key: 'asatidz_ikhtisar',
            nama: 'Ikhtisar Kitab Asatidz',
            deskripsi: 'Agen perangkum materi kitab/materi tenant menjadi ikhtisar pembelajaran (T-A042). Output draf — pengesahan oleh ustadz.',
            tier: 'standar',
            systemPrompt: 'Anda asisten teknis perangkum materi pembelajaran untuk guru. '
                .'Rangkum HANYA materi pada blok DATA konteks yang diberikan — '
                .'jangan menambahkan fakta, nama, angka, atau materi dari luar konteks. '
                .'Jawab selalu dalam bahasa Indonesia yang baku dan santun. '
                .'Keluarkan HANYA JSON objek valid tanpa teks pembuka maupun penutup, dengan kunci: '
                .'"poin" (array string poin pembelajaran utama dari konteks), '
                .'"istilah" (array objek {"istilah","arti"} berisi istilah kunci yang muncul di konteks beserta artinya menurut konteks), '
                .'"pertanyaan_diskusi" (array string pertanyaan diskusi untuk memperdalam pemahaman materi). '
                .'Bila konteks tidak memadai, hasilkan butir lebih sedikit — jangan mengarang.',
            changelog: 'Versi awal agen ikhtisar materi kitab (T-A042).',
        );

        // T-A042 — agen Generator Kultum untuk asatidz (fitur OPT-IN, default
        // OFF). Prompt NETRAL-PROSEDURAL: susun KERANGKA (struktur) kultum
        // dari konteks tema yang diberikan; tiap pernyataan yang memerlukan
        // dalil ditandai [PERIKSA DALIL]; model DILARANG menulis teks
        // ayat/hadits/terjemahnya — rujukan dikosongkan untuk diisi ustadz
        // (pipeline juga memaksa rujukan kosong, defense-in-depth). JSON ketat.
        $this->seedAgen(
            key: 'asatidz_kultum',
            nama: 'Generator Kerangka Kultum Asatidz',
            deskripsi: 'Agen penyusun KERANGKA kultum dari materi tenant (T-A042) — struktur saja, dalil dilengkapi ustadz. Output draf — pengesahan oleh ustadz.',
            tier: 'standar',
            systemPrompt: 'Anda asisten teknis penyusun KERANGKA (struktur garis besar) ceramah singkat untuk guru. '
                .'Susun kerangka HANYA dari materi pada blok DATA konteks yang diberikan — '
                .'jangan menambahkan fakta atau materi dari luar konteks. '
                .'JANGAN menuliskan teks ayat, teks hadits, maupun terjemahnya; '
                .'untuk setiap pernyataan yang memerlukan rujukan, tandai dengan penanda [PERIKSA DALIL] '
                .'dan biarkan kolom rujukan berupa string kosong agar dilengkapi guru. '
                .'Jawab selalu dalam bahasa Indonesia yang baku dan santun. '
                .'Keluarkan HANYA JSON objek valid tanpa teks pembuka maupun penutup, dengan kunci: '
                .'"kerangka" (array string urutan bagian: pembuka, poin-poin isi, penutup), '
                .'"pengingat_dalil" (array objek {"klaim","rujukan"} — "klaim" berisi pernyataan yang harus diperiksa berawalan [PERIKSA DALIL], "rujukan" selalu string kosong). '
                .'Bila konteks tidak memadai, susun kerangka lebih ringkas — jangan mengarang.',
            changelog: 'Versi awal agen generator kerangka kultum (T-A042).',
        );
    }

    /**
     * Seed satu agen + prompt versi 1 — IDEMPOTEN:
     *  - agen: updateOrCreate (metadata boleh diperbarui);
     *  - versi 1: firstOrCreate (BUKAN updateOrCreate) — isi versi lama tidak
     *    pernah diedit (artefak ber-versi, guard model menolak);
     *  - aktivasi versi 1 hanya bila agen belum punya versi aktif sama sekali
     *    (seeder ulang tidak menimpa pilihan operator).
     */
    private function seedAgen(
        string $key,
        string $nama,
        string $deskripsi,
        string $tier,
        string $systemPrompt,
        string $changelog,
    ): void {
        AiAgent::query()->updateOrCreate(
            ['key' => $key],
            [
                'nama' => $nama,
                'deskripsi' => $deskripsi,
                'is_active' => true,
                'default_tier' => $tier,
            ],
        );

        $versi1 = AiPromptVersion::query()->firstOrCreate(
            ['agent_key' => $key, 'versi' => 1],
            [
                'system_prompt' => $systemPrompt,
                'changelog' => $changelog,
                'status' => AiPromptVersion::STATUS_DRAFT,
            ],
        );

        $adaAktif = AiPromptVersion::query()
            ->where('agent_key', $key)
            ->where('status', AiPromptVersion::STATUS_AKTIF)
            ->exists();

        if (! $adaAktif) {
            $versi1->update(['status' => AiPromptVersion::STATUS_AKTIF]);
        }
    }
}
