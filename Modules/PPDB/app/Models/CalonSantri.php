<?php

namespace Modules\PPDB\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Kesantrian\Models\Santri;

class CalonSantri extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'calon_santri';

    protected $fillable = [
        'consent_kebijakan_at',
        'tenant_id',
        'santri_id',
        'user_id',
        'nomor_pendaftaran',
        'tracking_token',
        'tracking_token_generated_at',
        'nama',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'nama_ayah',
        'nama_ibu',
        'no_hp_wali',
        'email_wali',
        'alamat',
        'asal_sekolah',
        'program_diminati',
        'tahun_ajaran',
        'gelombang',
        'status_pendaftaran',
        'catatan_keputusan',
        'tanggal_keputusan',
        'tanggal_registrasi_ulang',
        'data_ortu',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'data_ortu' => 'array',
        'tracking_token_generated_at' => 'datetime',
        'tanggal_keputusan' => 'datetime',
        'tanggal_registrasi_ulang' => 'datetime',
    ];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class, 'santri_id');
    }

    /** Akun peserta PWA (user role `peserta`) — penghubung F0.3. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(CalonSantriDokumen::class, 'calon_santri_id');
    }

    public function tagihanPpdb(): HasMany
    {
        return $this->hasMany(TagihanPpdb::class, 'calon_santri_id');
    }

    public function hasilUjian(): HasMany
    {
        return $this->hasMany(HasilUjian::class, 'calon_santri_id');
    }
}
