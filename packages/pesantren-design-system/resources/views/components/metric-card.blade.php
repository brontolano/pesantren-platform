@props(['label', 'value', 'hint' => null, 'icon' => 'dashboard'])

<article {{ $attributes->class(['pu-metric-card']) }}>
    <span class="pu-metric-card__label">{{ $label }}</span>
    <strong class="pu-metric-card__value">{{ $value }}</strong>
    @if ($hint)
        <span class="pu-metric-card__hint">{{ $hint }}</span>
    @endif
    <span class="pu-metric-card__icon"><x-pesantren-ui::icon :name="$icon" /></span>
</article>
