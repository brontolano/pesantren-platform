<?php

namespace Modules\AI\Services\Settings;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiTenantSetting;

/**
 * T-A010 — siklus hidup pengaturan AI per tenant (versioned + approval kyai).
 *
 * Alur (Blueprint §8 butir 4, PRD §6.2):
 *   simpanDraf() → versi baru 'draft'
 *   ajukan()     → field SENSITIF berubah  → 'pending_approval' + ai_approval
 *                  (versi aktif LAMA tetap dipakai sampai kyai setuju ulang);
 *                → hanya field non-sensitif → langsung 'aktif' tanpa approval.
 *   putuskan()   → disetujui: versi jadi aktif + aktif lama diarsipkan;
 *                  ditolak: versi kembali 'draft'.
 *
 * Daftar field sensitif hidup di config('ai.settings.sensitive_fields') —
 * kebijakan di data/config, bukan hard-code (Aturan Emas #11).
 *
 * RBAC spatie: simpanDraf/ajukan butuh `ai.setting.kelola`,
 * putuskan butuh `ai.setting.setujui`.
 */
class TenantSettingService
{
    /**
     * Cache ringan PER REQUEST saja (properti instans, bukan CentralCache —
     * ini data tenant, dilarang nyangkut di cache bersama lintas tenant).
     * Dikunci per tenant aktif untuk jaga-jaga ganti konteks dalam 1 request.
     *
     * @var array{tenant: ?string, setting: ?AiTenantSetting}|null
     */
    private ?array $memo = null;

    /** Versi pengaturan yang sedang AKTIF (dipakai agen/PolicyEngine T-A020). */
    public function aktif(): ?AiTenantSetting
    {
        $tenantKey = tenancy()->initialized ? (string) tenant()->getKey() : null;

        if ($this->memo !== null && $this->memo['tenant'] === $tenantKey) {
            return $this->memo['setting'];
        }

        $setting = AiTenantSetting::query()
            ->where('status', AiTenantSetting::STATUS_AKTIF)
            ->first();

        $this->memo = ['tenant' => $tenantKey, 'setting' => $setting];

        return $setting;
    }

    /**
     * Simpan payload lengkap sebagai VERSI BARU berstatus draft.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthorizationException|ValidationException
     */
    public function simpanDraf(array $payload, User $user): AiTenantSetting
    {
        $this->pastikanBoleh($user, 'ai.setting.kelola');

        $payload = $this->validasi($payload);

        return DB::transaction(function () use ($payload, $user): AiTenantSetting {
            // Kunci baris versi tertinggi (bukan MAX() FOR UPDATE — tidak
            // didukung PostgreSQL); unique index `versi` menangkap balapan.
            $versi = (int) AiTenantSetting::query()
                ->orderByDesc('versi')
                ->lockForUpdate()
                ->value('versi') + 1;

            return AiTenantSetting::query()->create([
                'versi' => $versi,
                'payload' => $payload,
                'status' => AiTenantSetting::STATUS_DRAFT,
                'dibuat_oleh' => $user->getKey(),
                'ringkasan_perubahan' => $this->ringkasanPerubahan($payload),
            ]);
        });
    }

    /**
     * T-A011 — perbarui payload sebuah versi yang MASIH draft (autosave
     * Wizard antar-langkah). Sengaja meng-update baris yang sama, BUKAN
     * membuat versi baru per langkah: jejak audit yang harus utuh adalah
     * versi yang pernah diajukan/aktif, bukan coretan draf di tengah jalan.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthorizationException|ValidationException
     */
    public function updateDraf(int $versi, array $payload, User $user): AiTenantSetting
    {
        $this->pastikanBoleh($user, 'ai.setting.kelola');

        $payload = $this->validasi($payload);

        return DB::transaction(function () use ($versi, $payload): AiTenantSetting {
            /** @var AiTenantSetting $setting */
            $setting = AiTenantSetting::query()
                ->where('versi', $versi)
                ->lockForUpdate()
                ->firstOrFail();

            if ($setting->status !== AiTenantSetting::STATUS_DRAFT) {
                throw new InvalidArgumentException(
                    "Versi {$versi} berstatus '{$setting->status}' — hanya draft yang dapat diperbarui."
                );
            }

            $setting->update([
                'payload' => $payload,
                'ringkasan_perubahan' => $this->ringkasanPerubahan($payload),
            ]);

            return $setting->refresh();
        });
    }

