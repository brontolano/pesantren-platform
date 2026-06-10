<?php

namespace Modules\Core\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Models\Surat;
use RuntimeException;

/**
 * Surat & Administrasi — pencatatan surat masuk/keluar dengan penomoran
 * agenda otomatis yang RACE-SAFE: nomor urut diambil dari MAX(nomor_urut)
 * per (jenis, tahun) di dalam transaksi; unique index
 * (tenant, jenis, tahun, nomor_urut) jadi backstop bila dua pencatatan
 * bersamaan — yang kalah di-retry sekali.
 *
 * Format nomor surat KELUAR: {urut}/{kode}/{bulan-romawi}/{tahun}
 * Surat MASUK menyimpan nomor surat pengirim apa adanya; agenda tetap berurut.
 */
class SuratService
{
    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * @param array{jenis:string, tanggal_surat:string, pihak:string, perihal:string,
     *     nomor_surat?:?string, lampiran_path?:?string, keterangan?:?string,
     *     kode?:string, created_by?:?int} $payload
     */
    public function catat(array $payload): Surat
    {
        $this->ensureTenantContext();

        $jenis = $payload['jenis'] ?? '';
        if (! in_array($jenis, ['masuk', 'keluar'], true)) {
            throw new InvalidArgumentException("Jenis surat harus 'masuk' atau 'keluar'.");
        }
        if ($jenis === 'masuk' && blank($payload['nomor_surat'] ?? null)) {
            throw new InvalidArgumentException('Surat masuk wajib mencantumkan nomor surat pengirim.');
        }

        $tanggal = Carbon::parse($payload['tanggal_surat']);

        $simpan = function () use ($payload, $jenis, $tanggal): Surat {
            return DB::transaction(function () use ($payload, $jenis, $tanggal): Surat {
                // PG tak mengizinkan FOR UPDATE + agregat → lock baris urut tertinggi.
                // Balapan dua pencatat pada urut sama tetap tertangkap unique index
                // (tenant, jenis, tahun, nomor_urut) → retry sekali di bawah.
                $urut = 1 + (int) Surat::query()
                    ->where('jenis', $jenis)
                    ->where('tahun', $tanggal->year)
                    ->orderByDesc('nomor_urut')
                    ->lockForUpdate()
                    ->value('nomor_urut');

                $nomor = $jenis === 'keluar'
                    ? sprintf(
                        '%03d/%s/%s/%d',
                        $urut,
                        strtoupper($payload['kode'] ?? 'PES'),
                        self::ROMAWI[$tanggal->month],
                        $tanggal->year,
                    )
                    : (string) $payload['nomor_surat'];

                return Surat::create([
                    'jenis' => $jenis,
                    'tahun' => $tanggal->year,
                    'nomor_urut' => $urut,
                    'nomor_surat' => $nomor,
                    'tanggal_surat' => $tanggal->toDateString(),
                    'pihak' => $payload['pihak'],
                    'perihal' => $payload['perihal'],
                    'lampiran_path' => $payload['lampiran_path'] ?? null,
                    'keterangan' => $payload['keterangan'] ?? null,
                    'created_by' => $payload['created_by'] ?? null,
                ]);
            });
        };

        try {
            return $simpan();
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique (tenant, jenis, tahun, nomor_urut) kena saat balapan → retry sekali.
            if (in_array($e->getCode(), ['23505', '23000'], true)) {
                return $simpan();
            }
            throw $e;
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
