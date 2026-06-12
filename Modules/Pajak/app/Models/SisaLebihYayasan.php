<?php

namespace Modules\Pajak\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;

class SisaLebihYayasan extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'sisa_lebih_yayasan';

    protected $fillable = [
        'tenant_id', 'tahun_fiskal', 'surplus_pendidikan',
        'reinvestasi_teralokasi', 'batas_reinvestasi', 'status', 'catatan',
    ];

    protected $casts = [
        'surplus_pendidikan' => 'decimal:2',
        'reinvestasi_teralokasi' => 'decimal:2',
        'batas_reinvestasi' => 'date',
    ];

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiReinvestasi::class, 'sisa_lebih_id');
    }

    public function sisaBelumTeralokasi(): float
    {
        return round((float) $this->surplus_pendidikan - (float) $this->reinvestasi_teralokasi, 2);
    }

    public function hariBatasWaktu(): int
    {
        return (int) now()->diffInDays($this->batas_reinvestasi, false);
    }

    public function sudahLewatBatas(): bool
    {
        return now()->isAfter($this->batas_reinvestasi);
    }
}
