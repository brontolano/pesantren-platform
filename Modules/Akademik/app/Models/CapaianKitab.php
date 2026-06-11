<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Akademik\Database\Factories\CapaianKitabFactory;
use Modules\Core\Concerns\Auditable;
use Modules\Kesantrian\Models\Santri;

/**
 * Capaian kajian kitab. santri_id NULL = posisi KELOMPOK (bandongan/wetonan);
 * terisi = capaian per santri (sorogan).
 */
class CapaianKitab extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'capaian_kitab';

    protected $fillable = [
        'tenant_id',
        'kajian_kitab_id',
        'santri_id',
        'posisi',
        'tanggal',
        'status', // lanjut|ulang|khatam
        'catatan',
        'dicatat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    protected static function newFactory(): CapaianKitabFactory
    {
        return CapaianKitabFactory::new();
    }

    public function kajianKitab(): BelongsTo
    {
        return $this->belongsTo(KajianKitab::class, 'kajian_kitab_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    /** Capaian kelompok (bandongan/wetonan) — bukan per santri. */
    public function isKelompok(): bool
    {
        return $this->santri_id === null;
    }
}
