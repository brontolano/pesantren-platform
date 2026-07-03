<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Header Tips --}}
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/20">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100">💡 Tips Memilih Tema</h3>
            <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                Pilih tata letak & warna sesuai peran Anda. Tema akan diterapkan untuk semua pengguna di pesantren ini.
            </p>
        </div>

        {{-- ==== PAKET TEMA SIAP-PAKAI (bundel 1-klik) ==== --}}
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">📦 Paket Tema Siap-Pakai</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Pilih satu paket lengkap (tata letak + warna + tipografi) — langsung diterapkan dalam satu klik.
                    Ingin atur manual? Gunakan seksi <em>Custom</em> di bawah.
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

                        {{-- Thumbnail SVG generatif (warna asli tema) --}}
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
                                    ★ Disarankan untuk Anda
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ $pkg['name'] }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $pkg['description'] }}</p>

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>🔤 {{ $pkg['typography'] }}</span>
                                <span>🧭 {{ $pkg['category'] === 'topnav' ? 'Menu Atas' : 'Sidebar' }}</span>
                            </div>

                            @if ($pkg['features'] ?? false)
                                <ul class="mt-2 space-y-1">
                                    @foreach (array_slice($pkg['features'], 0, 3) as $feature)
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

        {{-- ==== CUSTOM (Tema + Warna) — pengaturan manual lanjutan ==== --}}
        <div class="border-t border-gray-200 pt-8 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">🛠️ Custom (Tema + Warna)</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Untuk pengguna lanjutan — pilih tata letak & palet warna secara terpisah.
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

                            {{-- Thumbnail SVG generatif (warna asli tema) --}}
                            <div class="mb-3 aspect-[16/10] w-full overflow-hidden rounded-md border border-gray-100 bg-gray-50 dark:border-gray-700">
                                {!! $this->themeThumbnail($theme['key']) !!}
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($theme['icon'] ?? false)
                                    <x-filament::icon :icon="$theme['icon']" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                @endif
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $theme['label'] }}</h3>
                            </div>
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

        {{-- Live Preview iFrame --}}
        <div
            class="space-y-4 border-t border-gray-200 pt-8 dark:border-gray-700"
            x-data="{ device: 'desktop', dark: false }"
        >
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">👁️ Pratinjau Langsung</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Preview diperbarui real-time saat Anda memilih tema, warna, tata letak, mode gelap & ukuran layar.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Mode responsif --}}
                    <div class="inline-flex rounded-lg border border-gray-300 p-0.5 dark:border-gray-600">
                        @foreach (['desktop' => '🖥️ Desktop', 'tablet' => '📱 Tablet', 'mobile' => '📱 Mobile'] as $mode => $label)
                            <button
                                type="button"
                                @click="device = '{{ $mode }}'"
                                :class="device === '{{ $mode }}'
                                    ? 'bg-emerald-600 text-white'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    {{-- Toggle dark mode --}}
                    <button
                        type="button"
                        @click="dark = !dark; $refs.previewFrame && $refs.previewFrame.contentDocument && $refs.previewFrame.contentDocument.documentElement.classList.toggle('dark', dark)"
                        :class="dark
                            ? 'border-emerald-500 bg-emerald-600 text-white'
                            : 'border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700'"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
                    >
                        <span x-text="dark ? '🌙 Gelap' : '☀️ Terang'"></span>
                    </button>
                </div>
            </div>

            <div class="flex justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
                <iframe
                    id="theme-preview-frame"
                    x-ref="previewFrame"
                    class="h-96 rounded-md border border-gray-200 bg-white shadow-sm transition-all duration-300 dark:border-gray-700"
                    :style="device === 'mobile' ? 'width:375px' : (device === 'tablet' ? 'width:768px' : 'width:100%')"
                    title="Preview Tema"
                    srcdoc="{{ htmlspecialchars($this->generatePreviewHtml()) }}"
                    @load="$refs.previewFrame.contentDocument && $refs.previewFrame.contentDocument.documentElement.classList.toggle('dark', dark)"
                ></iframe>
            </div>

            <script>
                // Real-time injection ke iFrame saat Livewire update:
                // CSS var (warna) + bodyClass (tata letak). Nilai berasal
                // dari server (registry PanelTheme), bukan input user.
                document.addEventListener('livewire:updated', function () {
                    const frame = document.getElementById('theme-preview-frame');
                    if (!frame || !frame.contentDocument) return;

                    const style = frame.contentDocument.querySelector('style[data-theme-css]');
                    if (style) {
                        style.textContent = ':root { ' + @js($this->previewCssVars()) + ' }';
                    }

                    // Ganti className body → user melihat perubahan LAYOUT.
                    const body = frame.contentDocument.body;
                    if (body) {
                        body.className = @js($this->previewBodyClass());
                    }
                });
            </script>
        </div>

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
