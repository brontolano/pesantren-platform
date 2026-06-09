<x-filament-panels::page>
    @php($p = $hasil['pph21'])
    @php($b = $hasil['bpjs'])
    <div class="space-y-6">
        {{ $this->form }}

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">PPh Pasal 21 (PTKP-tiered, UU HPP)</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        <tr><td class="py-1 text-gray-500">Bruto setahun</td><td class="py-1 text-right">Rp {{ number_format($p['bruto_tahunan'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-1 text-gray-500">Biaya jabatan (5%, maks 6jt)</td><td class="py-1 text-right">− Rp {{ number_format($p['biaya_jabatan'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-1 text-gray-500">PTKP</td><td class="py-1 text-right">− Rp {{ number_format($p['ptkp'], 0, ',', '.') }}</td></tr>
                        <tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-1 font-medium">PKP</td><td class="py-1 text-right font-medium">Rp {{ number_format($p['pkp'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-1 text-gray-500">PPh21 setahun</td><td class="py-1 text-right">Rp {{ number_format($p['pph21_tahunan'], 0, ',', '.') }}</td></tr>
                        <tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-2 font-bold text-primary-600">PPh21 / bulan</td><td class="py-2 text-right font-bold text-primary-600">Rp {{ number_format($p['pph21_bulanan'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Potongan BPJS (porsi pekerja)</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        <tr><td class="py-1 text-gray-500">JKN / Kesehatan</td><td class="py-1 text-right">Rp {{ number_format($b['kesehatan'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-1 text-gray-500">JHT</td><td class="py-1 text-right">Rp {{ number_format($b['jht'], 0, ',', '.') }}</td></tr>
                        <tr><td class="py-1 text-gray-500">JP</td><td class="py-1 text-right">Rp {{ number_format($b['jp'], 0, ',', '.') }}</td></tr>
                        <tr class="border-t border-gray-200 dark:border-gray-700"><td class="py-2 font-bold">Total BPJS / bulan</td><td class="py-2 text-right font-bold">Rp {{ number_format($b['total'], 0, ',', '.') }}</td></tr>
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-gray-400">Tarif konfigurabel via Pengaturan. Lembaga syariah dapat memilih akad wakalah/ta'awun.</p>
            </x-filament::section>
        </div>

        <p class="text-xs text-warning-600">
            ⚠️ Perhitungan menyederhanakan (tanpa TER PMK 168/2023, surcharge non-NPWP, atau cap upah JP) dan
            <strong>WAJIB ditinjau penasihat pajak</strong> sebelum dipakai resmi.
        </p>
    </div>
</x-filament-panels::page>
