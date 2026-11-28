@props(['title', 'description' => null, 'icon' => 'building'])

<div {{ $attributes->class(['pu-empty-state']) }}>
    <span class="pu-empty-state__icon"><x-pesantren-ui::icon :name="$icon" size="lg" /></span>
    <h2 class="pu-empty-state__title">{{ $title }}</h2>
    @if ($description)
        <p class="pu-empty-state__description">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div class="pu-empty-state__action">{{ $action }}</div>
    @endif
</div>
