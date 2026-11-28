@props(['title' => 'Pesantren.online', 'context' => 'Ruang kerja'])

<div {{ $attributes->class(['pu-shell']) }} data-app-shell>
    <a class="pu-skip-link" href="#pu-main">Lewati ke konten utama</a>

    <aside class="pu-shell__sidebar" aria-label="Navigasi utama">
        {{ $sidebar ?? '' }}
    </aside>

    <div class="pu-shell__workspace">
        <header class="pu-shell__header">
            <div class="pu-shell__mobile-start">
                <button class="pu-mobile-menu-button" type="button" data-drawer-toggle aria-controls="pu-mobile-drawer" aria-expanded="false">
                    <x-pesantren-ui::icon name="menu" />
                    <span>Menu</span>
                </button>
                <span class="pu-shell__mobile-brand">{{ $title }}<span class="pu-shell__mobile-context">{{ $context }}</span></span>
            </div>
            <div class="pu-shell__heading">
                {{ $topbar ?? '' }}
            </div>
        </header>

        <main id="pu-main" class="pu-shell__main" tabindex="-1">
            {{ $slot }}
        </main>
    </div>

    <div class="pu-shell__drawer" id="pu-mobile-drawer" data-mobile-drawer hidden>
        <button class="pu-shell__drawer-backdrop" type="button" data-drawer-close tabindex="-1" aria-label="Tutup menu navigasi"></button>
        <div class="pu-shell__drawer-panel" role="dialog" aria-modal="true" aria-label="Navigasi {{ $context }}" tabindex="-1">
            <div class="pu-shell__drawer-head">
                <span>{{ $title }} · {{ $context }}</span>
                <button class="pu-shell__drawer-close" type="button" data-drawer-close aria-label="Tutup menu">
                    <x-pesantren-ui::icon name="close" />
                </button>
            </div>
            <aside aria-label="Navigasi {{ $context }}">{{ $sidebar ?? '' }}</aside>
        </div>
    </div>
</div>
