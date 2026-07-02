<?php

namespace Modules\Kesantrian\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Akademik\Models\Lembaga;

class Kelas extends Model
{
    use BelongsToTenant;

    /**
     * Jenjang kesetaraan PKPPS/SPM (#338): kunci = nilai kolom,
     * nilai = jumlah tingkat (Ula ≈ SD 6 th, Wustha ≈ SMP 3 th, Ulya ≈ SMA 3 th).
     */
    public const JENJANG_KESETARAAN = ['ula' => 6, 'wustha' => 3, 'ulya' => 3];

    protected $table = 'kelas';

    protected $fillable = [
        'tenant_id',
        'nama_kelas',
        'tingkat',
        'jenjang_kesetaraan',
        'tingkat_kesetaraan',
        'jurusan',
        'capacity',
        'wali_kelas_id',
        'lembaga_id',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'tingkat_kesetaraan' => 'integer',
    ];

    protected static function booted(): void
    {
        // Default #338: turunkan jenjang kesetaraan dari Lembaga.jenjang bila terisi.
        static::creating(function (Kelas $kelas): void {
            if ($kelas->jenjang_kesetaraan === null && $kelas->lembaga_id !== null) {
                $kelas->jenjang_kesetaraan = self::normalisasiJenjang(
                    Lembaga::query()->whereKey($kelas->lembaga_id)->value('jenjang')
                );
            }
        });
    }

    /** Normalisasi jenjang bebas ("Ula", "WUSTHA") → kunci baku, selain itu null. */
    public static function normalisasiJenjang(?string $jenjang): ?string
    {
        $j = strtolower(trim((string) $jenjang));

        return array_key_exists($j, self::JENJANG_KESETARAAN) ? $j : null;
    }

    /** Opsi Select Filament: ['ula' => 'Ula', ...]. */
    public static function opsiJenjangKesetaraan(): array
    {
        return collect(self::JENJANG_KESETARAAN)
            ->mapWithKeys(fn (int $n, string $k) => [$k => ucfirst($k)])
            ->all();
    }

    /** Label ringkas: "Ula 3", "Wustha 1", atau null bila belum terpetakan. */
    public function getLabelKesetaraanAttribute(): ?string
    {
        if ($this->jenjang_kesetaraan === null) {
            return null;
        }

        return trim(ucfirst($this->jenjang_kesetaraan).' '.($this->tingkat_kesetaraan ?? ''));
    }

    /** WP-01: NULL = lembaga default tenant (data lama tetap sah). */
    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class, 'lembaga_id');
    }

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    public function santri(): HasMany
    {
        return $this->hasMany(Santri::class, 'kelas_id');
    }

    public function penempatanKelas(): HasMany
    {
        return $this->hasMany(PenempatanKelas::class, 'kelas_id');
    }

    public function jumlahSantriAktif(): int
    {
        return $this->santri()->where('status', 'aktif')->count();
    }

    public function sisaKapasitas(): int
    {
        return $this->capacity - $this->jumlahSantriAktif();
    }
}
