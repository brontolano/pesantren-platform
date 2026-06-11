<?php

namespace Modules\Akademik\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Akademik\Database\Factories\KitabFactory;

class Kitab extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'kitab';

    protected $fillable = [
        'tenant_id',
        'nama',
        'pengarang',
        'kategori',
        'jumlah_halaman',
    ];

    protected $casts = [
        'jumlah_halaman' => 'integer',
    ];

    protected static function newFactory(): KitabFactory
    {
        return KitabFactory::new();
    }

    public function kajian(): HasMany
    {
        return $this->hasMany(KajianKitab::class, 'kitab_id');
    }
}
