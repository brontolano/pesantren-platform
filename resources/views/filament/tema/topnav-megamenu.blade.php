@php
    /**
     * Navigasi atas hibrida (Priority+ + waffle "Semua Menu") untuk tema
     * topNav. Menggantikan deretan grup bawaan Filament yang tidak muat:
     * bar memuat grup PRIORITAS, sisanya tampil SEMUA di mega-panel grid.
     * Self-guard: kosong bila tema aktif bukan topNav.
     */
    $aktifTopnav = \App\Support\PanelTheme::topNavigation();
    $nav = $aktifTopnav ? filament()->getNavigation() : [];

    // Bar minimalis: 2 grup harian saja — sisanya via Semua Menu.
    $prioritas = ['Kesantrian', 'Keuangan'];
    $barGroups = [];      // grup prioritas (dropdown di bar)
    $barItems = [];       // item tanpa grup utk bar (Dasbor saja)
    foreach ($nav as $group) {
        $label = (string) $group->getLabel();
        if ($label === '') {
            foreach ($group->getItems() as $item) {
                if ($item->getLabel() === 'Dasbor') {
                    $barItems[] = $item;
                }
            }
            continue;
        }
        if (in_array($label, $prioritas, true)) {
            $barGroups[$label] = $group;
        }
    }
    // Urutkan bar sesuai daftar prioritas.
    $barGroups = array_replace(array_intersect_key(array_flip($prioritas), $barGroups), $barGroups);
@endphp

@if ($aktifTopnav && $nav !== [])
<ul class="tema-nav-utama" x-data="{ buka: null }" @keydown.escape.window="buka = null">
    {{-- Item langsung (Dasbor) --}}
    @foreach ($barItems as $item)
        <li class="fi-topbar-item {{ $item->isActive() ? 'fi-active' : '' }}">
            <a href="{{ $item->getUrl() }}" class="fi-topbar-item-btn">
                @if ($item->getIcon())
                    <x-filament::icon :icon="$item->getIcon()" class="fi-topbar-item-icon" />
                @endif
                <span class="fi-topbar-item-label">{{ $item->getLabel() }}</span>
            </a>
        </li>
    @endforeach

    {{-- Grup prioritas: dropdown ringkas --}}
    @foreach ($barGroups as $label => $group)
        @php
            $items = collect($group->getItems());
            $grupAktif = $items->contains(fn ($i) => $i->isActive());
        @endphp
        <li class="fi-topbar-item {{ $grupAktif ? 'fi-active' : '' }}" @click.outside="buka === @js($label) && (buka = null)">
            <button type="button" class="fi-topbar-item-btn" @click="buka = (buka === @js($label) ? null : @js($label))">
                <span class="fi-topbar-item-label">{{ $label }}</span>
                <svg class="fi-topbar-group-toggle-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
            </button>
            <div class="tema-nav-dropdown" x-show="buka === @js($label)" x-transition.opacity.duration.100ms x-cloak>
                @foreach ($items as $item)
                    <a href="{{ $item->getUrl() }}" class="tema-nav-dd-item {{ $item->isActive() ? 'aktif' : '' }}">
                        @if ($item->getIcon())
                            <x-filament::icon :icon="$item->getIcon()" class="tema-nav-dd-icon" />
                        @endif
                        <span>{{ $item->getLabel() }}</span>
                    </a>
                @endforeach
            </div>
        </li>
    @endforeach

    {{-- Waffle: SEMUA menu dalam mega-panel grid --}}
    <li class="fi-topbar-item" @click.outside="buka === 'semua' && (buka = null)">
        <button type="button" class="fi-topbar-item-btn" @click="buka = (buka === 'semua' ? null : 'semua')" aria-haspopup="true" :aria-expanded="buka === 'semua'">
            <svg class="fi-topbar-item-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 3h4v4H3V3Zm5 0h4v4H8V3Zm5 0h4v4h-4V3ZM3 8h4v4H3V8Zm5 0h4v4H8V8Zm5 0h4v4h-4V8ZM3 13h4v4H3v-4Zm5 0h4v4H8v-4Zm5 0h4v4h-4v-4Z"/></svg>
            <span class="fi-topbar-item-label">Semua Menu</span>
        </button>
        <div class="tema-nav-mega" x-show="buka === 'semua'" x-transition.opacity.duration.150ms x-cloak>
            <div class="tema-nav-mega-grid">
                @foreach ($nav as $group)
                    @php $label = (string) $group->getLabel(); @endphp
                    <div class="tema-nav-mega-grup">
                        <p class="tema-nav-mega-judul">{{ $label !== '' ? $label : 'Umum' }}</p>
                        @foreach ($group->getItems() as $item)
                            <a href="{{ $item->getUrl() }}" class="tema-nav-dd-item {{ $item->isActive() ? 'aktif' : '' }}">
                                @if ($item->getIcon())
                                    <x-filament::icon :icon="$item->getIcon()" class="tema-nav-dd-icon" />
                                @endif
                                <span>{{ $item->getLabel() }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <p class="tema-nav-mega-hint">Tips: tekan <kbd>Ctrl</kbd>+<kbd>K</kbd> untuk mencari menu lebih cepat.</p>
        </div>
    </li>
</ul>
@endif
