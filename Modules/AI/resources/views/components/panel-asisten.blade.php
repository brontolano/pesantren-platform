{{--
    T-A090 — <x-ai::panel-asisten> : kerangka panel form-first untuk fitur
    generator AI (PRD §3: bukan chat kosong — formulir dengan default aman).

    Slot/prop:
      - judul, deskripsi : kepala panel (bahasa manusia, tanpa jargon).
      - slot default     : FORM fitur (form-first).
      - slot lanjutan    : "Pengaturan lanjutan" — progressive disclosure,
                           collapsed default (Alpine — sudah dibawa Filament).
      - slot status      : area status async ("sedang disusun…"); ber-aria-live.
                           Bila dipakai dalam Livewire, bungkus dengan wire:poll
                           di sisi pemakai — komponen ini TIDAK memaksa polling.
      - slot hasil       : hasil generate — badge AI + blok sitasi otomatis
                           (prop varianBadge + sitasi dari AiGenerateResult).
      - slot kredit      : footer indikator kredit (<x-ai::indikator-kredit>).
--}}
@props([
    'judul' => 'Asisten AI',
    'deskripsi' => null,
    'varianBadge' => 'data',
    'sitasi' => [],
])

@php($idLanjutan = 'ai-lanjutan-'.substr(md5((string) $judul), 0, 8))

<section
    {{ $attributes->merge(['class' => 'ai-panel-asisten rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}
    aria-label="{{ $judul }}"
>
    <header class="border-b border-gray-200 p-4 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ $judul }}</h2>
        @if ($deskripsi)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $deskripsi }}</p>
        @endif
    </header>

    {{-- Form-first: slot utama adalah formulir fitur --}}
    <div class="p-4">
        {{ $slot }}
    </div>

    {{-- Progressive disclosure: pengaturan lanjutan tersembunyi default --}}
    @isset($lanjutan)
        <div class="border-t border-gray-100 px-4 py-2 dark:border-gray-700/50" x-data="{ buka: false }">
            <button
                type="button"
                class="inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-300"
                x-on:click="buka = !buka"
                x-bind:aria-expanded="buka.toString()"
                aria-expanded="false"
                aria-controls="{{ $idLanjutan }}"
            >
                <svg aria-hidden="true" class="h-4 w-4 transition-transform" x-bind:class="buka ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                Pengaturan lanjutan
            </button>
            <div id="{{ $idLanjutan }}" x-show="buka" x-cloak class="pb-2 pt-1">
                {{ $lanjutan }}
            </div>
        </div>
    @endisset

    {{-- Area status async (mis. "Sedang disusun, mohon menunggu…") --}}
    @isset($status)
        <div class="border-t border-gray-100 p-4 text-sm text-gray-500 dark:border-gray-700/50 dark:text-gray-400" role="status" aria-live="polite">
            {{ $status }}
        </div>
    @endisset

    {{-- Hasil: badge + sitasi otomatis --}}
    @isset($hasil)
        <div class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-700">
            <x-ai::badge-ai :varian="$varianBadge" />
            <div class="text-sm text-gray-700 dark:text-gray-200">
                {{ $hasil }}
            </div>
            <x-ai::blok-sitasi :sitasi="$sitasi" />
        </div>
    @endisset

    {{-- Footer indikator kredit --}}
    @isset($kredit)
        <footer class="rounded-b-xl border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
            {{ $kredit }}
        </footer>
    @endisset
</section>
