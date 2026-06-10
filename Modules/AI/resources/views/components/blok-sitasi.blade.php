{{--
    T-A090 — <x-ai::blok-sitasi :sitasi="[...]"> : daftar sumber jawaban.

    Menerima bentuk sitasi dari AiGenerateResult->sitasi
    ({judul, bab, halaman, label}) maupun baris model AiCitation
    (judul/bab/halaman). Bila 'label' tersedia dipakai apa adanya
    (sudah diformat deterministik oleh RagAnswerer); selain itu disusun
    "Judul — Bab, hlm. N".

    Daftar kosong → komponen TIDAK merender apa pun.
--}}
@props([
    'sitasi' => [],
])

@php
    $daftar = collect($sitasi)
        ->map(fn ($s) => is_object($s) && method_exists($s, 'toArray') ? $s->toArray() : (array) $s)
        ->map(function (array $s): string {
            $label = trim((string) ($s['label'] ?? ''));

            if ($label !== '') {
                return $label;
            }

            $bagian = trim((string) ($s['judul'] ?? ''));

            if (! empty($s['bab'])) {
                $bagian .= ' — '.$s['bab'];
            }

            if (isset($s['halaman']) && $s['halaman'] !== null && $s['halaman'] !== '') {
                $bagian .= ', hlm. '.$s['halaman'];
            }

            return trim($bagian);
        })
        ->filter(fn (string $label): bool => $label !== '')
        ->values();
@endphp

@if ($daftar->isNotEmpty())
    <section
        {{ $attributes->merge(['class' => 'ai-blok-sitasi rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40']) }}
        aria-label="Sumber jawaban"
    >
        <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Sumber
        </h4>
        <ol class="list-decimal space-y-0.5 pl-5 text-xs text-gray-600 dark:text-gray-300">
            @foreach ($daftar as $label)
                <li>{{ $label }}</li>
            @endforeach
        </ol>
    </section>
@endif
