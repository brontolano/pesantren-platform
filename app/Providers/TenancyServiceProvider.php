<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Event => listener mapping untuk siklus hidup database tenant.
     *
     * - TenantCreated  : buat database fisik + jalankan migrasi tenant
     * - TenantDeleted  : hapus database fisik tenant
     * - Bootstrapping / Ending: switch koneksi DB ke/ dari tenant
     */
    public function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    // Jobs\SeedDatabase::class, // diaktifkan via seeder_parameters bila perlu
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false),
            ],

            // Switch koneksi DB ke/dari database tenant saat request
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
                // WP-31: buang cache label istilah tiap ganti konteks tenant.
                // Penting untuk proses long-running (queue worker pesantren-worker*):
                // tanpa ini, label lama tertahan di memori proses walau tenant sudah
                // mengubah Setting dari panel (proses lain). Web FrankenPHP classic
                // mode tidak terdampak (state mati per request), tapi flush ini aman.
                fn () => \Modules\Core\Support\IstilahRegistry::flushCache(),
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
                fn () => \Modules\Core\Support\IstilahRegistry::flushCache(),
            ],
        ];
    }

    public function boot(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }
}
