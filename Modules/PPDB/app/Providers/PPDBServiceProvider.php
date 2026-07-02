<?php

namespace Modules\PPDB\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\PPDB\Console\IngatkanDaftarUlangCommand;
use Modules\PPDB\Http\Middleware\EnsureUserIsPeserta;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Policies\CalonSantriPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PPDBServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'PPDB';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'ppdb';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        IngatkanDaftarUlangCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        // F1.1.6 — gerbang area peserta /spmb/* (alias dipakai di routes/web.php).
        Route::aliasMiddleware('peserta', EnsureUserIsPeserta::class);

        // F1.1.6 — peserta hanya boleh menyentuh calon_santri MILIKNYA;
        // staf ERP tetap lewat permission view_santri/manage_santri.
        Gate::policy(CalonSantri::class, CalonSantriPolicy::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
