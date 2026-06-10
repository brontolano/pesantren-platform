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
    | Queue khusus AI (worker-ai, koneksi redis-ai — lihat config/queue.php)
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', 'redis-ai'),
        'name' => env('AI_QUEUE_NAME', 'ai'),
    ],
];
