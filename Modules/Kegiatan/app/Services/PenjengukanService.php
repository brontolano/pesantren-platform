<?php

namespace Modules\Kegiatan\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Kegiatan\Models\KunjunganWali;
use Modules\Kegiatan\Models\PengajuanJenguk;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * WP-30 — Penjengukan: pengajuan slot jenguk wali + registrasi tamu di pos.
 * Kebijakan per tenant via Setting kesantrian.jadwal_penjengukan (JSON).
 */
class PenjengukanService
{
    private const DEFAULT_JADWAL = [
        'hari' => ['ahad'],
        'jam_mulai' => '08:00',
        'jam_selesai' => '15:00',
        'kuota_per_slot' => 20,
        'konfirmasi' => 'otomatis',
        'walk_in_boleh' => true,
    ];

    private const HARI = ['minggu', 'senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

    /** Parsing toleran setting jadwal — selalu mengembalikan struktur lengkap. */
    public function jadwal(): array
    {
        $raw = Setting::getTenantValue('kesantrian.jadwal_penjengukan');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            $raw = [];
        }

        $jadwal = array_merge(self::DEFAULT_JADWAL, $raw);

        $hari = is_array($jadwal['hari']) ? $jadwal['hari'] : [$jadwal['hari']];
        $jadwal['hari'] = array_values(array_filter(array_map(
            fn ($h): string => $this->normalisasiHari((string) $h),
            $hari,
        )));
        if ($jadwal['hari'] === []) {
            $jadwal['hari'] = self::DEFAULT_JADWAL['hari'];
        }

        $kuota = (int) $jadwal['kuota_per_slot'];
        $jadwal['kuota_per_slot'] = $kuota > 0 ? $kuota : self::DEFAULT_JADWAL['kuota_per_slot'];
        $jadwal['konfirmasi'] = $jadwal['konfirmasi'] === 'manual' ? 'manual' : 'otomatis';
        $jadwal['walk_in_boleh'] = filter_var($jadwal['walk_in_boleh'], FILTER_VALIDATE_BOOLEAN);

        return $jadwal;
    }

    /** 'ahad'/'minggu'/'sunday' → nama hari kanonik (kolom HARI). */
    private function normalisasiHari(string $hari): string
    {
        $h = strtolower(trim($hari));
        $alias = [
            'ahad' => 'minggu', 'sunday' => 'minggu', 'monday' => 'senin',
            'tuesday' => 'selasa', 'wednesday' => 'rabu', 'thursday' => 'kamis',
            'friday' => 'jumat', "jum'at" => 'jumat', 'saturday' => 'sabtu',
        ];
        $h = $alias[$h] ?? $h;

        return in_array($h, self::HARI, true) ? $h : '';
    }

