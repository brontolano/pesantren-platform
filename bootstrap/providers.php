<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\TenantPanelProvider::class,
    // Panel Filament /portal (PortalPanelProvider) sudah DIHAPUS — portal peserta
    // disatukan ke PWA /spmb (PesertaAuthController). /portal/* diredirect ke /spmb/login.
];
