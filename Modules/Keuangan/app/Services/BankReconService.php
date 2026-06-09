<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Keuangan\Models\BankStatement;
use Modules\Keuangan\Models\BankStatementLine;
use Modules\Ledger\Models\Akun;
use Modules\Ledger\Models\JurnalBaris;
use RuntimeException;

/**
 * F6 — Reconciliation Engine (Bank Reconciliation).
 *
 * Mencocokkan baris rekening koran (bank_statement_line) ke jurnal_baris pada
 * akun kas/bank. Aturan cocok: akun sama, sisi sama (masuk→debit, keluar→kredit),
 * nominal sama persis, tanggal dalam toleransi, dan jurnal_baris belum dipakai
 * baris lain. Idempoten: menjalankan ulang autoMatch tidak mengubah yang sudah
 * cocok dan tidak mengikat satu jurnal_baris ke dua baris.
 */
class BankReconService
{
    /** Toleransi selisih tanggal (hari) saat auto-match. */
    public int $toleransiHari = 3;

    /**
     * Impor satu rekening koran + barisnya.
     *
     * @param  array{nama?:string, periode_awal?:?string, periode_akhir?:?string, saldo_awal?:?float, saldo_akhir?:?float, sumber?:string}  $meta
     * @param  array<int, array{tanggal:string, nominal:float, arah:string, keterangan?:?string, referensi?:?string}>  $lines
     */
    public function import(int $akunId, array $meta, array $lines): BankStatement
    {
        $this->ensureTenantContext();

        $akun = Akun::query()->findOrFail($akunId);
        if (! $akun->is_kas_bank) {
            throw new InvalidArgumentException('Akun yang dipilih bukan akun kas/bank.');
        }

        return DB::transaction(function () use ($akunId, $meta, $lines): BankStatement {
            $statement = BankStatement::create([
                'akun_id' => $akunId,
                'nama' => $meta['nama'] ?? 'Rekening Koran',
                'periode_awal' => $meta['periode_awal'] ?? null,
                'periode_akhir' => $meta['periode_akhir'] ?? null,
                'saldo_awal' => $meta['saldo_awal'] ?? null,
                'saldo_akhir' => $meta['saldo_akhir'] ?? null,
                'sumber' => $meta['sumber'] ?? 'manual',
                'status' => 'draft',
                'diimpor_oleh' => auth()->id(),
            ]);

            foreach ($lines as $row) {
                $arah = $row['arah'] ?? null;
                if (! in_array($arah, ['masuk', 'keluar'], true)) {
                    throw new InvalidArgumentException("Arah baris harus 'masuk' atau 'keluar'.");
                }
                $statement->lines()->create([
                    'tanggal' => $row['tanggal'],
                    'keterangan' => $row['keterangan'] ?? null,
                    'referensi' => $row['referensi'] ?? null,
                    'nominal' => abs((float) $row['nominal']),
                    'arah' => $arah,
                    'status' => 'belum_cocok',
                ]);
            }

            return $statement->fresh('lines');
        });
    }

    /**
     * Auto-cocokkan semua baris yang belum cocok. Idempoten.
     *
     * @return array{cocok:int, belum_cocok:int, total:int}
     */
    public function autoMatch(BankStatement $statement): array
    {
        $this->ensureTenantContext();
        $this->pastikanTidakTerkunci($statement);

        // jurnal_baris yang sudah terpakai baris lain (di statement manapun) — agar tidak dobel.
        $terpakai = BankStatementLine::query()
            ->whereNotNull('jurnal_baris_id')
            ->pluck('jurnal_baris_id')
            ->all();
        $terpakai = array_flip($terpakai);

        $belum = $statement->lines()->where('status', 'belum_cocok')->get();

        foreach ($belum as $line) {
            $kolom = $line->arah === 'masuk' ? 'debit' : 'kredit';
            $tanggal = Carbon::parse($line->tanggal);

            $kandidat = JurnalBaris::query()
                ->where('akun_id', $statement->akun_id)
                ->where($kolom, (float) $line->nominal)
                ->whereHas('jurnal', function ($q) use ($tanggal) {
                    $q->where('status', 'posted')
                        ->whereBetween('tanggal', [
                            $tanggal->copy()->subDays($this->toleransiHari)->startOfDay(),
                            $tanggal->copy()->addDays($this->toleransiHari)->endOfDay(),
                        ]);
                })
                ->orderBy('id')
                ->get();

            foreach ($kandidat as $jb) {
                if (isset($terpakai[$jb->id])) {
                    continue;
                }
                $line->update([
                    'status' => 'cocok',
                    'jurnal_baris_id' => $jb->id,
                    'dicocokkan_oleh' => auth()->id(),
                    'dicocokkan_pada' => now(),
                ]);
                $terpakai[$jb->id] = true;
                break;
            }
        }

        return $this->ringkasan($statement->fresh('lines'));
    }

