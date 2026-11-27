<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ruang kerja') · Pesantren.online v2</title>
    <link rel="stylesheet" href="{{ asset('vendor/pesantren-ui/pesantren-ui.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<x-pesantren-ui::app-shell title="Pesantren.online">
    <x-slot:sidebar>
        @if(($panelType ?? 'platform') === 'platform')
            <a class="brand" href="{{ route('platform.dashboard') }}"><span class="brand-mark" aria-hidden="true">P</span><span>Pesantren.online</span></a>
            <nav aria-label="Ruang platform">
                <p class="nav-label">Ruang platform</p>
                <ul class="nav-list">
                    <li><a class="nav-link" aria-current="{{ request()->routeIs('platform.dashboard') ? 'page' : 'false' }}" href="{{ route('platform.dashboard') }}">Ringkasan</a></li>
                    <li><a class="nav-link" aria-current="{{ request()->routeIs('platform.organizations.*') ? 'page' : 'false' }}" href="{{ route('platform.organizations.index') }}">Organisasi</a></li>
                    <li><a class="nav-link" aria-current="{{ request()->routeIs('platform.modules') ? 'page' : 'false' }}" href="{{ route('platform.modules') }}">Modul</a></li>
                    <li><a class="nav-link" aria-current="{{ request()->routeIs('platform.activity') ? 'page' : 'false' }}" href="{{ route('platform.activity') }}">Log Aktivitas</a></li>
                </ul>
            </nav>
            <p class="sidebar-note">Panel operator pusat. Data santri tidak tersedia di ruang ini.</p>
        @else
            <a class="brand" href="{{ route('tenant.dashboard') }}"><span class="brand-mark" aria-hidden="true">{{ mb_substr($organization->name, 0, 1) }}</span><span>{{ $organization->name }}</span></a>
            <nav aria-label="Ruang organisasi">
                <p class="nav-label">Ruang organisasi</p>
                <ul class="nav-list">
                    <li><a class="nav-link" aria-current="{{ request()->routeIs('tenant.dashboard') ? 'page' : 'false' }}" href="{{ route('tenant.dashboard') }}">Beranda</a></li>
                    <li><a class="nav-link" aria-current="{{ request()->routeIs('tenant.modules') ? 'page' : 'false' }}" href="{{ route('tenant.modules') }}">Modul</a></li>
                </ul>
            </nav>
        @endif
    </x-slot:sidebar>
    <x-slot:mobileNav>
        <nav class="pu-mobile-links" aria-label="Navigasi ponsel">
            @if(($panelType ?? 'platform') === 'platform')
                <a href="{{ route('platform.dashboard') }}">Ringkasan</a>
                <a href="{{ route('platform.organizations.index') }}">Organisasi</a>
                <a href="{{ route('platform.modules') }}">Modul</a>
                <a href="{{ route('platform.activity') }}">Aktivitas</a>
            @else
                <a href="{{ route('tenant.dashboard') }}">Beranda</a>
                <a href="{{ route('tenant.modules') }}">Modul</a>
            @endif
        </nav>
    </x-slot:mobileNav>
    <x-slot:topbar>
        <div class="pu-shell__topbar-actions">
            <div class="topbar-meta">{{ ($panelType ?? 'platform') === 'platform' ? auth('platform')->user()?->name : auth('tenant')->user()?->name }}</div>
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
