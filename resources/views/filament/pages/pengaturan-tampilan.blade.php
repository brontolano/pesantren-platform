<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Header Tips --}}
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/20">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100">💡 Tips Memilih Tema</h3>
            <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                Pilih tata letak & warna sesuai peran Anda. Tema akan diterapkan untuk semua pengguna di pesantren ini.
            </p>
        </div>

        {{-- Themes by Category --}}
        @foreach ($this->themesByCategory as $category => $themes)
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ \App\Support\PanelTheme::categoryLabel($category) }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @switch($category)
                            @case('sidebar')
                                Menu di sisi kiri (klasik & familiar)
                                @break
                            @case('topnav')
                                Menu di atas (lebih luas untuk pekerjaan)
                                @break
                            @case('utility')
                                Variasi khusus untuk efisiensi data
                                @break
                        @endswitch
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($themes as $theme)
                        <button
                            wire:click="selectTheme('{{ $theme['key'] }}')"
                            type="button"
                            class="group relative rounded-lg border-2 p-4 text-left transition-all
                                {{ $this->previewTheme === $theme['key']
                                    ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                    : 'border-gray-200 bg-white hover:border-emerald-300 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-emerald-400'
                                }}">
                            @if ($this->previewTheme === $theme['key'])
                                <div class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg">
                                    ✓
                                </div>
                            @endif

                            <h3 class="font-semibold text-gray-900 dark:text-white">{{ $theme['label'] }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $theme['desc'] }}</p>

                            @if ($theme['features'] ?? false)
                                <ul class="mt-2 space-y-1">
                                    @foreach (array_slice($theme['features'], 0, 2) as $feature)
                                        <li class="text-xs text-gray-500 dark:text-gray-500">• {{ $feature }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($theme['recommended_roles'] ?? false)
                                <div class="mt-3 flex flex-wrap gap-1">
                                    @foreach (array_slice($theme['recommended_roles'], 0, 2) as $role)
                                        <span class="inline-block rounded bg-gray-100 px-2 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ str()->headline($role) }}
                                        </span>
                                    @endforeach
                                    @if (count($theme['recommended_roles'] ?? []) > 2)
                                        <span class="text-xs text-gray-500">+{{ count($theme['recommended_roles'] ?? []) - 2 }}</span>
                                    @endif
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- Color Presets (jika applicable) --}}
        @if (!$this->applicableColors()['locked'])
            <div class="space-y-4 border-t border-gray-200 pt-8 dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">🎨 Palet Warna</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pilih preset warna untuk tema ini.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($this->applicableColors()['colors'] as $color)
                        @if (is_array($color) && isset($color['key']))
                            <button
                                wire:click="selectColor('{{ $color['key'] }}')"
                                type="button"
                                class="rounded-lg border-2 p-3 text-left transition-all
                                    {{ $this->previewColor === $color['key']
                                        ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/20'
                                        : 'border-gray-200 bg-white hover:border-emerald-300 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-emerald-400'
                                    }}">
                                <div class="flex items-center gap-2">
                                    @if ($color['primary'])
                                        <div class="h-5 w-5 rounded-full ring-1 ring-gray-300 dark:ring-gray-600" style="background-color: {{ $color['primary'] }}"></div>
                                    @endif
                                    <div>
                                        <p class="text-xs font-medium text-gray-900 dark:text-white">{{ $color['label'] }}</p>
                                    </div>
                                </div>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-900/20">
                <p class="text-sm text-amber-800 dark:text-amber-200">
                    🔒 <strong>Palet Terkunci:</strong> {{ $this->applicableColors()['reason'] }}
                </p>
            </div>
        @endif

        {{-- Action Buttons --}}
        <div class="flex gap-3 border-t border-gray-200 pt-6 dark:border-gray-700">
            @if ($this->previewTheme !== $this->selectedTheme || $this->previewColor !== $this->selectedColor)
                <button
                    wire:click="resetToSaved"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800">
                    ↶ Batal
                </button>
            @endif

            <button
                @if ($this->previewTheme === $this->selectedTheme && $this->previewColor === $this->selectedColor)
                    disabled
                    class="inline-flex items-center justify-center rounded-lg bg-gray-300 px-4 py-2 text-sm font-medium text-gray-500 cursor-not-allowed dark:bg-gray-700"
                @else
                    wire:click="saveThemeSettings"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-700 dark:hover:bg-emerald-600"
                @endif
                type="button">
                ✓ Simpan Tampilan
            </button>
        </div>
    </div>
</x-filament-panels::page>
