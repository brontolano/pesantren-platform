<?php

namespace Modules\Kegiatan\Models;

use App\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Kesantrian\Models\Santri;

class IzinSantri extends Model
{
    use BelongsToTenant;

    protected $table = 'izin_santri';

    protected $fillable = [
        'tenant_id', 'santri_id', 'jenis_izin',
        'tanggal_mulai', 'tanggal_selesai', 'alasan',
        'status', 'disetujui_oleh', 'catatan_pengurus',
        'sudah_kembali', 'tanggal_kembali_aktual', 'client_ref',
    ];

    protected $casts = [
        'tanggal_mulai'          => 'date',
        'tanggal_selesai'        => 'date',
        'tanggal_kembali_aktual' => 'datetime',
        'sudah_kembali'          => 'boolean',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function durasiHari(): int
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }
}
