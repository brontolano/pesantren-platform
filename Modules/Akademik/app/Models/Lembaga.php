<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Akademik\Database\Factories\LembagaFactory;
use Modules\Kesantrian\Models\Kelas;
use Modules\Ledger\Models\DivisiUnit;

/**
 * WP-01 — Lembaga: payung formal/diniyah/tahfidz/pondok per tenant.
 * Lembaga "Pondok" (jenis pondok) adalah default — kelas/periode dengan
 * lembaga_id NULL diperlakukan setara lembaga default (data lama).
 */
class Lembaga extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const JENIS = ['formal', 'diniyah', 'tahfidz', 'pondok', 'mahad_aly', 'unit_usaha'];

    protected $table = 'lembaga';

    protected $fillable = [
        'tenant_id',
        'nama',
        'jenis',
        'jenjang',
        'nomor_statistik',
        'izin_operasional',
        'tanggal_izin',
        'kepala_id',
        'divisi_unit_id',
        'aktif',
    ];

    protected $casts = [
        'tanggal_izin' => 'date',
        'aktif' => 'boolean',
    ];

    protected static function newFactory(): LembagaFactory
    {
        return LembagaFactory::new();
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'lembaga_id');
    }

    public function periodeAkademik(): HasMany
    {
        return $this->hasMany(PeriodeAkademik::class, 'lembaga_id');
    }

    public function divisiUnit(): BelongsTo
    {
        return $this->belongsTo(DivisiUnit::class, 'divisi_unit_id');
    }

    public function kepala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kepala_id');
    }
}
