<x-filament-widgets::widget>
    {{-- ===== Banner sambutan — gradient WA-green (light) / TikTok-dark (dark) ===== --}}
    <div class="relative isolate overflow-hidden rounded-2xl px-6 py-5 text-white shadow-sm sm:px-8 sm:py-6
                bg-gradient-to-r from-[#004a38] via-[#008069] to-[#00a884]
                dark:from-[#0f1c14] dark:via-[#161823] dark:to-[#1a243a]">

        {{-- Ornamen bulat samar kanan atas --}}
        <div class="pointer-events-none absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute right-32 -bottom-6 h-24 w-24 rounded-full bg-white/5 blur-xl"></div>

        {{-- Ilustrasi siluet masjid (kanan) — lebih terang di dark mode --}}
        <svg class="pointer-events-none absolute inset-y-0 right-0 h-full w-72 text-white/15 dark:text-white/10"
             viewBox="0 0 320 160" fill="currentColor" preserveAspectRatio="xMaxYMax meet" aria-hidden="true">
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
            <path d="M150 150v-22a10 10 0 0 1 20 0v22Z" fill="rgba(0,0,0,0.25)" />
        </svg>

        <div class="relative z-10 max-w-xl">
            <p class="mb-0.5 text-xs font-medium tracking-widest text-white/60 uppercase">
                {{ now()->translatedFormat('l, d F Y') }}
            </p>
            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">
                Assalamu&rsquo;alaikum, <span class="text-white/90">{{ $nama }}</span>
                <span class="inline-block">&#128075;</span>
            </h2>
            <p class="mt-1 text-sm text-white/75">
                Semoga semua aktivitas hari ini bernilai ibadah.
            </p>
        </div>
    </div>
</x-filament-widgets::widget>
