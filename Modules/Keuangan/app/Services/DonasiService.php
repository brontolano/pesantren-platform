<?php

namespace Modules\Keuangan\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\Donasi;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Services\JurnalService;
use RuntimeException;

/**
 * 2c — Baitul Maal: konfirmasi donasi manual-transfer oleh bendahara.
 *
 * Alur: niat donasi publik (menunggu_transfer) → bendahara cek bukti →
 * konfirmasi → posting jurnal D Kas / K Pendapatan-Dana sesuai SumberDana
 * program. Idempoten: ref_type=Donasi + ref_id (kunci non-null, dedup di
 * JurnalService + unique DB), konfirmasi 2x = 1 jurnal.
 *
 * Pemetaan akun pendapatan (COA LedgerSeeder):
 *  - SumberDana is_wakaf            → 4-3000 Penerimaan Wakaf
 *  - lainnya (zakat/infaq/sedekah)  → 4-4000 Penerimaan Zakat / Infak
 */
class DonasiService
{
    public function __construct(private readonly JurnalService $jurnalService) {}

    public function konfirmasi(Donasi $donasi, ?int $userId = null): Donasi
    {
        $this->ensureTenantContext();

        $simpan = function () use ($donasi, $userId): Donasi {
            return DB::transaction(function () use ($donasi, $userId): Donasi {
                /** @var Donasi $terkunci */
                $terkunci = Donasi::query()->lockForUpdate()->findOrFail($donasi->getKey());

                if ($terkunci->status === Donasi::STATUS_DIKONFIRMASI) {
                    return $terkunci; // idempoten — sudah dikonfirmasi.
                }

                if ($terkunci->status !== Donasi::STATUS_MENUNGGU) {
                    throw new InvalidArgumentException(
                        'Donasi berstatus "'.$terkunci->status.'" tidak bisa dikonfirmasi.'
                    );
                }

                $nominal = round((float) $terkunci->nominal, 2);
                if ($nominal <= 0) {
                    throw new InvalidArgumentException('Nominal donasi harus lebih besar dari 0.');
                }

                $program = $terkunci->program()->withTrashed()->firstOrFail();
                $sumberDana = $program->sumberDana()->firstOrFail();

                $akunKas = Akun::kasBank()->where('is_postable', true)->orderBy('kode')->first();
                $kodePendapatan = $sumberDana->is_wakaf ? '4-3000' : '4-4000';
                $akunPendapatan = Akun::where('kode', $kodePendapatan)->first();

                if ($akunKas === null || $akunPendapatan === null) {
                    throw new RuntimeException('COA belum di-seed — konfirmasi donasi membutuhkan akun kas & pendapatan.');
                }

                $jurnal = $this->jurnalService->postJurnal([
                    'tanggal' => now(),
                    'deskripsi' => sprintf('Donasi %s — %s (#%d)', $program->nama, $terkunci->nama_donatur, $terkunci->id),
                    'tipe_sumber' => 'donasi',
                    'ref_type' => 'Donasi',
                    'ref_id' => (string) $terkunci->id, // kunci idempotensi non-null, tertambat ke dokumen sumber.
                    'dibuat_oleh' => $userId,
                    'baris' => [
                        [
                            'akun_id' => $akunKas->id,
                            'sumber_dana_id' => $sumberDana->id,
                            'debit' => $nominal,
                            'kredit' => 0,
                            'memo' => 'Kas masuk donasi '.$program->nama,
                        ],
                        [
                            'akun_id' => $akunPendapatan->id,
                            'sumber_dana_id' => $sumberDana->id,
                            'debit' => 0,
                            'kredit' => $nominal,
                            'memo' => 'Penerimaan dana '.$sumberDana->nama_sumber,
                        ],
                    ],
                ]);

                // Nomor kuitansi race-safe (pola syahadah WP-08): lock baris urut
                // tertinggi (PG tak mengizinkan FOR UPDATE + agregat), unique DB
                // sebagai gerbang akhir, retry sekali bila kalah balapan.
                $urut = 1 + (int) Donasi::query()
                    ->whereNotNull('nomor_kuitansi_urut')
                    ->orderByDesc('nomor_kuitansi_urut')
                    ->lockForUpdate()
                    ->value('nomor_kuitansi_urut');

                $terkunci->forceFill([
                    'status' => Donasi::STATUS_DIKONFIRMASI,
                    'jurnal_id' => $jurnal->id,
                    'dikonfirmasi_oleh' => $userId,
                    'dikonfirmasi_at' => now(),
                    'nomor_kuitansi_urut' => $urut,
                    'nomor_kuitansi' => sprintf('KW-DON/%s/%04d', now()->format('Y'), $urut),
                ])->save();

                return $terkunci;
            });
        };

        try {
            return $simpan();
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23505', '23000'], true)) {
                return $simpan(); // kalah balapan nomor urut — retry sekali.
            }
            throw $e;
        }
    }

    public function tolak(Donasi $donasi, ?int $userId = null): Donasi
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($donasi, $userId): Donasi {
            /** @var Donasi $terkunci */
            $terkunci = Donasi::query()->lockForUpdate()->findOrFail($donasi->getKey());

            if ($terkunci->status === Donasi::STATUS_DITOLAK) {
                return $terkunci;
            }

            if ($terkunci->status !== Donasi::STATUS_MENUNGGU) {
                throw new InvalidArgumentException(
                    'Donasi berstatus "'.$terkunci->status.'" tidak bisa ditolak.'
                );
            }

            $terkunci->forceFill([
                'status' => Donasi::STATUS_DITOLAK,
                'dikonfirmasi_oleh' => $userId,
                'dikonfirmasi_at' => now(),
            ])->save();

            return $terkunci;
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk donasi.');
        }
    }
}
