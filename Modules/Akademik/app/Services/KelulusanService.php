<?php

namespace Modules\Akademik\Services;

use App\Models\Setting;
use App\Models\User;
use App\Services\StorageQuotaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Akademik\Models\KeputusanKenaikan;
use Modules\Akademik\Models\PeriodeAkademik;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\PenempatanKelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Models\StatusSantri;
use RuntimeException;

/**
 * WP-08 — Kelulusan & Mutasi.
 *
 * prosesLulus: eksekusi keputusan 'lulus' yang SUDAH FINAL di sidang WP-07 →
 * baris alumni + santri.status='lulus' + jejak status_santri + penempatan
 * aktif DITUTUP langsung (query builder — TANPA penempatan baru, tidak lewat
 * observer create). Riwayat nilai & penempatan TIDAK disentuh. IDEMPOTEN.
 *
 * Catatan status: enum santri.status = aktif|cuti|lulus|keluar|dikeluarkan —
 * 'alumni' diwakili 'lulus' (+ baris tabel alumni), mutasi keluar diwakili
 * 'keluar' (+ jejak status_santri beralasan). Tidak ada ALTER enum.
 *
 * Syahadah: template per lembaga via Setting `akademik.template_syahadah.{lembaga_id}`
 * (fallback `.default` → bawaan) — pola SkPenugasanService (repo belum punya
 * model SuratTemplate). Nomor seri: counter race-safe per (tenant, tahun_lulus)
 * pola SuratService; format via Setting `akademik.format_nomor_syahadah`.
 */
class KelulusanService
{
    public const SETTING_FORMAT_NOMOR = 'akademik.format_nomor_syahadah';

    public const SETTING_TEMPLATE_PREFIX = 'akademik.template_syahadah.';

    public const DEFAULT_FORMAT_NOMOR = '{counter}/SYH/{lembaga}/{tahun}';

    public const DEFAULT_TEMPLATE = <<<'TXT'
SYAHADAH
Nomor: {{nomor}}

Bismillahirrahmanirrahim.

Pimpinan {{pesantren}} menyatakan bahwa:

Nama          : {{nama}}
NIS           : {{nis}}
NISN          : {{nisn}}
Tempat/Tgl Lahir : {{ttl}}

telah menyelesaikan pendidikan pada {{lembaga}} dan dinyatakan LULUS pada tahun {{tahun_lulus}}.

Semoga ilmunya bermanfaat dan menjadi bekal khidmah bagi umat.

Diterbitkan pada tanggal {{tanggal_cetak}}.

Pimpinan {{pesantren}}
TXT;

    // ------------------------------------------------------------------
    // 1) Proses kelulusan
    // ------------------------------------------------------------------

