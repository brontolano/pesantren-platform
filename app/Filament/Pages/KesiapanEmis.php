<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Santri\SantriResource;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Modules\Akademik\Filament\Resources\Lembaga\LembagaResource;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Models\Santri;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Modules\Payroll\Models\Employee;

/**
 * WP-12 + Refs #327 — Rekap "Kesiapan EMIS": kelengkapan data identitas yang
 * dibutuhkan pelaporan EMIS 4.0 PD-Pontren (NIK/NISN/KK/akta/TTL/alamat/
 * ortu/mukim/rombel santri, NIK/NUPTK/sertifikasi pegawai, legalitas lembaga)
 * + rincian per lembaga + persentase kesiapan keseluruhan. Hanya REKAP +
 * tautan ke daftar yang kurang — ekspor terformat sudah ada di halaman
 * Ekspor EMIS; API EMIS sengaja TIDAK dibangun (tidak ada API publik).
 */
class KesiapanEmis extends Page
{
    use ModuleGate;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Kesiapan EMIS';

    protected static ?string $title = 'Kesiapan Data EMIS';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'kesiapan-emis';

    protected string $view = 'filament.pages.kesiapan-emis';

    public static function canAccess(): bool
    {
        return static::moduleVisible('akademik') && (auth()->user()?->can('manage_santri') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && static::canAccess();
    }

    /**
     * Katalog syarat kelengkapan data santri untuk form EMIS 4.0 PD-Pontren.
     * Satu sumber dipakai rekap halaman ini, rincian per lembaga, DAN daftar
     * peringatan EmisExportService — agar kriteria tidak menyimpang.
     *
     * @return array<string, array{label: string, filter: string, terisi: callable(Santri): bool}>
     */
    public static function syaratSantri(): array
    {
        $isi = fn ($v): bool => $v !== null && trim((string) $v) !== '';

        return [
            'nik' => ['label' => 'Ber-NIK', 'filter' => 'tanpa_nik', 'terisi' => fn (Santri $s): bool => $isi($s->nik)],
            'nisn' => ['label' => 'Ber-NISN', 'filter' => 'tanpa_nisn', 'terisi' => fn (Santri $s): bool => $isi($s->nisn)],
            'no_kk' => ['label' => 'No. Kartu Keluarga', 'filter' => 'tanpa_kk', 'terisi' => fn (Santri $s): bool => $isi($s->no_kk)],
            'no_akta_lahir' => ['label' => 'No. Akta Kelahiran', 'filter' => 'tanpa_akta', 'terisi' => fn (Santri $s): bool => $isi($s->no_akta_lahir)],
            'ttl' => ['label' => 'Tempat & tanggal lahir', 'filter' => 'tanpa_ttl', 'terisi' => fn (Santri $s): bool => $isi($s->tempat_lahir) && $s->tanggal_lahir !== null],
            'alamat' => ['label' => 'Alamat', 'filter' => 'tanpa_alamat', 'terisi' => fn (Santri $s): bool => $isi($s->alamat)],
            'ortu' => ['label' => 'Nama ayah & ibu', 'filter' => 'tanpa_ortu', 'terisi' => fn (Santri $s): bool => $isi($s->nama_ayah) && $isi($s->nama_ibu)],
            'mukim' => ['label' => 'Status mukim terdata', 'filter' => 'mukim_belum_didata', 'terisi' => fn (Santri $s): bool => $s->is_mukim !== null],
            'rombel' => ['label' => 'Punya rombel/kelas', 'filter' => 'tanpa_rombel', 'terisi' => fn (Santri $s): bool => $s->kelas_id !== null],
        ];
    }

    /**
     * Angka rekap kesiapan EMIS — dihitung langsung dari data (bukan kolom
     * tersimpan) agar selalu segar. Dipanggil juga dari test.
     *
     * Kunci lama (total/ber_nik/ber_nisn/persen_nik/persen_nisn dst.) sengaja
     * DIPERTAHANKAN — dipakai test WP-12 & operator terbiasa; tambahan #327:
     * santri.fields (semua syarat), santri.lengkap, per_lembaga, keseluruhan.
     *
     * @return array<string, mixed>
     */
    public static function hitungStatistik(): array
    {
        $persen = fn (int $isi, int $total): float => $total > 0 ? round($isi / $total * 100, 1) : 0.0;
        $syarat = static::syaratSantri();

        /** @var Collection<int, Santri> $santriAktif */
        $santriAktif = Santri::query()->where('status', 'aktif')->with('kelas')->get();
        $totalSantri = $santriAktif->count();

        $fields = [];
        $selIsi = 0;
        foreach ($syarat as $key => $def) {
            $isi = $santriAktif->filter($def['terisi'])->count();
            $selIsi += $isi;
            $fields[$key] = [
                'label' => $def['label'],
                'filter' => $def['filter'],
                'isi' => $isi,
                'persen' => $persen($isi, $totalSantri),
            ];
        }

        $santriLengkap = $santriAktif->filter(function (Santri $s) use ($syarat): bool {
            foreach ($syarat as $def) {
                if (! $def['terisi']($s)) {
                    return false;
                }
            }

            return true;
        })->count();

        // ---- Rincian per lembaga (santri → kelas → lembaga) ----
        $namaLembaga = Lembaga::query()->pluck('nama', 'id');
        $perLembaga = $santriAktif
            ->groupBy(fn (Santri $s) => $s->kelas?->lembaga_id ?? ($s->kelas_id !== null ? 'default' : 'tanpa_rombel'))
            ->map(function ($grup, $kunci) use ($syarat, $namaLembaga, $persen) {
                $total = $grup->count();
                $lengkap = $grup->filter(function (Santri $s) use ($syarat): bool {
                    foreach ($syarat as $def) {
                        if (! $def['terisi']($s)) {
                            return false;
                        }
                    }

                    return true;
                })->count();

                return [
                    'nama' => match (true) {
                        $kunci === 'tanpa_rombel' => 'Belum punya rombel',
                        $kunci === 'default' => 'Lembaga default (kelas tanpa lembaga)',
                        default => $namaLembaga[$kunci] ?? 'Lembaga #'.$kunci,
                    },
                    'total' => $total,
                    'lengkap' => $lengkap,
                    'persen' => $persen($lengkap, $total),
                    'tanpa_nik' => $grup->reject($syarat['nik']['terisi'])->count(),
                    'tanpa_nisn' => $grup->reject($syarat['nisn']['terisi'])->count(),
                ];
            })
            ->sortBy('nama')
            ->values()
            ->all();

        // ---- Pegawai & lembaga (kriteria WP-12, tidak berubah) ----
        $pegawaiAktif = Employee::query()->where('aktif', true);
        $totalPegawai = (clone $pegawaiAktif)->count();
        $pegawaiNik = (clone $pegawaiAktif)->whereNotNull('nik')->where('nik', '!=', '')->count();
        $pegawaiNuptk = (clone $pegawaiAktif)->whereNotNull('nuptk')->where('nuptk', '!=', '')->count();
        $pegawaiSertifikasi = (clone $pegawaiAktif)->whereHas('sertifikasi')->count();

        $totalLembaga = Lembaga::query()->count();
        $lembagaTanpaLegalitas = Lembaga::query()->where(
            fn ($q) => $q
                ->whereNull('nomor_statistik')->orWhere('nomor_statistik', '')
                ->orWhereNull('izin_operasional')->orWhere('izin_operasional', ''),
        )->count();

        // ---- Kesiapan keseluruhan: sel terisi / sel wajib (santri semua
        // syarat + pegawai NIK & NUPTK + lembaga legalitas) ----
        $selTotal = $totalSantri * count($syarat) + $totalPegawai * 2 + $totalLembaga;
        $selTerisi = $selIsi + $pegawaiNik + $pegawaiNuptk + ($totalLembaga - $lembagaTanpaLegalitas);

        return [
            'santri' => [
                'total' => $totalSantri,
                'lengkap' => $santriLengkap,
                'persen_lengkap' => $persen($santriLengkap, $totalSantri),
                'fields' => $fields,
                // Kunci lama WP-12 (kompatibilitas test & kebiasaan operator).
                'ber_nik' => $fields['nik']['isi'],
                'ber_nisn' => $fields['nisn']['isi'],
                'persen_nik' => $fields['nik']['persen'],
                'persen_nisn' => $fields['nisn']['persen'],
            ],
            'per_lembaga' => $perLembaga,
            'pegawai' => [
                'total' => $totalPegawai,
                'ber_nik' => $pegawaiNik,
                'ber_nuptk' => $pegawaiNuptk,
                'ber_sertifikasi' => $pegawaiSertifikasi,
                'persen_nik' => $persen($pegawaiNik, $totalPegawai),
                'persen_nuptk' => $persen($pegawaiNuptk, $totalPegawai),
                'persen_sertifikasi' => $persen($pegawaiSertifikasi, $totalPegawai),
            ],
            'lembaga' => [
                'total' => $totalLembaga,
                'tanpa_legalitas' => $lembagaTanpaLegalitas,
            ],
            'keseluruhan' => [
                'sel_terisi' => $selTerisi,
                'sel_total' => $selTotal,
                'persen' => $persen($selTerisi, $selTotal),
            ],
        ];
    }

    /**
     * Data untuk view: statistik + URL daftar-yang-kurang (resource + filter).
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filterUrl = fn (string $resource, string $filter): string => $resource::getUrl('index', [
            'tableFilters' => [$filter => ['isActive' => true]],
        ]);

        $urlSantri = [];
        foreach (static::syaratSantri() as $key => $def) {
            $urlSantri[$key] = $filterUrl(SantriResource::class, $def['filter']);
        }

        return [
            'stat' => static::hitungStatistik(),
            // Link daftar pegawai hanya untuk yang boleh membuka EmployeeResource
            // (view_santri saja → link dead-end 403; sembunyikan).
            'bisaLihatPegawai' => EmployeeResource::canAccess(),
            'urlSantri' => $urlSantri,
            'url' => [
                'pegawai_tanpa_nik' => $filterUrl(EmployeeResource::class, 'tanpa_nik'),
                'pegawai_tanpa_nuptk' => $filterUrl(EmployeeResource::class, 'tanpa_nuptk'),
                'pegawai_tanpa_sertifikasi' => $filterUrl(EmployeeResource::class, 'tanpa_sertifikasi'),
                'lembaga_tanpa_legalitas' => $filterUrl(LembagaResource::class, 'tanpa_legalitas'),
            ],
        ];
    }
}
