@props(['title', 'description' => null])

<header {{ $attributes->class(['pu-page-header']) }}>
    <div class="pu-page-header__copy">
        <h1 class="pu-page-header__title">{{ $title }}</h1>
        @if ($description)
            <p class="pu-page-header__description">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="pu-page-header__actions">{{ $actions }}</div>
    @endif
</header>
