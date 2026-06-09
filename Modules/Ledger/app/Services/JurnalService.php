<?php

namespace Modules\Ledger\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Ledger\Exceptions\JurnalTidakSeimbangException;
use Modules\Ledger\Exceptions\PeriodeTerkunciException;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Models\JurnalBaris;
use Modules\Ledger\Models\PeriodeAkuntansi;
use RuntimeException;

/**
 * Service tunggal & atomik untuk posting jurnal double-entry.
 *
 * Menegakkan lima pagar arsitektur keuangan:
 *  #1 Buku besar = sumber kebenaran (jurnal + jurnal_baris)
 *  #2 Immutable — koreksi via reverseJurnal(), bukan edit/hapus
 *  #3 Idempoten — satu transaksi sumber -> satu jurnal (idempotency_key)
 *  #4 Periode `closed` menolak posting bertanggal periode itu
 *  #5 Uang decimal(15,2), tiap baris bawa 3 dimensi (akun, divisi, sumber_dana)
 */
class JurnalService
{
    /** Toleransi pembulatan saat membandingkan total debit vs kredit. */
    private const EPSILON = 0.001;

    public function __construct(private readonly KehalalanGuard $kehalalanGuard = new KehalalanGuard)
    {
    }

    /**
     * Posting satu jurnal seimbang secara atomik & idempoten.
     *
     * @param  array{
     *     tanggal?: mixed,
     *     deskripsi?: ?string,
     *     tipe_sumber?: string,
     *     ref_type?: ?string,
     *     ref_id?: ?string,
     *     idempotency_key?: ?string,
     *     dibuat_oleh?: ?int,
     *     baris: array<int, array{akun_id:int, divisi_id?:?int, sumber_dana_id?:?int, debit?:float|int|string, kredit?:float|int|string, memo?:?string}>
     * }  $data
     */
    public function postJurnal(array $data): Jurnal
    {
        $this->ensureTenantContext();

        $baris = $data['baris'] ?? [];
        if (count($baris) < 2) {
            throw new InvalidArgumentException('Jurnal minimal memiliki 2 baris (satu debit, satu kredit).');
        }

        $tanggal = isset($data['tanggal']) ? Carbon::parse($data['tanggal']) : Carbon::now();

        // Idempotensi (#3): kunci eksplisit, atau diturunkan dari (ref_type, ref_id).
        $idempotencyKey = $data['idempotency_key'] ?? $this->deriveIdempotencyKey(
            $data['ref_type'] ?? null,
            $data['ref_id'] ?? null,
        );

        if ($idempotencyKey !== null) {
            $existing = Jurnal::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing; // sudah pernah diposting — jangan gandakan.
            }
        }

        // Validasi keseimbangan & bentuk baris (#1, #5).
        [$totalDebit, $totalKredit, $normalized] = $this->validateBaris($baris);

        if (abs($totalDebit - $totalKredit) > self::EPSILON) {
            throw new JurnalTidakSeimbangException(sprintf(
                'Jurnal tidak seimbang: total debit %s ≠ total kredit %s.',
                number_format($totalDebit, 2),
                number_format($totalKredit, 2),
            ));
        }

        if ($totalDebit <= self::EPSILON) {
            throw new JurnalTidakSeimbangException('Total jurnal harus lebih besar dari 0.');
        }

        // Penjagaan kehalalan (dimensi ke-4) — aktif hanya bila setting tenant ON.
        // Ditaruh di sini agar SEMUA jalur posting terlindungi (tidak ada celah).
        $this->kehalalanGuard->assert($normalized);

        // Periode terkunci (#4): tolak bila tanggal jatuh di periode `closed`.
        $periode = $this->resolvePeriode($tanggal);
        if ($periode !== null && $periode->isClosed()) {
            throw new PeriodeTerkunciException(sprintf(
                'Periode "%s" berstatus closed — transaksi bertanggal %s ditolak.',
                $periode->nama,
                $tanggal->toDateString(),
            ));
        }

