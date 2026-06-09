<?php

namespace Modules\Kegiatan\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Kegiatan\Models\Kegiatan;
use Modules\Kegiatan\Models\KegiatanPhase;
use Modules\Kegiatan\Models\KegiatanRecurring;
use Modules\Kegiatan\Models\KegiatanTemplate;
use Modules\Kegiatan\Models\PresensiKegiatan;
use RuntimeException;

/**
 * G3-19 — Template + milestone/Gantt + recurring (idempoten) + analitik
 * partisipasi. Memperluas modul Kegiatan TANPA mengubah KegiatanService teruji.
 */
class KegiatanLanjutService
{
    /** Batas keras occurrences untuk mencegah loop tak terbatas. */
    private const MAX_OCCURRENCE = 730;

    public function buatTemplate(array $data): KegiatanTemplate
    {
        $this->ensureTenantContext();
        if (empty($data['nama'])) {
            throw new InvalidArgumentException('Nama template wajib diisi.');
        }

        return KegiatanTemplate::create([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'jenis' => $data['jenis'] ?? 'umum',
            'default_phases' => $data['default_phases'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** Clone template menjadi satu Kegiatan + phase default. */
    public function cloneTemplate(KegiatanTemplate|int $template, array $overrides = []): Kegiatan
    {
        $this->ensureTenantContext();
        $tpl = $template instanceof KegiatanTemplate ? $template : KegiatanTemplate::query()->findOrFail($template);

        $mulai = isset($overrides['mulai']) ? Carbon::parse($overrides['mulai']) : Carbon::now();
        $kegiatan = Kegiatan::create([
            'nama' => $overrides['nama'] ?? $tpl->nama,
            'deskripsi' => $overrides['deskripsi'] ?? $tpl->deskripsi,
            'jenis' => $overrides['jenis'] ?? $tpl->jenis,
            'mulai' => $mulai,
            'selesai' => $overrides['selesai'] ?? null,
            'lokasi' => $overrides['lokasi'] ?? null,
            'status' => 'terjadwal',
            'template_id' => $tpl->id,
            'penanggung_jawab_id' => $overrides['penanggung_jawab_id'] ?? null,
        ]);

        $this->terapkanPhaseDefault($kegiatan, $tpl, $mulai);

        return $kegiatan->fresh();
    }

    /** Tambah phase/milestone manual (validasi urutan tanggal). */
    public function tambahPhase(Kegiatan|int $kegiatan, array $data): KegiatanPhase
    {
        $this->ensureTenantContext();
        $keg = $kegiatan instanceof Kegiatan ? $kegiatan : Kegiatan::query()->findOrFail($kegiatan);
        if (empty($data['nama'])) {
            throw new InvalidArgumentException('Nama phase wajib diisi.');
        }
        $mulai = isset($data['tanggal_mulai']) ? Carbon::parse($data['tanggal_mulai']) : null;
        $selesai = isset($data['tanggal_selesai']) ? Carbon::parse($data['tanggal_selesai']) : null;
        if ($mulai !== null && $selesai !== null && $selesai->lt($mulai)) {
            throw new InvalidArgumentException('Tanggal selesai phase tidak boleh sebelum tanggal mulai.');
        }

        return KegiatanPhase::create([
            'kegiatan_id' => $keg->id,
            'nama' => $data['nama'],
            'urutan' => $data['urutan'] ?? (((int) $keg->phases()->max('urutan')) + 1),
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'status' => $data['status'] ?? 'rencana',
        ]);
    }

    /** Data Gantt: phase terurut dgn rentang tanggal. */
    public function ganttData(Kegiatan|int $kegiatan): Collection
    {
        $this->ensureTenantContext();
        $keg = $kegiatan instanceof Kegiatan ? $kegiatan : Kegiatan::query()->findOrFail($kegiatan);

        return $keg->phases()->get()->map(fn (KegiatanPhase $p): array => [
            'nama' => $p->nama,
            'urutan' => $p->urutan,
            'mulai' => $p->tanggal_mulai?->toDateString(),
            'selesai' => $p->tanggal_selesai?->toDateString(),
            'status' => $p->status,
        ]);
    }

    public function buatRecurring(array $data): KegiatanRecurring
    {
        $this->ensureTenantContext();
        $frekuensi = $data['frekuensi'] ?? 'mingguan';
        if (! array_key_exists($frekuensi, KegiatanRecurring::FREKUENSI)) {
            throw new InvalidArgumentException('Frekuensi tidak valid (mingguan|bulanan).');
        }
        if (empty($data['mulai'])) {
            throw new InvalidArgumentException('Tanggal mulai recurring wajib diisi.');
        }
        $interval = max(1, (int) ($data['interval'] ?? 1));

        return KegiatanRecurring::create([
            'nama' => $data['nama'] ?? 'Kegiatan berulang',
            'template_id' => $data['template_id'] ?? null,
            'frekuensi' => $frekuensi,
            'interval' => $interval,
            'mulai' => $data['mulai'],
            'akhir' => $data['akhir'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Tanggal kemunculan dari anchor `mulai` hingga min(akhir, $sampai).
     * Berbasis indeks dari anchor → tanpa cumulative drift.
     *
     * @return array<int, Carbon>
     */
    public function occurrences(KegiatanRecurring $recurring, string|CarbonInterface $sampai): array
    {
        $anchor = $recurring->mulai->copy()->startOfDay();
        $batas = Carbon::parse($sampai)->startOfDay();
        if ($recurring->akhir !== null && $recurring->akhir->lt($batas)) {
            $batas = $recurring->akhir->copy()->startOfDay();
        }
        $interval = max(1, (int) $recurring->interval);

        $dates = [];
        for ($i = 0; $i < self::MAX_OCCURRENCE; $i++) {
            $tgl = $recurring->frekuensi === 'bulanan'
                ? $anchor->copy()->addMonthsNoOverflow($interval * $i)
                : $anchor->copy()->addDays(7 * $interval * $i);
            if ($tgl->gt($batas)) {
                return $dates;
            }
            $dates[] = $tgl;
        }

        // Mentok MAX_OCCURRENCE sementara masih dalam batas → surface (jangan
        // generate tak lengkap secara diam-diam). Persempit window atau set akhir.
        throw new RuntimeException(
            'Rentang recurring melebihi batas ' . self::MAX_OCCURRENCE . ' kemunculan — persempit tanggal akhir/sampai.'
        );
    }

    /**
     * Generate instance Kegiatan untuk tiap occurrence s/d $sampai (IDEMPOTEN:
     * lewati yang sudah ada per (recurring, tanggal_acuan); unique index backstop).
     *
     * @return int jumlah instance baru dibuat
     */
    public function generateHingga(KegiatanRecurring|int $recurring, string|CarbonInterface $sampai): int
    {
        $this->ensureTenantContext();
        $rec = $recurring instanceof KegiatanRecurring ? $recurring : KegiatanRecurring::query()->findOrFail($recurring);
        if (! $rec->is_active) {
            return 0;
        }
        $tpl = $rec->template_id !== null ? KegiatanTemplate::find($rec->template_id) : null;

        $n = 0;
        foreach ($this->occurrences($rec, $sampai) as $tgl) {
            $tglStr = $tgl->toDateString();
            $sudah = Kegiatan::where('recurring_id', $rec->id)->whereDate('tanggal_acuan', $tglStr)->exists();
            if ($sudah) {
                continue; // idempoten
            }
            try {
                $kegiatan = Kegiatan::create([
                    'nama' => $tpl?->nama ?? $rec->nama,
                    'deskripsi' => $tpl?->deskripsi,
                    'jenis' => $tpl?->jenis ?? 'umum',
                    'mulai' => $tgl->copy()->setTime(8, 0),
                    'status' => 'terjadwal',
                    'template_id' => $tpl?->id,
                    'recurring_id' => $rec->id,
                    'tanggal_acuan' => $tglStr,
                ]);
                if ($tpl !== null) {
                    $this->terapkanPhaseDefault($kegiatan, $tpl, $tgl);
                }
                $n++;
            } catch (QueryException $e) {
                // Race: unique index (tenant, recurring_id, tanggal_acuan) menolak
                // duplikat — abaikan, instance sudah ada.
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }
            }
        }

        return $n;
    }

    /**
     * Analitik partisipasi sebuah kegiatan (dari PresensiKegiatan existing).
     *
     * @return array{hadir:int, total:int, persen:float, per_status:array<string,int>}
     */
    public function rekapPartisipasi(Kegiatan|int $kegiatan): array
    {
        $this->ensureTenantContext();
        $kegId = $kegiatan instanceof Kegiatan ? $kegiatan->id : (int) $kegiatan;

        $rows = PresensiKegiatan::where('kegiatan_id', $kegId)->get();
        $total = $rows->count();
        $hadir = $rows->where('status', 'hadir')->count();
        $perStatus = $rows->groupBy('status')->map->count()->all();

        return [
            'hadir' => $hadir,
            'total' => $total,
            'persen' => $total > 0 ? round($hadir / $total * 100, 2) : 0.0,
            'per_status' => $perStatus,
        ];
    }

    private function terapkanPhaseDefault(Kegiatan $kegiatan, KegiatanTemplate $tpl, CarbonInterface $anchor): void
    {
        $phases = $tpl->default_phases ?? [];
        if (! is_array($phases)) {
            return;
        }
        $i = 0;
        foreach ($phases as $ph) {
            $i++;
            $mulai = $anchor->copy()->startOfDay()->addDays((int) ($ph['offset_hari'] ?? 0));
            $durasi = max(1, (int) ($ph['durasi_hari'] ?? 1));
            $selesai = $mulai->copy()->addDays($durasi - 1);
            KegiatanPhase::create([
                'kegiatan_id' => $kegiatan->id,
                'nama' => $ph['nama'] ?? ('Phase '.$i),
                'urutan' => (int) ($ph['urutan'] ?? $i),
                'tanggal_mulai' => $mulai,
                'tanggal_selesai' => $selesai,
                'status' => 'rencana',
            ]);
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // Modul tenant = PostgreSQL → 23505 unique_violation spesifik. TIDAK menelan
        // 23000 generik agar pelanggaran FK/NOT-NULL tetap tersurface (bukan diam).
        return $e->getCode() === '23505';
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
