<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tabungan;
use Modules\Keuangan\Models\TabunganTransaksi;
use RuntimeException;
use InvalidArgumentException;

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
     * List transaksi tabungan untuk satu santri.
     */
    public function listTransaksi(Santri|int $santri, int $limit = 50): \Illuminate\Database\Eloquent\Collection
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

            // RACE GUARD overdraft: cek saldo di tarik() memakai baca TANPA lock —
            // dua penarikan bersamaan bisa sama-sama lolos lalu serial di lock ini
            // dan keduanya mengurangi saldo → SALDO NEGATIF. Validasi ulang pada
            // saldo baris ter-LOCK adalah satu-satunya cek yang race-safe.
            if ($tipe === 'tarik' && $saldoSebelum < $nominal) {
                throw new InvalidArgumentException(
                    "Saldo tidak cukup. Saldo saat ini: {$saldoSebelum}, penarikan: {$nominal}"
                );
            }

            // Kontrol rekening (TAB-5, dicek pada baris ter-lock — race-safe):
            // tabungan dibekukan menolak SEMUA penarikan; limit_harian membatasi
            // total tarik per hari kalender (setoran tidak dibatasi).
            if ($tipe === 'tarik') {
                if ($tabungan->is_frozen) {
                    throw new InvalidArgumentException('Tabungan dibekukan — penarikan ditolak.');
                }

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

            $saldoSesudah = $tipe === 'setor'
                ? $saldoSebelum + $nominal
                : $saldoSebelum - $nominal;

            $transaksi = TabunganTransaksi::create([
                'tabungan_id' => $tabungan->id,
                'tipe' => $tipe,
                'nominal' => $nominal,
                'saldo_sebelum' => $saldoSebelum,
                'saldo_sesudah' => $saldoSesudah,
                'keterangan' => $options['keterangan'] ?? null,
                'tanggal' => $options['tanggal'] ?? now()->toDateString(),
                'created_by' => $options['created_by'] ?? null,
            ]);

            $tabungan->update(['saldo' => $saldoSesudah]);

            return $transaksi;
        });

        // Auto-jurnal (didengarkan modul Ledger bila aktif). Loose coupling.
        \Modules\Keuangan\Events\TabunganTransaksiDicatat::dispatch($transaksi);

        return $transaksi;
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