        try {
            return DB::transaction(function () use ($data, $tanggal, $idempotencyKey, $periode, $normalized) {
                $jurnal = Jurnal::create([
                    'nomor' => $this->generateNomor($tanggal),
                    'tanggal' => $tanggal,
                    'deskripsi' => $data['deskripsi'] ?? null,
                    'tipe_sumber' => $data['tipe_sumber'] ?? 'manual',
                    'ref_type' => $data['ref_type'] ?? null,
                    'ref_id' => $data['ref_id'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'periode_id' => $periode?->id,
                    'status' => 'posted',
                    'dibuat_oleh' => $data['dibuat_oleh'] ?? null,
                ]);

                foreach ($normalized as $row) {
                    $jurnal->baris()->create($row);
                }

                return $jurnal->load('baris');
            });
        } catch (QueryException $e) {
            // Race: dua proses memposting transaksi sumber yang sama secara bersamaan.
            // Unique index (tenant_id, idempotency_key) menolak yang kedua — kembalikan yang sudah ada.
            if ($idempotencyKey !== null && $this->isUniqueViolation($e)) {
                $existing = Jurnal::where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }
    }

    /**
     * Buat jurnal pembalik (reversal) untuk mengoreksi jurnal yang sudah `posted`.
     * Jurnal asal TIDAK diedit/dihapus (#2) — debit/kredit ditukar pada jurnal baru.
     */
    public function reverseJurnal(Jurnal $jurnal, ?string $alasan = null, ?int $dibuatOleh = null): Jurnal
    {
        $this->ensureTenantContext();

        if ($jurnal->status !== 'posted') {
            throw new RuntimeException("Hanya jurnal berstatus 'posted' yang dapat dibalik. Status sekarang: {$jurnal->status}.");
        }

        $tanggal = Carbon::now();
        $periode = $this->resolvePeriode($tanggal);
        if ($periode !== null && $periode->isClosed()) {
            throw new PeriodeTerkunciException('Tidak dapat membalik jurnal pada periode yang sudah closed.');
        }

        return DB::transaction(function () use ($jurnal, $alasan, $dibuatOleh, $tanggal, $periode) {
            $pembalik = Jurnal::create([
                'nomor' => $this->generateNomor($tanggal),
                'tanggal' => $tanggal,
                'deskripsi' => 'Pembalik #' . $jurnal->nomor . ($alasan ? ' — ' . $alasan : ''),
                'tipe_sumber' => 'pembalik',
                'ref_type' => $jurnal->ref_type,
                'ref_id' => $jurnal->ref_id,
                'idempotency_key' => 'reversal:' . $jurnal->id,
                'periode_id' => $periode?->id,
                'status' => 'posted',
                'reversal_of_id' => $jurnal->id,
                'dibuat_oleh' => $dibuatOleh,
            ]);

            foreach ($jurnal->baris as $row) {
                $pembalik->baris()->create([
                    'akun_id' => $row->akun_id,
                    'divisi_id' => $row->divisi_id,
                    'sumber_dana_id' => $row->sumber_dana_id,
                    'party_type' => $row->party_type,
                    'party_id' => $row->party_id,
                    'debit' => $row->kredit, // ditukar
                    'kredit' => $row->debit,
                    'memo' => 'Pembalik: ' . ($row->memo ?? ''),
                ]);
            }

            $jurnal->update([
                'status' => 'reversed',
                'reversed_by_id' => $pembalik->id,
            ]);

            return $pembalik->load('baris');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array{0: float, 1: float, 2: array<int, array<string, mixed>>}
     */
    private function validateBaris(array $baris): array
    {
        $totalDebit = 0.0;
        $totalKredit = 0.0;
        $normalized = [];

        foreach ($baris as $i => $row) {
            if (empty($row['akun_id'])) {
                throw new InvalidArgumentException("Baris #{$i}: akun_id wajib diisi.");
            }

            $debit = round((float) ($row['debit'] ?? 0), 2);
            $kredit = round((float) ($row['kredit'] ?? 0), 2);

            if ($debit < 0 || $kredit < 0) {
                throw new InvalidArgumentException("Baris #{$i}: nilai debit/kredit tidak boleh negatif.");
            }
            if ($debit > 0 && $kredit > 0) {
                throw new InvalidArgumentException("Baris #{$i}: satu baris hanya boleh debit ATAU kredit, bukan keduanya.");
            }
            if ($debit === 0.0 && $kredit === 0.0) {
                throw new InvalidArgumentException("Baris #{$i}: nilai debit dan kredit tidak boleh sama-sama 0.");
            }

            $totalDebit += $debit;
            $totalKredit += $kredit;

            $normalized[] = [
                'akun_id' => $row['akun_id'],
                'divisi_id' => $row['divisi_id'] ?? null,
                'sumber_dana_id' => $row['sumber_dana_id'] ?? null,
                'party_type' => $row['party_type'] ?? null,
                'party_id' => $row['party_id'] ?? null,
                'debit' => $debit,
                'kredit' => $kredit,
                'memo' => $row['memo'] ?? null,
            ];
        }

        return [round($totalDebit, 2), round($totalKredit, 2), $normalized];
    }

    private function deriveIdempotencyKey(?string $refType, ?string $refId): ?string
    {
        if ($refType !== null && $refId !== null) {
            return $refType . ':' . $refId;
        }

        return null;
    }

    /** Cari periode akuntansi yang memuat tanggal transaksi (bila ada). */
    private function resolvePeriode(Carbon $tanggal): ?PeriodeAkuntansi
    {
        return PeriodeAkuntansi::where('tgl_mulai', '<=', $tanggal->toDateString())
            ->where('tgl_selesai', '>=', $tanggal->toDateString())
            ->first();
    }

    /** Penomoran JRN/THN/BLN/URUT, unik & berurutan per tenant per bulan. */
    private function generateNomor(Carbon $tanggal): string
    {
        $prefix = sprintf('JRN/%04d/%02d/', $tanggal->year, $tanggal->month);

        $last = Jurnal::where('nomor', 'like', $prefix . '%')
            ->orderByDesc('nomor')
            ->lockForUpdate()
            ->value('nomor');

        $next = 1;
        if ($last !== null) {
            $next = ((int) substr($last, strlen($prefix))) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk posting jurnal.');
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // 23505 = unique_violation (PostgreSQL), 23000 = integrity constraint (MySQL/SQLite).
        return in_array($e->getCode(), ['23505', '23000'], true);
    }
}
