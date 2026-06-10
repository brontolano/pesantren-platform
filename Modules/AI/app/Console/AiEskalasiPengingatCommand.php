<?php

namespace Modules\AI\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\AI\Services\Escalation\EscalationService;
use Throwable;

/**
 * T-A023 — ai:eskalasi-pengingat: penjaga SLA antrean "Tanya Ustadz".
 *
 * Berbeda dari ai:anomali (murni central), antrean eskalasi hidup di TABEL
 * TENANT — perintah ini meminjam pola loop tenant RekonsiliasiHarianCommand:
 * Tenant::all → $tenant->run(). Per tenant:
 *  1. autoAssignBaru()    — eskalasi 'baru' menggantung > config menit
 *                           ditugaskan ke tujuan default setting Wizard;
 *  2. kirimPengingatSla() — eskalasi 'ditugaskan' lewat sla_deadline & belum
 *                           diingatkan → notifikasi pengingat SEKALI.
 *
 * Selalu exit 0 (pola penjadwal platform): kegagalan satu tenant di-log dan
 * tidak menghentikan tenant lain.
 */
class AiEskalasiPengingatCommand extends Command
{
    protected $signature = 'ai:eskalasi-pengingat
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Auto-assign eskalasi Tanya Ustadz yang menggantung + pengingat SLA ke penerima tugas (per tenant)';

    public function handle(EscalationService $service): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        if ($tenants->isEmpty()) {
            $this->error($target ? "Tenant '{$target}' tidak ditemukan." : 'Tidak ada tenant.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($tenants as $tenant) {
            try {
                [$ditugaskan, $diingatkan] = $tenant->run(function () use ($service): array {
                    // Tenant lama yang belum termigrasi kolom T-A023 dilewati
                    // anggun (pola AiSettingProvisioner).
                    if (! Schema::hasTable('ai_escalation')
                        || ! Schema::hasColumn('ai_escalation', 'sla_deadline')) {
                        return [0, 0];
                    }

                    return [$service->autoAssignBaru(), $service->kirimPengingatSla()];
                });

                $rows[] = [$tenant->getKey(), $ditugaskan, $diingatkan, 'OK'];
            } catch (Throwable $e) {
                Log::error('ai:eskalasi-pengingat gagal untuk tenant.', [
                    'tenant' => $tenant->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [$tenant->getKey(), '-', '-', 'GAGAL'];
            }
        }

        $this->table(['Tenant', 'Auto-assign', 'Pengingat SLA', 'Status'], $rows);
        $this->info('Pemeriksaan antrean eskalasi Tanya Ustadz selesai.');

        return self::SUCCESS;
    }
}
