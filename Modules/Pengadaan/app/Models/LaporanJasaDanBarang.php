<?php

namespace Modules\Pengadaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanJasaDanBarang extends Model
{
    use BelongsToTenant;

    protected $table = 'laporan_jasa_dan_barang';

    protected $fillable = [
        'tenant_id', 'belanja_id', 'jenis_laporan',
        'foto_nota', 'foto_barang', 'foto_sebelum', 'foto_sedang', 'foto_sesudah',
        'gps_latitude', 'gps_longitude', 'diambil_pada', 'created_by',
    ];

    protected $casts = [
        'gps_latitude' => 'decimal:7',
        'gps_longitude' => 'decimal:7',
        'diambil_pada' => 'datetime',
    ];

    public function belanja(): BelongsTo
    {
        return $this->belongsTo(Belanja::class, 'belanja_id');
    }
}
