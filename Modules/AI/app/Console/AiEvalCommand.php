<?php

namespace Modules\AI\Console;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AI\Database\Seeders\AiEvalCaseSeeder;
use Modules\AI\Models\AiEvalCase;
use Modules\AI\Models\AiEvalRun;
use Modules\AI\Services\Eval\EvalCaseResult;
use Modules\AI\Services\Eval\EvalKonteks;
use Modules\AI\Services\Eval\EvalRunner;
use Modules\AI\Services\Eval\EvalTenantPreparer;
use Modules\AI\Services\Gateway\ModelRouter;
use Modules\AI\Services\Prompts\PromptRegistry;
use Modules\AI\Services\Tools\ToolRegistry;
use Modules\AI\Services\Wa\IntentRouter;
use Throwable;

/**
 * T-A024 — `ai:eval`: gate eval rilis (Blueprint K7 + §10, BUKAN CI).
 *
 * Dijalankan SEBELUM rilis terhadap MODEL PRODUKSI sebenarnya (provider
 * LLM lokal — keputusan K3).
 * Ambang per suite di config ai.eval.ambang (syariah ≥98%, faktual ≥97%,
 * injection 100%); gate gagal = exit code != 0 = fitur terkait TIDAK dirilis
 * (bukan dilonggarkan). Setiap run tercatat di ai_eval_run lengkap dengan
 * model + versi prompt aktif — ganti model/prompt berarti WAJIB eval ulang
 * (bandingkan kolom antar run).
 *
 * Konteks tenant:
 *  - tanpa --tenant: memakai TENANT KHUSUS EVAL (config ai.eval.tenant_id,
 *    dibuat sekali lalu dipakai ulang) — harness bebas menyiapkan setting
 *    kebijakan + data minimal kasus tool di sandbox ini;
 *  - --tenant=demo: berjalan di tenant itu APA ADANYA (setting tidak diubah,
 *    data tidak ditanam) — kasus ber-setup dilewati & tercatat 'dilewati'.
 *    Diperlukan untuk kasus kurasi GATE-3 yang butuh korpus pengetahuan
 *    nyata, dan selama modul 'ai' berstatus development di module_registry
 *    (hanya tampil untuk tenant developer).
 */
class AiEvalCommand extends Command
{
    protected $signature = 'ai:eval
        {--suite= : Suite eval: syariah|faktual|injection}
        {--tenant= : ID tenant uji (default: tenant khusus eval dibuat/dipakai ulang otomatis)}
        {--dry : Jalankan eval tanpa menulis baris ai_eval_run}
        {--riwayat : Tampilkan 10 run eval terakhir lalu selesai}';

    protected $description = 'Gate eval rilis modul AI: jalankan suite syariah/faktual/injection terhadap pipeline & model produksi, catat ai_eval_run, exit != 0 bila gate gagal';

