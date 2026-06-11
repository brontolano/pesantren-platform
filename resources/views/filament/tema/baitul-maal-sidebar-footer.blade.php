@if (\App\Support\PanelTheme::bodyClass() === 'tema-baitul-maal')
    {{-- Kartu "Tenant aktif" di kaki sidebar (ala Sidebar desain Baitul Maal) --}}
    <div class="tema-bm-tenant">
        Pesantren aktif:
        <b>{{ \App\Support\TenantBranding::name(tenant('name') ?? 'Pesantren') }}</b>
    </div>
@endif