    /**
     * Ajukan sebuah versi draft:
     *  - menyentuh field SENSITIF → pending_approval + baris ai_approval
     *    'menunggu' (hash ringkasan); versi aktif lama TETAP berlaku;
     *  - hanya field non-sensitif → langsung aktif tanpa approval.
     */
    public function ajukan(int $versi, User $user): AiTenantSetting
    {
        $this->pastikanBoleh($user, 'ai.setting.kelola');

        return DB::transaction(function () use ($versi): AiTenantSetting {
            /** @var AiTenantSetting $setting */
            $setting = AiTenantSetting::query()
                ->where('versi', $versi)
                ->lockForUpdate()
                ->firstOrFail();

            if ($setting->status !== AiTenantSetting::STATUS_DRAFT) {
                throw new InvalidArgumentException(
                    "Versi {$versi} berstatus '{$setting->status}' — hanya draft yang dapat diajukan."
                );
            }

            $aktif = AiTenantSetting::query()
                ->where('status', AiTenantSetting::STATUS_AKTIF)
                ->lockForUpdate()
                ->first();

            if ($this->menyentuhFieldSensitif($setting->payload, $aktif?->payload)) {
                $setting->update(['status' => AiTenantSetting::STATUS_PENDING_APPROVAL]);

                AiApproval::query()->create([
                    'ai_tenant_setting_id' => $setting->getKey(),
                    'ringkasan_hash' => self::hashRingkasan($setting->payload),
                    'status' => AiApproval::STATUS_MENUNGGU,
                ]);
            } else {
                // Non-sensitif (jam layanan, bahasa/gaya, anggaran, ambang
                // alert, pesan) → aktifkan langsung, arsipkan aktif lama.
                $aktif?->update(['status' => AiTenantSetting::STATUS_ARSIP]);
                $setting->update(['status' => AiTenantSetting::STATUS_AKTIF]);
            }

            $this->memo = null;

            return $setting->refresh();
        });
    }

