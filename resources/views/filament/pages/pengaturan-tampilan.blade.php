<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Tips ringkas 1 baris --}}
        <p class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
            💡 Pilih satu <strong>Paket Tema</strong> di bawah, klik <strong>Terapkan</strong>, lalu muat ulang halaman — berlaku untuk semua pengguna di pesantren ini.
        </p>

        {{-- ==== PAKET TEMA (Standar + tema eksternal jadi) ==== --}}
        <div class="space-y-3">
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">📦 Paket Tema</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Tema jadi dari komunitas Filament — mengubah tampilan komponen menyeluruh, bukan sekadar warna.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->packages as $pkg)
                    @php($isActivePkg = $this->activePackageKey === $pkg['key'])
                    @php($isRecommended = $this->packageRecommended($pkg['key']))
                    <div
                        class="group relative flex flex-col overflow-hidden rounded-xl border-2 transition-all
                            {{ $isActivePkg
                                ? 'border-emerald-500 ring-2 ring-emerald-200 dark:border-emerald-400 dark:ring-emerald-900'
                                : 'border-gray-200 bg-white hover:border-emerald-300 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-emerald-400'
                            }}">

                        {{-- Thumbnail SVG generatif (warna khas tema) --}}
                        <div class="aspect-[16/10] w-full overflow-hidden border-b border-gray-100 bg-gray-50 dark:border-gray-700">
                            {!! $this->packageThumbnail($pkg['key']) !!}
                        </div>

                        {{-- Badge status --}}
                        <div class="absolute right-2 top-2 flex flex-col items-end gap-1">
                            @if ($isActivePkg)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 px-2 py-0.5 text-xs font-semibold text-white shadow">
                                    ✓ Aktif
                                </span>
                            @endif
                            @if ($isRecommended)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-400 px-2 py-0.5 text-xs font-semibold text-amber-900 shadow">
                                    ★ Disarankan
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-3">
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ $pkg['name'] }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">{{ $pkg['description'] }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>🔤 {{ $pkg['typography'] }}</span>
                                <span>{{ $pkg['category'] === 'eksternal' ? '✨ Tema Eksternal' : '🧭 Sidebar' }}</span>
                            </div>

                            @if ($pkg['features'] ?? false)
                                <ul class="mt-2 space-y-0.5">
                                    @foreach (array_slice($pkg['features'], 0, 2) as $feature)
                                        <li class="text-xs text-gray-500 dark:text-gray-500">• {{ $feature }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-auto pt-3">
                                <button
                                    wire:click="applyPackage('{{ $pkg['key'] }}')"
                                    type="button"
                                    @class([
                                        'inline-flex w-full items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold transition-all',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $isActivePkg,
                                        'bg-emerald-600 text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600' => ! $isActivePkg,
                                    ])>
                                    {{ $isActivePkg ? '✓ Sedang Dipakai' : 'Terapkan Paket' }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
