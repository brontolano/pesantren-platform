{{-- Logo brand adaptif: mark (kotak emerald + glyph masjid putih) + wordmark.
     Wordmark pakai currentColor → putih di sidebar gelap, gelap di login. --}}
<div class="flex items-center gap-2.5">
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-sm shadow-primary-600/30">
        <svg viewBox="0 0 32 32" class="h-5 w-5" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            {{-- bulan sabit di puncak --}}
            <path d="M16 3.2a2 2 0 1 0 1.6 3.2A2.4 2.4 0 0 1 16 3.2Z" fill="currentColor" stroke="none" />
            {{-- kubah utama --}}
            <path d="M10.5 14c0-3.3 2.5-5.6 5.5-5.6s5.5 2.3 5.5 5.6" />
            {{-- badan bangunan --}}
            <path d="M9 14h14v9.5a1 1 0 0 1-1 1H10a1 1 0 0 1-1-1V14Z" />
            {{-- pintu lengkung --}}
            <path d="M16 24.5v-4a2 2 0 0 0-4 0v4" />
            {{-- dua menara --}}
            <path d="M6 24.5V12.5c0-.8.7-1.5 1.5-1.5S9 11.7 9 12.5" />
            <path d="M26 24.5V12.5c0-.8-.7-1.5-1.5-1.5S23 11.7 23 12.5" />
        </svg>
    </span>
    <span class="text-base font-bold leading-none tracking-tight">
        TU Pesantren
    </span>
</div>
