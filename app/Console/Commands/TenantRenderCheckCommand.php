<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Render dashboard /admin tenant sebagai user super_admin, cetak status HTTP
 * sebagai JSON satu baris: {"status": 200}.
 *
 * Dipanggil TenantHealthCheck via proses terpisah — Auth::login di sini tidak
 * menyentuh sesi siapa pun. Teknik sama dengan TenantPanelAuthedSmokeTest:
 * halaman login tetap 200 walau panel rusak, jadi HARUS render ter-autentikasi.
 */
class TenantRenderCheckCommand extends Command
{
    protected $signature = 'tenant:render-check {tenant : ID tenant}';

    protected $description = 'Render authed /admin tenant, output JSON {"status": <http_code>}';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if (! $tenant) {
            $this->line(json_encode(['status' => 0, 'error' => 'tenant tidak ditemukan']));

            return self::FAILURE;
        }

        // Host: domain terdaftar pertama; fallback {id}.localhost (pola smoke test).
        $host = $tenant->domains()->orderByDesc('is_primary')->value('domain')
            ?? $tenant->getKey().'.localhost';

        $status = $tenant->run(function () use ($host): int {
            $admin = User::withoutGlobalScopes()
                ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->first();
            if (! $admin) {
                return -1;
            }
            Auth::login($admin);

            $req = Request::create("https://{$host}/admin", 'GET');
            $req->headers->set('Host', $host);

            return app(Kernel::class)->handle($req)->getStatusCode();
        });

        if ($status === -1) {
            $this->line(json_encode(['status' => 0, 'error' => 'tidak ada user super_admin']));

            return self::FAILURE;
        }

        $this->line(json_encode(['status' => $status]));

        return $status < 500 ? self::SUCCESS : self::FAILURE;
    }
}
