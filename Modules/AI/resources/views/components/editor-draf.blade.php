{{--
    T-A090 — <x-ai::editor-draf> : kerangka editor draf hasil AI dengan
    tombol "Sahkan" (human-in-the-loop, pagar #3: AI menyusun draf,
    MANUSIA yang mengesahkan).

    - Slot default : isi editor milik pemakai (textarea / richtext sederhana).
    - aksi         : nama handler Livewire untuk tombol Sahkan
                     (dirender sebagai wire:click="..."). Null = tanpa tombol bawaan.
    - Slot tombol  : alternatif — bawa tombol sendiri (mis. <x-filament::button>).
    - Badge DRAF otomatis + keterangan pagar selalu tampil.
--}}
@props([
    'judul' => 'Draf dari asisten',
    'aksi' => null,
    'labelAksi' => 'Sahkan',
])

<div {{ $attributes->merge(['class' => 'ai-editor-draf rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $judul }}</h3>
        <x-ai::badge-ai varian="draf" />
    </div>

    <div class="p-3">
        {{ $slot }}
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 p-3 dark:border-gray-700">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            AI hanya menyusun draf; Anda yang memutuskan.
        </p>

        @if ($aksi !== null)
            <button
                type="button"
                wire:click="{{ $aksi }}"
                class="ai-tombol-sahkan inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                style="background-color: #14532d;"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                {{ $labelAksi }}
            </button>
        @elseif (isset($tombol))
            {{ $tombol }}
        @endif
    </div>
</div>
