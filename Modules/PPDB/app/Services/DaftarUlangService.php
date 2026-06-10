<?php

namespace Modules\PPDB\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\PPDB\Models\CalonSantri;
use RuntimeException;

/**
 * Daftar ulang (F3/REREG): peserta LULUS → record Santri aktif (Kesantrian) +
 * tautan akun wali. Transaksional & IDEMPOTEN: hanya status 'lulus' yang diproses;
 * sukses → status_pendaftaran 'daftar_ulang' (mencegah konversi ganda).
 * Aditif — tidak mengubah skema modul lain.
 */
class DaftarUlangService
{
    public const STATUS_SELESAI = 'daftar_ulang';

    public function process(CalonSantri $calon): Santri
    {
        if ((string) $calon->status_pendaftaran !== 'lulus') {
            throw new RuntimeException('Daftar ulang hanya untuk peserta berstatus LULUS.');
        }

        if (! $calon->tanggal_lahir) {
            throw new RuntimeException('Lengkapi tanggal lahir pada biodata sebelum daftar ulang.');
        }

        return DB::transaction(function () use ($calon): Santri {
            $nis = (string) ($calon->nomor_pendaftaran ?: ('PPDB-'.$calon->id));

            // Idempoten: bila Santri dgn NIS ini sudah ada, jangan duplikasi.
            $santri = Santri::query()->where('nis', $nis)->first();

            if ($santri === null) {
                $tahun = (int) Carbon::now()->year;

                $ortu = (array) ($calon->data_ortu ?? []);

                $santri = Santri::create([
                    'nis' => $nis,
                    'nama' => (string) $calon->nama,
                    'tanggal_lahir' => $calon->tanggal_lahir->format('Y-m-d'),
                    'tempat_lahir' => $calon->tempat_lahir,
                    'jenis_kelamin' => $this->mapJenisKelamin((string) $calon->jenis_kelamin),
                    'asal_sekolah' => $calon->asal_sekolah,
                    'alamat' => $calon->alamat ?? null,
                    'no_hp_wali' => $calon->no_hp_wali,
                    'email_wali' => $calon->email_wali,
                    'nama_ayah' => $calon->nama_ayah ?? ($ortu['nama_ayah'] ?? null),
                    'nama_ibu' => $calon->nama_ibu ?? ($ortu['nama_ibu'] ?? null),
                    'no_hp_ayah' => $ortu['no_hp_ayah'] ?? null,
                    'no_hp_ibu' => $ortu['no_hp_ibu'] ?? null,
                    'status' => 'aktif',
                    'tahun_angkatan' => $tahun.'/'.($tahun + 1),
                    'wali_user_id' => $calon->user_id,
                ]);
            }

            // Titik penetapan AKUN: konversi calon→santri membuat akun login
            // santri (NIS+PIN) + akun wali (multi-anak via no_hp_wali). Best-effort —
            // kegagalan akun TIDAK menggagalkan daftar ulang. PIN dikirim TERPISAH
            // oleh staf lewat tombol "Kirim Kredensial".
            try {
                $akun = app(\Modules\Kesantrian\Services\SantriAccountService::class);
                $akun->provision($santri->fresh());
                $akun->tautkanWali($santri->fresh());
            } catch (\Throwable $e) {
                report($e);
            }

            $calon->forceFill(['status_pendaftaran' => self::STATUS_SELESAI])->save();

            return $santri;
        });
    }

    private function mapJenisKelamin(string $jk): string
    {
        $v = strtolower(trim($jk));

        return in_array($v, ['p', 'perempuan', 'female', 'wanita'], true) ? 'P' : 'L';
    }
}
