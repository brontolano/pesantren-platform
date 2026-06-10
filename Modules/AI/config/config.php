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
    | Queue khusus AI (worker-ai, koneksi redis-ai — lihat config/queue.php)
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', 'redis-ai'),
        'name' => env('AI_QUEUE_NAME', 'ai'),
    ],
];
