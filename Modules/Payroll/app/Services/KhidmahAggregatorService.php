<?php

namespace Modules\Payroll\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Akademik\Models\JurnalMengajar;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\Khidmah;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Models\PresensiStaf;
use Modules\Payroll\Models\SlipGaji;
use Modules\Payroll\Models\TarifKhidmah;
use RuntimeException;

/**
 * WP-13 — Loop Bisyaroh: agregasi poin khidmah per pegawai per periode dari
 * sumber operasional (JurnalMengajar F0 + PresensiStaf G2-11).
 *
 * Prinsip:
 *  - IDEMPOTEN per periode: hapus-tulis-ulang HANYA baris is_manual=false;
 *    baris manual bendahara TIDAK disentuh.
 *  - Tarif resolusi per jabatan penugasan AKTIF (guru rangkap 2 jabatan →
 *    tarif masing-masing per jenis sesi); fallback tarif default tenant
 *    (jabatan_id NULL). Tanpa tarif = tanpa poin (baris tidak ditulis).
 *  - Sesi 'diganti': poin ke pengganti_id, BUKAN guru asal — tidak dobel.
 *  - Periode TERKUNCI bila sudah ada SlipGaji status 'final' → agregasi
 *    ditolak (selisih dikoreksi di sumber SEBELUM slip difinalkan).
 *  - TIDAK menyentuh mesin slip/posting: integrasi slip lewat
 *    PayrollService::buatSlipUntukEmployee (poin = Σ khidmah periode).
 */
