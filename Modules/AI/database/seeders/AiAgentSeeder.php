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
 * Agen produk nyata (chatbot wali, generator soal, dst) menyusul di epic
 * fiturnya masing-masing.
 *
 * Jalankan manual di central:
 *   php artisan db:seed --class="Modules\\AI\\Database\\Seeders\\AiAgentSeeder"
 */
class AiAgentSeeder extends Seeder
{
    public function run(): void
    {
        AiAgent::query()->updateOrCreate(
            ['key' => 'uji-internal'],
            [
                'nama' => 'Agen Uji Internal',
                'deskripsi' => 'Agen uji kesehatan/QA internal platform — bukan fitur pengguna.',
                'is_active' => true,
                'default_tier' => 'ekonomis',
            ],
        );

        // Versi 1: firstOrCreate (BUKAN updateOrCreate) — isi versi lama tidak
        // pernah diedit (artefak ber-versi, guard model menolak).
        $versi1 = AiPromptVersion::query()->firstOrCreate(
            ['agent_key' => 'uji-internal', 'versi' => 1],
            [
                'system_prompt' => 'Anda asisten internal platform untuk pengujian teknis. Jawab singkat dan netral.',
                'changelog' => 'Versi awal agen uji internal (T-A004).',
                'status' => AiPromptVersion::STATUS_DRAFT,
            ],
        );

        // Aktifkan versi 1 hanya bila agen belum punya versi aktif sama sekali
        // (idempoten: seeder ulang tidak menimpa pilihan operator).
        $adaAktif = AiPromptVersion::query()
            ->where('agent_key', 'uji-internal')
            ->where('status', AiPromptVersion::STATUS_AKTIF)
            ->exists();

        if (! $adaAktif) {
            $versi1->update(['status' => AiPromptVersion::STATUS_AKTIF]);
        }
    }
}
