<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTopup;
use RuntimeException;

/**
 * Pengajuan & verifikasi setor saldo (topup) tabungan (Demo PWA Wali W3).
 *
 * Alur status: 'menunggu' -> 'disetujui' | 'ditolak'.
 *
 *   - ajukan():  wali mengirim bukti transfer  -> status 'menunggu'.
 *   - setujui(): bendahara menyetujui          -> setor saldo via TabunganService
 *                (yang sudah lockForUpdate)     -> status 'disetujui'.
 *   - tolak():   bendahara menolak             -> simpan alasan -> status 'ditolak'.
 */
class TopupService
{
    public function __construct(
        private TabunganService $tabunganService = new TabunganService
    ) {}

    /**
     * Ajukan permintaan setor saldo (oleh wali). Belum menambah saldo.
     *
     * Payload: nominal, tanggal_transfer, nama_pengirim, bukti (path file),
     *          created_by (opsional).
     */
    public function ajukan(Tabungan $tabungan, array $payload): TabunganTopup
    {
        $this->ensureTenantContext();

        $nominal = (float) ($payload['nominal'] ?? 0);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal topup harus lebih dari 0.');
        }

        return TabunganTopup::create([
            'tabungan_id' => $tabungan->id,
            'nominal' => $nominal,
            'tanggal_transfer' => $payload['tanggal_transfer'],
            'nama_pengirim' => $payload['nama_pengirim'],
            'bukti' => $payload['bukti'],
            'status' => 'menunggu',
            'created_by' => $payload['created_by'] ?? null,
        ]);
    }

    /**
     * Setujui topup (oleh bendahara). Menambah saldo lewat TabunganService::setor
     * yang sudah mengunci baris tabungan (lockForUpdate) secara atomik.
     */
    public function setujui(TabunganTopup $topup, int $verifiedBy): TabunganTopup
    {
        $this->ensureTenantContext();

        // RACE GUARD: status WAJIB dicek pada baris ter-LOCK di dalam SATU transaksi
        // dengan setor + update status. Tanpa ini, dua klik "setujui" bersamaan
        // sama-sama lolos cek 'menunggu' → setor() jalan dua kali → SALDO NAIK DOBEL.
        // Transaksi juga menjamin setor & status berubah bersama (tak ada setor yatim).
        $hasil = DB::transaction(function () use ($topup, $verifiedBy): TabunganTopup {
            $locked = TabunganTopup::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'menunggu') {
                throw new InvalidArgumentException('Topup sudah diproses.');
            }

            $tabungan = $locked->tabungan;

            $this->tabunganService->setor($tabungan->santri_id, (float) $locked->nominal, [
                'keterangan' => "Setor saldo (topup #{$locked->id}) dari {$locked->nama_pengirim}",
                'created_by' => $verifiedBy,
            ]);

            $locked->update([
                'status' => 'disetujui',
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            return $locked->fresh();
        });

        // Notif WA ke wali (TAB-4) — fire-and-forget SETELAH commit.
        app(KeuanganNotifikasiService::class)->topupDisetujui($hasil);

        return $hasil;
    }

    /**
     * Tolak topup (oleh bendahara) dengan alasan penolakan. Saldo tidak berubah.
     */
    public function tolak(TabunganTopup $topup, int $verifiedBy, string $alasan): TabunganTopup
    {
        $this->ensureTenantContext();

        // Lock + cek status di transaksi yang sama — cegah tolak menimpa topup
        // yang sedang/baru disetujui proses lain (saldo sudah naik).
        $hasil = DB::transaction(function () use ($topup, $verifiedBy, $alasan): TabunganTopup {
            $locked = TabunganTopup::query()->whereKey($topup->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'menunggu') {
                throw new InvalidArgumentException('Topup sudah diproses.');
            }

            $locked->update([
                'status' => 'ditolak',
                'alasan_penolakan' => $alasan,
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
            ]);

            return $locked->fresh();
        });

        // Notif WA ke wali (TAB-4) — fire-and-forget SETELAH commit.
        app(KeuanganNotifikasiService::class)->topupDitolak($hasil, $alasan);

        return $hasil;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
