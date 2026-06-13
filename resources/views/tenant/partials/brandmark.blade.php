{{-- Logo tenant (img) atau fallback ikon masjid. Vars dari scope induk: $logoUrl, $pesantren. --}}
@if (! empty($logoUrl))
    <img src="{{ $logoUrl }}" alt="{{ $pesantren }}">
@else
    <svg viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.33A48.4 48.4 0 0 0 12 9.75c-2.55 0-5.06.2-7.5.58V21M3 21h18M12 6.75h.01v.01H12V6.75Z"/></svg>
@endif
