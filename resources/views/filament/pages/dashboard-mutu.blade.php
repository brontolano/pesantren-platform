<x-filament-panels::page>
    @php($s = $ringkasan['sasaran'])
    @php($t = $ringkasan['temuan'])
    @php($k = $ringkasan['skm'])
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-filament::section>
            <x-slot name="heading">Sasaran / KPI</x-slot>
            <div class="text-3xl font-bold text-primary-600">{{ number_format($s['capaian_rata'], 1) }}%</div>
            <p class="text-sm text-gray-500">Rata-rata capaian dari {{ $s['jumlah'] }} sasaran aktif</p>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    @forelse ($s['per_area'] as $area => $capaian)
                        <tr><td class="py-1 text-gray-500">{{ ucfirst($area) }}</td>
                            <td class="py-1 text-right font-medium">{{ number_format($capaian, 1) }}%</td></tr>
                    @empty
                        <tr><td class="py-1 text-gray-400">Belum ada sasaran.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Temuan (Non-Conformance)</x-slot>
            <div class="text-3xl font-bold text-danger-600">{{ $t['terbuka'] }}</div>
            <p class="text-sm text-gray-500">Temuan masih terbuka ({{ $t['tutup'] }} sudah ditutup)</p>
            <table class="mt-3 w-full text-sm">
                <tbody>
                    @foreach (['terbuka' => 'Terbuka', 'tindakan' => 'Tindakan', 'verifikasi' => 'Verifikasi', 'tutup' => 'Tutup'] as $key => $label)
                        <tr><td class="py-1 text-gray-500">{{ $label }}</td>
                            <td class="py-1 text-right font-medium">{{ $t['per_status'][$key] ?? 0 }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Indeks SKM (Kepuasan Wali)</x-slot>
            <div class="text-3xl font-bold text-success-600">{{ number_format($k['indeks'], 1) }}</div>
            <p class="text-sm text-gray-500">Skala 0–100 (rata-rata {{ number_format($k['rata'], 2) }} dari 5)</p>
            <p class="mt-3 text-sm text-gray-500">{{ $k['jumlah_responden'] }} responden</p>
        </x-filament::section>
    </div>

    <p class="mt-4 text-xs text-gray-400">
        Acuan SPMI/KMA 939. Capaian, temuan, dan SKM diperbarui dari modul Mutu secara langsung.
    </p>
</x-filament-panels::page>
