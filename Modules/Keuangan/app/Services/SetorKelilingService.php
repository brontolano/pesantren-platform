<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\SetorKelilingItem;
use Modules\Keuangan\Models\SetorKelilingSesi;
use Modules\Kesantrian\Models\Santri;

/**
 * Setor keliling — petugas mencatat setoran di lapangan sebagai DRAF (tidak
 * menyentuh saldo), bendahara menyetujui → posting per item LEWAT
 * TabunganService::setor (jalur uang teruji; JANGAN posting saldo langsung).
 *
 * Idempotensi dua lapis: (1) status sesi dicek pada baris ter-LOCK — approve
 * ganda ditolak; (2) item yang sudah punya tabungan_transaksi_id dilewati.
 */
class SetorKelilingService
{
    public function __construct(
        private readonly TabunganService $tabungan,
        private readonly KeuanganNotifikasiService $notifikasi,
    ) {
    }

    public function buatSesi(int $petugasId, ?string $lokasi = null, ?string $tanggal = null): SetorKelilingSesi
    {
        return SetorKelilingSesi::create([
            'petugas_id' => $petugasId,
            'tanggal' => $tanggal ?? now()->toDateString(),
            'lokasi' => $lokasi,
            'status' => 'draf',
        ]);
    }

    public function tambahItem(SetorKelilingSesi $sesi, Santri|int $santri, float $nominal, ?string $catatan = null): SetorKelilingItem
    {
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal setoran harus lebih dari 0.');
        }

        // Status WAJIB dicek pada baris ter-LOCK (bukan instance in-memory yang
        // bisa basi) — tanpa ini item bisa diselundupkan setelah sesi diajukan/
        // disetujui dan total tak lagi sinkron dengan uang terposting.
        return DB::transaction(function () use ($sesi, $santri, $nominal, $catatan): SetorKelilingItem {
            $locked = SetorKelilingSesi::query()->lockForUpdate()->findOrFail($sesi->id);

            if ($locked->status !== 'draf') {
                throw new InvalidArgumentException('Item hanya bisa ditambah saat sesi masih draf.');
            }

            $item = $locked->items()->create([
                'tenant_id' => $locked->tenant_id,
                'santri_id' => $santri instanceof Santri ? $santri->id : $santri,
                'nominal' => $nominal,
                'catatan' => $catatan,
            ]);

            $locked->update(['total' => (float) $locked->items()->sum('nominal')]);

            return $item;
        });
    }

    /** Petugas lapor progres: kunci draf → menunggu persetujuan bendahara. */
    public function ajukan(SetorKelilingSesi $sesi): SetorKelilingSesi
    {
        return DB::transaction(function () use ($sesi): SetorKelilingSesi {
            $locked = SetorKelilingSesi::query()->lockForUpdate()->findOrFail($sesi->id);

            if ($locked->status !== 'draf') {
                throw new InvalidArgumentException('Hanya sesi draf yang bisa diajukan.');
            }
            if (! $locked->items()->exists()) {
                throw new InvalidArgumentException('Sesi tanpa item setoran tidak bisa diajukan.');
            }

            $locked->update(['status' => 'diajukan', 'total' => (float) $locked->items()->sum('nominal')]);

            return $locked;
        });
    }

    /** Bendahara setuju: posting SEMUA item ke saldo via TabunganService. */
    public function setujui(SetorKelilingSesi $sesi, int $approverId): SetorKelilingSesi
    {
        $locked = DB::transaction(function () use ($sesi, $approverId): SetorKelilingSesi {
            $locked = SetorKelilingSesi::query()->lockForUpdate()->findOrFail($sesi->id);

            if ($locked->status !== 'diajukan') {
                throw new InvalidArgumentException('Hanya sesi berstatus diajukan yang bisa disetujui.');
            }

            foreach ($locked->items()->get() as $item) {
                if ($item->tabungan_transaksi_id !== null) {
                    continue; // backstop: item sudah terposting, jangan dobel
                }

                $trx = $this->tabungan->setor($item->santri_id, (float) $item->nominal, [
                    'keterangan' => trim('Setor keliling #'.$locked->id.' '.($locked->lokasi ?? '')),
                    'tanggal' => $locked->tanggal->toDateString(),
                    'created_by' => $locked->petugas_id,
                ]);

                $item->update(['tabungan_transaksi_id' => $trx->id]);
            }

            $locked->update([
                'status' => 'disetujui',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $locked;
        });

        // Notif WA wali per item — SETELAH commit, tak boleh menggagalkan uang.
        foreach ($locked->items()->with('santri')->get() as $item) {
            $this->notifikasi->setorKelilingDisetujui($item);
        }

        return $locked;
    }

    public function tolak(SetorKelilingSesi $sesi, int $approverId, string $alasan): SetorKelilingSesi
    {
        return DB::transaction(function () use ($sesi, $approverId, $alasan): SetorKelilingSesi {
            $locked = SetorKelilingSesi::query()->lockForUpdate()->findOrFail($sesi->id);

            if ($locked->status !== 'diajukan') {
                throw new InvalidArgumentException('Hanya sesi berstatus diajukan yang bisa ditolak.');
            }

            $locked->update([
                'status' => 'ditolak',
                'approved_by' => $approverId,
                'approved_at' => now(),
                'alasan_tolak' => $alasan,
            ]);

            return $locked;
        });
    }
}
