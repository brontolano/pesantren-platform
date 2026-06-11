<?php

namespace Modules\Payroll\Services;

use App\Models\Setting;
use Modules\Payroll\Models\PenugasanPegawai;

/**
 * WP-03 — Render isi SK penugasan dari template ber-placeholder
 * (Blueprint SDM S-0 poin 5).
 *
 * Template per tenant disimpan di Setting key `payroll.template_sk`
 * (teks polos + placeholder) — belum diisi → template bawaan. Placeholder:
 * {{nomor_sk}} {{nama}} {{nip}} {{jabatan}} {{lembaga}} {{tanggal_mulai}}
 * {{tanggal_selesai}} {{pesantren}} {{tanggal_cetak}} {{keterangan}}.
 *
 * Catatan desain: repo BELUM punya model SuratTemplate; pola cetak yang
 * sudah ada adalah route print + blade (slip gaji, BKK, paket kredit).
 * Service ini mengikuti pola tersebut + template tersimpan di Setting
 * (pola branding) agar tenant bisa menyunting tanpa migrasi baru.
 */
class SkPenugasanService
{
    public const SETTING_KEY = 'payroll.template_sk';

    public const DEFAULT_TEMPLATE = <<<'TXT'
SURAT KEPUTUSAN
Nomor: {{nomor_sk}}

Bismillahirrahmanirrahim.

Pimpinan {{pesantren}} dengan ini menugaskan:

Nama     : {{nama}}
NIP      : {{nip}}

sebagai {{jabatan}} pada {{lembaga}} terhitung mulai tanggal {{tanggal_mulai}} sampai dengan {{tanggal_selesai}}.

{{keterangan}}

Surat keputusan ini berlaku sejak tanggal ditetapkan, dengan ketentuan apabila terdapat kekeliruan akan dibetulkan sebagaimana mestinya.

Ditetapkan pada tanggal {{tanggal_cetak}}.

Pimpinan {{pesantren}}
TXT;

    public function render(PenugasanPegawai $penugasan): string
    {
        $penugasan->loadMissing(['employee', 'jabatan', 'lembaga']);

        $template = Setting::getTenantStringValue(self::SETTING_KEY) ?: self::DEFAULT_TEMPLATE;

        return strtr($template, [
            '{{nomor_sk}}' => $penugasan->nomor_sk ?: '.../SK/...',
            '{{nama}}' => $penugasan->employee?->nama ?? '—',
            '{{nip}}' => $penugasan->employee?->nip ?? '—',
            '{{jabatan}}' => $penugasan->jabatan?->nama ?? '—',
            '{{lembaga}}' => $penugasan->lembaga?->nama ?? 'seluruh lembaga (lintas lembaga)',
            '{{tanggal_mulai}}' => $penugasan->tanggal_mulai?->translatedFormat('d F Y') ?? '—',
            '{{tanggal_selesai}}' => $penugasan->tanggal_selesai?->translatedFormat('d F Y') ?? 'waktu yang ditentukan kemudian',
            '{{tanggal_cetak}}' => now()->translatedFormat('d F Y'),
            '{{pesantren}}' => tenant('name') ?? config('app.name'),
            '{{keterangan}}' => $penugasan->keterangan ?? '',
        ]);
    }
}