    public function handle(): int
    {
        if ($this->option('riwayat')) {
            return $this->tampilkanRiwayat();
        }

        $suite = (string) $this->option('suite');

        if (! in_array($suite, AiEvalCase::SUITES, true)) {
            $this->error('Pilih suite yang valid: --suite='.implode('|', AiEvalCase::SUITES).' (atau --riwayat).');

            return self::FAILURE;
        }

        // Bank kasus kosong → impor otomatis dari berkas JSON (idempoten).
        if (AiEvalCase::query()->where('suite', $suite)->count() === 0) {
            $this->info('Bank kasus kosong — menjalankan AiEvalCaseSeeder...');
            app(AiEvalCaseSeeder::class)->run();
        }

        $kasusSuite = AiEvalCase::query()
            ->where('suite', $suite)
            ->where('aktif', true)
            ->orderBy('kode')
            ->get();

        if ($kasusSuite->isEmpty()) {
            $this->error("Tidak ada kasus aktif untuk suite '{$suite}'.");

            return self::FAILURE;
        }

        $tenantOpsi = (string) $this->option('tenant');
        $tenantKhususEval = $tenantOpsi === '';

        $tenant = $tenantKhususEval
            ? $this->siapkanTenantEval()
            : Tenant::query()->find($tenantOpsi);

        if ($tenant === null) {
            $this->error($tenantKhususEval
                ? 'Gagal menyiapkan tenant khusus eval.'
                : "Tenant '{$tenantOpsi}' tidak ditemukan.");

            return self::FAILURE;
        }

        $mulai = hrtime(true);
        $hasil = [];

        tenancy()->initialize($tenant);

        try {
            if (! app(ModuleService::class)->isVisibleForTenant('ai', $tenant)) {
                $this->warn(
                    "Modul 'ai' tidak tampil untuk tenant '{$tenant->getKey()}' (module_registry/tenant_modules) — "
                    .'seluruh jalur gateway akan tertolak. Gunakan --tenant=demo (tenant developer) '
                    .'atau aktifkan modul untuk tenant ini.',
                );
            }

            $ctx = $this->siapkanKonteks($tenant, $tenantKhususEval, $suite, $kasusSuite);

            // Snapshot konfigurasi yang dievaluasi — DI DALAM konteks tenant
            // (override prompt per tenant ikut terekam).
            [$model, $promptVersi] = $this->snapshotModelPrompt((string) $tenant->getKey());

            $runner = new EvalRunner(
                app(IntentRouter::class),
                app(ToolRegistry::class),
            );

            if ($suite === 'syariah' && $tenantKhususEval) {
                $hasil = $this->jalankanSyariahPerLevel($runner, $ctx, $kasusSuite);
            } else {
                foreach ($kasusSuite as $kasus) {
                    $hasil[] = $runner->jalankan($kasus, $ctx);
                }
            }
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $this->laporkan($suite, (string) $tenant->getKey(), $hasil, $model, $promptVersi, $mulai);
    }

    // ------------------------------------------------------------------
    // Eksekusi
    // ------------------------------------------------------------------

    /**
     * Suite syariah pada tenant khusus eval: kasus dikelompokkan per
     * level_fatwa setup-nya; konteks kebijakan diterapkan SEKALI per
     * kelompok lewat alur resmi TenantSettingService (approval kyai
     * sintetis), lalu seluruh kasus kelompok itu dievaluasi.
     *
     * @param  Collection<int, AiEvalCase>  $kasusSuite
     * @return list<EvalCaseResult>
     */
    private function jalankanSyariahPerLevel(EvalRunner $runner, EvalKonteks $ctx, $kasusSuite): array
    {
        $kelompok = [];

        foreach ($kasusSuite as $kasus) {
            $setup = (array) (((array) $kasus->input)['setup'] ?? []);
            // Kasus tanpa setup level (mis. kurasi GATE-3) dievaluasi pada
            // level default konservatif.
            $level = (string) ($setup['level_fatwa'] ?? 'edukatif_only');
            $kelompok[$level][] = $kasus;
        }

        // Urutan deterministik: edukatif_only dulu (default), lalu sisanya.
        uksort($kelompok, static fn (string $a, string $b): int => ($a === 'edukatif_only' ? 0 : 1) <=> ($b === 'edukatif_only' ? 0 : 1) ?: strcmp($a, $b));

        $preparer = app(EvalTenantPreparer::class);
        $hasil = [];

        foreach ($kelompok as $level => $daftar) {
            $preparer->terapkanKonteksSyariah($level, $this->aktorSyariah);

            foreach ($daftar as $kasus) {
                $hasil[] = $runner->jalankan($kasus, $ctx);
            }
        }

        return $hasil;
    }

    /** @var array{admin: User, kyai: User}|array{} */
    private array $aktorSyariah = [];

    /**
     * Konteks run: pada tenant khusus eval = fondasi (roles, setting default,
     * aktor sintetis) + setup data kasus tool suite faktual. Pada tenant
     * nyata = baca-saja (tanpa aktor; kasus ber-setup dilewati runner).
     *
     * @param  Collection<int, AiEvalCase>  $kasusSuite
     */
    private function siapkanKonteks(Tenant $tenant, bool $tenantKhususEval, string $suite, $kasusSuite): EvalKonteks
    {
        if (! $tenantKhususEval) {
            return new EvalKonteks((string) $tenant->getKey(), tenantKhususEval: false);
        }

        $preparer = app(EvalTenantPreparer::class);
        $aktor = $preparer->siapkanDasar((string) $tenant->getKey());
        $this->aktorSyariah = ['admin' => $aktor['admin'], 'kyai' => $aktor['kyai']];

        $petaSantri = [];

        if ($suite === 'faktual') {
            $kasusTool = $kasusSuite->filter(
                fn (AiEvalCase $k): bool => isset(((array) $k->harapan)['detail']['tool']),
            );

            $petaSantri = $preparer->siapkanDataTool($kasusTool, $aktor['wali']);
        }

        return new EvalKonteks(
            (string) $tenant->getKey(),
            tenantKhususEval: true,
            pemanggilAdmin: $aktor['admin'],
            pemanggilWali: $aktor['wali'],
            petaSantri: $petaSantri,
        );
    }

    /** Tenant sandbox khusus eval — dibuat sekali, dipakai ulang antar run. */
    private function siapkanTenantEval(): ?Tenant
    {
        $id = (string) config('ai.eval.tenant_id', 'eval-khadim');

        try {
            $tenant = Tenant::query()->find($id);

            if ($tenant !== null) {
                return $tenant;
            }

            $this->info("Membuat tenant khusus eval '{$id}' (sekali saja, dipakai ulang antar run)...");

            $tenant = Tenant::create([
                'id' => $id,
                'name' => 'Tenant Eval Khadim Digital',
                'is_active' => true,
            ]);

            $tenant->domains()->create(['domain' => "{$id}.localhost"]);

            // Aktifkan modul 'ai' untuk tenant eval (tenant_modules, central)
            // — efektif saat status modul 'released'. Selama masih
            // 'development', modul hanya tampil untuk tenant developer
            // (ModuleService::DEV_TENANTS) → gunakan --tenant=demo (lihat
            // peringatan di handle()).
            DB::connection((string) config('tenancy.database.central_connection'))
                ->table('tenant_modules')
                ->updateOrInsert(
                    ['tenant_id' => $id, 'module_name' => 'ai'],
                    ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                );

            return $tenant;
        } catch (Throwable $e) {
            $this->error('Gagal menyiapkan tenant eval: '.$e->getMessage());

            return null;
        }
    }

    // ------------------------------------------------------------------
    // Snapshot model + prompt (kontrak: ganti model/prompt = eval ulang)
    // ------------------------------------------------------------------

    /** @return array{0: string, 1: array<string, array{versi_id: int|null, source: string}>} */
    private function snapshotModelPrompt(string $tenantId): array
    {
        $selection = app(ModelRouter::class)->resolve(
            (string) config('ai.eval.agent_key', 'uji'),
            'ekonomis',
        );

        $promptVersi = [];

        foreach ((array) config('ai.eval.agen_snapshot', ['uji', 'wali_chat']) as $agent) {
            $pilihan = app(PromptRegistry::class)->resolve((string) $agent, $tenantId);

            $promptVersi[(string) $agent] = [
                'versi_id' => $pilihan->promptVersionId,
                'source' => $pilihan->source,
            ];
        }

        return [$selection->provider.'/'.$selection->model, $promptVersi];
    }

    // ------------------------------------------------------------------
    // Laporan & gate
    // ------------------------------------------------------------------

    /**
     * @param  list<EvalCaseResult>  $hasil
     * @param  array<string, array{versi_id: int|null, source: string}>  $promptVersi
     */
    private function laporkan(string $suite, string $tenantId, array $hasil, string $model, array $promptVersi, int $mulai): int
    {
        $lulus = array_values(array_filter($hasil, fn (EvalCaseResult $r): bool => $r->status === EvalCaseResult::STATUS_LULUS));
        $gagal = array_values(array_filter($hasil, fn (EvalCaseResult $r): bool => $r->status === EvalCaseResult::STATUS_GAGAL));
        $dilewati = array_values(array_filter($hasil, fn (EvalCaseResult $r): bool => $r->status === EvalCaseResult::STATUS_DILEWATI));

        $total = count($lulus) + count($gagal);

        if ($total === 0) {
            $this->error('Tidak ada kasus yang dievaluasi (semua dilewati?) — gate TIDAK bisa dinyatakan lulus.');

            return self::FAILURE;
        }

        $skor = count($lulus) / $total;
        $ambang = (float) config("ai.eval.ambang.{$suite}", 1.0);
        $lulusGate = $skor >= $ambang;
        $durasiMs = (int) ((hrtime(true) - $mulai) / 1_000_000);

        if ($gagal !== []) {
            $this->table(
                ['Kasus GAGAL', 'Alasan'],
                array_map(fn (EvalCaseResult $r): array => [$r->kode, mb_substr($r->alasan, 0, 110)], $gagal),
            );
        }

        if ($dilewati !== []) {
            $this->line(sprintf(
                '%d kasus dilewati (ber-setup, tenant nyata): %s',
                count($dilewati),
                implode(', ', array_map(fn (EvalCaseResult $r): string => $r->kode, array_slice($dilewati, 0, 10))),
            ));
        }

        $this->table(
            ['Suite', 'Tenant', 'Total', 'Lulus', 'Gagal', 'Skor', 'Ambang', 'Gate', 'Model', 'Durasi'],
            [[
                $suite,
                $tenantId,
                $total,
                count($lulus),
                count($gagal),
                sprintf('%.2f%%', $skor * 100),
                sprintf('%.2f%%', $ambang * 100),
                $lulusGate ? 'LULUS' : 'GAGAL',
                $model,
                $durasiMs.' ms',
            ]],
        );

        if (! $this->option('dry')) {
            AiEvalRun::query()->create([
                'suite' => $suite,
                'total' => $total,
                'lulus' => count($lulus),
                'gagal' => count($gagal),
                'skor' => round($skor, 4),
                'ambang' => $ambang,
                'lulus_gate' => $lulusGate,
                'model' => $model,
                'prompt_versi' => $promptVersi,
                'detail_gagal' => [
                    'gagal' => array_map(
                        fn (EvalCaseResult $r): array => ['kode' => $r->kode, 'alasan' => mb_substr($r->alasan, 0, 300)],
                        array_slice($gagal, 0, 100),
                    ),
                    'dilewati' => array_map(
                        fn (EvalCaseResult $r): array => ['kode' => $r->kode, 'alasan' => mb_substr($r->alasan, 0, 300)],
                        array_slice($dilewati, 0, 100),
                    ),
                ],
                'durasi_ms' => $durasiMs,
                'tenant_id' => $tenantId,
                'dijalankan_oleh' => get_current_user() ?: null,
                'created_at' => now(),
            ]);
        } else {
            $this->line('Mode --dry: hasil TIDAK ditulis ke ai_eval_run.');
        }

        if (! $lulusGate) {
            $this->error(sprintf(
                'GATE EVAL GAGAL: skor %s di bawah ambang %s — fitur terkait TIDAK dirilis (Blueprint §10).',
                sprintf('%.2f%%', $skor * 100),
                sprintf('%.2f%%', $ambang * 100),
            ));

            return self::FAILURE;
        }

        $this->info('Gate eval LULUS.');

        return self::SUCCESS;
    }

    private function tampilkanRiwayat(): int
    {
        $runs = AiEvalRun::query()->orderByDesc('id')->limit(10)->get();

        if ($runs->isEmpty()) {
            $this->info('Belum ada run eval tercatat di ai_eval_run.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Waktu', 'Suite', 'Tenant', 'Total', 'Lulus', 'Gagal', 'Skor', 'Ambang', 'Gate', 'Model', 'Durasi'],
            $runs->map(fn (AiEvalRun $run): array => [
                $run->id,
                (string) $run->created_at,
                $run->suite,
                (string) $run->tenant_id,
                $run->total,
                $run->lulus,
                $run->gagal,
                sprintf('%.2f%%', (float) $run->skor * 100),
                sprintf('%.2f%%', (float) $run->ambang * 100),
                $run->lulus_gate ? 'LULUS' : 'GAGAL',
                $run->model,
                $run->durasi_ms.' ms',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
