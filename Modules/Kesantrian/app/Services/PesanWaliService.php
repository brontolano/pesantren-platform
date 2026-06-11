<?php

namespace Modules\Kesantrian\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Akademik\Models\JadwalPelajaran;
use Modules\Kesantrian\Models\PesanWali;
use Modules\Kesantrian\Models\Santri;
use Modules\Notifikasi\Models\LogNotifikasi;
use Modules\Notifikasi\Services\NotifDispatcher;
use Modules\Tahfidz\Models\Halaqah;
use Throwable;

/**
 * F3-PWA — Mesin pesan guru → wali.
 *
 * - Otorisasi: guru hanya boleh mengirim ke santri kelas/halaqah binaannya
 *   (pola F1/F2: pelajaran.guru_pengampu_id + halaqah.musyrif_id).
 * - Saklar moderasi (Setting kesantrian.moderasi_pesan_perhatian, default ON):
 *   jenis 'perhatian' → menunggu_moderasi; apresiasi/info selalu langsung.
 * - Idempoten client_ref: dedup HARUS scoped guru_id+santri_id — client_ref
 *   milik guru/santri lain → 409 (unique DB (tenant, client_ref) race-safe).
 * - Delivery fire-and-forget: LogNotifikasi channel 'database' utk Portal Wali
 *   + WA wali via NotifDispatcher event pesan_wali_baru. TIDAK PERNAH
 *   menggagalkan alur pengiriman/moderasi.
 */
class PesanWaliService
{
    public const SETTING_TEMPLATE = 'kesantrian.template_pesan_wali';

    public const SETTING_MODERASI = 'kesantrian.moderasi_pesan_perhatian';

    public function __construct(private readonly NotifDispatcher $dispatcher) {}

    // ------------------------------------------------------------------
    // Template per tenant
    // ------------------------------------------------------------------

    /** @return array<int, array{jenis:string, label:string, isi:string}> */
    public static function templateDefault(): array
    {
        return [
            [
                'jenis' => 'apresiasi',
                'label' => 'Apresiasi hafalan',
                'isi' => 'Alhamdulillah, ananda menunjukkan kemajuan hafalan yang baik pekan ini. Mohon doa dan dukungan dari rumah agar semangatnya terjaga.',
            ],
            [
                'jenis' => 'apresiasi',
                'label' => 'Apresiasi adab & keaktifan',
                'isi' => 'Alhamdulillah, ananda menunjukkan adab yang baik dan aktif mengikuti pembelajaran. Semoga istiqamah.',
            ],
            [
                'jenis' => 'perhatian',
                'label' => 'Perhatian kehadiran',
                'isi' => 'Mohon perhatian Bapak/Ibu: kehadiran ananda di kelas/halaqah beberapa waktu terakhir perlu ditingkatkan. Mari kita dampingi bersama.',
            ],
            [
                'jenis' => 'perhatian',
                'label' => 'Perhatian tugas & murajaah',
                'isi' => 'Mohon pendampingan Bapak/Ibu: ananda perlu lebih rutin menyelesaikan tugas dan murajaah hafalannya di waktu istirahat.',
            ],
            [
                'jenis' => 'info',
                'label' => 'Info kegiatan',
                'isi' => 'Kami informasikan bahwa akan ada kegiatan di pesantren dalam waktu dekat. Detail menyusul melalui pengumuman resmi.',
            ],
        ];
    }

    /** Template aktif tenant (Setting menimpa default). */
    public function templates(): array
    {
        $value = Setting::getTenantValue(self::SETTING_TEMPLATE);

        if (is_array($value) && $value !== []) {
            return collect($value)
                ->filter(fn ($t): bool => is_array($t) && isset($t['jenis'], $t['label'], $t['isi'])
                    && in_array($t['jenis'], PesanWali::JENIS, true))
                ->values()
                ->all();
        }

        return self::templateDefault();
    }

