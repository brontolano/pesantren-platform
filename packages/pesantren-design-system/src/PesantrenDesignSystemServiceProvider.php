<?php

namespace Brontolano\PesantrenDesignSystem;

use Illuminate\Support\ServiceProvider;

final class PesantrenDesignSystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'pesantren-ui');

        $this->publishes([
            __DIR__.'/../resources/css/pesantren-ui.css' => public_path('vendor/pesantren-ui/pesantren-ui.css'),
        ], 'pesantren-ui-assets');
    }
}
