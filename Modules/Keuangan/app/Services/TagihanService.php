<?php

namespace Modules\Keuangan\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;

class TagihanService
{
    public function buatTagihanUntukSantri(Santri|int $santri, array $payload): Tagihan
    {
        $this->ensureTenantContext();

        $santriModel = $santri instanceof Santri
            ? $santri
            : Santri::query()->findOrFail($santri);

        return $this->persistTagihan($santriModel, $payload);
    }

    public function sinkronkanTagihanUntukSantri(Santri|int $santri, array $payload, ?Tagihan $tagihan = null): Tagihan
    {
        $this->ensureTenantContext();

        $santriModel = $santri instanceof Santri
            ? $santri
            : Santri::query()->findOrFail($santri);

        return $this->persistTagihan($santriModel, $payload, $tagihan);
    }

    private function persistTagihan(Santri $santriModel, array $payload, ?Tagihan $tagihan = null): Tagihan
    {
        $this->ensureTenantContext();

        $periode = $this->resolvePeriodePayload($payload);
        $nominal = (float) ($payload['nominal'] ?? 0);
        $diskon = (float) ($payload['diskon'] ?? 0);

        $attributes = [
            'santri_id' => $santriModel->id,
            'jenis_tagihan' => $payload['jenis_tagihan'],
            'keterangan' => $payload['keterangan'] ?? null,
            'nominal' => $nominal,
            // #332: rincian komponen syahriah (opsional, transparansi wali).
            'komponen' => $payload['komponen'] ?? null,
            'diskon' => $diskon,
            'nominal_akhir' => max($nominal - $diskon, 0),
            'jatuh_tempo' => $payload['jatuh_tempo'],
            'status' => $payload['status'] ?? 'belum_bayar',
            // G2-15: fund-aware — dimensi sumber dana & divisi kini benar-benar disimpan.
            'sumber_dana_id' => $payload['sumber_dana_id'] ?? null,
            'divisi_unit_id' => $payload['divisi_unit_id'] ?? null,
            'is_dibayar_dimuka' => (bool) ($payload['is_dibayar_dimuka'] ?? false),
            'periode_akademik' => $payload['periode_akademik'] ?? null,
            'bulan_masehi' => $periode['bulan_masehi'],
            'bulan_hijriah' => $periode['bulan_hijriah'],
            'created_by' => $payload['created_by'] ?? null,
        ];

        if ($tagihan instanceof Tagihan) {
            $tagihan->fill($attributes);
            $tagihan->save();

            return $tagihan->fresh();
        }

        return Tagihan::create($attributes);
    }

    public function setPeriodeKeuangan(string $mode): Setting
    {
        $this->ensureTenantContext();

        if (! in_array($mode, ['masehi', 'hijriah', 'keduanya'], true)) {
            throw new InvalidArgumentException('Mode periode keuangan tidak valid.');
        }

        return Setting::query()->updateOrCreate(
            ['key' => 'periode_keuangan'],
            ['value' => $mode]
        );
    }

    public function buatTagihanPerKelas(Kelas|int $kelas, array $payload): EloquentCollection
    {
        $this->ensureTenantContext();

        $kelasModel = $kelas instanceof Kelas
            ? $kelas
            : Kelas::query()->findOrFail($kelas);

        $santriAktif = $kelasModel->santri()
            ->where('status', 'aktif')
            ->orderBy('id')
            ->get();

        return $this->buatTagihanBulk(
            $santriAktif,
            $payload
        );
    }

    public function buatTagihanBulk(iterable $santriList, array $payload): EloquentCollection
    {
        $this->ensureTenantContext();

        $created = DB::transaction(function () use ($santriList, $payload): array {
            $records = [];

            foreach ($this->normaliseSantriList($santriList) as $santri) {
                $records[] = $this->buatTagihanUntukSantri($santri, $payload);
            }

            return $records;
        });

        return new EloquentCollection($created);
    }

