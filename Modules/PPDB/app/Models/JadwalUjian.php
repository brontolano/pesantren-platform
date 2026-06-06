<?php

namespace Modules\PPDB\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal ujian PPDB per kategori_ujian (selaras dgn SoalUjian/HasilUjian).
 * Sumber data jadwal pada kartu peserta digital.
 */
class JadwalUjian extends Model
{
    use BelongsToTenant;

    protected $table = 'jadwal_ujian';

    protected $fillable = [
        'tenant_id',
        'kategori_ujian',
        'tanggal',
        'waktu_mulai',
        'waktu_selesai',
        'ruang',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