    /** Saklar moderasi jenis 'perhatian' — default ON (policy-first). */
    public function moderasiPerhatianAktif(): bool
    {
        $value = Setting::getTenantValue(self::SETTING_MODERASI);

        if (is_array($value)) {
            $value = $value['value'] ?? $value['enabled'] ?? null;
        }

        return $value === null ? true : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    // ------------------------------------------------------------------
    // Otorisasi binaan (pola F1/F2)
    // ------------------------------------------------------------------

    /** Santri aktif di kelas yang diampu guru ATAU halaqah binaannya. */
    public function santriBinaanQuery(User $guru)
    {
        $kelasIds = JadwalPelajaran::query()
            ->whereHas('pelajaran', fn ($q) => $q->where('guru_pengampu_id', $guru->id))
            ->pluck('kelas_id')->unique()->values();

        $halaqahSantriIds = Halaqah::query()
            ->where('musyrif_id', $guru->id)
            ->where('aktif', true)
            ->get()
            ->flatMap(fn (Halaqah $h) => $h->santriAktif()->pluck('santri.id'))
            ->unique()->values();

        return Santri::query()
            ->where('status', 'aktif')
            ->where(function ($q) use ($kelasIds, $halaqahSantriIds): void {
                $q->whereIn('kelas_id', $kelasIds)
                    ->orWhereIn('id', $halaqahSantriIds);
            });
    }

    public function bolehKirimKe(User $guru, Santri $santri): bool
    {
        return $this->santriBinaanQuery($guru)->whereKey($santri->id)->exists();
    }

    // ------------------------------------------------------------------
    // Kirim (idempoten client_ref scoped guru+santri)
    // ------------------------------------------------------------------

    /**
     * @param  array{santri_id:int, jenis:string, isi:string, client_ref:string}  $data
     */
    public function kirim(User $guru, Santri $santri, array $data): PesanWali
    {
        $clientRef = (string) $data['client_ref'];

        // Dedup HARUS scoped guru+santri — client_ref orang lain → 409
        // (jebakan berulang: dedup global membuat ref tetangga "mengaku" milikku).
        $existing = PesanWali::query()->where('client_ref', $clientRef)->first();
        if ($existing !== null) {
            abort_unless(
                (int) $existing->guru_id === (int) $guru->id && (int) $existing->santri_id === (int) $santri->id,
                409,
                'client_ref sudah dipakai pengirim/tujuan lain.',
            );

            return $existing; // retry idempoten
        }

        $perluModerasi = $data['jenis'] === 'perhatian' && $this->moderasiPerhatianAktif();

        try {
            $pesan = DB::transaction(fn (): PesanWali => PesanWali::create([
                'guru_id' => $guru->id,
                'santri_id' => $santri->id,
                'jenis' => $data['jenis'],
                'isi' => $data['isi'],
                'status' => $perluModerasi ? 'menunggu_moderasi' : 'terkirim',
                'sent_at' => $perluModerasi ? null : now(),
                'client_ref' => $clientRef,
            ]));
        } catch (QueryException $e) {
            // Race retry: unique (tenant, client_ref) — baris pemenang yang sah.
            $existing = PesanWali::query()->where('client_ref', $clientRef)->first();
            if ($existing !== null) {
                abort_unless(
                    (int) $existing->guru_id === (int) $guru->id && (int) $existing->santri_id === (int) $santri->id,
                    409,
                    'client_ref sudah dipakai pengirim/tujuan lain.',
                );

                return $existing;
            }

            throw $e;
        }

        if (! $perluModerasi) {
            $this->deliver($pesan);
        }

        return $pesan;
    }

    // ------------------------------------------------------------------
    // Moderasi panel
    // ------------------------------------------------------------------

    public function setujui(PesanWali $pesan, int $moderatorId): void
    {
        // Conditional update: dua moderator paralel (atau setujui+tolak) —
        // hanya transisi pertama yang menang; deliver SEKALI saja.
        $diubah = PesanWali::query()->whereKey($pesan->getKey())
            ->where('status', 'menunggu_moderasi')
            ->update([
                'status' => 'terkirim',
                'moderated_by' => $moderatorId,
                'sent_at' => now(),
            ]);

        if ($diubah === 1) {
            $this->deliver($pesan->fresh());
        }
    }

    public function tolak(PesanWali $pesan, int $moderatorId, string $alasan): void
    {
        PesanWali::query()->whereKey($pesan->getKey())
            ->where('status', 'menunggu_moderasi')
            ->update([
                'status' => 'ditolak',
                'moderated_by' => $moderatorId,
                'alasan_tolak' => $alasan,
            ]);
    }

    // ------------------------------------------------------------------
    // Delivery (fire-and-forget)
    // ------------------------------------------------------------------

    private function deliver(PesanWali $pesan): void
    {
        $santri = $pesan->santri;
        $via = [];

        // 1) Notifikasi Portal Wali — LogNotifikasi channel 'database',
        //    target = email akun wali (pola PortalWaliService::getNotificationPayload).
        try {
            $targets = $this->waliPortalTargets($santri);
            foreach ($targets as $target) {
                LogNotifikasi::query()->create([
                    'jenis' => 'pesan_wali',
                    'channel' => 'database',
                    'target' => $target,
                    'status' => 'sent',
                    'payload' => [
                        'judul' => 'Pesan dari '.($pesan->guru?->name ?? 'Ustadz/Ustadzah'),
                        'pesan' => $pesan->isi,
                        'jenis' => $pesan->jenis,
                        'nama_santri' => $santri?->nama,
                        'pesan_wali_id' => $pesan->getKey(),
                    ],
                    'created_at' => now(),
                ]);
            }
            if ($targets !== []) {
                $via[] = 'portal';
            }
        } catch (Throwable $e) {
            report($e);
        }

        // 2) WA wali via NotifDispatcher (gated TemplateNotifikasi aktif tenant).
        try {
            $phone = PhoneNumber::normalize($santri?->no_hp_wali);
            if ($phone !== '') {
                $hasil = $this->dispatcher->kirim('pesan_wali_baru', [
                    'wa' => [$phone],
                    'data' => [
                        'nama_santri' => $santri?->nama ?? '-',
                        'nama_guru' => $pesan->guru?->name ?? '-',
                        'jenis' => $pesan->jenis,
                        'isi_pesan' => $pesan->isi,
                        'nama_pesantren' => Setting::getTenantStringValue('branding_name') ?? (tenant('name') ?? 'Pesantren'),
                    ],
                ]);
                if (($hasil['terkirim'] ?? 0) > 0) {
                    $via[] = 'wa';
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $pesan->update(['dikirim_via' => match (true) {
                count($via) === 2 => 'keduanya',
                $via === ['portal'] => 'portal',
                $via === ['wa'] => 'wa',
                default => $pesan->dikirim_via,
            }]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<int, string> Email akun wali ber-portal milik santri ini. */
    private function waliPortalTargets(?Santri $santri): array
    {
        if ($santri === null) {
            return [];
        }

        $emails = collect();

        if ($santri->wali_user_id !== null) {
            $emails->push(User::find($santri->wali_user_id)?->email);
        }

        $emails = $emails->merge(
            $santri->waliPortal()->pluck('users.email'),
        );

        return $emails->filter()->unique()->values()->all();
    }
}
