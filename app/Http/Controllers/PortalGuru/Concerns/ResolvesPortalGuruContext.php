<?php

declare(strict_types=1);

namespace App\Http\Controllers\PortalGuru\Concerns;

use App\Models\User;
use App\Services\PortalGuru\PortalGuruBrandingService;
use Illuminate\Http\Request;
use Modules\Tahfidz\Models\Halaqah;

trait ResolvesPortalGuruContext
{
    /** Nama hari (kolom jadwal_pelajaran.hari, huruf kecil) untuk tanggal sekarang. */
    public static function hariIni(): string
    {
        return ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'][now()->dayOfWeek];
    }

    protected function currentGuru(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** Halaqah aktif yang dibina guru ini (musyrif). */
    protected function halaqahKu(User $guru)
    {
        return Halaqah::query()
            ->where('musyrif_id', $guru->id)
            ->where('aktif', true)
            ->orderBy('nama')
            ->get();
    }

    /**
     * Props bersama semua halaman PWA Guru: branding tenant + label istilah
     * per tenant (WP-31) + identitas guru.
     *
     * @return array<string, mixed>
     */
    protected function sharedProps(Request $request): array
    {
        $guru = $this->currentGuru($request);

        return [
            'guru' => [
                'id' => $guru->id,
                'name' => $guru->name,
                'email' => $guru->email,
            ],
            'branding' => app(PortalGuruBrandingService::class)->resolve(),
            'istilah' => [
                'musyrif' => istilah('musyrif', 'Musyrif'),
                'halaqah' => istilah('halaqah', 'Halaqah'),
                'santri' => istilah('santri', 'Santri'),
                'ustadz' => istilah('ustadz', 'Ustadz'),
            ],
        ];
    }
}
