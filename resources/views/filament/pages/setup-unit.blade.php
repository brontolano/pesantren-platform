<x-filament-panels::page>
    @if ($mode === 'hub')
        {{-- Fondasi dulu: Setup Pesantren = pintu UTAMA, halaman ini = lanjutan isi data per unit. --}}
        <a href="{{ $setupUrl }}"
           class="flex items-center gap-3 rounded-2xl border px-5 py-4 transition
               {{ $setup['selesai']
                   ? 'border-emerald-200 bg-emerald-50/50 hover:bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/20'
                   : 'border-amber-300 bg-amber-50/60 hover:bg-amber-50 dark:border-amber-800/50 dark:bg-amber-950/20' }}">
            <x-filament::icon :icon="$setup['selesai'] ? 'heroicon-m-check-badge' : 'heroicon-o-sparkles'"
                class="h-6 w-6 shrink-0 {{ $setup['selesai'] ? 'text-emerald-600' : 'text-amber-600' }}" />
            <div class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-stone-900 dark:text-stone-100">
                    {{ $setup['selesai'] ? 'Fondasi (Setup Pesantren) selesai' : 'Mulai di sini: Setup Pesantren' }}
                </span>
                <span class="block text-xs text-stone-500 dark:text-stone-400">
                    {{ $setup['selesai']
                        ? 'Lanjutkan mengisi data tiap unit di bawah.'
                        : 'Atur fondasi (identitas, lembaga, periode, kebijakan) dulu sebelum mengisi data unit.' }}
                </span>
            </div>
            <span class="text-sm font-semibold {{ $setup['selesai'] ? 'text-emerald-600' : 'text-amber-600' }}">{{ $setup['persen'] }}%</span>
        </a>

        {{-- Penunjuk "Lanjut ke" (#350 poin 3): selalu ada 1 jalan jelas, tanpa mengunci navigasi bebas. --}}
        @if (! empty($lanjut))
            <a href="{{ $lanjut['url'] }}"
               class="flex items-center gap-3 rounded-2xl border border-emerald-300 bg-emerald-50/70 px-5 py-4 transition hover:bg-emerald-50 dark:border-emerald-800/60 dark:bg-emerald-950/30">
                <x-filament::icon icon="heroicon-m-arrow-right-circle" class="h-6 w-6 shrink-0 text-emerald-600" />
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                        Lanjut ke: {{ $lanjut['label'] }}
                    </span>
                    <span class="block text-xs text-emerald-700/80 dark:text-emerald-300/70">
                        Unit berikutnya yang belum selesai. Anda tetap bebas memilih unit lain di bawah.
                    </span>
                </span>
                <span class="shrink-0 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Lanjutkan</span>
            </a>
        @endif

        <p class="text-sm text-stone-500 dark:text-stone-400">
            Selesaikan setup tiap unit secara bertahap. Klik sebuah unit untuk dipandu langkah demi langkah.
            Tandai unit yang tidak dipakai pondok Anda sebagai <strong>opsional</strong> agar tak menahan kesiapan go-live.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($kartu as $u)
                <a href="{{ $u['url'] }}"
                   class="block rounded-2xl border border-stone-200 bg-white p-5 transition hover:border-emerald-300 hover:bg-emerald-50/40 dark:border-white/10 dark:bg-white/5 dark:hover:bg-emerald-950/20">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full
                            {{ $u['progress']['tuntas'] ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-300' }}">
                            <x-filament::icon :icon="$u['progress']['tuntas'] ? 'heroicon-m-check' : $u['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-stone-900 dark:text-stone-100">{{ $u['label'] }}</span>
                            <span class="block text-xs text-stone-500 dark:text-stone-400">
                                {{ $u['progress']['selesai'] }} dari {{ $u['progress']['total'] }} langkah
                            </span>
                        </div>
                        {{-- Toggle wajib/opsional per pesantren (#350 poin 1) — tulis Setting setup.unit_wajib. --}}
                        <button type="button"
                            wire:click.stop.prevent="toggleWajib('{{ $u['kode'] }}')"
                            title="Klik untuk mengubah: unit wajib ikut menahan kesiapan go-live, unit opsional tidak."
                            class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold
                                {{ $u['progress']['wajib']
                                    ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300'
                                    : 'bg-stone-100 text-stone-500 hover:bg-stone-200 dark:bg-white/10 dark:text-stone-400' }}">
                            {{ $u['progress']['wajib'] ? 'Wajib' : 'Opsional' }}
                        </button>
                        <span class="text-sm font-semibold {{ $u['progress']['tuntas'] ? 'text-emerald-600' : 'text-stone-400' }}">
                            {{ $u['progress']['persen'] }}%
                        </span>
                    </div>
                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-stone-200/70 dark:bg-white/10">
                        <div class="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500" style="width: {{ $u['progress']['persen'] }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                @if (! empty($tokoUrl))
                    <a href="{{ $tokoUrl }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">&larr; Pusat Modul</a>
                @endif
                <a href="{{ $hubUrl }}" class="text-sm text-stone-500 hover:text-emerald-700 dark:text-stone-400">Semua unit</a>
            </div>
            <span class="flex items-center gap-2">
                @unless ($wajib)
                    <span class="rounded-full bg-stone-100 px-2.5 py-1 text-[11px] font-semibold text-stone-500 dark:bg-white/10 dark:text-stone-400"
                        title="Unit ini ditandai opsional untuk pesantren Anda — tidak menahan kesiapan go-live.">
                        Opsional untuk pesantren Anda
                    </span>
                @endunless
                <span class="text-sm font-semibold {{ $progress['tuntas'] ? 'text-emerald-600' : 'text-stone-400' }}">
                    {{ $progress['persen'] }}% selesai
                </span>
            </span>
        </div>

        {{-- Penunjuk "Lanjut ke" (#350 poin 3): langkah wajib pertama yang belum beres. --}}
        @if (! empty($nextStep))
            <div class="mt-2 flex items-center gap-3 rounded-xl border border-emerald-300 bg-emerald-50/70 px-4 py-3 dark:border-emerald-800/60 dark:bg-emerald-950/30">
                <x-filament::icon icon="heroicon-m-arrow-right-circle" class="h-5 w-5 shrink-0 text-emerald-600" />
                <span class="min-w-0 flex-1 text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                    Lanjut ke: {{ $nextStep['label'] }}
                </span>
                @if ($nextStep['url'])
                    <a href="{{ $nextStep['url'] }}"
                       class="shrink-0 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                        Buka
                    </a>
                @endif
            </div>
        @endif

        <ul class="mt-2 space-y-2">
            @foreach ($steps as $i => $step)
                <li>
                    <div class="flex items-center gap-3 rounded-xl border px-4 py-3
                        {{ $step['key'] === $nextKey
                            ? 'border-emerald-400 bg-emerald-50/60 dark:border-emerald-700 dark:bg-emerald-950/30'
                            : ($step['done'] ? 'border-emerald-200 bg-white/70 dark:border-emerald-900/40 dark:bg-white/5' : 'border-stone-200 bg-white dark:border-white/10 dark:bg-white/5') }}">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                            {{ $step['done'] ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-500 dark:bg-white/10 dark:text-stone-300' }}">
                            @if ($step['done'])
                                <x-filament::icon icon="heroicon-m-check" class="h-5 w-5" />
                            @else
                                <span class="text-sm font-semibold">{{ $i + 1 }}</span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold {{ $step['done'] ? 'text-stone-400 line-through dark:text-stone-500' : 'text-stone-900 dark:text-stone-100' }}">
                                {{ $step['label'] }}
                            </span>
                            <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $step['desc'] }}</span>
                        </span>
                        @if ($step['url'])
                            <a href="{{ $step['url'] }}"
                               class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold
                                   {{ $step['done']
                                       ? 'text-stone-500 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-white/10'
                                       : 'bg-emerald-600 text-white hover:bg-emerald-700' }}">
                                {{ $step['done'] ? 'Tinjau' : 'Buka' }}
                            </a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if (! empty($stepsOpsional))
            {{-- Importer historis — untuk migrasi tengah tahun dari sistem lama.
                 Skenario 'migrasi' (#350 poin 2) → seksi ini DITONJOLKAN (gaya normal,
                 disarankan); skenario 'baru' → tetap redup/terlipat seperti semula. --}}
            <div class="mt-4 {{ $skenarioMigrasi ? 'rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/10' : '' }}">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold {{ $skenarioMigrasi ? 'text-emerald-900 dark:text-emerald-100' : 'text-stone-700 dark:text-stone-200' }}">
                        {{ $skenarioMigrasi ? 'Data historis dari sistem lama' : 'Data historis (opsional)' }}
                    </span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold
                        {{ $skenarioMigrasi
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' }}">
                        {{ $skenarioMigrasi ? 'Disarankan untuk skenario migrasi Anda' : 'Hanya untuk migrasi tengah tahun' }}
                    </span>
                </div>
                <p class="mt-1 text-xs {{ $skenarioMigrasi ? 'text-emerald-800/80 dark:text-emerald-200/70' : 'text-stone-500 dark:text-stone-400' }}">
                    @if ($skenarioMigrasi)
                        Anda memilih skenario <strong>migrasi tengah tahun</strong> — impor data historis di bawah
                        agar laporan menyambung dari sistem lama. Tetap tidak wajib; lewati yang tak relevan.
                    @else
                        Pesantren baru yang mulai dari nol boleh <strong>melewati semua langkah ini</strong>.
                        Isi hanya bila pindahan dari sistem/pembukuan lama di tengah tahun ajaran.
                    @endif
                </p>
                <ul class="mt-2 space-y-2">
                    @foreach ($stepsOpsional as $step)
                        <li>
                            <div class="flex items-center gap-3 rounded-xl border px-4 py-3 {{ $skenarioMigrasi ? '' : 'border-dashed' }}
                                {{ $step['done']
                                    ? 'border-emerald-200 bg-white/70 dark:border-emerald-900/40 dark:bg-white/5'
                                    : ($skenarioMigrasi
                                        ? 'border-stone-200 bg-white dark:border-white/10 dark:bg-white/5'
                                        : 'border-stone-300 bg-stone-50/60 dark:border-white/10 dark:bg-white/5') }}">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full
                                    {{ $step['done'] ? 'bg-emerald-600 text-white' : 'bg-stone-100 text-stone-400 dark:bg-white/10 dark:text-stone-400' }}">
                                    <x-filament::icon :icon="$step['done'] ? 'heroicon-m-check' : 'heroicon-o-archive-box-arrow-down'" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold {{ $step['done'] ? 'text-stone-400 dark:text-stone-500' : 'text-stone-800 dark:text-stone-100' }}">
                                        {{ $step['label'] }}
                                        <span class="ml-1 rounded bg-stone-200/70 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-stone-500 dark:bg-white/10 dark:text-stone-400">Opsional</span>
                                    </span>
                                    <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $step['desc'] }}</span>
                                </span>
                                @if ($step['url'])
                                    <a href="{{ $step['url'] }}"
                                       class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold
                                           {{ $skenarioMigrasi && ! $step['done']
                                               ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                               : 'text-stone-500 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-white/10' }}">
                                        {{ $step['done'] ? 'Tinjau' : 'Buka' }}
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Gate verifikasi "siap go-live": langkah wajib + cek TenantHealthCheck relevan. --}}
        @php
            $vWarna = match ($verifikasi['status']) {
                'hijau' => ['border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/50 dark:bg-emerald-950/20', 'text-emerald-800 dark:text-emerald-200', 'heroicon-m-check-badge', 'Siap go-live — verifikasi hijau'],
                'merah' => ['border-red-300 bg-red-50/70 dark:border-red-900/50 dark:bg-red-950/20', 'text-red-800 dark:text-red-200', 'heroicon-m-x-circle', 'Belum siap go-live — ada cek kesehatan GAGAL'],
                default => ['border-amber-300 bg-amber-50/60 dark:border-amber-800/50 dark:bg-amber-950/20', 'text-amber-800 dark:text-amber-200', 'heroicon-m-exclamation-triangle', 'Belum siap go-live — lengkapi langkah wajib'],
            };
        @endphp
        <div class="mt-4 rounded-xl border px-4 py-3 {{ $vWarna[0] }}">
            <p class="flex items-center gap-2 text-sm font-semibold {{ $vWarna[1] }}">
                <x-filament::icon :icon="$vWarna[2]" class="h-5 w-5" />
                {{ $vWarna[3] }}
            </p>
            <ul class="mt-2 space-y-1">
                @foreach ($verifikasi['items'] as $item)
                    <li class="flex items-start gap-2 text-xs {{ $vWarna[1] }}">
                        <x-filament::icon :icon="$item['ok'] ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'"
                            class="mt-0.5 h-4 w-4 shrink-0 {{ $item['ok'] ? 'text-emerald-600' : 'text-red-500' }}" />
                        <span><span class="font-medium">{{ $item['label'] }}</span> — {{ $item['detail'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($progress['tuntas'])
            <div class="mt-2 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/20 dark:text-emerald-200">
                <x-filament::icon icon="heroicon-m-check-badge" class="mr-1 inline h-5 w-5" />
                Unit {{ $label }} sudah lengkap. 🎉
            </div>
        @endif
    @endif
</x-filament-panels::page>
