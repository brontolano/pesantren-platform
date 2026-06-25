<?php

namespace Modules\Keuangan\Services;

use App\Models\Setting;
use App\Support\PhoneNumber;
use Modules\Keuangan\Models\Pembayaran;
use Modules\Keuangan\Models\SetorKelilingItem;
use Modules\Keuangan\Models\TabunganTopup;
use Modules\Notifikasi\Services\NotifDispatcher;
use Throwable;

/**
 * Wiring notifikasi WA untuk event uang PWA Wali/Peserta (NOTIF-1, TGH-5, TAB-4).
 *
 * Semua lewat NotifDispatcher (F7): fire-and-forget, gated TemplateNotifikasi
 * aktif per tenant — TIDAK PERNAH mengganggu alur uang (dipanggil SETELAH
 * transaksi commit; kegagalan kanal hanya tercatat di log).
 */
class KeuanganNotifikasiService
{
    public function __construct(private readonly NotifDispatcher $dispatcher) {}

    /** Wali: pembayaran diverifikasi (tagihan berkurang/lunas). */
    public function pembayaranDiverifikasi(Pembayaran $pembayaran): void
    {
        $this->safe(function () use ($pembayaran): void {
            $santri = $pembayaran->santri;
            $this->dispatcher->kirim('pembayaran_diverifikasi', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri?->nama ?? '-',
                    'jenis_tagihan' => $pembayaran->tagihan?->jenis_tagihan ?? '-',
                    'nominal' => number_format((float) $pembayaran->nominal, 0, ',', '.'),
                    'status_tagihan' => $pembayaran->tagihan?->fresh()?->status ?? '-',
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** Wali: bukti pembayaran ditolak (beserta alasan). */
    public function pembayaranDitolak(Pembayaran $pembayaran, string $alasan): void
    {
        $this->safe(function () use ($pembayaran, $alasan): void {
            $santri = $pembayaran->santri;
            $this->dispatcher->kirim('pembayaran_ditolak', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri?->nama ?? '-',
                    'jenis_tagihan' => $pembayaran->tagihan?->jenis_tagihan ?? '-',
                    'nominal' => number_format((float) $pembayaran->nominal, 0, ',', '.'),
                    'alasan' => $alasan,
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** Wali: setoran saldo disetujui (saldo bertambah). */
    public function topupDisetujui(TabunganTopup $topup): void
    {
        $this->safe(function () use ($topup): void {
            $santri = $topup->tabungan?->santri;
            $this->dispatcher->kirim('topup_diverifikasi', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri?->nama ?? '-',
                    'nominal' => number_format((float) $topup->nominal, 0, ',', '.'),
                    'saldo' => number_format((float) ($topup->tabungan?->fresh()?->saldo ?? 0), 0, ',', '.'),
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** Wali: setoran saldo ditolak (beserta alasan). */
    public function topupDitolak(TabunganTopup $topup, string $alasan): void
    {
        $this->safe(function () use ($topup, $alasan): void {
            $santri = $topup->tabungan?->santri;
            $this->dispatcher->kirim('topup_ditolak', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri?->nama ?? '-',
                    'nominal' => number_format((float) $topup->nominal, 0, ',', '.'),
                    'alasan' => $alasan,
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** Wali: setoran via petugas keliling disetujui (pakai template TOPUP_DIVERIFIKASI). */
    public function setorKelilingDisetujui(SetorKelilingItem $item): void
    {
        $this->safe(function () use ($item): void {
            $santri = $item->santri;
            $saldo = app(TabunganService::class)->getSaldo($item->santri_id);
            $this->dispatcher->kirim('topup_diverifikasi', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri?->nama ?? '-',
                    'nominal' => number_format((float) $item->nominal, 0, ',', '.'),
                    'saldo' => number_format($saldo, 0, ',', '.'),
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** Admin/bendahara: ada bukti bayar/topup baru masuk antrean verifikasi. */
    public function buktiMasuk(string $jenis, string $namaSantri, float $nominal): void
    {
        $this->safe(function () use ($jenis, $namaSantri, $nominal): void {
            $admin = Setting::getTenantStringValue('admission_admin_whatsapp');
            $this->dispatcher->kirim('bukti_bayar_masuk', [
                'wa' => array_filter([$admin]),
                'data' => [
                    'jenis' => $jenis,
                    'nama_santri' => $namaSantri,
                    'nominal' => number_format($nominal, 0, ',', '.'),
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** @return array<int,string> Nomor WA wali (kolom santri.no_hp_wali), ternormalisasi 62…. */
    private function waliPhones(?object $santri): array
    {
        $normalized = PhoneNumber::normalize($santri->no_hp_wali ?? null);

        return $normalized === '' ? [] : [$normalized];
    }

    private function namaPesantren(): string
    {
        return Setting::getTenantStringValue('branding_name') ?? (tenant('name') ?? 'Pesantren');
    }

    /** Pagar mutlak: notifikasi TIDAK BOLEH menggagalkan alur uang pemanggil. */
    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
