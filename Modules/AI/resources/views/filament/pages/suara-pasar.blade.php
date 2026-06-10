<x-filament-panels::page>
    @php($r = $this->ringkasan())

    {{-- Periode + catatan privasi --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3 p-4">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                    Suara Pasar — {{ $r['periode_label'] }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Agregat lintas-tenant dari telemetri riset <strong>ber-opt-in</strong> (PRD §6.4):
                    tanpa isi percakapan, tanpa PII, tanpa identitas tenant per baris data.
                    Izin dapat dicabut kapan saja — data tenant tersebut ikut terhapus.
                </p>
            </div>
            <select
                wire:model.live="periode"
                class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                @foreach ($this->pilihanPeriode() as $nilai => $label)
                    <option value="{{ $nilai }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Kartu ringkas --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Tenant opt-in riset</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ $r['tenant_optin'] }} <span class="text-base font-normal text-gray-400">/ {{ $r['tenant_total'] }} tenant</span>
            </p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Rata-rata rating jawaban</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ $r['rata_rata_rating'] !== null ? number_format($r['rata_rata_rating'], 2, ',', '.').' / 5' : '—' }}
            </p>
            <p class="text-xs text-gray-400">{{ $r['jumlah_rating'] }} rating bulan ini</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">Eskalasi Tanya Ustadz (event)</p>
            <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ array_sum($r['top_kategori_eskalasi']) }}
            </p>
            <p class="text-xs text-gray-400">dari tenant opt-in, bulan terpilih</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        {{-- Distribusi preset manhaj --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Distribusi preset manhaj</h3>
            @if (empty($r['distribusi_preset']))
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada snapshot konfigurasi.</p>
            @else
                <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($r['distribusi_preset'] as $preset => $jumlah)
                        <li class="flex justify-between gap-4">
                            <span>{{ $preset }}</span>
                            <span class="font-medium">{{ $jumlah }} tenant</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Distribusi level fatwa --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Distribusi level kehati-hatian</h3>
            @if (empty($r['distribusi_level']))
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada snapshot konfigurasi.</p>
            @else
                <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($r['distribusi_level'] as $level => $jumlah)
                        <li class="flex justify-between gap-4">
                            <span>{{ $level }}</span>
                            <span class="font-medium">{{ $jumlah }} tenant</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Top 5 kategori eskalasi --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Top 5 kategori eskalasi</h3>
            @if (empty($r['top_kategori_eskalasi']))
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada eskalasi pada bulan ini.</p>
            @else
                <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($r['top_kategori_eskalasi'] as $kategori => $jumlah)
                        <li class="flex justify-between gap-4">
                            <span>{{ $kategori }}</span>
                            <span class="font-medium">{{ $jumlah }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Fitur yang diaktifkan --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Fitur aktif (peran.fitur)</h3>
            @if (empty($r['fitur_aktif']))
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada fitur yang diaktifkan tenant opt-in.</p>
            @else
                <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($r['fitur_aktif'] as $fitur => $jumlah)
                        <li class="flex justify-between gap-4">
                            <span>{{ $fitur }}</span>
                            <span class="font-medium">{{ $jumlah }} tenant</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <p class="text-xs text-gray-400 dark:text-gray-500">
        Catatan: jumlah tenant platform masih kecil — agregat ber-n kecil tetap diperlakukan
        rahasia internal (jangan diekspor keluar tim tanpa ambang n minimum). Dashboard ini
        melengkapi, bukan menggantikan, 5–10 wawancara mendalam per kuartal (PRD §6.4).
    </p>
</x-filament-panels::page>
