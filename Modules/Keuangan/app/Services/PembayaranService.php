<?php

namespace Modules\Keuangan\Services;

use Illuminate\Support\Facades\DB;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\Tagihan;
use RuntimeException;
use InvalidArgumentException;

class PembayaranService
{
    /**
     * Input pembayaran manual oleh bendahara (T1.6.4).
     * Mendukung partial payment / cicilan (T1.6.5).
     */
    public function inputPembayaran(Tagihan|int $tagihan, array $payload): Pembayaran
    {
        $this->ensureTenantContext();

        $tagihanModel = $tagihan instanceof Tagihan
            ? $tagihan
            : Tagihan::query()->findOrFail($tagihan);

        if ($tagihanModel->status === 'lunas') {
            throw new InvalidArgumentException('Tagihan sudah lunas, tidak bisa menerima pembayaran.');
        }
        if ($tagihanModel->status === 'batal') {
            throw new InvalidArgumentException('Tagihan sudah dibatalkan.');
        }

        $nominal = (float) ($payload['nominal'] ?? 0);
        if ($nominal <= 0) {
            throw new InvalidArgumentException('Nominal pembayaran harus lebih dari 0.');
        }

        $sisaTagihan = $tagihanModel->sisa_tagihan;
        if ($nominal > $sisaTagihan && $sisaTagihan > 0) {
            throw new InvalidArgumentException(
                "Nominal pembayaran ({$nominal}) melebihi sisa tagihan ({$sisaTagihan})."
            );
        }

        return DB::transaction(function () use ($tagihanModel, $payload, $nominal): Pembayaran {
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihanModel->id,
                'santri_id' => $tagihanModel->santri_id,
                'nominal' => $nominal,
                'metode' => $payload['metode'] ?? 'tunai',
                'tanggal_pembayaran' => $payload['tanggal_pembayaran'] ?? now()->toDateString(),
                'nomor_bukti' => $payload['nomor_bukti'] ?? null,
                'bukti_pembayaran' => $payload['bukti_pembayaran'] ?? null,
                'catatan' => $payload['catatan'] ?? null,
                'is_verified' => false,
                'created_by' => $payload['created_by'] ?? null,
            ]);

            $this->updateStatusTagihan($tagihanModel);

            return $pembayaran;
        });
    }

    /**
     * Verifikasi pembayaran oleh admin/bendahara.
     */
    public function verifikasiPembayaran(Pembayaran|int $pembayaran, int $verifiedBy): Pembayaran
    {
        $this->ensureTenantContext();

        $model = $pembayaran instanceof Pembayaran
            ? $pembayaran
            : Pembayaran::query()->findOrFail($pembayaran);

        if ($model->is_verified) {
            throw new InvalidArgumentException('Pembayaran sudah diverifikasi.');
        }

        $model->update([
            'is_verified' => true,
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ]);

        // Re-evaluate status tagihan setelah verifikasi
        $this->updateStatusTagihan($model->tagihan);

        return $model->fresh();
    }

    /**
     * Batalkan pembayaran (soft — hanya hapus record, tagihan di-recalculate).
     */
    public function batalkanPembayaran(Pembayaran|int $pembayaran): void
    {
        $this->ensureTenantContext();

        $model = $pembayaran instanceof Pembayaran
            ? $pembayaran
            : Pembayaran::query()->findOrFail($pembayaran);

        $tagihan = $model->tagihan;

        DB::transaction(function () use ($model, $tagihan): void {
            $model->delete();
            $this->updateStatusTagihan($tagihan->fresh());
        });
    }

    /**
     * Recalculate status tagihan berdasarkan total pembayaran terverifikasi.
     */
    public function updateStatusTagihan(Tagihan $tagihan): void
    {
        $totalDibayar = $tagihan->pembayaran()
            ->where('is_verified', true)
            ->sum('nominal');

        $nominalAkhir = (float) $tagihan->nominal_akhir;

        if ($totalDibayar >= $nominalAkhir && $nominalAkhir > 0) {
            $tagihan->update(['status' => 'lunas']);
        } elseif ($totalDibayar > 0) {
            $tagihan->update(['status' => 'sebagian']);
        } else {
            $tagihan->update(['status' => 'belum_bayar']);
        }
    }

    /**
     * List pembayaran untuk satu tagihan.
     */
    public function listPembayaranTagihan(Tagihan|int $tagihan): \Illuminate\Database\Eloquent\Collection
    {
        $this->ensureTenantContext();

        $id = $tagihan instanceof Tagihan ? $tagihan->id : $tagihan;

        return Pembayaran::query()
            ->where('tagihan_id', $id)
            ->orderBy('tanggal_pembayaran', 'desc')
            ->get();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
