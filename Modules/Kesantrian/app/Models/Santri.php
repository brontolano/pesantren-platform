<?php

namespace Modules\Kesantrian\Models;

use App\Models\AccessWali;
use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Asrama\Models\BiayaAsrama;
use Modules\Asrama\Models\PenempatanAsrama;
use Modules\Core\Concerns\HasDocuments;
use Modules\Keuangan\Models\Tagihan;

class Santri extends Model
{
    use BelongsToTenant, HasDocuments, SoftDeletes;

    protected $table = 'santri';

    protected $fillable = [
        'tenant_id',
        'nis',
        'nama',
        'tanggal_lahir',
        'tempat_lahir',
        'jenis_kelamin',
        'agama',
        'nik',
        'nisn',
        'nama_panggilan',
        'cita_cita',
        'anak_ke',
        'jumlah_saudara',
        'asal_sekolah',
        'jenjang_asal',
        'npsn',
        'tahun_lulus',
        'alamat',
        'rt_rw',
        'desa_kelurahan',
        'kecamatan',
        'kabupaten_kota',
        'provinsi',
        'kode_pos',
        // Data wali (penanggung jawab) + akun portal
        'nama_wali',
        'hubungan_wali',
        'pekerjaan_wali',
        'penghasilan_wali',
        'pembiaya',
        'no_hp_wali',
        'alamat_wali',
        'rt_rw_wali',
        'desa_kelurahan_wali',
        'kecamatan_wali',
        'kabupaten_kota_wali',
        'provinsi_wali',
        'kode_pos_wali',
        'email_wali',
        'nama_ayah',
        'nama_ibu',
        'nik_ayah',
        'nik_ibu',
        'pendidikan_ayah',
        'pendidikan_ibu',
        'pekerjaan_ayah',
        'pekerjaan_ibu',
        'penghasilan_ayah',
        'penghasilan_ibu',
        // Kontak & alamat terpisah ayah/ibu (no_hp_wali = akun utama).
        'no_hp_ayah',
        'no_hp_ibu',
        'alamat_ayah',
        'rt_rw_ayah',
        'desa_kelurahan_ayah',
        'kecamatan_ayah',
        'kabupaten_kota_ayah',
        'provinsi_ayah',
        'kode_pos_ayah',
        'alamat_ibu',
        'rt_rw_ibu',
        'desa_kelurahan_ibu',
        'kecamatan_ibu',
        'kabupaten_kota_ibu',
        'provinsi_ibu',
        'kode_pos_ibu',
        // Stasiun Pendaftaran (Fase 0)
        'nis_urut',
        'nfc_uid',
        'signature_path',
        'signature_svg_path',
        'consent_ai_dataset',
        'consented_at',
        'consented_by',
        // Kesehatan
        'golongan_darah',
        'tinggi_badan',
        'berat_badan',
        'riwayat_penyakit',
        'status',
        'is_mukim',
        'tahun_angkatan',
        'kelas_id',
        'photo_url',
        'wali_user_id',
        'user_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'is_mukim' => 'boolean',
        'anak_ke' => 'integer',
        'jumlah_saudara' => 'integer',
        'tinggi_badan' => 'integer',
        'berat_badan' => 'integer',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /**
     * Peran yang boleh melihat FOTO santri perempuan (privasi syar'i).
     * Foto santri laki-laki tampil untuk semua peran yang berhak akses santri;
     * foto santri perempuan HANYA untuk pimpinan pondok & super admin — peran
     * lain melihat avatar inisial nama.
     *
     * @var list<string>
     */
    public const PERAN_LIHAT_FOTO_PUTRI = ['super_admin', 'kepala', 'dewan_pembina'];

    /**
     * Apakah foto ASLI santri ini boleh ditampilkan ke pengguna saat ini?
     * - Santri laki-laki → selalu boleh (untuk yang berhak akses santri).
     * - Santri perempuan → hanya pimpinan pondok / super admin.
     */
    public function fotoDapatDilihat(): bool
    {
        if ($this->jenis_kelamin !== 'P') {
            return true;
        }

        return auth()->user()?->hasRole(self::PERAN_LIHAT_FOTO_PUTRI) ?? false;
    }

    /**
     * URL avatar inisial nama (pengganti foto bila tak boleh dilihat / kosong).
     * Warna emerald (Banin/umum) atau rose (Banat) agar mudah dibedakan.
     */
    public function avatarInisialUrl(): string
    {
        $warna = $this->jenis_kelamin === 'P' ? 'be185d' : '047857';

        return 'https://ui-avatars.com/api/?background='.$warna.'&color=fff&bold=true&name='
            .urlencode($this->nama ?? 'Santri');
    }

    /**
     * URL foto yang AMAN ditampilkan ke pengguna saat ini:
     * foto asli (via /_tenant-storage) bila boleh dilihat & ada,
     * selain itu avatar inisial. Dipakai permukaan non-Filament
     * (portal guru gerbang, loket pendaftaran, dsb).
     */
    public function urlFotoAman(): string
    {
        if ($this->photo_url && $this->fotoDapatDilihat()) {
            return url('/_tenant-storage/'.ltrim($this->photo_url, '/'));
        }

        return $this->avatarInisialUrl();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SantriEnrollment::class, 'santri_id');
    }

    public function handwritingSamples(): HasMany
    {
        return $this->hasMany(HandwritingSample::class, 'santri_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function waliUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_user_id');
    }

    public function accessWali(): HasMany
    {
        return $this->hasMany(AccessWali::class, 'santri_id');
    }

    public function waliPortal(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_wali', 'santri_id', 'wali_id')
            ->withPivot(['tenant_id', 'relationship_type', 'is_primary'])
            ->withTimestamps();
    }

    public function penempatanKelas(): HasMany
    {
        return $this->hasMany(PenempatanKelas::class, 'santri_id');
    }

    public function penempatanAsrama(): HasMany
    {
        return $this->hasMany(PenempatanAsrama::class, 'santri_id');
    }

    public function penempatanAsramaAktif(): HasOne
    {
        return $this->hasOne(PenempatanAsrama::class, 'santri_id')
            ->where('is_current', true)
            ->latestOfMany('tanggal_masuk');
    }

    public function biayaAsrama(): HasMany
    {
        return $this->hasMany(BiayaAsrama::class, 'santri_id');
    }

    /** #338: jenjang formal kesetaraan (ula/wustha/ulya) diturunkan dari kelas aktif. */
    public function getJenjangKesetaraanAttribute(): ?string
    {
        return $this->kelas?->jenjang_kesetaraan;
    }

    /** #338: tingkat kesetaraan (Ula 1-6, Wustha 1-3, Ulya 1-3) dari kelas aktif. */
    public function getTingkatKesetaraanAttribute(): ?int
    {
        return $this->kelas?->tingkat_kesetaraan;
    }

    /** #338: label ringkas jenjang formal, mis. "Ula 3"; null bila belum terpetakan. */
    public function getLabelKesetaraanAttribute(): ?string
    {
        return $this->kelas?->label_kesetaraan;
    }

    public function penempatanAktif(): HasOne
    {
        return $this->hasOne(PenempatanKelas::class, 'santri_id')
            ->where('is_current', true)
            ->latestOfMany('tanggal_penempatan');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusSantri::class, 'santri_id')->orderByDesc('tanggal_status');
    }

    public function alumni(): HasOne
    {
        return $this->hasOne(Alumni::class, 'santri_id');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class, 'santri_id');
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }
}
