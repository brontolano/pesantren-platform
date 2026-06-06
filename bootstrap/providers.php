<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\TenantPanelProvider::class,
    // PortalPanelProvider (panel Filament /portal) DINONAKTIFKAN — portal peserta
    // disatukan ke PWA /spmb (PesertaAuthController). /portal/* diredirect ke /spmb/login.
];