class KhidmahAggregatorService
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * Agregasi ulang khidmah satu periode (YYYY-MM).
     *
     * @return array{baris:int, total_poin:string, pegawai:int, dilewati_tanpa_employee:int}
     */
    public function agregasi(string $periode): array
    {
        $this->ensureTenantContext();
        $this->validasiPeriode($periode);

        $awal = CarbonImmutable::createFromFormat('Y-m', $periode)->startOfMonth();
        $akhir = $awal->endOfMonth();

        return DB::transaction(function () use ($periode, $awal, $akhir): array {
            // Cek kunci DI DALAM transaksi dengan lockForUpdate — menutup celah
            // TOCTOU (slip difinalkan di antara cek & tulis oleh proses lain).
            $this->tolakBilaTerkunci($periode, lock: true);

            // Idempoten: bersihkan HANYA baris hasil agregasi sebelumnya.
            Khidmah::query()->where('periode', $periode)->where('is_manual', false)->delete();

            $employees = Employee::query()->where('aktif', true)->get();
            $byUserId = $employees->whereNotNull('users_id')->keyBy('users_id');

            // Penugasan aktif (is_primary lebih dulu) → urutan resolusi tarif.
            $penugasan = PenugasanPegawai::query()->aktif()
                ->whereIn('employee_id', $employees->pluck('id'))
                ->orderByDesc('is_primary')->orderByDesc('tanggal_mulai')
                ->get()
                ->groupBy('employee_id');

            $tarif = TarifKhidmah::query()->aktif()->get();

            $baris = 0;
            $totalCents = 0;
            $dilewatiTanpaEmployee = 0;

            // (1) Jurnal mengajar: terlaksana → guru; diganti → pengganti; kosong → tidak ada poin.
            $jurnals = JurnalMengajar::query()
                ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
                ->whereIn('status', ['terlaksana', 'diganti'])
                ->with(['halaqah', 'jadwalPelajaran.kelas'])
                ->get();

            foreach ($jurnals as $jurnal) {
                $userId = $jurnal->status === 'diganti' ? $jurnal->pengganti_id : $jurnal->guru_id;
                if ($userId === null) {
                    continue; // diganti tanpa pengganti tercatat — tidak ada poin.
                }

                $employee = $byUserId->get($userId);
                if ($employee === null) {
                    $dilewatiTanpaEmployee++; // user (guru/pengganti) belum tertaut Employee aktif — laporkan, jangan diam.

                    continue;
                }

                $jenis = $this->jenisSesiJurnal($jurnal);
                $t = $this->resolveTarif($tarif, $penugasan->get($employee->id, collect()), $jenis, $this->lembagaSumber($jurnal));
                if ($t === null) {
                    continue; // tanpa tarif = tanpa poin (kebijakan tenant).
                }

                Khidmah::create([
                    'employee_id' => $employee->id,
                    'periode' => $periode,
                    'jenis_sesi' => $jenis,
                    'poin' => $t->poin,
                    'sumber_type' => JurnalMengajar::class,
                    'sumber_id' => $jurnal->id,
                    'is_manual' => false,
                    'keterangan' => sprintf('Jurnal mengajar %s (%s)', $jurnal->tanggal->format('Y-m-d'), $jurnal->status),
                ]);
                $baris++;
                $totalCents += (int) round(((float) $t->poin) * 100);
            }

            // (2) Presensi staf 'hadir' → hadir_kantor (per hari).
            $presensis = PresensiStaf::query()
                ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
                ->where('status', 'hadir')
                ->get();

            foreach ($presensis as $presensi) {
                $employee = $employees->firstWhere('id', $presensi->employee_id);
                if ($employee === null) {
                    continue;
                }

                $t = $this->resolveTarif($tarif, $penugasan->get($employee->id, collect()), 'hadir_kantor');
                if ($t === null) {
                    continue;
                }

                Khidmah::create([
                    'employee_id' => $employee->id,
                    'periode' => $periode,
                    'jenis_sesi' => 'hadir_kantor',
                    'poin' => $t->poin,
                    'sumber_type' => PresensiStaf::class,
                    'sumber_id' => $presensi->id,
                    'is_manual' => false,
                    'keterangan' => 'Presensi hadir '.$presensi->tanggal->format('Y-m-d'),
                ]);
                $baris++;
                $totalCents += (int) round(((float) $t->poin) * 100);
            }

            $pegawai = Khidmah::query()->where('periode', $periode)->distinct('employee_id')->count('employee_id');

            return [
                'baris' => $baris,
                'total_poin' => number_format($totalCents / 100, 2, '.', ''),
                'pegawai' => $pegawai,
                'dilewati_tanpa_employee' => $dilewatiTanpaEmployee,
            ];
        });
    }

    /**
     * Generate slip draft per pegawai dari Σ khidmah periode. Poin slip =
     * Σ poin khidmah (manual + agregasi). Mesin slip TIDAK diubah — delegasi
     * penuh ke PayrollService::buatSlipUntukEmployee (idempoten per pegawai
     * per periode; slip yang sudah ada dikembalikan apa adanya).
     *
     * @return array{dibuat:int, dilewati:int}
     */
    public function generateSlip(string $periode, ?int $createdBy = null): array
    {
        $this->ensureTenantContext();
        $this->validasiPeriode($periode);

        return DB::transaction(function () use ($periode, $createdBy): array {
            // Cek kunci DI DALAM transaksi + lockForUpdate (anti-TOCTOU,
            // selaras agregasi()).
            $this->tolakBilaTerkunci($periode, lock: true);

            $totals = Khidmah::query()->where('periode', $periode)
                ->selectRaw('employee_id, SUM(poin) AS total_poin')
                ->groupBy('employee_id')
                ->pluck('total_poin', 'employee_id');

            $dibuat = 0;
            $dilewati = 0;

            foreach ($totals as $employeeId => $totalPoin) {
                $employee = Employee::query()->find($employeeId);
                if ($employee === null || ! $employee->users_id) {
                    $dilewati++; // pegawai tanpa akun login — slip butuh users_id.

                    continue;
                }

                // PayrollService::buatSlip men-cast poin ke (int) — truncate
                // diam-diam (10.75 → 10 = kurang bayar). Mesin slip TIDAK
                // diubah; pembulatan dilakukan DI SINI: round half-up agar
                // 10.75 → 11, bukan 10. Σ pecahan tarif tetap akurat di
                // tabel khidmah (decimal 12,2) untuk audit.
                $poinSlip = (int) round((float) $totalPoin);

                // Anti-slip-basi: bila draft sudah ada dengan poin LAMA yang
                // berbeda dari Σ khidmah terkini, buatSlip akan mengembalikan
                // draft lama apa adanya — bendahara bisa memfinalkan angka
                // basi tanpa sadar. Tolak keras dengan pesan jelas.
                $draftLama = SlipGaji::query()
                    ->where('users_id', $employee->users_id)
                    ->where('periode', $periode)
                    ->lockForUpdate()
                    ->first();
                if ($draftLama !== null && $draftLama->status === 'draft' && (int) $draftLama->poin !== $poinSlip) {
                    throw new RuntimeException(sprintf(
                        'Slip draft %s periode %s sudah ada dengan poin lama %d ≠ poin terkini %d. Hapus draft tersebut dulu, atau koreksi sumber lalu agregasi ulang sebelum generate slip.',
                        $employee->nama, $periode, (int) $draftLama->poin, $poinSlip
                    ));
                }

                $this->payrollService->buatSlipUntukEmployee($employee, $periode, [
                    'poin' => $poinSlip,
                    'created_by' => $createdBy,
                ]);
                $dibuat++;
            }

            return ['dibuat' => $dibuat, 'dilewati' => $dilewati];
        });
    }

    /**
     * Rekap pratinjau per pegawai (untuk layar bendahara): total poin +
     * rincian per jenis sesi + jumlah baris manual.
     *
     * @return Collection<int, array{employee:Employee, total:string, rincian:array<string, array{jumlah:int, poin:string}>, manual:int}>
     */
    public function rekap(string $periode): Collection
    {
        $this->ensureTenantContext();

        return Khidmah::query()->where('periode', $periode)
            ->with('employee')
            ->get()
            ->groupBy('employee_id')
            ->map(function (Collection $rows): array {
                $rincian = [];
                foreach ($rows->groupBy(fn (Khidmah $k) => $k->jenis_sesi ?? 'manual') as $jenis => $grup) {
                    $rincian[$jenis] = [
                        'jumlah' => $grup->count(),
                        'poin' => self::jumlahPoin($grup),
                    ];
                }

                return [
                    'employee' => $rows->first()->employee,
                    'total' => self::jumlahPoin($rows),
                    'rincian' => $rincian,
                    'manual' => $rows->where('is_manual', true)->count(),
                ];
            })
            ->values();
    }

    /** Sesi formal (jadwal) = jam_formal; halaqah tahfidz = sesi_tahfidz; lainnya (kitab/diniyah) = sesi_diniyah. */
    private function jenisSesiJurnal(JurnalMengajar $jurnal): string
    {
        if ($jurnal->jadwal_pelajaran_id !== null) {
            return 'jam_formal';
        }

        return $jurnal->halaqah?->jenis === 'tahfidz' ? 'sesi_tahfidz' : 'sesi_diniyah';
    }

    /**
     * Lembaga asal sesi: jurnal berbasis jadwal → kelas.lembaga_id; jurnal
     * halaqah → NULL (tabel halaqah tidak punya kolom lembaga — fallback ke
     * urutan primary di resolveTarif).
     */
    private function lembagaSumber(JurnalMengajar $jurnal): ?int
    {
        if ($jurnal->jadwal_pelajaran_id === null) {
            return null;
        }

        $lembagaId = $jurnal->jadwalPelajaran?->kelas?->lembaga_id;

        return $lembagaId !== null ? (int) $lembagaId : null;
    }

    /**
     * Resolusi tarif "guru 2 lembaga 2 tarif": penugasan aktif yang lembaganya
     * COCOK dengan lembaga sumber sesi dicoba lebih dulu, lalu sisanya
     * (primary lebih dulu — urutan query agregasi), terakhir fallback tarif
     * default tenant (jabatan_id NULL). Penugasan lembaga NULL = lintas
     * lembaga, ikut urutan primary biasa.
     *
     * @param  Collection<int, TarifKhidmah>  $tarif
     * @param  Collection<int, PenugasanPegawai>  $penugasanPegawai
     */
    private function resolveTarif(Collection $tarif, Collection $penugasanPegawai, string $jenis, ?int $lembagaId = null): ?TarifKhidmah
    {
        $kandidat = $lembagaId === null
            ? $penugasanPegawai
            : $penugasanPegawai->sortByDesc(fn (PenugasanPegawai $p) => (int) $p->lembaga_id === $lembagaId)->values();

        foreach ($kandidat as $p) {
            $match = $tarif->first(fn (TarifKhidmah $t) => $t->jabatan_id === $p->jabatan_id && $t->jenis_sesi === $jenis);
            if ($match !== null) {
                return $match;
            }
        }

        return $tarif->first(fn (TarifKhidmah $t) => $t->jabatan_id === null && $t->jenis_sesi === $jenis);
    }

    private function validasiPeriode(string $periode): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            throw new InvalidArgumentException("Periode '{$periode}' tidak valid — format YYYY-MM.");
        }
    }

    /**
     * Periode terkunci bila sudah ada slip FINAL — koreksi di sumber tidak
     * boleh mengubah dasar slip yang sudah diposting. Dengan $lock=true,
     * baris slip periode dikunci (SELECT … FOR UPDATE) — WAJIB dipanggil di
     * dalam DB::transaction agar cek-lalu-tulis bebas TOCTOU.
     */
    private function tolakBilaTerkunci(string $periode, bool $lock = false): void
    {
        $query = SlipGaji::query()->where('periode', $periode);
        if ($lock) {
            $query->lockForUpdate();
        }

        $adaFinal = $query->get()->contains(fn (SlipGaji $s) => $s->status === 'final');
        if ($adaFinal) {
            throw new RuntimeException("Periode {$periode} terkunci — sudah ada slip gaji final. Agregasi ulang ditolak.");
        }
    }

    /**
     * Σ poin koleksi khidmah dalam sen (integer) → string 2 desimal.
     * Hindari akumulasi float murni & dependensi ext-bcmath.
     *
     * @param  Collection<int, Khidmah>  $rows
     */
    private static function jumlahPoin(Collection $rows): string
    {
        $cents = $rows->reduce(fn (int $c, Khidmah $k) => $c + (int) round(((float) $k->poin) * 100), 0);

        return number_format($cents / 100, 2, '.', '');
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
