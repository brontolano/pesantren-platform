<?php

namespace Modules\Keuangan\Services;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Events\TabunganTransaksiDicatat;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use RuntimeException;

class TabunganService
{
    /**
     * Ambil atau buat tabungan untuk santri.
     */
    public function getOrCreateTabungan(Santri|int $santri): Tabungan
    {
        $this->ensureTenantContext();

        $santriId = $santri instanceof Santri ? $santri->id : $santri;

        return Tabungan::query()->firstOrCreate(
            ['santri_id' => $santriId],
            ['saldo' => 0]
        );
    }

    /**
     * Setor tabungan.
     */
    public function setor(Santri|int $santri, float $nominal, array $options = []): TabunganTransaksi
    {
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal setoran harus lebih dari 0.');
        }

        return $this->createTransaksi($santri, 'setor', $nominal, $options);
    }

    /**
     * Tarik tabungan.
     */
    public function tarik(Santri|int $santri, float $nominal, array $options = []): TabunganTransaksi
    {
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal penarikan harus lebih dari 0.');
        }

        $tabungan = $this->getOrCreateTabungan($santri);

        if ((float) $tabungan->saldo < $nominal) {
            throw new InvalidArgumentException(
                "Saldo tidak cukup. Saldo saat ini: {$tabungan->saldo}, penarikan: {$nominal}"
            );
        }

        return $this->createTransaksi($santri, 'tarik', $nominal, $options);
    }

    /**
     * Belanja kantin — debit saldo tabungan (POS Kantin 2b).
     *
     * Semua invarian dicek pada baris ter-LOCK di createTransaksi:
     * saldo tak boleh minus, tabungan beku ditolak, batas belanja harian
     * (per-santri batas_belanja_harian; NULL = default tenant
     * Setting 'kantin.batas_belanja_default'; 0 = blokir).
     */
    public function belanjaKantin(Santri|int $santri, float $nominal, array $options = []): TabunganTransaksi
    {
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal belanja harus lebih dari 0.');
        }

        return $this->createTransaksi($santri, 'belanja_kantin', $nominal, $options);
    }

    /**
     * Refund belanja kantin — kredit saldo kembali, ref ke transaksi asal.
     * Guard "sekali saja" ada di KantinService::refund (lock baris asal).
     */
    public function refundKantin(Santri|int $santri, float $nominal, array $options = []): TabunganTransaksi
    {
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal refund harus lebih dari 0.');
        }

        if (empty($options['ref_transaksi_id'])) {
            throw new InvalidArgumentException('Refund kantin wajib menunjuk transaksi belanja asal.');
        }

        return $this->createTransaksi($santri, 'refund_kantin', $nominal, $options);
    }

    /**
     * List transaksi tabungan untuk satu santri.
     */
    public function listTransaksi(Santri|int $santri, int $limit = 50): Collection
    {
        $this->ensureTenantContext();

        $tabungan = $this->getOrCreateTabungan($santri);

        return $tabungan->transaksi()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Saldo terkini.
     */
    public function getSaldo(Santri|int $santri): float
    {
        return (float) $this->getOrCreateTabungan($santri)->saldo;
    }

    private function createTransaksi(Santri|int $santri, string $tipe, float $nominal, array $options): TabunganTransaksi
    {
        $this->ensureTenantContext();

        $tabungan = $this->getOrCreateTabungan($santri);

        $transaksi = DB::transaction(function () use ($tabungan, $tipe, $nominal, $options): TabunganTransaksi {
            // Lock row for update
            $tabungan = Tabungan::query()->lockForUpdate()->find($tabungan->id);

            $saldoSebelum = (float) $tabungan->saldo;

            // Arah mutasi: setor & refund_kantin menambah saldo; tarik &
            // belanja_kantin mengurangi (debit).
            $isDebit = in_array($tipe, ['tarik', 'belanja_kantin'], true);

            // RACE GUARD overdraft: cek saldo di tarik() memakai baca TANPA lock —
            // dua penarikan bersamaan bisa sama-sama lolos lalu serial di lock ini
            // dan keduanya mengurangi saldo → SALDO NEGATIF. Validasi ulang pada
            // saldo baris ter-LOCK adalah satu-satunya cek yang race-safe.
            if ($isDebit && $saldoSebelum < $nominal) {
                throw new InvalidArgumentException(
                    "Saldo tidak cukup. Saldo saat ini: {$saldoSebelum}, penarikan: {$nominal}"
                );
            }

            // Kontrol rekening (TAB-5, dicek pada baris ter-lock — race-safe):
            // tabungan dibekukan menolak SEMUA penarikan; limit_harian membatasi
            // total tarik per hari kalender (setoran tidak dibatasi).
            if ($isDebit && $tabungan->is_frozen) {
                throw new InvalidArgumentException('Tabungan dibekukan — penarikan ditolak.');
            }

            if ($tipe === 'tarik') {
                $limit = (float) ($tabungan->limit_harian ?? 0);
                if ($limit > 0) {
                    $tarikHariIni = (float) TabunganTransaksi::query()
                        ->where('tabungan_id', $tabungan->id)
                        ->where('tipe', 'tarik')
                        ->whereDate('tanggal', $options['tanggal'] ?? now()->toDateString())
                        ->sum('nominal');
                    if ($tarikHariIni + $nominal > $limit + 0.001) {
                        throw new InvalidArgumentException(
                            "Melebihi limit penarikan harian ({$limit}). Sudah ditarik hari ini: {$tarikHariIni}."
                        );
                    }
                }
            }

            // Batas belanja kantin harian (2b, dicek pada baris ter-lock):
            // per-santri batas_belanja_harian; NULL = default tenant
            // Setting 'kantin.batas_belanja_default' (tidak ada = tanpa batas);
            // 0 = blokir belanja kantin sama sekali (keputusan wali/tenant).
            if ($tipe === 'belanja_kantin') {
                $batas = $tabungan->batas_belanja_harian;
                if ($batas === null) {
                    $default = Setting::getTenantValue('kantin.batas_belanja_default');
                    $batas = is_numeric($default) ? (float) $default : null;
                }

                if ($batas !== null) {
                    $batas = (float) $batas;
                    if ($batas <= 0) {
                        throw new InvalidArgumentException('Belanja kantin diblokir (batas harian 0).');
                    }

                    $belanjaHariIni = (float) TabunganTransaksi::query()
                        ->where('tabungan_id', $tabungan->id)
                        ->where('tipe', 'belanja_kantin')
                        ->whereDate('tanggal', $options['tanggal'] ?? now()->toDateString())
                        ->sum('nominal');
                    if ($belanjaHariIni + $nominal > $batas + 0.001) {
                        throw new InvalidArgumentException(
                            "Melebihi batas belanja harian ({$batas}). Sudah belanja hari ini: {$belanjaHariIni}."
                        );
                    }
                }
            }

            $saldoSesudah = $isDebit
                ? $saldoSebelum - $nominal
                : $saldoSebelum + $nominal;

            $transaksi = TabunganTransaksi::create([
                'tabungan_id' => $tabungan->id,
                'tipe' => $tipe,
                'nominal' => $nominal,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => $options['keterangan'] ?? null,
                'tanggal' => $options['tanggal'] ?? now()->toDateString(),
                'created_by' => $options['created_by'] ?? null,
                'ref_transaksi_id' => $options['ref_transaksi_id'] ?? null,
            ]);

            $tabungan->update(['saldo' => $saldoSesudah]);

            return $transaksi;
        });

        // Auto-jurnal (didengarkan modul Ledger bila aktif). Loose coupling.
        TabunganTransaksiDicatat::dispatch($transaksi);

        return $transaksi;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
