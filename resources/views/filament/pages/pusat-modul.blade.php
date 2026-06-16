<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Banner sambutan --}}
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 px-5 py-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
            <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">Pilih fitur yang pesantren Anda butuhkan.</p>
            <p class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-300/80">
                Tak perlu menyalakan semuanya sekaligus. Pasang fitur secukupnya, lengkapi datanya, lalu menunya muncul.
                Bisa menambah atau menonaktifkan kapan saja — datanya tetap aman.
            </p>
        </div>

        {{-- DISARANKAN --}}
        @if (! empty($disarankan))
            <section>
                <h3 class="mb-1 text-base font-semibold text-stone-900 dark:text-stone-100">Disarankan untuk Anda</h3>
                <p class="mb-3 text-sm text-stone-500 dark:text-stone-400">Cocok dengan profil pesantren Anda. Bisa dipasang sekaligus lewat tombol di atas.</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($disarankan as $m)
                        @include('filament.pages.partials.modul-kartu', ['m' => $m, 'mode' => 'pasang', 'badge' => 'Disarankan'])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- PERLU DILENGKAPI --}}
        @if (! empty($perluDisiapkan))
            <section>
                <h3 class="mb-1 text-base font-semibold text-amber-700 dark:text-amber-400">Perlu dilengkapi</h3>
                <p class="mb-3 text-sm text-stone-500 dark:text-stone-400">Sudah dipasang, tinggal isi datanya supaya siap dipakai & muncul di menu.</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($perluDisiapkan as $m)
                        @include('filament.pages.partials.modul-kartu', ['m' => $m, 'mode' => 'lanjutkan', 'badge' => null])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- AKTIF & SIAP (inti + ready) --}}
        <section>
            <h3 class="mb-1 text-base font-semibold text-stone-900 dark:text-stone-100">Sudah aktif</h3>
            <p class="mb-3 text-sm text-stone-500 dark:text-stone-400">Fitur yang sudah jalan di pesantren Anda.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($inti as $m)
                    <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/40 px-4 py-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <x-filament::icon icon="heroicon-s-lock-closed" class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        <div class="min-w-0">
                            <span class="block text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $m['label'] }}</span>
                            <span class="block text-xs text-stone-500 dark:text-stone-400">Bawaan — selalu aktif</span>
                        </div>
                    </div>
                @endforeach
                @foreach ($aktif as $m)
                    @include('filament.pages.partials.modul-kartu', ['m' => $m, 'mode' => 'aktif', 'badge' => null])
                @endforeach
            </div>
        </section>

        {{-- FITUR LAINNYA --}}
        @if (! empty($lainnya))
            <section>
                <h3 class="mb-1 text-base font-semibold text-stone-900 dark:text-stone-100">Fitur lainnya</h3>
                <p class="mb-3 text-sm text-stone-500 dark:text-stone-400">Pasang bila dibutuhkan nanti.</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($lainnya as $m)
                        @include('filament.pages.partials.modul-kartu', ['m' => $m, 'mode' => 'pasang', 'badge' => null])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
