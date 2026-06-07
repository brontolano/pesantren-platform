<x-filament-widgets::widget>
    <div class="relative isolate overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600 px-6 py-6 text-white shadow-sm sm:px-8 sm:py-7">
        {{-- Ornamen pola samar --}}
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>

        {{-- Ilustrasi siluet masjid (kanan) --}}
        <svg class="pointer-events-none absolute inset-y-0 right-0 h-full w-80 text-white/20"
             viewBox="0 0 320 160" fill="currentColor" preserveAspectRatio="xMaxYMax meet" aria-hidden="true">
            {{-- baseline --}}
            <rect x="0" y="150" width="320" height="10" />
            {{-- menara kiri --}}
            <rect x="92" y="58" width="9" height="92" rx="2" />
            <path d="M88 60c0-6 3.5-11 8.5-11S105 54 105 60Z" />
            <circle cx="96.5" cy="44" r="3" />
            {{-- menara kanan --}}
            <rect x="219" y="58" width="9" height="92" rx="2" />
            <path d="M215 60c0-6 3.5-11 8.5-11s8.5 5 8.5 11Z" />
            <circle cx="223.5" cy="44" r="3" />
            {{-- bangunan utama --}}
            <path d="M120 150V92h80v58Z" />
            {{-- kubah utama --}}
            <path d="M120 92c0-22 18-36 40-36s40 14 40 36Z" />
            <circle cx="160" cy="40" r="4" />
            <path d="M160 36v-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            {{-- kubah samping kiri --}}
            <path d="M104 150v-34h26v34Z" />
            <path d="M104 116c0-10 6-17 13-17s13 7 13 17Z" />
            {{-- kubah samping kanan --}}
            <path d="M190 150v-34h26v34Z" />
            <path d="M190 116c0-10 6-17 13-17s13 7 13 17Z" />
            {{-- pintu lengkung --}}
            <path d="M150 150v-22a10 10 0 0 1 20 0v22Z" fill="#0b3024" />
        </svg>

        <div class="relative z-10 max-w-2xl">
            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">
                Assalamu&rsquo;alaikum, {{ $nama }} <span class="inline-block">&#128075;</span>
            </h2>
            <p class="mt-2 text-sm text-white/85 sm:text-base">
                Selamat datang di Panel Admin. Semoga semua aktivitas hari ini bernilai ibadah.
            </p>
        </div>
    </div>
</x-filament-widgets::widget>