    /**
     * Ajukan slot jenguk. Validasi: wali pemilik anak, hari sesuai jadwal,
     * kuota slot, idempoten client_ref SCOPED pengaju+santri (ref milik
     * orang lain → 409).
     */
    public function ajukan(User $pengaju, array $payload): PengajuanJenguk
    {
        $this->ensureTenantContext();

        $santri = Santri::findOrFail($payload['santri_id']);

        // Wali hanya boleh mengajukan untuk anaknya sendiri (access_wali).
        if (! $pengaju->accessibleSantri()->where('santri.id', $santri->id)->exists()) {
            throw new AccessDeniedHttpException('Anda hanya dapat mengajukan jenguk untuk anak sendiri.');
        }

        $tanggal = Carbon::parse($payload['tanggal'])->startOfDay();
        $jadwal = $this->jadwal();
        $clientRef = $payload['client_ref'] ?? null;

        return DB::transaction(function () use ($pengaju, $santri, $tanggal, $jadwal, $clientRef, $payload): PengajuanJenguk {
            // Serialisasi booking per (tenant, tanggal): lockForUpdate tidak
            // memblokir INSERT phantom — advisory lock transaksional yang bisa.
            $this->kunciSlot($tanggal);

            if ($clientRef !== null && $clientRef !== '') {
                $existing = PengajuanJenguk::query()
                    ->where('tenant_id', tenant('id'))
                    ->where('client_ref', $clientRef)
                    ->first();

                if ($existing !== null) {
                    if ((int) $existing->pengaju_id === (int) $pengaju->id
                        && (int) $existing->santri_id === (int) $santri->id) {
                        return $existing; // idempoten: kembalikan yang sama
                    }

                    // JEBAKAN BERULANG: ref orang lain TIDAK boleh "nyangkut".
                    throw new ConflictHttpException('client_ref sudah dipakai pengajuan lain.');
                }
            }

            if ($tanggal->lt(Carbon::today())) {
                throw new InvalidArgumentException('Tanggal jenguk tidak boleh di masa lalu.');
            }

            $namaHari = self::HARI[$tanggal->dayOfWeek];
            if (! in_array($namaHari, $jadwal['hari'], true)) {
                $label = implode(', ', $jadwal['hari']);
                throw new InvalidArgumentException("Hari {$namaHari} di luar jadwal penjengukan (hari yang dibuka: {$label}).");
            }

            // Satu wali cukup satu slot per santri per tanggal (status hidup).
            $sudahAda = PengajuanJenguk::query()
                ->where('tenant_id', tenant('id'))
                ->where('santri_id', $santri->id)
                ->whereDate('tanggal', $tanggal)
                ->whereIn('status', ['diajukan', 'dikonfirmasi', 'hadir'])
                ->exists();

            if ($sudahAda) {
                throw new InvalidArgumentException('Sudah ada pengajuan untuk santri ini di tanggal itu.');
            }

            $terpakai = PengajuanJenguk::query()
                ->where('tenant_id', tenant('id'))
                ->whereDate('tanggal', $tanggal)
                ->whereIn('status', ['diajukan', 'dikonfirmasi', 'hadir'])
                ->count();

            if ($terpakai >= $jadwal['kuota_per_slot']) {
                throw new InvalidArgumentException("Kuota slot tanggal {$tanggal->format('d-m-Y')} sudah penuh ({$jadwal['kuota_per_slot']} pengajuan).");
            }

            return PengajuanJenguk::create([
                'tenant_id' => tenant('id'),
                'santri_id' => $santri->id,
                'pengaju_id' => $pengaju->id,
                'tanggal' => $tanggal->toDateString(),
                'status' => $jadwal['konfirmasi'] === 'otomatis' ? 'dikonfirmasi' : 'diajukan',
                'client_ref' => $clientRef !== '' ? $clientRef : null,
                'catatan' => $payload['catatan'] ?? null,
            ]);
        });
    }

    /** Registrasi di pos: pengajuan dikonfirmasi hari ini → hadir + tulis kunjungan_wali. */
    public function tandaiHadir(PengajuanJenguk $pengajuan, ?string $namaPengunjung = null, ?string $hubungan = null): KunjunganWali
    {
        $this->ensureTenantContext();

        if ($pengajuan->status !== 'dikonfirmasi') {
            throw new InvalidArgumentException("Hanya pengajuan 'dikonfirmasi' yang bisa ditandai hadir, sekarang {$pengajuan->status}.");
        }

        if (! $pengajuan->tanggal->isToday()) {
            throw new InvalidArgumentException('Pengajuan ini bukan untuk hari ini.');
        }

        $jadwal = $this->jadwal();

        return DB::transaction(function () use ($pengajuan, $namaPengunjung, $hubungan, $jadwal): KunjunganWali {
            // Re-check di bawah kunci baris: dua petugas menandai bersamaan
            // → hanya satu yang menulis kunjungan_wali.
            $fresh = PengajuanJenguk::query()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($fresh->status !== 'dikonfirmasi') {
                throw new InvalidArgumentException("Hanya pengajuan 'dikonfirmasi' yang bisa ditandai hadir, sekarang {$fresh->status}.");
            }

            $pengajuan->update(['status' => 'hadir']);

            return KunjunganWali::create([
                'tenant_id' => tenant('id'),
                'santri_id' => $pengajuan->santri_id,
                'nama_pengunjung' => $namaPengunjung ?: ($pengajuan->pengaju?->name ?? 'Wali Santri'),
                'hubungan' => $hubungan ?: 'wali',
                'tanggal' => $pengajuan->tanggal->toDateString(),
                'jam_datang' => now()->format('H:i'),
                'keperluan' => $pengajuan->catatan,
                'status' => 'hadir',
                'pengajuan_id' => $pengajuan->id,
                'slot' => "{$jadwal['jam_mulai']}-{$jadwal['jam_selesai']}",
            ]);
        });
    }

