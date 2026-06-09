<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        {{-- Hasil perhitungan (otomatis saat parameter berubah) --}}
        <x-filament::section>
            <x-slot name="heading">Hasil Perhitungan</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">DPP</div>
                    <div class="mt-1 text-xl font-semibold">Rp {{ number_format($hasil['dpp'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">PPN</div>
                    <div class="mt-1 text-xl font-semibold">Rp {{ number_format($hasil['ppn'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        PPh @if($hasil['pph_jenis'])<span class="text-xs">({{ $hasil['pph_jenis'] }})</span>@endif
                    </div>
                    <div class="mt-1 text-xl font-semibold">Rp {{ number_format($hasil['pph'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total (DPP + PPN)</div>
                    <div class="mt-1 text-xl font-bold text-primary-600">Rp {{ number_format($hasil['total'], 0, ',', '.') }}</div>
                </div>
            </div>

            @if (($hasil['ppn'] + $hasil['pph']) == 0 && $hasil['dpp'] > 0)
                <p class="mt-4 text-sm text-warning-600">
                    Pajak negara 0 — bisa karena dana syubhat/non-halal (G1-04), dana BOS dikecualikan,
                    di bawah ambang, atau tarif belum diatur. Cek menu <strong>Tarif Pajak</strong>.
                </p>
            @endif

            <p class="mt-4 text-xs text-gray-400">
                Mengikuti {{ \Modules\Pajak\Services\MesinPajakService::DASAR_REGULASI }}.
                Tarif dapat diubah di menu Tarif Pajak. Wajib ditinjau penasihat pajak sebelum dipakai resmi.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
