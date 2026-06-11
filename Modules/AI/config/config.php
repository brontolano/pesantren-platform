<?php

use Modules\AI\Services\Gateway\NullProvider;
use Modules\AI\Services\Gateway\OllamaProvider;

/**
 * Konfigurasi Modul AI "Khadim Digital".
 *
 * CATATAN ATURAN EMAS #4: string host/port Ollama HANYA boleh hidup di berkas
 * config ini dan di kelas-kelas Modules/AI/Services/Gateway. Modul/kode lain
 * dilarang memanggil HTTP LLM langsung — satu-satunya pintu = AiGateway.
 * Pelanggaran ditangkap test lint arsitektur (AiArchitectureLintTest).
 */

return [
    'name' => 'AI',

    /*
    |--------------------------------------------------------------------------
    | Provider Ollama (LLM lokal self-hosted, keputusan K3)
    |--------------------------------------------------------------------------
    */
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway (AiGateway) — timeout, retry, circuit breaker, rantai fallback
    |--------------------------------------------------------------------------
    | retry_connection_max: retry HANYA untuk kegagalan KONEKSI (refused/DNS),
    | BUKAN timeout — menghindari beban dobel inferensi di CPU 2 core.
    */
    'gateway' => [
        'timeout_chat' => (int) env('AI_GATEWAY_TIMEOUT_CHAT', 120),
        'timeout_embed' => (int) env('AI_GATEWAY_TIMEOUT_EMBED', 30),
        'connect_timeout' => (int) env('AI_GATEWAY_CONNECT_TIMEOUT', 5),
        'retry_connection_max' => (int) env('AI_GATEWAY_RETRY_CONNECTION_MAX', 1),

        // Pesan degradasi anggun (NullProvider). Bahasa Indonesia santun.
        'degraded_message' => 'Mohon maaf, asisten sedang sibuk. Silakan coba beberapa saat lagi.',

        // Peta provider terdaftar. Menambah provider baru (K4) = kelas baru
        // implementasi LlmProvider + satu baris di sini + baris ai_model_config.
        'providers' => [
            'ollama' => OllamaProvider::class,
            'null' => NullProvider::class,
        ],

        // Urutan rantai fallback provider NYATA. NullProvider selalu menjadi
        // jaring pengaman terakhir secara implisit (tidak perlu ditulis).
        'chain' => ['ollama'],

        'circuit_breaker' => [
            // Buka sirkuit setelah N kegagalan beruntun dalam jendela M detik.
            'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 5),
            'window_seconds' => (int) env('AI_CB_WINDOW_SECONDS', 300),
            // Setelah cooldown, sirkuit half-open: satu percobaan diizinkan.
            'cooldown_seconds' => (int) env('AI_CB_COOLDOWN_SECONDS', 60),
            // null = cache store default aplikasi.
            'store' => env('AI_CB_CACHE_STORE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ModelRouter — pemilihan provider+model per (agent, tier)
    |--------------------------------------------------------------------------
    | Sumber kebenaran: tabel CENTRAL ai_model_config (cache 60 dtk).
    | Bila tabel kosong/belum termigrasi → hard-default di bawah (v1 = Ollama
    | untuk semua tier, sesuai K3; tabel router tetap ada demi K4).
    */
    'router' => [
        'cache_seconds' => (int) env('AI_ROUTER_CACHE_SECONDS', 60),
        'tiers' => ['ekonomis', 'standar', 'premium', 'embedding'],
        'defaults' => [
            'ekonomis' => [
                'provider' => 'ollama',
                'model' => 'qwen2.5:3b-instruct',
                'max_context_tokens' => 4096,
                'options' => [],
            ],
            'standar' => [
                'provider' => 'ollama',
                'model' => 'qwen2.5:3b-instruct',
                'max_context_tokens' => 4096,
                'options' => [],
            ],
            'premium' => [
                'provider' => 'ollama',
                'model' => 'qwen2.5:3b-instruct',
                'max_context_tokens' => 4096,
                'options' => [],
            ],
            'embedding' => [
                'provider' => 'ollama',
                'model' => 'bge-m3',
                'max_context_tokens' => 8192,
                'options' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt registry (T-A004) — versi prompt per agen + feature flag tenant
    |--------------------------------------------------------------------------
    | Sumber kebenaran: tabel CENTRAL ai_agent / ai_prompt_version /
    | ai_prompt_tenant_override (cache 60 dtk + invalidasi eksplisit).
    */
    'prompts' => [
        'cache_seconds' => (int) env('AI_PROMPTS_CACHE_SECONDS', 60),

        // Pesan saat feature flag tenant mematikan sebuah agen. Bahasa
        // Indonesia santun — BERBEDA dari pesan anggaran/degradasi supaya
        // jelas ini soal fitur belum diaktifkan, bukan gangguan/kuota.
        'disabled_message' => 'Mohon maaf, fitur asisten ini tidak diaktifkan untuk pesantren Anda. Silakan hubungi admin bila membutuhkannya.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metering kredit (T-A002) — tarif per tier, anggaran & kill-switch
    |--------------------------------------------------------------------------
    | Satuan = KREDIT (bukan rupiah) per 1000 token, dipisah token masuk/keluar
    | (pagar #2 blueprint: pola pemakaian terukur & siap dimonetisasi).
    | Degradasi (NullProvider) = 0 kredit tapi TETAP termeter (pagar #5).
    */
    'metering' => [
        // Tarif kredit per 1000 token. 'masuk' = prompt, 'keluar' = completion.
        'tarif' => [
            'ekonomis' => ['masuk' => 0.25, 'keluar' => 0.5],
            'standar' => ['masuk' => 0.5, 'keluar' => 1.0],
            'premium' => ['masuk' => 2.0, 'keluar' => 4.0],
            'embedding' => ['masuk' => 0.05, 'keluar' => 0.0],
        ],

        // Ambang alert default (persen anggaran bulanan) untuk tenant baru.
        'ambang_alert_persen' => (int) env('AI_METERING_AMBANG_ALERT', 80),

        // Pesan kill-switch (anggaran habis). Bahasa Indonesia santun —
        // BERBEDA dari degraded_message supaya pemanggil/pengguna tahu ini
        // soal anggaran, bukan gangguan teknis.
        'kill_switch_message' => 'Mohon maaf, anggaran AI bulan ini telah habis. Silakan hubungi admin untuk top-up kredit.',

        // Alarm anomali biaya (ai:anomali): hari ini > faktor × rata-rata 7
        // hari terakhir DAN melebihi ambang minimum kredit absolut.
        'anomali' => [
            'faktor' => (float) env('AI_ANOMALI_FAKTOR', 3.0),
            'minimum_kredit' => (float) env('AI_ANOMALI_MIN_KREDIT', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privasi (T-A005) — redaksi PII pra-provider + sanitasi anti-injection
    |--------------------------------------------------------------------------
    | Berlaku walau provider v1 lokal (jaga log & konteks minimal-PII; pagar
    | sudah terpasang saat provider eksternal diaktifkan, keputusan K4).
    | Tiap pola bisa dimatikan per jenis; pola tambahan via *_tambahan TANPA
    | mengubah kelas (PiiRedactor / ContentSanitizer).
    | CATATAN: alamat sengaja TIDAK diredaksi — false positive regex terlalu
    | tinggi (lihat docblock PiiRedactor).
    */
    'privacy' => [
        'redaksi' => [
            'enabled' => (bool) env('AI_PII_REDAKSI_ENABLED', true),

            // URUTAN PENTING: pola berkata-kunci/lebih spesifik lebih dulu
            // (email → rekening → nik → hp → nisn) supaya deretan digit tidak
            // salah klaim oleh pola yang lebih generik.
            'pola' => [
                'email' => [
                    'aktif' => true,
                    'regex' => '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/u',
                ],
                // Nomor rekening HANYA dengan kata kunci konteks (rekening/
                // norek/no. rek) — deret 9-16 digit telanjang terlalu ambigu.
                'rekening' => [
                    'aktif' => true,
                    'regex' => '/\b(?:no(?:mor)?\.?\s*rek(?:ening)?|norek|rekening)\b[^0-9\r\n]{0,20}\K\d{9,16}(?!\d)/iu',
                ],
                'nik' => [
                    'aktif' => true,
                    'regex' => '/(?<!\d)\d{16}(?!\d)/u',
                ],
                'hp' => [
                    'aktif' => true,
                    // +62 / 62 / 08xx, total 9-13 digit, boleh pemisah - atau spasi.
                    'regex' => '/(?<![\d.])(?:\+?62|0)8\d{1,2}(?:[\s-]?\d{3,4}){2,3}(?!\d)/u',
                ],
                'nisn' => [
                    'aktif' => true,
                    'regex' => '/(?<!\d)\d{10}(?!\d)/u',
                ],
            ],

            // Pola operator tambahan: ['jenis' => '/regex/'].
            'pola_tambahan' => [],
        ],

        'sanitasi' => [
            'enabled' => (bool) env('AI_SANITASI_ENABLED', true),

            // Token peran/template chat + delimiter blok data — DI-DEFANG
            // karakternya (anti tokenizer-special & anti delimiter-escape).
            'pola_token' => [
                '/<\|[\w\/-]+\|>/u',
                '/<<\s*\/?\s*SYS\s*>>/iu',
                '/\[\/?INST\]/iu',
                '/<\/?(?:s|system|assistant|user|tool)\s*>/iu',
                '/<<<\s*(?:DATA\s*:[^>\r\n]*|AKHIR-DATA)\s*>>>/iu',
            ],

            // Frasa instruksi-meta ID+EN — DIBUNGKUS «dikutip-sebagai-data: ...».
            'pola_frasa' => [
                '/^[ \t]*(?:system|assistant|developer|tool)\s*:/imu',
                '/\babaikan\s+(?:semua\s+|seluruh\s+)?(?:instruksi|aturan|perintah)[^\n.!?«]{0,80}/iu',
                '/\bjangan\s+hiraukan\s+[^\n.!?«]{0,80}/iu',
                '/\blupakan\s+(?:semua\s+)?(?:instruksi|aturan|perintah|peranmu)[^\n.!?«]{0,80}/iu',
                '/\bignore\s+(?:all\s+)?(?:previous|prior|above|earlier|the)\s+[^\n.!?«]{0,80}/iu',
                '/\bdisregard\s+(?:all\s+|the\s+|any\s+)?[^\n.!?«]{0,40}(?:instructions?|rules?|prompts?)[^\n.!?«]{0,40}/iu',
                '/\bforget\s+(?:all\s+|your\s+)?(?:previous\s+)?(?:instructions?|rules?|training)[^\n.!?«]{0,80}/iu',
                '/\b(?:kamu|anda|engkau)\s+sekarang\s+(?:adalah|menjadi|bukan)\s+[^\n.!?«]{0,80}/iu',
                '/\byou\s+are\s+now\s+[^\n.!?«]{0,80}/iu',
                '/\bpretend\s+(?:to\s+be|you\s+are|that)\s+[^\n.!?«]{0,80}/iu',
                '/\bact\s+as\s+(?:if\s+|an?\s+|the\s+)[^\n.!?«]{0,80}/iu',
                '/\b(?:bocorkan|kirim(?:kan)?|tampilkan|tunjukkan|sebutkan)\b[^\n.!?«]{0,40}(?:data|kredensial|password|kata\s+sandi|rahasia|system\s+prompt|prompt\s+sistem)[^\n.!?«]{0,60}/iu',
                '/\breveal\b[^\n.!?«]{0,40}(?:system\s+prompt|instructions?|secrets?|credentials?)[^\n.!?«]{0,60}/iu',
                '/\b(?:instruksi|perintah|aturan)\s+baru\s*:[^\n«]{0,120}/iu',
                '/\bnew\s+(?:instructions?|system\s+prompt|rules?)\s*:[^\n«]{0,120}/iu',
                '/\b(?:developer|debug|god)\s+mode\b[^\n.!?«]{0,80}/iu',
                '/\bmode\s+pengembang\b[^\n.!?«]{0,80}/iu',
                '/\bDAN\s+mode\b[^\n.!?«]{0,60}/u',
                '/\bjailbreak\b[^\n.!?«]{0,80}/iu',
                '/\boverride\b[^\n.!?«]{0,30}(?:safety|security|system|aturan|keamanan)[^\n.!?«]{0,60}/iu',
                // Markdown image exfiltration: gambar ber-URL eksternal di dokumen.
                '/!\[[^\]\r\n]{0,100}\]\((?:https?:)?\/\/[^)\s]{1,300}\)/iu',
            ],

            // Pola frasa tambahan operator (list regex).
            'pola_tambahan' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengaturan tenant (T-A010) — ai_tenant_setting versioned + approval kyai
    |--------------------------------------------------------------------------
    | sensitive_fields: perubahan field ini WAJIB persetujuan kyai/pimpinan
    | (status pending_approval; agen memakai versi lama sampai disetujui).
    | Field di luar daftar (jam_layanan, bahasa/gaya, anggaran, ambang alert,
    | pesan) boleh langsung aktif. Daftar hidup di config — kebijakan di
    | data/konfigurasi, bukan hard-code (Aturan Emas #11).
    */
    'settings' => [
        'sensitive_fields' => [
            'level_fatwa',
            'blacklist_topik',
            'mode_akses_santri',
            'scope_data',
            'fitur_per_peran',
            'consent_ortu',
        ],

        // T-A012: umur tautan bertanda tangan (signed URL) halaman ringkasan
        // kebijakan untuk kyai/pimpinan. Tautan HANYA menampilkan ringkasan
        // (baca-saja, tanpa login); persetujuan tetap wajib login + permission
        // ai.setting.setujui di panel.
        'tautan_ringkasan_jam' => (int) env('AI_TAUTAN_RINGKASAN_JAM', 72),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardrails (T-A020) — PolicyEngine pre-prompt & post-response
    |--------------------------------------------------------------------------
    | RULE-BASED MURNI, TANPA LLM. Aturan Emas #8: bagian ini HANYA memuat
    | rule TEKNIS netral (pola linguistik, pemetaan fitur, pesan prosedural).
    | Konten keagamaan (preset blacklist per manhaj, daftar topik, dst.) HANYA
    | boleh masuk dari berkas seed tinjauan ustadz (GATE-3, PRD §12.2).
    */
    'guardrails' => [
        // [K3] Kanal yang dianggap EKSTERNAL (datang dari luar staf/dev).
        // Agen yang TIDAK terpetakan di agent_feature_map di kanal ini
        // di-FAIL-CLOSED ("fitur tidak dikenali"); kanal internal (web/cli/
        // internal) tetap bebas untuk agen uji/dev.
        'kanal_eksternal' => ['wa', 'chat'],

        // agent_key → nama fitur di matriks setting fitur_per_peran.
        // Agen TANPA pemetaan: bebas HANYA di kanal internal (lihat
        // kanal_eksternal di atas — kanal eksternal fail-closed).
        'agent_feature_map' => [
            'chatbot_wali' => 'chatbot',
            // T-A051: agen chatbot WA wali (free-form RAG) — satu fitur
            // 'chatbot' dengan chatbot_wali; aktivasi pilot via Wizard
            // (default konservatif wali.chatbot = OFF).
            'wali_chat' => 'chatbot',
            'chatbot_admin' => 'chatbot',
            'tutor_santri' => 'tutor',
            'generator_soal' => 'generator_soal',
            // T-A040: agen Generator Soal asatidz — gerbang matriks
            // fitur_per_peran (asatidz/admin) di PolicyEngine ikut berlaku.
            'asatidz_soal' => 'generator_soal',
            // T-A041: agen Generator RPP / Modul Ajar asatidz.
            'generator_rpp' => 'generator_rpp',
            'asatidz_rpp' => 'generator_rpp',
            // T-A042: ikhtisar materi kitab (asatidz/admin) & generator
            // kultum (asatidz, OPT-IN — default OFF di AiTenantSettingDefaults,
            // rekomendasi Q8 PRD belum dikonfirmasi owner).
            'ikhtisar_kitab' => 'ikhtisar_kitab',
            'asatidz_ikhtisar' => 'ikhtisar_kitab',
            'generator_kultum' => 'generator_kultum',
            'asatidz_kultum' => 'generator_kultum',
            'dokumen_tu' => 'dokumen_tu',
            'ringkasan_keuangan' => 'ringkasan_keuangan',
        ],

        // Role spatie → kunci peran matriks fitur_per_peran. Role yang tidak
        // terdaftar dipakai apa adanya (asatidz, bendahara, wali, santri).
        'role_map' => [
            'admin_pesantren' => 'admin',
            'kepala' => 'admin',
        ],

        // Kanal yang terikat jam layanan (eksternal). Kanal lain (web staf,
        // internal, cli) dilayani kapan pun.
        'jam_layanan_channels' => ['wa', 'chat'],

        // Pola TEKNIS-LINGUISTIK pertanyaan hukum (level_fatwa=edukatif_only
        // → dialihkan ke Tanya Ustadz). Ini pola BENTUK kalimat, BUKAN daftar
        // topik agama — isi topik menunggu seed GATE-3.
        'pola_hukum' => [
            '/\bapa\s+hukum(?:nya)?\b/iu',
            '/\bbagaimana(?:kah)?\s+hukum(?:nya)?\b/iu',
            '/\bhukum(?:nya)?\s+(?:apa|bagaimana)\b/iu',
            '/\bboleh\s*kah\b/iu',
            '/\bharam\s*kah\b/iu',
            '/\bhalal\s*kah\b/iu',
            '/\bwajib\s*kah\b/iu',
            '/\bmakruh\s*kah\b/iu',
            '/\bsunnah\s*kah\b/iu',
            '/\bapakah\s+(?:boleh|haram|halal|wajib|makruh|sunnah|sah)\b/iu',
            '/\bsah\s*kah\b/iu',
        ],

        // Panjang maksimal pertanyaan_ringkas pada ai_escalation (terredaksi).
        'eskalasi_ringkas_max' => (int) env('AI_ESKALASI_RINGKAS_MAX', 300),

        // Template respons default (Indonesia santun, netral-prosedural,
        // TANPA konten fikih). Tenant bisa override per rule lewat
        // ai_policy_rule.respons_template.
        'pesan' => [
            // T-A012: modul `ai` tidak tampil/aktif untuk tenant ini
            // (module_registry status development bukan tenant demo, atau
            // released tapi belum diaktifkan di tenant_modules).
            'modul_nonaktif' => 'Mohon maaf, modul asisten AI belum diaktifkan untuk pesantren Anda. Silakan hubungi pengelola platform bila pesantren ingin menggunakannya.',
            'belum_dikonfigurasi' => 'Mohon maaf, modul AI pesantren Anda belum selesai dikonfigurasi. Silakan hubungi admin untuk menuntaskan pengaturan terlebih dahulu.',
            'di_luar_jam' => 'Mohon maaf, layanan asisten sedang di luar jam operasional. Pertanyaan Anda kami catat dan akan ditindaklanjuti pada jam layanan.',
            'fitur_tidak_diizinkan' => 'Mohon maaf, fitur asisten ini tidak tersedia untuk akun Anda. Silakan hubungi admin pesantren bila membutuhkannya.',
            'fitur_tidak_dikenali' => 'Mohon maaf, fitur asisten yang Anda tuju tidak dikenali. Silakan hubungi admin pesantren bila membutuhkan bantuan.',
            'blacklist_topik' => 'Mohon maaf, topik ini tidak dapat dijawab oleh asisten. Pertanyaan Anda telah kami teruskan kepada pihak yang berwenang di pesantren.',
            'tanya_ustadz' => 'Mohon maaf, pertanyaan seperti ini sebaiknya dijawab langsung oleh ustadz. Pertanyaan Anda telah kami teruskan ke antrean Tanya Ustadz.',
            // T-A022: jawaban gagal verifikasi sitasi 2× (asli + regenerasi).
            'sitasi_gagal' => 'Mohon maaf, asisten tidak dapat menjawab pertanyaan ini dengan rujukan yang memadai. Pertanyaan Anda telah kami teruskan ke antrean Tanya Ustadz agar dijawab langsung oleh pihak pesantren.',
        ],

        // T-A022: agent_key → kategori jawaban (agama|keuangan|akademik|umum)
        // untuk CitationEnforcer & DisclaimerInjector. Agen tanpa pemetaan =
        // 'umum'. Pemanggil boleh override via opsi 'kategori_jawaban'.
        'kategori_map' => [
            'tutor_santri' => 'akademik',
            'generator_soal' => 'akademik',
            'asatidz_soal' => 'akademik', // T-A040
            'generator_rpp' => 'akademik',
            'asatidz_rpp' => 'akademik', // T-A041
            'asatidz_ikhtisar' => 'akademik', // T-A042
            // T-A042: kerangka kultum = materi keagamaan — disclaimer
            // kategori 'agama' (PRD §7.5 verbatim) dipakai IkhtisarGenerator.
            'asatidz_kultum' => 'agama',
            'ringkasan_keuangan' => 'keuangan',
        ],

        // T-A022: ambang CitationEnforcer (heuristik DETERMINISTIK tanpa LLM).
        'enforcer' => [
            // Kalimat dengan overlap token-konten < ambang ini = "di luar konteks".
            'ambang_overlap_kalimat' => (float) env('AI_ENFORCER_AMBANG_OVERLAP', 0.25),
            // Proporsi kalimat-luar > ambang ini → jawaban TIDAK lolos.
            'ambang_proporsi_luar' => (float) env('AI_ENFORCER_AMBANG_LUAR', 0.35),
            // Jawaban > N× panjang konteks → TIDAK lolos (indikasi mengarang).
            'rasio_panjang_max' => (float) env('AI_ENFORCER_RASIO_PANJANG', 3.0),
            // Panjang minimal kata konten (huruf) yang ikut dihitung overlap.
            'min_kata' => (int) env('AI_ENFORCER_MIN_KATA', 4),
            // Stopword TEKNIS bahasa Indonesia (kata fungsi/penghubung) —
            // BUKAN daftar topik/konten keagamaan (Aturan Emas #8).
            'stopwords' => [
                'yang', 'untuk', 'dengan', 'dalam', 'pada', 'dari', 'kepada',
                'adalah', 'akan', 'sudah', 'telah', 'bisa', 'dapat', 'tidak',
                'bukan', 'juga', 'karena', 'sehingga', 'agar', 'supaya',
                'namun', 'tetapi', 'jika', 'bila', 'serta', 'antara', 'secara',
                'oleh', 'sebagai', 'tentang', 'terhadap', 'setelah', 'sebelum',
                'ketika', 'saat', 'mohon', 'silakan', 'berikut', 'yaitu',
                'yakni', 'ialah', 'para', 'atau', 'maka', 'lalu', 'kemudian',
                'hanya', 'masih', 'lebih', 'kurang', 'sangat', 'setiap',
                'semua', 'seluruh', 'beberapa', 'adapun', 'demikian',
                'tersebut', 'berdasarkan', 'merupakan', 'seperti', 'sesuai',
                'maupun', 'bahwa', 'tanpa', 'melalui', 'hingga', 'sampai',
                'sekitar', 'apabila', 'sebab', 'agaknya', 'tentu',
            ],
        ],

        // T-A022: teks disclaimer per kategori (DisclaimerInjector).
        // 'agama' = teks PRD §7.5 VERBATIM — JANGAN diubah/dikarang; override
        // per tenant via rule ai_policy_rule tipe 'disclaimer' (seed GATE-3 /
        // input pesantren). Format polos kompatibel WA (tanpa markdown).
        'disclaimer' => [
            'agama' => 'Ini alat bantu belajar, bukan fatwa. Untuk amalan, mohon merujuk kepada Ustadz/Kyai yang sanadnya jelas.',
            'keuangan' => 'Informasi ini ringkasan alat bantu. Untuk angka resmi, mohon merujuk pada laporan keuangan pesantren.',
            'akademik' => 'Materi ini alat bantu belajar. Mohon konfirmasikan kepada guru/asatidz pengampu untuk kepastian materi.',
            // Footer jawaban data/tool (sumber sistem + cap waktu).
            'footer_data' => 'Sumber: :sumber • per :cap_waktu',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Eskalasi "Tanya Ustadz" (T-A023) — SLA, auto-assign, laporan kyai
    |--------------------------------------------------------------------------
    | sla_jam_default: dipakai bila setting tenant eskalasi_tujuan.sla_jam
    | tidak tersedia. auto_assign_menit: eskalasi 'baru' lebih tua dari ini
    | otomatis ditugaskan ke tujuan default (scheduled ai:eskalasi-pengingat).
    */
    'eskalasi' => [
        'sla_jam_default' => (int) env('AI_ESKALASI_SLA_JAM', 24),
        'auto_assign_menit' => (int) env('AI_ESKALASI_AUTO_ASSIGN_MENIT', 30),
        // Maks contoh anonim (pertanyaan_ringkas terredaksi) di laporan kyai.
        'laporan_contoh_max' => (int) env('AI_ESKALASI_LAPORAN_CONTOH_MAX', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG (T-A031) — retrieval hybrid ber-tenant + sitasi deterministik
    |--------------------------------------------------------------------------
    | top_k: jumlah chunk final yang masuk konteks (Blueprint §7.4: 3–5 saja).
    | rrf_k: konstanta Reciprocal Rank Fusion (60 = standar literatur).
    */
    'rag' => [
        'top_k' => (int) env('AI_RAG_TOP_K', 5),
        'rrf_k' => (int) env('AI_RAG_RRF_K', 60),

        'pesan' => [
            'tanpa_rujukan' => 'Mohon maaf, kami belum menemukan rujukan dokumen pesantren untuk pertanyaan ini, sehingga asisten tidak dapat menjawabnya. Pertanyaan Anda telah kami teruskan ke antrean Tanya Ustadz agar dijawab langsung oleh pihak pesantren.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Generator asatidz (T-A040) — Generator Soal & Kisi-kisi
    |--------------------------------------------------------------------------
    | Async-by-default (Blueprint §7.3): generator = job queue 'ai'.
    | batch_size: butir per panggilan LLM — kecil supaya konteks model 3B
    | tetap ramping (Blueprint §7.4). max_jumlah dibatasi hard cap 30 di
    | SoalGenerator walau env menyetel lebih besar.
    */
    'generator' => [
        'soal' => [
            'default_jumlah' => (int) env('AI_SOAL_DEFAULT_JUMLAH', 10),
            'max_jumlah' => (int) env('AI_SOAL_MAX_JUMLAH', 30),
            'batch_size' => (int) env('AI_SOAL_BATCH_SIZE', 5),

            // Pesan kegagalan baku (Indonesia santun).
            'pesan' => [
                // RAG-only (pagar #2): tanpa korpus TIDAK boleh generate dari
                // bobot model — draf gagal dengan ajakan unggah materi.
                'tanpa_korpus' => 'Mohon maaf, belum ada materi kurikulum yang dapat dirujuk untuk menyusun soal. Silakan unggah materi kurikulum terlebih dahulu di menu Sumber Pengetahuan, lalu coba kembali.',
                'format_invalid' => 'Mohon maaf, asisten tidak menghasilkan format soal yang valid setelah dicoba ulang. Silakan coba beberapa saat lagi.',
            ],
        ],

        // T-A041 — Generator RPP / Modul Ajar. Per SEKSI template satu
        // panggilan LLM; top_k retrieval ramping (konteks per seksi kecil,
        // Blueprint §7.4). Struktur dokumen = ai_template_rpp MILIK TENANT
        // (Aturan Emas #11: tanpa taksonomi regulasi di kode).
        'rpp' => [
            'top_k' => (int) env('AI_RPP_TOP_K', 3),

            // Pesan kegagalan baku (Indonesia santun).
            'pesan' => [
                // RAG-only (pagar #2): tanpa korpus TIDAK boleh mengisi dari
                // bobot model — draf gagal dengan ajakan unggah materi.
                'tanpa_korpus' => 'Mohon maaf, belum ada materi kurikulum yang dapat dirujuk untuk menyusun RPP. Silakan unggah materi kurikulum terlebih dahulu di menu Sumber Pengetahuan, lalu coba kembali.',
                'konten_kosong' => 'Mohon maaf, asisten tidak menghasilkan isi untuk salah satu seksi RPP. Silakan coba beberapa saat lagi.',
            ],
        ],

        // T-A042 — Ikhtisar materi kitab bersitasi & generator kultum.
        // ADAPTASI SADAR: sumber v1 = Sumber Pengetahuan TENANT
        // (ai_knowledge_source) karena whitelist kitab GATE-3 belum terisi;
        // kultum OPT-IN (default OFF di AiTenantSettingDefaults — rekomendasi
        // Q8 PRD, belum dikonfirmasi owner) & output SELALU draf berdisclaimer.
        'ikhtisar' => [
            'top_k' => (int) env('AI_IKHTISAR_TOP_K', 5),
            // Jepit durasi kultum (menit) ke rentang masuk akal.
            'durasi_min' => (int) env('AI_KULTUM_DURASI_MIN', 3),
            'durasi_max' => (int) env('AI_KULTUM_DURASI_MAX', 60),

            // Disclaimer TAMBAHAN kerangka kultum (melengkapi disclaimer
            // kategori 'agama' PRD §7.5 dari DisclaimerInjector). Teks
            // PROSEDURAL netral — bukan konten fikih.
            'disclaimer_kultum' => 'Kerangka ini wajib diperiksa dan dilengkapi dalil oleh Ustadz sebelum disampaikan.',

            // Pesan kegagalan/penolakan baku (Indonesia santun).
            'pesan' => [
                // RAG-only (pagar #2): tanpa korpus TIDAK boleh menyusun dari
                // bobot model — draf gagal dengan ajakan unggah materi.
                'tanpa_korpus' => 'Mohon maaf, belum ada materi yang dapat dirujuk untuk menyusun dokumen ini. Silakan unggah materi kitab terlebih dahulu di menu Sumber Pengetahuan, lalu coba kembali.',
                'format_invalid' => 'Mohon maaf, asisten tidak menghasilkan format dokumen yang valid setelah dicoba ulang. Silakan coba beberapa saat lagi.',
                // Fitur kultum OPT-IN belum diaktifkan pesantren (Wizard).
                'kultum_nonaktif' => 'Mohon maaf, fitur kerangka kultum belum diaktifkan untuk pesantren Anda. Silakan hubungi admin untuk mengaktifkannya melalui Pengaturan AI bila dibutuhkan.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools data resmi (T-A033) — registry tool READ-ONLY (Blueprint §6)
    |--------------------------------------------------------------------------
    | scope_map: tool → kunci scope_data Wizard (null = tanpa dimensi scope,
    | mis. status PPDB milik pemohon sendiri / profil anak tertaut wali yang
    | pagar utamanya AccessWali). peran_map: tool → role spatie yang boleh
    | memanggil. Keduanya OVERRIDE default ToolDefinition — kebijakan hidup di
    | konfigurasi, bukan kode (Aturan Emas #11).
    */
    'tools' => [
        'scope_map' => [
            'getTagihan' => 'tagihan',
            'getStatusBayar' => 'tagihan',
            'getRingkasanKeuangan' => 'keuangan',
            'getJadwal' => 'jadwal',
            'getNilai' => 'nilai',
            // Tahfidz = capaian belajar santri → ikut gerbang scope 'nilai'
            // (scope_data Wizard tidak punya kunci tahfidz tersendiri).
            'getTahfidz' => 'nilai',
            'getKehadiran' => 'kehadiran',
            'getIzin' => 'kehadiran',
            'getStatusPPDB' => null,
            'getSantri' => null,
        ],

        'peran_map' => [
            'getTagihan' => ['admin_pesantren', 'tu', 'keuangan', 'bendahara', 'bendahara_pusat', 'kepala', 'wali'],
            'getStatusBayar' => ['admin_pesantren', 'tu', 'keuangan', 'bendahara', 'bendahara_pusat', 'kepala', 'wali'],
            'getRingkasanKeuangan' => ['admin_pesantren', 'keuangan', 'bendahara', 'bendahara_pusat', 'kepala_keuangan', 'kepala', 'dewan_pembina'],
            'getJadwal' => ['admin_pesantren', 'tu', 'guru', 'asatidz', 'kepala', 'wali', 'santri'],
            'getNilai' => ['admin_pesantren', 'tu', 'guru', 'kepala', 'wali'],
            'getTahfidz' => ['admin_pesantren', 'guru_tahfidz', 'guru', 'kepala', 'wali'],
            'getKehadiran' => ['admin_pesantren', 'tu', 'pengelola_asrama', 'kepala', 'wali'],
            'getIzin' => ['admin_pesantren', 'tu', 'pengelola_asrama', 'kepala', 'wali'],
            'getStatusPPDB' => ['admin_pesantren', 'tu', 'kepala', 'peserta'],
            'getSantri' => ['wali'],
        ],

        // Pesan kegagalan baku (Indonesia santun, netral-prosedural).
        'pesan' => [
            'tidak_dikenal' => 'Mohon maaf, jenis data yang diminta tidak dikenali oleh asisten.',
            'tanpa_identitas' => 'Mohon maaf, permintaan data memerlukan identitas pengguna yang sah. Silakan masuk terlebih dahulu.',
            'belum_dikonfigurasi' => 'Mohon maaf, modul AI pesantren Anda belum selesai dikonfigurasi. Silakan hubungi admin untuk menuntaskan pengaturan terlebih dahulu.',
            'scope_nonaktif' => 'Mohon maaf, akses data jenis ini belum diaktifkan oleh pesantren. Silakan hubungi admin pesantren bila membutuhkannya.',
            'peran_tidak_diizinkan' => 'Mohon maaf, akun Anda tidak memiliki akses untuk melihat data ini.',
            'bukan_anak_tertaut' => 'Mohon maaf, Anda hanya dapat melihat data putra/putri yang tertaut dengan akun Anda. Silakan hubungi admin pesantren bila ada tautan yang belum terdaftar.',
            'args_invalid' => 'Mohon maaf, parameter permintaan data tidak lengkap atau tidak sesuai.',
            'data_tidak_ditemukan' => 'Mohon maaf, data yang dimaksud tidak ditemukan.',
            'modul_tidak_aktif' => 'Mohon maaf, modul sumber data ini belum aktif di pesantren Anda.',
            'gagal_internal' => 'Mohon maaf, terjadi kendala saat mengambil data. Silakan coba beberapa saat lagi atau hubungi admin pesantren.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | T-A024 — Eval harness & gate rilis (Blueprint K7 + §10)
    |--------------------------------------------------------------------------
    | ambang: skor minimum per suite — GAGAL = fitur terkait TIDAK dirilis
    | (bukan dilonggarkan). Dijalankan via `php artisan ai:eval --suite=...`
    | terhadap MODEL PRODUKSI sebenarnya; hasil tercatat di ai_eval_run.
    | konteks_syariah: blacklist TEKNIS-NETRAL milik suite policy_cases.json
    | (lihat _catatan berkas itu) — BUKAN konten keagamaan (Aturan Emas #8);
    | preset blacklist sungguhan menunggu seed GATE-3.
    */
    'eval' => [
        'ambang' => [
            'syariah' => (float) env('AI_EVAL_AMBANG_SYARIAH', 0.98),
            'faktual' => (float) env('AI_EVAL_AMBANG_FAKTUAL', 0.97),
            'injection' => (float) env('AI_EVAL_AMBANG_INJECTION', 1.0),
        ],

        // Tenant sandbox khusus eval (dibuat sekali oleh ai:eval, dipakai
        // ulang). --tenant=... mengabaikan ini (tenant nyata, baca-saja).
        'tenant_id' => env('AI_EVAL_TENANT', 'eval-khadim'),

        // Agen internal untuk request eval kanal non-WA (tanpa pemetaan
        // fitur → bebas di kanal internal, pola AiPolicyEngineTest).
        'agent_key' => 'uji',

        // Agen yang versi prompt aktifnya direkam di ai_eval_run.prompt_versi.
        'agen_snapshot' => ['uji', 'wali_chat'],

        // Konteks kebijakan suite syariah teknis (mirror policy_cases.json).
        'konteks_syariah' => [
            'blacklist' => ['topik-uji-terlarang', 'frasa uji rahasia'],
            'regex' => ['/uji-regex-\d+/iu'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FAQ-Mining (permintaan owner 2026-06-11) — tambang pertanyaan berulang
    |--------------------------------------------------------------------------
    | Chatbot "belajar dari kebiasaan pengguna" secara HUMAN-IN-THE-LOOP:
    | pertanyaan yang sering muncul (eskalasi + percakapan, SEMUA sudah
    | terredaksi) di-cluster TEKNIK KLASIK LOKAL (token-set Jaccard /
    | kandungan kata kunci — TANPA LLM, TANPA training) menjadi usulan FAQ.
    | Hanya setelah admin/ustadz MENGESAHKAN (isi jawaban) teksnya diingest
    | ke Sumber Pengetahuan kategori 'faq' sehingga terjawab via RAG.
    |
    | kategori_skip: kategori eskalasi yang TIDAK ditambang — pertanyaan
    | hukum/blacklist adalah wilayah ustadz, bukan bahan FAQ otomatis.
    | stopwords: kata fungsi/partikel percakapan ID TEKNIS-NETRAL (bukan
    | daftar topik keagamaan — Aturan Emas #8).
    */
    'faq_mining' => [
        // Jendela penambangan (hari ke belakang).
        'periode_hari' => (int) env('AI_FAQ_PERIODE_HARI', 30),
        // Minimal kemunculan pertanyaan serupa agar jadi usulan.
        'min_frekuensi' => (int) env('AI_FAQ_MIN_FREKUENSI', 3),
        // Ambang kesamaan token-set Jaccard (0–1).
        'ambang_jaccard' => (float) env('AI_FAQ_AMBANG_JACCARD', 0.6),
        // ATAU: irisan kata kunci utama — overlap/min(|A|,|B|) ≥ ambang ini
        // dengan minimal 2 token sama (pertanyaan pendek ⊂ panjang tetap
        // satu kelompok walau Jaccard rendah).
        'ambang_kandungan' => (float) env('AI_FAQ_AMBANG_KANDUNGAN', 0.8),
        // Maks variasi pertanyaan serupa yang disimpan per usulan.
        'maks_variasi' => (int) env('AI_FAQ_MAKS_VARIASI', 10),
        // Pagar memori/CPU: maks pertanyaan per sumber per run.
        'maks_pertanyaan' => (int) env('AI_FAQ_MAKS_PERTANYAAN', 2000),

        // Kategori eskalasi SENSITIF yang dilewati penambang (wilayah ustadz).
        'kategori_skip' => ['blacklist_topik', 'level_fatwa'],

        // Stopword ID ringan + partikel percakapan/sapaan (teknis-netral).
        // 'pii' = sisa token redaksi [PII:...]; token < 3 huruf otomatis gugur.
        'stopwords' => [
            'yang', 'untuk', 'dengan', 'dalam', 'pada', 'dari', 'kepada',
            'adalah', 'akan', 'sudah', 'telah', 'bisa', 'dapat', 'tidak',
            'bukan', 'juga', 'karena', 'jika', 'bila', 'atau', 'dan',
            'itu', 'ini', 'ada', 'apa', 'apakah', 'bagaimana', 'gimana',
            'berapa', 'kapan', 'dimana', 'mana', 'siapa', 'kenapa',
            'mengapa', 'mohon', 'tolong', 'silakan', 'terima', 'kasih',
            'assalamualaikum', 'assalamu', 'alaikum', 'wassalam', 'salam',
            'pak', 'ibu', 'bapak', 'kak', 'mas', 'mbak', 'min', 'admin',
            'iya', 'dong', 'sih', 'deh', 'nya', 'kah', 'lah', 'kok',
            'mau', 'ingin', 'tanya', 'bertanya', 'nanya', 'menanyakan',
            'info', 'informasi', 'tentang', 'soal', 'perihal', 'terkait',
            'saya', 'kami', 'kita', 'anda', 'beliau', 'anak', 'ananda',
            'pii',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue khusus AI (worker-ai, koneksi redis-ai — lihat config/queue.php)
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', 'redis-ai'),
        'name' => env('AI_QUEUE_NAME', 'ai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | T-A050 — WhatsApp inbound (adaptasi K6: pilot via WAHA+n8n eksisting)
    |--------------------------------------------------------------------------
    | BUKAN BSP resmi — keputusan sadar-risiko, volume kecil. Outbound balasan
    | HANYA lewat Modules/Notifikasi (WaGatewayService) — Aturan Emas #5.
    |
    | RESOLUSI TENANT (v1 paling sederhana): satu session WAHA = satu nomor =
    | SATU tenant pilot. n8n meneruskan payload WAHA apa adanya ke
    | POST /api/ai/webhook/wa di domain CENTRAL; tenant diresolusi dari:
    |  1. field 'tenant' pada payload (bila workflow n8n menyertakan); atau
    |  2. peta session→tenant di 'session_tenant_map' (multi-session); atau
    |  3. 'tenant_default' (env AI_WA_TENANT) — deployment pilot satu tenant.
    | Webhook diamankan header X-Webhook-Token (hash_equals) — tanpa auth baru.
    */
    'wa' => [
        // Token rahasia header X-Webhook-Token. Kosong = webhook NONAKTIF (401).
        'webhook_token' => env('AI_WA_WEBHOOK_TOKEN'),

        // Tenant pilot default (id tenant) bila payload tak membawa 'tenant'
        // dan session tidak terdaftar di peta.
        'tenant_default' => env('AI_WA_TENANT'),

        // Peta eksplisit session WAHA → tenant id, contoh:
        // ['default' => 'albakids']. Diisi via config deployment bila kelak
        // lebih dari satu session/nomor.
        'session_tenant_map' => [],

        // Mode uji T-A050: balasan template statis (tanpa LLM). Intent nyata
        // datang di T-A051 — saat itu mode_uji dimatikan.
        'mode_uji' => (bool) env('AI_WA_MODE_UJI', true),

        // Kode template Notifikasi untuk balasan chatbot (dibuat idempoten
        // saat pertama dipakai; tenant boleh menonaktifkan via panel).
        'template_kode' => 'AI_BALASAN_WA',

        // Teks balasan (Indonesia santun). Kata kunci opt-out/opt-in baku.
        'balasan_mode_uji' => 'Terima kasih, pesan Anda sudah kami terima. Asisten Khadim Digital akan segera membalas. Ketik BERHENTI bila tidak ingin menerima balasan otomatis.',
        'balasan_opt_out' => 'Baik, kami tidak akan mengirim balasan otomatis lagi ke nomor ini. Ketik MULAI kapan saja bila ingin mengaktifkannya kembali. Terima kasih.',
        'balasan_opt_in' => 'Alhamdulillah, balasan otomatis asisten telah aktif kembali untuk nomor ini. Silakan kirim pertanyaan Anda.',
        'kata_opt_out' => ['STOP', 'BERHENTI'],
        'kata_opt_in' => ['MULAI'],

        /*
        |----------------------------------------------------------------------
        | T-A051 — verifikasi wali & intent inti (PRD §8.2, Blueprint §7.1)
        |----------------------------------------------------------------------
        */

        // Agen chatbot wali untuk jalur free-form RAG (seed AiAgentSeeder).
        'agent_key' => 'wali_chat',

        // Verifikasi ringan sesi pertama: tanggal lahir anak. Salah beruntun
        // maks_percobaan kali → kunci kunci_jam jam + saran hubungi admin.
        'verifikasi' => [
            'maks_percobaan' => (int) env('AI_WA_VERIFIKASI_MAKS', 3),
            'kunci_jam' => (int) env('AI_WA_VERIFIKASI_KUNCI_JAM', 24),
        ],

        // Router intent RULE-BASED murni (TANPA LLM) — peta TERURUT intent →
        // daftar regex; pola pertama yang cocok menang. Kebijakan di config,
        // bukan kode (Aturan Emas #11). Urutan PENTING: intent spesifik
        // (tagihan/jadwal/...) sebelum 'ppdb' (kata "daftar" generik) dan
        // 'bantuan' (sapaan) supaya tidak saling mencuri.
        'intents' => [
            'ganti_anak' => [
                '/\bganti\s+(?:anak|santri|ananda)\b/iu',
                '/\bpindah\s+ke\s+anak\b/iu',
            ],
            'tagihan' => [
                '/\b(?:tagihan|tunggakan|spp|syahriah|syahriyah|bayar(?:an|nya)?|membayar|pembayaran|cicilan|pelunasan|melunasi|lunas|iuran|angsuran)\b/iu',
            ],
            'jadwal' => [
                '/\b(?:jadwal(?:nya)?|pelajaran|mapel)\b/iu',
                '/\bjam\s+(?:belajar|pelajaran|ngaji|ngaos)\b/iu',
            ],
            'nilai' => [
                '/\b(?:nilai|rapor|raport|rapot)\b/iu',
                '/\bhasil\s+(?:ujian|ulangan)\b/iu',
            ],
            'tahfidz' => [
                '/\b(?:hafalan|tahfidz|tahfiz|setoran|murajaah|murojaah|muroja\'ah|juz)\b/iu',
            ],
            'izin' => [
                '/\b(?:izin|ijin|sakit)\b/iu',
            ],
            'kehadiran' => [
                '/\b(?:kehadiran|absen(?:si)?|presensi|alpa|bolos|hadir)\b/iu',
            ],
            'ppdb' => [
                '/\b(?:ppdb|spmb|pendaftaran|daftar|mendaftar\w*|gelombang)\b/iu',
                '/\bsantri\s+baru\b/iu',
            ],
            'bantuan' => [
                '/\b(?:bantuan|menu|help)\b/iu',
                '/\bbisa\s+(?:apa|bantu)\b/iu',
                // Sapaan TANPA isi pertanyaan → balas menu (template-first,
                // hemat LLM). Sapaan + pertanyaan tetap dirouting isinya.
                '/^\s*(?:ass?alamu[\w\'’]*|salam|halo|hai|selamat\s+(?:pagi|siang|sore|malam))\s*(?:wr\.?\s*wb\.?)?\s*$/iu',
            ],
        ],

        // GUARD ANTI-KARANG ANGKA: teks bernada uang yang lolos semua intent
        // (kalimat panjang) dipaksa ke intent 'tagihan' SEBELUM free-form RAG
        // — nominal hanya boleh datang dari ToolResult, bukan LLM.
        'pola_uang' => [
            '/\b(?:uang|biaya|nominal|rupiah|rp\.?|dana|keuangan|utang|hutang|tarif)\b/iu',
        ],

        // Intent ber-tool → tool resmi T-A033 + kategori disclaimer
        // DisclaimerInjector untuk footer jawaban data.
        'intent_tool_map' => [
            'tagihan' => ['tool' => 'getTagihan', 'kategori' => 'keuangan'],
            'jadwal' => ['tool' => 'getJadwal', 'kategori' => 'akademik'],
            'nilai' => ['tool' => 'getNilai', 'kategori' => 'akademik'],
            'tahfidz' => ['tool' => 'getTahfidz', 'kategori' => 'akademik'],
            'kehadiran' => ['tool' => 'getKehadiran', 'kategori' => 'umum'],
            'izin' => ['tool' => 'getIzin', 'kategori' => 'umum'],
        ],

        /*
        |----------------------------------------------------------------------
        | T-A052 — handoff manusia & Kotak Masuk WA (PRD §8.2)
        |----------------------------------------------------------------------
        | Tiga pemicu status percakapan 'handoff':
        |  (a) wali terverifikasi minta dilayani manusia (pola di bawah);
        |  (b) jawaban free-form degraded/tanpa-rujukan beruntun mencapai
        |      maks_gagal_beruntun → bot menawarkan handoff otomatis;
        |  (c) admin Ambil Alih manual dari halaman Kotak Masuk WA.
        | Saat handoff: bot DIAM total (pesan masuk tetap tersimpan + admin
        | diberi tahu via LogNotifikasi), KECUALI STOP/MULAI (opt-out/opt-in
        | tetap dihormati). Balasan manual admin lewat jalur outbound resmi
        | (WaBalasanService → WaGatewayService, Aturan Emas #5).
        */
        'handoff' => [
            // Keyword wali minta manusia (regex, kebijakan di config —
            // Aturan Emas #11). Hanya berlaku untuk wali TERVERIFIKASI;
            // nomor asing tetap dilayani template info publik.
            'pola_minta_manusia' => [
                '/\b(?:admin|operator)\b/iu',
                '/\bmanusia\b/iu',
                '/\bbicara\s+(?:dengan\s+|sama\s+|ke\s+)?orang\b/iu',
            ],

            // Ambang jawaban gagal (degraded/tanpa-rujukan) BERTURUT dalam
            // sesi sebelum bot menawarkan handoff otomatis.
            'maks_gagal_beruntun' => (int) env('AI_WA_HANDOFF_MAKS_GAGAL', 2),
        ],

        // ------------------------------------------------------------------
        // Template balasan T-A051 (Indonesia santun, polos kompatibel WA).
        // ------------------------------------------------------------------
        'balasan_minta_tgl_lahir' => 'Assalamu\'alaikum. Nomor Anda terdaftar sebagai wali santri. Demi keamanan data, mohon balas dengan tanggal lahir salah satu putra/putri Anda (contoh: 05-03-2014).',
        'balasan_bukan_tanggal' => 'Mohon balas dengan tanggal lahir putra/putri Anda untuk verifikasi (contoh: 05-03-2014). Untuk informasi umum pendaftaran, ketik PPDB.',
        'balasan_tgl_salah' => 'Mohon maaf, tanggal lahir belum cocok dengan data kami (percobaan :percobaan dari :maks). Silakan coba kembali, contoh format: 05-03-2014.',
        'balasan_terkunci' => 'Mohon maaf, verifikasi gagal beberapa kali. Demi keamanan data, layanan informasi untuk nomor ini dinonaktifkan sementara selama 24 jam. Silakan hubungi admin pesantren untuk bantuan.',
        'balasan_terverifikasi' => 'Alhamdulillah, verifikasi berhasil. Anda terhubung dengan data ananda :nama. Ketik MENU untuk melihat layanan, atau langsung ketik TAGIHAN / JADWAL / PPDB.',
        'balasan_tidak_dikenal' => 'Mohon maaf, nomor Anda belum terdaftar sebagai wali santri di pesantren kami. Kami hanya dapat melayani informasi umum: ketik PPDB untuk info pendaftaran santri baru. Untuk bantuan lain, silakan hubungi admin pesantren.',
        'balasan_info_ppdb' => 'Informasi pendaftaran santri baru (PPDB) dapat dilihat pada laman PPDB website pesantren. Untuk pertanyaan lebih lanjut (jadwal gelombang, syarat, biaya pendaftaran), silakan hubungi admin pesantren.:jam_layanan',
        'balasan_menu' => "Layanan asisten Khadim Digital:\n- TAGIHAN — tagihan & status pembayaran ananda\n- JADWAL — jadwal pelajaran kelas ananda\n- NILAI — nilai akademik ananda\n- HAFALAN — perkembangan tahfidz ananda\n- KEHADIRAN / IZIN — rekap kehadiran & izin ananda\n- PPDB — info pendaftaran santri baru\n- GANTI ANAK <nama> — pindah konteks putra/putri lain\nKetik BERHENTI bila tidak ingin menerima balasan otomatis.",
        'balasan_menu_publik' => "Layanan informasi umum:\n- PPDB — info pendaftaran santri baru\nUntuk layanan data santri, nomor Anda harus terdaftar sebagai wali. Silakan hubungi admin pesantren bila perlu bantuan.",
        'balasan_ganti_anak_sukses' => 'Baik, sekarang menampilkan data ananda :nama.',
        'balasan_ganti_anak_pilihan' => "Putra/putri yang tertaut dengan nomor Anda:\n:daftar\nKetik: GANTI ANAK diikuti nama, contoh: GANTI ANAK Ahmad.",
        'balasan_tanpa_anak' => 'Mohon maaf, belum ada data santri yang tertaut dengan akun wali Anda. Silakan hubungi admin pesantren untuk menautkan data ananda.',
        'balasan_kelas_belum_ada' => 'Mohon maaf, data kelas ananda :nama belum terdaftar sehingga jadwal belum dapat ditampilkan. Silakan hubungi admin pesantren.',

        // ------------------------------------------------------------------
        // Template balasan T-A052 (handoff manusia).
        // ------------------------------------------------------------------
        'balasan_handoff_diminta' => 'Baik, permintaan Anda kami teruskan kepada admin pesantren. Mohon berkenan menunggu, admin akan membalas melalui nomor ini pada jam layanan. Jazakumullahu khairan.',
        'balasan_handoff_otomatis' => 'Kami mohon maaf asisten belum dapat membantu dengan baik. Percakapan ini kami teruskan kepada admin pesantren — mohon berkenan menunggu balasan admin melalui nomor ini.',
    ],
];
