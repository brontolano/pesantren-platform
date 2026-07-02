<?php

namespace Modules\AI\Console;

use Illuminate\Console\Command;
use Modules\AI\Models\AiAgent;
use Modules\AI\Models\AiPromptVersion;
use Modules\AI\Services\Prompts\PromptRegistry;
use Throwable;

/**
 * ai:prompt — operasi prompt registry untuk operator CLI (T-A004).
 *
 * UI Filament operator menyusul di EP-A9 (T-A090); untuk kini perintah ini
 * cukup untuk: melihat daftar agen+versi, mengaktifkan versi, dan rollback —
 * semuanya tanpa deploy (cache ter-invalidasi otomatis oleh PromptRegistry).
 */
class AiPromptCommand extends Command
{
    protected $signature = 'ai:prompt
        {aksi : list|aktifkan|rollback}
        {--agent= : Kunci agen (wajib untuk aktifkan/rollback)}
        {--versi= : Nomor versi target (wajib untuk aktifkan/rollback)}';

    protected $description = 'Kelola prompt registry AI: daftar agen & versi, aktifkan versi, rollback ke versi lama';

    public function handle(PromptRegistry $registry): int
    {
        return match ($this->argument('aksi')) {
            'list' => $this->daftar(),
            'aktifkan' => $this->aktifkan($registry, 'diaktifkan'),
            'rollback' => $this->aktifkan($registry, 'dipulihkan (rollback)'),
            default => $this->salahPakai(),
        };
    }

    private function daftar(): int
    {
        $agents = AiAgent::query()->orderBy('key')->get();

        if ($agents->isEmpty()) {
            $this->info('Belum ada agen terdaftar. Jalankan seeder AiAgentSeeder terlebih dahulu.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($agents as $agent) {
            $versions = AiPromptVersion::query()
                ->where('agent_key', $agent->key)
                ->orderBy('versi')
                ->get();

            if ($versions->isEmpty()) {
                $rows[] = [$agent->key, $agent->is_active ? 'aktif' : 'nonaktif', '-', '-', 'Belum ada versi prompt'];

                continue;
            }

            foreach ($versions as $v) {
                $rows[] = [
                    $agent->key,
                    $agent->is_active ? 'aktif' : 'nonaktif',
                    'v'.$v->versi,
                    $v->status,
                    (string) $v->changelog,
                ];
            }
        }

        $this->table(['Agen', 'Status agen', 'Versi', 'Status versi', 'Changelog'], $rows);

        return self::SUCCESS;
    }

    private function aktifkan(PromptRegistry $registry, string $kata): int
    {
        $agent = (string) $this->option('agent');
        $versi = $this->option('versi');

        if ($agent === '' || $versi === null || ! ctype_digit((string) $versi)) {
            $this->error('Mohon sertakan --agent= dan --versi= (angka). Contoh: ai:prompt aktifkan --agent=uji-internal --versi=2');

            return self::INVALID;
        }

        try {
            $target = $registry->aktifkanVersi($agent, (int) $versi);
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            "Versi %d agen '%s' berhasil %s — berlaku segera tanpa deploy (cache sudah disegarkan).",
            $target->versi,
            $agent,
            $kata,
        ));

        return self::SUCCESS;
    }

    private function salahPakai(): int
    {
        $this->error('Aksi tidak dikenal. Pilihan: list | aktifkan | rollback.');

        return self::INVALID;
    }
}
