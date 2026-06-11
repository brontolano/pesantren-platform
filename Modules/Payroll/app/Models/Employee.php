<?php

namespace Modules\Payroll\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Concerns\Auditable;
use Modules\Payroll\Observers\EmployeeObserver;

/**
 * G2-10 — Master Pegawai/Employee. Tertaut user (untuk slip gaji) + struktur
 * gaji konfigurabel (komponen_gaji). WP-03: observer memindahkan role
 * turunan penugasan saat users_id diganti.
 */
#[ObservedBy(EmployeeObserver::class)]
class Employee extends Model
{
    use Auditable, BelongsToTenant;

    protected $table = 'employee';

    /**
     * WP-04 — Satu-satunya sumber kebenaran daftar status kepegawaian
     * (EMIS-ready). Kolom DB string bebas (tanpa CHECK) — validasi app-level:
     * form & filter WAJIB membaca konstanta ini, jangan hardcode.
     *
     * Map nilai lama (migrasi 2026_06_25_100000): tetap→gty,
     * honorer→gtt_honorer, kontrak→kontrak.
     *
     * @var array<string, string> nilai => label tampilan
     */
    public const STATUS_KEPEGAWAIAN = [
        'gty' => 'GTY (Guru Tetap Yayasan)',
        'gtt_honorer' => 'GTT / Honorer',
        'pns_dpk' => 'PNS DPK',
        'pppk' => 'PPPK',
        'pengabdian' => 'Pengabdian',
        'kontrak' => 'Kontrak',
    ];

    /** Label tampilan status; nilai tak dikenal (data lama) tampil apa adanya. */
    public static function labelStatusKepegawaian(?string $status): string
    {
        return self::STATUS_KEPEGAWAIAN[$status] ?? (string) $status;
    }

    protected $fillable = [
        'tenant_id', 'users_id', 'nip', 'nama', 'jabatan', 'status_kepegawaian',
        'npwp', 'ptkp_status', 'tanggal_masuk', 'aktif',
        'nik', 'nuptk', 'nrg', 'is_mukim', 'tanggal_selesai', 'pendidikan_terakhir', 'asal_alumni',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'tanggal_selesai' => 'date',
        'aktif' => 'boolean',
        'is_mukim' => 'boolean',
        'asal_alumni' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function komponenGaji(): HasMany
    {
        return $this->hasMany(KomponenGaji::class, 'employee_id');
    }

    /**
     * WP-03 — Riwayat penugasan multi-peran (pengganti kolom `jabatan` string
     * yang deprecated). Aktif = tanggal_selesai NULL.
     */
    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanPegawai::class, 'employee_id');
    }

    /** WP-18 — Arsip sertifikasi (TPG/Inpassing/GBPNS/PPG). */
    public function sertifikasi(): HasMany
    {
        return $this->hasMany(SertifikasiPegawai::class, 'employee_id');
    }

    /**
     * Jabatan aktif untuk tampilan: prioritas penugasan is_primary aktif,
     * fallback penugasan aktif termuda. NULL bila belum ada penugasan.
     */
    public function jabatanAktif(): ?Jabatan
    {
        $penugasan = $this->penugasan()
            ->whereNull('tanggal_selesai')
            ->orderByDesc('is_primary')
            ->orderByDesc('tanggal_mulai')
            ->with('jabatan')
            ->first();

        return $penugasan?->jabatan;
    }

    /**
     * Resolusi struktur gaji → honor_pokok (Σtetap), tunjangan (Σtunjangan),
     * potongan (Σpotongan). Hanya komponen aktif.
     *
     * @return array{honor_pokok:float, tunjangan:float, potongan:float}
     */
    public function strukturGaji(): array
    {
        $komponen = $this->relationLoaded('komponenGaji') ? $this->komponenGaji : $this->komponenGaji()->get();
        $aktif = $komponen->where('aktif', true);

        return [
            'honor_pokok' => round((float) $aktif->where('tipe', 'tetap')->sum('nominal'), 2),
            'tunjangan' => round((float) $aktif->where('tipe', 'tunjangan')->sum('nominal'), 2),
            'potongan' => round((float) $aktif->where('tipe', 'potongan')->sum('nominal'), 2),
        ];
    }
}
