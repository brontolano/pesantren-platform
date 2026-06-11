<?php

namespace Modules\AI\Services\Eval;

use App\Models\AccessWali;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Modules\AI\Database\Seeders\AiPolicySeeder;
use Modules\AI\Models\AiApproval;
use Modules\AI\Models\AiEvalCase;
use Modules\AI\Models\AiPolicy;
use Modules\AI\Models\AiPolicyRule;
use Modules\AI\Models\AiTenantSetting;
use Modules\AI\Services\Settings\AiSettingProvisioner;
use Modules\AI\Services\Settings\AiTenantSettingDefaults;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\Kesantrian\Models\Santri;
use Modules\Keuangan\Models\Tagihan;
use Modules\PPDB\Models\CalonSantri;

/**
 * T-A024 — penyiapan TENANT KHUSUS EVAL (sandbox milik harness, id default
 * config ai.eval.tenant_id). SEMUA metode dipanggil DALAM konteks tenancy
 * tenant itu dan IDEMPOTEN — run berulang memakai ulang data yang sama.
 *
 * Kelas ini TIDAK PERNAH dipakai pada tenant nyata (--tenant=...): harness
 * dilarang mengubah pengaturan/menanam data di tenant produksi — kasus
 * ber-setup dilewati di sana (lihat EvalRunner).
 */
class EvalTenantPreparer
{
    private const EMAIL_ADMIN = 'eval-admin@eval.internal';

    private const EMAIL_KYAI = 'eval-kyai@eval.internal';

    private const EMAIL_WALI = 'eval-wali@eval.internal';

    /**
     * Fondasi tenant eval: roles+permissions, pengaturan AI default
     * konservatif (provisioner resmi), wadah policy, dan aktor sintetis.
     *
     * @return array{admin: User, kyai: User, wali: User}
     */
    public function siapkanDasar(string $tenantId): array
    {
        // Cermin baris tenant di DB tenant sendiri (FK users.tenant_id, pola
        // provisioning platform / helper seedTenantRow di test) — idempoten.
        $tenant = Tenant::query()->find($tenantId);

        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenantId],
            [
                'name' => (string) ($tenant->name ?? 'Tenant Eval Khadim Digital'),
                'is_active' => true,
                'data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Roles & permissions platform (idempoten; seeder sudah membersihkan
        // cache spatie sendiri di awal run-nya).
        app(RolesAndPermissionsSeeder::class)->run();

        app(AiSettingProvisioner::class)->provision($tenantId);
        (new AiPolicySeeder)->run();

        $admin = $this->user(self::EMAIL_ADMIN, 'Eval Admin', roles: ['admin_pesantren'], permissions: ['ai.setting.kelola']);
        $kyai = $this->user(self::EMAIL_KYAI, 'Eval Kyai', permissions: ['ai.setting.setujui']);
        $wali = $this->user(self::EMAIL_WALI, 'Eval Wali', roles: ['wali']);

        return ['admin' => $admin, 'kyai' => $kyai, 'wali' => $wali];
    }

    /**
     * Konteks kebijakan suite syariah: level fatwa tertentu + blacklist
     * TEKNIS-NETRAL milik suite policy_cases.json (config ai.eval.konteks_
     * syariah — BUKAN konten keagamaan, Aturan Emas #8) + fitur chatbot wali
     * aktif (supaya gerbang konten SETELAH gerbang fitur ikut teruji di
     * kanal wa). Perubahan lewat alur resmi TenantSettingService (field
     * sensitif → approval kyai sintetis) — pipeline yang sama dengan
     * produksi, termasuk mirror blacklist ke ai_policy_rule.
     *
     * @param  array{admin: User, kyai: User}  $aktor
     */
    public function terapkanKonteksSyariah(string $level, array $aktor): void
    {
        $blacklist = (array) config('ai.eval.konteks_syariah.blacklist', []);

        $fitur = AiTenantSettingDefaults::konservatif()['fitur_per_peran'];
        $fitur['wali']['chatbot'] = true;

        $aktif = app(TenantSettingService::class)->aktif();
        $payloadAktif = (array) ($aktif?->payload ?? []);

        $sudahSesuai = ($payloadAktif['level_fatwa'] ?? null) === $level
            && array_values((array) ($payloadAktif['blacklist_topik'] ?? [])) === array_values($blacklist)
            && (($payloadAktif['fitur_per_peran']['wali']['chatbot'] ?? false) === true);

        if (! $sudahSesuai) {
            $service = app(TenantSettingService::class);

            $payload = array_replace(AiTenantSettingDefaults::konservatif(), [
                'level_fatwa' => $level,
                'blacklist_topik' => $blacklist,
                'fitur_per_peran' => $fitur,
            ]);

            $draf = $service->simpanDraf($payload, $aktor['admin']);
            $hasil = $service->ajukan($draf->versi, $aktor['admin']);

            if ($hasil->status === AiTenantSetting::STATUS_PENDING_APPROVAL) {
                $approval = AiApproval::query()
                    ->where('ai_tenant_setting_id', $draf->id)
                    ->firstOrFail();
                $service->putuskan($approval->id, $aktor['kyai'], true);
            }
        }

        $this->pastikanRuleRegexUji();
    }

