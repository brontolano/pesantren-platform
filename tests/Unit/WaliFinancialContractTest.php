<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Modules\Keuangan\Data\PembayaranWaliData;
use Modules\Keuangan\Data\TagihanWaliData;
use Modules\Keuangan\Data\WaliFinancialData;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci KONTRAK payload Portal Wali → frontend (lihat resources/js/types/wali.d.ts).
 * Murni unit (tanpa boot Laravel / tanpa DB). Kalau field DTO berubah tanpa update .d.ts,
 * test ini gagal — itu pengaman regresi kontrak lapisan-2.
 */
final class WaliFinancialContractTest extends TestCase
{
    public function test_tagihan_keys_sama_dengan_kontrak_dts(): void
    {
        $d = new TagihanWaliData(
            id: 1, jenisTagihan: 'SPP', keterangan: 'September',
            nominalAkhir: 500000.0, totalDibayar: 200000.0, sisaTagihan: 300000.0,
            status: 'sebagian', jatuhTempo: '2026-09-10', periodeAkademik: '2026/2027',
        );

        self::assertSame(
            ['id', 'jenisTagihan', 'keterangan', 'nominalAkhir', 'totalDibayar', 'sisaTagihan', 'status', 'jatuhTempo', 'periodeAkademik'],
            array_keys($d->toArray()),
        );
        self::assertSame('sebagian', $d->toArray()['status']);
        self::assertSame(300000.0, $d->toArray()['sisaTagihan']);
    }

    public function test_pembayaran_keys_sama_dengan_kontrak_dts(): void
    {
        $p = new PembayaranWaliData(
            id: 9, tagihan: 'SPP', nominal: 200000.0, metode: 'transfer',
            tanggalPembayaran: '2026-09-05', isVerified: true, nomorBukti: 'TRX-1',
        );

        self::assertSame(
            ['id', 'tagihan', 'nominal', 'metode', 'tanggalPembayaran', 'isVerified', 'nomorBukti'],
            array_keys($p->toArray()),
        );
        self::assertTrue($p->toArray()['isVerified']);
    }

    public function test_agregat_financial_membentuk_props_lengkap(): void
    {
        $payload = (new WaliFinancialData(
            bills: new Collection([new TagihanWaliData(1, 'SPP', null, 500000.0, 0.0, 500000.0, 'belum_bayar', null, '2026/2027')]),
            payments: new Collection([new PembayaranWaliData(9, 'SPP', 200000.0, 'tunai', '2026-09-05', false, null)]),
            savings: ['saldo' => 150000.0, 'transactions' => []],
            summary: ['tagihanBelumLunas' => 1, 'totalOutstanding' => 500000.0],
        ))->toArray();

        self::assertSame(['bills', 'payments', 'savings', 'summary'], array_keys($payload));
        self::assertCount(1, $payload['bills']);
        self::assertSame('belum_bayar', $payload['bills'][0]['status']);
        self::assertSame(150000.0, $payload['savings']['saldo']);
        self::assertSame(1, $payload['summary']['tagihanBelumLunas']);
    }
}