    /** Cocokkan satu baris ke jurnal_baris tertentu (manual override). */
    public function cocokkanManual(BankStatementLine $line, int $jurnalBarisId): BankStatementLine
    {
        $this->ensureTenantContext();
        $this->pastikanTidakTerkunci($line->statement);

        $jb = JurnalBaris::query()->findOrFail($jurnalBarisId);
        if ((int) $jb->akun_id !== (int) $line->statement->akun_id) {
            throw new InvalidArgumentException('Jurnal baris bukan pada akun kas/bank statement ini.');
        }
        $dipakaiLain = BankStatementLine::query()
            ->where('jurnal_baris_id', $jurnalBarisId)
            ->where('id', '!=', $line->id)
            ->exists();
        if ($dipakaiLain) {
            throw new InvalidArgumentException('Jurnal baris sudah dicocokkan ke mutasi lain.');
        }

        $line->update([
            'status' => 'cocok',
            'jurnal_baris_id' => $jurnalBarisId,
            'dicocokkan_oleh' => auth()->id(),
            'dicocokkan_pada' => now(),
        ]);

        return $line->fresh();
    }

    /** Batalkan pencocokan satu baris. */
    public function batalkanCocok(BankStatementLine $line): BankStatementLine
    {
        $this->ensureTenantContext();
        $this->pastikanTidakTerkunci($line->statement);

        $line->update([
            'status' => 'belum_cocok',
            'jurnal_baris_id' => null,
            'dicocokkan_oleh' => null,
            'dicocokkan_pada' => null,
        ]);

        return $line->fresh();
    }

    /** Kunci statement (lock bulanan) — tidak boleh diubah lagi. */
    public function kunci(BankStatement $statement): BankStatement
    {
        $this->ensureTenantContext();
        $statement->update(['status' => 'terkunci']);

        return $statement->fresh();
    }

    /**
     * Ringkasan rekonsiliasi.
     *
     * @return array{cocok:int, belum_cocok:int, total:int, nominal_cocok:float, nominal_belum:float, selisih_saldo:?float}
     */
    public function ringkasan(BankStatement $statement): array
    {
        $this->ensureTenantContext();

        $lines = $statement->relationLoaded('lines') ? $statement->lines : $statement->lines()->get();
        $cocok = $lines->where('status', 'cocok');
        $belum = $lines->where('status', 'belum_cocok');

        $signed = fn ($c) => (float) $c->sum(fn ($l) => $l->arah === 'masuk' ? (float) $l->nominal : -(float) $l->nominal);

        $selisih = null;
        if ($statement->saldo_awal !== null && $statement->saldo_akhir !== null) {
            $mutasi = $signed($lines);
            $selisih = round((float) $statement->saldo_awal + $mutasi - (float) $statement->saldo_akhir, 2);
        }

        return [
            'cocok' => $cocok->count(),
            'belum_cocok' => $belum->count(),
            'total' => $lines->count(),
            'nominal_cocok' => round((float) $cocok->sum('nominal'), 2),
            'nominal_belum' => round((float) $belum->sum('nominal'), 2),
            'selisih_saldo' => $selisih,
        ];
    }

    private function pastikanTidakTerkunci(BankStatement $statement): void
    {
        if ($statement->terkunci()) {
            throw new RuntimeException('Rekonsiliasi sudah dikunci dan tidak dapat diubah.');
        }
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk rekonsiliasi.');
        }
    }
}
