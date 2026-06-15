<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Akademik\Models\Nilai;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesehatan\Models\Obat;
use Modules\Payroll\Models\Employee;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Models\Halaqah;

/**
 * Cetak (print-friendly HTML) untuk dokumen operasional unit — mengikuti pola
 * yang sudah ada (response()->view ke blade 'exports.*-print', dicetak dari
 * browser, tanpa dependensi PDF). Empat cetakan: jadwal per kelas, daftar nilai
 * (legger), penempatan asrama per blok, stok obat.
 */
class CetakController extends Controller
{
    private function izinkan(array $abilities): void
    {
        abort_unless(auth()->user()?->canAny($abilities) ?? false, 403);
    }

    private function namaTenant(): string
    {
        return tenant('name') ?? (string) config('app.name');
    }

    public function jadwalKelas(Request $request)
    {
        $this->izinkan(['view_santri', 'manage_akademik']);

        $kelas = Kelas::query()->find((int) $request->integer('kelas_id'));
        abort_unless($kelas !== null, redirect('/admin/akademik/jadwal-pelajaran/jadwal-pelajarans')
            ->with('error', 'Pilih kelas terlebih dahulu untuk mencetak jadwal.'));

        $jadwal = JadwalPelajaran::query()
            ->where('kelas_id', $kelas->id)
            ->with(['pelajaran', 'periodeAkademik'])
            ->orderByRaw("array_position(ARRAY['senin','selasa','rabu','kamis','jumat','sabtu','ahad','minggu']::text[], hari)")
            ->orderBy('jam_mulai')
            ->get();

        return response()->view('exports.jadwal-per-kelas-print', [
            'tenantName' => $this->namaTenant(),
            'kelas' => $kelas,
            'jadwal' => $jadwal,
            'printedAt' => now(),
        ]);
    }

    public function nilaiLegger(Request $request)
    {
        $this->izinkan(['view_santri', 'manage_akademik']);

        $kelas = Kelas::query()->find((int) $request->integer('kelas_id'));
        abort_unless($kelas !== null, redirect('/admin/akademik/nilai/nilais')
            ->with('error', 'Pilih kelas terlebih dahulu untuk mencetak daftar nilai.'));

        $periodeId = (int) $request->integer('periode_id');
        $periode = $periodeId > 0 ? PeriodeAkademik::query()->find($periodeId) : null;

        $nilai = Nilai::query()
            ->where('kelas_id', $kelas->id)
            ->when($periode !== null, fn ($q) => $q->where('periode_akademik_id', $periode->id))
            ->with(['santri', 'pelajaran'])
            ->get()
            ->sortBy(fn (Nilai $n) => ($n->santri?->nama ?? '').'|'.($n->pelajaran?->nama ?? ''))
            ->values();

        return response()->view('exports.nilai-legger-print', [
            'tenantName' => $this->namaTenant(),
            'kelas' => $kelas,
            'periode' => $periode,
            'nilai' => $nilai,
            'printedAt' => now(),
        ]);
    }

    public function penempatanBlok(Request $request)
    {
        $this->izinkan(['view_santri', 'manage_santri']);

        $penempatan = PenempatanAsrama::query()
            ->where('is_current', true)
            ->with(['santri', 'kamar.blok'])
            ->get()
            ->sortBy(fn (PenempatanAsrama $p) => ($p->kamar?->blok?->nama_blok ?? 'zzz').'|'.($p->kamar?->nomor_kamar ?? '').'|'.($p->santri?->nama ?? ''))
            ->groupBy(fn (PenempatanAsrama $p) => $p->kamar?->blok?->nama_blok ?? '(Tanpa blok)');

        return response()->view('exports.penempatan-per-blok-print', [
            'tenantName' => $this->namaTenant(),
            'perBlok' => $penempatan,
            'printedAt' => now(),
        ]);
    }

    public function stokObat()
    {
        $this->izinkan(['view_santri', 'manage_santri']);

        $obat = Obat::query()->orderBy('nama')->get();

        return response()->view('exports.stok-obat-print', [
            'tenantName' => $this->namaTenant(),
            'obat' => $obat,
            'printedAt' => now(),
        ]);
    }

    public function halaqah()
    {
        $this->izinkan(['view_santri', 'manage_santri']);

        $halaqah = Halaqah::query()->with('musyrif')->orderBy('nama')->get();

        return response()->view('exports.halaqah-print', [
            'tenantName' => $this->namaTenant(),
            'halaqah' => $halaqah,
            'printedAt' => now(),
        ]);
    }

    public function hafalan()
    {
        $this->izinkan(['view_santri', 'manage_santri']);

        $hafalan = HafalanTahfidz::query()
            ->with(['santri', 'halaqah'])
            ->get()
            ->sortBy(fn (HafalanTahfidz $h) => ($h->halaqah?->nama ?? 'zzz').'|'.($h->santri?->nama ?? ''))
            ->groupBy(fn (HafalanTahfidz $h) => $h->halaqah?->nama ?? '(Tanpa halaqah)');

        return response()->view('exports.hafalan-print', [
            'tenantName' => $this->namaTenant(),
            'perHalaqah' => $hafalan,
            'printedAt' => now(),
        ]);
    }

    public function pegawai()
    {
        $this->izinkan(['manage_payroll']);

        $pegawai = Employee::query()->orderBy('nama')->get();

        return response()->view('exports.pegawai-print', [
            'tenantName' => $this->namaTenant(),
            'pegawai' => $pegawai,
            'printedAt' => now(),
        ]);
    }
}
