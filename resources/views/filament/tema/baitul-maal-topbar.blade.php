@if (\App\Support\PanelTheme::bodyClass() === 'tema-baitul-maal')
    @php
        // Badge periode: Hijriah · Masehi (ala Topbar desain Baitul Maal).
        $masehi = now()->translatedFormat('j F Y');
        $hijriah = null;
        try {
            $fmt = new \IntlDateFormatter(
                'id_ID@calendar=islamic-civil',
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE,
                config('app.timezone'),
                \IntlDateFormatter::TRADITIONAL,
                'd MMMM yyyy',
            );
            $hijriah = $fmt->format(now()->getTimestamp()).' H';
        } catch (\Throwable $e) {
            // intl tanpa data kalender islam → tampilkan masehi saja.
        }
    @endphp
    <span class="tema-bm-periode" title="Periode berjalan">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:0.8rem;height:0.8rem"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
        {{ $hijriah ? $hijriah.' · ' : '' }}{{ $masehi }}
    </span>
@endif