    private function normaliseSantriList(iterable $santriList): Collection
    {
        $items = collect($santriList);

        if ($items->isEmpty()) {
            return collect();
        }

        $first = $items->first();

        if ($first instanceof Santri) {
            return $items->values();
        }

        return Santri::query()
            ->whereIn('id', $items->all())
            ->orderBy('id')
            ->get();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk pembuatan tagihan.');
        }
    }

    private function resolvePeriodePayload(array $payload): array
    {
        $mode = $this->getPeriodeKeuanganMode();
        $jatuhTempo = $payload['jatuh_tempo'] instanceof CarbonInterface
            ? $payload['jatuh_tempo']
            : now()->parse($payload['jatuh_tempo']);

        $bulanMasehi = $payload['bulan_masehi'] ?? $jatuhTempo->format('Y-m');
        $bulanHijriah = $payload['bulan_hijriah'] ?? null;

        return match ($mode) {
            'masehi' => [
                'bulan_masehi' => $bulanMasehi,
                'bulan_hijriah' => null,
            ],
            'hijriah' => [
                'bulan_masehi' => null,
                'bulan_hijriah' => $this->requireBulanHijriah($bulanHijriah, $mode),
            ],
            default => [
                'bulan_masehi' => $bulanMasehi,
                'bulan_hijriah' => $this->requireBulanHijriah($bulanHijriah, $mode),
            ],
        };
    }

    private function getPeriodeKeuanganMode(): string
    {
        $setting = Setting::query()->where('key', 'periode_keuangan')->first();
        $mode = $setting?->value;

        if (! is_string($mode) || $mode === '') {
            return 'masehi';
        }

        return $mode;
    }

    private function requireBulanHijriah(mixed $bulanHijriah, string $mode): string
    {
        if (is_string($bulanHijriah) && $bulanHijriah !== '') {
            return $bulanHijriah;
        }

        throw new InvalidArgumentException("Mode periode keuangan {$mode} membutuhkan `bulan_hijriah`.");
    }

    /**
     * Konversi tanggal Masehi ke label bulan Hijriah.
     * Menggunakan IntlCalendar jika ext-intl tersedia,
     * fallback ke Islamic Civil (Tabular) calendar.
     */
    public function hitungBulanHijriah(string|\DateTimeInterface $tanggal): string
    {
        $date = $tanggal instanceof \DateTimeInterface
            ? $tanggal
            : new \DateTimeImmutable($tanggal);

        $timestamp = $date->getTimestamp();

        if (extension_loaded('intl')) {
            $cal = \IntlCalendar::createInstance(null, 'ar_SA@calendar=islamic-civil');
            $cal->setTime($timestamp * 1000);
            $bulanIndex = $cal->get(\IntlCalendar::FIELD_MONTH); // 0-based
            $tahun = $cal->get(\IntlCalendar::FIELD_YEAR);
        } else {
            $jd = gregoriantojd((int) $date->format('n'), (int) $date->format('j'), (int) $date->format('Y'));
            $l = $jd - 1948440 + 10632;
            $n = (int) (($l - 1) / 10631);
            $l = $l - 10631 * $n + 354;
            $j = (int) ((10985 - $l) / 5316) * (int) ((50 * $l) / 17719)
                + (int) (($l) / 5670) * (int) ((43 * $l) / 15238);
            $l = $l - (int) ((30 - $j) / 15) * (int) ((17719 * $j) / 50)
                - (int) (($j) / 16) * (int) ((15238 * $j) / 43) + 29;
            $bulanIndex = (int) ((24 * $l) / 709) - 1;
            $tahun = (int) (30 * $n + $j - 30);
        }

        $namaBulan = [
            'Muharram', 'Safar', 'Rabiul Awal', 'Rabiul Akhir',
            'Jumadil Awal', 'Jumadil Akhir', 'Rajab', "Sya'ban",
            'Ramadhan', 'Syawal', "Dzulqa'dah", 'Dzulhijjah',
        ];

        return ($namaBulan[$bulanIndex] ?? 'Unknown')." {$tahun}";
    }
}
