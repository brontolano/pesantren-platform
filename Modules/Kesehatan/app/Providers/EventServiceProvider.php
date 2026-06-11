<?php

namespace Modules\Kesehatan\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Kesehatan\Models\KunjunganUks;
use Modules\Kesehatan\Observers\KunjunganUksObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    public function boot(): void
    {
        parent::boot();

        // Register KunjunganUks observer untuk auto-sync PresensiSantri
        KunjunganUks::observe(KunjunganUksObserver::class);
    }

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
