{{--
    T-A090 — <x-ai::badge-ai> : badge kecil penanda jawaban AI (pagar persepsi).

    Varian:
      - agama (default) : "Jawaban AI — bukan fatwa/keputusan"
      - data            : "Jawaban AI — dihasilkan sistem"
      - draf            : "DRAF — perlu pengesahan"

    Warna memakai token eksplisit (inline style) supaya kontras AA terjamin
    di mana pun komponen dipakai (Filament/Portal) tanpa bergantung pada
    kelas warna Tailwind yang belum tentu ter-compile dari Modules/*.
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

    // Pasangan latar/teks lolos kontras WCAG AA (≥ 4,5:1).
    $warna = match ($varian) {
        // amber-100 / amber-900 / amber-300 — menonjol: draf perlu tindakan.
        'draf' => ['latar' => '#fef3c7', 'teks' => '#78350f', 'garis' => '#fcd34d'],
        // slate-100 / slate-800 / slate-300 — netral informatif.
        default => ['latar' => '#f1f5f9', 'teks' => '#1e293b', 'garis' => '#cbd5e1'],
    };
@endphp

<span
    {{ $attributes->merge(['class' => 'ai-badge inline-flex min-h-6 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium leading-none']) }}
    style="background-color: {{ $warna['latar'] }}; color: {{ $warna['teks'] }}; border-color: {{ $warna['garis'] }};"
    role="note"
    aria-label="{{ $teks }}"
>
    <svg aria-hidden="true" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3v2m0 14v2m9-9h-2M5 12H3m13.66-6.34-1.41 1.41M8.75 15.25l-1.41 1.41m9.32 0-1.41-1.41M8.75 8.75 7.34 7.34" />
        <circle cx="12" cy="12" r="3.25" />
    </svg>
    {{ $teks }}
</span>
