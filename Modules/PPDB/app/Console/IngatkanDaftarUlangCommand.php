<?php

namespace Modules\PPDB\Console;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Notifikasi\Services\NotifDispatcher;
use Modules\PPDB\Models\CalonSantri;

/**
 * NOTIF-1/DASH-3 — Pengingat daftar ulang via WA (terjadwal harian).
 *
 * Sasaran: calon berstatus 'lulus' yang BELUM daftar ulang
 * (tanggal_registrasi_ulang null). Kirim via NotifDispatcher
 * (fire-and-forget, gated template PENGINGAT_DAFTAR_ULANG per tenant —
 * tenant bisa menonaktifkan/mengubah isi).
 */
class IngatkanDaftarUlangCommand extends Command
{
    protected $signature = 'ppdb:ingatkan-daftar-ulang
        {tenant? : ID tenant tertentu; kosong = SEMUA tenant}';

    protected $description = 'Kirim pengingat WA ke calon lulus yang belum daftar ulang';

    public function handle(): int
    {
        $target = $this->argument('tenant');

        $tenants = $target
            ? Tenant::query()->where('id', $target)->get()
            : Tenant::query()->orderBy('id')->get();

        $rows = [];
        foreach ($tenants as $tenant) {
            $hasil = $tenant->run(fn (): array => $this->ingatkanTenantAktif());
            $rows[] = [$tenant->getKey(), $hasil['kandidat'], $hasil['terkirim']];
        }

        $this->table(['Tenant', 'Kandidat', 'Terkirim'], $rows);

        return self::SUCCESS;
    }

    /** @return array{kandidat:int, terkirim:int} */
    private function ingatkanTenantAktif(): array
    {
        $dispatcher = app(NotifDispatcher::class);
        $kandidat = CalonSantri::query()
            ->where('status_pendaftaran', 'lulus')
            ->whereNull('tanggal_registrasi_ulang')
            ->get();

        $terkirim = 0;
        foreach ($kandidat as $calon) {
            $hasil = $dispatcher->kirim('pengingat_daftar_ulang', [
                'wa' => $this->nomorTujuan($calon),
                'data' => [
                    'nama_calon' => $calon->nama ?? '-',
                    'nomor_pendaftaran' => $calon->nomor_pendaftaran ?? '-',
                    'nama_pesantren' => Setting::getTenantStringValue('branding_name')
                        ?? (tenant('name') ?? 'Pesantren'),
                ],
            ]);
            $terkirim += (int) ($hasil['terkirim'] ?? 0);
        }

        return ['kandidat' => $kandidat->count(), 'terkirim' => $terkirim];
    }

    /** @return array<int,string> No. WA peserta + ortu/wali (dedup, kosong dibuang). */
    private function nomorTujuan(CalonSantri $calon): array
    {
        $ortu = (array) ($calon->data_ortu ?? []);

        return array_values(array_unique(array_filter([
            $calon->no_hp_wali ?? null,
            $ortu['no_hp_ayah'] ?? null,
            $ortu['no_hp_ibu'] ?? null,
            $ortu['no_hp_peserta'] ?? null,
        ])));
    }
}
