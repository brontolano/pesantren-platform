<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\TenantPanelProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AdminPanelProvider::class,
    TenantPanelProvider::class,
    // Panel Filament /portal (PortalPanelProvider) sudah DIHAPUS — portal peserta
    // disatukan ke PWA /spmb (PesertaAuthController). /portal/* diredirect ke /spmb/login.
];