    /**
     * Eksekusi semua keputusan 'lulus' FINAL pada satu periode (opsional satu
     * kelas asal). Idempoten: santri yang sudah berstatus lulus + sudah punya
     * baris alumni dilewati tanpa duplikasi.
     *
     * @return int jumlah santri yang baru diproses lulus
     */
    public function prosesLulus(PeriodeAkademik $periode, ?Kelas $kelasAsal = null, ?User $actor = null): int
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($periode, $kelasAsal, $actor): int {
            $keputusanLulus = KeputusanKenaikan::query()
                ->where('periode_akademik_id', $periode->id)
                ->when($kelasAsal, fn ($q) => $q->where('kelas_asal_id', $kelasAsal->id))
                ->where('keputusan', 'lulus')
                ->whereNotNull('finalized_at')
                ->lockForUpdate()
                ->get();

            $tahunLulus = (string) $periode->tanggal_akhir->year;
            $jumlah = 0;

            foreach ($keputusanLulus as $keputusan) {
                $santri = Santri::query()->lockForUpdate()->find($keputusan->santri_id);

                if (! $santri) {
                    continue;
                }

                $sudahAlumni = Alumni::query()->where('santri_id', $santri->id)->exists();

                if ($santri->status === 'lulus' && $sudahAlumni) {
                    continue; // idempoten — sudah diproses sebelumnya
                }

                // Status terminal lain (keluar/dikeluarkan setelah sidang
                // final) JANGAN ditimpa jadi lulus — butuh koreksi manual TU.
                if (in_array($santri->status, ['keluar', 'dikeluarkan'], true)) {
                    continue;
                }

                if (! $sudahAlumni) {
                    Alumni::create([
                        'santri_id' => $santri->id,
                        'tahun_lulus' => $tahunLulus,
                        'tanggal_lulus' => now()->toDateString(),
                        'lembaga_id' => Kelas::query()->find($keputusan->kelas_asal_id)?->lembaga_id,
                    ]);
                }

                if ($santri->status !== 'lulus') {
                    StatusSantri::create([
                        'santri_id' => $santri->id,
                        'status' => 'lulus',
                        'tanggal_status' => now()->toDateString(),
                        'alasan' => 'Lulus — '.$periode->nama.' ('.$periode->tahun_akademik.')',
                        'created_by' => $actor?->getKey(),
                    ]);
                }

                $this->tutupPenempatanAktif($santri, 'lulus');

                $jumlah++;
            }

            return $jumlah;
        });
    }

    // ------------------------------------------------------------------
    // 2) Syahadah — nomor seri race-safe + render template per lembaga
    // ------------------------------------------------------------------

    /**
     * Terbitkan nomor syahadah utk satu alumni (idempoten: sudah bernomor →
     * kembalikan apa adanya). Counter per (tenant, tahun_lulus) di dalam
     * transaksi + lock; partial unique index jadi backstop balapan → retry sekali.
     */
    public function generateNomorSyahadah(Alumni $alumni): Alumni
    {
        $this->ensureTenantContext();

        if ($alumni->nomor_syahadah !== null) {
            return $alumni;
        }

        $simpan = function () use ($alumni): Alumni {
            return DB::transaction(function () use ($alumni): Alumni {
                $terkunci = Alumni::query()->lockForUpdate()->findOrFail($alumni->getKey());

                if ($terkunci->nomor_syahadah !== null) {
                    return $terkunci;
                }

                // PG tak mengizinkan FOR UPDATE + agregat → lock baris urut tertinggi.
                $urut = 1 + (int) Alumni::query()
                    ->where('tahun_lulus', $terkunci->tahun_lulus)
                    ->whereNotNull('nomor_syahadah_urut')
                    ->orderByDesc('nomor_syahadah_urut')
                    ->lockForUpdate()
                    ->value('nomor_syahadah_urut');

                $terkunci->forceFill([
                    'nomor_syahadah_urut' => $urut,
                    'nomor_syahadah' => $this->formatNomorSyahadah($terkunci, $urut),
                ])->save();

                return $terkunci;
            });
        };

        try {
            return $simpan();
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23505', '23000'], true)) {
                return $simpan(); // kalah balapan urut — retry sekali
            }
            throw $e;
        }
    }

    public function formatNomorSyahadah(Alumni $alumni, int $urut): string
    {
        $format = Setting::getTenantStringValue(self::SETTING_FORMAT_NOMOR) ?: self::DEFAULT_FORMAT_NOMOR;

        $lembaga = $alumni->lembaga?->nama ?? 'PESANTREN';
        $kodeLembaga = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', collect(explode(' ', $lembaga))
            ->map(fn (string $kata) => mb_substr($kata, 0, 1))
            ->implode('')) ?: 'PES');

        return strtr($format, [
            '{counter}' => sprintf('%03d', $urut),
            '{lembaga}' => $kodeLembaga,
            '{tahun}' => (string) $alumni->tahun_lulus,
        ]);
    }

    /** Render isi syahadah dari template per lembaga (Setting → bawaan). */
    public function renderSyahadah(Alumni $alumni): string
    {
        $alumni->loadMissing(['santri', 'lembaga']);

        $template = ($alumni->lembaga_id
            ? Setting::getTenantStringValue(self::SETTING_TEMPLATE_PREFIX.$alumni->lembaga_id)
            : null)
            ?: Setting::getTenantStringValue(self::SETTING_TEMPLATE_PREFIX.'default')
            ?: self::DEFAULT_TEMPLATE;

        $santri = $alumni->santri;

        return strtr($template, [
            '{{nomor}}' => $alumni->nomor_syahadah ?? '.../SYH/...',
            '{{nama}}' => $santri?->nama ?? '—',
            '{{nis}}' => $santri?->nis ?? '—',
            '{{nisn}}' => ($santri?->nisn ?: null) ?? '—',
            '{{nspp}}' => ($alumni->lembaga?->nomor_statistik ?: null) ?? '—',
            '{{jenjang}}' => ($alumni->lembaga?->jenjang ?: null) ?? '—',
            '{{nomor_ijazah}}' => $alumni->nomor_ijazah ?? '—',
            '{{ttl}}' => trim(($santri?->tempat_lahir ? $santri->tempat_lahir.', ' : '')
                .($santri?->tanggal_lahir?->translatedFormat('d F Y') ?? '—')),
            '{{lembaga}}' => $alumni->lembaga?->nama ?? 'pesantren ini',
            '{{tahun_lulus}}' => (string) $alumni->tahun_lulus,
            '{{tanggal_cetak}}' => now()->translatedFormat('d F Y'),
            '{{pesantren}}' => tenant('name') ?? config('app.name'),
        ]);
    }

    // ------------------------------------------------------------------
    // 4) Mutasi keluar (pindah pondok)
    // ------------------------------------------------------------------

    /**
     * Mutasi keluar: status 'keluar' (nilai enum existing — tidak ada ALTER),
     * jejak status_santri beralasan + arsip surat (disk tenant, serve via
     * /_tenant-storage), penempatan aktif ditutup. Riwayat utuh.
     *
     * $fileSurat: UploadedFile (disimpan service) ATAU string path yang sudah
     * tersimpan di disk tenant (mis. hasil FileUpload Filament).
     */
    public function mutasiKeluar(Santri $santri, string $tanggal, string $alasan, UploadedFile|string|null $fileSurat = null, ?User $actor = null): Santri
    {
        $this->ensureTenantContext();

        if ($santri->status === 'keluar') {
            throw new RuntimeException('Santri sudah berstatus keluar/mutasi.');
        }

        $path = is_string($fileSurat) ? ($fileSurat ?: null) : null;
        if ($fileSurat instanceof UploadedFile) {
            // Hanya pdf/jpg/png — html/svg via /_tenant-storage = stored XSS.
            $ekstensi = strtolower($fileSurat->getClientOriginalExtension());
            if (! in_array($ekstensi, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                throw new RuntimeException('Surat mutasi harus PDF/JPG/PNG.');
            }
            $disimpan = Storage::disk(StorageQuotaService::diskName())
                ->putFile('mutasi', $fileSurat);
            $path = $disimpan === false ? null : $disimpan;
        }

        return DB::transaction(function () use ($santri, $tanggal, $alasan, $path, $actor): Santri {
            StatusSantri::create([
                'santri_id' => $santri->id,
                'status' => 'keluar',
                'tanggal_status' => $tanggal,
                'alasan' => 'Mutasi keluar — '.$alasan,
                'file_surat_path' => $path,
                'created_by' => $actor?->getKey(),
            ]);

            $this->tutupPenempatanAktif($santri, 'keluar');

            return $santri->fresh();
        });
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Tutup penempatan aktif + set status santri — LANGSUNG via query builder
     * (tanpa event model): tidak ada penempatan baru, observer WP-05 tidak
     * dipicu, baris riwayat penempatan tetap utuh.
     */
    private function tutupPenempatanAktif(Santri $santri, string $statusBaru): void
    {
        PenempatanKelas::query()
            ->where('santri_id', $santri->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        Santri::query()->whereKey($santri->id)->update([
            'status' => $statusBaru,
            'kelas_id' => null, // tanpa penempatan aktif: keluar dari roster kelas
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