    /** Walk-in (tamu tanpa pengajuan) — hanya bila saklar walk_in_boleh true. */
    public function walkIn(Santri $santri, string $namaPengunjung, string $hubungan, ?string $keperluan = null): KunjunganWali
    {
        $this->ensureTenantContext();

        $jadwal = $this->jadwal();

        if (! $jadwal['walk_in_boleh']) {
            throw new InvalidArgumentException('Kunjungan tanpa pengajuan (walk-in) tidak diizinkan. Silakan ajukan slot via Portal Wali.');
        }

        return KunjunganWali::create([
            'tenant_id' => tenant('id'),
            'santri_id' => $santri->id,
            'nama_pengunjung' => $namaPengunjung,
            'hubungan' => $hubungan,
            'tanggal' => Carbon::today()->toDateString(),
            'jam_datang' => now()->format('H:i'),
            'keperluan' => $keperluan,
            'status' => 'hadir',
            'slot' => "{$jadwal['jam_mulai']}-{$jadwal['jam_selesai']}",
        ]);
    }

    /** Konfirmasi manual oleh pengurus (status diajukan → dikonfirmasi/ditolak). */
    public function putuskan(PengajuanJenguk $pengajuan, string $keputusan): PengajuanJenguk
    {
        $this->ensureTenantContext();

        if ($pengajuan->status !== 'diajukan') {
            throw new InvalidArgumentException("Hanya pengajuan 'diajukan' yang bisa diputuskan, sekarang {$pengajuan->status}.");
        }

        if (! in_array($keputusan, ['dikonfirmasi', 'ditolak'], true)) {
            throw new InvalidArgumentException('Keputusan tidak valid.');
        }

        $pengajuan->update(['status' => $keputusan]);

        return $pengajuan->refresh();
    }

    /**
     * Wali membatalkan pengajuan miliknya sendiri (diajukan|dikonfirmasi → batal).
     * Update kondisional WHERE status IN (...) — race-safe terhadap petugas pos.
     */
    public function batal(PengajuanJenguk $pengajuan, User $pembatal): PengajuanJenguk
    {
        $this->ensureTenantContext();

        if ((int) $pengajuan->pengaju_id !== (int) $pembatal->id) {
            throw new AccessDeniedHttpException('Anda hanya dapat membatalkan pengajuan milik sendiri.');
        }

        $updated = PengajuanJenguk::query()
            ->whereKey($pengajuan->id)
            ->where('pengaju_id', $pembatal->id)
            ->whereIn('status', ['diajukan', 'dikonfirmasi'])
            ->update(['status' => 'batal']);

        if ($updated === 0) {
            throw new InvalidArgumentException("Pengajuan tidak bisa dibatalkan (status sekarang: {$pengajuan->refresh()->status}).");
        }

        return $pengajuan->refresh();
    }

    /** Advisory lock transaksional per (tenant, tanggal) — hanya PostgreSQL. */
    private function kunciSlot(Carbon $tanggal): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // sqlite dkk: serialisasi file-level sudah cukup di test
        }

        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [
            tenant('id').'|jenguk|'.$tanggal->toDateString(),
        ]);
    }

    private function ensureTenantContext(): void
    {
        if (! tenancy()->initialized || tenancy()->tenant === null) {
            throw new RuntimeException('Tenant context belum aktif.');
        }
    }
}
