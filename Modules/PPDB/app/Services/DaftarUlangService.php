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

                $santri = Santri::create(array_merge($this->mapBiodata($calon), [
                    'nis' => $nis,
                    'status' => 'aktif',
                    'tahun_angkatan' => $tahun.'/'.($tahun + 1),
                    'wali_user_id' => $calon->user_id,
                    // Persetujuan Kebijakan Privasi Sistem (dicentang wali saat
                    // daftar PPDB) ikut ke record santri — dasar penggunaan
                    // foto/ttd/sampel tulisan utk pengembangan internal.
                    'consent_ai_dataset' => $calon->consent_kebijakan_at !== null,
                    'consented_at' => $calon->consent_kebijakan_at,
                    'consented_by' => $calon->nama_wali ?: $calon->nama_ayah,
                ]));
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

    /**
     * Salin SELURUH biodata calon → kolom santri yang setara (parity 1:1).
     * Kunci kolom sama persis di kedua tabel, jadi cukup copy by nama.
     *
     * @return array<string, mixed>
     */
    private function mapBiodata(CalonSantri $calon): array
    {
        $shared = [
            // Pribadi
            'nama', 'tempat_lahir', 'nik', 'nisn', 'agama', 'nama_panggilan', 'cita_cita', 'anak_ke', 'jumlah_saudara',
            // Alamat / domisili
            'alamat', 'rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            // Pendidikan asal
            'asal_sekolah', 'jenjang_asal', 'npsn', 'tahun_lulus',
            // Ayah + alamat ayah
            'nama_ayah', 'nik_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah', 'no_hp_ayah',
            'alamat_ayah', 'rt_rw_ayah', 'desa_kelurahan_ayah', 'kecamatan_ayah', 'kabupaten_kota_ayah', 'provinsi_ayah', 'kode_pos_ayah',
            // Ibu + alamat ibu
            'nama_ibu', 'nik_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu', 'no_hp_ibu',
            'alamat_ibu', 'rt_rw_ibu', 'desa_kelurahan_ibu', 'kecamatan_ibu', 'kabupaten_kota_ibu', 'provinsi_ibu', 'kode_pos_ibu',
            // Wali (akun) + alamat wali
            'nama_wali', 'hubungan_wali', 'pekerjaan_wali', 'penghasilan_wali', 'no_hp_wali', 'email_wali',
            'alamat_wali', 'rt_rw_wali', 'desa_kelurahan_wali', 'kecamatan_wali', 'kabupaten_kota_wali', 'provinsi_wali', 'kode_pos_wali',
            // Penanggung biaya
            'pembiaya',
            // Kesehatan
            'golongan_darah', 'tinggi_badan', 'berat_badan', 'riwayat_penyakit',
        ];

        $data = [];
        foreach ($shared as $col) {
            $data[$col] = $calon->{$col};
        }

        $data['tanggal_lahir'] = $calon->tanggal_lahir->format('Y-m-d');
        $data['jenis_kelamin'] = $this->mapJenisKelamin((string) $calon->jenis_kelamin);

        return $data;
    }

    private function mapJenisKelamin(string $jk): string
    {
        $v = strtolower(trim($jk));

        return in_array($v, ['p', 'perempuan', 'female', 'wanita'], true) ? 'P' : 'L';
    }
}
