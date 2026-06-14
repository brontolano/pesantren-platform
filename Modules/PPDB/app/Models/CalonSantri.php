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
        'nama_panggilan',
        'nik',
        'nisn',
        'agama',
        'cita_cita',
        'anak_ke',
        'jumlah_saudara',
        'photo_url',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        // Alamat / domisili santri (cascade wilayah)
        'alamat',
        'rt_rw',
        'desa_kelurahan',
        'kecamatan',
        'kabupaten_kota',
        'provinsi',
        'kode_pos',
        // Riwayat pendidikan
        'asal_sekolah',
        'jenjang_asal',
        'npsn',
        'tahun_lulus',
        // Data ayah
        'nama_ayah',
        'nik_ayah',
        'pendidikan_ayah',
        'pekerjaan_ayah',
        'penghasilan_ayah',
        'no_hp_ayah',
        // Data ibu
        'nama_ibu',
        'nik_ibu',
        'pendidikan_ibu',
        'pekerjaan_ibu',
        'penghasilan_ibu',
        'no_hp_ibu',
        // Data wali (akun portal)
        'nama_wali',
        'hubungan_wali',
        'pekerjaan_wali',
        'penghasilan_wali',
        'no_hp_wali',
        'email_wali',
        // Kesehatan
        'golongan_darah',
        'tinggi_badan',
        'berat_badan',
        'riwayat_penyakit',
        // Administratif
        'program_diminati',
        'tahun_ajaran',
        'gelombang',
        'jalur',
        'asal_pondok',
        'status_pendaftaran',
        'catatan_keputusan',
        'tanggal_keputusan',
        'tanggal_registrasi_ulang',
        'data_ortu',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'data_ortu' => 'array',
        'anak_ke' => 'integer',
        'jumlah_saudara' => 'integer',
        'tinggi_badan' => 'integer',
        'berat_badan' => 'integer',
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
