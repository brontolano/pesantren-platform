{{--
    T-A090 — <x-ai::badge-ai> : badge kecil penanda jawaban AI (pagar persepsi).

    Varian:
      - agama (default) : "Jawaban AI — bukan fatwa/keputusan"
      - data            : "Jawaban AI — dihasilkan sistem"
      - draf            : "DRAF — perlu pengesahan"

    Warna memakai kelas Tailwind + varian dark: (kontras AA di kedua mode) —
    Modules/AI/resources/views/components dipindai @source di semua build
    theme.css sehingga kelasnya pasti ter-compile.
--}}
@props([
    'varian' => 'agama',
])

@php
    $teks = match ($varian) {
        'data' => 'Jawaban AI — dihasilkan sistem',
        'draf' => 'DRAF — perlu pengesahan',
        default => 'Jawaban AI — bukan fatwa/keputusan',
    };

    // Pasangan latar/teks lolos kontras WCAG AA (≥ 4,5:1) di light & dark.
    $warna = match ($varian) {
        // amber — menonjol: draf perlu tindakan.
        'draf' => 'border-amber-300 bg-amber-100 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-300',
        // slate — netral informatif.
        default => 'border-slate-300 bg-slate-100 text-slate-800 dark:border-slate-500/40 dark:bg-slate-500/15 dark:text-slate-200',
    };
@endphp

<span
    {{ $attributes->merge(['class' => "ai-badge inline-flex min-h-6 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium leading-none {$warna}"]) }}
    role="note"
    aria-label="{{ $teks }}"
>
    <svg aria-hidden="true" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v2m0 14v2m9-9h-2M5 12H3m13.66-6.34-1.41 1.41M8.75 15.25l-1.41 1.41m9.32 0-1.41-1.41M8.75 8.75 7.34 7.34" />
        <circle cx="12" cy="12" r="3.25" />
    </svg>
    {{ $teks }}
</span>
