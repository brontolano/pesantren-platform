<?php

namespace Modules\AI\Services\Tools;

/**
 * T-A033 — definisi satu tool data resmi terdaftar di ToolRegistry.
 *
 * skema = JSON-schema RINGAN per parameter:
 *   ['santri_id' => ['type' => 'integer', 'required' => true, 'deskripsi' => '...']]
 * tipe yang dikenal: integer, string, date (Y-m-d). Kunci argumen di luar
 * skema DITOLAK (fail-closed, sama seperti validasi payload setting).
 *
 * scopeKey/peran adalah DEFAULT — dapat dioverride config('ai.tools.scope_map')
 * dan config('ai.tools.peran_map') (kebijakan di konfigurasi, bukan kode).
 *
 * terikatSantri = tool berdimensi per-santri: pemanggil berperan WALI wajib
 * tertaut ke santri tsb via App\Models\AccessWali (validasi di ToolRegistry).
 */
final class ToolDefinition
{
    /**
     * @param  array<string, array<string, mixed>>  $skema
     * @param  list<string>  $peran  role spatie yang diizinkan (default)
     * @param  class-string  $adapterClass
     */
    public function __construct(
        public readonly string $nama,
        public readonly string $deskripsi,
        public readonly array $skema,
        public readonly ?string $scopeKey,
        public readonly array $peran,
        public readonly string $adapterClass,
        public readonly string $adapterMethod,
        public readonly bool $terikatSantri = false,
    ) {}
}