    /**
     * Setup data minimal kasus tool faktual di tenant eval (idempoten per
     * nis/nomor pendaftaran). Mengembalikan peta kode kasus → id santri
     * untuk resolusi placeholder '@santri' di EvalRunner.
     *
     * @param  iterable<AiEvalCase>  $kasusTool
     * @return array<string, int>
     */
    public function siapkanDataTool(iterable $kasusTool, User $wali): array
    {
        $peta = [];

        foreach ($kasusTool as $kasus) {
            $setup = (array) (((array) $kasus->input)['setup'] ?? []);

            if (isset($setup['santri'])) {
                $spek = (array) $setup['santri'];

                $santri = Santri::query()->firstOrCreate(
                    ['nis' => (string) $spek['nis']],
                    [
                        'nama' => (string) ($spek['nama'] ?? 'Santri Eval'),
                        'tanggal_lahir' => '2012-01-01',
                        'jenis_kelamin' => 'L',
                        'status' => 'aktif',
                    ],
                );

                // Tagihan hanya ditanam saat belum ada — run ulang tidak
                // menggandakan baris (ekspektasi nilai_harus tetap stabil).
                $daftarTagihan = (array) ($setup['tagihan'] ?? []);

                if ($daftarTagihan !== [] && ! Tagihan::query()->where('santri_id', $santri->getKey())->exists()) {
                    foreach ($daftarTagihan as $t) {
                        Tagihan::query()->create([
                            'santri_id' => $santri->getKey(),
                            'jenis_tagihan' => 'spp',
                            'nominal' => (float) $t['nominal'],
                            'nominal_akhir' => (float) $t['nominal'],
                            'status' => (string) ($t['status'] ?? 'belum_bayar'),
                            'jatuh_tempo' => '2026-07-01',
                        ]);
                    }
                }

                if (($setup['akses_wali'] ?? false) === true) {
                    AccessWali::query()->firstOrCreate([
                        'wali_id' => $wali->getKey(),
                        'santri_id' => $santri->getKey(),
                    ]);
                }

                $peta[$kasus->kode] = (int) $santri->getKey();
            }

            if (isset($setup['calon_santri'])) {
                $spek = (array) $setup['calon_santri'];

                CalonSantri::query()->firstOrCreate(
                    ['nomor_pendaftaran' => (string) $spek['nomor_pendaftaran']],
                    [
                        'nama' => (string) ($spek['nama'] ?? 'Calon Eval'),
                        'jenis_kelamin' => 'L',
                        'tanggal_lahir' => '2012-01-01',
                        'status_pendaftaran' => (string) ($spek['status_pendaftaran'] ?? 'diterima'),
                        'tahun_ajaran' => (string) ($spek['tahun_ajaran'] ?? '2026'),
                        'gelombang' => (string) ($spek['gelombang'] ?? 'Gelombang 1'),
                    ],
                );
            }
        }

        return $peta;
    }

    // ------------------------------------------------------------------
    // Pendukung
    // ------------------------------------------------------------------

    /**
     * Rule regex blacklist TEKNIS suite policy_cases (config ai.eval) pada
     * policy "Kebijakan Pesantren" — prioritas 50 khusus harness, tidak
     * menyentuh rule mirror setting (prioritas 0) maupun rule lain.
     */
    private function pastikanRuleRegexUji(): void
    {
        $regex = (array) config('ai.eval.konteks_syariah.regex', []);

        if ($regex === []) {
            return;
        }

        $policy = AiPolicy::query()->firstOrCreate(
            ['nama' => AiPolicy::NAMA_KEBIJAKAN_PESANTREN, 'sumber' => AiPolicy::SUMBER_TENANT],
            ['aktif' => true],
        );

        AiPolicyRule::query()->updateOrCreate(
            [
                'policy_id' => $policy->getKey(),
                'tipe' => AiPolicyRule::TIPE_BLACKLIST_TOPIK,
                'prioritas' => 50,
            ],
            [
                'pola' => ['keyword' => [], 'regex' => array_values($regex)],
                'respons_template' => null,
                'aktif' => true,
            ],
        );
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    private function user(string $email, string $nama, array $roles = [], array $permissions = []): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $nama, 'password' => str()->random(32)],
        );

        // assignRole/givePermissionTo idempoten (spatie tidak menggandakan).
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
