{{--
    T-A090 — <x-ai::indikator-kredit :terpakai :anggaran> : bar kecil
    persentase pemakaian kredit AI bulan berjalan + teks santun.

    - terpakai : kredit terpakai periode berjalan (AiTenantBudget.terpakai_periode).
    - anggaran : anggaran kredit bulanan; null/0 = "tanpa batas anggaran"
                 (AiTenantBudget.anggaran_kredit_bulanan nullable).

    Warna bar mengikuti ambang: hijau brand (#14532d) normal, amber ≥ 80%
    (ambang alert default), merah ≥ 100% (kill-switch anggaran).
--}}
@props([
    'terpakai' => 0,
    'anggaran' => null,
])

@php
    $terpakai = max(0.0, (float) $terpakai);
    $tanpaBatas = $anggaran === null || (float) $anggaran <= 0;
    $persen = $tanpaBatas ? null : round($terpakai / (float) $anggaran * 100, 1);
    $lebarBar = $persen === null ? 0 : min(100.0, $persen);
    $warnaBar = match (true) {
        $persen !== null && $persen >= 100 => '#b91c1c', // red-700
        $persen !== null && $persen >= 80 => '#b45309',  // amber-700
        default => '#14532d',                            // Forest Green brand
    };
    $angka = fn ($n): string => number_format((float) $n, 0, ',', '.');
    $persenLabel = $persen === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format($persen, 1, '.', ''), '0'), '.'));
@endphp

<div {{ $attributes->merge(['class' => 'ai-indikator-kredit space-y-1.5']) }}>
    @if ($tanpaBatas)
        <p class="text-xs text-gray-600 dark:text-gray-300">
            Kredit AI terpakai bulan ini: <strong>{{ $angka($terpakai) }}</strong> — tanpa batas anggaran.
        </p>
    @else
        <div
            class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $lebarBar }}"
            aria-label="Pemakaian kredit AI {{ $persenLabel }} persen dari anggaran bulanan"
        >
            <div class="h-full rounded-full" style="width: {{ $lebarBar }}%; background-color: {{ $warnaBar }};"></div>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-300">
            Kredit AI terpakai {{ $angka($terpakai) }} dari {{ $angka($anggaran) }} ({{ $persenLabel }}%).
            @if ($persen >= 100)
                Mohon maaf, anggaran bulan ini telah habis — silakan hubungi admin untuk top-up.
            @endif
        </p>
    @endif
</div>
