<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ruang kerja') · Pesantren.online</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<x-pesantren-ui::app-shell title="Pesantren.online" :context="($panelType ?? 'platform') === 'platform' ? 'Superadmin' : 'Admin pesantren'">
    <x-slot:sidebar>
        @if(($panelType ?? 'platform') === 'platform')
            <a class="pu-shell__brand" href="{{ route('platform.dashboard') }}">
                <span class="pu-shell__brand-icon"><x-pesantren-ui::icon name="building" size="lg" /></span>
                <span>Pesantren.online</span>
            </a>
            <span class="pu-shell__panel-label">Superadmin</span>
            <nav class="pu-shell__nav" aria-label="Ruang platform">
                <p class="pu-shell__nav-label">Ruang platform</p>
                <ul class="pu-shell__nav-list">
                    <li><a class="pu-shell__nav-link" @if(request()->routeIs('platform.dashboard')) aria-current="page" @endif href="{{ route('platform.dashboard') }}"><x-pesantren-ui::icon name="dashboard" />Ringkasan</a></li>
                    <li><a class="pu-shell__nav-link" @if(request()->routeIs('platform.organizations.*')) aria-current="page" @endif href="{{ route('platform.organizations.index') }}"><x-pesantren-ui::icon name="building" />Organisasi</a></li>
                    <li><a class="pu-shell__nav-link" @if(request()->routeIs('platform.modules')) aria-current="page" @endif href="{{ route('platform.modules') }}"><x-pesantren-ui::icon name="layers" />Modul</a></li>
                    <li><a class="pu-shell__nav-link" @if(request()->routeIs('platform.activity')) aria-current="page" @endif href="{{ route('platform.activity') }}"><x-pesantren-ui::icon name="activity" />Log Aktivitas</a></li>
                </ul>
            </nav>
            <p class="pu-shell__sidebar-note">Panel operator pusat. Data santri tidak tersedia di ruang ini.</p>
        @else
            <a class="pu-shell__brand" href="{{ route('tenant.dashboard') }}">
                <span class="pu-shell__brand-icon"><x-pesantren-ui::icon name="building" size="lg" /></span>
                <span>{{ $organization->name }}</span>
            </a>
            <nav class="pu-shell__nav" aria-label="Ruang organisasi">
                <p class="pu-shell__nav-label">Ruang organisasi</p>
                <ul class="pu-shell__nav-list">
                    <li><a class="pu-shell__nav-link" @if(request()->routeIs('tenant.dashboard')) aria-current="page" @endif href="{{ route('tenant.dashboard') }}"><x-pesantren-ui::icon name="dashboard" />Beranda</a></li>
                    <li><a class="pu-shell__nav-link" @if(request()->routeIs('tenant.modules')) aria-current="page" @endif href="{{ route('tenant.modules') }}"><x-pesantren-ui::icon name="layers" />Modul</a></li>
                </ul>
            </nav>
            <p class="pu-shell__sidebar-note">Ruang kerja {{ $organization->name }}.</p>
        @endif
    </x-slot:sidebar>
    <x-slot:topbar>
        <div class="pu-shell__topbar-actions">
            <span class="pu-shell__user">Assalamu’alaikum, {{ ($panelType ?? 'platform') === 'platform' ? auth('platform')->user()?->name : auth('tenant')->user()?->name }}</span>
            <form method="post" action="{{ ($panelType ?? 'platform') === 'platform' ? route('platform.logout') : route('tenant.logout') }}">
                @csrf
                <x-pesantren-ui::button type="submit" variant="secondary" size="sm">Keluar</x-pesantren-ui::button>
            </form>
        </div>
    </x-slot:topbar>
    @if(session('success')) <x-pesantren-ui::status title="{{ session('success') }}" tone="success" /> @endif
    @if(session('error')) <x-pesantren-ui::status title="{{ session('error') }}" tone="danger" /> @endif
    @if($errors->any())
        <x-pesantren-ui::status title="Periksa kembali formulir." tone="danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-pesantren-ui::status>
    @endif
    @yield('content')
</x-pesantren-ui::app-shell>
</body>
</html>
