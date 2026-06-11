<?php

namespace Modules\Kesantrian\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenempatanKelas extends Model
{
    use BelongsToTenant;

    protected $table = 'penempatan_kelas';

    protected $fillable = [
        'tenant_id',
        'santri_id',
        'kelas_id',
        'semester',
        'tahun_akademik',
        'tanggal_penempatan',
        'alasan_mutasi',
        'is_current',
    ];

    protected $casts = [
        'tanggal_penempatan' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * WP-05 — Sinkronisasi PenempatanKelas → Santri.kelas_id.
     *
     * Semua mutasi di dalam hook memakai QUERY BUILDER (bukan model event)
     * sehingga TIDAK memicu event Eloquent lain → bebas rekursi observer.
     */
    protected static function booted(): void
    {
        // PENTING: nonaktifkan penempatan aktif lain SEBELUM insert/update
        // baris ini (event saving, bukan saved). Partial unique index
        // (santri_id WHERE is_current) menolak insert bila penempatan aktif
        // lama belum dinonaktifkan lebih dulu.
        static::saving(function (self $penempatan): void {
            if (! $penempatan->is_current) {
                return;
            }

            if ($penempatan->exists
                && ! $penempatan->isDirty(['is_current', 'santri_id'])) {
                return;
            }

            static::query()
                ->where('santri_id', $penempatan->santri_id)
                ->when($penempatan->exists, fn ($q) => $q->where('id', '!=', $penempatan->getKey()))
                ->where('is_current', true)
                ->update(['is_current' => false]);
        });

        static::saved(function (self $penempatan): void {
            if ($penempatan->is_current) {
                // Lewati bila tidak ada perubahan relevan (hindari query
                // sia-sia saat kolom lain di-update, mis. alasan_mutasi).
                if (! $penempatan->wasRecentlyCreated
                    && ! $penempatan->wasChanged(['is_current', 'kelas_id', 'santri_id'])) {
                    return;
                }

                // Sinkronkan kelas santri (idempoten: hanya bila berbeda).
                Santri::query()
                    ->whereKey($penempatan->santri_id)
                    ->where(function ($query) use ($penempatan) {
                        $query->whereNull('kelas_id')
                            ->orWhere('kelas_id', '!=', $penempatan->kelas_id);
                    })
                    ->update(['kelas_id' => $penempatan->kelas_id]);

                return;
            }

            // Penempatan aktif dinonaktifkan manual (mis. EditAction tabel):
            // perlakuan konsisten dengan delete — jatuhkan ke pengganti/NULL.
            if ($penempatan->wasChanged('is_current')) {
                static::jatuhkanKelasSantri((int) $penempatan->santri_id);
            }
        });

        static::deleted(function (self $penempatan): void {
            if (! $penempatan->is_current) {
                return;
            }

            static::jatuhkanKelasSantri((int) $penempatan->santri_id);
        });
    }

    /**
     * Santri kehilangan penempatan aktifnya: jatuhkan kelas_id ke penempatan
     * aktif lain bila ada; bila tidak ada, NULL-kan — santri tanpa penempatan
     * aktif tidak boleh tetap tampil di roster kelas lama.
     */
    protected static function jatuhkanKelasSantri(int $santriId): void
    {
        $pengganti = static::query()
            ->where('santri_id', $santriId)
            ->where('is_current', true)
            ->orderByDesc('tanggal_penempatan')
            ->orderByDesc('id')
            ->first();

        Santri::query()
            ->whereKey($santriId)
            ->update(['kelas_id' => $pengganti?->kelas_id]);
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }
}
