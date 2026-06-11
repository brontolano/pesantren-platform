<?php

namespace Modules\Akademik\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Modules\Akademik\Database\Factories\JurnalMengajarFactory;
use Modules\Core\Concerns\Auditable;
use Modules\Tahfidz\Models\Halaqah;

/**
 * Berita acara KBM. Konteks: jadwal_pelajaran_id XOR halaqah_id —
 * dijaga di model (ValidationException) + CHECK constraint di DB.
 */
class JurnalMengajar extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'jurnal_mengajar';

    protected $fillable = [
        'tenant_id',
        'guru_id',
        'jadwal_pelajaran_id',
        'halaqah_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'materi',
        'metode',
        'jumlah_hadir',
        'status', // terlaksana|kosong|diganti
        'pengganti_id',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah_hadir' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $jurnal): void {
            $punyaJadwal = $jurnal->jadwal_pelajaran_id !== null;
            $punyaHalaqah = $jurnal->halaqah_id !== null;

            if ($punyaJadwal === $punyaHalaqah) {
                throw ValidationException::withMessages([
                    'jadwal_pelajaran_id' => 'Jurnal mengajar harus terkait TEPAT SATU konteks: jadwal pelajaran ATAU halaqah.',
                ]);
            }
        });
    }

    protected static function newFactory(): JurnalMengajarFactory
    {
        return JurnalMengajarFactory::new();
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_pelajaran_id');
    }

    public function halaqah(): BelongsTo
    {
        return $this->belongsTo(Halaqah::class, 'halaqah_id');
    }

    public function pengganti(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengganti_id');
    }
}
