<?php

declare(strict_types=1);

namespace Modules\Keuangan\Data;

use Illuminate\Support\Collection;

/**
 * KONTRAK AGREGAT untuk layar Portal Wali "Keuangan" (Financial).
 *
 * Inilah seluruh "props" yang diterima resources/js/Pages/PortalWali/Financial.vue.
 * Controller cukup `return WaliFinancialData::build(...)->toArray()` lewat Inertia,
 * dan frontend tahu persis bentuknya dari resources/js/types/wali.d.ts (WaliFinancial).
 *
 * Lihat docs/PLAYBOOK-IRISAN.md untuk cara memindahkan PortalWaliService::getFinancialPayload()
 * agar memakai agregat ini (mengganti map-array inline menjadi DTO).
 *
 * @param Collection<int,TagihanWaliData>    $bills
 * @param Collection<int,PembayaranWaliData> $payments
 */
final class WaliFinancialData
{
    /**
     * @param array{saldo: float, transactions: array<int,array<string,mixed>>} $savings
     * @param array{tagihanBelumLunas: int, totalOutstanding: float}            $summary
     */
    public function __construct(
        public readonly Collection $bills,
        public readonly Collection $payments,
        public readonly array $savings,
        public readonly array $summary,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'bills' => $this->bills->map(fn (TagihanWaliData $d) => $d->toArray())->values()->all(),
            'payments' => $this->payments->map(fn (PembayaranWaliData $d) => $d->toArray())->values()->all(),
            'savings' => $this->savings,
            'summary' => $this->summary,
        ];
    }
}
