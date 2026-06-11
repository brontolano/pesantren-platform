<?php

namespace App\Services\Mutu;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Akademik\Models\PresensiSantri;
use Modules\Akademik\Services\KalenderService;
use Modules\Kegiatan\Models\PresensiKegiatan;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\PresensiAsrama;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Mutu\Models\MutuLampu;
use Modules\Tahfidz\Models\HafalanTahfidz;

/**
 * 2a — Dasbor Mutu Santri (penjaminan mutu UU 18/2019).
 *
 * Agregator LINTAS MODUL: membaca tabel/servis existing (Nilai, Tahfidz,
 * CapaianKitab, Presensi ×3, Disiplin, UKS) TANPA tabel data baru dan TANPA
 * mengubah service sumber. Ditempatkan di App\Services\Mutu (bukan
 * Modules\Mutu\Services\MutuService yang sudah dipakai QMS dokumen SPMI) —
 * nama kelas tetap MutuService sesuai spek, namespace beda agar tidak tabrakan.
 *
 * Lampu kuning policy-first: ambang per tenant via Setting `mutu.ambang`
 * (JSON, parsing toleran, default dari riset).
 */
class MutuService
{
    public const SETTING_AMBANG = 'mutu.ambang';

    public const SETTING_NOTIF = 'mutu.notif';

    /** Default ambang lampu kuning (hasil riset roadmap §2a). */
    public const DEFAULT_AMBANG = [
        'hafalan_macet_pekan' => 3,  // tak ada setoran ≥3 pekan
        'alpa_per_bulan' => 3,       // alpa ≥3 pada bulan berjalan
        'poin_persen_sp' => 75,      // total poin ≥75% ambang SP pertama
        'nilai_turun' => 15,         // rata turun ≥15 dari periode sebelumnya
    ];

    /** Default saklar notifikasi lampu kuning. */
    public const DEFAULT_NOTIF = ['mustahiq' => true, 'wali' => false];

    /** Jumlah pekan tren hafalan pada profil. */
    public const PEKAN_TREN_HAFALAN = 8;

    public function __construct(
        private readonly KalenderService $kalender,
        private readonly DisiplinService $disiplin,
    ) {}

    // ------------------------------------------------------------------
    // Pengaturan (policy-first, parsing toleran)
    // ------------------------------------------------------------------

    /** @return array{hafalan_macet_pekan:int, alpa_per_bulan:int, poin_persen_sp:int, nilai_turun:int} */
    public function ambang(): array
    {
        $raw = Setting::getTenantValue(self::SETTING_AMBANG);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        $hasil = self::DEFAULT_AMBANG;
        if (is_array($raw)) {
            foreach (array_keys(self::DEFAULT_AMBANG) as $kunci) {
                $nilai = $raw[$kunci] ?? null;
                if (is_numeric($nilai) && (int) $nilai > 0) {
                    $hasil[$kunci] = (int) $nilai;
                }
            }
        }

        return $hasil;
    }

    /** @return array{mustahiq:bool, wali:bool} */
    public function notifSaklar(): array
    {
        $raw = Setting::getTenantValue(self::SETTING_NOTIF);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        $hasil = self::DEFAULT_NOTIF;
        if (is_array($raw)) {
            foreach (array_keys(self::DEFAULT_NOTIF) as $kunci) {
                if (array_key_exists($kunci, $raw)) {
                    $hasil[$kunci] = filter_var($raw[$kunci], FILTER_VALIDATE_BOOL);
                }
            }
        }

        return $hasil;
    }

    /**
     * Periode akademik acuan, di-scope LEMBAGA kelas santri (kontrak WP-01 —
     * tenant multi-lembaga punya ≥2 periode aktif; tanpa scoping, indikator
     * nilai-turun membandingkan periode lembaga lain → alarm palsu).
     */
    public function periodeAktif(?int $lembagaId = null): ?PeriodeAkademik
    {
        return PeriodeAkademik::query()
            ->where('is_active', true)
            ->where('lembaga_id', $lembagaId)
            ->orderByDesc('tanggal_mulai')
            ->first()
            ?? PeriodeAkademik::query()
                ->where('is_active', true)
                ->whereNull('lembaga_id')
                ->orderByDesc('tanggal_mulai')
                ->first()
            ?? PeriodeAkademik::query()->orderByDesc('tanggal_mulai')->first();
    }

