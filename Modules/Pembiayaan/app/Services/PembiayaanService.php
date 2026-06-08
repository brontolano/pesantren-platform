<?php

namespace Modules\Pembiayaan\Services;

use InvalidArgumentException;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Models\PengajuanPembiayaan;
use RuntimeException;

/**
 * Alur pengajuan pembiayaan: hitung DSCR, pilih agunan (WAJIB non-wakaf),
 * gerbang persetujuan Pembina (UU Yayasan Ps 37) + e-signature.
 */
class PembiayaanService
{
    public function __construct(private readonly DscrService $dscrService)
    {
    }

    /** Buat pengajuan + hitung DSCR. Status draft. */
    public function ajukan(array $data): PengajuanPembiayaan
    {
        $this->ensureTenantContext();

        $hasil = $this->dscrService->hitung(
            (float) ($data['ebitda_tahunan'] ?? 0),
            (float) ($data['angsuran_pokok'] ?? 0),
            (float) ($data['margin'] ?? 0),
        );

        // Agunan WAJIB non-wakaf (UU 41/2004 Ps 40).
        $asetId = $data['aset_agunan_id'] ?? null;
        if ($asetId !== null) {
            $this->pastikanAgunanSah($asetId);
        }

        return PengajuanPembiayaan::create([
            'bank_tujuan' => $data['bank_tujuan'] ?? null,
            'produk' => $data['produk'] ?? null,
            'plafon' => (float) ($data['plafon'] ?? 0),
            'tenor' => (int) ($data['tenor'] ?? 0),
            'margin' => (float) ($data['margin'] ?? 0),
            'ebitda_tahunan' => (float) ($data['ebitda_tahunan'] ?? 0),
            'dscr' => $hasil['dscr'],
            'zona' => $hasil['zona'],
            'aset_agunan_id' => $asetId,
            'status' => 'draft',
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    /** Pilih/ubah agunan — menolak aset wakaf. */
    public function pilihAgunan(PengajuanPembiayaan $pengajuan, int $asetId): PengajuanPembiayaan
    {
        $this->ensureTenantContext();
        $this->pastikanAgunanSah($asetId);
        $pengajuan->update(['aset_agunan_id' => $asetId]);

        return $pengajuan->fresh();
    }

    /** Ajukan ke Pembina untuk persetujuan agunan. */
    public function mintaPersetujuanPembina(PengajuanPembiayaan $pengajuan): PengajuanPembiayaan
    {
        $this->ensureTenantContext();
        if ($pengajuan->aset_agunan_id === null) {
            throw new InvalidArgumentException('Agunan belum dipilih.');
        }
        $pengajuan->update(['status' => 'menunggu_pembina']);

        return $pengajuan->fresh();
    }

    /** Pembina menyetujui dengan e-signature (TTE). */
    public function setujuiPembina(PengajuanPembiayaan $pengajuan, string $esignature): PengajuanPembiayaan
    {
        $this->ensureTenantContext();
        if ($pengajuan->status !== 'menunggu_pembina') {
            throw new InvalidArgumentException('Pengajuan belum diajukan ke Pembina.');
        }
        if (trim($esignature) === '') {
            throw new InvalidArgumentException('E-signature Pembina wajib diisi (UU Yayasan Ps 37).');
        }
        $pengajuan->update(['status' => 'disetujui', 'esignature_pembina' => $esignature]);

        return $pengajuan->fresh();
    }

    private function pastikanAgunanSah(int $asetId): void
    {
        $aset = Aset::find($asetId);
        if ($aset === null) {
            throw new RuntimeException('Aset agunan tidak ditemukan.');
        }
        if ($aset->is_wakaf) {
            throw new InvalidArgumentException('Aset wakaf TIDAK dapat dijadikan agunan (UU 41/2004 Ps 40).');
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
