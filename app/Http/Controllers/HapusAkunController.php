<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\PenghapusanAkun;
use App\Support\TenantBranding;
use Illuminate\View\View;

/**
 * Halaman publik "Penghapusan Akun & Data" (/hapus-akun) — wajib Google Play.
 *
 * Domain TENANT (mis. asy-syifaa.pesantren.online) → halaman bermerek + redaksi
 * yang bisa diedit pesantren ({@see PenghapusanAkun}) + kanal kontak tenant.
 * Central domain (pesantren.online) → versi generik platform. Pola mengikuti
 * route /kebijakan-privasi.
 */
class HapusAkunController extends Controller
{
    public function __invoke(): View
    {
        if (tenancy()->initialized && tenant() !== null) {
            return view('tenant.hapus-akun', [
                'nama' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
                'isi' => PenghapusanAkun::isi(),
                'kontak' => $this->kontakTenant(),
            ]);
        }

        return view('legal.account-deletion');
    }

    /**
     * Kanal yang bisa dipakai pengguna untuk meminta penghapusan, diambil dari
     * pengaturan tenant bila ada. Semua opsional — hanya yang terisi tampil.
     *
     * @return array{whatsapp:?string, whatsapp_link:?string, email:?string, telepon:?string, alamat:?string}
     */
    private function kontakTenant(): array
    {
        $contact = [];
        try {
            $raw = \Modules\CMS\Models\WebsiteSetting::getTenantValue('contact', []);
            $contact = is_array($raw) ? $raw : [];
        } catch (\Throwable) {
            $contact = [];
        }

        $wa = null;
        try {
            $wa = Setting::getTenantStringValue('admission_admin_whatsapp');
        } catch (\Throwable) {
            $wa = null;
        }
        $wa = $this->bersih($wa);

        return [
            'whatsapp' => $wa,
            'whatsapp_link' => $this->waLink($wa),
            'email' => $this->bersih($contact['email'] ?? null),
            'telepon' => $this->bersih($contact['phone'] ?? null),
            'alamat' => $this->bersih($contact['address'] ?? null),
        ];
    }

    private function bersih(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /** 08xx / +62xx / 62xx → tautan https://wa.me/62xxxxxxxxxx. */
    private function waLink(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }
}