    // ------------------------------------------------------------------
    // Profil mutu per santri
    // ------------------------------------------------------------------

    /**
     * Profil mutu lengkap satu santri pada satu periode. SEMUA agregat
     * difilter santri_id (BUKAN kelas_id) — santri pindah kelas di tengah
     * periode datanya tetap menyambung.
     *
     * Santri tanpa data → struktur lengkap berisi nol/null (bukan error).
     */
    public function profil(Santri $santri, ?PeriodeAkademik $periode = null): array
    {
        $periode ??= $this->periodeAktif($santri->kelas?->lembaga_id);
        [$dari, $sampai] = $this->rentangPeriode($periode);

        return [
            'periode' => $periode?->only(['id', 'nama', 'tahun_akademik', 'semester']),
            'nilai' => $this->nilaiRata($santri, $periode),
            'hafalan' => $this->trenHafalan($santri),
            'kitab' => $this->capaianKitabTerakhir($santri),
            'kehadiran' => $this->kehadiran($santri, $dari, $sampai),
            'disiplin' => $this->ringkasDisiplin($santri),
            'uks' => $this->ringkasUks($santri, $dari, $sampai),
        ];
    }

    /** @return array{0:Carbon,1:Carbon} Rentang tanggal periode (fallback: 6 bulan terakhir), di-clamp s.d. hari ini. */
    private function rentangPeriode(?PeriodeAkademik $periode): array
    {
        $dari = $periode?->tanggal_mulai ? Carbon::parse($periode->tanggal_mulai) : now()->subMonths(6)->startOfDay();
        $sampai = $periode?->tanggal_akhir ? Carbon::parse($periode->tanggal_akhir) : now();

        if ($sampai->gt(now())) {
            $sampai = now();
        }

        return [$dari->startOfDay(), $sampai->endOfDay()];
    }

    /** @return array{rata: ?float, jumlah_mapel: int} */
    private function nilaiRata(Santri $santri, ?PeriodeAkademik $periode): array
    {
        if (! $periode) {
            return ['rata' => null, 'jumlah_mapel' => 0];
        }

        $q = Nilai::query()
            ->where('santri_id', $santri->id)
            ->where('periode_akademik_id', $periode->id)
            ->whereNotNull('nilai_akhir');

        $jumlah = (clone $q)->count();

        return [
            'rata' => $jumlah > 0 ? round((float) $q->avg('nilai_akhir'), 2) : null,
            'jumlah_mapel' => $jumlah,
        ];
    }

    /**
     * Laju setoran hafalan per pekan, PEKAN_TREN_HAFALAN pekan terakhir
     * (pekan berjalan di indeks terakhir). Setoran = hafalan dengan
     * tanggal_selesai terisi (selesai disetor).
     *
     * @return array{per_pekan: array<int, array{mulai: string, jumlah: int}>, terakhir: ?string}
     */
    public function trenHafalan(Santri $santri): array
    {
        $awal = now()->startOfWeek()->subWeeks(self::PEKAN_TREN_HAFALAN - 1);

        $setoran = HafalanTahfidz::query()
            ->where('santri_id', $santri->id)
            ->whereNotNull('tanggal_selesai')
            ->whereDate('tanggal_selesai', '>=', $awal->toDateString())
            ->get(['tanggal_selesai'])
            ->groupBy(fn (HafalanTahfidz $h): string => Carbon::parse($h->tanggal_selesai)->startOfWeek()->toDateString());

        $perPekan = [];
        for ($i = 0; $i < self::PEKAN_TREN_HAFALAN; $i++) {
            $mulai = $awal->copy()->addWeeks($i)->toDateString();
            $perPekan[] = ['mulai' => $mulai, 'jumlah' => $setoran->get($mulai)?->count() ?? 0];
        }

        $terakhir = HafalanTahfidz::query()
            ->where('santri_id', $santri->id)
            ->whereNotNull('tanggal_selesai')
            ->max('tanggal_selesai');

        return ['per_pekan' => $perPekan, 'terakhir' => $terakhir ? Carbon::parse($terakhir)->toDateString() : null];
    }

