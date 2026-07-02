<?php

namespace Modules\Payroll\Console;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Notifikasi\Services\NotifDispatcher;
use Modules\Payroll\Models\KontrakKerja;

/**
 * WP-16 — Pengingat kontrak/SK akan habis (terjadwal harian).
 *
 * Sasaran: kontrak status 'aktif' dengan `selesai` ≤ X hari lagi.
 * X = Setting per-tenant `payroll.kontrak_reminder_hari` (default 60) —
 * policy-first, tiap pesantren bisa beda ambang.
 *
 * Idempoten per hari: kolom last_reminded_at di-stamp SEBELUM kirim;
 * baris yang sudah di-stamp hari ini dilewati (tidak spam dobel).
 * Kirim via NotifDispatcher (fire-and-forget, template KONTRAK_AKAN_HABIS).
 */
class CekKontrakHabisCommand extends Command
{
    protected $signature = 'payroll:cek-kontrak-habis
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Kirim pengingat kontrak/SK pegawai yang akan habis masa berlakunya';

    public function handle(): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        $rows = [];
        foreach ($tenants as $tenant) {
            // Per-tenant terisolasi: tenant belum migrasi / error WA tidak
            // boleh menggugurkan sisa tenant di jadwal harian.
            try {
                $hasil = $tenant->run(function (): array {
                    if (! Schema::hasTable('kontrak_kerja')) {
                        return ['kandidat' => 0, 'terkirim' => 0];
                    }

                    return $this->ingatkanTenantAktif();
                });
                $rows[] = [$tenant->getKey(), $hasil['kandidat'], $hasil['terkirim']];
            } catch (\Throwable $e) {
                report($e);
                $rows[] = [$tenant->getKey(), 'GAGAL', $e->getMessage()];
            }
        }

        $this->table(['Tenant', 'Kandidat', 'Terkirim'], $rows);

        return self::SUCCESS;
    }

    /** @return array{kandidat:int, terkirim:int} */
    private function ingatkanTenantAktif(): array
    {
        $kandidat = KontrakKerja::query()
            ->mendekatiHabis()
            ->where(function ($q): void {
                $q->whereNull('last_reminded_at')
                    ->orWhereDate('last_reminded_at', '<', today());
            })
            ->with('employee')
            ->get();

        $dispatcher = app(NotifDispatcher::class);
        $admin = Setting::getTenantStringValue('payroll_admin_whatsapp')
            ?? Setting::getTenantStringValue('admission_admin_whatsapp');

        $terkirim = 0;
        foreach ($kandidat as $kontrak) {
            // Stamp DULU — bila kirim gagal pun tidak retry-spam hari ini;
            // besok otomatis dicoba lagi (last_reminded_at < today).
            $kontrak->forceFill(['last_reminded_at' => today()])->save();

            $hasil = $dispatcher->kirim('kontrak_akan_habis', [
                'wa' => array_filter([$admin]),
                'data' => [
                    'nama_pegawai' => $kontrak->employee?->nama ?? '-',
                    'jenis' => KontrakKerja::JENIS[$kontrak->jenis] ?? $kontrak->jenis,
                    'nomor' => $kontrak->nomor,
                    'tanggal_selesai' => $kontrak->selesai?->translatedFormat('d M Y') ?? '-',
                    'sisa_hari' => (string) (int) today()->diffInDays($kontrak->selesai, false),
                    'nama_pesantren' => Setting::getTenantStringValue('branding_name')
                        ?? (tenant('name') ?? 'Pesantren'),
                ],
            ]);
            $terkirim += (int) ($hasil['terkirim'] ?? 0);
        }

        return ['kandidat' => $kandidat->count(), 'terkirim' => $terkirim];
    }
}
