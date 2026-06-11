<?php

namespace Modules\Keuangan\Services;

use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\Payroll\Models\Employee;

/**
 * Generator EKSPOR TERFORMAT EMIS (BUKAN API real-time — tidak ada API publik EMIS).
 *
 * WP-22: tiga kategori (santri/pegawai/lembaga) mengikuti struktur umum
 * EMIS 4.0 — header dibuat JELAS + mudah diubah, TIDAK dikunci ke format
 * kaku. Data kurang → kolom KOSONG + masuk daftar peringatan (export tetap
 * jalan, reuse semangat query Kesiapan EMIS WP-12).
 */
class EmisExportService
{
    public const KATEGORI = ['santri', 'pegawai', 'lembaga'];

    /**
     * Header santri. 8 kolom pertama = skema lama (JANGAN ubah urutan —
     * dipakai operator yang sudah terbiasa); kolom EMIS 4.0 tambahan
     * di-APPEND di belakang.
     */
    public const HEADER = [
        'NISN', 'NIS', 'NAMA', 'JENIS_KELAMIN', 'TAHUN_ANGKATAN', 'STATUS_SANTRI', 'STATUS_SPP', 'TUNGGAKAN',
        'NIK', 'TEMPAT_LAHIR', 'TANGGAL_LAHIR', 'ALAMAT', 'MUKIM',
    ];

    public const HEADER_PEGAWAI = [
        'NIP', 'NAMA', 'NIK', 'NUPTK', 'NRG', 'JABATAN', 'STATUS_KEPEGAWAIAN',
        'PENDIDIKAN_TERAKHIR', 'TANGGAL_MASUK', 'MUKIM',
    ];

    public const HEADER_LEMBAGA = [
        'NAMA_LEMBAGA', 'JENIS', 'JENJANG', 'NOMOR_STATISTIK_NSM', 'IZIN_OPERASIONAL', 'TANGGAL_IZIN', 'AKTIF',
    ];

    /** @return array<int, string> */
    public static function headerFor(string $kategori): array
    {
        return match ($kategori) {
            'pegawai' => self::HEADER_PEGAWAI,
            'lembaga' => self::HEADER_LEMBAGA,
            default => self::HEADER,
        };
    }

    /** @return array<int, array<int, string|float>> */
    public function barisFor(string $kategori): array
    {
        return match ($kategori) {
            'pegawai' => $this->barisPegawai(),
            'lembaga' => $this->barisLembaga(),
            default => $this->barisSantri(),
        };
    }

    /**
     * @return array<int, array<int, string|float>>
     */
    public function barisSantri(): array
    {
        return Santri::query()->orderBy('nama')->get()->map(function (Santri $s) {
            $tunggakan = Tagihan::where('santri_id', $s->id)->belumLunas()->get()
                ->sum(fn (Tagihan $t) => $t->sisa_tagihan);

            return [
                $s->nisn ?? '',
                $s->nis ?? '',
                $s->nama,
                $s->jenis_kelamin ?? '',
                $s->tahun_angkatan ?? '',
                $s->status ?? '',
                $tunggakan > 0 ? 'MENUNGGAK' : 'LUNAS',
                round((float) $tunggakan, 2),
                $s->nik ?? '',
                $s->tempat_lahir ?? '',
                $s->tanggal_lahir?->format('Y-m-d') ?? '',
                $s->alamat ?? '',
                $s->is_mukim === null ? '' : ($s->is_mukim ? 'MUKIM' : 'NON-MUKIM'),
            ];
        })->all();
    }

    /** @return array<int, array<int, string>> */
    public function barisPegawai(): array
    {
        return Employee::query()->where('aktif', true)->orderBy('nama')->get()
            ->map(fn (Employee $p) => [
                $p->nip ?? '',
                $p->nama,
                $p->nik ?? '',
                $p->nuptk ?? '',
                $p->nrg ?? '',
                $p->jabatan ?? '',
                $p->status_kepegawaian ?? '',
                $p->pendidikan_terakhir ?? '',
                $p->tanggal_masuk?->format('Y-m-d') ?? '',
                $p->is_mukim === null ? '' : ($p->is_mukim ? 'MUKIM' : 'NON-MUKIM'),
            ])->all();
    }

    /** @return array<int, array<int, string>> */
    public function barisLembaga(): array
    {
        return Lembaga::query()->orderBy('nama')->get()
            ->map(fn (Lembaga $l) => [
                $l->nama,
                $l->jenis ?? '',
                $l->jenjang ?? '',
                $l->nomor_statistik ?? '',
                $l->izin_operasional ?? '',
                $l->tanggal_izin?->format('Y-m-d') ?? '',
                ($l->aktif ?? true) ? 'AKTIF' : 'NONAKTIF',
            ])->all();
    }

    /**
     * Daftar peringatan kelengkapan data (reuse kriteria Kesiapan EMIS WP-12).
     * Data kurang TIDAK menggagalkan ekspor — hanya dilampirkan agar operator
     * tahu apa yang harus dilengkapi sebelum unggah ke portal EMIS.
     *
     * @return array<int, string>
     */
    public function peringatan(string $kategori): array
    {
        $kosong = fn ($v): bool => $v === null || trim((string) $v) === '';
        $hasil = [];

        if ($kategori === 'santri') {
            foreach (Santri::query()->where('status', 'aktif')->orderBy('nama')->get() as $s) {
                $kurang = [];
                if ($kosong($s->nik)) {
                    $kurang[] = 'NIK kosong';
                }
                if ($kosong($s->nisn)) {
                    $kurang[] = 'NISN kosong';
                }
                if ($kurang !== []) {
                    $hasil[] = sprintf('Santri %s (NIS %s): %s', $s->nama, $s->nis ?? '-', implode(', ', $kurang));
                }
            }
        } elseif ($kategori === 'pegawai') {
            foreach (Employee::query()->where('aktif', true)->orderBy('nama')->get() as $p) {
                $kurang = [];
                if ($kosong($p->nik)) {
                    $kurang[] = 'NIK kosong';
                }
                if ($kosong($p->nuptk)) {
                    $kurang[] = 'NUPTK kosong';
                }
                if ($kurang !== []) {
                    $hasil[] = sprintf('Pegawai %s (NIP %s): %s', $p->nama, $p->nip ?? '-', implode(', ', $kurang));
                }
            }
        } elseif ($kategori === 'lembaga') {
            foreach (Lembaga::query()->orderBy('nama')->get() as $l) {
                $kurang = [];
                if ($kosong($l->nomor_statistik)) {
                    $kurang[] = 'Nomor statistik (NSM) kosong';
                }
                if ($kosong($l->izin_operasional)) {
                    $kurang[] = 'Izin operasional kosong';
                }
                if ($kurang !== []) {
                    $hasil[] = sprintf('Lembaga %s: %s', $l->nama, implode(', ', $kurang));
                }
            }
        }

        return $hasil;
    }
}
