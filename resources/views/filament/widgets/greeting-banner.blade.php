<x-filament-widgets::widget>
    {{-- ===== Banner sambutan — gradient WA-green (light) / TikTok-dark (dark) ===== --}}
    <div class="relative isolate overflow-hidden rounded-2xl px-6 py-5 text-white shadow-sm sm:px-8 sm:py-6
                bg-gradient-to-r from-[#004a38] via-[#008069] to-[#00a884]
                dark:from-[#0f1c14] dark:via-[#161823] dark:to-[#1a243a]">

        {{-- Ornamen bulat samar kanan atas --}}
        <div class="pointer-events-none absolute -right-8 -top-8 h-36 w-36 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute right-32 -bottom-6 h-24 w-24 rounded-full bg-white/5 blur-xl"></div>

        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            {{-- Kiri: sambutan --}}
            <div class="max-w-xl">
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

            {{-- Kanan: JADWAL SHOLAT hari ini (pengganti ilustrasi masjid) --}}
            <div class="shrink-0 rounded-2xl bg-white/10 p-3 ring-1 ring-inset ring-white/15 backdrop-blur-sm
                        dark:bg-white/[0.06] dark:ring-white/10">
                <div class="mb-2 flex items-center gap-1.5 px-1 text-[11px] font-semibold uppercase tracking-wider text-white/70">
                    {{-- ikon bulan-sabit kecil --}}
                    <svg viewBox="0 0 16 16" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
                        <path d="M9.4 2.2A5.2 5.2 0 1 0 13 11a4 4 0 1 1-3.6-8.8Z" />
                    </svg>
                    Jadwal Sholat Hari Ini
                </div>
                <div class="flex items-stretch gap-1.5">
                    @foreach ($prayers as $p)
                        @php($isNext = $p['key'] === ($nextPrayer ?? null))
                        <div @class([
                                'flex min-w-[3.25rem] flex-col items-center rounded-xl px-2.5 py-2 text-center transition',
                                'bg-white text-[#008069] shadow-sm dark:bg-[#00a884] dark:text-white' => $isNext,
                                'text-white/85' => ! $isNext,
                            ])>
                            <span @class([
                                    'text-[10px] font-semibold uppercase tracking-wide',
                                    'text-[#008069]/80 dark:text-white/90' => $isNext,
                                    'text-white/60' => ! $isNext,
                                ])>{{ $p['label'] }}</span>
                            <span class="mt-0.5 text-sm font-bold tabular-nums">{{ $p['time'] }}</span>
                            @if ($isNext)
                                <span class="mt-0.5 text-[8px] font-semibold uppercase tracking-wider text-[#008069]/70 dark:text-white/80">berikutnya</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <p class="mt-1.5 px-1 text-[10px] text-white/45">
                    Perkiraan waktu (metode Kemenag) · zona WIB
                </p>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
