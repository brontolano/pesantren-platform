<?php

namespace Modules\Kegiatan\Services;

use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kegiatan\Models\IzinSantri;
use Modules\Kegiatan\Models\KunjunganWali;
use Modules\Kesantrian\Models\Santri;
use Illuminate\Support\Collection;

class KegiatanService
{
    // ── Kegiatan ──────────────────────────────────────────────────────────

    public function buatKegiatan(string $tenantId, array $data): Kegiatan
    {
        return Kegiatan::create([
            'tenant_id'            => $tenantId,
            'nama'                 => $data['nama'],
            'deskripsi'            => $data['deskripsi'] ?? null,
            'jenis'                => $data['jenis'] ?? 'umum',
            'mulai'                => $data['mulai'],
            'selesai'              => $data['selesai'] ?? null,
            'lokasi'               => $data['lokasi'] ?? null,
            'status'               => 'terjadwal',
            'penanggung_jawab_id'  => $data['penanggung_jawab_id'] ?? null,
        ]);
    }

    public function catatPresensi(Kegiatan $kegiatan, Santri $santri, string $status, ?string $keterangan = null): PresensiKegiatan
    {
        return PresensiKegiatan::updateOrCreate(
            ['tenant_id' => $kegiatan->tenant_id, 'kegiatan_id' => $kegiatan->getKey(), 'santri_id' => $santri->getKey()],
            ['status' => $status, 'keterangan' => $keterangan]
        );
    }

    public function getPresensiKegiatan(Kegiatan $kegiatan): Collection
    {
        return PresensiKegiatan::query()
            ->where('kegiatan_id', $kegiatan->getKey())
            ->with('santri')
            ->get();
    }

    // ── Izin ──────────────────────────────────────────────────────────────

    public function ajukanIzin(Santri $santri, array $data): IzinSantri
    {
        return IzinSantri::create([
            'tenant_id'      => $santri->tenant_id,
            'santri_id'      => $santri->getKey(),
            'jenis_izin'     => $data['jenis_izin'],
            'tanggal_mulai'  => $data['tanggal_mulai'],
            'tanggal_selesai'=> $data['tanggal_selesai'],
            'alasan'         => $data['alasan'],
            'status'         => 'pending',
        ]);
    }

    public function setujuiIzin(IzinSantri $izin, int $userId, ?string $catatan = null): IzinSantri
    {
        $izin->update([
            'status'           => 'disetujui',
            'disetujui_oleh'   => $userId,
            'catatan_pengurus' => $catatan,
        ]);

        return $izin->fresh();
    }

    public function tolakIzin(IzinSantri $izin, int $userId, ?string $catatan = null): IzinSantri
    {
        $izin->update([
            'status'           => 'ditolak',
            'disetujui_oleh'   => $userId,
            'catatan_pengurus' => $catatan,
        ]);

        return $izin->fresh();
    }

    public function konfirmasiKembali(IzinSantri $izin): IzinSantri
    {
        $izin->update([
            'sudah_kembali'          => true,
            'tanggal_kembali_aktual' => now(),
        ]);

        return $izin->fresh();
    }

    public function getIzinPending(): Collection
    {
        return IzinSantri::query()
            ->where('status', 'pending')
            ->with('santri')
            ->orderByDesc('tanggal_mulai')
            ->get();
    }

    // ── Kunjungan ─────────────────────────────────────────────────────────

    public function catatKunjungan(Santri $santri, array $data): KunjunganWali
    {
        return KunjunganWali::create([
            'tenant_id'       => $santri->tenant_id,
            'santri_id'       => $santri->getKey(),
            'nama_pengunjung' => $data['nama_pengunjung'],
            'hubungan'        => $data['hubungan'],
            'tanggal'         => $data['tanggal'] ?? today()->toDateString(),
            'jam_datang'      => $data['jam_datang'] ?? null,
            'keperluan'       => $data['keperluan'] ?? null,
            'status'          => 'hadir',
        ]);
    }
}
