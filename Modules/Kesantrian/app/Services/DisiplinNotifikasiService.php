<?php

namespace Modules\Kesantrian\Services;

use App\Models\Setting;
use App\Support\PhoneNumber;
use Modules\Kesantrian\Models\EskalasiDisiplin;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Notifikasi\Services\NotifDispatcher;
use Throwable;

/**
 * WP-09 — Wiring notifikasi WA wali untuk event disiplin, pola
 * KeuanganNotifikasiService: semua lewat NotifDispatcher (fire-and-forget,
 * gated TemplateNotifikasi aktif per tenant) — TIDAK PERNAH mengganggu
 * alur pencatatan/verifikasi pemanggil.
 */
class DisiplinNotifikasiService
{
    public function __construct(private readonly NotifDispatcher $dispatcher) {}

    /** Wali: kejadian pelanggaran terverifikasi (kebijakan 'semua'/'berat'). */
    public function kejadian(PelanggaranSantri $pelanggaran): void
    {
        $this->safe(function () use ($pelanggaran): void {
            $santri = $pelanggaran->santri;
            $this->dispatcher->kirim('disiplin_pelanggaran', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri?->nama ?? '-',
                    'jenis_pelanggaran' => $pelanggaran->jenis?->nama ?? '-',
                    'kategori' => $pelanggaran->jenis?->kategori ?? '-',
                    'poin' => (string) ($pelanggaran->jenis?->poin ?? 0),
                    'waktu' => $pelanggaran->waktu_kejadian?->translatedFormat('d M Y H:i') ?? '-',
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** Wali: eskalasi SP (ambang poin terlampaui). */
    public function eskalasi(Santri $santri, EskalasiDisiplin $eskalasi): void
    {
        $this->safe(function () use ($santri, $eskalasi): void {
            $this->dispatcher->kirim('disiplin_eskalasi', [
                'wa' => $this->waliPhones($santri),
                'data' => [
                    'nama_santri' => $santri->nama ?? '-',
                    'label' => $eskalasi->label,
                    'total_poin' => (string) $eskalasi->poin,
                    'periode' => $eskalasi->periode,
                    'nama_pesantren' => $this->namaPesantren(),
                ],
            ]);
        });
    }

    /** @return array<int,string> Nomor WA wali ternormalisasi 62…. */
    private function waliPhones(?object $santri): array
    {
        $normalized = PhoneNumber::normalize($santri->no_hp_wali ?? null);

        return $normalized === '' ? [] : [$normalized];
    }

    private function namaPesantren(): string
    {
        return Setting::getTenantStringValue('branding_name') ?? (tenant('name') ?? 'Pesantren');
    }

    /** Pagar mutlak: notifikasi tidak boleh menggagalkan alur disiplin. */
    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
