<?php

namespace Modules\AI\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AI\Models\AiModelConfig;

/**
 * Seed default router model AI (tabel CENTRAL ai_model_config) — IDEMPOTEN.
 *
 * v1 (keputusan K3): semua tier chat + embedding diarahkan ke provider lokal
 * sesuai hard-default config('ai.router.defaults') — satu sumber kebenaran,
 * tanpa mengulang nama provider/model di sini.
 *
 * Jalankan manual di central:
 *   php artisan db:seed --class="Modules\\AI\\Database\\Seeders\\AiModelConfigSeeder"
 */
class AiModelConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('ai.router.defaults', []) as $tier => $default) {
            if (! is_array($default) || ! isset($default['provider'], $default['model'])) {
                continue;
            }

            AiModelConfig::query()->updateOrCreate(
                ['tier' => $tier, 'agent_key' => null],
                [
                    'provider' => (string) $default['provider'],
                    'model' => (string) $default['model'],
                    'max_context_tokens' => (int) ($default['max_context_tokens'] ?? 4096),
                    'options' => (array) ($default['options'] ?? []),
                    'is_active' => true,
                ],
            );
        }
    }
}
