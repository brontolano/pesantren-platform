@props(['title' => 'Pesantren.online'])

<div {{ $attributes->class(['pu-shell']) }}>
    <a class="pu-skip-link" href="#pu-main">Lewati ke konten utama</a>

    <aside class="pu-shell__sidebar" aria-label="Navigasi utama">
        {{ $sidebar ?? '' }}
    </aside>

    <div class="pu-shell__workspace">
        <header class="pu-shell__header">
            <div class="pu-shell__mobile-nav">
                {{ $mobileNav ?? '' }}
            </div>
            <div class="pu-shell__heading">
                <p class="pu-shell__brand">{{ $title }}</p>
                {{ $topbar ?? '' }}
            </div>
        </header>

        <main id="pu-main" class="pu-shell__main" tabindex="-1">
            {{ $slot }}
        </main>
    </div>
</div>