<?php

namespace Modules\Keuangan\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\TabunganTransaksi;
use RuntimeException;

/**
 * POS Kantin via Tabungan (2b).
 *
 * Belanja: verifikasi PIN santri (REUSE hash akun App Santri — PIN adalah
 * password user santri, di-set via SantriAccountService::setPin) lalu potong
 * saldo lewat TabunganService::belanjaKantin (lock + no-minus + batas harian).
 *
 * Refund: SEKALI SAJA per transaksi belanja — guard race-safe via lock baris
 * transaksi asal sebelum cek refund existing.
 */
class KantinService
{
    public function __construct(private readonly TabunganService $tabunganService) {}

    /**
     * Verifikasi PIN App Santri. Santri tanpa akun login = belum punya PIN
     * → SELALU ditolak (tidak ada bypass).
     */
    public function verifikasiPin(Santri $santri, string $pin): bool
    {
        if (trim($pin) === '' || ! $santri->user_id) {
            return false;
        }

        // Rate limit: PIN = password login App Santri — brute force 6 digit
        // di kasir tidak boleh jadi pintu kompromi akun. 5 percobaan / 10 menit.
        $kunci = 'kantin-pin:'.tenant('id').':'.$santri->id;
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw new RuntimeException('Terlalu banyak percobaan PIN — coba lagi 10 menit lagi.');
        }

        $user = User::withoutGlobalScopes()->find($santri->user_id);
        $cocok = $user !== null && Hash::check($pin, $user->password);

        if ($cocok) {
            RateLimiter::clear($kunci);
        } else {
            RateLimiter::hit($kunci, 600);
        }

        return $cocok;
    }

    /**
     * Belanja kantin: PIN wajib benar, lalu debit tabungan.
     *
     * @param  array{keterangan?: ?string, created_by?: ?int, tanggal?: ?string}  $options
     */
    public function belanja(Santri $santri, float $nominal, string $pin, array $options = []): TabunganTransaksi
    {
        $this->ensureTenantContext();

        if (! $this->verifikasiPin($santri, $pin)) {
            throw new InvalidArgumentException('PIN santri salah atau belum disetel — transaksi ditolak.');
        }

        $options['keterangan'] = $options['keterangan'] ?? 'Belanja kantin';
        // Belanja selalu tercatat HARI INI — tanggal kiriman caller dibuang
        // (date-shift bisa melompati batas belanja harian).
        unset($options['tanggal']);

        return $this->tabunganService->belanjaKantin($santri, $nominal, $options);
    }

    /**
     * Refund penuh transaksi belanja kantin — sekali saja per transaksi.
     */
    public function refund(TabunganTransaksi $asal, ?int $byUserId = null, ?string $alasan = null): TabunganTransaksi
    {
        $this->ensureTenantContext();

        return DB::transaction(function () use ($asal, $byUserId, $alasan): TabunganTransaksi {
            // Lock baris asal: dua refund bersamaan serial di sini, yang kedua
            // melihat refund pertama dan ditolak (guard sekali-saja race-safe).
            $asal = TabunganTransaksi::query()->lockForUpdate()->findOrFail($asal->id);

            if ($asal->tipe !== 'belanja_kantin') {
                throw new InvalidArgumentException('Hanya transaksi belanja kantin yang bisa di-refund.');
            }

            $sudah = TabunganTransaksi::query()
                ->where('tipe', 'refund_kantin')
                ->where('ref_transaksi_id', $asal->id)
                ->exists();
            if ($sudah) {
                throw new InvalidArgumentException('Transaksi ini sudah pernah di-refund.');
            }

            $santriId = $asal->tabungan?->santri_id;
            if ($santriId === null) {
                throw new RuntimeException('Tabungan transaksi asal tidak ditemukan.');
            }

            return $this->tabunganService->refundKantin($santriId, (float) $asal->nominal, [
                'ref_transaksi_id' => $asal->id,
                'keterangan' => 'Refund belanja kantin #'.$asal->id.($alasan ? ' — '.$alasan : ''),
                'created_by' => $byUserId,
            ]);
        });
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