    /**
     * Keputusan kyai/pimpinan atas approval 'menunggu'.
     * Setuju → versi jadi aktif + aktif lama diarsipkan; tolak → kembali draft.
     */
    public function putuskan(int $approvalId, User $kyai, bool $setuju, ?string $alasan = null): AiApproval
    {
        $this->pastikanBoleh($kyai, 'ai.setting.setujui');

        return DB::transaction(function () use ($approvalId, $kyai, $setuju, $alasan): AiApproval {
            /** @var AiApproval $approval */
            $approval = AiApproval::query()->lockForUpdate()->findOrFail($approvalId);

            if ($approval->status !== AiApproval::STATUS_MENUNGGU) {
                throw new InvalidArgumentException(
                    "Approval #{$approvalId} sudah diputuskan ({$approval->status})."
                );
            }

            /** @var AiTenantSetting $setting */
            $setting = $approval->setting()->lockForUpdate()->firstOrFail();

            // Anti tukar diam-diam: payload tidak boleh berubah sejak diajukan.
            if (self::hashRingkasan($setting->payload) !== $approval->ringkasan_hash) {
                throw new InvalidArgumentException(
                    'Ringkasan kebijakan berubah sejak diajukan — mohon ajukan ulang.'
                );
            }

            if ($setuju) {
                AiTenantSetting::query()
                    ->where('status', AiTenantSetting::STATUS_AKTIF)
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (AiTenantSetting $lama) => $lama->update([
                        'status' => AiTenantSetting::STATUS_ARSIP,
                    ]));

                $setting->update(['status' => AiTenantSetting::STATUS_AKTIF]);
            } else {
                $setting->update(['status' => AiTenantSetting::STATUS_DRAFT]);
            }

            $approval->update([
                'status' => $setuju ? AiApproval::STATUS_DISETUJUI : AiApproval::STATUS_DITOLAK,
                'disetujui_oleh' => $kyai->getKey(),
                'alasan' => $alasan,
                'decided_at' => now(),
            ]);

            $this->memo = null;

            return $approval->refresh();
        });
    }

    /** Hash kanonik payload — sidik jari ringkasan kebijakan yang disetujui. */
    public static function hashRingkasan(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Apakah payload baru mengubah salah satu field sensitif relatif terhadap
     * versi aktif? Tanpa versi aktif sebagai pembanding (kasus tak lazim),
     * konservatif: anggap sensitif.
     */
    private function menyentuhFieldSensitif(array $baru, ?array $aktif): bool
    {
        if ($aktif === null) {
            return true;
        }

        foreach ((array) config('ai.settings.sensitive_fields', []) as $field) {
            if (($baru[$field] ?? null) != ($aktif[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function ringkasanPerubahan(array $payload): string
    {
        $aktif = AiTenantSetting::query()
            ->where('status', AiTenantSetting::STATUS_AKTIF)
            ->first();

        if ($aktif === null) {
            return 'Versi awal pengaturan AI.';
        }

        $berubah = [];
        foreach ($payload as $field => $nilai) {
            if (($aktif->payload[$field] ?? null) != $nilai) {
                $berubah[] = $field;
            }
        }

        return $berubah === []
            ? 'Tidak ada perubahan terhadap versi aktif.'
            : 'Mengubah: '.implode(', ', $berubah).'.';
    }

    private function pastikanBoleh(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException(
                "Mohon maaf, Anda tidak memiliki izin '{$permission}' untuk tindakan ini."
            );
        }
    }

    /**
     * Validasi ketat seluruh field katalog Wizard PRD §6.3.
     * Kunci tak dikenal DITOLAK (bukan diabaikan) — typo kebijakan berbahaya.
     *
     * @return array<string, mixed> payload tervalidasi
     *
     * @throws ValidationException
     */
    private function validasi(array $payload): array
    {
        $dikenal = array_keys(AiTenantSettingDefaults::konservatif());
        $asing = array_diff(array_keys($payload), $dikenal);

        if ($asing !== []) {
            throw ValidationException::withMessages([
                'payload' => 'Field tidak dikenal: '.implode(', ', $asing).'.',
            ]);
        }

        $scopeDikenal = ['tagihan', 'jadwal', 'nilai', 'kehadiran', 'kesehatan', 'keuangan'];
        $scopeAsing = array_diff(array_keys((array) ($payload['scope_data'] ?? [])), $scopeDikenal);
        if ($scopeAsing !== []) {
            throw ValidationException::withMessages([
                'scope_data' => 'Scope data tidak dikenal: '.implode(', ', $scopeAsing).'.',
            ]);
        }

        $validator = Validator::make($payload, [
            'preset_manhaj' => ['required', 'string', 'in:aswaja_nu,muhammadiyah,salafi,kustom'],
            'level_fatwa' => ['required', 'string', 'in:edukatif_only,informatif,khilafiyah'],
            'whitelist_kitab' => ['present', 'array'],
            'whitelist_kitab.*' => ['string', 'max:255'],
            'blacklist_topik' => ['present', 'array'],
            'blacklist_topik.*' => ['string', 'max:255'],
            'eskalasi_tujuan' => ['required', 'array'],
            'eskalasi_tujuan.penerima' => ['present', 'array'],
            'eskalasi_tujuan.penerima.*' => ['integer'],
            'eskalasi_tujuan.kanal' => ['required', 'string', 'in:panel,wa'],
            'eskalasi_tujuan.sla_jam' => ['required', 'integer', 'min:1', 'max:168'],
            'fitur_per_peran' => ['required', 'array'],
            'fitur_per_peran.*' => ['array'],
            'fitur_per_peran.*.*' => ['boolean'],
            'mode_akses_santri' => ['required', 'string', 'in:tanpa_akses,lab_terjadwal,perangkat_terbatas'],
            'scope_data' => ['required', 'array'],
            'scope_data.*' => ['boolean'],
            'bahasa' => ['required', 'string', 'in:id,id_jawa,id_sunda'],
            'gaya' => ['required', 'string', 'in:formal_santun,hangat'],
            'jam_layanan' => ['required', 'array'],
            'jam_layanan.mulai' => ['required', 'date_format:H:i'],
            'jam_layanan.selesai' => ['required', 'date_format:H:i'],
            'pesan_di_luar_jam' => ['required', 'string', 'max:1000'],
            'anggaran_kredit_bulanan' => ['nullable', 'numeric', 'min:0'],
            'ambang_alert_persen' => ['required', 'integer', 'min:1', 'max:100'],
            'retensi_hari' => ['required', 'integer', 'min:1', 'max:3650'],
            'retensi_hari_santri' => ['required', 'integer', 'min:1', 'max:3650'],
            'consent_ortu' => ['required', 'boolean'],
            'optin_riset' => ['required', 'boolean'],
        ]);

        return $validator->validate();
    }
}
