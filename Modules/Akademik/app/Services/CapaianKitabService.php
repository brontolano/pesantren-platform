<?php

namespace Modules\Akademik\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Models\CapaianKitab;
use Modules\Akademik\Models\KajianKitab;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * F2-PWA — Capaian kajian kitab.
 *
 * Sorogan  : capaian PER SANTRI (santri_id wajib & harus peserta kajian).
 * Bandongan/wetonan: SATU baris posisi KELOMPOK (santri_id NULL, skema F0)
 * — satu update berlaku untuk semua peserta, TIDAK menulis baris per santri.
 * Riwayat = append (bukan upsert) — posisi berjalan maju/ulang per pertemuan.
 */
class CapaianKitabService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function catat(KajianKitab $kajian, array $data, User $actor): CapaianKitab
    {
        $this->ensureTenantContext();

        Validator::make($data, [
            'santri_id' => ['nullable', 'integer'],
            'posisi' => ['required', 'string', 'max:255'],
            'tanggal' => ['nullable', 'date', 'before_or_equal:'.now()->toDateString()],
            'status' => ['required', 'in:lanjut,ulang,khatam'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $santriId = null;

        if ($kajian->metode === 'sorogan') {
            $santriId = (int) ($data['santri_id'] ?? 0);

            if ($santriId <= 0) {
                throw ValidationException::withMessages([
                    'santri_id' => 'Sorogan dicatat per santri — pilih santri.',
                ]);
            }

            if (! $this->pesertaIds($kajian)->contains($santriId)) {
                throw ValidationException::withMessages([
                    'santri_id' => 'Santri bukan peserta kajian ini.',
                ]);
            }
        } elseif (! empty($data['santri_id'])) {
            throw ValidationException::withMessages([
                'santri_id' => 'Bandongan/wetonan dicatat sebagai posisi kelompok — tanpa santri.',
            ]);
        }

        return CapaianKitab::create([
            'kajian_kitab_id' => $kajian->id,
            'santri_id' => $santriId,
            'posisi' => $data['posisi'],
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'status' => $data['status'],
            'catatan' => $data['catatan'] ?? null,
            'dicatat_oleh' => $actor->id,
        ]);
    }

    /** Peserta kajian: anggota halaqah aktif ATAU santri aktif kelas terkait. */
    public function peserta(KajianKitab $kajian): Collection
    {
        if ($kajian->halaqah_id !== null && $kajian->halaqah) {
            return $kajian->halaqah->santriAktif()->orderBy('nama')->get()->toBase();
        }

        if ($kajian->kelas_id !== null) {
            return Santri::query()
                ->where('kelas_id', $kajian->kelas_id)
                ->where('status', 'aktif')
                ->orderBy('nama')
                ->get()
                ->toBase();
        }

        return collect();
    }

    /** @return Collection<int, int> */
    public function pesertaIds(KajianKitab $kajian): Collection
    {
        return $this->peserta($kajian)->pluck('id');
    }

    /**
     * Riwayat singkat (terbaru dulu). $santriId null = riwayat kelompok.
     */
    public function riwayat(KajianKitab $kajian, ?int $santriId = null, int $limit = 10): Collection
    {
        return CapaianKitab::query()
            ->where('kajian_kitab_id', $kajian->id)
            ->when(
                $santriId !== null,
                fn ($q) => $q->where('santri_id', $santriId),
                fn ($q) => $q->whereNull('santri_id'),
            )
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toBase();
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif untuk capaian kitab.');
        }
    }
}
