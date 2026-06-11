@if (\App\Support\PanelTheme::bodyClass() === 'tema-baitul-maal')
    <footer class="tema-bm-footer">
        <span>© {{ now()->year }} <b>{{ \App\Support\TenantBranding::name(tenant('name') ?? 'Pesantren') }}</b> — pesantren.online</span>
        <span class="tema-bm-footer-gold">Khidmah digital untuk pesantren Indonesia 🌙</span>
    </footer>
@endif