    /** Capaian kitab TERAKHIR per kitab (sorogan; baris kelompok santri_id NULL tidak ikut). */
    private function capaianKitabTerakhir(Santri $santri): array
    {
        return CapaianKitab::query()
            ->where('santri_id', $santri->id)
            ->with('kajianKitab.kitab')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get()
            ->unique('kajian_kitab_id')
            ->map(fn (CapaianKitab $c): array => [
                'kitab' => $c->kajianKitab?->kitab?->nama ?? $c->kajianKitab?->nama ?? '-',
                'posisi' => $c->posisi,
                'status' => $c->status,
                'tanggal' => $c->tanggal?->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** % kehadiran per jenis: kelas (÷ hari efektif kalender), kegiatan, asrama per slot. */
    private function kehadiran(Santri $santri, Carbon $dari, Carbon $sampai): array
    {
        // Kelas: pembagi = hari efektif KalenderService (lembaga dari kelas santri).
        $hariEfektif = $this->kalender->hariEfektifDalamRentang($dari->copy(), $sampai->copy(), $santri->kelas?->lembaga);
        $hadirKelas = PresensiSantri::query()
            ->where('santri_id', $santri->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->whereIn('status', PresensiSantri::HADIR_EFEKTIF)
            ->count();
        $alpa = PresensiSantri::query()
            ->where('santri_id', $santri->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->where('status', 'alpa')
            ->count();

        // Kegiatan: hadir ÷ seluruh presensi kegiatan tercatat dalam rentang.
        $keg = PresensiKegiatan::query()
            ->where('presensi_kegiatan.santri_id', $santri->id)
            ->join('kegiatan', 'kegiatan.id', '=', 'presensi_kegiatan.kegiatan_id')
            ->whereBetween('kegiatan.mulai', [$dari, $sampai])
            ->selectRaw("count(*) as total, count(*) filter (where presensi_kegiatan.status = 'hadir') as hadir")
            ->first();

        // Asrama/sholat: per slot.
        $asrama = PresensiAsrama::query()
            ->where('santri_id', $santri->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw("slot, count(*) as total, count(*) filter (where status = 'hadir') as hadir")
            ->groupBy('slot')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->slot => [
                'hadir' => (int) $r->hadir,
                'total' => (int) $r->total,
                'persen' => $r->total > 0 ? round($r->hadir * 100 / $r->total, 1) : null,
            ]])
            ->all();

        return [
            'kelas' => [
                'hadir' => $hadirKelas,
                'hari_efektif' => $hariEfektif,
                'alpa' => $alpa,
                'persen' => $hariEfektif > 0 ? round(min(100, $hadirKelas * 100 / $hariEfektif), 1) : null,
            ],
            'kegiatan' => [
                'hadir' => (int) ($keg->hadir ?? 0),
                'total' => (int) ($keg->total ?? 0),
                'persen' => ($keg->total ?? 0) > 0 ? round($keg->hadir * 100 / $keg->total, 1) : null,
            ],
            'asrama' => $asrama,
        ];
    }

    /** Poin disiplin bersih (DisiplinService — sudah memperhitungkan poin negatif prestasi) + ambang eskalasi terdekat. */
    private function ringkasDisiplin(Santri $santri): array
    {
        $total = $this->disiplin->totalPoin($santri);
        $ambang = $this->disiplin->ambang();

        $terdekat = collect($ambang)->first(fn (array $a): bool => $a['poin'] > $total)
            ?? collect($ambang)->last();

        return [
            'total_poin' => $total,
            'ambang_terdekat' => $terdekat,
            'persen_ambang' => $terdekat && $terdekat['poin'] > 0
                ? round($total * 100 / $terdekat['poin'], 1)
                : null,
        ];
    }

    /** @return array{jumlah: int, terakhir: ?string} */
    private function ringkasUks(Santri $santri, Carbon $dari, Carbon $sampai): array
    {
        $q = KunjunganUks::query()
            ->where('santri_id', $santri->id)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);

        $terakhir = (clone $q)->max('tanggal');

        return [
            'jumlah' => $q->count(),
            'terakhir' => $terakhir ? Carbon::parse($terakhir)->toDateString() : null,
        ];
    }

    // ------------------------------------------------------------------
    // Lampu kuning
    // ------------------------------------------------------------------

    /**
     * Hitung lampu kuning satu santri (real-time, tidak menyentuh cache).
     *
     * @return array<int, array{jenis: string, label: string, detail: array}>
     */
    public function lampu(Santri $santri, ?CarbonInterface $acuan = null): array
    {
        $acuan = Carbon::parse($acuan ?? now());
        $ambang = $this->ambang();
        $hasil = [];

        // 1) Hafalan macet: punya riwayat setoran TAPI tak ada setoran ≥N pekan.
        $terakhir = HafalanTahfidz::query()
            ->where('santri_id', $santri->id)
            ->whereNotNull('tanggal_selesai')
            ->max('tanggal_selesai');
        if ($terakhir !== null) {
            $batas = $acuan->copy()->startOfWeek()->subWeeks($ambang['hafalan_macet_pekan'] - 1);
            if (Carbon::parse($terakhir)->lt($batas)) {
                $pekan = (int) Carbon::parse($terakhir)->startOfWeek()->diffInWeeks($acuan->copy()->startOfWeek());
                $hasil[] = [
                    'jenis' => 'hafalan_macet',
                    'label' => "Hafalan macet {$pekan} pekan",
                    'detail' => ['pekan_tanpa_setoran' => $pekan, 'setoran_terakhir' => Carbon::parse($terakhir)->toDateString(), 'ambang_pekan' => $ambang['hafalan_macet_pekan']],
                ];
            }
        }

        // 2) Alpa bulan berjalan ≥ ambang.
        $alpa = PresensiSantri::query()
            ->where('santri_id', $santri->id)
            ->where('status', 'alpa')
            ->whereBetween('tanggal', [$acuan->copy()->startOfMonth()->toDateString(), $acuan->toDateString()])
            ->count();
        if ($alpa >= $ambang['alpa_per_bulan']) {
            $hasil[] = [
                'jenis' => 'alpa',
                'label' => "Alpa {$alpa}× bulan ini",
                'detail' => ['alpa_bulan_ini' => $alpa, 'ambang' => $ambang['alpa_per_bulan']],
            ];
        }

        // 3) Poin disiplin ≥ X% ambang SP pertama (eskalasi DisiplinService).
        $total = $this->disiplin->totalPoin($santri, $acuan);
        $spPertama = $this->disiplin->ambang()[0] ?? null;
        if ($spPertama && $spPertama['poin'] > 0) {
            $persen = $total * 100 / $spPertama['poin'];
            if ($persen >= $ambang['poin_persen_sp']) {
                $hasil[] = [
                    'jenis' => 'poin_disiplin',
                    'label' => "Poin disiplin {$total} ({$spPertama['label']} = {$spPertama['poin']})",
                    'detail' => ['total_poin' => $total, 'ambang_sp' => $spPertama, 'persen' => round($persen, 1), 'ambang_persen' => $ambang['poin_persen_sp']],
                ];
            }
        }

        // 4) Nilai rata turun ≥ ambang dibanding periode sebelumnya.
        $turun = $this->nilaiTurun($santri, $ambang['nilai_turun']);
        if ($turun !== null) {
            $hasil[] = $turun;
        }

        return $hasil;
    }

    private function nilaiTurun(Santri $santri, int $ambangTurun): ?array
    {
        $lembagaId = $santri->kelas?->lembaga_id;
        $periode = $this->periodeAktif($lembagaId);
        if (! $periode) {
            return null;
        }

        // "Sebelumnya" dari lembaga yang SAMA dengan periode acuan — lintas
        // lembaga membandingkan apel dengan kurma (alarm palsu).
        $sebelum = PeriodeAkademik::query()
            ->where('tanggal_mulai', '<', $periode->tanggal_mulai)
            ->where(function ($q) use ($periode): void {
                $q->where('lembaga_id', $periode->lembaga_id);
                if ($periode->lembaga_id !== null) {
                    $q->orWhereNull('lembaga_id');
                }
            })
            ->orderByDesc('tanggal_mulai')
            ->first();
        if (! $sebelum) {
            return null;
        }

        $rataKini = Nilai::query()->where('santri_id', $santri->id)
            ->where('periode_akademik_id', $periode->id)->whereNotNull('nilai_akhir')->avg('nilai_akhir');
        $rataLalu = Nilai::query()->where('santri_id', $santri->id)
            ->where('periode_akademik_id', $sebelum->id)->whereNotNull('nilai_akhir')->avg('nilai_akhir');

        if ($rataKini === null || $rataLalu === null) {
            return null;
        }

        $selisih = round((float) $rataLalu - (float) $rataKini, 2);
        if ($selisih < $ambangTurun) {
            return null;
        }

        return [
            'jenis' => 'nilai_turun',
            'label' => "Nilai rata turun {$selisih} poin",
            'detail' => [
                'rata_sekarang' => round((float) $rataKini, 2),
                'rata_sebelumnya' => round((float) $rataLalu, 2),
                'selisih' => $selisih,
                'ambang' => $ambangTurun,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Agregat batched (anti N+1) untuk layar kelas & lembaga
    // ------------------------------------------------------------------

    /**
     * Heatmap per kelas: satu set query agregat GROUP BY santri_id
     * (bukan loop profil per santri). Lampu dibaca dari cache mutu_lampu.
     *
     * @return array<int, array> baris per santri
     */
    public function ringkasanKelas(int $kelasId, ?PeriodeAkademik $periode = null): array
    {
        $kelasLembagaId = Kelas::query()->whereKey($kelasId)->value('lembaga_id');
        $periode ??= $this->periodeAktif($kelasLembagaId);
        [$dari, $sampai] = $this->rentangPeriode($periode);

        $santris = Santri::query()
            ->where('kelas_id', $kelasId)
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nis', 'nama']);
        $ids = $santris->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $nilai = $periode
            ? Nilai::query()->whereIn('santri_id', $ids)
                ->where('periode_akademik_id', $periode->id)->whereNotNull('nilai_akhir')
                ->selectRaw('santri_id, avg(nilai_akhir) as rata')->groupBy('santri_id')->pluck('rata', 'santri_id')
            : collect();

        $presensi = PresensiSantri::query()->whereIn('santri_id', $ids)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw("santri_id, count(*) filter (where status = 'hadir') as hadir, count(*) filter (where status = 'alpa') as alpa, count(*) as total")
            ->groupBy('santri_id')->get()->keyBy('santri_id');

        $awalHafalan = now()->startOfWeek()->subWeeks(self::PEKAN_TREN_HAFALAN - 1)->toDateString();
        $hafalan = HafalanTahfidz::query()->whereIn('santri_id', $ids)
            ->whereNotNull('tanggal_selesai')->whereDate('tanggal_selesai', '>=', $awalHafalan)
            ->selectRaw('santri_id, count(*) as jumlah')->groupBy('santri_id')->pluck('jumlah', 'santri_id');

        $poin = $this->poinBatch($ids);

        $lampu = MutuLampu::query()->whereIn('santri_id', $ids)
            ->get(['santri_id', 'jenis', 'label'])
            ->groupBy('santri_id');

        $hariEfektif = $this->kalender->hariEfektifDalamRentang($dari->copy(), $sampai->copy());

        return $santris->map(function (Santri $s) use ($nilai, $presensi, $hafalan, $poin, $lampu, $hariEfektif): array {
            $p = $presensi->get($s->id);

            return [
                'santri_id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'nilai_rata' => $nilai->has($s->id) ? round((float) $nilai->get($s->id), 1) : null,
                'hadir_persen' => $hariEfektif > 0 && $p
                    ? round(min(100, (int) $p->hadir * 100 / $hariEfektif), 1) : null,
                'alpa' => (int) ($p->alpa ?? 0),
                'setoran_8pekan' => (int) ($hafalan->get($s->id) ?? 0),
                'poin_disiplin' => (int) ($poin->get($s->id) ?? 0),
                'lampu' => $lampu->get($s->id)?->map(fn ($l) => ['jenis' => $l->jenis, 'label' => $l->label])->values()->all() ?? [],
            ];
        })->all();
    }

    /**
     * Agregat ringkas per lembaga (join santri→kelas→lembaga, semua GROUP BY).
     *
     * @return array<int, array>
     */
    public function ringkasanLembaga(?PeriodeAkademik $periode = null): array
    {
        $periode ??= $this->periodeAktif();
        [$dari, $sampai] = $this->rentangPeriode($periode);

        $base = Santri::query()
            ->where('santri.status', 'aktif')
            ->join('kelas', 'kelas.id', '=', 'santri.kelas_id')
            ->join('lembaga', 'lembaga.id', '=', 'kelas.lembaga_id');

        $jumlah = (clone $base)
            ->selectRaw('lembaga.id, lembaga.nama, count(*) as jumlah_santri')
            ->groupBy('lembaga.id', 'lembaga.nama')
            ->get();

        $nilai = $periode
            ? (clone $base)->join('nilai', 'nilai.santri_id', '=', 'santri.id')
                ->where('nilai.periode_akademik_id', $periode->id)->whereNotNull('nilai.nilai_akhir')
                ->selectRaw('lembaga.id, avg(nilai.nilai_akhir) as rata')->groupBy('lembaga.id')->pluck('rata', 'id')
            : collect();

        $presensi = (clone $base)->join('presensi_santri', 'presensi_santri.santri_id', '=', 'santri.id')
            ->whereBetween('presensi_santri.tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw("lembaga.id, count(*) filter (where presensi_santri.status = 'hadir') as hadir, count(*) as total")
            ->groupBy('lembaga.id')->get()->keyBy('id');

        $lampu = (clone $base)->join('mutu_lampu', 'mutu_lampu.santri_id', '=', 'santri.id')
            ->selectRaw('lembaga.id, mutu_lampu.jenis, count(*) as jumlah')
            ->groupBy('lembaga.id', 'mutu_lampu.jenis')
            ->get()->groupBy('id');

        return $jumlah->map(function ($l) use ($nilai, $presensi, $lampu): array {
            $p = $presensi->get($l->id);

            return [
                'lembaga_id' => $l->id,
                'lembaga' => $l->nama,
                'jumlah_santri' => (int) $l->jumlah_santri,
                'nilai_rata' => $nilai->has($l->id) ? round((float) $nilai->get($l->id), 1) : null,
                'hadir_persen' => $p && $p->total > 0 ? round($p->hadir * 100 / $p->total, 1) : null,
                'lampu' => $lampu->get($l->id)?->mapWithKeys(fn ($r) => [$r->jenis => (int) $r->jumlah])->all() ?? [],
                'total_lampu' => (int) ($lampu->get($l->id)?->sum('jumlah') ?? 0),
            ];
        })->values()->all();
    }

    /** Total poin disiplin batched (replika query agregat DisiplinService::totalPoin, GROUP BY santri_id). */
    private function poinBatch(array $santriIds): Collection
    {
        [$dari, $sampai] = $this->disiplin->rentangPeriode();

        $q = PelanggaranSantri::query()
            ->whereIn('pelanggaran_santri.santri_id', $santriIds)
            ->whereIn('pelanggaran_santri.status', PelanggaranSantri::STATUS_POIN_BERLAKU)
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id');

        if ($dari !== null && $sampai !== null) {
            $q->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        return $q->selectRaw('pelanggaran_santri.santri_id, sum(jenis_pelanggaran.poin) as total')
            ->groupBy('pelanggaran_santri.santri_id')
            ->pluck('total', 'santri_id');
    }
}
