<x-filament-panels::page>

    {{-- Kartu ringkasan semua santri aktif periode ini --}}
    @php $stats = $this->stats; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

        <div class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950 dark:border-red-800 p-4 flex flex-col gap-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-red-500 dark:text-red-400">
                ⊕ Total Poin Pelanggaran
            </span>
            <span class="text-3xl font-bold text-red-600 dark:text-red-300">
                +{{ number_format($stats['pelanggaran']) }}
            </span>
            <span class="text-xs text-red-400 dark:text-red-500">semua santri aktif, periode ini</span>
        </div>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:bg-emerald-950 dark:border-emerald-800 p-4 flex flex-col gap-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-emerald-500 dark:text-emerald-400">
                ⊖ Total Poin Prestasi
            </span>
            <span class="text-3xl font-bold text-emerald-600 dark:text-emerald-300">
                -{{ number_format($stats['prestasi']) }}
            </span>
            <span class="text-xs text-emerald-400 dark:text-emerald-500">penghargaan & capaian baik</span>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-700 p-4 flex flex-col gap-1">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                = Total Bersih
            </span>
            <span class="text-3xl font-bold {{ $stats['net'] > 0 ? 'text-orange-600 dark:text-orange-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                {{ $stats['net'] > 0 ? '+' : '' }}{{ number_format($stats['net']) }}
            </span>
            <span class="text-xs text-gray-400">pelanggaran dikurangi prestasi</span>
        </div>

    </div>

    {{ $this->table }}

</x-filament-panels::page>
