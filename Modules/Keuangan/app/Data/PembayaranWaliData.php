<?php

declare(strict_types=1);

namespace Modules\Keuangan\Data;

use Modules\Keuangan\Models\Pembayaran;

/**
 * KONTRAK: bentuk satu pembayaran saat dikirim ke Portal Wali.
 * Cermin TypeScript: resources/js/types/wali.d.ts (PembayaranWali).
 */
final class PembayaranWaliData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $tagihan,        // jenis_tagihan terkait
        public readonly float $nominal,
        public readonly ?string $metode,
        public readonly ?string $tanggalPembayaran, // 'Y-m-d'
        public readonly bool $isVerified,
        public readonly ?string $nomorBukti,
    ) {}

    public static function fromModel(Pembayaran $p): self
    {
        return new self(
            id: (int) $p->id,
            tagihan: $p->tagihan?->jenis_tagihan,
            nominal: (float) $p->nominal,
            metode: $p->metode,
            tanggalPembayaran: $p->tanggal_pembayaran?->format('Y-m-d'),
            isVerified: (bool) $p->is_verified,
            nomorBukti: $p->nomor_bukti,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tagihan' => $this->tagihan,
            'nominal' => $this->nominal,
            'metode' => $this->metode,
            'tanggalPembayaran' => $this->tanggalPembayaran,
            'isVerified' => $this->isVerified,
            'nomorBukti' => $this->nomorBukti,
        ];
    }
}
