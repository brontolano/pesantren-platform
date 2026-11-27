<section {{ $attributes->class(['pu-card']) }}>
    @if (isset($header))
        <div class="pu-card__header">{{ $header }}</div>
    @endif
    <div class="pu-card__body">{{ $slot }}</div>
    @if (isset($footer))
        <div class="pu-card__footer">{{ $footer }}</div>
    @endif
</section>
