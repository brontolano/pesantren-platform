<?php

namespace Modules\Pembiayaan\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Concerns\Auditable;

class Aset extends Model
{
    use Auditable, BelongsToTenant, SoftDeletes;

    protected $table = 'aset';

    /** Metode penyusutan yang didukung. */
    public const METODE = [
        'none' => 'Tanpa Penyusutan (tanah/wakaf)',
        'garis_lurus' => 'Garis Lurus',
        'saldo_menurun' => 'Saldo Menurun Ganda',
    ];

    protected $fillable = [
        'tenant_id', 'nama_aset', 'jenis', 'tanggal_perolehan', 'nilai', 'nilai_perolehan',
        'lokasi', 'kategori', 'metode_penyusutan', 'nilai_residu', 'umur_manfaat_bulan',
        'akumulasi_penyusutan', 'status_aset', 'sumber_dana_id', 'is_wakaf', 'nomor_sertifikat',
    ];

    protected $casts = [
        'tanggal_perolehan' => 'date',
        'nilai' => 'decimal:2',
        'nilai_perolehan' => 'decimal:2',
        'nilai_residu' => 'decimal:2',
        'akumulasi_penyusutan' => 'decimal:2',
        'umur_manfaat_bulan' => 'integer',
        'is_wakaf' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Invarian syariah: aset wakaf TIDAK disusutkan (UU 41/2004) — paksa
        // metode 'none' di SEMUA jalur tulis (create/update/import/API), bukan
        // hanya form. Menutup celah is_wakaf & metode_penyusutan tak sinkron.
        static::saving(function (self $aset): void {
            if ($aset->is_wakaf) {
                $aset->metode_penyusutan = 'none';
            }
            // Basis penyusutan = harga perolehan yang STABIL. Bila belum diisi
            // (default migrasi/form = 0), turunkan SEKALI dari nilai awal agar tidak
            // ikut termutasi tiap posting — mencegah nilai buku negatif & akumulasi
            // overshoot saat depresiasi diposting berkali-kali. Setelah terisi (>0),
            // tidak pernah diubah otomatis (= harga perolehan historis).
            if ((float) $aset->nilai_perolehan <= 0 && (float) $aset->nilai > 0) {
                $aset->nilai_perolehan = $aset->nilai;
            }
        });
    }

    public function scopeNonWakaf($query)
    {
        return $query->where('is_wakaf', false);
    }

    public function jadwalPenyusutan(): HasMany
    {
        return $this->hasMany(JadwalPenyusutan::class, 'aset_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiAset::class, 'aset_id');
    }

    /**
     * Nilai buku berjalan = nilai_perolehan − akumulasi_penyusutan.
     * Fallback ke `nilai` bila nilai_perolehan belum diisi (data lama).
     */
    public function nilaiBuku(): float
    {
        $dasar = (float) ($this->nilai_perolehan ?: $this->nilai);

        return round($dasar - (float) $this->akumulasi_penyusutan, 2);
    }

    /** Aset boleh disusutkan? (bukan wakaf, metode != none, ada umur manfaat). */
    public function bolehDisusutkan(): bool
    {
        return ! $this->is_wakaf
            && $this->metode_penyusutan !== 'none'
            && (int) $this->umur_manfaat_bulan > 0;
    }
}
